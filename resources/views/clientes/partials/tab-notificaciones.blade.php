        {{-- ════════════════════ PESTAÑA 2: NOTIFICACIONES ════════════════════ --}}
        <div x-show="tab === 'notificaciones'" x-cloak class="mt-6"
             x-data="expedienteAlertas({
                 alertas: @js($cliente->alertas->sortByDesc('fecha_recepcion')->values())
             })">
            <div class="rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            Buzón Electrónico SUNAT / SUNAFIL
                        </h3>
                        <p class="mt-0.5 text-xs text-gray-500">Historial auditado de notificaciones oficiales de {{ $cliente->razon_social }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <span x-show="conteoPendientes > 0"
                              class="rounded-full bg-rose-100 border border-rose-200 px-2.5 py-0.5 text-xs font-bold text-rose-700 animate-pulse"
                              x-text="conteoPendientes + ' Pendientes'">
                        </span>
                        <span x-show="conteoPendientes === 0"
                              class="rounded-full bg-emerald-100 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                            Sin alertas pendientes
                        </span>

                        {{-- Botones de Sincronización SUNAT y SUNAFIL --}}
                        <button type="button"
                                @click="sincronizarSunat()"
                                :disabled="sincronizandoSunat || sincronizandoSunafil"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 text-xs font-semibold shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="!sincronizandoSunat" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <svg x-show="sincronizandoSunat" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="sincronizandoSunat ? 'Sincronizando SUNAT...' : 'Sincronizar SUNAT'"></span>
                        </button>

                        <button type="button"
                                @click="sincronizarSunafil()"
                                :disabled="sincronizandoSunat || sincronizandoSunafil"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-rose-700 hover:bg-rose-800 text-white px-3 py-1.5 text-xs font-semibold shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg x-show="!sincronizandoSunafil" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <svg x-show="sincronizandoSunafil" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="sincronizandoSunafil ? 'Sincronizando SUNAFIL...' : 'Sincronizar SUNAFIL'"></span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-gray-50/90 text-gray-500 font-bold uppercase tracking-wider text-[11px] border-b border-gray-200">
                            <tr>
                                <th class="w-12 px-3 py-3 text-center">N°</th>
                                <th class="w-28 px-3 py-3 text-center">Estado</th>
                                <th class="w-24 px-3 py-3 text-center">Plataforma</th>
                                <th class="px-4 py-3">Asunto / Notificación</th>
                                <th class="w-32 px-3 py-3 text-center">Visto</th>
                                <th class="w-32 px-3 py-3 text-right">Fecha</th>
                                <th class="w-24 px-3 py-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="(alerta, index) in listaAlertas" :key="alerta.id">
                                <tr @click="abrirSlideOver(alerta)"
                                    class="cursor-pointer transition-colors duration-150 hover:bg-indigo-50/40"
                                    :class="{
                                        'bg-rose-50/20 font-semibold text-gray-900': alerta.estado === 'Pendiente',
                                        'text-gray-600 bg-white opacity-90': alerta.estado === 'Leído'
                                    }">

                                    {{-- Correlativo --}}
                                    <td class="px-3 py-3.5 text-center whitespace-nowrap font-mono text-xs text-gray-400 font-bold">
                                        <span x-text="index + 1"></span>
                                    </td>

                                    {{-- Estado --}}
                                    <td class="px-3 py-3.5 text-center whitespace-nowrap">
                                        <template x-if="alerta.estado === 'Pendiente'">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wide bg-rose-50 text-rose-700 border border-rose-200">
                                                <span class="relative flex h-2 w-2">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-600"></span>
                                                </span>
                                                <span>Pendiente</span>
                                            </span>
                                        </template>
                                        <template x-if="alerta.estado === 'Leído'">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <svg class="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                                <span>Leído</span>
                                            </span>
                                        </template>
                                    </td>

                                    {{-- Plataforma --}}
                                    <td class="px-3 py-3.5 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                              :class="alerta.plataforma === 'SUNAT' ? 'bg-sky-100 text-sky-800 border border-sky-200' : 'bg-red-100 text-red-900 border border-red-200'"
                                              x-text="alerta.plataforma">
                                        </span>
                                    </td>

                                    {{-- Asunto --}}
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <template x-if="alerta.prioridad === 'Alta'">
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded bg-rose-100 border border-rose-200 px-1.5 py-0.5 text-[10px] font-extrabold text-rose-700 uppercase tracking-tight">
                                                    Alta
                                                </span>
                                            </template>
                                            <template x-if="alerta.prioridad === 'Media'">
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded bg-amber-100 border border-amber-200 px-1.5 py-0.5 text-[10px] font-extrabold text-amber-800 uppercase tracking-tight">
                                                    Media
                                                </span>
                                            </template>
                                            {{-- Botón Descarga Documento Oficial (Carta/Resolución/OP) --}}
                                            <template x-if="(alerta.tiene_pdf || alerta.ruta_pdf) && (!alerta.ruta_pdf || !alerta.ruta_pdf.includes('constancia_'))">
                                                <a :href="'/alertas/' + alerta.id + '/descargar-pdf?tipo=documento'"
                                                   target="_blank"
                                                   @click.stop
                                                   class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 hover:border-rose-300 transition shrink-0 shadow-2xs"
                                                   title="Descargar Documento Oficial Notificado (Resolución / Carta / OP)">
                                                    <svg class="h-2.5 w-2.5 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5h-1v-2h1c.55 0 1 .45 1 1s-.45 1-1 1zm5 2h-1.5v-4H14v1.5h-1v-1.5H12v4h1v-1.5h1v1.5z"/></svg>
                                                    <span>Doc PDF</span>
                                                </a>
                                            </template>

                                            {{-- Botón Descarga Constancia de Notificación (Acuse) --}}
                                            <template x-if="alerta.ruta_constancia_pdf || (alerta.ruta_pdf && alerta.ruta_pdf.includes('constancia_'))">
                                                <a :href="'/alertas/' + alerta.id + '/descargar-pdf?tipo=constancia'"
                                                   target="_blank"
                                                   @click.stop
                                                   class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9.5px] font-bold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 hover:border-blue-300 transition shrink-0 shadow-2xs"
                                                   title="Descargar Constancia de Notificación SUNAT (Acuse)">
                                                    <svg class="h-2.5 w-2.5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    <span>Acuse</span>
                                                </a>
                                            </template>
                                            <span class="truncate text-xs min-w-0 flex-1 ml-1"
                                                  :class="alerta.estado === 'Pendiente' ? 'font-bold text-gray-950' : 'font-medium text-gray-700'"
                                                  :title="alerta.asunto"
                                                  x-text="alerta.asunto">
                                            </span>
                                            <template x-if="alerta.notificado_whatsapp_at">
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0" title="Notificación despachada a WhatsApp">
                                                    <span>📲 WA</span>
                                                </span>
                                            </template>
                                        </div>
                                    </td>

                                    {{-- Marcar Visto (Botón Interactivo Rápido) --}}
                                    <td class="px-3 py-3.5 text-center whitespace-nowrap">
                                        <button type="button"
                                                @click.stop="toggleLeido(alerta)"
                                                :disabled="marcandoId === alerta.id"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition shadow-2xs active:scale-95 border"
                                                :class="alerta.estado === 'Leído' 
                                                    ? 'border-emerald-200 bg-emerald-50/70 hover:bg-amber-50 hover:border-amber-200 hover:text-amber-800 text-emerald-700' 
                                                    : 'border-gray-300 bg-white hover:bg-brand-50 hover:border-brand-400 hover:text-brand-700 text-gray-700'">
                                            <svg x-show="marcandoId !== alerta.id && alerta.estado === 'Leído'" class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                            <svg x-show="marcandoId !== alerta.id && alerta.estado === 'Pendiente'" class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            <svg x-show="marcandoId === alerta.id" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span x-text="alerta.estado === 'Leído' ? '✓ Visto' : 'Marcar Visto'"></span>
                                        </button>
                                    </td>

                                    {{-- Fecha --}}
                                    <td class="px-3 py-3.5 text-right whitespace-nowrap font-mono text-gray-500 text-xs">
                                        <span x-text="formatearFecha(alerta.fecha_recepcion)"></span>
                                    </td>

                                    {{-- Acción --}}
                                    <td class="px-3 py-3.5 text-center whitespace-nowrap">
                                        <button type="button"
                                                @click.stop="abrirSlideOver(alerta)"
                                                class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-700 shadow-xs hover:bg-gray-50 hover:border-brand-500 transition active:scale-95">
                                            <span>Detalle</span>
                                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="listaAlertas.length === 0">
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-gray-500">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <svg class="mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                            </svg>
                                            <p class="text-sm font-semibold text-gray-700">No hay alertas registradas</p>
                                            <p class="mt-1 text-xs text-gray-400">El cliente no registra notificaciones oficiales en su buzón electrónico.</p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ════════════════════ SLIDE-OVER DE LECTURA (MODO EXPEDIENTE) ════════════════════ --}}
            <div x-show="slideOverOpen"
                 class="fixed inset-0 z-50 overflow-hidden"
                 role="dialog"
                 aria-modal="true">

                {{-- Backdrop suave con blur --}}
                <div x-show="slideOverOpen"
                     x-transition:enter="ease-in-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in-out duration-300"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="cerrarSlideOver()"
                     class="fixed inset-0 bg-gray-900/40 backdrop-blur-xs transition-opacity">
                </div>

                <div class="fixed inset-y-0 right-0 z-10 flex max-w-full pl-10">
                    <div x-show="slideOverOpen"
                         x-transition:enter="transform transition ease-in-out duration-300"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-300"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full"
                         class="w-screen max-w-xl sm:max-w-2xl lg:max-w-3xl bg-white shadow-2xl flex flex-col justify-between border-l border-gray-200">

                        {{-- Header del Slide-over --}}
                        <div class="border-b border-gray-200 bg-gray-50/80 px-6 py-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-black uppercase tracking-wider"
                                              :class="alertaActiva?.plataforma === 'SUNAT' ? 'bg-sky-100 text-sky-800 border border-sky-200' : 'bg-red-100 text-red-900 border border-red-200'"
                                              x-text="alertaActiva?.plataforma">
                                        </span>
                                        <span class="rounded px-2 py-0.5 text-xs font-bold"
                                              :class="{
                                                  'bg-rose-100 text-rose-800': alertaActiva?.prioridad === 'Alta',
                                                  'bg-amber-100 text-amber-800': alertaActiva?.prioridad === 'Media',
                                                  'bg-gray-100 text-gray-700': alertaActiva?.prioridad === 'Baja'
                                              }"
                                              x-text="'Prioridad ' + alertaActiva?.prioridad">
                                        </span>
                                    </div>
                                    <h3 class="mt-3 text-base font-extrabold text-gray-900 leading-snug break-words" x-text="alertaActiva?.asunto"></h3>
                                </div>

                                <button type="button"
                                        @click="cerrarSlideOver()"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 transition shrink-0"
                                        title="Cerrar panel">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Meta información --}}
                            <div class="mt-3 flex flex-col gap-1 text-xs text-gray-500 bg-white p-3 rounded-lg border border-gray-200">
                                <template x-if="alertaActiva?.numero_notificacion">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-400 uppercase font-semibold text-[10px]">N° Notificación</span>
                                        <span class="font-mono font-semibold text-brand-700" x-text="alertaActiva?.numero_notificacion"></span>
                                    </div>
                                </template>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-400 uppercase font-semibold text-[10px]">Fecha Emisión</span>
                                    <span class="font-mono text-gray-700" x-text="formatearFechaCompleta(alertaActiva?.fecha_recepcion)"></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-400 uppercase font-semibold text-[10px]">Estado</span>
                                    <span class="font-bold"
                                          :class="alertaActiva?.estado === 'Pendiente' ? 'text-rose-600' : 'text-emerald-600'"
                                          x-text="alertaActiva?.estado">
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Cuerpo del Mensaje --}}
                        <div class="flex-1 overflow-y-auto p-6 space-y-4">
                            {{-- Tarjeta 1: Documento Oficial Notificado (Resolución / Carta / OP) --}}
                            <template x-if="(alertaActiva?.tiene_pdf || alertaActiva?.ruta_pdf) && (!alertaActiva?.ruta_pdf || !alertaActiva?.ruta_pdf.includes('constancia_'))">
                                <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 flex items-center justify-between gap-3 shadow-2xs">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="p-2 rounded-lg bg-rose-100 text-rose-700 shrink-0">
                                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5h-1v-2h1c.55 0 1 .45 1 1s-.45 1-1 1zm5 2h-1.5v-4H14v1.5h-1v-1.5H12v4h1v-1.5h1v1.5z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-gray-900 truncate">Documento Oficial Notificado (Resolución / Carta / OP)</div>
                                            <div class="text-[11px] text-gray-500 font-mono truncate" x-text="alertaActiva?.nombre_archivo_pdf || 'documento_oficial_sunat.pdf'"></div>
                                        </div>
                                    </div>
                                    <a :href="'/alertas/' + alertaActiva?.id + '/descargar-pdf?tipo=documento'"
                                       target="_blank"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 transition shadow-xs shrink-0">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.5V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        <span>Descargar Documento</span>
                                    </a>
                                </div>
                            </template>

                            {{-- Tarjeta 2: Constancia de Notificación (Acuse de recibo SUNAT) --}}
                            <template x-if="alertaActiva?.ruta_constancia_pdf || (alertaActiva?.ruta_pdf && alertaActiva?.ruta_pdf.includes('constancia_'))">
                                <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-3.5 flex items-center justify-between gap-3 shadow-2xs">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="p-2 rounded-lg bg-blue-100 text-blue-700 shrink-0">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-bold text-gray-900 truncate">Constancia de Notificación (Acuse Legal SUNAT)</div>
                                            <div class="text-[11px] text-gray-500 font-mono truncate" x-text="alertaActiva?.nombre_archivo_constancia_pdf || alertaActiva?.nombre_archivo_pdf || 'constancia_notificacion_sunat.pdf'"></div>
                                        </div>
                                    </div>
                                    <a :href="'/alertas/' + alertaActiva?.id + '/descargar-pdf?tipo=constancia'"
                                       target="_blank"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-600 text-white hover:bg-blue-700 transition shadow-xs shrink-0">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.5V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        <span>Descargar Constancia</span>
                                    </a>
                                </div>
                            </template>

                            <template x-if="!alertaActiva?.tiene_pdf && !alertaActiva?.ruta_pdf && !alertaActiva?.ruta_constancia_pdf">
                                <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 flex items-center gap-2.5 text-xs text-gray-600">
                                    <svg class="h-5 w-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <span>Comunicación oficial textual. Esta notificación no cuenta con un archivo PDF anexo en SUNAT SOL.</span>
                                </div>
                            </template>

                            <div class="flex items-center justify-between pt-1">
                                <div class="text-xs font-bold uppercase tracking-wider text-gray-500">Contenido de la Notificación</div>
                                <span class="text-[10px] text-gray-400 font-medium" x-text="'Buzón ' + (alertaActiva?.plataforma || 'SUNAT')"></span>
                            </div>

                            {{-- Contenido Textual / HTML fielmente capturado --}}
                            <template x-if="(alertaActiva?.contenido_formateado || alertaActiva?.contenido) && (alertaActiva?.contenido_formateado || alertaActiva?.contenido).trim().length > 0">
                                <div class="rounded-xl border border-gray-200 bg-white p-5 text-xs text-gray-800 leading-relaxed font-sans shadow-xs prose prose-sm max-w-none break-words [&_table]:w-full [&_table]:border-collapse [&_table]:my-2 [&_td]:p-2 [&_td]:border [&_td]:border-gray-200 [&_th]:p-2 [&_th]:border [&_th]:border-gray-200 [&_th]:bg-gray-50 [&_img]:max-w-full [&_img]:h-auto [&_img[src*='blank.gif']]:hidden"
                                     x-html="alertaActiva?.contenido_formateado || alertaActiva?.contenido">
                                </div>
                            </template>

                            {{-- Fallback si no tiene texto extenso en el cuerpo --}}
                            <template x-if="!alertaActiva?.contenido || alertaActiva?.contenido.trim().length === 0">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-500">
                                    <p class="font-medium text-gray-700">Esta notificación no contiene texto en el cuerpo del mensaje.</p>
                                    <p class="mt-1 text-gray-400">El detalle completo del acto administrativo se encuentra en el archivo PDF descargable superior.</p>
                                </div>
                            </template>
                        </div>

                        {{-- Footer con Botón AJAX: Marcar como Leído y Descarga PDF --}}
                        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between gap-2">
                            <button type="button"
                                    @click="cerrarSlideOver()"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 hover:text-gray-900 transition">
                                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>Cerrar</span>
                            </button>

                            <div class="flex items-center gap-2">
                                {{-- Botón Descargar Documento Oficial --}}
                                <template x-if="(alertaActiva?.tiene_pdf || alertaActiva?.ruta_pdf) && (!alertaActiva?.ruta_pdf || !alertaActiva?.ruta_pdf.includes('constancia_'))">
                                    <a :href="'/alertas/' + alertaActiva?.id + '/descargar-pdf?tipo=documento'"
                                       target="_blank"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 shadow-xs transition hover:bg-rose-100 active:scale-95"
                                       title="Descargar documento oficial">
                                        <svg class="h-4 w-4 text-rose-600" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5h-1v-2h1c.55 0 1 .45 1 1s-.45 1-1 1zm5 2h-1.5v-4H14v1.5h-1v-1.5H12v4h1v-1.5h1v1.5z"/></svg>
                                        <span>Doc PDF</span>
                                    </a>
                                </template>

                                {{-- Botón Descargar Constancia --}}
                                <template x-if="alertaActiva?.ruta_constancia_pdf || (alertaActiva?.ruta_pdf && alertaActiva?.ruta_pdf.includes('constancia_'))">
                                    <a :href="'/alertas/' + alertaActiva?.id + '/descargar-pdf?tipo=constancia'"
                                       target="_blank"
                                       class="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 shadow-xs transition hover:bg-blue-100 active:scale-95"
                                       title="Descargar constancia de notificación">
                                        <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span>Acuse</span>
                                    </a>
                                </template>
                            </div>

                                <button type="button"
                                        @click="marcarComoLeido(alertaActiva)"
                                        :disabled="marcando || alertaActiva?.estado === 'Leído'"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-bold text-white shadow-xs transition focus:outline-none focus:ring-2"
                                        :class="alertaActiva?.estado === 'Leído' ? 'bg-emerald-600 opacity-80 cursor-default' : 'bg-brand-600 hover:bg-brand-700 focus:ring-brand-500/30 active:scale-95'">
                                    <svg x-show="!marcando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    <svg x-show="marcando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="alertaActiva?.estado === 'Leído' ? '✓ Leído' : 'Marcar Leído'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
