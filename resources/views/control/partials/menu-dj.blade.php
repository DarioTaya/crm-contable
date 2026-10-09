                                            {{-- Miniventana flotante de DJ y tributos. El ancho deja aire entre la leyenda IGV Justo y su interruptor. --}}
                                            <div
                                                x-ref="menuDJ"
                                                x-show="openDJ"
                                                x-cloak
                                                data-menu-flotante
                                                @click.outside="if (!$el._clicInicioDentro && !$event.target.closest('[data-abre-dj]')) { openDJ = false; panelLiquidacionAbierto = false; panelLiquidacionPlameAbierto = false; deudaAbiertaKey = null; }"
                                                class="fixed z-50 flex flex-col rounded-2xl border border-gray-200 bg-white shadow-2xl text-left max-h-[calc(100vh-24px)] overflow-hidden transition-[width] duration-150 ease-out"
                                                :style="esHojaGrande() ? 'width: min(880px, calc(100vw - 280px))' : 'width: 460px'"
                                            >
                                                {{-- Encabezado con Tabs INMOVILIZADO / FROZEN (Fijo arriba sin moverse con el scroll) --}}
                                                <div class="shrink-0 p-3.5 pb-2 bg-white border-b border-gray-100 z-20">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-2 truncate pr-2">
                                                            <span class="inline-flex items-center justify-center rounded bg-gray-100 border border-gray-200 px-1.5 py-0.5 text-[10px] font-mono font-bold text-gray-700" x-text="etiquetaPeriodo"></span>
                                                            <span class="text-xs font-bold text-gray-900 truncate" x-text="razonSocial"></span>
                                                        </div>
                                                        <button type="button" @click="openDJ = false; panelLiquidacionAbierto = false; panelLiquidacionPlameAbierto = false; deudaAbiertaKey = null" class="text-gray-400 hover:text-gray-700 text-base font-bold leading-none p-1">&times;</button>
                                                    </div>

                                                    {{-- 2 Pestañas perfectamente alineadas en una sola línea --}}
                                                    <div class="flex border-b border-gray-200 text-xs font-semibold">
                                                        <button
                                                            type="button"
                                                            @click="activeTab = 'declaracion'; panelLiquidacionAbierto = false; panelLiquidacionPlameAbierto = false; encuadrarMenuDJ()"
                                                            :class="activeTab === 'declaracion' ? 'border-b-2 border-brand-600 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700'"
                                                            class="flex-1 pb-2 text-center transition flex items-center justify-center gap-1.5 whitespace-nowrap"
                                                        >
                                                            <span>Estado Declaración</span>
                                                            <span class="text-[10px]" x-text="meses[mesIdx].estado === 'Declarado' ? '🟢' : (meses[mesIdx].estado === 'Procesando' ? '🟡' : '⚪')"></span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="activeTab = 'deuda'; deudaAbiertaKey = null; panelLiquidacionAbierto = false; panelLiquidacionPlameAbierto = false; encuadrarMenuDJ(); if (!deudaCargada) cargarDeuda()"
                                                            :class="activeTab === 'deuda' ? 'border-b-2 border-brand-600 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700'"
                                                            class="flex-1 pb-2 text-center transition relative flex items-center justify-center gap-1.5 whitespace-nowrap"
                                                        >
                                                            <span>Tributos y Pagos</span>
                                                            <template x-if="meses[mesIdx].tiene_deuda && meses[mesIdx].saldo_pendiente > 0">
                                                                <span class="inline-flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                                                            </template>
                                                            <template x-if="meses[mesIdx].tiene_deuda && meses[mesIdx].saldo_pendiente <= 0">
                                                                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                                            </template>
                                                        </button>
                                                    </div>
                                                </div>

                                                {{-- Contenido Desplazable con Scroll Independiente --}}
                                                <div class="flex-1 overflow-y-auto px-3.5 py-2.5">

                                                {{-- TAB 1: ESTADO DECLARACIÓN (CONTROL INDIVIDUAL: SIRE + PDT 621 + PLAME) --}}
                                                <div x-show="activeTab === 'declaracion'" class="space-y-2.5 py-1">
                                                    {{-- Resumen del Estado Maestro de la DJ mensual --}}
                                                    <div class="rounded-xl border p-2.5 flex items-center justify-between gap-2"
                                                         :class="meses[mesIdx].estado === 'Declarado' ? 'border-emerald-200 bg-emerald-50/70' : (meses[mesIdx].estado === 'Procesando' ? 'border-amber-200 bg-amber-50/70' : 'border-slate-200 bg-slate-50/70')">
                                                        <div class="min-w-0">
                                                            <span class="text-[9px] font-bold uppercase tracking-wider text-gray-500 block">Estado General DJ</span>
                                                            <p class="text-xs font-bold truncate"
                                                               :class="meses[mesIdx].estado === 'Declarado' ? 'text-emerald-800' : (meses[mesIdx].estado === 'Procesando' ? 'text-amber-800' : 'text-slate-700')"
                                                               x-text="meses[mesIdx].estado === 'Declarado' ? '🟢 Declarado (3 de 3 listos)' : (meses[mesIdx].estado === 'Procesando' ? '🟡 En Proceso (Incompleto)' : '⚪ Pendiente (Sin iniciar)')">
                                                            </p>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 shrink-0">
                                                            <button type="button" @click="marcarTodoEstado('Declarado')"
                                                                    title="Marcar SIRE, PDT 621 y PLAME como Declarado"
                                                                    class="px-2 py-1 rounded-lg text-[10px] font-bold bg-white text-emerald-700 border border-emerald-300 hover:bg-emerald-100 transition shadow-2xs">
                                                                Todo 🟢
                                                            </button>
                                                            <button type="button" @click="marcarTodoEstado('Pendiente')"
                                                                    title="Marcar todos como Pendiente"
                                                                    class="px-2 py-1 rounded-lg text-[10px] font-bold bg-white text-slate-600 border border-slate-300 hover:bg-slate-100 transition shadow-2xs">
                                                                Todo ⚪
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- OBLIGACIÓN 1: SIRE (Compras y Ventas) --}}
                                                    <div class="rounded-xl border border-slate-200 bg-white p-2.5 space-y-1.5">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-xs font-bold text-gray-900">SIRE</span>
                                                            <span class="text-[10px] text-gray-500 font-medium">· Compras y Ventas (RCE / RVIE)</span>
                                                        </div>
                                                        <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-0.5 border border-slate-200/80">
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('sire', 'Pendiente')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="(meses[mesIdx].sire === 'Pendiente' || !meses[mesIdx].sire) ? 'bg-white text-slate-900 shadow-xs border border-slate-300 font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">⚪</span>
                                                                <span>Pendiente</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('sire', 'Procesando')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="meses[mesIdx].sire === 'Procesando' ? 'bg-amber-50 text-amber-900 border border-amber-300 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">🟡</span>
                                                                <span>En Proceso</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('sire', 'Declarado')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="meses[mesIdx].sire === 'Declarado' ? 'bg-emerald-50 text-emerald-900 border border-emerald-300 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">🟢</span>
                                                                <span>Declarado</span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- OBLIGACIÓN 2: PDT 621 (IGV - Renta Mensual) --}}
                                                    <div class="rounded-xl border border-slate-200 bg-white p-2.5 space-y-1.5">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-xs font-bold text-gray-900">PDT 621</span>
                                                            <span class="text-[10px] text-gray-500 font-medium">· IGV y Renta Mensual</span>
                                                            <span x-show="regimen === 'RUS'" class="rounded bg-amber-100 px-1 py-0.2 text-[8px] font-bold text-amber-800 uppercase whitespace-nowrap">NRUS</span>
                                                        </div>
                                                        <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-0.5 border border-slate-200/80">
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('pdt', 'Pendiente')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="(meses[mesIdx].pdt === 'Pendiente' || !meses[mesIdx].pdt) ? 'bg-white text-slate-900 shadow-xs border border-slate-300 font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">⚪</span>
                                                                <span>Pendiente</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('pdt', 'Procesando')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="meses[mesIdx].pdt === 'Procesando' ? 'bg-amber-50 text-amber-900 border border-amber-300 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">🟡</span>
                                                                <span>En Proceso</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('pdt', 'Declarado')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="meses[mesIdx].pdt === 'Declarado' ? 'bg-emerald-50 text-emerald-900 border border-emerald-300 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">🟢</span>
                                                                <span>Declarado</span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- OBLIGACIÓN 3: PLAME / PDT 601 (Planilla Electrónica) --}}
                                                    <div class="rounded-xl border border-slate-200 bg-white p-2.5 space-y-1.5">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-xs font-bold text-gray-900">PLAME</span>
                                                            <span class="text-[10px] text-gray-500 font-medium">· PDT 601 (Planilla, EsSalud, ONP, AFP)</span>
                                                        </div>
                                                        <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-0.5 border border-slate-200/80">
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('ple', 'Pendiente')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="(meses[mesIdx].ple === 'Pendiente' || !meses[mesIdx].ple) ? 'bg-white text-slate-900 shadow-xs border border-slate-300 font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">⚪</span>
                                                                <span>Pendiente</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('ple', 'Procesando')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="meses[mesIdx].ple === 'Procesando' ? 'bg-amber-50 text-amber-900 border border-amber-300 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">🟡</span>
                                                                <span>En Proceso</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="cambiarEstadoIndividual('ple', 'Declarado')"
                                                                class="flex items-center justify-center gap-1 py-1.5 px-2 rounded-md text-[11px] transition"
                                                                :class="meses[mesIdx].ple === 'Declarado' ? 'bg-emerald-50 text-emerald-900 border border-emerald-300 shadow-xs font-bold' : 'text-slate-500 hover:text-slate-800 hover:bg-white/60 font-medium'"
                                                            >
                                                                <span class="text-[10px]">🟢</span>
                                                                <span>Declarado</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- TAB 2: TRIBUTOS Y PAGOS (PDT 621 + PDT 601) --}}
                                                <div x-show="activeTab === 'deuda'" class="space-y-3 py-1">
                                                    <p class="text-[11px] leading-snug text-gray-500">
                                                        Digite lo que salió a pagar en cada declaración. Los pagos siguen aquí. Solo el saldo por pagar entra a Control de Deudas.
                                                    </p>

                                                    <div class="grid grid-cols-3 gap-2">
                                                        <div class="rounded-xl bg-gray-50 border border-gray-200 p-2 text-center">
                                                            <p class="text-[9px] font-bold uppercase text-gray-500 tracking-wider">A pagar</p>
                                                            <p class="text-xs font-black text-gray-800 mt-0.5" x-text="formatearMoneda(totalMontoAPagar())"></p>
                                                        </div>
                                                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-2 text-center">
                                                            <p class="text-[9px] font-bold uppercase text-emerald-600 tracking-wider">Pagado</p>
                                                            <p class="text-xs font-black text-emerald-600 mt-0.5" x-text="formatearMoneda(totalMontoPagado())"></p>
                                                        </div>
                                                        <div class="rounded-xl p-2 text-center border"
                                                             :class="totalMontoPorPagar() > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'">
                                                            <p class="text-[9px] font-bold uppercase tracking-wider"
                                                               :class="totalMontoPorPagar() > 0 ? 'text-red-600' : 'text-gray-500'">Por pagar</p>
                                                            <p class="text-xs font-black mt-0.5"
                                                               :class="totalMontoPorPagar() > 0 ? 'text-red-600' : 'text-gray-600'"
                                                               x-text="formatearMoneda(totalMontoPorPagar())"></p>
                                                        </div>
                                                    </div>

                                                    <template x-for="bloque in declaraciones" :key="bloque.id">
                                                        <section class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                                                            <div class="flex items-center justify-between gap-2 border-b px-3 py-2"
                                                                 :class="bloque.id === 'pdt_601' ? 'border-indigo-200 bg-indigo-100' : 'border-slate-300 bg-slate-200'">
                                                                <p class="text-[11px] font-black tracking-wide text-gray-900">
                                                                    <span x-text="bloque.titulo"></span>
                                                                    <span class="font-semibold text-gray-400"> · </span>
                                                                    <span class="font-semibold text-gray-600" x-text="bloque.detalle"></span>
                                                                </p>
                                                                <span class="text-[10px] font-mono font-bold shrink-0"
                                                                      x-show="resumenBloque(bloque.id).hay"
                                                                      :class="resumenBloque(bloque.id).saldo > 0 ? 'text-rose-600' : 'text-emerald-600'"
                                                                      x-text="formatearMoneda(resumenBloque(bloque.id).saldo)"></span>
                                                            </div>

                                                            <div class="space-y-2 p-2">
                                                                <p x-show="bloque.id === 'pdt_621' && regimen === 'RUS'" class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-[10px] leading-snug text-amber-900">
                                                                    NRUS no presenta PDT 621. Use esta sección solo si el régimen del cliente no corresponde.
                                                                </p>

                                                                {{-- ACORDEÓN: HOJA DE LIQUIDACIÓN TRIBUTARIA PDT 621 (IGV & RENTA) --}}
                                                                <div x-show="bloque.id === 'pdt_621'" class="rounded-xl border border-blue-200 bg-gradient-to-b from-blue-50/70 to-slate-50 overflow-hidden shadow-2xs">
                                                                    {{-- Botón Cabecera del Acordeón --}}
                                                                    <div role="button" tabindex="0" @click="panelLiquidacionAbierto = !panelLiquidacionAbierto; if (panelLiquidacionAbierto) encuadrarMenuDJ()"
                                                                         @keydown.enter.prevent="panelLiquidacionAbierto = !panelLiquidacionAbierto; if (panelLiquidacionAbierto) encuadrarMenuDJ()"
                                                                         @keydown.space.prevent="panelLiquidacionAbierto = !panelLiquidacionAbierto; if (panelLiquidacionAbierto) encuadrarMenuDJ()"
                                                                         class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left hover:bg-blue-100/50 transition cursor-pointer">
                                                                        <div class="flex items-center gap-2 min-w-0">
                                                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-blue-600 text-white text-[11px] font-black">📑</span>
                                                                            <div class="min-w-0">
                                                                                <p class="text-xs font-bold text-blue-950 flex items-center gap-1.5 truncate">
                                                                                    <span>Hoja de Liquidación PDT 621</span>
                                                                                    <span class="text-[9px] font-semibold text-blue-700 bg-blue-100 px-1.5 py-0.2 rounded-full">PDT 621 · IGV y Renta 3ra</span>
                                                                                </p>
                                                                                <p class="text-[10px] text-blue-800/80 truncate">
                                                                                    Determina IGV y Renta 3ra con arrastre automático de saldos a favor, retenciones, percepciones e ITAN
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                        <div class="flex items-center gap-1.5 shrink-0">
                                                                            <button type="button" @click.stop="panelLiquidacionAbierto = true; importacion['621'].abierta = true; $nextTick(() => encuadrarMenuDJ())"
                                                                                    class="inline-flex items-center rounded-md border border-blue-300 bg-white px-2 py-0.5 text-[10px] font-bold text-blue-800 hover:bg-blue-50 shadow-2xs transition cursor-pointer">
                                                                                📥 Importar DJ
                                                                            </button>
                                                                            <button type="button" @click.stop="limpiarHojaLiquidacion()" :disabled="savingLiquidacion"
                                                                                    title="Restablecer o limpiar la hoja de liquidación PDT 621 a cero"
                                                                                    class="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 shadow-2xs transition cursor-pointer">
                                                                                <span>🗑️</span>
                                                                                <span>Limpiar</span>
                                                                            </button>
                                                                            <span x-show="liquidacion.igv_saldo_favor_siguiente > 0" class="text-[9px] font-bold bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded border border-emerald-200" title="Saldo a favor para siguiente mes">
                                                                                Favor M+1: S/ <span x-text="formatearNumeroConComas(liquidacion.igv_saldo_favor_siguiente)"></span>
                                                                            </span>
                                                                            <svg class="h-4 w-4 text-blue-700 transition" :class="panelLiquidacionAbierto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                                            </svg>
                                                                        </div>
                                                                    </div>

                                                                    {{-- Contenedor Desplegable de la Hoja de Liquidación --}}
                                                                    <div x-show="panelLiquidacionAbierto" class="border-t border-blue-200/80 p-3 space-y-3 bg-white">
                                                                        @include('control.partials.importar-declaracion', ['formulario' => '621', 'tono' => 'blue'])
                                                                        {{-- Banner / Switch de Régimen Amazonía - Selva (Ley 27037) --}}
                                                                        <div class="flex items-center justify-between p-2 rounded-lg border transition"
                                                                             :class="liquidacion.es_zona_selva ? 'bg-emerald-50 border-emerald-300 shadow-xs' : 'bg-slate-50 border-slate-200'">
                                                                            <div class="flex items-center gap-2 min-w-0">
                                                                                <span class="text-base shrink-0">🌴</span>
                                                                                <div class="min-w-0">
                                                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                                                        <span class="font-bold text-[11px]" :class="liquidacion.es_zona_selva ? 'text-emerald-950' : 'text-slate-700'">
                                                                                            Régimen Amazonía / Selva (Ley 27037)
                                                                                        </span>
                                                                                        <span x-show="liquidacion.es_zona_selva" class="text-[9px] font-bold bg-emerald-600 text-white px-1.5 py-0.2 rounded-full">
                                                                                            Exonerado IGV
                                                                                        </span>
                                                                                    </div>
                                                                                    <p class="text-[9.5px] truncate" :class="liquidacion.es_zona_selva ? 'text-emerald-800' : 'text-slate-500'">
                                                                                        <span x-show="liquidacion.es_zona_selva">Ventas exoneradas de IGV. Las ventas se aplican directamente para el cálculo de Renta.</span>
                                                                                        <span x-show="!liquidacion.es_zona_selva">Activar si el cliente opera en la Amazonía (sin IGV en ventas ni compras).</span>
                                                                                    </p>
                                                                                </div>
                                                                            </div>
                                                                            <button type="button" @click="toggleZonaSelva()"
                                                                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                                                                    :class="liquidacion.es_zona_selva ? 'bg-emerald-600' : 'bg-slate-300'"
                                                                                    :title="liquidacion.es_zona_selva ? 'Desactivar Régimen Selva' : 'Activar Régimen Selva (Ley 27037)'">
                                                                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                                                                      :class="liquidacion.es_zona_selva ? 'translate-x-4' : 'translate-x-0'"></span>
                                                                            </button>
                                                                        </div>

                                                                        {{-- Selector de Sub-pestañas: IGV vs RTA 3RA --}}
                                                                        <div class="flex rounded-lg bg-slate-100 p-0.5 text-xs font-bold border border-slate-200/70">
                                                                            <button type="button" @click="tabLiquidacion = 'igv'"
                                                                                    class="flex-1 py-1.5 text-center rounded-md transition text-xs"
                                                                                    :class="tabLiquidacion === 'igv' ? 'bg-white text-blue-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'">
                                                                                <span>IGV (1011)</span>
                                                                                <span class="ml-1 text-[10px]"
                                                                                      :class="liquidacion.es_zona_selva ? 'text-emerald-600 font-bold' : (liquidacion.igv_resultante_pagar > 0 ? 'text-rose-600 font-bold' : (liquidacion.igv_saldo_favor_siguiente > 0 ? 'text-emerald-600 font-bold' : 'text-slate-400'))"
                                                                                      x-text="liquidacion.es_zona_selva ? '· Exonerado' : (liquidacion.igv_resultante_pagar > 0 ? '· Pagar S/ ' + formatearNumeroConComas(liquidacion.igv_resultante_pagar) : (liquidacion.igv_saldo_favor_siguiente > 0 ? '· Favor S/ ' + formatearNumeroConComas(liquidacion.igv_saldo_favor_siguiente) : ''))"></span>
                                                                            </button>
                                                                            <button type="button" @click="tabLiquidacion = 'renta'"
                                                                                    class="flex-1 py-1.5 text-center rounded-md transition text-xs"
                                                                                    :class="tabLiquidacion === 'renta' ? 'bg-white text-indigo-900 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'">
                                                                                <span>RTA 3RA</span>
                                                                                <span class="ml-1 text-[10px]"
                                                                                      :class="liquidacion.renta_resultante_pagar > 0 ? 'text-rose-600 font-bold' : 'text-emerald-600'"
                                                                                      x-text="liquidacion.renta_resultante_pagar > 0 ? '· Pagar S/ ' + formatearNumeroConComas(liquidacion.renta_resultante_pagar) : (limpiarMoneda(liquidacion.renta_base_ingresos) > 0 ? '· S/ 0.00' : '')"></span>
                                                                            </button>
                                                                        </div>

                                                                        {{-- PANEL 1: IGV (1011) - REPLICA EXACTA SUNAT FORMULARIO VIRTUAL 621 --}}
                                                                        <div x-show="tabLiquidacion === 'igv'" class="space-y-3">
                                                                            {{-- Cuadrícula 2 Columnas: 1. Ventas & 2. Compras --}}
                                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 items-start">
                                                                                {{-- 1. VENTAS (DÉBITO FISCAL) --}}
                                                                                <div class="rounded-xl border border-blue-200/80 bg-slate-50/80 p-2.5 space-y-2">
                                                                                    <div class="flex items-center justify-between border-b border-blue-100 pb-1">
                                                                                        <span class="text-[10px] font-black uppercase tracking-wider text-blue-950 flex items-center gap-1">
                                                                                            <span>1. Ventas</span>
                                                                                            <span class="text-[9px] text-blue-600 font-semibold">(Débito Fiscal)</span>
                                                                                        </span>
                                                                                        <span class="text-[9px] font-mono font-bold text-blue-800" x-show="!liquidacion.es_zona_selva">
                                                                                            Cas. 131: S/ <span x-text="formatearNumeroConComas(totalDebitoFiscal())"></span>
                                                                                        </span>
                                                                                    </div>

                                                                                    {{-- Casillas Frecuentes de Ventas --}}
                                                                                    <div class="grid grid-cols-2 gap-2">
                                                                                        <div class="col-span-2 sm:col-span-1">
                                                                                            <label class="mb-0.5 block text-[9.5px] font-bold text-slate-700 truncate" x-text="liquidacion.es_zona_selva ? 'Ventas Selva (Cas. 100)' : 'Base Gravada (Cas. 100)'"></label>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.ventas_base"
                                                                                                   @input="liquidacion.ventas_base = formatearMilesInput($event); onVentasBaseInput()"
                                                                                                   placeholder="0.00" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 font-mono text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                                                                        </div>
                                                                                        <div class="col-span-2 sm:col-span-1">
                                                                                            <label class="mb-0.5 block text-[9.5px] font-bold text-slate-700 truncate">Débito Fiscal (Cas. 101)</label>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.igv_ventas" :readonly="liquidacion.es_zona_selva"
                                                                                                   @input="liquidacion.igv_ventas = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                   placeholder="0.00" class="w-full rounded-lg border px-2 py-1 font-mono text-xs font-semibold"
                                                                                                   :class="liquidacion.es_zona_selva ? 'border-slate-200 bg-slate-100 text-slate-400' : 'border-slate-300 bg-white text-blue-950 focus:border-blue-500 focus:ring-1 focus:ring-blue-500'">
                                                                                        </div>
                                                                                        <div class="col-span-2" x-show="!liquidacion.es_zona_selva">
                                                                                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                                                                                <label class="text-[9px] font-medium text-slate-600">Ventas No Gravadas (Cas. 105)</label>
                                                                                                <span class="text-[8px] text-slate-400">Sin IGV · Suma a Renta</span>
                                                                                            </div>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.ventas_no_gravadas"
                                                                                                   @input="liquidacion.ventas_no_gravadas = formatearMilesInput($event); onVentasNoGravadasInput()"
                                                                                                   placeholder="0.00" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 font-mono text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                                                                        </div>
                                                                                    </div>

                                                                                    {{-- Desplegable de Casillas Secundarias de Ventas --}}
                                                                                    <div x-show="!liquidacion.es_zona_selva" class="pt-0.5">
                                                                                        <button type="button" @click="masCasillasVentas = !masCasillasVentas"
                                                                                                class="text-[9.5px] font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition">
                                                                                            <span x-text="(masCasillasVentas || tieneValoresMasVentas()) ? '▾ Ocultar casillas secundarias' : '▸ + Más casillas de ventas (Descuentos, Ley 31556...)'"></span>
                                                                                        </button>

                                                                                        <div x-show="masCasillasVentas || tieneValoresMasVentas()" class="mt-1.5 p-2 rounded-lg bg-white border border-slate-200 grid grid-cols-2 gap-2">
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">Descuentos Vtas (Cas. 102)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_descuentos_base"
                                                                                                       @input="liquidacion.igv_descuentos_base = formatearMilesInput($event); onDescuentosBaseInput()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">IGV Descuentos (Cas. 103)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_descuentos"
                                                                                                       @input="liquidacion.igv_descuentos = formatearMilesInput($event); sincronizarBaseRenta(); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">Base Ley 31556 (Cas. 154)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_ventas_ley_base"
                                                                                                       @input="liquidacion.igv_ventas_ley_base = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">IGV Ley 31556 (Cas. 155)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_ventas_ley"
                                                                                                       @input="liquidacion.igv_ventas_ley = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                    {{-- Pie de Ventas: Total Débito Fiscal --}}
                                                                                    <div class="flex items-center justify-between pt-1 border-t border-slate-200/70 text-[10px]">
                                                                                        <span class="font-bold text-slate-600">Total Débito Fiscal (Cas. 131):</span>
                                                                                        <span class="font-black font-mono text-blue-900" x-text="'S/ ' + formatearNumeroConComas(totalDebitoFiscal())"></span>
                                                                                    </div>
                                                                                </div>

                                                                                {{-- 2. COMPRAS (CRÉDITO FISCAL) --}}
                                                                                <div class="rounded-xl border border-blue-200/80 bg-slate-50/80 p-2.5 space-y-2">
                                                                                    <div class="flex items-center justify-between border-b border-blue-100 pb-1">
                                                                                        <span class="text-[10px] font-black uppercase tracking-wider text-blue-950 flex items-center gap-1">
                                                                                            <span>2. Compras</span>
                                                                                            <span class="text-[9px] text-blue-600 font-semibold">(Crédito Fiscal)</span>
                                                                                        </span>
                                                                                        <span class="text-[9px] font-mono font-bold text-blue-800" x-show="!liquidacion.es_zona_selva">
                                                                                            Cas. 178: S/ <span x-text="formatearNumeroConComas(totalCreditoFiscal())"></span>
                                                                                        </span>
                                                                                    </div>

                                                                                    {{-- Casillas Frecuentes de Compras --}}
                                                                                    <div class="grid grid-cols-2 gap-2">
                                                                                        <div class="col-span-2 sm:col-span-1">
                                                                                            <label class="mb-0.5 block text-[9.5px] font-bold text-slate-700 truncate" x-text="liquidacion.es_zona_selva ? 'Compras Selva (Cas. 107)' : 'Destino Gravado (Cas. 107)'"></label>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.compras_base"
                                                                                                   @input="liquidacion.compras_base = formatearMilesInput($event); onComprasBaseInput()"
                                                                                                   placeholder="0.00" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 font-mono text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                                                                        </div>
                                                                                        <div class="col-span-2 sm:col-span-1">
                                                                                            <label class="mb-0.5 block text-[9.5px] font-bold text-slate-700 truncate">Crédito Fiscal (Cas. 108)</label>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.igv_compras" :readonly="liquidacion.es_zona_selva"
                                                                                                   @input="liquidacion.igv_compras = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                   placeholder="0.00" class="w-full rounded-lg border px-2 py-1 font-mono text-xs font-semibold"
                                                                                                   :class="liquidacion.es_zona_selva ? 'border-slate-200 bg-slate-100 text-slate-400' : 'border-slate-300 bg-white text-blue-950 focus:border-blue-500 focus:ring-1 focus:ring-blue-500'">
                                                                                        </div>
                                                                                        <div class="col-span-2" x-show="!liquidacion.es_zona_selva">
                                                                                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                                                                                <label class="text-[9px] font-medium text-slate-600">Internas No Gravadas (Cas. 120)</label>
                                                                                                <span class="text-[8px] text-slate-400">Sin Crédito Fiscal</span>
                                                                                            </div>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.compras_internas_no_gravadas"
                                                                                                   @input="liquidacion.compras_internas_no_gravadas = formatearMilesInput($event)"
                                                                                                   placeholder="0.00" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 font-mono text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                                                                        </div>
                                                                                    </div>

                                                                                    {{-- Desplegable de Casillas Secundarias de Compras --}}
                                                                                    <div x-show="!liquidacion.es_zona_selva" class="pt-0.5">
                                                                                        <button type="button" @click="masCasillasCompras = !masCasillasCompras"
                                                                                                class="text-[9.5px] font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 transition">
                                                                                            <span x-text="(masCasillasCompras || tieneValoresMasCompras()) ? '▾ Ocultar casillas secundarias' : '▸ + Más casillas de compras (Mixtas, Importaciones...)'"></span>
                                                                                        </button>

                                                                                        <div x-show="masCasillasCompras || tieneValoresMasCompras()" class="mt-1.5 p-2 rounded-lg bg-white border border-slate-200 grid grid-cols-2 gap-2">
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">Compras Mixtas (Cas. 110)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_compras_mixtas_base"
                                                                                                       @input="liquidacion.igv_compras_mixtas_base = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">IGV Mixtas (Cas. 111)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_compras_mixtas"
                                                                                                       @input="liquidacion.igv_compras_mixtas = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">Importaciones (Cas. 114)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_importaciones_base"
                                                                                                       @input="liquidacion.igv_importaciones_base = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">IGV Import. (Cas. 115)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_importaciones"
                                                                                                       @input="liquidacion.igv_importaciones = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">Import. Mixtas (Cas. 116)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_importaciones_mixtas_base"
                                                                                                       @input="liquidacion.igv_importaciones_mixtas_base = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">IGV Imp. Mixtas (Cas. 117)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_importaciones_mixtas"
                                                                                                       @input="liquidacion.igv_importaciones_mixtas = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">No Gravadas (Cas. 113)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.compras_destino_no_gravadas"
                                                                                                       @input="liquidacion.compras_destino_no_gravadas = formatearMilesInput($event)"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">Import. No Grav. (Cas. 122)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.compras_importadas_no_gravadas"
                                                                                                       @input="liquidacion.compras_importadas_no_gravadas = formatearMilesInput($event)"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">Compras Ley 31556 (Cas. 156)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_compras_ley_base"
                                                                                                       @input="liquidacion.igv_compras_ley_base = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="mb-0.5 block text-[8.5px] font-medium text-slate-600">IGV Ley 31556 (Cas. 157)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_compras_ley"
                                                                                                       @input="liquidacion.igv_compras_ley = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 px-1.5 py-0.5 font-mono text-xs text-slate-800">
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                    {{-- Pie de Compras: Total Crédito Fiscal --}}
                                                                                    <div class="flex items-center justify-between pt-1 border-t border-slate-200/70 text-[10px]">
                                                                                        <span class="font-bold text-slate-600">Total Crédito Fiscal (Cas. 178):</span>
                                                                                        <span class="font-black font-mono text-blue-900" x-text="'S/ ' + formatearNumeroConComas(totalCreditoFiscal())"></span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            {{-- 3. DETERMINACIÓN OFICIAL DE LA DEUDA SUNAT (PÁGINA 2 DEL FV 621) --}}
                                                                            <div x-show="!liquidacion.es_zona_selva" class="rounded-xl border border-slate-200 bg-white p-3 space-y-2.5 shadow-2xs">
                                                                                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                                                                                    <span class="text-[10.5px] font-black uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                                                                                        <span>3. Determinación de la Deuda</span>
                                                                                        <span class="text-[9px] font-semibold text-slate-500">(Página 2 PDT 621)</span>
                                                                                    </span>
                                                                                    <span class="text-[9.5px] text-slate-500 font-medium">Secuencia oficial SUNAT</span>
                                                                                </div>

                                                                                {{-- PASO A: Impuesto Resultante o Saldo a Favor del Mes (Casilla 140) --}}
                                                                                <div class="flex items-center justify-between p-2 rounded-lg border transition"
                                                                                     :class="casilla140ImpuestoMes() > 0 ? 'bg-amber-50/70 border-amber-200 text-amber-950' : (casilla140ImpuestoMes() < 0 ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950' : 'bg-slate-50 border-slate-200 text-slate-800')">
                                                                                    <div>
                                                                                        <div class="flex items-center gap-1.5">
                                                                                            <span class="text-[9.5px] font-black">Casilla 140:</span>
                                                                                            <span class="text-[9.5px] font-bold"
                                                                                                  x-text="casilla140ImpuestoMes() > 0 ? 'Impuesto Resultante del Mes (Débito − Crédito)' : (casilla140ImpuestoMes() < 0 ? 'Saldo a Favor del Mes (Crédito > Débito)' : 'Impuesto Resultante / Saldo a Favor')"></span>
                                                                                        </div>
                                                                                        <p class="text-[8.5px] text-slate-500 mt-0.5">
                                                                                            <span x-text="'Débito S/ ' + formatearNumeroConComas(totalDebitoFiscal()) + ' − Crédito S/ ' + formatearNumeroConComas(totalCreditoFiscal())"></span>
                                                                                        </p>
                                                                                    </div>
                                                                                    <div class="text-right">
                                                                                        <span class="font-mono text-xs font-black"
                                                                                              :class="casilla140ImpuestoMes() > 0 ? 'text-amber-900' : (casilla140ImpuestoMes() < 0 ? 'text-emerald-800' : 'text-slate-700')"
                                                                                              x-text="casilla140ImpuestoMes() > 0 ? ('S/ ' + formatearNumeroConComas(casilla140ImpuestoMes())) : (casilla140ImpuestoMes() < 0 ? ('- S/ ' + formatearNumeroConComas(Math.abs(casilla140ImpuestoMes()))) : 'S/ 0.00')"></span>
                                                                                        <span class="block text-[8px] font-bold uppercase"
                                                                                              :class="casilla140ImpuestoMes() > 0 ? 'text-amber-700' : (casilla140ImpuestoMes() < 0 ? 'text-emerald-600' : 'text-slate-400')"
                                                                                              x-text="casilla140ImpuestoMes() > 0 ? 'Tributo previo' : (casilla140ImpuestoMes() < 0 ? 'A favor contribuyente' : 'Neutro')"></span>
                                                                                    </div>
                                                                                </div>

                                                                                {{-- PASO B: Saldo a Favor del Periodo Anterior (Casilla 145) --}}
                                                                                <div class="p-2 rounded-lg bg-slate-50/80 border border-slate-200">
                                                                                    <div class="flex items-center justify-between gap-1 mb-1">
                                                                                        <label class="text-[9.5px] text-slate-800 font-bold flex items-center gap-1">
                                                                                            <span>Saldo Favor Periodo Anterior (Casilla 145)</span>
                                                                                        </label>
                                                                                        <div class="flex items-center gap-1">
                                                                                            <span x-show="saldoFavorSugerido > 0" class="text-[8.5px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded border border-emerald-200" title="Arrastre automático de M-1">
                                                                                                Auto M-1: S/ <span x-text="formatearNumeroConComas(saldoFavorSugerido)"></span>
                                                                                            </span>
                                                                                            <button type="button" x-show="saldoFavorSugerido > 0"
                                                                                                    @click="aplicarSaldoAnteriorSugerido()"
                                                                                                    class="text-[8px] text-emerald-700 hover:text-emerald-900 underline font-semibold" title="Recargar saldo de M-1">
                                                                                                Reaplicar
                                                                                            </button>
                                                                                        </div>
                                                                                    </div>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.igv_saldo_favor_anterior"
                                                                                           @input="liquidacion.igv_saldo_favor_anterior = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                           placeholder="0.00" class="w-full rounded-lg border border-slate-300 bg-white py-1 px-2 text-xs font-mono text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                                                                </div>

                                                                                {{-- PASO C: Tributo a Pagar o Saldo a Favor (Casilla 184) --}}
                                                                                <div class="flex items-center justify-between p-2 rounded-lg border transition"
                                                                                     :class="casilla184TributoResultante() > 0 ? 'bg-blue-50/60 border-blue-200 text-blue-950' : (casilla184TributoResultante() < 0 ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : 'bg-slate-50 border-slate-200 text-slate-800')">
                                                                                    <div>
                                                                                        <div class="flex items-center gap-1.5">
                                                                                            <span class="text-[9.5px] font-black">Casilla 184:</span>
                                                                                            <span class="text-[9.5px] font-bold"
                                                                                                  x-text="casilla184TributoResultante() > 0 ? 'Tributo a Pagar (antes de ret./perc.)' : (casilla184TributoResultante() < 0 ? 'Saldo a Favor Acumulado (para mes siguiente)' : 'Tributo a Pagar o Saldo a Favor')"></span>
                                                                                        </div>
                                                                                        <p class="text-[8.5px] text-slate-500 mt-0.5">
                                                                                            <span>Casilla 140 menos Casilla 145</span>
                                                                                        </p>
                                                                                    </div>
                                                                                    <div class="text-right">
                                                                                        <span class="font-mono text-xs font-black"
                                                                                              :class="casilla184TributoResultante() > 0 ? 'text-blue-900' : (casilla184TributoResultante() < 0 ? 'text-emerald-800' : 'text-slate-700')"
                                                                                              x-text="casilla184TributoResultante() > 0 ? ('S/ ' + formatearNumeroConComas(casilla184TributoResultante())) : (casilla184TributoResultante() < 0 ? ('S/ ' + formatearNumeroConComas(Math.abs(casilla184TributoResultante()))) : 'S/ 0.00')"></span>
                                                                                        <span class="block text-[8px] font-bold uppercase"
                                                                                              :class="casilla184TributoResultante() > 0 ? 'text-blue-700' : (casilla184TributoResultante() < 0 ? 'text-emerald-600' : 'text-slate-400')"
                                                                                              x-text="casilla184TributoResultante() > 0 ? 'A deducir ret/perc' : (casilla184TributoResultante() < 0 ? 'Pasa a Cas. 145 M+1' : 'Saldo S/ 0')"></span>
                                                                                    </div>
                                                                                </div>

                                                                                {{-- PASO D: Deducción de Retenciones y Percepciones --}}
                                                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 pt-1">
                                                                                    {{-- Retenciones de IGV (Casillas 176, 179 y 165) --}}
                                                                                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 space-y-1.5">
                                                                                        <div class="flex items-center justify-between">
                                                                                            <span class="text-[9.5px] font-bold text-slate-800">Menos: Retenciones de IGV</span>
                                                                                            <span class="text-[8.5px] font-mono font-bold text-blue-700" x-show="liquidacion.igv_retenciones_saldo_siguiente > 0" title="Retenciones no aplicadas que pasan a M+1">
                                                                                                Pasa M+1 (Cas. 165): S/ <span x-text="formatearNumeroConComas(liquidacion.igv_retenciones_saldo_siguiente)"></span>
                                                                                            </span>
                                                                                        </div>
                                                                                        <div class="grid grid-cols-2 gap-1.5">
                                                                                            <div>
                                                                                                <div class="flex items-center justify-between gap-0.5 mb-0.5">
                                                                                                    <label class="text-[8.5px] text-slate-600 font-medium">Saldo Ant. (Cas. 176)</label>
                                                                                                    <button type="button" x-show="retencionesSugeridas > 0" @click="aplicarRetencionesSugeridas()" class="text-[7.5px] text-blue-700 hover:underline font-bold" title="Reaplicar retenciones M-1">Auto M-1</button>
                                                                                                </div>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_retenciones_saldo_anterior"
                                                                                                       @input="liquidacion.igv_retenciones_saldo_anterior = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-0.5 px-1.5 text-xs font-mono text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="text-[8.5px] text-slate-600 font-medium block mb-0.5">Del Periodo (Cas. 179)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_retenciones"
                                                                                                       @input="liquidacion.igv_retenciones = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-0.5 px-1.5 text-xs font-mono text-slate-800">
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="flex items-center justify-between text-[8.5px] text-slate-500 pt-0.5">
                                                                                            <span>Deducidas este mes:</span>
                                                                                            <span class="font-mono font-bold text-slate-700" x-text="'S/ ' + formatearNumeroConComas(retencionesAplicadas())"></span>
                                                                                        </div>
                                                                                    </div>

                                                                                    {{-- Percepciones de IGV (Casillas 168, 171 y 164) --}}
                                                                                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 space-y-1.5">
                                                                                        <div class="flex items-center justify-between">
                                                                                            <span class="text-[9.5px] font-bold text-slate-800">Menos: Percepciones de IGV</span>
                                                                                            <span class="text-[8.5px] font-mono font-bold text-amber-700" x-show="liquidacion.igv_percepciones_saldo_siguiente > 0" title="Percepciones no aplicadas que pasan a M+1">
                                                                                                Pasa M+1 (Cas. 164): S/ <span x-text="formatearNumeroConComas(liquidacion.igv_percepciones_saldo_siguiente)"></span>
                                                                                            </span>
                                                                                        </div>
                                                                                        <div class="grid grid-cols-2 gap-1.5">
                                                                                            <div>
                                                                                                <div class="flex items-center justify-between gap-0.5 mb-0.5">
                                                                                                    <label class="text-[8.5px] text-slate-600 font-medium">Saldo Ant. (Cas. 168)</label>
                                                                                                    <button type="button" x-show="percepcionesSugeridas > 0" @click="aplicarPercepcionesSugeridas()" class="text-[7.5px] text-amber-700 hover:underline font-bold" title="Reaplicar percepciones M-1">Auto M-1</button>
                                                                                                </div>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_percepciones_saldo_anterior"
                                                                                                       @input="liquidacion.igv_percepciones_saldo_anterior = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-0.5 px-1.5 text-xs font-mono text-slate-800">
                                                                                            </div>
                                                                                            <div>
                                                                                                <label class="text-[8.5px] text-slate-600 font-medium block mb-0.5">Del Periodo (Cas. 171)</label>
                                                                                                <input type="text" inputmode="decimal" x-model="liquidacion.igv_percepciones"
                                                                                                       @input="liquidacion.igv_percepciones = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                       placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-0.5 px-1.5 text-xs font-mono text-slate-800">
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="flex items-center justify-between text-[8.5px] text-slate-500 pt-0.5">
                                                                                            <span>Deducidas este mes:</span>
                                                                                            <span class="font-mono font-bold text-slate-700" x-text="'S/ ' + formatearNumeroConComas(percepcionesAplicadas())"></span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            {{-- TARJETA RESULTADO DETERMINADO DE IGV (CASILLA 188 / 189) --}}
                                                                            <div class="rounded-xl p-3 border transition shadow-2xs"
                                                                                 :class="liquidacion.es_zona_selva ? 'bg-emerald-50 border-emerald-300' : (liquidacion.igv_resultante_pagar > 0 ? 'bg-rose-50 border-rose-200' : (liquidacion.igv_saldo_favor_siguiente > 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200'))">
                                                                                <div class="flex items-center justify-between gap-2">
                                                                                    <div>
                                                                                        <p class="text-[9.5px] font-black uppercase tracking-wider"
                                                                                           :class="liquidacion.es_zona_selva ? 'text-emerald-800' : (liquidacion.igv_resultante_pagar > 0 ? 'text-rose-700' : (liquidacion.igv_saldo_favor_siguiente > 0 ? 'text-emerald-700' : 'text-slate-600'))"
                                                                                           x-text="liquidacion.es_zona_selva ? '🌴 Exonerado de IGV (Amazonía Ley 27037)' : (liquidacion.igv_resultante_pagar > 0 ? 'IGV a pagar (Casilla 189)' : (liquidacion.igv_saldo_favor_siguiente > 0 ? 'Saldo a favor de IGV para M+1 (Casilla 184)' : 'Resultado IGV: S/ 0.00'))"></p>
                                                                                        <p class="text-base font-black mt-0.5 font-mono"
                                                                                           :class="liquidacion.es_zona_selva ? 'text-emerald-800' : (liquidacion.igv_resultante_pagar > 0 ? 'text-rose-700' : (liquidacion.igv_saldo_favor_siguiente > 0 ? 'text-emerald-700' : 'text-slate-700'))"
                                                                                           x-text="liquidacion.es_zona_selva ? 'S/ 0.00 (Exonerado)' : ('S/ ' + formatearNumeroConComas(liquidacion.igv_resultante_pagar > 0 ? liquidacion.igv_resultante_pagar : liquidacion.igv_saldo_favor_siguiente))"></p>
                                                                                        <p x-show="cruceDj.igv !== null" class="mt-0.5 text-[10px] font-semibold"
                                                                                           :class="cruceCuadra('igv') ? 'text-emerald-700' : 'text-amber-700'"
                                                                                           x-text="cruceCuadra('igv') ? '🟢 Cuadra con la DJ oficial de SUNAT' : ('⚠️ La DJ de SUNAT indica S/ ' + formatearNumeroConComas(cruceDj.igv))"></p>
                                                                                    </div>
                                                                                    <div class="flex flex-col items-end gap-1.5">
                                                                                        <span x-show="!liquidacion.es_zona_selva && liquidacion.igv_resultante_pagar > 0" class="text-[10px] font-bold text-rose-700 bg-rose-100/90 px-2 py-0.5 rounded-md border border-rose-200">
                                                                                            Tributo 1011 · IGV
                                                                                        </span>
                                                                                        <span x-show="liquidacion.es_zona_selva" class="text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md border border-emerald-200">
                                                                                            Ley 27037 Selva
                                                                                        </span>
                                                                                        <div class="flex items-center gap-1 flex-wrap justify-end">
                                                                                            <span x-show="liquidacion.igv_retenciones_saldo_siguiente > 0" class="text-[8.5px] font-bold text-blue-700 bg-blue-100 px-1.5 py-0.2 rounded" title="Retenciones que pasan a Casilla 176 de M+1">
                                                                                                Ret. M+1: S/ <span x-text="formatearNumeroConComas(liquidacion.igv_retenciones_saldo_siguiente)"></span>
                                                                                            </span>
                                                                                            <span x-show="liquidacion.igv_percepciones_saldo_siguiente > 0" class="text-[8.5px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.2 rounded" title="Percepciones que pasan a Casilla 168 de M+1">
                                                                                                Perc. M+1: S/ <span x-text="formatearNumeroConComas(liquidacion.igv_percepciones_saldo_siguiente)"></span>
                                                                                            </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        {{-- PANEL 2: RENTA MENSUAL (3111 / 3121 / 3031) --}}
                                                                        <div x-show="tabLiquidacion === 'renta'" class="space-y-2.5">
                                                                            {{-- BANNER EJECUTIVO: RÉGIMEN TRIBUTARIO Y CONTROL DE LÍMITES SUNAT --}}
                                                                            <div class="rounded-xl border p-2.5 transition shadow-2xs"
                                                                                 :class="(regimen || '').toUpperCase() === 'RER' ? 'border-blue-200 bg-blue-50/70' : ((regimen || '').toUpperCase() === 'RMT' ? 'border-indigo-200 bg-indigo-50/70' : ((regimen || '').toUpperCase() === 'RUS' ? 'border-amber-200 bg-amber-50/70' : 'border-purple-200 bg-purple-50/70'))">
                                                                                <div class="flex items-center justify-between gap-2">
                                                                                    <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                                                                        <span class="text-[9.5px] font-bold text-slate-500 uppercase tracking-wider">Régimen Ficha:</span>
                                                                                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-black border"
                                                                                              :class="(regimen || '').toUpperCase() === 'RER' ? 'bg-blue-100 text-blue-900 border-blue-300' : ((regimen || '').toUpperCase() === 'RMT' ? 'bg-indigo-100 text-indigo-900 border-indigo-300' : ((regimen || '').toUpperCase() === 'RUS' ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-purple-100 text-purple-900 border-purple-300'))">
                                                                                            <span x-text="regimen || 'NO ASIGNADO'"></span>
                                                                                        </span>
                                                                                        <span class="text-[10px] text-slate-600 font-medium"
                                                                                              x-text="(regimen || '').toUpperCase() === 'RER' ? '· Tasa 1.5% fija (Tributo 3111)' : ((regimen || '').toUpperCase() === 'RMT' ? '· Tasa 1.0% hasta 300 UIT (Tributo 3121)' : ((regimen || '').toUpperCase() === 'RUS' ? '· Cuota mensual fija NRUS' : '· Coeficiente / 1.5% (Tributo 3031)'))">
                                                                                        </span>
                                                                                    </div>
                                                                                    <div class="flex items-center gap-1 shrink-0">
                                                                                        <template x-if="(regimen || '').toUpperCase() === 'RER'">
                                                                                            <span class="rounded bg-white/90 border border-blue-200 px-1.5 py-0.5 text-[9px] font-semibold text-blue-800" title="Tope anual de ingresos netos según Art. 119 Ley Impuesto a la Renta">
                                                                                                Tope Ley: S/ 525,000 / año
                                                                                            </span>
                                                                                        </template>
                                                                                    </div>
                                                                                </div>

                                                                                {{-- Alerta interactiva de discrepancia si la DJ oficial difiere de la ficha --}}
                                                                                <div x-show="importacion['621']?.resultado?.regimen_discrepancia" x-cloak
                                                                                     class="mt-2 pt-2 border-t border-amber-200/80 flex items-center justify-between gap-2">
                                                                                    <div class="flex items-center gap-1.5 min-w-0 text-[10px] text-amber-900">
                                                                                        <span class="text-xs">⚠️</span>
                                                                                        <span>La DJ oficial declaró en <strong class="font-bold underline" x-text="importacion['621']?.resultado?.regimen_discrepancia?.dj"></strong> (<span x-text="importacion['621']?.resultado?.regimen_discrepancia?.tasa_dj + '%'"></span>). Su ficha está en <strong x-text="importacion['621']?.resultado?.regimen_discrepancia?.ficha"></strong>.</span>
                                                                                    </div>
                                                                                    <template x-if="importacion['621']?.resultado?.regimen_discrepancia?.puede_cambiar_ficha">
                                                                                        <button type="button"
                                                                                                @click="actualizarRegimenFicha(importacion['621']?.resultado?.regimen_discrepancia?.dj)"
                                                                                                class="shrink-0 px-2 py-0.5 rounded bg-amber-600 hover:bg-amber-700 text-white font-bold text-[9px] shadow-2xs transition cursor-pointer">
                                                                                            <span>⚡ Sincronizar Ficha a <span x-text="importacion['621']?.resultado?.regimen_discrepancia?.dj"></span></span>
                                                                                        </button>
                                                                                    </template>
                                                                                    <template x-if="!importacion['621']?.resultado?.regimen_discrepancia?.puede_cambiar_ficha">
                                                                                        <span class="shrink-0 px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300 font-bold text-[8.5px]" title="Normativa SUNAT: el descenso de régimen solo procede en Enero">
                                                                                            Art. 121 LIR
                                                                                        </span>
                                                                                    </template>
                                                                                </div>
                                                                            </div>

                                                                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 space-y-2">
                                                                                <span class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider">1. Determinación Pago a Cuenta</span>

                                                                                {{-- Desglose de Ventas: Gravadas + No Gravadas --}}
                                                                                <div class="grid grid-cols-2 gap-2">
                                                                                    <div>
                                                                                        <label class="text-[9px] text-slate-600 font-medium block">
                                                                                            <span x-text="liquidacion.es_zona_selva ? 'Ventas Selva (Cas. 100)' : 'Ventas Gravadas (Cas. 100)'"></span>
                                                                                        </label>
                                                                                        <input type="text" readonly :value="liquidacion.ventas_base || '0.00'"
                                                                                               class="w-full rounded border border-slate-200 py-1 px-1.5 text-xs font-mono bg-slate-100 text-slate-600 cursor-default">
                                                                                    </div>
                                                                                    <div>
                                                                                        <label class="text-[9px] text-slate-600 font-medium block flex items-center justify-between">
                                                                                            <span>Ventas No Gravadas (Cas. 105)</span>
                                                                                            <span class="text-[8px] text-indigo-600 font-bold bg-indigo-50 px-1 rounded">Suma a Renta</span>
                                                                                        </label>
                                                                                        <input type="text" inputmode="decimal" x-model="liquidacion.ventas_no_gravadas"
                                                                                               @input="liquidacion.ventas_no_gravadas = formatearMilesInput($event); onVentasNoGravadasInput()"
                                                                                               placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
                                                                                    </div>
                                                                                </div>

                                                                                <div class="grid grid-cols-3 gap-2">
                                                                                    <div class="col-span-2">
                                                                                        <div class="flex items-center justify-between gap-1 mb-0.5">
                                                                                            <label class="text-[9px] text-slate-600 font-medium">Ingresos Netos del Mes (Cas. 301)</label>
                                                                                            <span class="text-[8px] text-indigo-700 bg-indigo-50 px-1 py-0.2 rounded font-mono">
                                                                                                Cas. 100 − 102 + 105
                                                                                            </span>
                                                                                        </div>
                                                                                        <input type="text" inputmode="decimal" x-model="liquidacion.renta_base_ingresos"
                                                                                               @input="liquidacion.renta_base_ingresos = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                               placeholder="0.00" class="w-full rounded border border-indigo-200 bg-white py-1 px-1.5 text-xs font-mono bg-white font-bold text-indigo-950 shadow-2xs hover:border-indigo-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition">
                                                                                    </div>
                                                                                    <div>
                                                                                        <label class="text-[9px] text-slate-600 font-medium block">Tasa / Coef. %</label>
                                                                                        <input type="number" step="0.01" min="0" max="100" x-model="liquidacion.renta_tasa"
                                                                                               @input="recalcularTotalesLiquidacion()"
                                                                                               class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
                                                                                    </div>
                                                                                </div>
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-500 font-medium block">Pago a cuenta calculado (Cas. 312)</label>
                                                                                    <input type="text" readonly x-model="liquidacion.renta_calculada"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-200 py-1 px-1.5 text-xs font-mono bg-slate-100 text-slate-700 cursor-default">
                                                                                </div>
                                                                            </div>

                                                                            {{-- Compensaciones de Renta --}}
                                                                            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 space-y-2">
                                                                                <span class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider">2. Compensaciones, ITAN y Créditos</span>
                                                                                <div>
                                                                                    <div class="flex items-center justify-between gap-1 mb-0.5">
                                                                                        <label class="text-[9px] text-slate-600 font-medium">Saldo a favor del período anterior (Cas. 303)</label>
                                                                                        <div class="flex items-center gap-1">
                                                                                            <span x-show="rentaSugeridaM1 > 0" class="text-[8.5px] font-bold text-indigo-700 bg-indigo-100 px-1.5 py-0.2 rounded" title="Arrastre automático de saldo de renta M-1">
                                                                                                Auto M-1: S/ <span x-text="formatearNumeroConComas(rentaSugeridaM1)"></span>
                                                                                            </span>
                                                                                            <button type="button" x-show="rentaSugeridaM1 > 0"
                                                                                                    @click="aplicarRentaSugeridaM1()"
                                                                                                    class="text-[8px] text-indigo-600 hover:text-indigo-800 underline" title="Recargar saldo de M-1">
                                                                                                Reaplicar
                                                                                            </button>
                                                                                        </div>
                                                                                    </div>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.renta_saldo_favor_anterior"
                                                                                           @input="liquidacion.renta_saldo_favor_anterior = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
                                                                                </div>

                                                                                {{-- Bloque ITAN: Arrastre automático con opción de edición voluntaria (Ley 28424 Art. 8) --}}
                                                                                <div class="rounded-md bg-white p-2 border border-slate-200/80 space-y-1.5">
                                                                                    <div class="flex items-center justify-between">
                                                                                        <span class="text-[9.5px] font-bold text-slate-700">Crédito ITAN (Ley 28424)</span>
                                                                                        <span class="text-[9px] font-mono text-purple-700 font-bold" x-show="liquidacion.renta_itan_saldo_siguiente > 0" title="Saldo ITAN disponible que pasa a M+1">
                                                                                            Pasa M+1: S/ <span x-text="formatearNumeroConComas(liquidacion.renta_itan_saldo_siguiente)"></span>
                                                                                        </span>
                                                                                    </div>
                                                                                    <div class="grid grid-cols-2 gap-2">
                                                                                        <div>
                                                                                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                                                                                <label class="text-[9px] text-slate-600 font-medium leading-snug">ITAN Disp. M-1</label>
                                                                                                <div class="flex items-center gap-1">
                                                                                                    <span x-show="itanSugeridoM1 > 0" class="text-[8px] font-bold text-purple-700 bg-purple-100 px-1 py-0.2 rounded" title="Saldo disponible arrastrado de M-1">Auto M-1</span>
                                                                                                    <button type="button" x-show="itanSugeridoM1 > 0"
                                                                                                            @click="aplicarItanSugeridoM1()"
                                                                                                            class="text-[8px] text-purple-600 hover:text-purple-800 underline" title="Recargar ITAN disponible de M-1">
                                                                                                        ↺
                                                                                                    </button>
                                                                                                </div>
                                                                                            </div>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.renta_itan_saldo_anterior"
                                                                                                   @input="liquidacion.renta_itan_saldo_anterior = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                   placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition">
                                                                                        </div>
                                                                                        <div>
                                                                                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                                                                                <label class="text-[9px] text-slate-600 font-medium leading-snug">Compensación ITAN (Cas. 328)</label>
                                                                                                <button type="button" x-show="limpiarMoneda(liquidacion.renta_itan_saldo_anterior) > 0"
                                                                                                        @click="aplicarCompensacionItanTotal()"
                                                                                                        class="text-[8px] font-bold text-purple-700 hover:text-purple-900 underline" title="Compensar el pago a cuenta con el ITAN disponible">
                                                                                                    Aplicar
                                                                                                </button>
                                                                                            </div>
                                                                                            <input type="text" inputmode="decimal" x-model="liquidacion.renta_compensacion_itan"
                                                                                                   @input="liquidacion.renta_compensacion_itan = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                                   placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block mb-0.5">Retenciones 3ra Cat. (Cas. 326)</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.renta_retenciones"
                                                                                           @input="liquidacion.renta_retenciones = formatearMilesInput($event); recalcularTotalesLiquidacion()"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
                                                                                </div>
                                                                            </div>

                                                                            {{-- Tarjeta Resultado Determinado de Renta --}}
                                                                            <div class="rounded-xl p-2.5 border"
                                                                                 :class="liquidacion.renta_resultante_pagar > 0 ? 'bg-rose-50 border-rose-200' : 'bg-emerald-50 border-emerald-200'">
                                                                                <div class="flex items-center justify-between gap-2">
                                                                                    <div>
                                                                                        <p class="text-[9px] font-bold uppercase tracking-wider"
                                                                                           :class="liquidacion.renta_resultante_pagar > 0 ? 'text-rose-700' : 'text-emerald-700'">
                                                                                            <span x-show="liquidacion.renta_resultante_pagar > 0">Importe a pagar de renta (Cas. 307 o 324)</span>
                                                                                            <span x-show="!(liquidacion.renta_resultante_pagar > 0)">
                                                                                                <span x-show="liquidacion.renta_saldo_favor_siguiente > 0">Saldo a favor de renta para el mes siguiente</span>
                                                                                                <span x-show="!(liquidacion.renta_saldo_favor_siguiente > 0)">Pago a Cuenta de Renta S/ 0.00</span>
                                                                                            </span>
                                                                                        </p>
                                                                                        <p class="text-sm font-black mt-0.5 font-mono"
                                                                                           :class="liquidacion.renta_resultante_pagar > 0 ? 'text-rose-700' : 'text-emerald-700'"
                                                                                           x-text="'S/ ' + formatearNumeroConComas(liquidacion.renta_resultante_pagar > 0 ? liquidacion.renta_resultante_pagar : liquidacion.renta_saldo_favor_siguiente)"></p>
                                                                                        <p x-show="cruceDj.renta !== null" class="mt-0.5 text-[10px] font-semibold"
                                                                                           :class="cruceCuadra('renta') ? 'text-emerald-700' : 'text-amber-700'"
                                                                                           x-text="cruceCuadra('renta') ? 'Cuadra con la DJ' : ('La DJ dice S/ ' + formatearNumeroConComas(cruceDj.renta))"></p>
                                                                                    </div>
                                                                                    <div class="flex flex-col items-end gap-1">
                                                                                        <span x-show="liquidacion.renta_resultante_pagar > 0" class="text-[10px] font-bold text-indigo-700 bg-indigo-100/90 px-2 py-0.5 rounded-md border border-indigo-200"
                                                                                              x-text="'Tributo ' + rentaCodigoSugerido()">
                                                                                        </span>
                                                                                        <span x-show="liquidacion.renta_saldo_favor_siguiente > 0" class="text-[8.5px] font-bold text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded">
                                                                                            Saldo no agotado M+1
                                                                                        </span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        {{-- Pie del Acordeón PDT 621: Nota y Botones de Acción --}}
                                                                        <div class="pt-1 space-y-2 border-t border-slate-100">
                                                                            <input type="text" x-model="liquidacion.notas_liquidacion"
                                                                                   placeholder="Nota contable interna (ej: Prórroga acogida, saldo compensado, exoneración...)"
                                                                                   class="w-full rounded-lg border border-slate-300/80 py-1 px-2 text-xs bg-slate-50 text-slate-700 placeholder-slate-400 hover:border-slate-400 focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">

                                                                            <div class="flex items-center justify-between gap-2">
                                                                                <div class="flex items-center gap-2 flex-1">
                                                                                    <button type="button" @click="guardarLiquidacion(true)" :disabled="savingLiquidacion"
                                                                                            class="flex-1 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs disabled:opacity-50 transition shadow-xs"
                                                                                            x-text="savingLiquidacion ? 'Guardando...' : (guardadoLiquidacionExitoso ? '¡Liquidación guardada y deudas actualizadas!' : '💾 Guardar y Actualizar PDT 621')">
                                                                                    </button>
                                                                                    <button type="button" @click="guardarLiquidacion(false)" :disabled="savingLiquidacion"
                                                                                            title="Guarda la liquidación sin alterar las deudas inferiores"
                                                                                            class="py-1.5 px-2.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-[11px] transition">
                                                                                        Solo Hoja
                                                                                    </button>
                                                                                </div>
                                                                                <button type="button" @click="limpiarHojaLiquidacion()" :disabled="savingLiquidacion"
                                                                                        title="Restablecer o limpiar la hoja de liquidación PDT 621 a cero"
                                                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-rose-700 hover:text-white bg-rose-50 hover:bg-rose-600 rounded-lg border border-rose-300 transition shadow-2xs shrink-0 cursor-pointer">
                                                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                                                    </svg>
                                                                                    <span>Limpiar Hoja</span>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                {{-- ACORDEÓN: HOJA DE LIQUIDACIÓN PDT 601 (PLAME) --}}
                                                                <div x-show="bloque.id === 'pdt_601'" class="rounded-xl border border-purple-200 bg-gradient-to-b from-purple-50/70 to-slate-50 overflow-hidden shadow-2xs">
                                                                    {{-- Botón Cabecera del Acordeón PLAME --}}
                                                                    <div role="button" tabindex="0" @click="panelLiquidacionPlameAbierto = !panelLiquidacionPlameAbierto; if (panelLiquidacionPlameAbierto) encuadrarMenuDJ()"
                                                                         @keydown.enter.prevent="panelLiquidacionPlameAbierto = !panelLiquidacionPlameAbierto; if (panelLiquidacionPlameAbierto) encuadrarMenuDJ()"
                                                                         @keydown.space.prevent="panelLiquidacionPlameAbierto = !panelLiquidacionPlameAbierto; if (panelLiquidacionPlameAbierto) encuadrarMenuDJ()"
                                                                         class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left hover:bg-purple-100/50 transition cursor-pointer">
                                                                        <div class="flex items-center gap-2 min-w-0">
                                                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-purple-600 text-white text-[11px] font-black">👥</span>
                                                                            <div class="min-w-0">
                                                                                <p class="text-xs font-bold text-purple-950 flex items-center gap-1.5 truncate">
                                                                                    <span>Hoja de Liquidación PDT 601</span>
                                                                                    <span class="text-[9px] font-semibold text-purple-700 bg-purple-100 px-1.5 py-0.2 rounded-full">PLAME · Planilla Mensual</span>
                                                                                </p>
                                                                                <p class="text-[10px] text-purple-800/80 truncate">
                                                                                    Determina EsSalud (piso RMV 2026: S/ 1,130), ONP, AFP y Retenciones de 4ta y 5ta Cat.
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                        <div class="flex items-center gap-1.5 shrink-0">
                                                                            <button type="button" @click.stop="panelLiquidacionPlameAbierto = true; importacion['601'].abierta = true; $nextTick(() => encuadrarMenuDJ())"
                                                                                    class="inline-flex items-center rounded-md border border-purple-300 bg-white px-2 py-0.5 text-[10px] font-bold text-purple-800 hover:bg-purple-50 shadow-2xs transition cursor-pointer">
                                                                                📥 Importar DJ
                                                                            </button>
                                                                            <button type="button" @click.stop="limpiarHojaLiquidacion()" :disabled="savingLiquidacion"
                                                                                    title="Restablecer o limpiar la planilla PLAME a cero"
                                                                                    class="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 shadow-2xs transition cursor-pointer">
                                                                                <span>🗑️</span>
                                                                                <span>Limpiar</span>
                                                                            </button>
                                                                            <span x-show="totalPlamePagar() > 0" class="text-[9px] font-bold bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded border border-purple-200">
                                                                                Pagar: S/ <span x-text="formatearNumeroConComas(totalPlamePagar())"></span>
                                                                            </span>
                                                                            <svg class="h-4 w-4 text-purple-700 transition" :class="panelLiquidacionPlameAbierto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                                            </svg>
                                                                        </div>
                                                                    </div>

                                                                    {{-- Contenedor Desplegable de la Hoja de Liquidación PLAME --}}
                                                                    <div x-show="panelLiquidacionPlameAbierto" class="border-t border-purple-200/80 p-3 space-y-3 bg-white">
                                                                        @include('control.partials.importar-declaracion', ['formulario' => '601', 'tono' => 'purple'])
                                                                        {{-- Seguridad Social / Aportes Empleador --}}
                                                                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 space-y-2">
                                                                            <div class="flex items-center justify-between">
                                                                                <span class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider">1. EsSalud (Tributo 5210)</span>
                                                                                <span class="text-[8.5px] font-bold text-purple-700 bg-purple-100 px-1.5 py-0.5 rounded border border-purple-200">Piso legal RMV 2026: S/ 101.70</span>
                                                                            </div>
                                                                            <div class="grid grid-cols-2 gap-2">
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block">Base Imponible Planilla (Cas. 502/503)</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.plame_essalud_base"
                                                                                           @input="liquidacion.plame_essalud_base = formatearMilesInput($event); onPlameEssaludBaseInput()"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition">
                                                                                    <span class="text-[8px] text-slate-500 mt-0.5 block">9% de S/ 1,130.00 = S/ 101.70 mín.</span>
                                                                                </div>
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block">Aporte EsSalud a Pagar (9%)</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.plame_essalud_a_pagar"
                                                                                           @input="liquidacion.plame_essalud_a_pagar = formatearMilesInput($event)"
                                                                                           placeholder="0.00" class="w-full rounded border border-purple-200 bg-white py-1 px-1.5 text-xs font-mono font-bold text-purple-950 shadow-2xs hover:border-purple-300 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition">
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        {{-- Retenciones al Trabajador (ONP, Renta 4ta, 5ta, AFP) --}}
                                                                        <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 space-y-2">
                                                                            <span class="block text-[10px] font-bold text-slate-700 uppercase tracking-wider">2. Pensiones y Retenciones</span>
                                                                            
                                                                            <div class="grid grid-cols-2 gap-2">
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block">ONP 13% (Tributo 5310)</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.plame_onp_a_pagar"
                                                                                           @input="liquidacion.plame_onp_a_pagar = formatearMilesInput($event)"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
                                                                                </div>
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block">AFP Net / Previsión Privada</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.plame_afp_a_pagar"
                                                                                           @input="liquidacion.plame_afp_a_pagar = formatearMilesInput($event)"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
                                                                                </div>
                                                                            </div>

                                                                            {{-- Honorarios 4ta y Planilla 5ta --}}
                                                                            <div class="grid grid-cols-3 gap-2">
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block">Base RxH (4ta Cat.)</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.plame_renta_4ta_base"
                                                                                           @input="liquidacion.plame_renta_4ta_base = formatearMilesInput($event); onPlameRenta4taBaseInput()"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition">
                                                                                </div>
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block">Ret. 4ta 8% (Trib. 3042)</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.plame_renta_4ta_a_pagar"
                                                                                           @input="liquidacion.plame_renta_4ta_a_pagar = formatearMilesInput($event)"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition">
                                                                                </div>
                                                                                <div>
                                                                                    <label class="text-[9px] text-slate-600 font-medium block">Renta 5ta (Trib. 3052)</label>
                                                                                    <input type="text" inputmode="decimal" x-model="liquidacion.plame_renta_5ta_a_pagar"
                                                                                           @input="liquidacion.plame_renta_5ta_a_pagar = formatearMilesInput($event)"
                                                                                           placeholder="0.00" class="w-full rounded border border-slate-300 bg-white py-1 px-1.5 text-xs font-mono text-slate-800 shadow-2xs hover:border-slate-400 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition">
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        {{-- Tarjeta Resumen Total PLAME --}}
                                                                        <div class="rounded-xl p-2.5 border"
                                                                             :class="totalPlamePagar() > 0 ? 'bg-purple-50 border-purple-200' : 'bg-slate-50 border-slate-200'">
                                                                            <div class="flex items-center justify-between gap-2">
                                                                                <div>
                                                                                    <p class="text-[9px] font-bold uppercase tracking-wider text-purple-900">
                                                                                        Total Tributos y Aportes PLAME
                                                                                    </p>
                                                                                    <p class="text-sm font-black mt-0.5 font-mono text-purple-900"
                                                                                       x-text="'S/ ' + formatearNumeroConComas(totalPlamePagar())"></p>
                                                                                </div>
                                                                                <div class="flex flex-col items-end gap-1">
                                                                                    <span class="text-[10px] font-bold text-purple-700 bg-purple-100/90 px-2 py-0.5 rounded-md border border-purple-200">
                                                                                        PDT 601
                                                                                    </span>
                                                                                    <div class="flex items-center gap-1 flex-wrap justify-end">
                                                                                        <span x-show="limpiarMoneda(liquidacion.plame_essalud_a_pagar) > 0" class="text-[8px] font-bold text-purple-700 bg-purple-100 px-1 rounded">5210</span>
                                                                                        <span x-show="limpiarMoneda(liquidacion.plame_onp_a_pagar) > 0" class="text-[8px] font-bold text-blue-700 bg-blue-100 px-1 rounded">5310</span>
                                                                                        <span x-show="limpiarMoneda(liquidacion.plame_afp_a_pagar) > 0" class="text-[8px] font-bold text-indigo-700 bg-indigo-100 px-1 rounded">AFP</span>
                                                                                        <span x-show="limpiarMoneda(liquidacion.plame_renta_4ta_a_pagar) > 0" class="text-[8px] font-bold text-amber-700 bg-amber-100 px-1 rounded">3042</span>
                                                                                        <span x-show="limpiarMoneda(liquidacion.plame_renta_5ta_a_pagar) > 0" class="text-[8px] font-bold text-rose-700 bg-rose-100 px-1 rounded">3052</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        {{-- Pie del Acordeón PLAME: Botones de Acción --}}
                                                                        <div class="pt-1 flex items-center justify-between gap-2 border-t border-slate-100">
                                                                            <div class="flex items-center gap-2 flex-1">
                                                                                <button type="button" @click="guardarLiquidacion(true)" :disabled="savingLiquidacion"
                                                                                        class="flex-1 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs disabled:opacity-50 transition shadow-xs"
                                                                                        x-text="savingLiquidacion ? 'Guardando...' : (guardadoLiquidacionExitoso ? '¡PLAME guardado y tributos actualizados!' : '💾 Guardar y Actualizar PLAME')">
                                                                                </button>
                                                                                <button type="button" @click="guardarLiquidacion(false)" :disabled="savingLiquidacion"
                                                                                        title="Guarda la liquidación sin alterar las deudas inferiores"
                                                                                        class="py-1.5 px-2.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-[11px] transition">
                                                                                    Solo Hoja
                                                                                </button>
                                                                            </div>
                                                                            <button type="button" @click="limpiarHojaLiquidacion()" :disabled="savingLiquidacion"
                                                                                    title="Restablecer o limpiar la liquidación PLAME a cero"
                                                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-rose-700 hover:text-white bg-rose-50 hover:bg-rose-600 rounded-lg border border-rose-300 transition shadow-2xs shrink-0 cursor-pointer">
                                                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                                                </svg>
                                                                                <span>Limpiar Hoja</span>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <template x-for="deuda in deudasDelBloque(bloque.id)" :key="deuda.localKey">
                                                                    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                                                                        <button type="button" @click="deudaAbiertaKey = deudaAbiertaKey === deuda.localKey ? null : deuda.localKey"
                                                                                class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left hover:bg-slate-50">
                                                                            <div class="min-w-0">
                                                                                <p class="text-xs font-bold text-gray-900 truncate" x-text="deuda.tributo_nombre_corto || nombreTributo(deuda.tributo_codigo) || 'Tributo'"></p>
                                                                                <p class="text-[10px] text-gray-500" x-show="deuda.id" x-text="deuda.vencimiento_deuda_dmy || deuda.vencimiento || 'Sin vencimiento'"></p>
                                                                            </div>
                                                                            <div class="flex items-center gap-2 shrink-0">
                                                                                <span class="text-[11px] font-mono font-bold"
                                                                                      :class="!(deuda.id || deuda.monto_deuda_inicial) ? 'text-gray-400' : (deuda.saldo_pendiente > 0 ? 'text-rose-600' : 'text-emerald-600')"
                                                                                      x-text="(deuda.id || deuda.monto_deuda_inicial) ? formatearMoneda(deuda.saldo_pendiente) : 'Sin guardar'"></span>
                                                                                <svg class="h-4 w-4 text-gray-400 transition" :class="deudaAbiertaKey === deuda.localKey ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                                                </svg>
                                                                            </div>
                                                                        </button>

                                                                        <div x-show="deudaAbiertaKey === deuda.localKey" data-deuda-formulario class="border-t border-slate-100 p-3 space-y-2.5 bg-slate-50">
                                                                            <div>
                                                                                <label class="block text-[10px] font-bold text-gray-600 uppercase mb-1">Tributo SUNAT</label>
                                                                                <select x-model="deuda.borrador_codigo" @change="alCambiarTributo(deuda)" class="w-full rounded-lg border border-gray-300 py-1.5 px-2.5 text-xs text-gray-800 bg-white shadow-xs hover:border-gray-400 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 font-medium transition">
                                                                                    <option value="" disabled>Seleccione el tributo</option>
                                                                                    <template x-for="opcion in bloque.tributos" :key="opcion.codigo">
                                                                                        <option :value="opcion.codigo" x-text="etiquetaTributo(opcion)" :disabled="tributoUsadoPorOtra(opcion.codigo, deuda)"></option>
                                                                                    </template>
                                                                                </select>
                                                                            </div>

                                                                    <div x-show="deuda.borrador_codigo === '1011'" class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-2.5">
                                                                        <div class="flex items-center justify-between gap-2.5">
                                                                            <p class="text-xs font-bold text-indigo-950 flex items-center gap-1.5 whitespace-nowrap min-w-0">
                                                                                <span class="whitespace-nowrap">¿Acoger a Ley IGV Justo (prórroga de 3 meses)?</span>
                                                                                <span class="rounded bg-indigo-200/80 px-1 py-0.2 text-[8px] font-black text-indigo-800 uppercase whitespace-nowrap shrink-0">Ley 30524</span>
                                                                            </p>
                                                                            <button type="button" @click="toggleIgvJusto(deuda)" :disabled="deuda.calculandoIgv"
                                                                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                                                                                    :class="deuda.borrador_igv ? 'bg-indigo-600' : 'bg-gray-300'">
                                                                                <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition" :class="deuda.borrador_igv ? 'translate-x-4' : 'translate-x-0'"></span>
                                                                            </button>
                                                                        </div>
                                                                    </div>

                                                                    <div class="grid grid-cols-2 gap-2.5">
                                                                        <div>
                                                                            <label class="block text-[10px] font-bold text-gray-600 uppercase mb-1">Importe a pagar (S/)</label>
                                                                            <input type="text" inputmode="decimal" x-model="deuda.borrador_monto" @input="deuda.borrador_monto = formatearMilesInput($event)"
                                                                                   class="w-full rounded-lg border border-gray-300 py-1.5 px-2.5 text-xs font-semibold font-mono text-gray-800 bg-white shadow-2xs hover:border-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none transition" placeholder="0.00">
                                                                        </div>
                                                                        <div>
                                                                            <label class="mb-1 flex items-center justify-between gap-2 text-[10px] font-bold uppercase text-gray-600">
                                                                                <span>Vencimiento</span>
                                                                                <span x-show="igvJustoEnEdicion(deuda)" x-cloak class="text-[9px] font-bold uppercase text-indigo-700">🔒 IGV JUSTO</span>
                                                                            </label>
                                                                            <input type="date" x-model="deuda.borrador_vencimiento"
                                                                                   :readonly="igvJustoEnEdicion(deuda)"
                                                                                   @mousedown="if (igvJustoEnEdicion(deuda)) $event.preventDefault()"
                                                                                   @keydown="if (igvJustoEnEdicion(deuda)) $event.preventDefault()"
                                                                                   class="w-full rounded-lg border py-1.5 px-2.5 text-xs transition"
                                                                                   :class="igvJustoEnEdicion(deuda) ? 'bg-indigo-50 border-indigo-200 text-indigo-900 cursor-not-allowed font-bold' : 'border-gray-300 bg-white shadow-2xs hover:border-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none'">
                                                                        </div>
                                                                    </div>

                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <button type="button" x-show="deuda.id" @click="eliminarTributo(deuda)" class="text-[11px] font-bold text-rose-600 hover:text-rose-800">Eliminar tributo</button>
                                                                        <button type="button" @click="guardarTributo(deuda)" :disabled="deuda.saving"
                                                                                class="ml-auto px-4 py-1.5 rounded-lg text-white font-bold text-xs"
                                                                                :class="deuda.guardado ? 'bg-emerald-600' : 'bg-brand-600 hover:bg-brand-700 disabled:opacity-50'">
                                                                            <span x-text="deuda.saving ? 'Guardando...' : (deuda.guardado ? '¡Guardado!' : 'Guardar tributo')"></span>
                                                                        </button>
                                                                    </div>

                                                                    <div class="border-t border-gray-200 pt-2 space-y-2" x-show="deuda.id">
                                                                        <div class="flex items-center justify-between">
                                                                            <span class="text-[11px] font-bold text-gray-800">Pagos de este tributo</span>
                                                                            <span class="text-[10px] text-gray-500" x-text="(deuda.abonos || []).length + ' pagos'"></span>
                                                                        </div>
                                                                        <div class="max-h-28 overflow-y-auto rounded-xl border border-gray-200 bg-white">
                                                                            <table class="w-full text-[10px]">
                                                                                <tbody class="divide-y divide-gray-100">
                                                                                    <template x-for="abono in deuda.abonos" :key="abono.id">
                                                                                        <tr>
                                                                                            <td class="py-1 px-2 font-mono text-gray-600" x-text="abono.fecha_pago"></td>
                                                                                            <td class="py-1 px-2 text-right font-bold text-emerald-600" x-text="abono.monto_formateado || formatearMoneda(abono.monto)"></td>
                                                                                            <td class="py-1 px-2 text-gray-500 truncate max-w-[140px]">
                                                                                                <span class="font-semibold text-gray-700" x-show="abono.medio_etiqueta" x-text="abono.medio_etiqueta"></span>
                                                                                                <span x-show="abono.medio_etiqueta && abono.observacion"> · </span>
                                                                                                <span x-text="abono.observacion" x-show="abono.observacion"></span>
                                                                                                <span x-show="!abono.medio_etiqueta && !abono.observacion">—</span>
                                                                                            </td>
                                                                                            <td class="py-1 px-1 text-center"><button type="button" @click="eliminarAbono(deuda, abono.id)" class="text-gray-300 hover:text-red-600 font-bold">&times;</button></td>
                                                                                        </tr>
                                                                                    </template>
                                                                                    <tr x-show="!deuda.abonos || deuda.abonos.length === 0">
                                                                                        <td colspan="4" class="py-3 text-center text-gray-400">Sin pagos de este tributo</td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-2.5 space-y-2">
                                                                            <div class="grid grid-cols-2 gap-2">
                                                                                <input type="text" inputmode="decimal" x-model="deuda.nuevoMonto" @input="deuda.nuevoMonto = formatearMilesInput($event)" placeholder="Monto del pago" class="rounded-lg border border-gray-300 py-1.5 px-2 text-xs font-mono bg-white shadow-2xs hover:border-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition">
                                                                                <input type="date" x-model="deuda.nuevaFecha" class="rounded-lg border border-gray-300 py-1.5 px-2 text-xs bg-white shadow-2xs hover:border-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition">
                                                                            </div>
                                                                            <select x-model="deuda.nuevoMedio" class="w-full rounded-lg border border-gray-300 py-1.5 px-2 text-xs bg-white shadow-2xs hover:border-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition">
                                                                                <option value="">Medio de pago</option>
                                                                                @foreach ($mediosPago as $medioPago)
                                                                                    <option value="{{ $medioPago['codigo'] }}">{{ $medioPago['denominacion'] }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                            <input type="text" x-model="deuda.nuevaObs" placeholder="N° operación o nota" class="w-full rounded-lg border border-gray-300 py-1.5 px-2 text-xs bg-white shadow-2xs hover:border-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition">
                                                                            <button type="button" @click="registrarAbono(deuda)" :disabled="deuda.savingAbono || limpiarMoneda(deuda.nuevoMonto) <= 0 || !deuda.nuevoMedio"
                                                                                    class="w-full py-1.5 rounded-lg bg-emerald-600 text-white font-bold text-xs disabled:opacity-50"
                                                                                    x-text="deuda.savingAbono ? 'Registrando...' : (deuda.abonoGuardado ? '¡Pago registrado!' : '+ Registrar pago')"></button>
                                                                        </div>
                                                                    </div>
                                                                    <p x-show="!deuda.id" class="text-[10px] text-gray-500">Guarde el tributo para registrar sus pagos.</p>
                                                                        </div>
                                                                    </div>
                                                                </template>

                                                                <button type="button"
                                                                        x-show="tributosLibres(bloque).length && !borradorSinTributo(bloque.id)"
                                                                        @click="agregarTributo('', bloque.id)"
                                                                        class="flex w-full items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white px-2 py-1.5 text-[11px] font-bold text-slate-600 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700">
                                                                    + Nuevo tributo
                                                                </button>
                                                        </section>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
