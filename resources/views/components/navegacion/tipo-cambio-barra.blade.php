@props(['tipoCambio' => []])

@php
    $anioInicial = (int) ($tipoCambio['anio'] ?? now('America/Lima')->year);
    $mesInicial = (int) ($tipoCambio['mes'] ?? now('America/Lima')->month);
    $vigenteInicial = $tipoCambio['vigente'] ?? null;
@endphp

<div
    x-data="{
        abierto: false,
        cargando: false,
        cargado: false,

        anio: {{ $anioInicial }},
        mes: {{ $mesInicial }},
        nombreMes: '',
        esMesActual: true,
        mesAnterior: null,
        mesSiguiente: null,
        vigente: @js($vigenteInicial),
        semanas: [],
        diasSemana: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
        tieneDatos: false,
        urlCompleta: '{{ route('tipo-cambio.index') }}',

        cacheMeses: {},

        alternar() {
            this.abierto = !this.abierto;
            if (this.abierto) {
                this.$dispatch('topbar-panel', { panel: 'tipo-cambio' });
                if (!this.cargado) {
                    this.irMes(this.anio, this.mes);
                }
                this.$nextTick(() => this.ubicar());
            }
        },

        ubicar() {
            if (window.ubicarMenuFlotante && this.$refs.panel && this.$refs.boton) {
                window.ubicarMenuFlotante(this.$refs.panel, this.$refs.boton);
            }
        },

        async irMes(anio, mes) {
            if (!anio || !mes || this.cargando) return;

            const clave = anio + '-' + mes;
            if (this.cacheMeses[clave]) {
                this.aplicarDatos(this.cacheMeses[clave]);
                return;
            }

            this.cargando = true;
            try {
                const url = '{{ route('tipo-cambio.mes') }}?anio=' + encodeURIComponent(anio) + '&mes=' + encodeURIComponent(mes);
                const resp = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.cacheMeses[clave] = data;
                    this.aplicarDatos(data);
                }
            } catch (e) {
                // Silencioso en caso de error de red
            } finally {
                this.cargando = false;
                this.cargado = true;
            }
        },

        aplicarDatos(data) {
            this.anio = data.anio;
            this.mes = data.mes;
            this.nombreMes = data.nombre_mes;
            this.esMesActual = data.es_mes_actual;
            this.mesAnterior = data.mes_anterior;
            this.mesSiguiente = data.mes_siguiente;
            if (data.vigente) {
                this.vigente = data.vigente;
            }
            this.semanas = data.semanas || [];
            this.tieneDatos = !!data.tiene_datos;
            this.urlCompleta = data.url_completa;
            this.$nextTick(() => this.ubicar());
        }
    }"
    class="relative shrink-0"
    @keydown.escape.window="abierto = false"
    @topbar-panel.window="if ($event.detail.panel !== 'tipo-cambio') abierto = false"
