<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\Credencial;
use App\Models\Estudio;
use App\Models\MovimientoDetraccion;
use App\Models\Operacion;
use App\Models\SaldoDetraccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CuentaDetraccionTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $admin;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::factory()->create(['nombre' => 'Estudio Contable Taya']);
        $this->admin = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);

        $this->cliente = Cliente::factory()->paraEstudio($this->estudio)->create([
            'razon_social' => 'TAYA YUCRA EDGAR JUAN',
            'ruc' => '10452143998',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        Credencial::create([
            'cliente_id' => $this->cliente->id,
            'cuenta_detracciones' => '00129002484',
            'clave_detracciones' => '123456',
        ]);
    }

    public function test_la_pantalla_de_cuenta_detracciones_responde_con_extracto_oficial(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('clientes.detracciones', $this->cliente));

        $response->assertOk();
        $response->assertSee('Banco de la Nación', false);
        $response->assertSee('Cuenta SPOT', false);
        $response->assertSee('00129002484', false);
        $response->assertSee('data-seccion="cuenta-detracciones"', false);
        $response->assertSee('Cargar Detracción Oficial', false);
        $response->assertSee('Saldo Inicial', false);
    }

    public function test_registro_y_arrastre_de_saldo_inicial(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('clientes.detracciones.saldo-inicial', $this->cliente), [
            'anio' => 2026,
            'saldo_inicial' => '2,500.50',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('saldos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'anio' => 2026,
            'saldo_inicial' => 2500.50,
        ]);

        $extracto = $this->get(route('clientes.detracciones', [
            'cliente' => $this->cliente,
            'anio' => 2026,
        ]));

        $extracto->assertOk();
        $extracto->assertSee('2,500.50');
    }

    public function test_registro_manual_de_movimiento_abono_y_cargo(): void
    {
        $this->actingAs($this->admin);

        // Registro de abono (depósito)
        $respAbono = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'abono',
            'fecha' => '2026-05-15',
            'monto' => '1,450.00',
            'descripcion' => 'Factura de Venta E001-45',
            'numero_constancia' => '2026051512',
            'numero_operacion' => '987654',
            'comprobante_referencia' => 'E001-45',
        ]);

        $respAbono->assertOk();
        $respAbono->assertJson(['success' => true]);

        $this->assertDatabaseHas('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'tipo' => 'deposito',
            'monto' => 1450.00,
            'descripcion' => 'Factura de Venta E001-45',
            'numero_constancia' => '2026051512',
            'origen' => 'manual',
        ]);

        // Registro de cargo (retiro manual)
        $respCargo = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'cargo',
            'fecha' => '2026-05-20',
            'monto' => '450.00',
            'descripcion' => 'Comisión de Chequera BN',
            'numero_operacion' => '112233',
        ]);

        $respCargo->assertOk();
        $this->assertDatabaseHas('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'tipo' => 'retiro',
            'monto' => 450.00,
            'descripcion' => 'Comisión de Chequera BN',
            'origen' => 'manual',
        ]);
    }

    public function test_sincronizacion_automatica_con_control_de_impuestos_al_pagar_con_detracciones(): void
    {
        $this->actingAs($this->admin);

        // Saldo inicial de 3,000 en 2026
        SaldoDetraccion::create([
            'cliente_id' => $this->cliente->id,
            'anio' => 2026,
            'saldo_inicial' => 3000.00,
        ]);

        // Operación fiscal
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '05-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 850.00,
            'vencimiento_deuda' => '2026-06-20',
        ]);

        // Registrar abono de impuesto pagado con cuenta de detracciones
        $abono = AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'monto' => 850.00,
            'fecha_pago' => '2026-06-18',
            'medio_pago' => 'detracciones',
            'numero_operacion' => 'BN-889911',
            'observacion' => 'Pago IGV con cuenta SPOT',
        ]);

        // El hook debe haber creado automáticamente el cargo/retiro en movimientos_detracciones
        $movimiento = MovimientoDetraccion::query()->where('abono_deuda_id', $abono->id)->first();
        $this->assertNotNull($movimiento, 'El movimiento de detracción debió sincronizarse automáticamente');
        $this->assertSame('retiro', $movimiento->tipo);
        $this->assertSame('control_impuestos', $movimiento->origen);
        $this->assertEquals(850.00, $movimiento->monto);
        $this->assertSame($this->cliente->id, $movimiento->cliente_id);
        $this->assertStringContainsString('05-2026', $movimiento->descripcion);

        // Si actualizamos el monto en Control de Impuestos, se actualiza el movimiento
        $abono->update(['monto' => 900.00]);
        $movimiento->refresh();
        $this->assertEquals(900.00, $movimiento->monto);

        // Si eliminamos el abono en Control de Impuestos, se elimina el movimiento de detracción
        $abono->delete();
        $this->assertDatabaseMissing('movimientos_detracciones', [
            'id' => $movimiento->id,
        ]);
    }

    public function test_lectura_en_memoria_de_pdf_oficial_sin_guardar_archivos(): void
    {
        $this->actingAs($this->admin);

        $pdfPath = base_path('E001-45 DETRACCIÓN.pdf');
        $this->assertFileExists($pdfPath, 'El archivo de prueba oficial debe existir en la raíz');

        $uploadedFile = new UploadedFile(
            $pdfPath,
            'E001-45 DETRACCIÓN.pdf',
            'application/pdf',
            null,
            true
        );

        $response = $this->postJson(route('clientes.detracciones.cargar-pdf', $this->cliente), [
            'archivos' => [$uploadedFile],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json('resultados.0.resultado.datos');
        $this->assertNotNull($data);
        $this->assertNotEmpty($data['numero_constancia']);
        $this->assertNotEmpty($data['numero_operacion']);
        $this->assertNotEmpty($data['fecha_pago']);
        $this->assertGreaterThan(0, $data['monto']);
        $this->assertSame('E001-00000045', $data['comprobante_numero']);
        $this->assertStringContainsString('E001-45', $data['glosa_sugerida']);

        // Verificar que no se guardó ningún archivo en storage público ni en carpetas temporales
        $this->assertFalse(Storage::disk('public')->exists('E001-45 DETRACCIÓN.pdf'));
    }

    public function test_impresion_de_extracto_bancario_oficial_responde_200(): void
    {
        $this->actingAs($this->admin);

        // Agregar un movimiento para verificar renderizado
        MovimientoDetraccion::create([
            'cliente_id' => $this->cliente->id,
            'tipo' => 'deposito',
            'fecha' => '2026-05-15',
            'monto' => 1200.00,
            'descripcion' => 'Factura de Venta E001-45',
            'origen' => 'manual',
        ]);

        $response = $this->get(route('clientes.detracciones.imprimir', [
            'cliente' => $this->cliente,
            'anio' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('EXTRACTO OFICIAL BANCO DE LA NACIÓN', false);
        $response->assertSee('TAYA YUCRA EDGAR JUAN', false);
        $response->assertSee('1,200.00', false);
        $response->assertSee('Contador Público Colegiado', false);
        $response->assertSee('Titular', false);
    }

    public function test_exportar_extracto_en_csv_descarga_archivo_con_formato(): void
    {
        $this->actingAs($this->admin);

        MovimientoDetraccion::create([
            'cliente_id' => $this->cliente->id,
            'tipo' => 'deposito',
            'fecha' => '2026-05-15',
            'monto' => 1200.00,
            'descripcion' => 'Factura de Venta E001-45',
            'origen' => 'manual',
        ]);

        $response = $this->get(route('clientes.detracciones.exportar', [
            'cliente' => $this->cliente,
            'anio' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsStringIgnoringCase('extracto_detracciones_bn_10452143998_2026.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_aislamiento_entre_estudios_impide_acceso_a_cliente_ajeno(): void
    {
        $otroEstudio = Estudio::factory()->create(['nombre' => 'Estudio Ajeno']);
        $otroAdmin = User::factory()->create([
            'estudio_id' => $otroEstudio->id,
            'rol' => 'admin',
        ]);

        $this->actingAs($otroAdmin);

        // Intentar acceder a detracciones del cliente de otro estudio
        $response = $this->get(route('clientes.detracciones', $this->cliente));
        $response->assertNotFound();

        // Intentar registrar movimiento en cliente de otro estudio
        $respPost = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'abono',
            'fecha' => '2026-05-15',
            'monto' => '500.00',
            'descripcion' => 'Intrusión',
        ]);
        $respPost->assertNotFound();
    }

    public function test_lectura_de_constancia_pdf_e001_35_con_fuentes_cmap_complejas(): void
    {
        $this->actingAs($this->admin);

        $pdfPath = base_path('E001-35 DETRACCIÓN.pdf');
        $this->assertFileExists($pdfPath, 'El archivo de prueba E001-35 DETRACCIÓN.pdf debe existir en la raíz');

        $uploadedFile = new UploadedFile(
            $pdfPath,
            'E001-35 DETRACCIÓN.pdf',
            'application/pdf',
            null,
            true
        );

        $response = $this->postJson(route('clientes.detracciones.cargar-pdf', $this->cliente), [
            'archivos' => [$uploadedFile],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json('resultados.0.resultado.datos');
        $this->assertNotNull($data);
        $this->assertSame('295493809', $data['numero_constancia']);
        $this->assertSame('7732140798', $data['numero_operacion']);
        $this->assertSame(6509.00, (float) $data['monto']);
        $this->assertSame('E001-00000035', $data['comprobante_numero']);
        $this->assertSame('2026-01-16', $data['fecha_pago']);
        $this->assertStringContainsString('E001-35', $data['glosa_sugerida']);
    }

    public function test_consulta_saldo_sugerido_y_arrastre_automatico_entre_ejercicios(): void
    {
        $this->actingAs($this->admin);

        // En 2026 configuramos saldo inicial de 1,000 y registramos depósito de 500 y retiro de 200 => Cierre 2026 = 1,300
        SaldoDetraccion::create([
            'cliente_id' => $this->cliente->id,
            'anio' => 2026,
            'saldo_inicial' => 1000.00,
        ]);

        MovimientoDetraccion::create([
            'cliente_id' => $this->cliente->id,
            'tipo' => 'deposito',
            'fecha' => '2026-02-10',
            'monto' => 500.00,
            'descripcion' => 'Abono febrero',
            'origen' => 'manual',
        ]);

        MovimientoDetraccion::create([
            'cliente_id' => $this->cliente->id,
            'tipo' => 'retiro',
            'fecha' => '2026-03-15',
            'monto' => 200.00,
            'descripcion' => 'Cargo marzo',
            'origen' => 'manual',
        ]);

        // Consultamos el saldo sugerido para 2027
        $response = $this->getJson(route('clientes.detracciones.saldo-sugerido', [
            'cliente' => $this->cliente,
            'anio' => 2027,
        ]));

        $response->assertOk();
        $response->assertJson([
            'anio' => 2027,
            'anio_anterior' => 2026,
            'saldo_sugerido' => 1300.00,
        ]);

        // Al visitar el extracto de 2027 sin haber guardado un registro explícito en saldos_detracciones,
        // el saldo inicial debe arrastrarse automáticamente con 1,300.00
        $extracto2027 = $this->get(route('clientes.detracciones', [
            'cliente' => $this->cliente,
            'anio' => 2027,
        ]));

        $extracto2027->assertOk();
        $extracto2027->assertSee('1,300.00');
    }

    public function test_carga_pdf_detecta_discrepancia_de_periodo_y_ofrece_redireccion(): void
    {
        $this->actingAs($this->admin);

        $pdfPath = base_path('E001-35 DETRACCIÓN.pdf');
        $this->assertFileExists($pdfPath);

        $uploadedFile = new UploadedFile(
            $pdfPath,
            'E001-35 DETRACCIÓN.pdf',
            'application/pdf',
            null,
            true
        );

        // La constancia es del 16/01/2026 (Enero 2026), pero simulamos que el usuario está en Abril 2026 (mes = 4)
        $response = $this->postJson(route('clientes.detracciones.cargar-pdf', $this->cliente), [
            'archivos' => [$uploadedFile],
            'anio_contexto' => 2026,
            'mes_contexto' => 4,
        ]);

        $response->assertOk();
        $item = $response->json('resultados.0.resultado');
        $this->assertFalse($item['periodo_info']['coincide_periodo']);
        $this->assertStringContainsString('Enero 2026', $item['periodo_info']['motivo_incompatibilidad']);
        $this->assertStringContainsString('Abril 2026', $item['periodo_info']['motivo_incompatibilidad']);
        $this->assertStringContainsString('detraccion_mes=1', $item['periodo_info']['url_ir_periodo']);

        // Ahora enviamos con el mes correcto (mes = 1, Enero)
        $uploadedFileCorrecto = new UploadedFile(
            $pdfPath,
            'E001-35 DETRACCIÓN.pdf',
            'application/pdf',
            null,
            true
        );

        $responseOk = $this->postJson(route('clientes.detracciones.cargar-pdf', $this->cliente), [
            'archivos' => [$uploadedFileCorrecto],
            'anio_contexto' => 2026,
            'mes_contexto' => 1,
        ]);

        $responseOk->assertOk();
        $itemOk = $responseOk->json('resultados.0.resultado');
        $this->assertTrue($itemOk['periodo_info']['coincide_periodo']);
    }

    public function test_guardar_movimiento_rechaza_registro_fuera_del_periodo_contextual(): void
    {
        $this->actingAs($this->admin);

        // Intentar registrar un movimiento con fecha de Febrero 2026 mientras se filtra Abril 2026
        $response = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'deposito',
            'fecha' => '2026-02-15',
            'monto' => '500.00',
            'descripcion' => 'Depósito Febrero',
            'anio_contexto' => 2026,
            'mes_contexto' => 4,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'periodo_mismatch' => true,
        ]);
        $this->assertStringContainsString('Febrero 2026', $response->json('message'));
        $this->assertStringContainsString('detraccion_mes=2', $response->json('url_ir_periodo'));

        // Comprobar que no se guardó en la base de datos
        $this->assertDatabaseMissing('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'descripcion' => 'Depósito Febrero',
        ]);

        // Registrar con la fecha correspondiente al periodo activo (Abril 2026)
        $responseValido = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'deposito',
            'fecha' => '2026-04-15',
            'monto' => '500.00',
            'descripcion' => 'Depósito Abril',
            'anio_contexto' => 2026,
            'mes_contexto' => 4,
        ]);

        $responseValido->assertOk();
        $this->assertDatabaseHas('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'descripcion' => 'Depósito Abril',
        ]);
    }

    public function test_guardar_movimiento_rechaza_fechas_futuras(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'deposito',
            'fecha' => '2030-01-01',
            'monto' => '350.00',
            'descripcion' => 'Depósito Futuro Inválido',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['fecha']);
    }

    public function test_guardar_movimiento_rechaza_constancia_duplicada(): void
    {
        $this->actingAs($this->admin);

        // Primer depósito
        $resp1 = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'deposito',
            'fecha' => '2026-03-10',
            'monto' => '800.00',
            'descripcion' => 'Factura E001-10',
            'numero_constancia' => 'SPOT-998877',
        ]);
        $resp1->assertOk();

        // Segundo depósito con el mismo número de constancia
        $resp2 = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'deposito',
            'fecha' => '2026-03-10',
            'monto' => '800.00',
            'descripcion' => 'Factura E001-10 Reintento',
            'numero_constancia' => 'SPOT-998877',
        ]);

        $resp2->assertStatus(422);
        $resp2->assertJson([
            'success' => false,
            'duplicado' => true,
        ]);
    }

    public function test_guardar_movimiento_rechaza_ruc_de_tercero_en_constancia(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('clientes.detracciones.movimiento.store', $this->cliente), [
            'tipo' => 'deposito',
            'fecha' => '2026-03-15',
            'monto' => '1200.00',
            'descripcion' => 'Factura ajena',
            'origen' => 'constancia_pdf',
            'proveedor_ruc' => '20555666777', // RUC diferente al cliente
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'ruc_mismatch' => true,
        ]);
    }
}
