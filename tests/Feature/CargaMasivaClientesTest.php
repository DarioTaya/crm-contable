<?php

namespace Tests\Feature;

use App\Enums\EstadoCliente;
use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\Credencial;
use App\Models\Estudio;
use App\Models\User;
use App\Services\Clientes\LibroCargaMasivaClientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CargaMasivaClientesTest extends TestCase
{
    use RefreshDatabase;

    private const RUC = '20614054230';

    private const OTRO_RUC = '20605435191';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Estudio::query()->firstOrCreate(
            ['id' => 1],
            ['nombre' => 'Estudio Test', 'correo' => 'estudio@test.com', 'activo' => true]
        );
    }

    public function test_la_pagina_de_clientes_ofrece_la_carga_masiva_sin_quitar_el_alta_manual(): void
    {
        $response = $this->get(route('clientes.index'));

        $response->assertOk();
        $response->assertSee('Carga masiva');
        $response->assertSee('Nuevo Cliente');
        $response->assertSee('Revisar con SUNAT');
        $response->assertSee('carga-masiva', false);
        $response->assertSee('afpnet.com.pe', false);
        $response->assertSee('bancaporinternet.bn.com.pe', false);
        $response->assertSee('Enlace AFPnet');
        $response->assertSee('Enlace de detracciones');
    }

    public function test_la_plantilla_vacia_no_registra_clientes(): void
    {
        $plantilla = $this->get(route('clientes.carga_masiva.plantilla'));

        $plantilla->assertOk();
        $plantilla->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $contenido = $plantilla->getContent();
        $this->assertStringStartsWith('PK', $contenido);
        $ruta = tempnam(sys_get_temp_dir(), 'cmx');
        file_put_contents($ruta, $contenido);
        $zip = new \ZipArchive;
        $zip->open($ruta);
        $guia = $zip->getFromName('xl/worksheets/sheet2.xml');
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($ruta);
        $this->assertIsString($guia);
        $this->assertStringContainsString('Cómo completar la plantilla', $guia);
        $this->assertStringNotContainsString('# Carga masiva', $guia);
        $this->assertIsString($hoja);
        $this->assertStringNotContainsString('#', $hoja);

        $texto = (new LibroCargaMasivaClientes)->aTexto($contenido);
        $this->assertNotNull($texto);
        $this->assertStringContainsString('RUC', $texto);
        $this->assertStringContainsString('Régimen', $texto);
        $this->assertStringNotContainsString('#', $texto);

        $lectura = $this->postJson(route('clientes.carga_masiva.leer'), ['contenido' => $texto]);

        $lectura->assertOk();
        $lectura->assertJsonPath('filas', []);
        $this->assertStringContainsString('no tiene clientes', $lectura->json('errores.0.mensaje'));
        $this->assertSame(0, Cliente::count());
    }

    public function test_la_plantilla_excel_se_sube_y_un_libro_con_cadenas_compartidas_trae_el_ruc(): void
    {
        $plantilla = $this->get(route('clientes.carga_masiva.plantilla'))->getContent();
        $ruta = tempnam(sys_get_temp_dir(), 'cmx');
        file_put_contents($ruta, $plantilla);
        $vacia = new UploadedFile($ruta, 'carga-masiva-clientes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $lectura = $this->post(route('clientes.carga_masiva.leer'), [
            'archivo' => $vacia,
        ], ['Accept' => 'application/json']);

        $lectura->assertOk();
        $lectura->assertJsonPath('filas', []);

        $libro = UploadedFile::fake()->createWithContent('cartera.xlsx', $this->libroConRucCompartido());
        $conDatos = $this->post(route('clientes.carga_masiva.leer'), [
            'archivo' => $libro,
        ], ['Accept' => 'application/json']);

        $conDatos->assertOk();
        $conDatos->assertJsonPath('filas.0.ruc', self::RUC);
        $conDatos->assertJsonPath('filas.0.regimen', 'RER');
        $conDatos->assertJsonPath('filas.0.errores', []);
        $this->assertSame(0, Cliente::count());
    }

    public function test_un_corte_del_padron_se_consulta_de_nuevo_y_no_descarta_el_ruc(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.apis.net.pe/v1/ruc*' => Http::sequence()
                ->push('no disponible', 503)
                ->push([
                    'nombre' => 'BANDA TARRILLO LUZ NEY',
                    'estado' => 'ACTIVO',
                    'condicion' => 'HABIDO',
                ], 200),
        ]);

        $consulta = $this->postJson(route('clientes.carga_masiva.consultar'), ['ruc' => self::RUC]);

        $consulta->assertOk();
        $consulta->assertJsonPath('lista', true);
        $consulta->assertJsonPath('razon_social', 'BANDA TARRILLO LUZ NEY');
        Http::assertSentCount(2);
    }

    public function test_si_el_padron_sigue_sin_responder_la_fila_queda_para_reintentar(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.apis.net.pe/v1/ruc*' => Http::response('no disponible', 503),
        ]);

        $consulta = $this->postJson(route('clientes.carga_masiva.consultar'), ['ruc' => self::RUC]);

        $consulta->assertOk();
        $consulta->assertJsonPath('lista', false);
        $consulta->assertJsonPath('reintentable', true);
        $this->assertStringContainsString('no respondió', $consulta->json('mensaje'));
        $this->assertStringNotContainsString('no existe', $consulta->json('mensaje'));
        Http::assertSentCount(2);
        $this->assertSame(0, Cliente::count());
    }

    public function test_si_el_padron_pide_una_pausa_se_espera_y_se_lee_el_mismo_ruc(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.apis.net.pe/v1/ruc*' => Http::sequence()
                ->push('Too Many Requests', 429)
                ->push([
                    'nombre' => 'SECURITY PACHACUTEC S.A.C.',
                    'estado' => 'ACTIVO',
                    'condicion' => 'HABIDO',
                ], 200),
        ]);

        $consulta = $this->postJson(route('clientes.carga_masiva.consultar'), ['ruc' => self::OTRO_RUC]);

        $consulta->assertOk();
        $consulta->assertJsonPath('lista', true);
        $consulta->assertJsonPath('razon_social', 'SECURITY PACHACUTEC S.A.C.');
        $consulta->assertJsonMissingPath('codigo');
        Http::assertSentCount(2);
    }

    public function test_un_ruc_que_el_padron_no_encuentra_no_se_describe_como_corte(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.apis.net.pe/v1/ruc*' => Http::response('no encontrado', 404),
        ]);

        $consulta = $this->postJson(route('clientes.carga_masiva.consultar'), ['ruc' => self::RUC]);

        $consulta->assertOk();
        $consulta->assertJsonPath('lista', false);
        $consulta->assertJsonPath('reintentable', true);
        $this->assertStringContainsString('no encontró', $consulta->json('mensaje'));
        $this->assertStringNotContainsString('no respondió', $consulta->json('mensaje'));
        $this->assertSame(0, Cliente::count());
    }

    public function test_leer_rechaza_ruc_regimen_correo_repetidos_y_excel(): void
    {
        $contenido = implode("\n", [
            '# instrucción',
            'RUC;Régimen;Correo;Teléfono',
            '20614054239;NRUS;mal;123',
            self::RUC.';LOQUESEA;contacto@empresa.pe;925147601',
            self::RUC.';General;otro-mal;925147601',
            ';MYPE;;',
            '2.06141E+10;MYPE;;',
        ]);

        $response = $this->postJson(route('clientes.carga_masiva.leer'), ['contenido' => $contenido]);

        $response->assertOk();
        $filas = $response->json('filas');
        $this->assertCount(5, $filas);
        $this->assertSame(3, $filas[0]['fila']);
        $this->assertStringContainsString('dígito verificador incorrecto', $filas[0]['errores'][0]);
        $this->assertStringContainsString('correo', strtolower($filas[0]['errores'][1]));
        $this->assertSame('', $filas[1]['regimen']);
        $this->assertStringContainsString('no es válido', $filas[1]['errores'][0]);
        $this->assertStringContainsString('fila 4', implode(' ', $filas[2]['errores']));
        $this->assertStringContainsString('Indique el RUC', $filas[3]['errores'][0]);
        $this->assertStringContainsString('Excel', $filas[4]['errores'][0]);

        $excel = UploadedFile::fake()->create('clientes.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $rechazo = $this->post(route('clientes.carga_masiva.leer'), [
            'archivo' => $excel,
        ], ['Accept' => 'application/json']);

        $rechazo->assertStatus(422);
        $this->assertStringContainsString('No se pudo leer', $rechazo->json('errors.archivo.0'));
    }

    public function test_leer_acepta_alias_de_regimen_coma_tab_y_archivo_csv(): void
    {
        $puntoYComa = "RUC;Régimen\n".self::RUC.";NRUS\n";
        $coma = "RUC,Régimen,Correo\n".self::OTRO_RUC.",RMT,hola@estudio.pe\n";
        $tab = "RUC\tRégimen Tributario\n".self::RUC."\tRégimen General\n";

        $nrus = $this->postJson(route('clientes.carga_masiva.leer'), ['contenido' => $puntoYComa]);
        $nrus->assertOk();
        $nrus->assertJsonPath('filas.0.regimen', 'RUS');
        $nrus->assertJsonPath('filas.0.errores', []);

        $rmt = $this->postJson(route('clientes.carga_masiva.leer'), ['contenido' => $coma]);
        $rmt->assertJsonPath('filas.0.regimen', 'RMT');
        $rmt->assertJsonPath('filas.0.correo', 'hola@estudio.pe');

        $general = $this->postJson(route('clientes.carga_masiva.leer'), ['contenido' => $tab]);
        $general->assertJsonPath('filas.0.regimen', 'RG');

        $archivo = UploadedFile::fake()->createWithContent('clientes.csv', $puntoYComa);
        $subido = $this->post(route('clientes.carga_masiva.leer'), [
            'archivo' => $archivo,
        ], ['Accept' => 'application/json']);
        $subido->assertOk();
        $subido->assertJsonPath('filas.0.ruc', self::RUC);
        $subido->assertJsonPath('filas.0.regimen', 'RUS');
    }

    public function test_registrar_guarda_el_padron_las_credenciales_y_el_primer_numero(): void
    {
        $this->fingirPadron(self::RUC);

        $response = $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC, 'NRUS', [
            'usuario_sol' => 'moddatos',
            'url_afp' => Credencial::ENLACE_AFP,
            'url_bn' => 'https://detracciones.example.test/inicio',
        ]));

        $response->assertOk();
        $response->assertJsonPath('registrado', true);
        $response->assertJsonPath('cliente.numero', 1);
        $response->assertJsonPath('cliente.razon_social', 'QORI PORACOTA S.A.C.');

        $cliente = Cliente::where('ruc', self::RUC)->first();
        $this->assertNotNull($cliente);
        $this->assertSame(1, $cliente->numero);
        $this->assertSame(RegimenTributario::Rus, $cliente->regimen_tributario);
        $this->assertSame(EstadoCliente::Activo, $cliente->estado);
        $this->assertSame('ACTIVO', $cliente->estado_contribuyente);
        $this->assertSame('HABIDO', $cliente->condicion_domicilio);
        $this->assertSame('Persona Jurídica (Empresa / Sociedad)', $cliente->tipo_contribuyente);
        $this->assertSame('CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA', $cliente->direccion_fiscal);
        $this->assertSame('040409', $cliente->ubigeo);
        $this->assertSame('ORCOPAMPA', $cliente->distrito);
        $this->assertSame('CASTILLA', $cliente->provincia);
        $this->assertSame('AREQUIPA', $cliente->departamento);
        $this->assertNotNull($cliente->fecha_sincronizacion_sunat);
        $this->assertFalse($cliente->es_buen_contribuyente);
        $this->assertSame('contacto@empresa.pe', $cliente->correo);
        $this->assertSame('925147601', $cliente->telefono);
        $this->assertSame('client-id', $cliente->sunat_client_id);
        $this->assertSame('secreto-api', $cliente->sunat_client_secret);
        $this->assertNotSame('secreto-api', DB::table('clientes')->where('id', $cliente->id)->value('sunat_client_secret'));

        $credencial = $cliente->credencial;
        $this->assertNotNull($credencial);
        $this->assertSame('MODDATOS', $credencial->usuario_sol);
        $this->assertSame('ClaveSol123', $credencial->clave_sol);
        $this->assertNotSame('ClaveSol123', DB::table('credenciales')->where('cliente_id', $cliente->id)->value('clave_sol'));
        $this->assertNull($credencial->url_afp);
        $this->assertSame(Credencial::ENLACE_AFP, $credencial->enlaceAfp());
        $this->assertSame('https://detracciones.example.test/inicio', $credencial->url_bn);
        $this->assertNull($credencial->usuario_afp);
        $this->assertNull($credencial->clave_afp);
        $this->assertNull($credencial->cuenta_detracciones);
        $this->assertNull($credencial->dni_titular);
        $this->assertNull($credencial->banco_1_nombre);
        $this->assertNull($credencial->banco_2_nombre);
        Http::assertSentCount(1);
    }

    public function test_no_registra_sin_razon_social_de_sunat(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.apis.net.pe/v1/ruc?numero='.self::RUC => Http::response(['nombre' => ''], 200),
        ]);

        $vacio = $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC));

        $vacio->assertStatus(422);
        $vacio->assertJsonPath('registrado', false);
        $this->assertStringContainsString('razón social', $vacio->json('mensaje'));
        $this->assertSame(0, Cliente::count());
    }

    public function test_no_repite_un_ruc_ya_registrado_en_la_misma_carga(): void
    {
        $this->fingirPadron(self::RUC);

        $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC))->assertOk();
        $repetido = $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC));

        $repetido->assertStatus(422);
        $this->assertStringContainsString('ya se encuentra registrado', $repetido->json('mensaje'));
        $this->assertSame(1, Cliente::count());
    }

    public function test_el_numero_reutiliza_el_hueco_definitivo_y_conserva_el_de_la_papelera(): void
    {
        $primero = Cliente::create([
            'razon_social' => 'PRIMERO SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);
        $segundo = Cliente::create([
            'razon_social' => 'SEGUNDO SAC',
            'ruc' => self::OTRO_RUC,
            'regimen_tributario' => 'General',
            'estado' => 'activo',
        ]);
        $this->assertSame(1, $primero->numero);
        $this->assertSame(2, $segundo->numero);
        $segundo->delete();

        $this->fingirPadron(self::RUC);
        $enPapelera = $this->postJson(route('clientes.carga_masiva.consultar'), ['ruc' => self::OTRO_RUC]);
        $enPapelera->assertOk();
        $enPapelera->assertJsonPath('lista', false);
        $this->assertStringContainsString('papelera', $enPapelera->json('mensaje'));
        Http::assertNothingSent();

        $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC))->assertOk();
        $this->assertSame(3, Cliente::where('ruc', self::RUC)->value('numero'));

        $segundo->forceDelete();
        $this->fingirPadron('10297388580', ['nombre' => 'PERSONA DE PRUEBA']);
        $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila('10297388580', 'General'))->assertOk();
        $this->assertSame(2, Cliente::where('ruc', '10297388580')->value('numero'));
    }

    public function test_la_consulta_del_alta_manual_no_reutiliza_la_memoria_de_la_carga(): void
    {
        $this->fingirPadron(self::RUC);

        $this->postJson(route('clientes.validar-ruc'), ['ruc' => self::RUC])->assertOk();
        $this->postJson(route('clientes.validar-ruc'), ['ruc' => self::RUC])->assertOk();

        Http::assertSentCount(2);
    }

    public function test_registrar_reutiliza_el_padron_ya_consultado(): void
    {
        $this->fingirPadron(self::RUC);

        $consulta = $this->postJson(route('clientes.carga_masiva.consultar'), ['ruc' => self::RUC]);
        $consulta->assertOk();
        $consulta->assertJsonPath('lista', true);
        $consulta->assertJsonPath('razon_social', 'QORI PORACOTA S.A.C.');
        $consulta->assertJsonPath('distrito', 'ORCOPAMPA');

        $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC))->assertOk();

        Http::assertSentCount(1);
        $this->assertSame('QORI PORACOTA S.A.C.', Cliente::where('ruc', self::RUC)->value('razon_social'));
    }

    public function test_un_asistente_queda_asignado_al_cliente_que_carga(): void
    {
        $estudio = Estudio::factory()->create();
        $asistente = User::factory()->asistente()->create([
            'estudio_id' => $estudio->id,
            'email' => 'asistente-carga@estudio.test',
        ]);
        $this->actingAs($asistente);
        $this->fingirPadron(self::RUC);

        $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC))->assertOk();

        $cliente = Cliente::where('ruc', self::RUC)->first();
        $this->assertSame($estudio->id, $cliente->estudio_id);
        $this->assertTrue($cliente->asistentes()->whereKey($asistente->id)->exists());
    }

    public function test_otro_estudio_puede_cargar_el_mismo_ruc(): void
    {
        $estudioA = Estudio::factory()->create();
        $estudioB = Estudio::factory()->create();
        Cliente::factory()->paraEstudio($estudioA)->create([
            'ruc' => self::RUC,
            'razon_social' => 'YA ESTA EN EL OTRO ESTUDIO SAC',
        ]);
        $this->actingAs(User::factory()->create([
            'estudio_id' => $estudioB->id,
            'email' => 'admin-b@estudio.test',
        ]));
        $this->fingirPadron(self::RUC);

        $this->postJson(route('clientes.carga_masiva.registrar'), $this->fila(self::RUC))->assertOk();

        $this->assertSame(2, Cliente::withoutGlobalScopes()->where('ruc', self::RUC)->count());
        $this->assertSame(1, Cliente::where('ruc', self::RUC)->value('numero'));
    }

    public function test_un_invitado_no_carga_clientes(): void
    {
        $this->withHeader('X-Test-Guest', '1')
            ->postJson(route('clientes.carga_masiva.leer'), ['contenido' => 'RUC;Régimen'])
            ->assertUnauthorized();

        $this->withHeader('X-Test-Guest', '1')
            ->get(route('clientes.carga_masiva.plantilla'))
            ->assertRedirect(route('login'));
    }

    public function test_leer_marca_un_ruc_que_ya_esta_en_la_cartera(): void
    {
        Cliente::create([
            'razon_social' => 'EMPRESA REGISTRADA S.A.C.',
            'ruc' => self::RUC,
            'regimen_tributario' => 'General',
            'estado' => 'activo',
        ]);

        $response = $this->postJson(route('clientes.carga_masiva.leer'), [
            'contenido' => "RUC;Régimen\n".self::RUC.";General\n",
        ]);

        $response->assertOk();
        $this->assertStringContainsString('EMPRESA REGISTRADA S.A.C.', $response->json('filas.0.errores.0'));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function fila(string $ruc, string $regimen = 'RMT', array $extra = []): array
    {
        return array_merge([
            'ruc' => $ruc,
            'regimen' => $regimen,
            'correo' => 'contacto@empresa.pe',
            'telefono' => '925147601',
            'usuario_sol' => 'moddatos',
            'clave_sol' => 'ClaveSol123',
            'sunat_client_id' => 'client-id',
            'sunat_client_secret' => 'secreto-api',
            'url_afp' => Credencial::ENLACE_AFP,
            'url_bn' => Credencial::ENLACE_BN,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function fingirPadron(string $ruc, array $datos = []): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.apis.net.pe/v1/ruc?numero='.$ruc => Http::response(array_merge([
                'nombre' => 'QORI PORACOTA S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA',
                'ubigeo' => '040409',
                'distrito' => 'ORCOPAMPA',
                'provincia' => 'CASTILLA',
                'departamento' => 'AREQUIPA',
            ], $datos), 200),
        ]);
    }

    private function libroConRucCompartido(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($ruta === false) {
            return '';
        }
        unlink($ruta);
        $zip = new \ZipArchive;
        $zip->open($ruta, \ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Instrucciones" sheetId="1" r:id="rId1"/><sheet name="Clientes" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>RUC</t></si><si><t>Régimen</t></si><si><t>RER</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Esta hoja no se carga</t></is></c></row></sheetData></worksheet>');
        $zip->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Carga masiva de clientes</t></is></c></row><row r="3"><c r="A3" t="s"><v>0</v></c><c r="B3" t="s"><v>1</v></c></row><row r="4"><c r="A4"><v>'.self::RUC.'</v></c><c r="B4" t="s"><v>2</v></c></row></sheetData></worksheet>');
        $zip->close();
        $bytes = (string) file_get_contents($ruta);
        unlink($ruta);

        return $bytes;
    }
}
