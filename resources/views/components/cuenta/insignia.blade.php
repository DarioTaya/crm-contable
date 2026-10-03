@props([
    'rol' => null,
    'admin' => false,
    'tamano' => 'sm',
])

@php
    $rolInsignia = $rol ?: ($admin ? 'admin' : 'asistente');
    $medidas = [
        'sm' => ['disco' => 'h-3.5 w-3.5', 'glifo' => 'text-[9px]'],
        'md' => ['disco' => 'h-5 w-5', 'glifo' => 'text-sm'],
        'lg' => ['disco' => 'h-6 w-6', 'glifo' => 'text-base'],
    ];
    $marcas = [
        'admin' => ['emoji' => '👑', 'fondo' => 'bg-gold-400', 'titulo' => 'Administrador', 'dato' => 'administrador'],
        'contador' => ['emoji' => '🧑', 'fondo' => 'bg-emerald-100', 'titulo' => 'Contador', 'dato' => 'contador'],
        'asistente' => ['emoji' => '👷', 'fondo' => 'bg-blue-100', 'titulo' => 'Asistente', 'dato' => 'asistente'],
    ];
    $medida = $medidas[$tamano] ?? $medidas['sm'];
    $marca = $marcas[$rolInsignia] ?? $marcas['asistente'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex {$medida['disco']} shrink-0 items-center justify-center overflow-hidden rounded-full {$marca['fondo']} ring-2 ring-white"]) }}
      data-insignia-rol="{{ $marca['dato'] }}"
      title="{{ $marca['titulo'] }}">
    <span class="select-none {{ $medida['glifo'] }} leading-none" aria-hidden="true">{{ $marca['emoji'] }}</span>
</span>
