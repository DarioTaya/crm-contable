{{-- Ventana flotante compacta para selección de accesos SUNAT SOL (ubicada junto al botón clickeado) --}}
<template x-teleport="body">
    <div
        id="menu-accesos-sunat-flotante"
        x-show="menuSunat.abierto"
        x-cloak
        data-menu-flotante
        data-separacion="8"
        data-panel-sunat
        @keydown.escape.window="menuSunat.abierto = false"
        @click.outside="if (menuSunat.abierto && !$event.target.closest('[data-ancla-sunat]')) menuSunat.abierto = false"
        class="fixed z-[60] w-88 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xl"
        style="display: none;"
    >
        {{-- Encabezado con Toque Sutil de Marca (Limpio y Sobrio) --}}
        <div class="border-b border-brand-200/60 bg-gradient-to-r from-brand-50 via-white to-brand-50/40 px-4 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white shadow-xs">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-xs font-bold text-slate-900 tracking-tight">Accesos SUNAT</h3>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-200/80">
                            Clave SOL
                        </span>
                    </div>
                    <p class="truncate text-[11px] font-semibold text-slate-700 mt-0.5" x-text="menuSunat.nombre"></p>
                    <p class="text-[10px] font-mono text-slate-500">RUC: <span class="font-semibold text-slate-700" x-text="menuSunat.ruc"></span></p>
                </div>
            </div>
            <button
                type="button"
                @click="menuSunat.abierto = false"
                class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                title="Cerrar ventana"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Opciones con Elevación Suave y Elegante --}}
        <div class="p-2.5 space-y-1.5 bg-slate-50/40">
            @foreach (\App\Support\AccesosSunat::destinos() as $codigo => $destino)
                <button
                    type="button"
                    @click.stop="elegirAccesoSunat('{{ $codigo }}')"
                    class="group cursor-pointer flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200/70 bg-white px-3.5 py-2.5 text-left transition-all duration-200 hover:border-slate-300 hover:bg-slate-50/60 hover:shadow-md hover:-translate-y-0.5 active:scale-[0.99]"
                >
                    <div class="flex items-start gap-2.5 min-w-0 flex-1">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[11px] font-bold text-slate-500 font-mono transition-colors group-hover:bg-slate-200/80 group-hover:text-slate-800">
                            {{ $loop->iteration }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <span class="block text-xs font-semibold text-slate-900 transition-colors group-hover:text-slate-950">{{ $destino['etiqueta'] }}</span>
                            <span class="block text-[11px] leading-tight text-slate-500 mt-0.5">{{ $destino['detalle'] }}</span>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Pie Limpio y Sobrio --}}
        <div class="border-t border-slate-100 bg-white px-4 py-2 flex items-center justify-between text-slate-400">
            <span class="text-[10px] text-slate-500">Inicia sesión en 1 clic como aplicación</span>
            <button
                type="button"
                @click="menuSunat.abierto = false"
                class="cursor-pointer text-[10px] font-semibold text-slate-600 hover:text-slate-900 transition"
            >
                Cerrar
            </button>
        </div>
    </div>
</template>
@once
    <script>
        window.posicionarMenuAccesosSunat = function (ancla) {
            const panel = document.getElementById('menu-accesos-sunat-flotante');
            const target = (ancla instanceof HTMLElement) ? ancla : document.querySelector('[data-ancla-sunat="1"]');
            if (panel && target && typeof window.ubicarMenuFlotante === 'function') {
                window.ubicarMenuFlotante(panel, target);
            }
        };
    </script>
@endonce
