@props(['regimen'])

{{--
    Unico lugar donde se decide como se ve un regimen tributario. Antes este
    mapa de colores estaba copiado en clientes/index, clientes/show,
    control/index y dashboard, ya con variaciones entre copias.
--}}
@php
    $regimen = $regimen instanceof \App\Enums\RegimenTributario
        ? $regimen
        : \App\Enums\RegimenTributario::tryFromNormalizado((string) $regimen);
@endphp

@if ($regimen)
    <x-ui.badge :variant="$regimen->variante()" :title="$regimen->nombreOficial()" {{ $attributes }}>
        {{ $regimen->value }}
    </x-ui.badge>
@else
    <x-ui.badge variant="neutral" {{ $attributes }}>Sin régimen</x-ui.badge>
@endif
