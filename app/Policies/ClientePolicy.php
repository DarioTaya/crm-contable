<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/**
 * Autorizacion sobre la cartera de clientes.
 *
 * El aislamiento entre estudios ya lo garantiza EstudioScope a nivel de
 * consulta. Esta politica resuelve la segunda dimension: dentro de un mismo
 * estudio, un asistente solo alcanza los clientes que tiene asignados, mientras
 * que el administrador gobierna toda la cartera.
 */
class ClientePolicy
{
    /**
     * El administrador del estudio tiene acceso total dentro de su inquilino.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->activo) {
            return false;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $this->tieneAsignado($user, $cliente);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Cubre la edicion de datos fiscales, el estado en el estudio y la
     * sincronizacion con el padron SUNAT.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        return $this->tieneAsignado($user, $cliente);
    }

    /**
     * Enviar a papelera, restaurar y eliminar definitivamente quedan
     * reservados al administrador mediante `before`.
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        return false;
    }

    /**
     * Lectura y escritura de Clave SOL, AFP, detracciones y bancos.
     */
    public function gestionarCredenciales(User $user, Cliente $cliente): bool
    {
        return $this->tieneAsignado($user, $cliente);
    }

    /**
     * Disparar scraping de buzones o el inicio de sesion asistido en los
     * portales de SUNAT y SUNAFIL para un cliente concreto.
     */
    public function sincronizar(User $user, Cliente $cliente): bool
    {
        return $this->tieneAsignado($user, $cliente);
    }

    /**
     * Registrar declaraciones, balances y honorarios del cliente.
     */
    public function registrarOperaciones(User $user, Cliente $cliente): bool
    {
        return $this->tieneAsignado($user, $cliente);
    }

    private function tieneAsignado(User $user, Cliente $cliente): bool
    {
        return $cliente->asistentes()->whereKey($user->getKey())->exists();
    }
}
