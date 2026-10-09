<?php

namespace Tests\Unit;

use App\Enums\SeccionExpediente;
use PHPUnit\Framework\TestCase;

class SeccionExpedienteTest extends TestCase
{
    public function test_el_enlace_antiguo_y_la_ruta_resuelven_la_misma_seccion(): void
    {
        $this->assertSame(SeccionExpediente::Notificaciones, SeccionExpediente::desde('notificaciones'));
        $this->assertSame(SeccionExpediente::Operaciones, SeccionExpediente::desde('operaciones'));
        $this->assertSame(SeccionExpediente::Creditos, SeccionExpediente::desde('creditos'));
        $this->assertSame(SeccionExpediente::Sire, SeccionExpediente::desde('sire'));
        $this->assertSame(SeccionExpediente::Datos, SeccionExpediente::desde('datos'));
        $this->assertSame(SeccionExpediente::Datos, SeccionExpediente::desde('api_credentials'));
        $this->assertSame(SeccionExpediente::Datos, SeccionExpediente::desde('desconocida'));
        $this->assertSame(SeccionExpediente::Datos, SeccionExpediente::desde(null));
        $this->assertSame(SeccionExpediente::Datos, SeccionExpediente::desde(['notificaciones']));

        $this->assertSame(SeccionExpediente::Notificaciones, SeccionExpediente::actual('clientes.notificaciones'));
        $this->assertSame(SeccionExpediente::Operaciones, SeccionExpediente::actual('clientes.operaciones'));
        $this->assertSame(SeccionExpediente::Creditos, SeccionExpediente::actual('clientes.creditos'));
        $this->assertSame(SeccionExpediente::Sire, SeccionExpediente::actual('clientes.sire'));
        $this->assertSame(SeccionExpediente::Datos, SeccionExpediente::actual('clientes.show'));
        $this->assertSame(SeccionExpediente::Datos, SeccionExpediente::actual(null));
    }
}
