@extends('layouts.app')

@section('title', $cliente->razon_social)
@section('header', 'Expediente Digital')

@section('content')
<div
    data-seccion="{{ $seccion->value }}"
    x-data="expedienteCliente(@js([
        'initialTab' => $seccion->value,
        'clienteId' => $cliente->id,
        'alertasPendientes' => $cliente->alertas->where('estado', 'Pendiente')->count(),
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
        <div x-show="hudAcceso.abierto"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
            
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
                        <button type="button" @click="hudAcceso.abierto = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold leading-none">&times;</button>
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

                {{-- Barra de Progreso y Etapas --}}
                <template x-if="!hudAcceso.error">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-600 flex items-center gap-2">
                                <template x-if="!hudAcceso.completado">
                                    <svg class="animate-spin h-3.5 w-3.5" :class="hudAcceso.portal === 'SUNAT' ? 'text-emerald-600' : 'text-blue-600'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </template>
                                <template x-if="hudAcceso.completado">
                                    <span class="text-emerald-600 font-bold">✓</span>
                                </template>
                                <span x-text="hudAcceso.faseTexto"></span>
                            </span>
                            <span class="font-mono font-bold text-slate-700" x-text="hudAcceso.progreso + '%'"></span>
                        </div>

                        {{-- Pista de progreso animada --}}
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 shadow-inner">
                            <div class="h-full rounded-full transition-all duration-300 ease-out"
                                 :class="hudAcceso.portal === 'SUNAT' ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-blue-600 to-indigo-500'"
                                 :style="'width: ' + hudAcceso.progreso + '%'">
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1 text-[11px] text-slate-400">
                            <span x-text="(hudAcceso.nombreNavegador || 'Navegador') + ' · ventana normal'"></span>
                            <span x-text="hudAcceso.completado ? 'Portal listo en pantalla' : 'Conexión cifrada'"></span>
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

        {{-- Tab headers --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6" role="tablist">
                <a
                    href="{{ $urlsSeccion['datos'] }}"
                    @click="abrirSeccion('datos', $event)"
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
                    href="{{ route('clientes.sire', array_merge(['cliente' => $cliente], $consultaSire)) }}"
                    @click="abrirSeccion('sire', $event)"
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

        {{-- ════════════════════ PESTAÑA 1: DATOS Y ACCESOS ════════════════════ --}}
        <div x-show="tab === 'datos'" x-cloak class="mt-6 space-y-6">
            {{-- Datos Fiscales --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                        </svg>
                        Datos Fiscales
                    </h3>
                    <div class="flex items-center gap-2">
                        {{-- Botón Sincronizar SUNAT en Vivo --}}
                        <button
                            type="button"
                            @click="sincronizarEstadoSunat()"
                            :disabled="sincronizandoSunat"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-xs transition hover:bg-emerald-100 hover:text-emerald-800 hover:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 active:scale-95 disabled:opacity-60"
                        >
                            <svg class="h-3.5 w-3.5 text-emerald-600" :class="{ 'animate-spin': sincronizandoSunat }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span x-text="sincronizandoSunat ? 'Sincronizando...' : 'Actualizar Estado SUNAT'"></span>
                        </button>

                        <button
                            type="button"
                            @click="modalEstadoEstudio = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-xs transition hover:bg-indigo-100 hover:text-indigo-800 hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 active:scale-95"
                            title="Gestionar estado del cliente en el estudio contable"
                        >
                            <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            Estado en Estudio
                        </button>

                        <button
                            type="button"
                            @click="modalEditarDatos = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 active:scale-95"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Editar Datos Fiscales
                        </button>
                    </div>
                </div>
                {{-- Contenido Unificado dentro de la misma caja sin sub-cuadros --}}
                <div class="p-6 space-y-6">
                    {{-- Grid 1: Datos Base --}}
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">RUC</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $cliente->ruc }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Razón Social</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900" x-text="sunatInfo.razonSocial || '{{ $cliente->razon_social }}'">{{ $cliente->razon_social }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Régimen Tributario</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900" title="{{ $cliente->regimen_tributario?->nombreOficial() }}">{{ $cliente->regimen_tributario?->etiquetaCompleta() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Correo</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $cliente->correo ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Teléfono</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $cliente->telefono ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Registrado en CRM</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $cliente->created_at->isoFormat('D MMM YYYY') }}</dd>
                        </div>
                    </div>

                    {{-- Separador fino continuo --}}
                    <div class="border-t border-gray-100 pt-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                            <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <span class="text-base">🏛️</span>
                                Situación en el Padrón Oficial SUNAT
                            </h4>
                            <div class="text-[11px] text-gray-500 flex items-center gap-1.5 font-medium">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                <span>Última verificación SUNAT:</span>
                                <span class="font-semibold text-gray-700" x-text="sunatInfo.sincronizado || '{{ $cliente->fecha_sincronizacion_sunat ? $cliente->fecha_sincronizacion_sunat->isoFormat('D MMM YYYY, HH:mm') : 'No registrado' }}'">
                                    {{ $cliente->fecha_sincronizacion_sunat ? $cliente->fecha_sincronizacion_sunat->isoFormat('D MMM YYYY, HH:mm') : 'No registrado' }}
                                </span>
                            </div>
                        </div>

                        {{-- Grid 2: Datos del Padrón integrados limpiamente --}}
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Estado del Contribuyente</dt>
                                <dd class="mt-1.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                          :class="(sunatInfo.estado || '{{ $cliente->estado_contribuyente }}') === 'ACTIVO' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                          x-text="sunatInfo.estado || '{{ $cliente->estado_contribuyente ?? '—' }}'">
                                        {{ $cliente->estado_contribuyente ?? '—' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Condición de Domicilio</dt>
                                <dd class="mt-1.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                          :class="(sunatInfo.condicion || '{{ $cliente->condicion_domicilio }}') === 'HABIDO' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                          x-text="sunatInfo.condicion || '{{ $cliente->condicion_domicilio ?? '—' }}'">
                                        {{ $cliente->condicion_domicilio ?? '—' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Tipo de Contribuyente</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-900"
                                    x-text="sunatInfo.tipo || '{{ $cliente->tipo_contribuyente ?? (str_starts_with($cliente->ruc, '20') ? 'Persona Jurídica (Empresa / Sociedad)' : 'Persona Natural con Negocio') }}'">
                                    {{ $cliente->tipo_contribuyente ?? (str_starts_with($cliente->ruc, '20') ? 'Persona Jurídica (Empresa / Sociedad)' : 'Persona Natural con Negocio') }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Dirección Fiscal Registrada</dt>
                                <dd class="mt-1 text-sm text-gray-900 leading-relaxed"
                                    x-text="sunatInfo.direccion || '{{ $cliente->direccion_fiscal ?? '—' }}'">
                                    {{ $cliente->direccion_fiscal ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Ubicación / Ubigeo</dt>
                                <dd class="mt-1 text-sm text-gray-900"
                                    x-text="sunatInfo.ubicacion || '{{ ($cliente->distrito ? $cliente->distrito . ', ' : '') . ($cliente->provincia ? $cliente->provincia . ' — ' : '') . ($cliente->departamento ?? '') . ($cliente->ubigeo ? ' (Ubigeo: ' . $cliente->ubigeo . ')' : '') ?: '—' }}'">
                                    {{ ($cliente->distrito ? $cliente->distrito . ', ' : '') . ($cliente->provincia ? $cliente->provincia . ' — ' : '') . ($cliente->departamento ?? '') . ($cliente->ubigeo ? ' (Ubigeo: ' . $cliente->ubigeo . ')' : '') ?: '—' }}
                                </dd>
                            </div>
                        </div>
                    </div>

                    {{-- Separador fino continuo: Control Interno del Estudio Contable --}}
                    <div class="border-t border-gray-100 pt-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                            <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <span class="text-base">📋</span>
                                Control de Estado en el Estudio Contable
                            </h4>
                            <button type="button"
                                    @click="modalEstadoEstudio = true"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline">
                                <span>Cambiar Estado de Estudio</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>

                        {{-- Grid 3: Datos de Control del Estudio --}}
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Estado en Estudio</dt>
                                <dd class="mt-1.5">
                                    @if ($cliente->isActivo())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Activo en Cartera
                                        </span>
                                    @elseif ($cliente->isSuspendido())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Suspendido Temporalmente
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                            Retirado / Baja de Estudio
                                        </span>
                                    @endif
                                </dd>
                                <p class="mt-1 text-[11px] text-gray-400">
                                    @if ($cliente->isActivo())
                                        Obligaciones mensuales activas en Control Tributario y Balances.
                                    @elseif ($cliente->isSuspendido())
                                        Servicio pausado temporalmente por honorarios o trámites pendientes.
                                    @else
                                        Finalizó servicio. A partir del mes de corte no genera pendientes.
                                    @endif
                                </p>
                            </div>

                            @if ($cliente->isRetirado())
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Fecha y Mes de Retiro</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                        {{ $cliente->fecha_baja ? $cliente->fecha_baja->translatedFormat('d \d\e F, Y') : ($cliente->mes_baja ?? 'No registrada') }}
                                        @if ($cliente->mes_baja)
                                            <span class="ml-1 text-xs font-normal text-gray-500">
                                                (Corte: {{ \Carbon\Carbon::createFromFormat('Y-m', $cliente->mes_baja)->translatedFormat('M Y') }})
                                            </span>
                                        @endif
                                    </dd>
                                    <p class="mt-1 text-[11px] text-gray-400">Desde este periodo ya no genera pendientes ni alertas.</p>
                                </div>

                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Motivo del Retiro</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-900">
                                        @php
                                            $motivos = [
                                                'cambio_contador'   => 'Cambio de Contador / Estudio Contable',
                                                'cese_actividades'  => 'Cierre / Cese de Actividades',
                                                'transferencia'     => 'Transferencia / Venta de Empresa',
                                                'decision_cliente'  => 'Decisión del Cliente',
                                                'otro'              => 'Otro motivo',
                                            ];
                                        @endphp
                                        {{ $motivos[$cliente->motivo_baja] ?? ($cliente->motivo_baja ?: 'No especificado') }}
                                    </dd>
                                </div>

                                @if ($cliente->notas_estado)
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Observaciones del Retiro</dt>
                                        <dd class="mt-1 rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs text-gray-700 leading-relaxed">
                                            {{ $cliente->notas_estado }}
                                        </dd>
                                    </div>
                                @endif
                            @elseif ($cliente->isSuspendido() && $cliente->notas_estado)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Detalle de la Suspensión</dt>
                                    <dd class="mt-1 rounded-lg border border-amber-100 bg-amber-50/40 p-3 text-xs text-amber-900 leading-relaxed">
                                        {{ $cliente->notas_estado }}
                                    </dd>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- MODAL: EDITAR DATOS FISCALES (Únicamente correo, régimen tributario y teléfono) --}}
            <div x-show="modalEditarDatos"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 aria-labelledby="modal-datos-fiscales-title"
                 role="dialog"
                 aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="modalEditarDatos"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
                         @click="modalEditarDatos = false"></div>

                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="modalEditarDatos"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="relative z-10 inline-block w-full max-w-lg overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl sm:my-8 sm:align-middle">
                        
                        {{-- Header del Modal --}}
                        <div class="border-b border-gray-100 bg-gray-50/70 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900" id="modal-datos-fiscales-title">Editar Datos Fiscales</h3>
                                    <p class="text-xs text-gray-500">{{ $cliente->razon_social }}</p>
                                </div>
                            </div>
                            <button type="button" @click="modalEditarDatos = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Formulario --}}
                        <form method="POST" action="{{ route('clientes.datos-fiscales.update', $cliente) }}" class="p-6 space-y-4">
                            @csrf
                            @method('PUT')

                            {{-- RUC y Razón Social (Solo Lectura) --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-gray-50 p-3.5 border border-gray-200">
                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500">RUC</label>
                                    <input type="text" value="{{ $cliente->ruc }}" disabled class="mt-1 block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 font-mono text-xs text-gray-600 cursor-not-allowed select-none">
                                    <span class="text-[10px] text-gray-400">🔒 Identificador inmutable</span>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500">Razón Social</label>
                                    <input type="text" value="{{ $cliente->razon_social }}" disabled class="mt-1 block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 text-xs text-gray-600 cursor-not-allowed select-none truncate">
                                    <span class="text-[10px] text-gray-400">🔒 Denominación legal</span>
                                </div>
                            </div>

                            {{-- Régimen Tributario (Editable) --}}
                            <div>
                                <label for="modal_regimen_tributario" class="block text-xs font-semibold text-gray-700 mb-1">Régimen Tributario <span class="text-red-500">*</span></label>
                                <select id="modal_regimen_tributario"
                                        name="regimen_tributario"
                                        required
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @foreach (\App\Enums\RegimenTributario::cases() as $reg)
                                        <option value="{{ $reg->value }}" @selected(old('regimen_tributario', $cliente->regimen_tributario?->value) === $reg->value)>
                                            {{ $reg->etiquetaCompleta() }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-gray-500">Se sincroniza automáticamente con el módulo de Control de Impuestos.</p>
                            </div>

                            {{-- Correo Electrónico (Editable) --}}
                            <div>
                                <label for="modal_correo" class="block text-xs font-semibold text-gray-700 mb-1">Correo Electrónico</label>
                                <input type="email"
                                       id="modal_correo"
                                       name="correo"
                                       value="{{ old('correo', $cliente->correo) }}"
                                       placeholder="ejemplo@empresa.pe"
                                       class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            </div>

                            {{-- Teléfono (Editable) --}}
                            <div>
                                <label for="modal_telefono" class="block text-xs font-semibold text-gray-700 mb-1">Teléfono</label>
                                <input type="text"
                                       id="modal_telefono"
                                       name="telefono"
                                       value="{{ old('telefono', $cliente->telefono) }}"
                                       placeholder="987 654 321"
                                       class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            </div>

                            {{-- Dirección Fiscal (Editable) --}}
                            <div>
                                <label for="modal_direccion_fiscal" class="block text-xs font-semibold text-gray-700 mb-1">Dirección Fiscal</label>
                                <input type="text"
                                       id="modal_direccion_fiscal"
                                       name="direccion_fiscal"
                                       value="{{ old('direccion_fiscal', $cliente->direccion_fiscal) }}"
                                       placeholder="Ej: CALLE 4 MZA. G LOTE 1 URB. LA LAGUNA"
                                       class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            </div>

                            {{-- Ubicación (Editable) --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <div>
                                    <label for="modal_ubigeo" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Ubigeo</label>
                                    <input type="text"
                                           id="modal_ubigeo"
                                           name="ubigeo"
                                           value="{{ old('ubigeo', $cliente->ubigeo) }}"
                                           placeholder="040409"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 font-mono text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                                <div>
                                    <label for="modal_distrito" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Distrito</label>
                                    <input type="text"
                                           id="modal_distrito"
                                           name="distrito"
                                           value="{{ old('distrito', $cliente->distrito) }}"
                                           placeholder="ORCOPAMPA"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                                <div>
                                    <label for="modal_provincia" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Provincia</label>
                                    <input type="text"
                                           id="modal_provincia"
                                           name="provincia"
                                           value="{{ old('provincia', $cliente->provincia) }}"
                                           placeholder="CASTILLA"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                                <div>
                                    <label for="modal_departamento" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Departamento</label>
                                    <input type="text"
                                           id="modal_departamento"
                                           name="departamento"
                                           value="{{ old('departamento', $cliente->departamento) }}"
                                           placeholder="AREQUIPA"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                            </div>

                            {{-- Buen Contribuyente SUNAT (Editable) --}}
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3.5">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox"
                                           name="es_buen_contribuyente"
                                           value="1"
                                           {{ old('es_buen_contribuyente', $cliente->es_buen_contribuyente) ? 'checked' : '' }}
                                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="text-xs font-bold text-gray-900">⭐ Buen Contribuyente / UESP (SUNAT)</span>
                                        <p class="text-[11px] text-gray-600 mt-0.5">
                                            Aplica la fecha extendida especial del Cronograma SUNAT en PDT 621 y SIRE para Buenos Contribuyentes y Unidades Ejecutoras del Sector Público.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button"
                                        @click="modalEditarDatos = false"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- MODAL: CAMBIAR ESTADO EN EL ESTUDIO CONTABLE.
                 Se teletransporta al body: si queda dentro de la pestaña, el clic
                 del encabezado no puede mostrar el diálogo. --}}
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

            {{-- Botones de Acceso Rápido Automatizado (1 Clic) --}}
            @if ($cliente->credencial?->usuario_sol && $cliente->credencial?->clave_sol)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Botón Ingresar a SUNAT --}}
                    <div class="relative">
                    <button type="button"
                       @click.stop="abrirMenuSunat($event, '{{ route('clientes.sunat', $cliente) }}', '{{ addslashes($cliente->razon_social) }}', '{{ $cliente->ruc }}')"
                       class="group flex w-full items-center justify-between gap-3 rounded-xl border-2 border-emerald-200 bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-3.5 text-left text-white shadow-md transition-all hover:from-emerald-600 hover:to-teal-700 hover:shadow-lg hover:scale-[1.01] active:scale-[0.99]">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🚀</span>
                            <div>
                                <span class="block text-sm font-bold tracking-wide">Ingresar a SUNAT (1 Clic)</span>
                                <span class="block text-xs font-medium text-emerald-100">Elige el módulo y abre tu navegador</span>
                            </div>
                        </div>
                        <svg class="h-5 w-5 shrink-0 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    </div>

                    {{-- Botón Ingresar a SUNAFIL --}}
                    <a href="{{ route('clientes.sunafil', $cliente) }}"
                       @click.prevent="lanzarAcceso('{{ route('clientes.sunafil', $cliente) }}', 'SUNAFIL', '{{ addslashes($cliente->razon_social) }}', '{{ $cliente->ruc }}')"
                       class="group flex items-center justify-between gap-3 rounded-xl border-2 border-blue-200 bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3.5 text-white shadow-md transition-all hover:from-blue-700 hover:to-indigo-700 hover:shadow-lg hover:scale-[1.01] active:scale-[0.99]">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">👷</span>
                            <div>
                                <span class="block text-sm font-bold tracking-wide">Ingresar a SUNAFIL (1 Clic)</span>
                                <span class="block text-xs font-medium text-blue-100">Abre Casilla Electrónica automática</span>
                            </div>
                        </div>
                        <svg class="h-5 w-5 shrink-0 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>
            @endif

            {{-- Credenciales de Acceso --}}
            @if ($cliente->credencial)
                <div class="rounded-xl border border-gray-200 bg-white shadow-xs" x-data="credencialesClipboard()">
                    <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                                Credenciales de Acceso
                                <span class="ml-2 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700">Cifrado AES-256</span>
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="modalEditarCredenciales = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-amber-500 hover:bg-amber-50 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 active:scale-95"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Editar Credenciales
                        </button>
                    </div>
                    <div class="divide-y divide-gray-100">
                        {{-- Clave SOL --}}
                        <div class="px-6 py-5">
                            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Clave SOL (SUNAT)</h4>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Usuario SOL --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800">{{ $cliente->credencial->usuario_sol ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->usuario_sol)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->usuario_sol) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar usuario"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                {{-- Clave SOL --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->clave_sol ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->clave_sol)
                                        <button
                                            @click="copiar(claves.clave_sol, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar clave SOL al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- AFPnet --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">AFPnet</h4>
                                <a href="{{ $cliente->credencial->enlaceAfp() }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline"
                                   title="Abrir portal oficial de AFPnet en nueva pestaña">
                                    <span>💼 Abrir AFPnet ↗</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800">{{ $cliente->credencial->usuario_afp ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->usuario_afp)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->usuario_afp) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar usuario AFP"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->clave_afp ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->clave_afp)
                                        <button
                                            @click="copiar(claves.clave_afp, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar clave AFP al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Detracciones --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Detracciones (Banco de la Nación)</h4>
                                <a href="{{ $cliente->credencial->enlaceBn() }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700 hover:underline"
                                   title="Abrir Banco de la Nación por Internet en nueva pestaña">
                                    <span>🏦 Abrir BN por Internet ↗</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- N° Cuenta Detracciones --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">N° Cuenta Detracciones</p>
                                        <p class="mt-0.5 font-mono text-sm font-semibold text-gray-800">{{ $cliente->credencial->cuenta_detracciones ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->cuenta_detracciones)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->cuenta_detracciones) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar N° de cuenta"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                {{-- DNI Titular --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">DNI Titular</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800">{{ $cliente->credencial->dni_titular ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->dni_titular)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->dni_titular) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar DNI"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                {{-- Clave --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->clave_detracciones ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->clave_detracciones)
                                        <button
                                            @click="copiar(claves.clave_detracciones, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar clave detracciones al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Acceso Adicional 1 (Bancos / Libre - ej. BCP) --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">
                                        {{ $cliente->credencial->banco_1_nombre ?: 'Acceso Adicional 1 (Libre / Bancos)' }}
                                    </h4>
                                    @if ($cliente->credencial->banco_1_nombre)
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Configurado</span>
                                    @endif
                                </div>
                                @if ($cliente->credencial->enlaceBanco1())
                                    <a href="{{ $cliente->credencial->enlaceBanco1() }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline shrink-0"
                                       title="Abrir el enlace de este acceso en una nueva pestaña">
                                        <span>Abrir {{ $cliente->credencial->banco_1_nombre ?: 'enlace' }} ↗</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-[11px] text-gray-400 font-medium">Ej. Telecrédito BCP / Tarjeta y Clave</span>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Usuario / N° Tarjeta --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario / N° Tarjeta</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800 truncate">{{ $cliente->credencial->banco_1_usuario ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_1_usuario)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_1_usuario) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar usuario o tarjeta"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Clave / Contraseña --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->banco_1_clave ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_1_clave)
                                        <button
                                            @click="copiar(claves.banco_1_clave, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar clave al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Notas / Detalles --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Notas / Detalles</p>
                                        <p class="mt-0.5 text-xs text-gray-700 truncate" title="{{ $cliente->credencial->banco_1_notas ?? '' }}">
                                            {{ $cliente->credencial->banco_1_notas ?? '—' }}
                                        </p>
                                    </div>
                                    @if ($cliente->credencial->banco_1_notas)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_1_notas) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar notas"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Acceso Adicional 2 (Bancos / Libre - ej. BBVA) --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">
                                        {{ $cliente->credencial->banco_2_nombre ?: 'Acceso Adicional 2 (Libre / Bancos)' }}
                                    </h4>
                                    @if ($cliente->credencial->banco_2_nombre)
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Configurado</span>
                                    @endif
                                </div>
                                @if ($cliente->credencial->enlaceBanco2())
                                    <a href="{{ $cliente->credencial->enlaceBanco2() }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline shrink-0"
                                       title="Abrir el enlace de este acceso en una nueva pestaña">
                                        <span>Abrir {{ $cliente->credencial->banco_2_nombre ?: 'enlace' }} ↗</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-[11px] text-gray-400 font-medium">Ej. BBVA Net Cash / Tarjeta y Clave</span>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Usuario / N° Tarjeta --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario / N° Tarjeta</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800 truncate">{{ $cliente->credencial->banco_2_usuario ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_2_usuario)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_2_usuario) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar usuario o tarjeta"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Clave / Contraseña --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->banco_2_clave ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_2_clave)
                                        <button
                                            @click="copiar(claves.banco_2_clave, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar clave al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Notas / Detalles --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Notas / Detalles</p>
                                        <p class="mt-0.5 text-xs text-gray-700 truncate" title="{{ $cliente->credencial->banco_2_notas ?? '' }}">
                                            {{ $cliente->credencial->banco_2_notas ?? '—' }}
                                        </p>
                                    </div>
                                    @if ($cliente->credencial->banco_2_notas)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_2_notas) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar notas"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                    <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-500">No se han registrado credenciales para este cliente.</p>
                    <button
                        type="button"
                        @click="modalEditarCredenciales = true"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Registrar Credenciales
                    </button>
                </div>
            @endif

            {{-- MODAL: EDITAR CREDENCIALES DE ACCESO (Todos los accesos + Banco de la Nación Detracciones) --}}
            <div x-show="modalEditarCredenciales"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 aria-labelledby="modal-credenciales-title"
                 role="dialog"
                 aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="modalEditarCredenciales"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
                         @click="modalEditarCredenciales = false"></div>

                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="modalEditarCredenciales"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="relative z-10 inline-block w-full max-w-xl overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl sm:my-8 sm:align-middle">
                        
                        {{-- Header del Modal --}}
                        <div class="border-b border-gray-100 bg-amber-50/50 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold text-gray-900" id="modal-credenciales-title">Credenciales de Acceso</h3>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Cifrado AES-256</span>
                                    </div>
                                    <p class="text-xs text-gray-500">{{ $cliente->razon_social }}</p>
                                </div>
                            </div>
                            <button type="button" @click="modalEditarCredenciales = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Formulario --}}
                        <form method="POST" action="{{ route('clientes.credenciales.update', $cliente) }}" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                            @csrf
                            @method('PUT')

                            {{-- SECCIÓN 1: CLAVE SOL SUNAT --}}
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/30 p-4 space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                                    <span>🚀</span> Clave SOL (SUNAT)
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="modal_usuario_sol" class="block text-xs font-semibold text-gray-700 mb-1">Usuario SOL</label>
                                        <input type="text"
                                               id="modal_usuario_sol"
                                               name="usuario_sol"
                                               value="{{ old('usuario_sol', $cliente->credencial?->usuario_sol) }}"
                                               placeholder="MODDATOS"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_clave_sol" class="block text-xs font-semibold text-gray-700 mb-1">Clave SOL</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_clave_sol"
                                                   name="clave_sol"
                                                   value="{{ old('clave_sol', $cliente->credencial?->clave_sol) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 2: AFPNET --}}
                            <div class="rounded-xl border border-blue-100 bg-blue-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
                                        <span>💼</span> AFPnet
                                    </h4>
                                    <a href="{{ $cliente->credencial?->enlaceAfp() ?? \App\Models\Credencial::ENLACE_AFP }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="text-[11px] font-semibold text-sky-600 hover:underline flex items-center gap-1">
                                        AFPnet Oficial ↗
                                    </a>
                                </div>
                                <div>
                                    <label for="modal_url_afp" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                    <input type="url"
                                           id="modal_url_afp"
                                           name="url_afp"
                                           value="{{ old('url_afp', $cliente->credencial?->url_afp ?: \App\Models\Credencial::ENLACE_AFP) }}"
                                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('url_afp')
                                        <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="modal_usuario_afp" class="block text-xs font-semibold text-gray-700 mb-1">Usuario AFP</label>
                                        <input type="text"
                                               id="modal_usuario_afp"
                                               name="usuario_afp"
                                               value="{{ old('usuario_afp', $cliente->credencial?->usuario_afp) }}"
                                               placeholder="Usuario AFP"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_clave_afp" class="block text-xs font-semibold text-gray-700 mb-1">Clave AFP</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_clave_afp"
                                                   name="clave_afp"
                                                   value="{{ old('clave_afp', $cliente->credencial?->clave_afp) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 3: DETRACCIONES (BANCO DE LA NACIÓN) --}}
                            <div class="rounded-xl border border-red-100 bg-red-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-red-800 flex items-center gap-1.5">
                                        <span>🏦</span> Detracciones (Banco de la Nación)
                                    </h4>
                                    <a href="{{ $cliente->credencial?->enlaceBn() ?? \App\Models\Credencial::ENLACE_BN }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="text-[11px] font-semibold text-red-600 hover:underline flex items-center gap-1">
                                        BN por Internet ↗
                                    </a>
                                </div>
                                <div>
                                    <label for="modal_url_bn" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                    <input type="url"
                                           id="modal_url_bn"
                                           name="url_bn"
                                           value="{{ old('url_bn', $cliente->credencial?->url_bn ?: \App\Models\Credencial::ENLACE_BN) }}"
                                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('url_bn')
                                        <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="modal_dni_titular" class="block text-xs font-semibold text-gray-700 mb-1">DNI Titular</label>
                                        <input type="text"
                                               id="modal_dni_titular"
                                               name="dni_titular"
                                               maxlength="8"
                                               value="{{ old('dni_titular', $cliente->credencial?->dni_titular) }}"
                                               placeholder="8 dígitos"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_clave_detracciones" class="block text-xs font-semibold text-gray-700 mb-1">Clave BN</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_clave_detracciones"
                                                   name="clave_detracciones"
                                                   value="{{ old('clave_detracciones', $cliente->credencial?->clave_detracciones) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="modal_cuenta_detracciones" class="block text-xs font-semibold text-gray-700 mb-1">N° Cuenta Detracciones</label>
                                        <input type="text"
                                               id="modal_cuenta_detracciones"
                                               name="cuenta_detracciones"
                                               maxlength="50"
                                               value="{{ old('cuenta_detracciones', $cliente->credencial?->cuenta_detracciones) }}"
                                               placeholder="00-000-000000"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 4: ACCESO ADICIONAL 1 (LIBRE / BANCOS - EJ. BCP) --}}
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-900">
                                        Acceso Adicional 1 (Bancos / Libre - Ej. BCP)
                                    </h4>
                                    <div class="flex items-center gap-3">
                                        @if ($cliente->credencial?->enlaceBanco1())
                                            <a href="{{ $cliente->credencial->enlaceBanco1() }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="text-[11px] font-semibold text-sky-600 hover:underline flex items-center gap-1">
                                                {{ $cliente->credencial->banco_1_nombre ?: 'Enlace' }} ↗
                                            </a>
                                        @endif
                                        <span class="text-[10px] text-gray-500 font-medium">Opcional</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_1_nombre" class="block text-xs font-semibold text-gray-700 mb-1">Nombre o Entidad</label>
                                        <input type="text"
                                               id="modal_banco_1_nombre"
                                               name="banco_1_nombre"
                                               value="{{ old('banco_1_nombre', $cliente->credencial?->banco_1_nombre) }}"
                                               placeholder="Ej: Telecrédito BCP"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div>
                                        <label for="modal_banco_1_usuario" class="block text-xs font-semibold text-gray-700 mb-1">Usuario / N° Tarjeta</label>
                                        <input type="text"
                                               id="modal_banco_1_usuario"
                                               name="banco_1_usuario"
                                               value="{{ old('banco_1_usuario', $cliente->credencial?->banco_1_usuario) }}"
                                               placeholder="Ej: 4557 0000 0000 0000"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_banco_1_clave" class="block text-xs font-semibold text-gray-700 mb-1">Clave / Contraseña</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_banco_1_clave"
                                                   name="banco_1_clave"
                                                   value="{{ old('banco_1_clave', $cliente->credencial?->banco_1_clave) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_1_notas" class="block text-xs font-semibold text-gray-700 mb-1">Notas / Detalles Adicionales</label>
                                        <input type="text"
                                               id="modal_banco_1_notas"
                                               name="banco_1_notas"
                                               value="{{ old('banco_1_notas', $cliente->credencial?->banco_1_notas) }}"
                                               placeholder="Ej: Para descarga de movimientos bancarios y extractos"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_1_url" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                        <input type="url"
                                               id="modal_banco_1_url"
                                               name="banco_1_url"
                                               value="{{ old('banco_1_url', $cliente->credencial?->banco_1_url) }}"
                                               placeholder="https://"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                        @error('banco_1_url')
                                            <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 5: ACCESO ADICIONAL 2 (LIBRE / BANCOS - EJ. BBVA) --}}
                            <div class="rounded-xl border border-sky-100 bg-sky-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-sky-900">
                                        Acceso Adicional 2 (Bancos / Libre - Ej. BBVA)
                                    </h4>
                                    <div class="flex items-center gap-3">
                                        @if ($cliente->credencial?->enlaceBanco2())
                                            <a href="{{ $cliente->credencial->enlaceBanco2() }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="text-[11px] font-semibold text-sky-600 hover:underline flex items-center gap-1">
                                                {{ $cliente->credencial->banco_2_nombre ?: 'Enlace' }} ↗
                                            </a>
                                        @endif
                                        <span class="text-[10px] text-gray-500 font-medium">Opcional</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_2_nombre" class="block text-xs font-semibold text-gray-700 mb-1">Nombre o Entidad</label>
                                        <input type="text"
                                               id="modal_banco_2_nombre"
                                               name="banco_2_nombre"
                                               value="{{ old('banco_2_nombre', $cliente->credencial?->banco_2_nombre) }}"
                                               placeholder="Ej: BBVA Net Cash"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div>
                                        <label for="modal_banco_2_usuario" class="block text-xs font-semibold text-gray-700 mb-1">Usuario / N° Tarjeta</label>
                                        <input type="text"
                                               id="modal_banco_2_usuario"
                                               name="banco_2_usuario"
                                               value="{{ old('banco_2_usuario', $cliente->credencial?->banco_2_usuario) }}"
                                               placeholder="Ej: BBVA-USER-123"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_banco_2_clave" class="block text-xs font-semibold text-gray-700 mb-1">Clave / Contraseña</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_banco_2_clave"
                                                   name="banco_2_clave"
                                                   value="{{ old('banco_2_clave', $cliente->credencial?->banco_2_clave) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_2_notas" class="block text-xs font-semibold text-gray-700 mb-1">Notas / Detalles Adicionales</label>
                                        <input type="text"
                                               id="modal_banco_2_notas"
                                               name="banco_2_notas"
                                               value="{{ old('banco_2_notas', $cliente->credencial?->banco_2_notas) }}"
                                               placeholder="Ej: Token digital en celular de administración"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_2_url" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                        <input type="url"
                                               id="modal_banco_2_url"
                                               name="banco_2_url"
                                               value="{{ old('banco_2_url', $cliente->credencial?->banco_2_url) }}"
                                               placeholder="https://"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                        @error('banco_2_url')
                                            <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button"
                                        @click="modalEditarCredenciales = false"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Guardar Credenciales
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

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
                                            <span class="truncate max-w-lg" :class="alerta.estado === 'Pendiente' ? 'font-bold text-gray-950' : 'text-gray-700'" x-text="alerta.asunto"></span>
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
                         class="w-screen max-w-md bg-white shadow-2xl flex flex-col justify-between border-l border-gray-200">

                        {{-- Header del Slide-over --}}
                        <div class="border-b border-gray-200 bg-gray-50/80 px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
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

                                <button type="button"
                                        @click="cerrarSlideOver()"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 transition">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Asunto --}}
                            <h3 class="mt-4 text-base font-extrabold text-gray-900 leading-snug" x-text="alertaActiva?.asunto"></h3>

                            {{-- Meta información --}}
                            <div class="mt-3 flex flex-col gap-1 text-xs text-gray-500 bg-white p-3 rounded-lg border border-gray-200">
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
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-bold uppercase tracking-wider text-gray-400">Contenido de la Notificación</div>
                                <span class="text-[10px] text-gray-400 font-medium">Buzón SOL SUNAT</span>
                            </div>

                            {{-- Fallback oficial si es PDF o viene sin cuerpo detallado --}}
                            <template x-if="!alertaActiva?.contenido || alertaActiva?.contenido.trim().length < 25 || alertaActiva?.contenido.includes('formato PDF')">
                                <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-4 text-xs text-sky-900 leading-relaxed shadow-xs flex items-start gap-3">
                                    <svg class="h-5 w-5 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <div>
                                        <span class="font-bold block text-sky-950 mb-1">Documento Anexo en Portal SUNAT</span>
                                        <p>El contenido detallado o documento anexo se encuentra en formato PDF. Por favor, acceda directamente al portal SUNAT desde el Expediente del Cliente para descargarlo.</p>
                                    </div>
                                </div>
                            </template>

                            {{-- Contenido HTML completo si está disponible --}}
                            <template x-if="alertaActiva?.contenido && alertaActiva?.contenido.trim().length >= 25 && !alertaActiva?.contenido.includes('formato PDF')">
                                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 text-xs text-gray-800 leading-relaxed font-sans shadow-inner prose prose-sm max-w-none break-words"
                                     x-html="alertaActiva?.contenido">
                                </div>
                            </template>
                        </div>

                        {{-- Footer con Botón AJAX: Marcar como Leído --}}
                        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-end gap-3">
                            <button type="button"
                                    @click="cerrarSlideOver()"
                                    class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition">
                                Cerrar
                            </button>

                            <button type="button"
                                    @click="marcarComoLeido(alertaActiva)"
                                    :disabled="marcando || alertaActiva?.estado === 'Leído'"
                                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold text-white shadow-xs transition focus:outline-none focus:ring-2"
                                    :class="alertaActiva?.estado === 'Leído' ? 'bg-emerald-600 opacity-80 cursor-default' : 'bg-brand-600 hover:bg-brand-700 focus:ring-brand-500/30 active:scale-95'">
                                <svg x-show="!marcando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <svg x-show="marcando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="alertaActiva?.estado === 'Leído' ? '✓ Leído' : 'Marcar como Leído'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ════════════════════ PESTAÑA 3: IMPUESTOS ════════════════════ --}}
        <div x-show="tab === 'operaciones'" x-cloak class="mt-3">
            @include('clientes.partials.reporte-impuestos')

        </div>
    </div>

    {{-- ════════════════════ PESTAÑA 4: SIRE / COMPROBANTES ════════════════════ --}}
        <div x-show="tab === 'sire'" x-cloak class="mt-6 space-y-6">
            {{-- Encabezado de la pestaña y Botonera SIRE --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        Bóveda SIRE — Expediente Digital
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Comprobantes electrónicos de compras (RCE) y ventas (RVIE) asociados exclusivamente a este cliente.</p>
                </div>

                {{-- Botonera de Acciones --}}
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Botón Importar desde SUNAT --}}
                    <button
                        type="button"
                        @click="importarSireDesdeSunat"
                        :disabled="sireImportando"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed transition"
                    >
                        <template x-if="!sireImportando">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </template>
                        <template x-if="sireImportando">
                            <svg class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <span x-text="sireImportando ? 'Importando...' : '⬇️ Importar desde SUNAT'"></span>
                    </button>

                    {{-- Botón Descargar Paquete ZIP --}}
                    <a
                        href="{{ route('comprobantes.zip', ['cliente_id' => $cliente->id, 'tipo_registro' => request('sire_tipo'), 'periodo' => request('sire_periodo')]) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 hover:border-gray-400 active:scale-95 transition"
                        title="Descargar paquete ZIP del cliente"
                    >
                        <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        <span>📦 Descargar Paquete ZIP</span>
                    </a>

                    {{-- Botón Exportar Excel --}}
                    <a
                        href="{{ route('comprobantes.export', ['cliente_id' => $cliente->id, 'tipo_registro' => request('sire_tipo'), 'periodo' => request('sire_periodo')]) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 shadow-xs hover:bg-emerald-100 hover:border-emerald-400 active:scale-95 transition"
                        title="Exportar comprobantes del cliente a Excel compatible"
                    >
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125" />
                        </svg>
                        <span>📊 Exportar Excel</span>
                    </a>
                </div>
            </div>

            {{-- Filtros Rápidos Locales --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
                <form method="GET" action="{{ route('clientes.sire', $cliente) }}" class="flex flex-wrap items-end gap-3">

                    {{-- Filtro Registro --}}
                    <div class="space-y-1">
                        <label for="sire_tipo" class="block text-[11px] font-semibold text-gray-700">Tipo de Registro</label>
                        <select name="sire_tipo" id="sire_tipo" class="rounded-lg border-gray-300 py-1.5 px-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Todos los Registros</option>
                            <option value="RCE" {{ request('sire_tipo') === 'RCE' ? 'selected' : '' }}>RCE - Registro de Compras</option>
                            <option value="RVIE" {{ request('sire_tipo') === 'RVIE' ? 'selected' : '' }}>RVIE - Registro de Ventas</option>
                        </select>
                    </div>

                    {{-- Filtro Periodo --}}
                    <div class="space-y-1">
                        <label for="sire_periodo" class="block text-[11px] font-semibold text-gray-700">Periodo Mensual</label>
                        <input
                            type="month"
                            name="sire_periodo"
                            id="sire_periodo"
                            value="{{ request('sire_periodo') }}"
                            class="rounded-lg border-gray-300 py-1.5 px-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500"
                        />
                    </div>

                    {{-- Botón Filtrar --}}
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1 rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-gray-800 active:scale-95 transition"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <span>Filtrar</span>
                    </button>

                    @if (request()->hasAny(['sire_tipo', 'sire_periodo']))
                        <a
                            href="{{ route('clientes.sire', $cliente) }}"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white p-2 text-xs font-semibold text-gray-600 shadow-xs hover:bg-gray-50 active:scale-95 transition"
                            title="Limpiar filtros"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </form>
            </div>

            {{-- Tres Tarjetas Resumen Pre-filtradas para el Cliente --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{-- Base Imponible --}}
                <div class="relative overflow-hidden rounded-xl border border-blue-100 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Total Base Imponible</p>
                            <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($sireTotalBase, 2) }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-400">Subtotal acumulado para este cliente</div>
                </div>

                {{-- IGV --}}
                <div class="relative overflow-hidden rounded-xl border border-emerald-100 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Total IGV</p>
                            <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($sireTotalIgv, 2) }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5 3a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-400">Impuesto General a las Ventas (18%)</div>
                </div>

                {{-- Total Facturado --}}
                <div class="relative overflow-hidden rounded-xl border border-purple-100 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-purple-600">Total Facturado</p>
                            <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($sireTotalFacturado, 2) }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0-8.25a60.106 60.106 0 0 1 15.797-2.101c.727-.198 1.453.342 1.453 1.096V6m-17.25 0h17.25m-17.25 0v1.5m0 0h17.25m-17.25 0v9m17.25-9v9m0 0H2.25m17.25 0a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25m17.25 0v1.5" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
                        <span>Total bruto comprobantes</span>
                        <span class="font-semibold text-gray-700">{{ $sireTotalRegistros }} {{ Str::plural('registro', $sireTotalRegistros) }}</span>
                    </div>
                </div>
            </div>

            {{-- Tabla de Comprobantes del Cliente --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-gray-50/80 uppercase tracking-wider text-gray-500 font-semibold">
                            <tr>
                                <th scope="col" class="px-3.5 py-3 text-center">Periodo</th>
                                <th scope="col" class="px-3 py-3 text-center">Registro</th>
                                <th scope="col" class="px-4 py-3">Comprobante</th>
                                <th scope="col" class="px-4 py-3">Tercero (Emisor/Receptor)</th>
                                <th scope="col" class="px-3 py-3 text-right">Base Imp.</th>
                                <th scope="col" class="px-3 py-3 text-right">IGV</th>
                                <th scope="col" class="px-3.5 py-3 text-right">Total</th>
                                <th scope="col" class="px-3 py-3 text-center">Estado SIRE</th>
                                <th scope="col" class="px-4 py-3 text-center">Archivos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($comprobantesCliente as $comp)
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    {{-- Periodo --}}
                                    <td class="whitespace-nowrap px-3.5 py-3 text-center font-mono font-medium text-gray-700">
                                        {{ $comp->periodo }}
                                    </td>

                                    {{-- Tipo de Registro --}}
                                    <td class="whitespace-nowrap px-3 py-3 text-center">
                                        @if ($comp->tipo_registro === 'RCE')
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200">
                                                RCE Compras
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                                RVIE Ventas
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Comprobante --}}
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="font-semibold text-gray-900">{{ $comp->descripcion_tipo_comprobante }}</span>
                                        <div class="font-mono text-xs text-gray-500">{{ $comp->numero_comprobante }}</div>
                                    </td>

                                    {{-- Tercero --}}
                                    <td class="px-4 py-3 max-w-xs truncate">
                                        <div class="font-medium text-gray-900 truncate" title="{{ $comp->razon_social_tercero }}">
                                            {{ $comp->razon_social_tercero ?: 'Consumidor Final / Anónimo' }}
                                        </div>
                                        <div class="font-mono text-[11px] text-gray-500">
                                            {{ $comp->ruc_tercero ?: '-' }}
                                        </div>
                                    </td>

                                    {{-- Base Imponible --}}
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-gray-700">
                                        {{ number_format((float) $comp->base_imponible, 2) }}
                                    </td>

                                    {{-- IGV --}}
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-gray-700">
                                        {{ number_format((float) $comp->igv, 2) }}
                                    </td>

                                    {{-- Total --}}
                                    <td class="whitespace-nowrap px-3.5 py-3 text-right font-mono font-bold text-gray-900">
                                        <span class="text-[10px] font-normal text-gray-400">{{ $comp->moneda }}</span>
                                        {{ number_format((float) $comp->total, 2) }}
                                    </td>

                                    {{-- Estado SIRE --}}
                                    <td class="whitespace-nowrap px-3 py-3 text-center">
                                        @if ($comp->estado_sire === 'Aceptado')
                                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                                ✓ Aceptado
                                            </span>
                                        @elseif ($comp->estado_sire === 'Rechazado')
                                            <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                                ✗ Rechazado
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                                ● Propuesta
                                            </span>
                                        @endif
                                    </td>

                                     {{-- Columna de Archivos con micro-botones reactivos --}}
                                     <td class="whitespace-nowrap px-4 py-3 text-center" x-data="{
                                         state: {
                                             estado_descarga: '{{ $comp->estado_descarga ?: ($comp->esta_completamente_disponible ? 'disponible' : 'pendiente') }}',
                                             estado_xml: '{{ $comp->tiene_xml ? 'disponible' : ($comp->estado_xml ?: 'pendiente') }}',
                                             estado_cdr: '{{ $comp->tiene_cdr ? 'disponible' : ($comp->estado_cdr ?: 'pendiente') }}',
                                             estado_pdf: '{{ $comp->tiene_pdf ? 'disponible' : ($comp->estado_pdf ?: 'pendiente') }}',
                                             tiene_xml: {{ $comp->tiene_xml ? 'true' : 'false' }},
                                             tiene_cdr: {{ $comp->tiene_cdr ? 'true' : 'false' }},
                                             tiene_pdf: {{ $comp->tiene_pdf ? 'true' : 'false' }},
                                             url_xml: '{{ route('comprobantes.download', [$comp, 'xml']) }}',
                                             url_cdr: '{{ route('comprobantes.download', [$comp, 'cdr']) }}',
                                             url_pdf: '{{ route('comprobantes.download', [$comp, 'pdf']) }}',
                                             mensaje_soap: '{{ addslashes($comp->mensaje_soap ?? '') }}'
                                         }
                                     }">
                                         <div class="inline-flex items-center gap-1.5">
                                             {{-- Micro-botón XML --}}
                                             <template x-if="state.estado_xml === 'disponible' || state.tiene_xml">
                                                 <a :href="state.url_xml"
                                                    class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition shadow-xs"
                                                    title="Descargar archivo XML oficial (UBL 2.1)">
                                                     XML
                                                 </a>
                                             </template>
                                             <template x-if="state.estado_xml === 'descargando' && !state.tiene_xml">
                                                 <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-blue-700 bg-blue-50 border border-blue-200 shadow-xs"
                                                       title="Descargando XML desde SUNAT...">
                                                     <svg class="h-2.5 w-2.5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                                         <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                         <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                     </svg>
                                                     XML
                                                 </span>
                                             </template>
                                             <template x-if="state.estado_xml === 'pendiente' && !state.tiene_xml">
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-gray-500 bg-gray-100 border border-gray-200 shadow-xs cursor-not-allowed"
                                                       title="En cola para descarga">
                                                     XML
                                                 </span>
                                             </template>
                                             <template x-if="state.estado_xml === 'error' && !state.tiene_xml">
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 border border-rose-300 shadow-xs"
                                                       :title="state.mensaje_soap || 'Error en descarga.'">
                                                     XML ⚠
                                                 </span>
                                             </template>

                                             {{-- Micro-botón CDR --}}
                                             <template x-if="state.estado_cdr === 'disponible' || state.tiene_cdr">
                                                 <a :href="state.url_cdr"
                                                    class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition shadow-xs"
                                                    title="Descargar Constancia de Recepción CDR (ZIP)">
                                                     CDR
                                                 </a>
                                             </template>
                                             <template x-if="state.estado_cdr === 'descargando' && !state.tiene_cdr">
                                                 <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-blue-700 bg-blue-50 border border-blue-200 shadow-xs"
                                                       title="Descargando CDR desde SUNAT...">
                                                     <svg class="h-2.5 w-2.5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                                         <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                         <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                     </svg>
                                                     CDR
                                                 </span>
                                             </template>
                                             <template x-if="state.estado_cdr === 'pendiente' && !state.tiene_cdr">
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-gray-500 bg-gray-100 border border-gray-200 shadow-xs cursor-not-allowed"
                                                       title="En cola para descarga">
                                                     CDR
                                                 </span>
                                             </template>
                                             <template x-if="state.estado_cdr === 'error' && !state.tiene_cdr">
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 border border-rose-300 shadow-xs"
                                                       :title="state.mensaje_soap || 'Error en descarga.'">
                                                     CDR ⚠
                                                 </span>
                                             </template>

                                             {{-- Micro-botón PDF --}}
                                             <template x-if="state.estado_pdf === 'disponible' || state.tiene_pdf">
                                                 <a :href="state.url_pdf"
                                                    class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition shadow-xs"
                                                    title="Descargar representación impresa en PDF">
                                                     PDF
                                                 </a>
                                             </template>
                                             <template x-if="state.estado_pdf === 'descargando' && !state.tiene_pdf">
                                                 <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-blue-700 bg-blue-50 border border-blue-200 shadow-xs"
                                                       title="Generando PDF...">
                                                     <svg class="h-2.5 w-2.5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                                         <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                         <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                     </svg>
                                                     PDF
                                                 </span>
                                             </template>
                                             <template x-if="state.estado_pdf === 'pendiente' && !state.tiene_pdf">
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-gray-500 bg-gray-100 border border-gray-200 shadow-xs cursor-not-allowed"
                                                       title="En cola">
                                                     PDF
                                                 </span>
                                             </template>
                                             <template x-if="state.estado_pdf === 'error' && !state.tiene_pdf">
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 border border-rose-300 shadow-xs"
                                                       :title="state.mensaje_soap || 'Error al generar PDF.'">
                                                     PDF ⚠
                                                 </span>
                                             </template>
                                         </div>
                                     </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <svg class="mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                            </svg>
                                            <p class="text-sm font-semibold text-gray-700">Sin comprobantes para este cliente</p>
                                            <p class="mt-1 text-xs text-gray-400">No se encontraron registros de compras (RCE) ni ventas (RVIE) con los filtros actuales.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    {{-- ═══════════════════ PESTAÑA 5: CREDENCIALES API SUNAT ═══════════════════ --}}
    <div x-show="tab === 'datos'" x-cloak class="mt-6 space-y-6">
        {{-- Card Principal --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-xs" x-data="apiCredentialsForm()">
            <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Credenciales API SUNAT</h3>
                        <p class="text-xs text-gray-500">Client ID y Client Secret para integraciones con la API de SUNAT (SIRE, facturación electrónica)</p>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <template x-if="clientIdVisible">
                        <div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 border border-green-200 px-3 py-1 text-xs font-semibold text-green-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                Configurado
                            </span>
                            <p class="mt-1 max-w-[16rem] truncate font-mono text-xs text-gray-800" x-text="clientIdVisible" title="Client ID"></p>
                        </div>
                    </template>
                    <template x-if="!clientIdVisible">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-500">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            Sin configurar
                        </span>
                    </template>
                </div>
            </div>

            <div class="px-6 py-5">
                {{-- Toast de feedback --}}
                <div x-show="toast.mostrar"
                     x-cloak
                     x-transition:enter="transform ease-out duration-300 transition"
                     x-transition:enter-start="translate-y-2 opacity-0"
                     x-transition:enter-end="translate-y-0 opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="mb-4 flex items-center gap-3 rounded-lg border px-4 py-3"
                     :class="toast.tipo === 'success'
                         ? 'bg-green-50 border-green-200 text-green-800'
                         : 'bg-red-50 border-red-200 text-red-800'">
                    <template x-if="toast.tipo === 'success'">
                        <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </template>
                    <template x-if="toast.tipo === 'error'">
                        <svg class="h-4 w-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </template>
                    <p class="text-sm font-medium" x-text="toast.mensaje"></p>
                </div>

                <form @submit.prevent="guardar" autocomplete="off" class="space-y-5">
                    {{-- Campos señuelo: el navegador vuelca aquí el usuario y la
                         clave de la sesión en lugar de tocar Client ID / Secret. --}}
                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="usuario" autocomplete="username" tabindex="-1">
                        <input type="password" name="clave" autocomplete="current-password" tabindex="-1">
                    </div>

                    {{-- Client ID --}}
                    <div>
                        <label for="api_client_id" class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                        <input type="text"
                               id="api_client_id"
                               name="sunat_api_client_id"
                               autocomplete="off"
                               autocapitalize="off"
                               spellcheck="false"
                               data-1p-ignore
                               data-lpignore="true"
                               readonly
                               @focus="$el.removeAttribute('readonly')"
                               x-model="form.sunat_client_id"
                               placeholder="Ej: 4b2c8a1e-f3d9-4c7b-a8e5-1234567890ab"
                               class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-brand-500 focus:ring-brand-500 sm:text-sm font-mono" />
                    </div>

                    {{-- Client Secret. Es texto, no password: si el tipo fuera
                         password el navegador lo trata como un inicio de sesión
                         y rellena la clave del estudio. --}}
                    <div>
                        <label for="api_client_secret" class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                        <div class="relative">
                            <input type="text"
                                   id="api_client_secret"
                                   name="sunat_api_client_secret"
                                   autocomplete="off"
                                   autocapitalize="off"
                                   spellcheck="false"
                                   data-1p-ignore
                                   data-lpignore="true"
                                   readonly
                                   @focus="prepararSecreto($el)"
                                   x-model="form.sunat_client_secret"
                                   :style="mostrarSecret ? '' : '-webkit-text-security: disc'"
                                   placeholder="{{ ! empty($cliente->sunat_client_secret) ? '••••••••••••••••' : 'Ingrese el Client Secret de la aplicación registrada' }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-brand-500 focus:ring-brand-500 sm:text-sm font-mono pr-10" />
                            <button type="button"
                                    @click="alternarSecreto()"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition"
                                    title="Mostrar / ocultar clave">
                                <template x-if="!mostrarSecret">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </template>
                                <template x-if="mostrarSecret">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </template>
                            </button>
                        </div>
                        @if (! empty($cliente->sunat_client_secret))
                            <p class="mt-1 text-[11px] text-gray-500">Deje este campo vacío si no desea modificar el Client Secret configurado.</p>
                        @endif
                    </div>

                    {{-- Botón Guardar --}}
                    <div class="flex items-center justify-between pt-2">
                        <button type="submit"
                                :disabled="guardando"
                                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            <template x-if="!guardando">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </template>
                            <template x-if="guardando">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="guardando ? 'Guardando...' : '{{ ! empty($cliente->sunat_client_id) ? 'Actualizar Credenciales' : 'Guardar Credenciales' }}'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Card de Ayuda --}}
        <div class="rounded-xl border border-amber-200 bg-amber-50/50 shadow-xs">
            <div class="px-6 py-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 mt-0.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-amber-900">Cómo obtener las credenciales</h4>
                        <ol class="mt-2 space-y-1.5 text-xs text-amber-800 list-decimal list-inside">
                            <li>Ingresar al portal <span class="font-semibold">SUNAT SOL</span> con la Clave SOL del contribuyente.</li>
                            <li>Navegar a: <span class="font-mono bg-amber-100 px-1 py-0.5 rounded text-[11px]">Empresas &gt; Credenciales de API SUNAT &gt; Credenciales de API SUNAT &gt; Gestión Credenciales de API SUNAT</span>.</li>
                            <li>Registrar una aplicación. En <span class="font-bold text-amber-950">'Alcance'</span> seleccionar obligatoriamente <span class="font-bold underline text-amber-950">'Desktop'</span> <span class="text-amber-700">(SUNAT bloquea las llamadas API SIRE con Error 401 si se selecciona 'Web')</span>.</li>
                            <li>Marcar obligatoriamente el check: <span class="font-semibold font-mono bg-amber-100 px-1 py-0.5 rounded text-[11px]">'MIGE RCE y RVIE - SIRE /v1/contribuyente/migeigv'</span>.</li>
                            <li>Copiar el <span class="font-semibold">'ID'</span> y la <span class="font-semibold">'CLAVE'</span> y pegarlos en este formulario.</li>
                        </ol>
                        <p class="mt-3 text-[11px] text-amber-700 flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            El Client Secret se almacena cifrado con AES-256-CBC en la base de datos.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('clientes.partials.menu-accesos-sunat')
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
                alertasPendientesCount: config.alertasPendientes ?? 0,
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
                    document.querySelectorAll('[data-ancla-sunat]').forEach((nodo) => nodo.removeAttribute('data-ancla-sunat'));
                    event.currentTarget.setAttribute('data-ancla-sunat', '1');
                    this.menuSunat.url = url;
                    this.menuSunat.nombre = nombre;
                    this.menuSunat.ruc = ruc;
                    window.posicionarMenuAccesosSunat(this.menuSunat);
                    this.menuSunat.abierto = true;
                    if (!this._oyendoScrollSunat) {
                        this._oyendoScrollSunat = true;
                        const seguir = () => {
                            if (!this.menuSunat.abierto) {
                                return;
                            }
                            window.posicionarMenuAccesosSunat(this.menuSunat);
                        };
                        window.addEventListener('scroll', seguir, true);
                        window.addEventListener('resize', seguir);
                    }
                },
                elegirAccesoSunat(destino) {
                    const url = this.menuSunat.url + '?destino=' + encodeURIComponent(destino);
                    const nombre = this.menuSunat.nombre;
                    const ruc = this.menuSunat.ruc;
                    this.menuSunat.abierto = false;
                    this.lanzarAcceso(url, 'SUNAT', nombre, ruc);
                },
                async lanzarAcceso(url, portal, nombre, ruc) {
                    if (this.hudAcceso.animTimer) clearInterval(this.hudAcceso.animTimer);
                    this.hudAcceso.portal = portal;
                    this.hudAcceso.clienteNombre = nombre;
                    this.hudAcceso.clienteRuc = ruc;
                    this.hudAcceso.icono = portal === 'SUNAT' ? '🚀' : '👷';
                    this.hudAcceso.progreso = 15;
                    this.hudAcceso.faseTexto = portal === 'SUNAT'
                        ? 'Ingresando a SUNAT...'
                        : 'Ingresando a SUNAFIL...';
                    this.hudAcceso.completado = false;
                    this.hudAcceso.error = null;
                    this.hudAcceso.abierto = true;

                    // Animación fluida sincronizada mientras el navegador inicia y se autentica
                    let step = 0;
                    this.hudAcceso.animTimer = setInterval(() => {
                        step++;
                        if (step === 1 && this.hudAcceso.progreso < 40) {
                            this.hudAcceso.progreso = 38;
                            this.hudAcceso.faseTexto = 'Preparando el navegador...';
                        } else if (step === 2 && this.hudAcceso.progreso < 70) {
                            this.hudAcceso.progreso = 68;
                            this.hudAcceso.faseTexto = portal === 'SUNAT'
                                ? 'Ingresando a SUNAT...'
                                : 'Ingresando a SUNAFIL...';
                        } else if (step >= 3 && this.hudAcceso.progreso < 92) {
                            this.hudAcceso.progreso = Math.min(92, this.hudAcceso.progreso + 4);
                            this.hudAcceso.faseTexto = 'La ventana se abre cuando la sesión ya está lista...';
                        }
                    }, 550);

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
                            this.hudAcceso.faseTexto = '¡Sesión lista!';
                            // Se cierra de inmediato para desaparecer por completo antes de que la ventana tome el foco
                            this.hudAcceso.abierto = false;
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
                    event.preventDefault();
                    if (this.tab === seccion) {
                        return;
                    }
                    this.destinoPendiente = event.currentTarget.getAttribute('href');
                    this.tab = seccion;
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
