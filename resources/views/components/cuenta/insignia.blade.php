@props([
    'rol' => null,
    'admin' => false,
    'variante' => 'mixto',
    'tamano' => 'sm',
])

@php
    $rolInsignia = $rol ?: ($admin ? 'admin' : 'asistente');
    $clave = in_array($variante, \App\Models\User::INSIGNIAS, true) ? $variante : 'mixto';
    $emoji = \App\Models\User::emojiDe($rolInsignia, $clave);
    $medidas = [
        'sm' => ['disco' => 'h-3.5 w-3.5', 'glifo' => 'text-[9px]'],
        'md' => ['disco' => 'h-5 w-5', 'glifo' => 'text-sm'],
        'lg' => ['disco' => 'h-6 w-6', 'glifo' => 'text-base'],
    ];
    $fondos = [
        'admin' => ['fondo' => 'bg-gold-400', 'titulo' => 'Administrador', 'dato' => 'administrador'],
        'contador' => ['fondo' => 'bg-emerald-100', 'titulo' => 'Contador', 'dato' => 'contador'],
        'asistente' => ['fondo' => 'bg-blue-100', 'titulo' => 'Asistente', 'dato' => 'asistente'],
    ];
    $medida = $medidas[$tamano] ?? $medidas['sm'];
    $marca = $fondos[$rolInsignia] ?? $fondos['asistente'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex {$medida['disco']} shrink-0 items-center justify-center overflow-hidden rounded-full {$marca['fondo']} ring-2 ring-white"]) }}
      data-insignia-rol="{{ $marca['dato'] }}"
      data-insignia-variante="{{ $clave }}"
      title="{{ $marca['titulo'] }}">
    <span class="select-none {{ $medida['glifo'] }} leading-none" aria-hidden="true">{{ $emoji }}</span>
</span>
