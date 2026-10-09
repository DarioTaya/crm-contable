@extends('layouts.app')

@section('title', 'Buzón de Alertas — SUNAT y SUNAFIL')
@section('header', 'Buzón de Alertas')

@section('content')
    <div x-data="bandejaAlertas({
            alertas: @js($alertas),
            totalPendientes: {{ $totalPendientes }}
         })"
         x-cloak
         class="space-y-5">

        {{-- ════════════════════ ENCABEZADO Y KPIS RÁPIDOS ════════════════════ --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Buzón de Alertas Electrónicas</h2>
                    <span x-show="pendientesCount > 0"
                          class="rounded-full bg-rose-100 border border-rose-200 px-2.5 py-0.5 text-xs font-bold text-rose-700 animate-pulse"
                          x-text="pendientesCount + ' Pendientes'">
                        {{ $totalPendientes }} Pendientes
                    </span>
                    <span x-show="pendientesCount === 0"
                          class="rounded-full bg-emerald-100 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                        Al día
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-gray-500">
                    Bandeja unificada de notificaciones oficiales emitidas por SUNAT y SUNAFIL con lectura en panel lateral y actualización en tiempo real.
                </p>
            </div>

            {{-- Filtros Rápidos de Plataforma y Estado con Botones Sincronizar SUNAT y SUNAFIL --}}
            <div class="flex flex-wrap items-center gap-2">
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

                <a href="{{ route('alertas.index') }}"
                   class="rounded-lg px-3 py-1.5 text-xs font-semibold shadow-xs transition border
                          {{ !request()->has('plataforma') && !request()->has('estado') ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                    Todas ({{ $totalAlertas }})
                </a>

                <a href="{{ route('alertas.index', ['estado' => 'Pendiente']) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-xs transition border
                          {{ request('estado') === 'Pendiente' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-rose-700 border-rose-200 hover:bg-rose-50' }}">
                    <span class="h-2 w-2 rounded-full bg-rose-500 {{ request('estado') === 'Pendiente' ? 'bg-white' : '' }}"></span>
                    Pendientes (<span x-text="pendientesCount">{{ $totalPendientes }}</span>)
                </a>

                <a href="{{ route('alertas.index', ['plataforma' => 'SUNAT']) }}"
                   class="rounded-lg px-3 py-1.5 text-xs font-semibold shadow-xs transition border
                          {{ request('plataforma') === 'SUNAT' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50' }}">
                    SUNAT ({{ $totalSunatPendientes }} pend.)
                </a>

                <a href="{{ route('alertas.index', ['plataforma' => 'SUNAFIL']) }}"
                   class="rounded-lg px-3 py-1.5 text-xs font-semibold shadow-xs transition border
                          {{ request('plataforma') === 'SUNAFIL' ? 'bg-rose-700 text-white border-rose-700' : 'bg-white text-rose-800 border-rose-200 hover:bg-rose-50' }}">
                    SUNAFIL ({{ $totalSunafilPendientes }} pend.)
                </a>
            </div>
        </div>

        {{-- ════════════════════ GRID DE INBOX (TABLA LIMPIA) ════════════════════ --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/90 text-gray-500 font-bold uppercase tracking-wider text-[11px] border-b border-gray-200">
                        <tr>
                            <th class="w-10 px-2 py-3 text-center">N°</th>
                            <th class="w-24 px-2 py-3 text-center">Estado</th>
                            <th class="w-20 px-2 py-3 text-center">Plataforma</th>
                            <th class="w-44 xl:w-52 px-2.5 py-3">Cliente / RUC</th>
                            <th class="px-3 py-3">Asunto / Notificación</th>
                            <th class="w-28 px-2 py-3 text-center">Visto</th>
                            <th class="w-24 px-2 py-3 text-right">Fecha</th>
                            <th class="w-20 px-2 py-3 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <template x-for="(alerta, index) in alertasFiltradas" :key="alerta.id">
                            <tr @click="abrirSlideOver(alerta)"
                                class="cursor-pointer transition-colors duration-150 hover:bg-indigo-50/40"
                                :class="{
                                    'bg-rose-50/20 font-semibold text-gray-900': alerta.estado === 'Pendiente',
                                    'text-gray-600 bg-white opacity-90': alerta.estado === 'Leído'
                                }">

                                {{-- Correlativo --}}
                                <td class="w-10 px-2 py-3 text-center whitespace-nowrap font-mono text-xs text-gray-400 font-bold">
                                    <span x-text="index + 1"></span>
                                </td>

                                {{-- Estado --}}
                                <td class="w-24 px-2 py-3 text-center whitespace-nowrap">
                                    <template x-if="alerta.estado === 'Pendiente'">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wide bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="relative flex h-2 w-2">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-600"></span>
                                            </span>
                                            <span>Pendiente</span>
                                        </span>
                                    </template>
                                    <template x-if="alerta.estado === 'Leído'">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <svg class="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                            <span>Leído</span>
                                        </span>
                                    </template>
                                </td>

                                {{-- Plataforma --}}
                                <td class="w-20 px-2 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                          :class="alerta.plataforma === 'SUNAT' ? 'bg-sky-100 text-sky-800 border border-sky-200' : 'bg-red-100 text-red-900 border border-red-200'"
                                          x-text="alerta.plataforma">
                                    </span>
                                </td>

                                {{-- Cliente (Razón Social y RUC) --}}
                                <td class="w-44 xl:w-52 px-2.5 py-3 whitespace-nowrap">
                                    <div class="truncate max-w-[155px] xl:max-w-[195px]">
                                        <span class="block truncate font-bold text-gray-900 text-xs" x-text="alerta.cliente ? alerta.cliente.razon_social : 'Cliente # ' + alerta.cliente_id"></span>
                                        <span class="block font-mono text-[11px] text-gray-400" x-text="alerta.cliente ? alerta.cliente.ruc : ''"></span>
                                    </div>
                                </td>

                                {{-- Asunto --}}
                                <td class="px-3 py-3 min-w-0">
                                    <div class="flex items-center gap-1.5 min-w-0">
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
                                        <template x-if="alerta.notificado_whatsapp_at">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0" title="Notificación despachada a WhatsApp">
                                                <span>📲 WA</span>
                                            </span>
                                        </template>
                                        <span class="truncate text-xs min-w-0 flex-1 ml-1"
                                              :class="alerta.estado === 'Pendiente' ? 'font-bold text-gray-950' : 'font-medium text-gray-700'"
                                              :title="alerta.asunto"
                                              x-text="alerta.asunto">
                                        </span>
                                    </div>
                                </td>

                                {{-- Marcar Visto (Botón Interactivo Rápido) --}}
                                <td class="w-28 px-2 py-3 text-center whitespace-nowrap">
                                    <button type="button"
                                            @click.stop="toggleLeido(alerta)"
                                            :disabled="marcandoId === alerta.id"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10.5px] font-semibold transition shadow-2xs active:scale-95 border"
                                            :class="alerta.estado === 'Leído' 
                                                ? 'border-emerald-200 bg-emerald-50/70 hover:bg-amber-50 hover:border-amber-200 hover:text-amber-800 text-emerald-700' 
                                                : 'border-gray-300 bg-white hover:bg-brand-50 hover:border-brand-400 hover:text-brand-700 text-gray-700'">
                                        <svg x-show="marcandoId !== alerta.id && alerta.estado === 'Leído'" class="h-3 w-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <svg x-show="marcandoId !== alerta.id && alerta.estado === 'Pendiente'" class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        <svg x-show="marcandoId === alerta.id" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span x-text="alerta.estado === 'Leído' ? '✓ Visto' : 'Marcar Visto'"></span>
                                    </button>
                                </td>

                                {{-- Fecha --}}
                                <td class="w-24 px-2 py-3 text-right whitespace-nowrap font-mono text-gray-500 text-[11px]">
                                    <span x-text="formatearFecha(alerta.fecha_recepcion)"></span>
                                </td>

                                {{-- Acción --}}
                                <td class="w-20 px-2 py-3 text-center whitespace-nowrap">
                                    <button type="button"
                                            @click.stop="abrirSlideOver(alerta)"
                                            class="inline-flex items-center gap-0.5 rounded-lg border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-700 shadow-xs hover:bg-gray-50 hover:border-brand-500 transition active:scale-95">
                                        <span>Detalle</span>
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <template x-if="alertasFiltradas.length === 0">
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="mx-auto h-9 w-9 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                    </svg>
                                    <p class="text-sm font-semibold text-gray-700">No se encontraron alertas</p>
                                    <p class="text-xs text-gray-400 mt-0.5">No hay notificaciones con los filtros seleccionados.</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ════════════════════ UX PREMIUM: SLIDE-OVER DE LECTURA ════════════════════ --}}
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

            <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
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

                        {{-- Meta información del Cliente --}}
                        <div class="mt-3 flex flex-col gap-1 text-xs text-gray-500 bg-white p-3 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400 uppercase font-semibold text-[10px]">Cliente</span>
                                <span class="font-bold text-gray-800 truncate max-w-[200px]" x-text="alertaActiva?.cliente?.razon_social"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400 uppercase font-semibold text-[10px]">RUC</span>
                                <span class="font-mono text-gray-700" x-text="alertaActiva?.cliente?.ruc"></span>
                            </div>
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
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3 flex items-center gap-2.5 text-xs text-gray-600">
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

                    {{-- Footer con Botones de Acción --}}
                    <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between gap-2">
                        {{-- Enlace: Ir al Expediente --}}
                        <a :href="'/clientes/' + alertaActiva?.cliente_id"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                            </svg>
                            Expediente
                        </a>

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

                        {{-- Botón Principal AJAX: Marcar como Leído --}}
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

    {{-- ════════════════════ COMPONENTE ALPINE.JS ════════════════════ --}}
    <script>
        function bandejaAlertas(config) {
            return {
                alertasFiltradas: config.alertas || [],
                pendientesCount: config.totalPendientes || 0,
                alertaActiva: null,
                slideOverOpen: false,
                marcando: false,
                marcandoId: null,
                sincronizandoSunat: false,
                sincronizandoSunafil: false,
                sincronizando: false,

                async sincronizarSunat() {
                    if (this.sincronizandoSunat || this.sincronizandoSunafil) return;
                    this.sincronizandoSunat = true;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('alertas.sync-all') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al ejecutar sincronización SUNAT.');
                        }

                        window.location.reload();
                    } catch (err) {
                        console.error('Error al sincronizar buzón SUNAT:', err);
                        alert('No se pudo completar la sincronización SUNAT: ' + err.message);
                    } finally {
                        this.sincronizandoSunat = false;
                    }
                },

                async sincronizarSunafil() {
                    if (this.sincronizandoSunat || this.sincronizandoSunafil) return;
                    this.sincronizandoSunafil = true;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('alertas.sync-sunafil') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al ejecutar sincronización SUNAFIL.');
                        }

                        window.location.reload();
                    } catch (err) {
                        console.error('Error al sincronizar casilla SUNAFIL:', err);
                        alert('No se pudo completar la sincronización SUNAFIL: ' + err.message);
                    } finally {
                        this.sincronizandoSunafil = false;
                    }
                },

                sincronizarTodo() {
                    return this.sincronizarSunat();
                },

                abrirSlideOver(alerta) {
                    this.alertaActiva = alerta;
                    this.slideOverOpen = true;
                },

                cerrarSlideOver() {
                    this.slideOverOpen = false;
                },

                async toggleLeido(alerta) {
                    if (!alerta || this.marcandoId) return;

                    this.marcandoId = alerta.id;
                    const estadoAnterior = alerta.estado;
                    const nuevoEstadoEsperado = (estadoAnterior === 'Pendiente') ? 'Leído' : 'Pendiente';

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch(`/alertas/${alerta.id}/mark-as-read`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ estado: nuevoEstadoEsperado })
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al actualizar el estado.');
                        }

                        alerta.estado = data.nuevo_estado || nuevoEstadoEsperado;
                        if (this.alertaActiva && this.alertaActiva.id === alerta.id) {
                            this.alertaActiva.estado = alerta.estado;
                        }

                        if (typeof data.pendientesCount !== 'undefined') {
                            this.pendientesCount = data.pendientesCount;
                        }
                    } catch (err) {
                        console.error('Error al cambiar estado de alerta:', err);
                        alert('No se pudo actualizar el estado de la alerta: ' + err.message);
                    } finally {
                        this.marcandoId = null;
                    }
                },

                async marcarComoLeido(alerta) {
                    if (!alerta || alerta.estado === 'Leído') return;

                    this.marcando = true;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch(`/alertas/${alerta.id}/mark-as-read`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ estado: 'Leído' })
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al actualizar el estado.');
                        }

                        // Actualizar en el estado local
                        alerta.estado = 'Leído';
                        if (this.alertaActiva && this.alertaActiva.id === alerta.id) {
                            this.alertaActiva.estado = 'Leído';
                        }
                        if (typeof data.pendientesCount !== 'undefined') {
                            this.pendientesCount = data.pendientesCount;
                        }

                    } catch (err) {
                        console.error('Error al marcar como leído:', err);
                        alert('No se pudo marcar como leído: ' + err.message);
                    } finally {
                        this.marcando = false;
                    }
                },

                formatearFecha(fechaStr) {
                    if (!fechaStr) return '';
                    const d = new Date(fechaStr);
                    if (isNaN(d.getTime())) return fechaStr;
                    return d.toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric' });
                },

                formatearFechaCompleta(fechaStr) {
                    if (!fechaStr) return '';
                    const d = new Date(fechaStr);
                    if (isNaN(d.getTime())) return fechaStr;
                    return d.toLocaleDateString('es-PE', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            };
        }
    </script>
@endsection
