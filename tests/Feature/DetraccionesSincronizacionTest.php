<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\CatalogoMedioPago;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
use App\Models\MovimientoDetraccion;
use App\Models\Operacion;
use App\Models\User;
use App\Services\ControlImpuestos\RegistrarPagoDetraccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetraccionesSincronizacionTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $admin;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::factory()->create();
        $this->admin = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);

        $this->cliente = Cliente::factory()->paraEstudio($this->estudio)->create([
            'ruc' => '20601234567',
            'razon_social' => 'EMPRESA PRUEBA S.A.C.',
            'regimen_tributario' => 'RMT',
            'estado' => 'activo',
        ]);

        CatalogoMedioPago::create([
            'estudio_id' => $this->estudio->id,
            'codigo' => 'detracciones',
            'denominacion' => 'Cuenta de Detracciones Banco de la Nación',
            'activo' => true,
            'sistema' => true,
        ]);
    }

    public function test_limpiar_liquidacion_elimina_cargos_de_detracciones_sin_dejar_huerfanos(): void
    {
        $this->actingAs($this->admin);

        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
            'tiene_deuda' => true,
        ]);

        $deuda = DeudaTributaria::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '1011',
            'monto_deuda_inicial' => 5000.00,
        ]);

        $abono = AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 5000.00,
            'fecha_pago' => '2026-02-15',
            'medio_pago' => 'detracciones',
            'observacion' => 'NPS 7711223344',
        ]);

        $this->assertDatabaseHas('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'abono_deuda_id' => $abono->id,
            'tipo' => 'retiro',
            'monto' => 5000.00,
            'numero_operacion' => '7711223344',
        ]);

        // Simular que el usuario hace clic en "Limpiar" en Control de Impuestos
        $response = $this->postJson(route('control.liquidacion.limpiar'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // La deuda, el abono y el movimiento deben haber desaparecido
        $this->assertDatabaseMissing('deuda_tributarias', ['id' => $deuda->id]);
        $this->assertDatabaseMissing('abonos_deudas', ['id' => $abono->id]);
        $this->assertDatabaseMissing('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
            'origen' => 'control_impuestos',
        ]);
    }

    public function test_registrar_pago_detraccion_reemplaza_en_lugar_de_duplicar(): void
    {
        $servicio = app(RegistrarPagoDetraccion::class);

        // 1. Registro inicial (por ejemplo, primer registro o preliminar)
        $pagos1 = [
            [
                'tributo' => '1011',
                'importe' => 3500.00,
                'medio_codigo' => 'detracciones',
                'fecha' => '15/02/2026',
                'numero_operacion' => null,
            ],
        ];

        $res1 = $servicio->registrar($this->cliente, '01-2026', $pagos1);
        $this->assertSame(1, $res1['cantidad']);

        $this->assertDatabaseCount('abonos_deudas', 1);
        $this->assertDatabaseCount('movimientos_detracciones', 1);

        $mov1 = MovimientoDetraccion::where('cliente_id', $this->cliente->id)->first();
        $this->assertSame('3500.00', (string) $mov1->monto);
        $this->assertNull($mov1->numero_operacion);

        // 2. Re-importación de constancia con NPS oficial
        $pagos2 = [
            [
                'tributo' => '1011',
                'importe' => 3500.00,
                'medio_codigo' => 'detracciones',
                'fecha' => '15/02/2026',
                'numero_operacion' => '7799881122',
            ],
        ];

        $res2 = $servicio->registrar($this->cliente, '01-2026', $pagos2);
        $this->assertSame(1, $res2['cantidad']);

        // Debe mantener EXACTAMENTE 1 abono y 1 movimiento (reemplazado, no duplicado)
        $this->assertDatabaseCount('abonos_deudas', 1);
        $this->assertDatabaseCount('movimientos_detracciones', 1);

        $movActualizado = MovimientoDetraccion::where('cliente_id', $this->cliente->id)->first();
        $this->assertSame('3500.00', (string) $movActualizado->monto);
        $this->assertSame('7799881122', $movActualizado->numero_operacion);
        $this->assertStringContainsString('NPS 7799881122', $movActualizado->descripcion);
    }

    public function test_eliminar_deuda_en_cascada_elimina_movimiento_detraccion(): void
    {
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '02-2026',
            'tiene_deuda' => true,
        ]);

        $deuda = DeudaTributaria::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '3111',
            'monto_deuda_inicial' => 1200.00,
        ]);

        $abono = AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 1200.00,
            'fecha_pago' => '2026-03-10',
            'medio_pago' => 'detracciones',
            'observacion' => 'NPS 7788990011',
        ]);

        $this->assertDatabaseHas('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'abono_deuda_id' => $abono->id,
        ]);

        // Al eliminar la deuda directamente, debe disparar deleting y eliminar el movimiento
        $deuda->delete();

        $this->assertDatabaseMissing('abonos_deudas', ['id' => $abono->id]);
        $this->assertDatabaseMissing('movimientos_detracciones', [
            'cliente_id' => $this->cliente->id,
            'abono_deuda_id' => $abono->id,
        ]);
    }
}