>
    {{-- Botón disparador en el Topbar con el color corporativo de la marca --}}
    <button
        type="button"
        x-ref="boton"
        data-tipo-cambio-boton
        @click="alternar()"
        :class="abierto ? 'bg-gray-100 text-brand-700 ring-2 ring-brand-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'"
        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200/80 bg-white shadow-2xs transition cursor-pointer"
        title="Tipo de Cambio SUNAT"
        aria-label="Tipo de Cambio SUNAT"
        :aria-expanded="abierto ? 'true' : 'false'"
    >
        <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
    </button>

    {{-- Ventana Flotante de Acceso Rápido (Solo Consulta) --}}
    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="abierto"
            x-cloak
            data-menu-flotante
            data-abrir="abajo"
            data-separacion="8"
            @click.outside="if (!$event.target.closest('[data-tipo-cambio-boton]') && !$el._clicInicioDentro) abierto = false"
            role="dialog"
            aria-label="Tipo de cambio SUNAT"
            class="fixed z-50 w-[min(33rem,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white text-slate-800 shadow-2xl flex flex-col max-h-[85vh] overflow-hidden"
            style="display: none;"
        >
            {{-- Encabezado de la Ventana Flotante con estilo de marca y periodo resaltado --}}
            <div class="shrink-0 border-b border-slate-200 bg-slate-50/75 px-3.5 py-2.5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-50 text-brand-600 shrink-0 border border-brand-200/50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-xs font-bold text-slate-900 tracking-tight leading-none truncate">Tipo de Cambio SUNAT</h3>
                                <span class="rounded bg-slate-100 px-1 py-0.2 text-[9px] font-bold text-slate-600 border border-slate-200 leading-none">USD/PEN</span>
                            </div>
                            <div class="mt-1 flex items-center">
                                <span
                                    class="inline-flex items-center text-[11px] font-bold text-brand-900 bg-brand-50/90 border border-brand-200/70 rounded px-2 py-0.5 leading-none shadow-2xs tracking-wide"
                                    x-text="nombreMes || 'Cargando cotizaciones...'"
                                ></span>
                            </div>
                        </div>
                    </div>

                    {{-- Controles de Navegación de Mes --}}
                    <div class="flex items-center gap-1 shrink-0">
                        {{-- Mes anterior --}}
                        <button
                            type="button"
                            @click="if (mesAnterior) irMes(mesAnterior.anio, mesAnterior.mes)"
                            :disabled="!mesAnterior || cargando"
                            :class="mesAnterior && !cargando ? 'hover:bg-slate-200 text-slate-700' : 'opacity-40 cursor-not-allowed text-slate-400'"
                            class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white shadow-2xs transition cursor-pointer"
                            title="Mes anterior"
                            aria-label="Mes anterior"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </button>

                        {{-- Volver al mes actual si no estamos en él --}}
                        <button
                            type="button"
                            x-show="!esMesActual"
                            x-cloak
                            @click="irMes({{ $anioInicial }}, {{ $mesInicial }})"
                            :disabled="cargando"
                            class="h-7 px-2.5 text-[10px] font-semibold rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 shadow-2xs transition cursor-pointer"
                            title="Volver al mes actual"
                        >
                            Hoy
                        </button>

                        {{-- Mes siguiente --}}
                        <button
                            type="button"
                            @click="if (mesSiguiente) irMes(mesSiguiente.anio, mesSiguiente.mes)"
                            :disabled="!mesSiguiente || cargando"
                            :class="mesSiguiente && !cargando ? 'hover:bg-slate-200 text-slate-700' : 'opacity-40 cursor-not-allowed text-slate-400'"
                            class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white shadow-2xs transition cursor-pointer"
                            title="Mes siguiente"
                            aria-label="Mes siguiente"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Contenedor del Calendario con Scroll Inteligente --}}
            <div class="flex-1 overflow-y-auto p-3 custom-scrollbar min-h-[180px] relative">
                {{-- Spinner de carga cuando se navega de mes --}}
                <div x-show="cargando" x-cloak class="absolute inset-0 bg-white/80 z-20 flex items-center justify-center backdrop-blur-2xs">
                    <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                        <svg class="h-4 w-4 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Cargando cotizaciones...</span>
                    </div>
                </div>

                <template x-if="tieneDatos && semanas.length > 0">
                    <table class="w-full table-fixed border-collapse text-left">
                        <thead>
                            <tr>
                                <template x-for="dia in diasSemana" :key="dia">
                                    <th class="border-b border-slate-200 pb-1.5 text-center text-[11px] font-bold text-slate-500 uppercase tracking-tight" x-text="dia"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(semana, idxSemana) in semanas" :key="idxSemana">
                                <tr>
                                    <template x-for="(celda, idxDia) in semana" :key="idxDia">
                                        <td
                                            class="border border-slate-100 p-1.5 align-top h-14 transition"
                                            :class="{
                                                'bg-brand-50/60 ring-1 ring-inset ring-brand-500/40': celda.hoy,
                                                'bg-slate-50/50': !celda.del_mes,
                                                'bg-white hover:bg-slate-50/50': celda.del_mes && !celda.hoy
                                            }"
                                        >
                                            <div class="flex items-center justify-between leading-none mb-1">
                                                <span
                                                    class="text-[11px] tabular-nums font-bold"
                                                    :class="{
                                                        'text-brand-700 font-black': celda.hoy,
                                                        'text-slate-800': celda.del_mes && !celda.hoy,
                                                        'text-slate-300': !celda.del_mes
                                                    }"
                                                    x-text="celda.dia"
                                                ></span>
                                                <template x-if="celda.hoy">
                                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand-600" title="Hoy"></span>
                                                </template>
                                            </div>

                                            <template x-if="celda.del_mes && celda.compra && celda.venta">
                                                <div class="space-y-0.5 leading-none">
                                                    <div class="flex items-center justify-between text-[10px] tabular-nums font-bold text-emerald-800 bg-emerald-50/95 rounded px-1 py-0.5 border border-emerald-200/50" title="Compra">
                                                        <span class="text-[9px] font-extrabold text-emerald-600">C</span>
                                                        <span class="tracking-tight" x-text="celda.compra"></span>
                                                    </div>
                                                    <div class="flex items-center justify-between text-[10px] tabular-nums font-bold text-sky-800 bg-sky-50/95 rounded px-1 py-0.5 border border-sky-200/50" title="Venta">
                                                        <span class="text-[9px] font-extrabold text-sky-600">V</span>
                                                        <span class="tracking-tight" x-text="celda.venta"></span>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="celda.del_mes && (!celda.compra || !celda.venta)">
                                                <div class="h-7 flex items-center justify-center">
                                                    <span class="text-[10px] text-slate-300 select-none font-medium">—</span>
                                                </div>
                                            </template>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>

                <template x-if="!tieneDatos || semanas.length === 0">
                    <div class="py-6 px-4 text-center">
                        <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500 mb-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-slate-800">Este mes no tiene cotizaciones guardadas</p>
                        <p class="text-[11px] text-slate-500 mt-1">Para consultar o sincronizar meses anteriores, puedes hacerlo desde la página de Tipo de Cambio.</p>
                        <a
                            :href="urlCompleta"
                            class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-bold shadow-xs hover:bg-brand-700 transition cursor-pointer"
                        >
                            <span>Ir a Tipo de Cambio</span>
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>
                </template>
            </div>

            {{-- Pie de la Ventana Flotante --}}
            <div class="shrink-0 flex items-center justify-between border-t border-slate-200 bg-slate-50/50 px-3.5 py-2">
                <span class="text-[10px] text-slate-400">Tipo de cambio oficial SUNAT / SBS</span>
                <a
                    :href="urlCompleta"
                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-brand-700 hover:text-brand-800 transition"
                >
                    <span>Abrir módulo completo</span>
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>
        </div>
    </template>
</div>
