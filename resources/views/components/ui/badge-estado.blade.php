@props(['estado'])

{{-- Situacion del cliente dentro del estudio: activo / suspendido / retirado. --}}
@php
    $estado = \App\Enums\EstadoCliente::desde($estado);
@endphp

<x-ui.badge :variant="$estado->variante()" dot {{ $attributes }}>{{ $estado->etiqueta() }}</x-ui.badge>
