{{-- Menú anclado al botón SUNAT de la fila. Vive en el documento, no en la
     ventana, para que el scroll no lo despegue del cliente. --}}
<template x-teleport="body">
    <div x-show="menuSunat.abierto"
         x-cloak
         @click.outside="menuSunat.abierto = false"
         @keydown.escape.window="menuSunat.abierto = false"
         class="absolute z-50 w-80 overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-2xl"
         :style="`top:${menuSunat.top}px;left:${menuSunat.left}px`">
        <div class="bg-emerald-600 px-4 py-3 text-white">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-100">Accesos SUNAT</p>
            <p class="truncate text-sm font-semibold" x-text="menuSunat.nombre"></p>
            <p class="mt-0.5 text-[11px] text-emerald-100">Elige el módulo. La sesión se abre sola.</p>
        </div>
        <div class="space-y-0.5 p-2">
            @foreach (\App\Support\AccesosSunat::destinos() as $codigo => $destino)
                <button type="button"
                        @click="elegirAccesoSunat('{{ $codigo }}')"
                        class="flex w-full items-start gap-3 rounded-xl px-2.5 py-2.5 text-left transition hover:bg-emerald-50">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">{{ $loop->iteration }}</span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-900">{{ $destino['etiqueta'] }}</span>
                        <span class="block text-[11px] leading-4 text-gray-500">{{ $destino['detalle'] }}</span>
                    </span>
                </button>
            @endforeach
        </div>
        <p class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-400">Clic en cualquier otro lado para cerrar</p>
    </div>
</template>
@once
    <script>
        window.posicionarMenuAccesosSunat = function (menu) {
            const ancla = document.querySelector('[data-ancla-sunat="1"]');
            if (!ancla || !menu) {
                return;
            }
            const rect = ancla.getBoundingClientRect();
            const ancho = 320;
            const alto = 390;
            let top = rect.bottom + window.scrollY + 8;
            if (rect.bottom + alto > window.innerHeight && rect.top > alto) {
                top = rect.top + window.scrollY - alto - 8;
            }
            let left = rect.left + window.scrollX;
            const maxLeft = window.scrollX + window.innerWidth - ancho - 12;
            if (left > maxLeft) {
                left = Math.max(window.scrollX + 8, maxLeft);
            }
            menu.top = Math.round(top);
            menu.left = Math.round(left);
        };
    </script>
@endonce
