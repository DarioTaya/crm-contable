@extends('layouts.app')

@section('title', $cliente->razon_social)
@section('header', 'Expediente Digital')

@section('content')
<div
    data-seccion="{{ $seccion->value }}"
    x-data="expedienteCliente(@js([
        'initialTab' => $seccion->value,
        'clienteId' => $cliente->id,
        'alertasPendientes' => $alertasPendientesCount ?? $cliente->alertas()->where('estado', 'Pendiente')->count(),
        'importarUrl' => route('comprobantes.importar'),
        'secciones' => $urlsSeccion,
    ]))"
>
    {{-- ═══════════════════════════ ENCABEZADO DEL EXPEDIENTE ═══════════════════════════ --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            {{-- Avatar con iniciales --}}
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold text-white shadow-xs">
                {{ mb_substr($cliente->razon_social, 0, 2) }}
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex h-6 min-w-[26px] px-2 items-center justify-center rounded-md bg-gray-100 border border-gray-300 text-xs font-mono font-black text-gray-700 shadow-xs" title="Número de Cliente">
                        N° {{ $cliente->numero }}
                    </span>
                    <h2 class="text-xl font-bold text-gray-900">{{ $cliente->razon_social }}</h2>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                    <span class="font-mono">RUC: {{ $cliente->ruc }}</span>
                    <span class="text-gray-300">|</span>
                    <x-ui.badge-regimen :regimen="$cliente->regimen_tributario" />
                    <span class="text-gray-300">|</span>
                    <div class="inline-flex items-center gap-2">
                        @if ($cliente->isActivo())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 shadow-2xs">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Activo en Estudio
                            </span>
                        @elseif ($cliente->isSuspendido())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-700 shadow-2xs"
                                  title="{{ $cliente->notas_estado ? 'Suspensión: '.$cliente->notas_estado : 'Suspendido temporalmente' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Suspendido
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-300 px-2.5 py-0.5 text-xs font-semibold text-slate-700 shadow-2xs"
                                  title="{{ $cliente->motivo_baja ? 'Motivo: '.$cliente->motivo_baja : 'Retirado del estudio' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Retirado @if($cliente->mes_baja) <span class="text-[11px] font-normal text-slate-500">({{ \Carbon\Carbon::createFromFormat('Y-m', $cliente->mes_baja)->translatedFormat('M Y') }})</span> @endif
                            </span>
                        @endif

                        <button type="button"
                                @click="modalEstadoEstudio = true"
                                class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-0.5 text-[11px] font-medium text-gray-600 shadow-2xs hover:bg-gray-50 hover:text-gray-900 transition"
                                title="Cambiar estado del cliente en el estudio contable">
                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Cambiar Estado
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            {{-- Botón Eliminar Cliente (Exclusivo para Administradores) --}}
            @if (auth()->user()?->isAdmin())
            <form method="POST"
                  action="{{ route('clientes.destroy', $cliente) }}"
                  onsubmit="return confirm('⚠️ ¿ESTÁS SEGURO DE ELIMINAR ESTE CLIENTE?\n\nEsta acción eliminará de forma permanente:\n• Datos del cliente y credenciales de acceso\n• Historial completo de notificaciones y buzón SOL\n• Historial de control de impuestos y notas\n\nNo quedará ningún rastro y su número correlativo (N° {{ $cliente->numero }}) quedará disponible para el próximo cliente creado.\n\n¿Deseas continuar?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-3.5 py-2.5 text-sm font-semibold text-red-700 shadow-xs transition hover:bg-red-100 hover:text-red-800 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-500/20 active:scale-95">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                    Eliminar Cliente
                </button>
            </form>
            @endif

            <a href="{{ route('clientes.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-xs transition hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Volver al listado
            </a>
        </div>
    </div>

    {{-- ═══════════════════════════ SISTEMA DE PESTAÑAS ═══════════════════════════ --}}
    <div>
        {{-- Toast para SIRE en Expediente --}}
        <div x-show="sireToast.mostrar"
             x-cloak
             x-transition:enter="transform ease-out duration-300 transition"
             x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
             x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-xl backdrop-blur max-w-md"
             :class="{
                 'bg-emerald-900/90 text-white border-emerald-500/50': sireToast.tipo === 'success',
                 'bg-rose-900/90 text-white border-rose-500/50': sireToast.tipo === 'error',
                 'bg-sky-900/95 text-white border-sky-500/50': sireToast.tipo === 'info'
             }">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                 :class="{
                     'bg-emerald-500/20 text-emerald-300': sireToast.tipo === 'success',
                     'bg-rose-500/20 text-rose-300': sireToast.tipo === 'error',
                     'bg-sky-500/20 text-sky-300': sireToast.tipo === 'info'
                 }">
                <template x-if="sireToast.tipo === 'info'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                </template>
                <template x-if="sireToast.tipo === 'success'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </template>
                <template x-if="sireToast.tipo === 'error'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </template>
            </div>
            <div>
                <p class="text-xs font-bold" x-text="sireToast.titulo"></p>
                <p class="text-[11px] opacity-90" x-text="sireToast.mensaje"></p>
            </div>
        </div>

        {{-- ════════════════════ MODAL HUD PREMIUM DE ACCESO RÁPIDO (1 CLIC) ════════════════════ --}}
        <template x-teleport="body">
            <div x-show="hudAcceso.abierto && !hudAcceso.completado"
                 x-cloak
                 @keydown.escape.window="if (hudAcceso.abierto && hudAcceso.error) hudAcceso.abierto = false"
                 class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
                 style="display: none;">
                
                <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white p-6 shadow-2xl border border-slate-100"
                     @click.away="if (hudAcceso.completado || hudAcceso.error) hudAcceso.abierto = false">
                    
                    {{-- Encabezado con Icono y Portal --}}
                    <div class="flex items-center gap-4 mb-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-2xl shadow-inner"
                             :class="hudAcceso.portal === 'SUNAT' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-blue-50 text-blue-600 border border-blue-200'">
                            <span x-text="hudAcceso.icono"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                                      :class="hudAcceso.portal === 'SUNAT' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'"
                                      x-text="hudAcceso.portal === 'SUNAT' ? 'Portal SUNAT SOL' : 'Casilla Electrónica SUNAFIL'">
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-slate-900 truncate mt-1" x-text="hudAcceso.clienteNombre"></h3>
                            <p class="text-xs font-mono text-slate-500">RUC: <span x-text="hudAcceso.clienteRuc" class="font-semibold text-slate-700"></span></p>
                        </div>
                        <template x-if="hudAcceso.error">
                            <button type="button" @click="hudAcceso.abierto = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold leading-none" aria-label="Cerrar">&times;</button>
                        </template>
                    </div>

                    {{-- Estado de Error si ocurrió alguno --}}
                    <template x-if="hudAcceso.error">
                        <div class="mb-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-start gap-2.5">
                            <span class="text-base">⚠️</span>
                            <div class="flex-1">
                                <p class="font-bold">No se pudo iniciar el acceso automático</p>
                                <p class="mt-0.5" x-text="hudAcceso.error"></p>
                            </div>
                        </div>
                    </template>

                    {{-- Barra de Progreso Única y Etapas --}}
                    <template x-if="!hudAcceso.error">
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-700 flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full"
                                          :class="hudAcceso.portal === 'SUNAT' ? 'bg-emerald-500' : 'bg-blue-600'"></span>
                                    <span x-text="hudAcceso.faseTexto"></span>
                                </span>
                                <span class="font-mono font-bold tabular-nums text-slate-700" x-text="hudAcceso.progreso + '%'"></span>
                            </div>

                            {{-- Pista de progreso animada --}}
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 shadow-inner">
                                <div class="h-full rounded-full transition-all duration-200 ease-out"
                                     :class="hudAcceso.portal === 'SUNAT' ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-blue-600 to-indigo-500'"
                                     :style="'width: ' + hudAcceso.progreso + '%'">
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1 text-[11px] text-slate-400">
                                <span x-text="(hudAcceso.nombreNavegador || 'Navegador predeterminado') + ' · Perfil sincronizado'"></span>
                                <span x-text="hudAcceso.completado ? 'Portal listo en pantalla' : 'Sesión directa 1 clic'"></span>
                            </div>
                        </div>
                    </template>

                    {{-- Botón de acción en caso de error --}}
                    <template x-if="hudAcceso.error">
                        <div class="mt-4 flex justify-end">
                            <button type="button"
                                    @click="hudAcceso.abierto = false"
                                    class="rounded-lg bg-slate-100 hover:bg-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition">
                                Entendido
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Modal para cambiar el Estado en el Estudio (Activo / Suspendido / Retirado).
             Teletransportado al <body> para ser accesible desde el encabezado en todas las secciones. --}}
        <template x-teleport="body">
            <div x-show="modalEstadoEstudio"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 aria-labelledby="modal-estado-title"
                 role="dialog"
                 aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 z-0 bg-gray-900/60 backdrop-blur-xs"
                         @click="modalEstadoEstudio = false"></div>

                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div class="relative z-10 inline-block w-full max-w-lg overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl sm:my-8 sm:align-middle">

                        {{-- Header del Modal --}}
                        <div class="border-b border-gray-100 bg-slate-50 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900" id="modal-estado-title">Control de Estado en el Estudio</h3>
                                    <p class="text-xs text-gray-500">{{ $cliente->razon_social }}</p>
                                </div>
                            </div>
                            <button type="button" @click="modalEstadoEstudio = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Nota informativa de distinción con SUNAT --}}
                        <div class="bg-indigo-50/70 px-6 py-3 border-b border-indigo-100 flex items-start gap-2.5">
                            <svg class="h-4 w-4 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <p class="text-[11px] text-indigo-900 leading-relaxed">
                                Este estado es de <span class="font-bold">control interno del estudio</span>. Si el cliente se retira o cambia de contador, su expediente e historial contable previo se conservarán en el CRM, y no generará pendientes ni alertas a partir del mes de corte.
                            </p>
                        </div>

                        {{-- Formulario --}}
                        <form method="POST" action="{{ route('clientes.estado-estudio.update', $cliente) }}" class="p-6 space-y-4" x-data="{ estadoSeleccionado: '{{ old('estado', $cliente->estado->value) }}' }">
                            @csrf
                            @method('PUT')

                            {{-- Selector de Estado --}}
                            <div>
                                <label for="form_estado_estudio" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                    Estado en el Estudio Contable <span class="text-red-500">*</span>
                                </label>
                                <select id="form_estado_estudio"
                                        name="estado"
                                        x-model="estadoSeleccionado"
                                        required
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    <option value="activo">🟢 Activo en Cartera (Genera obligaciones mensuales)</option>
                                    <option value="suspendido">🟡 Suspendido Temporalmente (Pausa de servicios)</option>
                                    <option value="retirado">🔴 Retirado / Baja del Estudio (Cambio de contador, cese)</option>
                                </select>
                            </div>

                            {{-- Opciones dinámicas para RETIRADO --}}
                            <div x-show="estadoSeleccionado === 'retirado'" x-cloak class="rounded-xl border border-rose-200 bg-rose-50/40 p-4 space-y-3.5">
                                <div>
                                    <label for="fecha_baja" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Fecha de Retiro / Cambio de Contador <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date"
                                           id="fecha_baja"
                                           name="fecha_baja"
                                           value="{{ old('fecha_baja', $cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m-d') : date('Y-m-d')) }}"
                                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    <p class="mt-1 text-[11px] text-gray-500">A partir del mes de esta fecha no generará pendientes ni alertas en Control de Impuestos ni Balances.</p>
                                </div>

                                <div>
                                    <label for="motivo_baja" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Motivo del Retiro <span class="text-red-500">*</span>
                                    </label>
                                    <select id="motivo_baja"
                                            name="motivo_baja"
                                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                        <option value="cambio_contador" {{ old('motivo_baja', $cliente->motivo_baja) === 'cambio_contador' ? 'selected' : '' }}>Cambió de Contador / Estudio Contable</option>
                                        <option value="cese_actividades" {{ old('motivo_baja', $cliente->motivo_baja) === 'cese_actividades' ? 'selected' : '' }}>Cierre / Cese de Actividades de la Empresa</option>
                                        <option value="transferencia" {{ old('motivo_baja', $cliente->motivo_baja) === 'transferencia' ? 'selected' : '' }}>Transferencia / Venta de la Empresa</option>
                                        <option value="decision_cliente" {{ old('motivo_baja', $cliente->motivo_baja) === 'decision_cliente' ? 'selected' : '' }}>Decisión del Cliente / Reorganización</option>
                                        <option value="otro" {{ old('motivo_baja', $cliente->motivo_baja) === 'otro' ? 'selected' : '' }}>Otro motivo</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Observaciones / Notas de Estado --}}
                            <div>
                                <label for="notas_estado" class="block text-xs font-semibold text-gray-700 mb-1">
                                    Notas u Observaciones Internas del Estudio
                                </label>
                                <textarea id="notas_estado"
                                          name="notas_estado"
                                          rows="3"
                                          placeholder="Ej: Se coordinó entrega de libros foliados y comprobantes al nuevo colega contador..."
                                          class="block w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">{{ old('notas_estado', $cliente->notas_estado) }}</textarea>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button"
                                        @click="modalEstadoEstudio = false"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Guardar Estado
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            </template>
        {{-- Tab headers --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6" role="tablist">
                <a
                    href="{{ $urlsSeccion['datos'] }}"
                    @click="abrirSeccion('datos', $event)"
                    @mouseenter="prefetch('{{ $urlsSeccion['datos'] }}')"
                    @focus="prefetch('{{ $urlsSeccion['datos'] }}')"
                    :class="tab === 'datos'
                        ? 'border-brand-500 text-brand-600'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    :aria-selected="tab === 'datos'"
                    class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition"
                    role="tab"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                    </svg>
                    Datos y Accesos
                </a>
                <a
                    href="{{ $urlsSeccion['notificaciones'] }}"
                    @click="abrirSeccion('notificaciones', $event)"
                    @mouseenter="prefetch('{{ $urlsSeccion['notificaciones'] }}')"
                    @focus="prefetch('{{ $urlsSeccion['notificaciones'] }}')"
                    :class="tab === 'notificaciones'
                        ? 'border-brand-500 text-brand-600'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    :aria-selected="tab === 'notificaciones'"
                    class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition"
                    role="tab"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    Notificaciones
                    @php
                        $alertasPendientesCount = $cliente->alertas->where('estado', 'Pendiente')->count();
                    @endphp
                    <span x-show="alertasPendientesCount > 0"
                          x-text="alertasPendientesCount"
                          class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold text-white shadow-xs">
                        {{ $alertasPendientesCount }}
                    </span>
                </a>
                <a
                    href="{{ $urlsSeccion['operaciones'] }}"
                    @click="abrirSeccion('operaciones', $event)"
                    @mouseenter="prefetch('{{ $urlsSeccion['operaciones'] }}')"
                    @focus="prefetch('{{ $urlsSeccion['operaciones'] }}')"
                    :class="tab === 'operaciones'
                        ? 'border-brand-500 text-brand-600'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    :aria-selected="tab === 'operaciones'"
                    class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition"
                    role="tab"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    Impuestos
                </a>
                <a
                    href="{{ $urlsSeccion['cuenta-detracciones'] }}"
                    @click="abrirSeccion('cuenta-detracciones', $event)"
                    @mouseenter="prefetch('{{ $urlsSeccion['cuenta-detracciones'] }}')"
                    @focus="prefetch('{{ $urlsSeccion['cuenta-detracciones'] }}')"
                    :class="tab === 'cuenta-detracciones'
                        ? 'border-brand-500 text-brand-600'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    :aria-selected="tab === 'cuenta-detracciones'"
                    class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition"
                    role="tab"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5m15 10.5H3" />
                    </svg>
                    Cuenta Detracciones BN
                    <span class="inline-flex h-5 items-center justify-center rounded-full bg-rose-100 border border-rose-200 px-1.5 text-[10px] font-bold text-rose-800">
                        SPOT
                    </span>
                </a>
                <a
                    href="{{ $urlsSeccion['creditos'] }}"
                    @click="abrirSeccion('creditos', $event)"
                    @mouseenter="prefetch('{{ $urlsSeccion['creditos'] }}')"
                    @focus="prefetch('{{ $urlsSeccion['creditos'] }}')"
                    :class="tab === 'creditos'
                        ? 'border-brand-500 text-brand-600'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    :aria-selected="tab === 'creditos'"
                    class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition"
                    role="tab"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0H3m-1.5 0A2.25 2.25 0 0 0 0 8.25v7.5A2.25 2.25 0 0 0 2.25 18H3m0 0h1.5m15-12h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-.75m0-12H18m0 0v-1.5A2.25 2.25 0 0 0 15.75 0h-7.5A2.25 2.25 0 0 0 6 2.25V4.5" />
                    </svg>
                    Retenciones y Percepciones
                </a>
                <a
                    href="{{ route('clientes.sire', array_merge(['cliente' => $cliente], $consultaSire)) }}"
                    @click="abrirSeccion('sire', $event)"
                    @mouseenter="prefetch('{{ route('clientes.sire', array_merge(['cliente' => $cliente], $consultaSire)) }}')"
                    @focus="prefetch('{{ route('clientes.sire', array_merge(['cliente' => $cliente], $consultaSire)) }}')"
                    :class="tab === 'sire'
                        ? 'border-brand-500 text-brand-600'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    :aria-selected="tab === 'sire'"
                    class="flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition"
                    role="tab"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    SIRE / Comprobantes
                    @if (($sireTotalRegistros ?? 0) > 0)
                        <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-blue-100 border border-blue-200 px-1.5 text-[10px] font-bold text-blue-700">
                            {{ $sireTotalRegistros }}
                        </span>
                    @endif
                </a>
            </nav>
        </div>


        {{-- Indicador visual de transición de sección ultrarrápido --}}
        <div x-show="navegandoSeccion" x-cloak class="fixed top-0 left-0 right-0 h-1 bg-brand-600 z-50 animate-pulse"></div>

        <div class="mt-6" :class="{ 'opacity-60 pointer-events-none transition-opacity duration-150': navegandoSeccion }">
            @if ($seccion === \App\Enums\SeccionExpediente::Datos)
                @include('clientes.partials.tab-datos')
            @elseif ($seccion === \App\Enums\SeccionExpediente::Notificaciones)
                @include('clientes.partials.tab-notificaciones')
            @elseif ($seccion === \App\Enums\SeccionExpediente::Operaciones)
                <div class="mt-3">
                    @include('clientes.partials.reporte-impuestos')
                </div>
            @elseif ($seccion === \App\Enums\SeccionExpediente::Detracciones)
                <div class="mt-4">
                    @include('clientes.partials.extracto-cuenta-detracciones')
                </div>
            @elseif ($seccion === \App\Enums\SeccionExpediente::Creditos)
                <div class="mt-3">
                    @include('clientes.partials.control-creditos-fiscales')
                </div>
            @elseif ($seccion === \App\Enums\SeccionExpediente::Sire)
                @include('clientes.partials.tab-sire')
            @endif
        </div>
    </div>
    {{-- ═══════════════════════════ ALPINE.JS + CLIPBOARD ═══════════════════════════ --}}
    <script>
        /**
         * Componente Alpine para la gestión general del expediente del cliente y SIRE.
         */
        function expedienteCliente(config) {
            return {
                tab: config.initialTab || 'datos',
                destinoPendiente: null,
                _sincronizandoHistorial: false,
                _prefetchedUrls: {},
                navegandoSeccion: false,
                alertasPendientesCount: config.alertasPendientes ?? 0,
                prefetch(url) {
                    if (!url || this._prefetchedUrls[url]) return;
                    this._prefetchedUrls[url] = true;
                    const link = document.createElement('link');
                    link.rel = 'prefetch';
                    link.href = url;
                    document.head.appendChild(link);
                },
                modalEditarDatos: false,
                modalEditarCredenciales: {{ $errors->hasAny(['url_afp', 'url_bn', 'banco_1_url', 'banco_2_url']) ? 'true' : 'false' }},
                modalEstadoEstudio: false,
                menuSunat: { abierto: false, top: 0, left: 0, url: '', nombre: '', ruc: '' },
                _ignorarCierreSunat: false,
                sireImportando: false,
                sincronizandoSunat: false,
                sunatInfo: {
                    estado: @json($cliente->estado_contribuyente),
                    condicion: @json($cliente->condicion_domicilio),
                    tipo: @json($cliente->tipo_contribuyente),
                    direccion: @json($cliente->direccion_fiscal),
                    ubicacion: @json((($cliente->distrito ? $cliente->distrito . ', ' : '') . ($cliente->provincia ? $cliente->provincia . ' — ' : '') . ($cliente->departamento ?? '') . ($cliente->ubigeo ? ' (Ubigeo: ' . $cliente->ubigeo . ')' : '')) ?: '—'),
                    sincronizado: @json($cliente->fecha_sincronizacion_sunat ? $cliente->fecha_sincronizacion_sunat->isoFormat('D MMM YYYY, HH:mm') : ''),
                    razonSocial: @json($cliente->razon_social),
                },
                async sincronizarEstadoSunat() {
                    if (this.sincronizandoSunat) return;
                    this.sincronizandoSunat = true;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const res = await fetch('{{ route('clientes.actualizar-estado-sunat', $cliente) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const data = await res.json();

                        if (res.ok && data.success && data.cliente) {
                            this.sunatInfo.estado = data.cliente.estado_contribuyente;
                            this.sunatInfo.condicion = data.cliente.condicion_domicilio;
                            this.sunatInfo.tipo = data.cliente.tipo_contribuyente;
                            this.sunatInfo.direccion = data.cliente.direccion_fiscal;
                            this.sunatInfo.ubicacion = (data.cliente.distrito ? data.cliente.distrito + ', ' : '') + 
                                                      (data.cliente.provincia ? data.cliente.provincia + ' — ' : '') + 
                                                      (data.cliente.departamento || '') + 
                                                      (data.cliente.ubigeo ? ' (Ubigeo: ' + data.cliente.ubigeo + ')' : '');
                            this.sunatInfo.sincronizado = data.cliente.fecha_sincronizacion_exacta || 'Recién sincronizado';
                            if (data.cliente.razon_social) {
                                this.sunatInfo.razonSocial = data.cliente.razon_social;
                            }

                            this.lanzarSireToast('¡Padrón SUNAT Actualizado!', data.message || 'Datos fiscales sincronizados exitosamente con SUNAT.', 'success');
                        } else {
                            this.lanzarSireToast('Error al consultar SUNAT', data.message || 'No se pudo obtener información del padrón.', 'error');
                        }
                    } catch (err) {
                        this.lanzarSireToast('Error de Conexión', 'Ocurrió un error al contactar al servidor: ' + err.message, 'error');
                    } finally {
                        this.sincronizandoSunat = false;
                    }
                },
                sireToast: { mostrar: false, titulo: '', mensaje: '', tipo: 'info' },
                hudAcceso: {
                    abierto: false,
                    portal: 'SUNAT',
                    clienteNombre: '',
                    clienteRuc: '',
                    progreso: 0,
                    faseTexto: '',
                    icono: '🚀',
                    nombreNavegador: 'Google Chrome',
                    completado: false,
                    error: null,
                    animTimer: null,
                },
                abrirMenuSunat(event, url, nombre, ruc) {
                    if (this.menuSunat.abierto && this.menuSunat.url === url) {
                        this.menuSunat.abierto = false;
                        document.querySelectorAll('[data-ancla-sunat]').forEach((nodo) => nodo.removeAttribute('data-ancla-sunat'));
                        return;
                    }
                    document.querySelectorAll('[data-ancla-sunat]').forEach((nodo) => nodo.removeAttribute('data-ancla-sunat'));
                    const boton = event.currentTarget;
                    boton.setAttribute('data-ancla-sunat', '1');
                    this.menuSunat.url = url;
                    this.menuSunat.nombre = nombre;
                    this.menuSunat.ruc = ruc;
                    this.menuSunat.abierto = true;
                    this.$nextTick(() => {
                        const panel = document.getElementById('menu-accesos-sunat-flotante');
                        if (panel && typeof window.ubicarMenuFlotante === 'function') {
                            window.ubicarMenuFlotante(panel, boton);
                        }
                    });
                },
                elegirAccesoSunat(destino) {
                    const separador = this.menuSunat.url.includes('?') ? '&' : '?';
                    const url = this.menuSunat.url + separador + 'destino=' + encodeURIComponent(destino);
                    const nombre = this.menuSunat.nombre;
                    const ruc = this.menuSunat.ruc;
                    this.menuSunat.abierto = false;
                    document.querySelectorAll('[data-ancla-sunat]').forEach((nodo) => nodo.removeAttribute('data-ancla-sunat'));
                    this.lanzarAcceso(url, 'SUNAT', nombre, ruc);
                },
                async lanzarAcceso(url, portal, nombre, ruc) {
                    if (this.hudAcceso.animTimer) clearInterval(this.hudAcceso.animTimer);
                    this.menuSunat.abierto = false;
                    this.hudAcceso.portal = portal;
                    this.hudAcceso.clienteNombre = nombre;
                    this.hudAcceso.clienteRuc = ruc;
                    this.hudAcceso.icono = portal === 'SUNAT' ? '🚀' : '👷';
                    this.hudAcceso.progreso = 22;
                    this.hudAcceso.faseTexto = portal === 'SUNAT'
                        ? 'Conectando con SUNAT SOL...'
                        : 'Conectando con Casilla SUNAFIL...';
                    this.hudAcceso.completado = false;
                    this.hudAcceso.error = null;
                    this.hudAcceso.abierto = true;

                    // Animación fluida sincronizada mientras el navegador inicia y se autentica (~2.5-3.5s)
                    let step = 0;
                    this.hudAcceso.animTimer = setInterval(() => {
                        step++;
                        if (step === 1 && this.hudAcceso.progreso < 48) {
                            this.hudAcceso.progreso = 48;
                            this.hudAcceso.faseTexto = 'Autenticando credenciales Clave SOL...';
                        } else if (step === 2 && this.hudAcceso.progreso < 74) {
                            this.hudAcceso.progreso = 74;
                            this.hudAcceso.faseTexto = portal === 'SUNAT'
                                ? 'Abriendo servicio en SUNAT...'
                                : 'Abriendo Casilla Electrónica...';
                        } else if (step >= 3 && this.hudAcceso.progreso < 94) {
                            this.hudAcceso.progreso = Math.min(94, this.hudAcceso.progreso + 5);
                            this.hudAcceso.faseTexto = 'Listo, abriendo navegador...';
                        }
                    }, 180);

                    try {
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        clearInterval(this.hudAcceso.animTimer);
                        const data = await res.json();

                        if (res.ok && data.success) {
                            const bName = data.browser || 'Google Chrome';
                            this.hudAcceso.nombreNavegador = bName;
                            this.hudAcceso.progreso = 100;
                            this.hudAcceso.completado = true;
                            this.hudAcceso.abierto = false;
                            this.menuSunat.abierto = false;
                            this.hudAcceso.faseTexto = '¡Sesión lista!';
                        } else {
                            this.hudAcceso.error = data.message || 'No se pudo iniciar la sesión automática.';
                            this.hudAcceso.progreso = 0;
                        }
                    } catch (err) {
                        clearInterval(this.hudAcceso.animTimer);
                        this.hudAcceso.error = 'Ocurrió un error al contactar al servidor.';
                        this.hudAcceso.progreso = 0;
                    }
                },
                init() {
                    window.expedienteClienteInstancia = this;
                    const limpiarHudAlVolver = () => {
                        if (this.hudAcceso.completado) {
                            this.hudAcceso.abierto = false;
                            this.menuSunat.abierto = false;
                        }
                    };
                    window.addEventListener('focus', limpiarHudAlVolver);
                    document.addEventListener('visibilitychange', limpiarHudAlVolver);
                    window.addEventListener('popstate', () => {
                        const seccion = this.seccionDesdeRuta(window.location.pathname);
                        if (this.tab === seccion) {
                            return;
                        }
                        this._sincronizandoHistorial = true;
                        this.tab = seccion;
                        this._sincronizandoHistorial = false;
                    });
                    this.$watch('tab', (val) => {
                        if (this._sincronizandoHistorial) {
                            return;
                        }
                        const destino = this.destinoPendiente || this.urlDe(val);
                        this.destinoPendiente = null;
                        const local = this.rutaLocal(destino);
                        const actual = window.location.pathname + window.location.search;
                        if (actual !== local) {
                            window.history.pushState({ seccion: val }, '', local);
                        }
                    });
                },
                abrirSeccion(seccion, event) {
                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                        return;
                    }
                    if (this.tab === seccion) {
                        event.preventDefault();
                        return;
                    }
                    const href = event.currentTarget.getAttribute('href');
                    if (href) {
                        event.preventDefault();
                        this.navegandoSeccion = true;
                        window.location.assign(href);
                    }
                },
                urlDe(seccion) {
                    return this.rutaLocal(config.secciones[seccion] || config.secciones.datos);
                },
                rutaLocal(direccion) {
                    const url = new URL(direccion, window.location.origin);
                    return url.pathname + url.search;
                },
                seccionDesdeRuta(pathname) {
                    const segmentos = pathname.split('/').filter(Boolean);
                    const ultimo = segmentos[segmentos.length - 1] || '';
                    if (ultimo !== 'datos' && Object.prototype.hasOwnProperty.call(config.secciones, ultimo)) {
                        return ultimo;
                    }
                    return 'datos';
                },
                irASire(registro, periodo) {
                    const destino = new URL(config.secciones.sire, window.location.origin);
                    if (registro) {
                        destino.searchParams.set('sire_tipo', registro);
                    }
                    if (periodo) {
                        destino.searchParams.set('sire_periodo', periodo);
                    }
                    window.location.assign(destino.toString());
                },
                lanzarSireToast(titulo, mensaje, tipo = 'info') {
                    this.sireToast.titulo = titulo;
                    this.sireToast.mensaje = mensaje;
                    this.sireToast.tipo = tipo;
                    this.sireToast.mostrar = true;
                    setTimeout(() => this.sireToast.mostrar = false, 5000);
                },
                async importarSireDesdeSunat() {
                    if (this.sireImportando) return;

                    const tipoSelect = document.getElementById('sire_tipo');
                    const periodoInput = document.getElementById('sire_periodo');

                    const registro = (tipoSelect && tipoSelect.value) ? tipoSelect.value : '';
                    let periodo = (periodoInput && periodoInput.value) ? periodoInput.value : '';

                    if (!registro || registro === 'Todos') {
                        this.lanzarSireToast('Selecciona un Registro', 'Por favor, selecciona específicamente RCE (Compras) o RVIE (Ventas) en el selector para importar desde SUNAT.', 'info');
                        if (tipoSelect) tipoSelect.focus();
                        return;
                    }

                    if (!periodo) {
                        const now = new Date();
                        const year = now.getFullYear();
                        const month = String(now.getMonth() + 1).padStart(2, '0');
                        periodo = `${year}-${month}`;
                        if (periodoInput) periodoInput.value = periodo;
                    }

                    this.sireImportando = true;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch(config.importarUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                cliente_id: config.clienteId,
                                registro: registro,
                                periodo: periodo
                            })
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Error al conectar con SUNAT.');
                        }

                        if (data.status === 'procesando' && data.ticket) {
                            this.lanzarSireToast('Procesando SIRE', data.mensaje || 'Ticket generado en SUNAT. Procesando descarga...', 'info');
                            await this.esperarSireTicket(data.ticket, registro, periodo);
                            return;
                        }

                        this.sireImportando = false;
                        this.lanzarSireToast('¡Importación Exitosa!', data.message || 'Comprobantes importados exitosamente.', 'success');

                        if (data.recarga) {
                            setTimeout(() => {
                                this.irASire(registro, periodo);
                            }, 1200);
                        }
                    } catch (err) {
                        this.sireImportando = false;
                        console.error('Error importación SIRE:', err);
                        const isSunatError = err.message && err.message.startsWith('Error SUNAT');
                        const errorMsg = isSunatError ? err.message : (err.message || 'Error desconocido');
                        const title = isSunatError ? 'Error SUNAT' : 'Aviso SIRE';
                        this.lanzarSireToast(title, errorMsg, 'error');
                    }
                },
                async esperarSireTicket(ticket, registro, periodo) {
                    let intentos = 0;
                    const maxIntentos = 15;

                    const sondeo = async () => {
                        if (intentos >= maxIntentos) {
                            this.sireImportando = false;
                            this.lanzarSireToast('Proceso en cola', `El ticket ${ticket} continúa procesándose en SUNAT. Podrás volver a sincronizar en unos instantes.`, 'info');
                            return;
                        }

                        intentos++;

                        try {
                            const tokenEl = document.querySelector('meta[name="csrf-token"]');
                            const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                            const response = await fetch(config.importarUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    cliente_id: config.clienteId,
                                    registro: registro,
                                    periodo: periodo,
                                    ticket: ticket
                                })
                            });

                            const data = await response.json();

                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Error consultando estado del ticket en SUNAT.');
                            }

                            if (data.status === 'procesando') {
                                setTimeout(sondeo, 2500);
                            } else {
                                this.sireImportando = false;
                                this.lanzarSireToast('¡Importación Exitosa!', data.message || 'Comprobantes importados exitosamente.', 'success');
                                if (data.recarga) {
                                    setTimeout(() => {
                                        this.irASire(registro, periodo);
                                    }, 1200);
                                }
                            }
                        } catch (err) {
                            this.sireImportando = false;
                            console.error('Error sondeo ticket SIRE:', err);
                            const isSunatError = err.message && err.message.startsWith('Error SUNAT:');
                            const errorMsg = isSunatError ? err.message : (err.message || 'Error al consultar ticket.');
                            this.lanzarSireToast('Error SIRE', errorMsg, 'error');
                        }
                    };

                    setTimeout(sondeo, 2000);
                }
            };
        }

        /**
         * Componente Alpine para copiar credenciales al portapapeles.
         * Las claves desencriptadas se pasan desde Blade (server-side) al objeto JS.
         */
        function credencialesClipboard() {
            return {
                claves: {
                    clave_sol: @json($cliente->credencial?->clave_sol ?? ''),
                    clave_afp: @json($cliente->credencial?->clave_afp ?? ''),
                    clave_detracciones: @json($cliente->credencial?->clave_detracciones ?? ''),
                    banco_1_clave: @json($cliente->credencial?->banco_1_clave ?? ''),
                    banco_2_clave: @json($cliente->credencial?->banco_2_clave ?? ''),
                },
                async copiar(texto, event) {
                    if (!texto) return;
                    const btn = event?.currentTarget;
                    try {
                        await navigator.clipboard.writeText(texto);
                        this.mostrarFeedback(btn);
                    } catch {
                        // Fallback para contextos sin HTTPS
                        const ta = document.createElement('textarea');
                        ta.value = texto;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        this.mostrarFeedback(btn);
                    }
                },
                mostrarFeedback(btn) {
                    if (!btn || btn.dataset.copiando === '1') return;
                    btn.dataset.copiando = '1';
                    const originalHTML = btn.innerHTML;
                    const originalClass = btn.className;

                    btn.innerHTML = `
                        <span class="inline-flex items-center gap-1 rounded bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-xs font-bold text-emerald-800 shadow-xs transition-all duration-200">
                            <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            ¡Copiado!
                        </span>
                    `;
                    btn.className = 'transition-all duration-200';

                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.className = originalClass;
                        delete btn.dataset.copiando;
                    }, 2200);
                }
            };
        }

        /**
         * Componente Alpine para el formulario de credenciales API SUNAT.
         * Envía los datos por AJAX (PUT) y muestra feedback con toast.
         */
        function apiCredentialsForm() {
            const clientIdGuardado = @json($cliente->sunat_client_id ?? '');
            const secretGuardado = @json($cliente->sunat_client_secret ?? '');
            const mascara = '••••••••••••••••';

            return {
                clientIdVisible: clientIdGuardado,
                form: {
                    sunat_client_id: clientIdGuardado,
                    sunat_client_secret: secretGuardado ? mascara : '',
                },
                secretoReal: secretGuardado,
                mostrarSecret: false,
                guardando: false,
                toast: { mostrar: false, mensaje: '', tipo: 'success' },

                alternarSecreto() {
                    this.mostrarSecret = !this.mostrarSecret;
                    if (this.mostrarSecret) {
                        if (!this.form.sunat_client_secret || this.form.sunat_client_secret === mascara) {
                            this.form.sunat_client_secret = this.secretoReal;
                        }
                    } else if (this.secretoReal && this.form.sunat_client_secret === this.secretoReal) {
                        this.form.sunat_client_secret = mascara;
                    }
                },

                prepararSecreto(el) {
                    el.removeAttribute('readonly');
                    if (!this.mostrarSecret && this.form.sunat_client_secret === mascara) {
                        this.form.sunat_client_secret = '';
                    }
                },

                async guardar() {
                    this.guardando = true;
                    this.toast.mostrar = false;

                    let secret = (this.form.sunat_client_secret || '').trim();
                    if (!secret || secret === mascara || /^[•*]+$/.test(secret)) {
                        secret = null;
                    }

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const body = {
                            _method: 'PUT',
                            sunat_client_id: (this.form.sunat_client_id || '').trim() || null,
                            sunat_client_secret: secret,
                        };

                        const response = await fetch('{{ route("clientes.api-credentials.update", $cliente) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar las credenciales.');
                        }

                        this.clientIdVisible = body.sunat_client_id || '';
                        if (secret) {
                            this.secretoReal = secret;
                        }
                        this.mostrarSecret = false;
                        this.form.sunat_client_secret = this.secretoReal ? mascara : '';

                        this.toast.tipo = 'success';
                        this.toast.mensaje = data.message || 'Credenciales guardadas correctamente.';
                        this.toast.mostrar = true;
                        setTimeout(() => this.toast.mostrar = false, 5000);
                    } catch (err) {
                        this.toast.tipo = 'error';
                        this.toast.mensaje = err.message || 'Error inesperado al guardar.';
                        this.toast.mostrar = true;
                        setTimeout(() => this.toast.mostrar = false, 5000);
                    } finally {
                        this.guardando = false;
                    }
                }
            };
        }

        /**
         * Componente Alpine para el formulario de credenciales API SUNAT - Validez de Comprobantes (CPE).
         * Ruta SUNAT: Empresas > Comprobantes de Pago > Consulta de Validez de Comprobante de Pago > Credenciales de API SUNAT
         */
        function cpeCredentialsForm() {
            const clientIdGuardado = @json($cliente->sunat_cpe_client_id ?? '');
            const secretGuardado = @json($cliente->sunat_cpe_client_secret ?? '');
            const mascara = '••••••••••••••••';

            return {
                clientIdVisible: clientIdGuardado,
                form: {
                    sunat_cpe_client_id: clientIdGuardado,
                    sunat_cpe_client_secret: secretGuardado ? mascara : '',
                },
                secretoReal: secretGuardado,
                mostrarSecret: false,
                guardando: false,
                probando: false,
                testResultado: null,
                toast: { mostrar: false, mensaje: '', tipo: 'success' },

                alternarSecreto() {
                    this.mostrarSecret = !this.mostrarSecret;
                    if (this.mostrarSecret) {
                        if (!this.form.sunat_cpe_client_secret || this.form.sunat_cpe_client_secret === mascara) {
                            this.form.sunat_cpe_client_secret = this.secretoReal;
                        }
                    } else if (this.secretoReal && this.form.sunat_cpe_client_secret === this.secretoReal) {
                        this.form.sunat_cpe_client_secret = mascara;
                    }
                },

                prepararSecreto(el) {
                    el.removeAttribute('readonly');
                    if (!this.mostrarSecret && this.form.sunat_cpe_client_secret === mascara) {
                        this.form.sunat_cpe_client_secret = '';
                    }
                },

                async guardar() {
                    this.guardando = true;
                    this.toast.mostrar = false;

                    let secret = (this.form.sunat_cpe_client_secret || '').trim();
                    if (!secret || secret === mascara || /^[•*]+$/.test(secret)) {
                        secret = null;
                    }

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const body = {
                            _method: 'PUT',
                            sunat_cpe_client_id: (this.form.sunat_cpe_client_id || '').trim() || null,
                            sunat_cpe_client_secret: secret,
                        };

                        const response = await fetch('{{ route("clientes.cpe-credentials.update", $cliente) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar las credenciales CPE.');
                        }

                        this.clientIdVisible = body.sunat_cpe_client_id || '';
                        if (secret) {
                            this.secretoReal = secret;
                        }
                        this.mostrarSecret = false;
                        this.form.sunat_cpe_client_secret = this.secretoReal ? mascara : '';

                        this.toast.tipo = 'success';
                        this.toast.mensaje = data.message || 'Credenciales CPE guardadas correctamente.';
                        this.toast.mostrar = true;
                        setTimeout(() => this.toast.mostrar = false, 5000);
                    } catch (err) {
                        this.toast.tipo = 'error';
                        this.toast.mensaje = err.message || 'Error inesperado al guardar.';
                        this.toast.mostrar = true;
                        setTimeout(() => this.toast.mostrar = false, 5000);
                    } finally {
                        this.guardando = false;
                    }
                },

                async probarConexion() {
                    if (this.probando) return;
                    this.probando = true;
                    this.testResultado = null;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route("clientes.api-cpe.test", $cliente) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        this.testResultado = data;
                    } catch (err) {
                        this.testResultado = {
                            success: false,
                            message: 'Error de red al intentar probar la conexión: ' + err.message
                        };
                    } finally {
                        this.probando = false;
                    }
                }
            };
        }

        /**
         * Componente Alpine para la bandeja de alertas en el expediente digital del cliente.
         */
        function expedienteAlertas(config) {
            return {
                listaAlertas: config.alertas || [],
                alertaActiva: null,
                slideOverOpen: false,
                marcando: false,
                sincronizandoSunat: false,
                sincronizandoSunafil: false,
                sincronizando: false,

                async sincronizarSunat() {
                    if (this.sincronizandoSunat || this.sincronizandoSunafil) return;
                    this.sincronizandoSunat = true;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('clientes.buzon.sync', $cliente) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al sincronizar con SUNAT');
                        }

                        window.location.assign(@js(route('clientes.notificaciones', $cliente)));
                    } catch (err) {
                        console.error('Error al sincronizar SUNAT:', err);
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

                        const response = await fetch('{{ route('clientes.sunafil.sync', $cliente) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al sincronizar con SUNAFIL');
                        }

                        window.location.assign(@js(route('clientes.notificaciones', $cliente)));
                    } catch (err) {
                        console.error('Error al sincronizar SUNAFIL:', err);
                        alert('No se pudo completar la sincronización SUNAFIL: ' + err.message);
                    } finally {
                        this.sincronizandoSunafil = false;
                    }
                },

                get conteoPendientes() {
                    return this.listaAlertas.filter(a => a.estado === 'Pendiente').length;
                },

                abrirSlideOver(alerta) {
                    this.alertaActiva = alerta;
                    this.slideOverOpen = true;
                },

                cerrarSlideOver() {
                    this.slideOverOpen = false;
                },

                marcandoId: null,

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

                        // Sincronizar el badge del tab superior en tiempo real
                        if (window.expedienteClienteInstancia) {
                            window.expedienteClienteInstancia.alertasPendientesCount = this.conteoPendientes;
                        }
                    } catch (err) {
                        console.error('Error al actualizar alerta:', err);
                        alert('No se pudo actualizar el estado: ' + err.message);
                    } finally {
                        this.marcandoId = null;
                    }
                },

                async marcarComoLeido(alerta) {
                    return this.toggleLeido(alerta);
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

    <style>
        [x-cloak] { display: none !important; }

        @media print {
            aside, header, nav, .no-print, button, [role="tablist"] {
                display: none !important;
            }
            body, main, .min-h-screen {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            input[type="text"] {
                border: none !important;
                background: transparent !important;
                box-shadow: none !important;
                color: #000 !important;
                font-size: 11pt !important;
            }
        }
    </style>
@endsection
