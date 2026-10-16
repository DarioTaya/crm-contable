@props(['empresa' => null])

@php
    $clienteRuta = request()->route('cliente');
    $config = [
        'buscarUrl' => route('acceso-rapido.empresas'),
        'seleccionarUrl' => route('acceso-rapido.seleccionar'),
        'fijarUrl' => route('acceso-rapido.fijar'),
        'empresa' => $empresa ? [
            'id' => $empresa->id,
            'razon_social' => $empresa->razon_social,
            'ruc' => $empresa->ruc,
            'url' => route('clientes.show', $empresa),
        ] : null,
        'contextoClienteId' => $clienteRuta instanceof \App\Models\Cliente ? $clienteRuta->id : null,
        'rutaActual' => request()->route()?->getName(),
    ];
@endphp

<div
    class="shrink-0"
    x-data="selectorEmpresa(@js($config))"
    @keydown.escape.window="alEscape($event)"
>
    <button
        type="button"
        x-ref="disparador"
        @click="alternar()"
        :aria-expanded="abierto ? 'true' : 'false'"
        aria-haspopup="dialog"
        aria-controls="selector-empresa-panel"
        title="{{ $empresa ? $empresa->razon_social.' · RUC '.$empresa->ruc : 'Seleccionar empresa' }}"
        :title="empresa ? (empresa.razon_social + ' · RUC ' + empresa.ruc) : 'Seleccionar empresa'"
        class="flex h-9 shrink-0 items-center gap-2 rounded-lg border border-gray-200 bg-white px-1.5 text-left shadow-xs transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300"
        :class="abierto ? 'border-gray-300 bg-gray-50' : ''"
    >
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
            </svg>
        </span>
        <span class="relative block">
            <span class="invisible block whitespace-nowrap text-[11px] font-semibold leading-tight" aria-hidden="true">IMPORTACIONES VIRGEN DE CHAPI SRL</span>
            <span class="invisible mt-0.5 block text-[10px] leading-tight" aria-hidden="true">&nbsp;</span>
            <span class="absolute inset-0 flex min-w-0 flex-col">
                <span class="block min-w-0 truncate text-[11px] font-semibold leading-tight text-gray-900" x-text="empresa ? empresa.razon_social : 'Seleccionar empresa'">
                    {{ $empresa?->razon_social ?? 'Seleccionar empresa' }}
                </span>
                <span class="mt-0.5 block min-w-0 truncate font-mono text-[10px] leading-tight tabular-nums text-gray-500" x-text="empresa ? empresa.ruc : 'Busca por nombre o RUC'">
                    {{ $empresa?->ruc ?? 'Busca por nombre o RUC' }}
                </span>
            </span>
        </span>
        <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform" :class="abierto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25 12 15.75 4.5 8.25" />
        </svg>
    </button>

    <template x-teleport="body">
        <div
            id="selector-empresa-panel"
            x-ref="panel"
            x-show="abierto"
            x-cloak
            data-menu-flotante
            data-alineacion="centro"
            data-separacion="6"
            data-abrir="abajo"
            @click.outside="cerrarDesdeFuera($event, $el)"
            role="dialog"
            aria-label="Empresas del estudio"
            class="fixed z-50 flex max-h-[min(32rem,calc(100vh-2rem))] w-[min(26rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl"
            style="display: none;"
        >
            <div class="shrink-0 border-b border-gray-100 p-2">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        x-ref="buscador"
                        x-model="consulta"
                        @input="alEscribir()"
                        @keydown="alTeclado($event)"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="Buscar empresa..."
                        aria-label="Buscar empresa"
                        class="w-full rounded-lg border border-gray-300 py-1.5 pl-8 pr-8 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                    >
                    <span x-show="cargando" x-cloak class="absolute right-2.5 top-1/2 -translate-y-1/2 text-brand-600" aria-hidden="true">
                        <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                </div>
            </div>

            <div x-ref="lista" class="min-h-0 flex-1 overflow-y-auto py-1">
                <p x-show="errorLista" x-cloak class="px-3 py-6 text-center text-xs text-rose-600" x-text="errorLista"></p>

                <p x-show="!errorLista && !cargando && empresas.length === 0" x-cloak class="px-3 py-6 text-center text-xs text-gray-500">
                    <span x-show="consulta.trim() !== ''">Ninguna empresa coincide con la búsqueda.</span>
                    <span x-show="consulta.trim() === ''">Aún no hay empresas en tu cartera.</span>
                </p>

                <template x-for="(item, index) in empresas" :key="item.id">
                    <div
                        class="group relative flex items-center gap-1.5 px-2 py-1"
                        :data-opcion-activa="index === indiceActivo ? '1' : '0'"
                        :class="index === indiceActivo ? 'bg-brand-50' : 'hover:bg-gray-50'"
                        @mouseenter="indiceActivo = index"
                    >
                        <button
                            type="button"
                            @click.stop="alternarFijada(item)"
                            :title="item.fijada ? 'Quitar de las 5 fijas' : 'Fijar en las 5 del acceso rápido'"
                            :aria-label="item.fijada ? 'Quitar de las empresas fijas' : 'Fijar empresa'"
                            :aria-pressed="item.fijada ? 'true' : 'false'"
                            class="absolute left-6 top-1/2 z-10 flex h-3.5 w-3.5 -translate-y-1/2 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm ring-1 ring-gray-200 transition hover:text-brand-700 focus:outline-none focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-brand-500/40"
                            :class="item.fijada ? 'pointer-events-auto text-brand-700 opacity-100' : 'pointer-events-none opacity-0 group-hover:pointer-events-auto group-hover:opacity-100'"
                        >
                            <svg class="h-2.5 w-2.5" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true" :fill="item.fijada ? 'currentColor' : 'none'">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                            </svg>
                        </button>
                        <div class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-1 py-1 text-left">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-700 select-none">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    {{-- ÚNICAMENTE al dar clic sobre las letras del nombre de la empresa, lleva a su carpeta --}}
                                    <a
                                        :href="item.carpeta"
                                        class="truncate text-xs font-semibold text-gray-900 hover:text-brand-600 hover:underline cursor-pointer focus:outline-none focus-visible:ring-1 focus-visible:ring-brand-500 rounded-xs"
                                        x-text="item.razon_social"
                                        title="Abrir carpeta de esta empresa"
                                    ></a>
                                    <span
                                        x-show="item.estado_etiqueta"
                                        x-cloak
                                        class="shrink-0 rounded bg-gray-100 px-1 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-gray-500 select-none"
                                        x-text="item.estado_etiqueta"
                                    ></span>
                                </div>
                                {{-- RUC: solo informativo, no lleva a la carpeta ni tiene acción de enlace --}}
                                <span class="mt-0.5 block truncate font-mono text-[10px] tabular-nums text-gray-500 select-none" x-text="item.ruc"></span>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                data-accion-sunat
                                @click.stop="abrirSunat($event, item)"
                                title="SUNAT"
                                aria-label="Abrir accesos SUNAT"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 transition hover:bg-emerald-600 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click.stop="lanzarSunafil(item)"
                                title="SUNAFIL"
                                aria-label="Ingresar a SUNAFIL"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-600 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </button>
                            <a
                                :href="item.carpeta"
                                title="Carpeta del cliente"
                                aria-label="Abrir carpeta del cliente"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition hover:bg-brand-600 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </template>

                <p x-show="hayMas && !errorLista" x-cloak class="px-3 py-2 text-center text-[11px] text-gray-400">
                    Hay más empresas. Sigue escribiendo el nombre o el RUC.
                </p>
            </div>

            <a
                href="{{ route('clientes.create') }}"
                class="flex shrink-0 items-center justify-center border-t border-gray-100 px-3 py-2.5 text-center text-sm font-semibold text-brand-700 transition hover:bg-brand-50"
            >
                <span>+ Agregar empresa</span>
            </a>
        </div>
    </template>

    <template x-teleport="body">
        <div
            x-ref="panelSunat"
            x-show="menuSunat.abierto"
            x-cloak
            data-menu-flotante
            data-separacion="8"
            data-panel-sunat
            @click.outside="cerrarSunatDesdeFuera($event, $el)"
            class="fixed z-[60] w-88 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
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
                <button type="button" @click="menuSunat.abierto = false" class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" title="Cerrar">
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
                        @click.stop="elegirAccesoSunat(@js($codigo))"
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

            <div class="border-t border-slate-100 bg-white px-4 py-2 flex items-center justify-between text-slate-400">
                <span class="text-[10px] text-slate-500">Ingreso directo en 1 clic en tu navegador</span>
                <button type="button" @click="menuSunat.abierto = false" class="cursor-pointer text-[10px] font-semibold text-slate-600 hover:text-slate-900 transition">Cerrar</button>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div
            x-show="hud.abierto && !hud.completado"
            x-cloak
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            style="display: none;"
        >
            <div
                class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl"
                @click.away="if (hud.completado || hud.error) hud.abierto = false"
            >
                <div class="mb-4 flex items-center gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border text-2xl shadow-inner"
                        :class="hud.portal === 'SUNAT' ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-blue-200 bg-blue-50 text-blue-600'"
                    >
                        <span x-text="hud.icono"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wider"
                            :class="hud.portal === 'SUNAT' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'"
                            x-text="hud.portal === 'SUNAT' ? 'Portal SUNAT SOL' : 'Casilla Electrónica SUNAFIL'"
                        ></span>
                        <h3 class="mt-1 truncate text-base font-bold text-slate-900" x-text="hud.clienteNombre"></h3>
                        <p class="font-mono text-xs text-slate-500">RUC: <span class="font-semibold text-slate-700" x-text="hud.clienteRuc"></span></p>
                    </div>
                    <template x-if="hud.error">
                        <button type="button" @click="hud.abierto = false" class="text-lg font-bold leading-none text-slate-400 hover:text-slate-600" aria-label="Cerrar">&times;</button>
                    </template>
                </div>

                <template x-if="hud.error">
                    <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs text-rose-700">
                        <span class="text-base">⚠️</span>
                        <div class="flex-1">
                            <p class="font-bold">No se pudo iniciar el acceso automático</p>
                            <p class="mt-0.5" x-text="hud.error"></p>
                        </div>
                    </div>
                </template>

                <template x-if="!hud.error">
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2 font-semibold text-slate-700">
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :class="hud.portal === 'SUNAT' ? 'bg-emerald-500' : 'bg-blue-600'"
                                ></span>
                                <span x-text="hud.faseTexto"></span>
                            </span>
                            <span class="font-mono font-bold tabular-nums text-slate-700" x-text="hud.progreso + '%'"></span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 shadow-inner">
                            <div
                                class="h-full rounded-full transition-all duration-200 ease-out"
                                :class="hud.portal === 'SUNAT' ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-blue-600 to-indigo-500'"
                                :style="'width: ' + hud.progreso + '%'"
                            ></div>
                        </div>
                        <div class="flex items-center justify-between pt-1 text-[11px] text-slate-400">
                            <span x-text="(hud.nombreNavegador || 'Navegador predeterminado') + ' · Perfil sincronizado'"></span>
                            <span x-text="hud.completado ? 'Portal listo en pantalla' : 'Sesión directa 1 clic'"></span>
                        </div>
                    </div>
                </template>

                <template x-if="hud.error">
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="hud.abierto = false" class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                            Entendido
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
