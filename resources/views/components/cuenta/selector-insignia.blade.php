@props([
    'fijo' => null,
])

@php
    $catalogo = \App\Models\User::catalogoInsignias();
    $rolFijo = is_string($fijo) && array_key_exists($fijo, $catalogo) ? $fijo : null;
    $opciones = [
        'hombre' => 'Hombre',
        'mujer' => 'Mujer',
        'mixto' => 'Mixto',
    ];
    $fondo = match ($rolFijo) {
        'admin' => 'bg-gold-400',
        'contador' => 'bg-emerald-100',
        default => 'bg-blue-100',
    };
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <div class="flex items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-700">Marca en la foto</p>
            <p class="mt-0.5 text-xs leading-5 text-gray-500">Elige una de tres. Esa marca queda en la foto del menú superior.</p>
        </div>
        <span class="relative inline-flex shrink-0">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-600 text-[11px] font-bold text-white ring-2 ring-slate-300">Aa</span>
            @foreach ($opciones as $clave => $etiqueta)
                <span x-show="insignia === '{{ $clave }}'"
                      x-cloak
                      class="absolute -bottom-0.5 -right-0.5 inline-flex h-3.5 w-3.5 items-center justify-center overflow-hidden rounded-full ring-2 ring-white {{ $rolFijo ? $fondo : '' }}"
                      @unless ($rolFijo)
                          :class="rol === 'admin' ? 'bg-gold-400' : (rol === 'contador' ? 'bg-emerald-100' : 'bg-blue-100')"
                      @endunless>
                    <span class="select-none text-[9px] leading-none" aria-hidden="true">
                        @if ($rolFijo)
                            {{ $catalogo[$rolFijo][$clave]['emoji'] }}
                        @else
                            <span x-text="marcas[rol]['{{ $clave }}']"></span>
                        @endif
                    </span>
                </span>
            @endforeach
        </span>
    </div>

    <div class="grid grid-cols-1 gap-2">
        @foreach ($opciones as $clave => $etiqueta)
            <label class="flex min-w-0 cursor-pointer items-center gap-3 rounded-xl border p-3 transition"
                   :class="insignia === '{{ $clave }}' ? 'border-brand-600 bg-brand-50/40 ring-2 ring-brand-500/20' : 'border-gray-200 bg-white hover:bg-gray-50'">
                <input type="radio"
                       name="insignia"
                       value="{{ $clave }}"
                       x-model="insignia"
                       @change="insigniaTocada = true"
                       class="sr-only">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-xl leading-none">
                    @if ($rolFijo)
                        {{ $catalogo[$rolFijo][$clave]['emoji'] }}
                    @else
                        <span x-text="marcas[rol]['{{ $clave }}']"></span>
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-bold text-gray-900">{{ $etiqueta }}</span>
                    <span class="mt-0.5 block text-xs leading-5 text-gray-500">
                        @if ($rolFijo)
                            {{ $catalogo[$rolFijo][$clave]['detalle'] }}
                        @else
                            <span x-text="detalles[rol]['{{ $clave }}']"></span>
                        @endif
                    </span>
                </span>
            </label>
        @endforeach
    </div>
</div>
