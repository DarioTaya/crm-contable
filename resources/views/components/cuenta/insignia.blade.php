@props([
    'admin' => false,
    'tamano' => 'sm',
])

@php
    $corona = [
        'sm' => ['disco' => 'h-3.5 w-3.5', 'glifo' => 'text-[9px]'],
        'md' => ['disco' => 'h-5 w-5', 'glifo' => 'text-sm'],
        'lg' => ['disco' => 'h-6 w-6', 'glifo' => 'text-base'],
    ];
    $maletin = [
        'sm' => ['caja' => 'h-3.5 w-3.5', 'icono' => 'h-2 w-2'],
        'md' => ['caja' => 'h-4 w-4', 'icono' => 'h-2.5 w-2.5'],
        'lg' => ['caja' => 'h-[18px] w-[18px]', 'icono' => 'h-3 w-3'],
    ];
    $medidaCorona = $corona[$tamano] ?? $corona['sm'];
    $medidaMaletin = $maletin[$tamano] ?? $maletin['sm'];
@endphp

@if ($admin)
    <span {{ $attributes->merge(['class' => "inline-flex {$medidaCorona['disco']} shrink-0 items-center justify-center overflow-hidden rounded-full bg-gold-400 ring-2 ring-white"]) }}
          data-insignia-rol="administrador"
          title="Administrador">
        <span class="select-none {{ $medidaCorona['glifo'] }} leading-none" aria-hidden="true">👑</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => "inline-flex {$medidaMaletin['caja']} shrink-0 items-center justify-center rounded-full bg-slate-600 ring-2 ring-white"]) }}
          data-insignia-rol="asistente"
          title="Asistente">
        <svg class="{{ $medidaMaletin['icono'] }} text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M9 6.2V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5v.7h3.2A1.8 1.8 0 0 1 20 8v9.2a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 17.2V8a1.8 1.8 0 0 1 1.8-1.8H9Zm1.5 0h3V5.5h-3v.7Z" />
        </svg>
    </span>
@endif
