<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Comprobante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VisorXmlCpeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica que la vista del Visor XML & Generador CPE cargue correctamente (HTTP 200).
     */
    public function test_visor_xml_page_loads_successfully(): void
    {
        $response = $this->get(route('cpe.visor'));

        $response->assertStatus(200);
        $response->assertSee('Visor XML & Generador Premium de CPE');
        $response->assertSee('100% Stateless');
        $response->assertSee('Arrastra aquí cualquier archivo XML de Comprobante SUNAT');
    }

    /**
     * Verifica el parsing stateless de una factura del Portal SOL (E001) sin tocar la base de datos.
     */
    public function test_parse_xml_portal_sol_statelessly(): void
    {
        $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:InvoiceTypeCode listID="0101">01</cbc:InvoiceTypeCode>
    <cbc:ID>E001-45</cbc:ID>
    <cbc:IssueDate>2026-09-15</cbc:IssueDate>
    <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification><cbc:ID>20555555555</cbc:ID></cac:PartyIdentification>
            <cac:PartyLegalEntity><cbc:RegistrationName>EMPRESA SOL EMISORA SAC</cbc:RegistrationName></cac:PartyLegalEntity>
            <cac:PartyPostalAddress><cbc:StreetName>AV. SOL 123 - LIMA</cbc:StreetName></cac:PartyPostalAddress>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification><cbc:ID>20111111111</cbc:ID></cac:PartyIdentification>
            <cac:PartyLegalEntity><cbc:RegistrationName>CLIENTE RECEPTOR SAC</cbc:RegistrationName></cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal>
        <cbc:TaxAmount>180.00</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount>1000.00</cbc:TaxableAmount>
            <cbc:TaxAmount>180.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cac:TaxScheme><cbc:ID>1000</cbc:ID><cbc:Name>IGV</cbc:Name></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount>1000.00</cbc:LineExtensionAmount>
        <cbc:PayableAmount>1180.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="NIU">2</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount>1000.00</cbc:LineExtensionAmount>
        <cac:PricingReference>
            <cac:AlternativeConditionPrice>
                <cbc:PriceAmount>590.00</cbc:PriceAmount>
            </cac:AlternativeConditionPrice>
        </cac:PricingReference>
        <cac:TaxTotal>
            <cbc:TaxAmount>180.00</cbc:TaxAmount>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Description>SERVICIO DE ASESORÍA CONTABLE SUNAT</cbc:Description>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount>500.00</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
    <ds:Signature>
        <ds:SignedInfo>
            <ds:Reference>
                <ds:DigestValue>abCdEf123456HashDigest=</ds:DigestValue>
            </ds:Reference>
        </ds:SignedInfo>
    </ds:Signature>
</Invoice>
XML;

        $file = UploadedFile::fake()->createWithContent('20555555555-01-E001-45.xml', $xmlContent);

        $response = $this->postJson(route('cpe.parse'), [
            'xml_file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id_completo' => 'E001-45',
                'serie' => 'E001',
                'correlativo' => '45',
                'es_portal_sol' => true,
                'es_facturador_privado' => false,
                'total' => 1180.00,
                'base_imponible' => 1000.00,
                'igv' => 180.00,
                'digest_value' => 'abCdEf123456HashDigest=',
                'emisor' => [
                    'ruc' => '20555555555',
                    'razon_social' => 'EMPRESA SOL EMISORA SAC',
                ],
            ],
        ]);

        // Asegurar que no se tocó ni creó ningún registro en base de datos
        $this->assertEquals(0, Comprobante::count());
    }

    /**
     * Verifica el parsing de un facturador privado (F001) con detracción SPOT y cuotas al crédito.
     */
    public function test_parse_xml_private_invoice_with_spot_and_cuotas_statelessly(): void
    {
        $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:InvoiceTypeCode listID="0101">01</cbc:InvoiceTypeCode>
    <cbc:ID>F001-00008899</cbc:ID>
    <cbc:IssueDate>2026-09-16</cbc:IssueDate>
    <cbc:DueDate>2026-10-16</cbc:DueDate>
    <cbc:DocumentCurrencyCode>PEN</cbc:DocumentCurrencyCode>
    <cbc:Note languageLocaleID="2006">Operacion sujeta a detraccion SPOT Banco de la Nacion Cta Cte: 00-055-123456 tasa 12%</cbc:Note>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification><cbc:ID>20999888777</cbc:ID></cac:PartyIdentification>
            <cac:PartyLegalEntity><cbc:RegistrationName>LOGISTICA PRIVADA S.A.C.</cbc:RegistrationName></cac:PartyLegalEntity>
            <cac:PartyPostalAddress><cbc:StreetName>AV. INDUSTRIAL 500 - CALLAO</cbc:StreetName></cac:PartyPostalAddress>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification><cbc:ID>20111111111</cbc:ID></cac:PartyIdentification>
            <cac:PartyLegalEntity><cbc:RegistrationName>CORPORACION CLIENTE S.A.</cbc:RegistrationName></cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode>001</cbc:PaymentMeansCode>
        <cac:PayeeFinancialAccount>
            <cbc:ID>00-055-123456</cbc:ID>
        </cac:PayeeFinancialAccount>
    </cac:PaymentMeans>
    <cac:PaymentTerms>
        <cbc:PaymentMeansID>Credito</cbc:PaymentMeansID>
        <cbc:Amount>5000.00</cbc:Amount>
    </cac:PaymentTerms>
    <cac:PaymentTerms>
        <cbc:PaymentMeansID>Cuota 1</cbc:PaymentMeansID>
        <cbc:Amount>2500.00</cbc:Amount>
        <cbc:PaymentDueDate>2026-10-01</cbc:PaymentDueDate>
    </cac:PaymentTerms>
    <cac:PaymentTerms>
        <cbc:PaymentMeansID>Cuota 2</cbc:PaymentMeansID>
        <cbc:Amount>2500.00</cbc:Amount>
        <cbc:PaymentDueDate>2026-10-16</cbc:PaymentDueDate>
    </cac:PaymentTerms>
    <cac:PaymentTerms>
        <cbc:PaymentMeansID>Detraccion</cbc:PaymentMeansID>
        <cbc:PaymentPercent>12.00</cbc:PaymentPercent>
        <cbc:Amount>600.00</cbc:Amount>
    </cac:PaymentTerms>
    <cac:TaxTotal>
        <cbc:TaxAmount>762.71</cbc:TaxAmount>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount>4237.29</cbc:LineExtensionAmount>
        <cbc:PayableAmount>5000.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="ZZ">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount>4237.29</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Description>TRANSPORTE DE CARGA PESADA</cbc:Description>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount>4237.29</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
    <ds:Signature>
        <ds:SignedInfo>
            <ds:Reference>
                <ds:DigestValue>PrivHash987654321=</ds:DigestValue>
            </ds:Reference>
        </ds:SignedInfo>
    </ds:Signature>
</Invoice>
XML;

        $response = $this->postJson(route('cpe.parse'), [
            'xml_content' => $xmlContent,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('F001', $data['serie']);
        $this->assertEquals('00008899', $data['correlativo']);
        $this->assertFalse($data['es_portal_sol']);
        $this->assertTrue($data['es_facturador_privado']);
        $this->assertEquals('Credito', $data['forma_pago']);
        $this->assertCount(2, $data['cuotas']);
        $this->assertEquals('00-055-123456', $data['detraccion']['cuenta_banco']);
        $this->assertEquals(12.0, $data['detraccion']['porcentaje']);
        $this->assertEquals(600.0, $data['detraccion']['monto']);

        // Cero escrituras en BD
        $this->assertEquals(0, Comprobante::count());
    }

    /**
     * Verifica que descargar PDF genere un stream binario PDF 1.4 al vuelo sin archivos en disco ni BD.
     */
    public function test_descargar_pdf_endpoint_streams_binary_pdf_without_disk_or_db_records(): void
    {
        $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:InvoiceTypeCode>01</cbc:InvoiceTypeCode>
    <cbc:ID>F001-00012345</cbc:ID>
    <cbc:IssueDate>2026-09-17</cbc:IssueDate>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification><cbc:ID>20123456789</cbc:ID></cac:PartyIdentification>
            <cac:PartyLegalEntity><cbc:RegistrationName>PROVEEDOR TEST SAC</cbc:RegistrationName></cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification><cbc:ID>20987654321</cbc:ID></cac:PartyIdentification>
            <cac:PartyLegalEntity><cbc:RegistrationName>CLIENTE COMPRADOR SAC</cbc:RegistrationName></cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:TaxTotal><cbc:TaxAmount>18.00</cbc:TaxAmount></cac:TaxTotal>
    <cac:LegalMonetaryTotal><cbc:PayableAmount>118.00</cbc:PayableAmount></cac:LegalMonetaryTotal>
</Invoice>
XML;

        $response = $this->post(route('cpe.descargar-pdf'), [
            'xml_content' => $xmlContent,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="20123456789-01-F001-00012345.pdf"');

        // El contenido debe comenzar con la firma mágica estándar de PDF 1.4
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $content);

        // Cero escrituras en base de datos
        $this->assertEquals(0, Comprobante::count());
    }

    /**
     * Verifica que la Bóveda SIRE y el Expediente del Cliente continúen funcionando limpiamente tras el rollback.
     */
    public function test_boveda_sire_and_cliente_show_remain_intact_and_functional(): void
    {
        $cliente = Cliente::create([
            'ruc' => '20123456780',
            'razon_social' => 'CLIENTE TEST LIMPIEZA SAC',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        // Bóveda SIRE global
        $resBoveda = $this->get(route('comprobantes.index'));
        $resBoveda->assertStatus(200);
        $resBoveda->assertDontSee('Cargar Originales (ZIP / XML)');

        // Expediente SIRE del cliente
        $resCliente = $this->get(route('clientes.sire', $cliente));
        $resCliente->assertStatus(200);
        $resCliente->assertDontSee('Cargar Originales');
    }
}
