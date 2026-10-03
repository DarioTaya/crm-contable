@props([
    'user',
    'tamano' => 'sm',
])

@php
    $esAdmin = $user->isAdmin();
    $foto = $user->fotoPerfilUrl();
    $medidas = [
        'sm' => ['caja' => 'h-8 w-8', 'texto' => 'text-[11px]'],
        'md' => ['caja' => 'h-11 w-11', 'texto' => 'text-sm'],
        'lg' => ['caja' => 'h-16 w-16', 'texto' => 'text-lg'],
    ];
    $medida = $medidas[$tamano] ?? $medidas['sm'];
    $anillo = $esAdmin ? 'ring-gold-500' : 'ring-slate-300';
    $fondo = $esAdmin ? 'bg-gold-600' : 'bg-slate-600';
@endphp

<span {{ $attributes->merge(['class' => 'relative inline-flex shrink-0']) }}>
    @if ($foto)
        <img src="{{ $foto }}"
             alt=""
             class="{{ $medida['caja'] }} rounded-full object-cover ring-2 {{ $anillo }}">
    @else
        <span class="{{ $medida['caja'] }} inline-flex items-center justify-center rounded-full {{ $medida['texto'] }} font-bold text-white ring-2 {{ $anillo }} {{ $fondo }}">
            {{ $user->iniciales() }}
        </span>
    @endif

    <x-cuenta.insignia :admin="$esAdmin" :tamano="$tamano" class="absolute -bottom-0.5 -right-0.5" />
</span>
