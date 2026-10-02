<?php

namespace Tests\Feature;

use App\Models\AjusteSistema;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EstudioBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected Estudio $estudio;

    protected User $admin;

    protected User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::firstOrCreate(
            ['id' => 1],
            [
                'nombre' => 'Grupo DC Soluciones SAC',
                'ruc' => '20601234567',
                'representante' => 'Sebastian Taya Yucra',
                'correo' => 'contacto@grupodc.pe',
                'telefono' => '987654321',
                'activo' => true,
            ]
        );

        $this->admin = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Admin Titular',
            'email' => 'admin@grupodc.pe',
            'password' => Hash::make('password123'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->asistente = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Asistente Junior',
            'email' => 'asistente@grupodc.pe',
            'password' => Hash::make('password123'),
            'rol' => 'asistente',
            'activo' => true,
        ]);
    }

    public function test_admin_can_upload_logo_and_it_is_saved_in_public_storage(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('mi_logotipo.png', 400, 400);

        $payload = [
            'razon_social' => 'Grupo DC Soluciones SAC',
            'ruc' => '20601234567',
            'representante' => 'Sebastian Taya Yucra',
            'correo_corporativo' => 'contacto@grupodc.pe',
            'telefono_whatsapp' => '987654321',
            'frecuencia_cron_horas' => 6,
            'nivel_alerta_whatsapp' => 'Solo Altas',
            'sidebar_display' => 'logo_nombre',
            'logo' => $file,
        ];

        $response = $this->actingAs($this->admin)->post(route('ajustes.update'), $payload, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);

        $this->estudio->refresh();
        $this->assertNotNull($this->estudio->logo_path);
        Storage::disk('public')->assertExists($this->estudio->logo_path);
        $this->assertStringContainsString('/storage/'.$this->estudio->logo_path, $this->estudio->logoUrl());
    }

    public function test_admin_can_delete_existing_logo(): void
    {
        Storage::fake('public');

        $path = 'estudios/logos/1/test_logo.png';
        Storage::disk('public')->put($path, 'dummy-content');

        $this->estudio->update(['logo_path' => $path]);
        $this->assertTrue(Storage::disk('public')->exists($path));

        $payload = [
            'razon_social' => 'Grupo DC Soluciones SAC',
            'ruc' => '20601234567',
            'representante' => 'Sebastian Taya Yucra',
            'correo_corporativo' => 'contacto@grupodc.pe',
            'telefono_whatsapp' => '987654321',
            'frecuencia_cron_horas' => 6,
            'nivel_alerta_whatsapp' => 'Solo Altas',
            'eliminar_logo' => true,
        ];

        $response = $this->actingAs($this->admin)->post(route('ajustes.update'), $payload, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);

        $this->estudio->refresh();
        $this->assertNull($this->estudio->logo_path);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->estudio->logoUrl());
    }

    public function test_admin_can_update_all_branding_and_fiscal_fields(): void
    {
        $payload = [
            'razon_social' => 'ESTUDIO CONTABLE DC & ASOCIADOS S.A.C.',
            'nombre_comercial' => 'DC Consultores',
            'ruc' => '20609876543',
            'representante' => 'Sebastian Taya Yucra',
            'colegiatura' => 'Matrícula CPC 14205 (CCPA)',
            'slogan' => 'Asesoría contable y tributaria estratégica',
            'correo_corporativo' => 'gerencia@dcconsultores.pe',
            'telefono_whatsapp' => '954321987',
            'direccion' => 'Av. Goyeneche 123 Of. 401',
            'ciudad' => 'Arequipa, Perú',
            'sitio_web' => 'https://www.dcconsultores.pe',
            'sidebar_display' => 'logo_solo',
            'frecuencia_cron_horas' => 4,
            'nivel_alerta_whatsapp' => 'Todas',
            'notificar_whatsapp' => true,
        ];

        $response = $this->actingAs($this->admin)->post(route('ajustes.update'), $payload, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);

        $this->estudio->refresh();
        $this->assertSame('ESTUDIO CONTABLE DC & ASOCIADOS S.A.C.', $this->estudio->nombre);
        $this->assertSame('DC Consultores', $this->estudio->nombre_comercial);
        $this->assertSame('20609876543', $this->estudio->ruc);
        $this->assertSame('Sebastian Taya Yucra', $this->estudio->representante);
        $this->assertSame('Matrícula CPC 14205 (CCPA)', $this->estudio->colegiatura);
        $this->assertSame('Asesoría contable y tributaria estratégica', $this->estudio->slogan);
        $this->assertSame('gerencia@dcconsultores.pe', $this->estudio->correo);
        $this->assertSame('954321987', $this->estudio->telefono);
        $this->assertSame('Av. Goyeneche 123 Of. 401', $this->estudio->direccion);
        $this->assertSame('Arequipa, Perú', $this->estudio->ciudad);
        $this->assertSame('https://www.dcconsultores.pe', $this->estudio->sitio_web);
        $this->assertSame('logo_solo', $this->estudio->sidebar_display);

        // AjustesSistema model should also be synchronized
        $ajustes = AjusteSistema::getSettings();
        $this->assertSame('ESTUDIO CONTABLE DC & ASOCIADOS S.A.C.', $ajustes->razon_social);
        $this->assertSame('20609876543', $ajustes->ruc);
        $this->assertSame(4, $ajustes->frecuencia_cron_horas);
    }

    public function test_estudio_model_helpers(): void
    {
        $this->estudio->update([
            'nombre' => 'CORPORACION CONTABLE DEL SUR SAC',
            'nombre_comercial' => 'Contable Sur',
            'logo_path' => null,
        ]);

        $this->assertSame('CS', $this->estudio->iniciales());
        $this->assertSame('Contable Sur', $this->estudio->nombreParaMostrar());
        $this->assertNull($this->estudio->logoUrl());

        $this->estudio->update(['nombre_comercial' => null]);
        $this->assertSame('CORPORACION CONTABLE DEL SUR SAC', $this->estudio->nombreParaMostrar());
        $this->assertSame('CC', $this->estudio->iniciales());
    }

    public function test_non_admin_cannot_update_ajustes(): void
    {
        $payload = [
            'razon_social' => 'Hackeado',
            'ruc' => '20601234567',
            'representante' => 'Hacker',
            'frecuencia_cron_horas' => 8,
            'nivel_alerta_whatsapp' => 'Todas',
        ];

        $response = $this->actingAs($this->asistente)->post(route('ajustes.update'), $payload);

        $response->assertStatus(403);
    }

    public function test_logo_upload_validates_mime_type_and_size(): void
    {
        Storage::fake('public');

        // Invalid MIME type (text file disguised as image)
        $invalidFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->postJson(route('ajustes.update'), [
            'razon_social' => 'Grupo DC',
            'ruc' => '20601234567',
            'representante' => 'Titular',
            'frecuencia_cron_horas' => 8,
            'nivel_alerta_whatsapp' => 'Todas',
            'logo' => $invalidFile,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['logo']);
    }

    public function test_admin_can_upload_and_delete_logo_reportes(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo_reportes_impresion.png', 500, 200);

        $payload = [
            'razon_social' => 'Grupo DC Soluciones SAC',
            'ruc' => '20601234567',
            'representante' => 'Sebastian Taya Yucra',
            'frecuencia_cron_horas' => 6,
            'nivel_alerta_whatsapp' => 'Solo Altas',
            'logo_reportes' => $file,
        ];

        $response = $this->actingAs($this->admin)->post(route('ajustes.update'), $payload, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['logo_reportes_url', 'logo_reportes_nombre']);

        $this->estudio->refresh();
        $this->assertNotNull($this->estudio->logo_reportes_path);
        Storage::disk('public')->assertExists($this->estudio->logo_reportes_path);
        $this->assertNotNull($this->estudio->logoReportesUrl());
        $this->assertSame($this->estudio->logoReportesUrl(), $this->estudio->logoDocumentosUrl());

        // Ahora eliminarlo
        $deletePayload = [
            'razon_social' => 'Grupo DC Soluciones SAC',
            'ruc' => '20601234567',
            'representante' => 'Sebastian Taya Yucra',
            'frecuencia_cron_horas' => 6,
            'nivel_alerta_whatsapp' => 'Solo Altas',
            'eliminar_logo_reportes' => true,
        ];

        $deleteResponse = $this->actingAs($this->admin)->post(route('ajustes.update'), $deletePayload, [
            'Accept' => 'application/json',
        ]);

        $deleteResponse->assertStatus(200);
        $this->estudio->refresh();
        $this->assertNull($this->estudio->logo_reportes_path);
        $this->assertNull($this->estudio->logoReportesUrl());
    }

    public function test_estudio_logo_documentos_url_prioritizes_logo_reportes_over_standard_logo(): void
    {
        Storage::fake('public');

        $logoPath = 'estudios/logos/1/dark_logo.png';
        $reportesPath = 'estudios/logos_reportes/1/light_logo.png';

        Storage::disk('public')->put($logoPath, 'dummy-dark');
        Storage::disk('public')->put($reportesPath, 'dummy-light');

        $this->estudio->update([
            'logo_path' => $logoPath,
            'logo_reportes_path' => $reportesPath,
        ]);

        $this->assertSame($this->estudio->logoReportesUrl(), $this->estudio->logoDocumentosUrl());

        // Si se quita el de reportes, recurre al estándar
        $this->estudio->update(['logo_reportes_path' => null]);
        $this->assertSame($this->estudio->logoUrl(), $this->estudio->logoDocumentosUrl());
    }
}
