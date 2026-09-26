<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credencial;
use App\Models\Notificacion;
use App\Models\Operacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_cliente_with_all_relations(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'ESTUDIO CONTABLE DEMO S.A.C.',
            'ruc' => '20601234567',
            'regimen_tributario' => 'MYPE',
            'correo' => 'contacto@democontable.pe',
            'telefono' => '987654321',
            'estado' => true,
        ]);

        $this->assertDatabaseHas('clientes', [
            'ruc' => '20601234567',
            'razon_social' => 'ESTUDIO CONTABLE DEMO S.A.C.',
        ]);

        // 1. Test Credencial and automatic encryption/decryption
        $credencial = $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'ClaveSecretaSOL123*',
            'usuario_afp' => 'AFPUSER2026',
            'clave_afp' => 'AfpPass987$',
            'dni_titular' => '45678901',
            'clave_detracciones' => 'Detrac456!',
        ]);

        // Verify Eloquent automatically decrypts on read
        $freshCredencial = Credencial::find($credencial->id);
        $this->assertEquals('ClaveSecretaSOL123*', $freshCredencial->clave_sol);
        $this->assertEquals('AfpPass987$', $freshCredencial->clave_afp);
        $this->assertEquals('Detrac456!', $freshCredencial->clave_detracciones);

        // Verify raw database record is actually encrypted and does NOT contain plaintext passwords
        $rawRecord = DB::table('credenciales')->where('id', $credencial->id)->first();
        $this->assertNotEquals('ClaveSecretaSOL123*', $rawRecord->clave_sol);
        $this->assertNotEquals('AfpPass987$', $rawRecord->clave_afp);
        $this->assertNotEquals('Detrac456!', $rawRecord->clave_detracciones);
        $this->assertStringNotContainsString('ClaveSecretaSOL123*', $rawRecord->clave_sol);

        // Verify hidden attributes in JSON serialization
        $jsonArray = $freshCredencial->toArray();
        $this->assertArrayNotHasKey('clave_sol', $jsonArray);
        $this->assertArrayNotHasKey('clave_afp', $jsonArray);
        $this->assertArrayNotHasKey('clave_detracciones', $jsonArray);

        // 2. Test Notificacion relationship
        $notificacion = $cliente->notificaciones()->create([
            'fecha' => '2026-09-15',
            'asunto' => 'Notificación de Buzón Electrónico SUNAT - Orden de Pago',
            'emisor' => 'SUNAT',
            'leido' => false,
        ]);

        $this->assertTrue($cliente->notificaciones->contains($notificacion));
        $this->assertEquals($cliente->id, $notificacion->cliente->id);

        // 3. Test Operacion relationship
        $operacion = $cliente->operaciones()->create([
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Presentado',
            'estado_ple' => 'Generado',
        ]);

        $this->assertTrue($cliente->operaciones->contains($operacion));
        $this->assertEquals($cliente->id, $operacion->cliente->id);

        // 4. Test Soft Delete preserves relationships, and forceDelete cleans them up
        $cliente->delete();
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
        $this->assertDatabaseHas('credenciales', ['id' => $credencial->id]);
        $this->assertDatabaseHas('notificaciones', ['id' => $notificacion->id]);
        $this->assertDatabaseHas('operaciones', ['id' => $operacion->id]);

        $cliente->forceDelete();
        $this->assertDatabaseMissing('credenciales', ['id' => $credencial->id]);
        $this->assertDatabaseMissing('notificaciones', ['id' => $notificacion->id]);
        $this->assertDatabaseMissing('operaciones', ['id' => $operacion->id]);
    }

    public function test_login_sunat_requires_valid_credentials(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'SIN CREDENCIALES S.A.C.',
            'ruc' => '20111111111',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.sunat', $cliente));

        $response->assertRedirect(route('clientes.show', $cliente));
        $response->assertSessionHas('error');
    }

    public function test_sunafil_login_requires_valid_credentials(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE SIN SOL SUNAFIL',
            'ruc' => '20444444444',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.sunafil', $cliente));

        $response->assertRedirect(route('clientes.show', $cliente));
        $response->assertSessionHas('error');
    }

    public function test_sync_buzon_requires_valid_credentials(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE SIN SOL S.A.C.',
            'ruc' => '20222222222',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->post(route('clientes.buzon.sync', $cliente));

        $response->assertRedirect(route('clientes.show', $cliente));
        $response->assertSessionHas('error');
    }

    public function test_notificaciones_update_or_create_avoids_duplicates(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE TEST S.A.C.',
            'ruc' => '20333333333',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        // Simular inserción inicial
        $cliente->notificaciones()->updateOrCreate(
            ['fecha' => '2026-09-15', 'asunto' => 'Resolución de Intendencia 001-2026'],
            ['emisor' => 'SUNAT', 'leido' => false]
        );

        $this->assertDatabaseCount('notificaciones', 1);

        // Simular sincronización posterior con el mismo registro
        $cliente->notificaciones()->updateOrCreate(
            ['fecha' => '2026-09-15', 'asunto' => 'Resolución de Intendencia 001-2026'],
            ['emisor' => 'SUNAT', 'leido' => true]
        );

        // Debe mantenerse en 1 registro (actualizado a leído = true, sin duplicarse)
        $this->assertDatabaseCount('notificaciones', 1);
        $this->assertTrue((bool) $cliente->notificaciones()->first()->leido);
    }

    public function test_notificaciones_filter_prioritizes_whitelist_and_blocks_blacklist(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA FILTROS S.A.C.',
            'ruc' => '20444444444',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $whitelist = [
            'orden de pago', 'valor', 'resolución', 'resolucion',
            'carta inductiva', 'esquela', 'requerimiento', 'multa',
        ];

        $blacklist = [
            'rvie', 'rce', 'sire', 'propuesta', 'comprobantes',
            'rhe', 'fe', 'generación de registro', 'generacion de registro',
        ];

        $sampleItems = [
            // Blacklist puro -> debe descartarse
            ['fecha' => '2026-09-15', 'asunto' => 'Propuesta RVIE y RCE del periodo 08-2026'],
            ['fecha' => '2026-09-15', 'asunto' => 'Generación de registro de comprobantes electrónicos'],
            // Whitelist puro -> debe guardarse con es_importante = true
            ['fecha' => '2026-09-14', 'asunto' => 'Orden de Pago N° 011-001-998877'],
            ['fecha' => '2026-09-13', 'asunto' => 'Notificación de Carta Inductiva por omisión'],
            // Mixto (contiene palabra de blacklist 'RVIE' pero es una 'Multa' de whitelist) -> gana whitelist!
            ['fecha' => '2026-09-12', 'asunto' => 'Resolución de Multa por no presentar RVIE'],
            // Mensaje neutro sin palabras de lista -> se guarda con es_importante = false
            ['fecha' => '2026-09-11', 'asunto' => 'Aviso de mantenimiento de servidores SOL'],
        ];

        $saved = 0;
        foreach ($sampleItems as $item) {
            $asuntoLower = mb_strtolower($item['asunto']);

            $esImportante = false;
            foreach ($whitelist as $t) {
                if (str_contains($asuntoLower, $t)) {
                    $esImportante = true;
                    break;
                }
            }

            if (! $esImportante) {
                $isSpam = false;
                foreach ($blacklist as $t) {
                    if (str_contains($asuntoLower, $t)) {
                        $isSpam = true;
                        break;
                    }
                }
                if ($isSpam) {
                    continue;
                }
            }

            $cliente->notificaciones()->create([
                'fecha' => $item['fecha'],
                'asunto' => $item['asunto'],
                'emisor' => 'SUNAT',
                'leido' => false,
                'es_importante' => $esImportante,
            ]);
            $saved++;
        }

        // De los 6 mensajes: 2 spam descartados, 3 importantes guardados, 1 neutro guardado = 4 guardados en total
        $this->assertEquals(4, $saved);
        $this->assertEquals(4, $cliente->notificaciones()->count());
        $this->assertEquals(3, $cliente->notificaciones()->where('es_importante', true)->count());
        $this->assertDatabaseMissing('notificaciones', ['asunto' => 'Propuesta RVIE y RCE del periodo 08-2026']);
    }

    public function test_toggle_leido_switches_notification_read_status(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA TOGGLE S.A.C.',
            'ruc' => '20555555555',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $notificacion = $cliente->notificaciones()->create([
            'fecha' => '2026-09-15',
            'asunto' => 'Orden de Pago N° 001-2026',
            'emisor' => 'SUNAT',
            'leido' => false,
            'es_importante' => true,
        ]);

        $this->assertFalse((bool) $notificacion->leido);

        // Primer toggle: de false a true
        $response = $this->from(route('clientes.show', $cliente))
            ->patch(route('notificaciones.toggle', $notificacion));

        $response->assertRedirect(route('clientes.show', $cliente));
        $response->assertSessionHas('success', 'Notificación marcada como leída.');
        $this->assertTrue((bool) $notificacion->fresh()->leido);

        // Segundo toggle: de true a false
        $response = $this->from(route('clientes.show', $cliente))
            ->patch(route('notificaciones.toggle', $notificacion));

        $response->assertRedirect(route('clientes.show', $cliente));
        $response->assertSessionHas('success', 'Notificación marcada como pendiente.');
        $this->assertFalse((bool) $notificacion->fresh()->leido);
    }

    public function test_can_store_or_update_operacion_periodo(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA OPERACIONES S.A.C.',
            'ruc' => '20666666666',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        // 1. Guardar nueva operación de un periodo
        $response = $this->post(route('clientes.operaciones.store', $cliente), [
            'periodo' => '01-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Procesando',
            'estado_ple' => 'Pendiente',
        ]);

        $response->assertRedirect(route('clientes.operaciones', $cliente));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Procesando',
            'estado_ple' => 'Pendiente',
        ]);

        // 2. Actualizar la misma operación del periodo
        $response2 = $this->post(route('clientes.operaciones.store', $cliente), [
            'periodo' => '01-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
        ]);

        $response2->assertRedirect(route('clientes.operaciones', $cliente));

        // No debe duplicar registros por periodo
        $this->assertDatabaseCount('operaciones', 1);
        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'estado_pdt621' => 'Declarado',
        ]);
    }

    public function test_show_view_includes_12_months_operations(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA MESES S.A.C.',
            'ruc' => '20777777777',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertViewHas('mesesOperaciones', function ($meses) {
            return count($meses) === 12
                && $meses[0]['nombre_mes'] === 'Enero'
                && $meses[11]['nombre_mes'] === 'Diciembre';
        });
    }

    public function test_cliente_auto_assigns_consecutive_numero_and_reuses_gaps(): void
    {
        $c1 = Cliente::create([
            'razon_social' => 'CLIENTE UNO SAC',
            'ruc' => '20100000001',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        $this->assertEquals(1, $c1->numero);

        $c2 = Cliente::create([
            'razon_social' => 'CLIENTE DOS SAC',
            'ruc' => '20100000002',
            'regimen_tributario' => 'RER',
            'estado' => true,
        ]);
        $this->assertEquals(2, $c2->numero);

        $c3 = Cliente::create([
            'razon_social' => 'CLIENTE TRES SAC',
            'ruc' => '20100000003',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);
        $this->assertEquals(3, $c3->numero);

        // Eliminar permanentemente cliente 2 (libera el número 2)
        $c2->forceDelete();

        // El siguiente cliente nuevo debe reutilizar el número 2 vacío
        $c4 = Cliente::create([
            'razon_social' => 'CLIENTE CUATRO REEMPLAZO SAC',
            'ruc' => '20100000004',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        $this->assertEquals(2, $c4->numero);

        // El siguiente cliente debe tomar el número que sigue (4)
        $c5 = Cliente::create([
            'razon_social' => 'CLIENTE CINCO SIGUIENTE SAC',
            'ruc' => '20100000005',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        $this->assertEquals(4, $c5->numero);
    }

    public function test_destroy_cliente_route_deletes_all_related_data_cleanly(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE PARA ELIMINAR SAC',
            'ruc' => '20999000111',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $credencial = $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'Clave123*',
        ]);

        $notificacion = $cliente->notificaciones()->create([
            'fecha' => '2026-09-15',
            'asunto' => 'Orden de pago SUNAT',
            'emisor' => 'SUNAT',
            'leido' => false,
        ]);

        $operacion = $cliente->operaciones()->create([
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
            'notas' => 'Nota a eliminar',
        ]);

        // 1. Destroy realiza soft delete preservando datos para auditoría
        $response = $this->delete(route('clientes.destroy', $cliente));

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
        $this->assertDatabaseHas('credenciales', ['id' => $credencial->id]);
        $this->assertDatabaseHas('notificaciones', ['id' => $notificacion->id]);
        $this->assertDatabaseHas('operaciones', ['id' => $operacion->id]);

        // 2. Purgar definitivamente elimina físicamente el cliente y sus relaciones
        $responsePurge = $this->delete(route('clientes.forzar-eliminacion', $cliente->id));
        $responsePurge->assertRedirect(route('clientes.papelera'));

        $this->assertDatabaseMissing('clientes', ['id' => $cliente->id]);
        $this->assertDatabaseMissing('credenciales', ['id' => $credencial->id]);
        $this->assertDatabaseMissing('notificaciones', ['id' => $notificacion->id]);
        $this->assertDatabaseMissing('operaciones', ['id' => $operacion->id]);
    }
}
