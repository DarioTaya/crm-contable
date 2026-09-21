<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Services\SunatSoapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class SunatSoapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_soap_envelope_builder_creates_valid_security_header_and_body(): void
    {
        $soapService = app(SunatSoapService::class);

        $xml = $soapService->buildSoapEnvelope(
            '20123456789MODDATOS',
            'moddatos',
            'getStatusCdr',
            '20123456789',
            '01',
            'F001',
            456
        );

        $this->assertStringContainsString('<wsse:Username>20123456789MODDATOS</wsse:Username>', $xml);
        $this->assertStringContainsString('<wsse:Password>moddatos</wsse:Password>', $xml);
        $this->assertStringContainsString('<ser:getStatusCdr>', $xml);
        $this->assertStringContainsString('<rucComprobante>20123456789</rucComprobante>', $xml);
        $this->assertStringContainsString('<tipoComprobante>01</tipoComprobante>', $xml);
        $this->assertStringContainsString('<serieComprobante>F001</serieComprobante>', $xml);
        $this->assertStringContainsString('<numeroComprobante>456</numeroComprobante>', $xml);
    }

    public function test_consultar_cpe_ventas_success_with_zip_cdr(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA EMISORA SAC',
            'ruc' => '20601234567',
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RVIE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '00000099',
            'ruc_tercero' => '20555444333',
            'razon_social_tercero' => 'CLIENTE FINAL SAC',
            'moneda' => 'PEN',
            'base_imponible' => 500.00,
            'igv' => 90.00,
            'total' => 590.00,
            'estado_sire' => 'Propuesta',
            'estado_descarga' => 'pendiente',
        ]);

        // Crear ZIP en memoria simulando respuesta oficial de SUNAT
        $tempZip = tempnam(sys_get_temp_dir(), 'test_cdr_');
        $zip = new ZipArchive;
        $zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('R-20601234567-01-F001-00000099.xml', '<ar:ApplicationResponse><cbc:ResponseCode>0</cbc:ResponseCode></ar:ApplicationResponse>');
        $zip->close();
        $zipContent = base64_encode(file_get_contents($tempZip));
        unlink($tempZip);

        Http::fake([
            'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService' => Http::response(
                <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
    <S:Body>
        <ns0:getStatusCdrResponse xmlns:ns0="http://service.sunat.gob.pe">
            <statusCdr>
                <statusCode>0004</statusCode>
                <statusMessage>La Factura numero F001-00000099 ha sido aceptada</statusMessage>
                <content>{$zipContent}</content>
            </statusCdr>
        </ns0:getStatusCdrResponse>
    </S:Body>
</S:Envelope>
XML, 200, ['Content-Type' => 'text/xml;charset=UTF-8']
            ),
        ]);

        $soapService = app(SunatSoapService::class);
        $res = $soapService->consultarCpe($comprobante);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['cdr_descargado']);
        $this->assertEquals('0004', $res['status_code']);

        $comprobante->refresh();
        $this->assertEquals('disponible', $comprobante->estado_descarga);
        $this->assertEquals('disponible', $comprobante->estado_cdr);
        $this->assertNotEmpty($comprobante->ruta_cdr);
        $this->assertNotEmpty($comprobante->ruta_xml);
        $this->assertNotEmpty($comprobante->ruta_pdf);
        $this->assertStringContainsString('clientes/'.$cliente->id.'/sire/2026/08/ventas', $comprobante->ruta_cdr);
    }

    public function test_consultar_cpe_compras_receptor_validates_with_get_status(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA RECEPTORA SAC',
            'ruc' => '20444333222',
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '00000123',
            'ruc_tercero' => '20111222333',
            'razon_social_tercero' => 'PROVEEDOR PRIVADO SAC',
            'moneda' => 'PEN',
            'base_imponible' => 1000.00,
            'igv' => 180.00,
            'total' => 1180.00,
            'estado_sire' => 'Propuesta',
            'estado_descarga' => 'pendiente',
        ]);

        Http::fake([
            'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService' => Http::response(
                <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
    <S:Body>
        <ns0:getStatusResponse xmlns:ns0="http://service.sunat.gob.pe">
            <status>
                <statusCode>0001</statusCode>
                <statusMessage>El comprobante existe y está aceptado.</statusMessage>
            </status>
        </ns0:getStatusResponse>
    </S:Body>
</S:Envelope>
XML, 200, ['Content-Type' => 'text/xml;charset=UTF-8']
            ),
        ]);

        $soapService = app(SunatSoapService::class);
        $res = $soapService->consultarCpe($comprobante);

        $this->assertTrue($res['success']);
        $this->assertEquals('0001', $res['status_code']);

        $comprobante->refresh();
        $this->assertEquals('disponible', $comprobante->estado_descarga);
        $this->assertStringContainsString('El comprobante existe y está aceptado', $comprobante->mensaje_soap);
    }

    public function test_consultar_cpe_serie_e001_recognized_as_sol_portal(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA CONTRIBUYENTE SAC',
            'ruc' => '20999888777',
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RVIE',
            'tipo_comprobante' => '01',
            'serie' => 'E001',
            'correlativo' => '00000456',
            'ruc_tercero' => '20333222111',
            'razon_social_tercero' => 'CLIENTE FINAL SAC',
            'moneda' => 'PEN',
            'base_imponible' => 200.00,
            'igv' => 36.00,
            'total' => 236.00,
            'estado_sire' => 'Propuesta',
            'estado_descarga' => 'pendiente',
        ]);

        $soapService = app(SunatSoapService::class);
        $res = $soapService->consultarCpe($comprobante);

        $this->assertTrue($res['success']);
        $this->assertEquals('0010', $res['status_code']);

        $comprobante->refresh();
        $this->assertEquals('disponible', $comprobante->estado_descarga);
        $this->assertStringContainsString('portal SOL', $comprobante->mensaje_soap);
    }
}
