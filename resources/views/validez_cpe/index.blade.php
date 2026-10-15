@extends('layouts.app')

@section('title', 'Validez de Comprobantes (API SUNAT)')

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 max-w-[1700px] mx-auto space-y-6" x-data="moduloValidezCpe({
    clientes: @js($clientesData),
    clienteInicialId: {{ $clienteSeleccionado?->id ?? 'null' }},
    tiposCpe: @js($tiposComprobante),
    rutas: {
        validarIndividual: '{{ route('validez-cpe.validar-individual') }}',
        cargarArchivo: '{{ route('validez-cpe.cargar-archivo') }}',
        validarLote: '{{ route('validez-cpe.validar-lote') }}',
        exportarReporte: '{{ route('validez-cpe.exportar-reporte') }}',
        plantilla: '{{ route('validez-cpe.plantilla') }}'
    }
})">

    {{-- ═══════════════════════════ HEADER DEL MÓDULO (DISEÑO EJECUTIVO / PREMIUM) ═══════════════════════════ --}}
    {{-- Se eliminó overflow-hidden del contenedor principal para que la ventana flotante no se corte --}}
    <div class="relative rounded-3xl border border-slate-200/90 bg-gradient-to-br from-white via-slate-50/50 to-teal-50/30 p-6 sm:p-8 shadow-xs">
        {{-- Patrón sutil de fondo encapsulado para evitar desbordes visuales sin cortar dropdowns --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-3xl">
            <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-teal-500/5 blur-3xl"></div>
            <div class="absolute -left-16 -bottom-16 h-64 w-64 rounded-full bg-sky-500/5 blur-3xl"></div>
        </div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            {{-- Bloque Izquierdo: Título, Metadatos y Salvaguardas Tributarias --}}
            <div class="space-y-3.5 max-w-4xl">
                {{-- Eyebrow con Insignias Oficiales --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-100/80 border border-teal-300/80 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-teal-900 shadow-2xs">
                        <svg class="h-3.5 w-3.5 text-teal-700" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        Auditoría Tributaria SUNAT
                    </span>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200/90 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        API SUNAT OAuth 2.0
                    </span>

                    <span class="inline-flex items-center gap-1 rounded-full bg-white border border-slate-200 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">
                        En Tiempo Real
                    </span>
                </div>

                {{-- Título Principal con Jerarquía Visual Robusta --}}
                <div class="flex items-center gap-3.5">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-md shadow-teal-500/20">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 leading-tight">
                            Consulta de Validez de Comprobantes de Pago
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-0.5">
                            Verificación preventiva oficial de comprobantes electrónicos (CPE) y físicos en los servidores de SUNAT.
                        </p>
                    </div>
                </div>

                {{-- Pills de Resguardo Tributario --}}
                <div class="flex flex-wrap items-center gap-2 pt-1 text-xs">
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/90 border border-slate-200/80 px-2.5 py-1 text-slate-700 shadow-2xs">
                        <span class="text-teal-600 font-black">✓</span>
                        <span>Resguardo Crédito Fiscal <strong class="text-slate-900">(Arts. 18°-19° LIGV)</strong></span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/90 border border-slate-200/80 px-2.5 py-1 text-slate-700 shadow-2xs">
                        <span class="text-teal-600 font-black">✓</span>
                        <span>Deducción Costo / Gasto <strong class="text-slate-900">(Art. 37° LIR)</strong></span>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/90 border border-slate-200/80 px-2.5 py-1 text-slate-700 shadow-2xs">
                        <span class="text-teal-600 font-black">✓</span>
                        <span>Condición de <strong class="text-slate-900">Habido</strong> & Estado RUC</span>
                    </span>
                </div>
            </div>

            {{-- Bloque Derecho: Selector Flotante Premium de Empresa Consultante (Z-Index Elevado y No Cortado) --}}
            <div class="shrink-0 flex flex-col sm:flex-row sm:items-center xl:flex-col xl:items-end gap-3 z-40">
                <div class="relative w-full sm:w-auto"
                     @click.outside="openEmpresa = false">

                    {{-- Botón Disparador Premium para Empresa Consultante (Compacto, Elegante y Rápido) --}}
                    <button type="button"
                            @click="openEmpresa = !openEmpresa"
                            class="w-full sm:w-auto flex items-center justify-between gap-3 rounded-2xl bg-white border border-slate-200 hover:border-teal-500 hover:shadow-md transition-colors duration-100 py-2 px-3 pr-3.5 shadow-2xs text-left cursor-pointer group focus:outline-none focus:ring-4 focus:ring-teal-500/10">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-teal-800 text-white shadow-2xs group-hover:scale-105 transition-transform duration-100">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </div>
                        <div class="min-w-0 max-w-[14rem] sm:max-w-[18rem]">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9.5px] font-black uppercase tracking-wider text-slate-400">Empresa Consultante</span>
                                <span x-show="clienteActual?.tiene_credenciales" class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" title="API SUNAT Conectada"></span>
                                <span x-show="!clienteActual?.tiene_credenciales" class="h-2 w-2 rounded-full bg-amber-500" title="Requiere Credenciales API"></span>
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5 truncate">
                                <span class="font-mono text-[10.5px] font-bold text-slate-700 bg-slate-100 px-1.5 py-0.2 rounded" x-text="clienteActual?.ruc || '—'"></span>
                                <span class="text-xs font-bold text-slate-900 truncate" x-text="clienteActual?.razon_social || 'Seleccione Empresa'"></span>
                            </div>
                        </div>
                        <div class="pl-1">
                            <svg class="h-4 w-4 text-slate-400 group-hover:text-teal-600 transition-transform duration-100"
                                 :class="{ 'rotate-180 text-teal-600': openEmpresa }"
                                 fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </button>

                    {{-- Caja Flotante Premium Libre de Cortes con Contorno Íntegro y Cero Lag --}}
                    <div x-show="openEmpresa"
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                         class="absolute right-0 top-full mt-2 w-76 sm:w-88 rounded-2xl border border-slate-200 bg-white shadow-2xl z-50 p-2 ring-1 ring-black/10">

                        {{-- Buscador Rápido y Compacto de Empresas --}}
                        <div class="relative mb-1.5">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                            </div>
                            <input type="text"
                                   x-model="busquedaEmpresa"
                                   placeholder="Buscar por RUC o Razón Social..."
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50/70 py-1.5 pl-8 pr-2.5 text-xs font-medium text-slate-800 placeholder-slate-400 hover:border-slate-300 focus:border-teal-600 focus:bg-white focus:ring-2 focus:ring-teal-600/20 focus:outline-none transition-colors duration-75">
                        </div>

                        <div class="px-2 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400 flex items-center justify-between border-b border-slate-100 mb-1">
                            <span>Cartera de Clientes</span>
                            <span x-text="clientesFiltrados.length + ' empresas'"></span>
                        </div>

                        {{-- Lista Scrollable de Clientes con Diseño Compacto, Padding Anti-Cortes y Rápida Selección --}}
                        <div class="max-h-56 overflow-y-auto space-y-1 p-1">
                            <template x-for="c in clientesFiltrados" :key="c.id">
                                <button type="button"
                                        @click="seleccionarEmpresa(c.id)"
                                        class="w-full flex items-center justify-between gap-2.5 px-2.5 py-1.5 rounded-lg text-left transition-colors duration-75 cursor-pointer border"
                                        :class="clienteId == c.id
                                            ? 'bg-teal-50/90 text-teal-950 font-bold border-teal-500 ring-1 ring-inset ring-teal-400/60 shadow-2xs'
                                            : 'border-transparent hover:bg-slate-100/80 text-slate-700 font-normal'">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono text-[9.5px] font-bold px-1.5 py-0.2 rounded shrink-0"
                                                  :class="clienteId == c.id ? 'bg-teal-200/80 text-teal-900' : 'bg-slate-100 text-slate-700 border border-slate-200/80'"
                                                  x-text="c.ruc"></span>
                                            <span class="text-[11.5px] truncate font-bold" x-text="c.razon_social"></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5 text-[9.5px]">
                                            <span x-show="c.tiene_credenciales" class="inline-flex items-center gap-1 font-bold text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> API Conectada
                                            </span>
                                            <span x-show="!c.tiene_credenciales" class="inline-flex items-center gap-1 font-bold text-amber-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Sin Credenciales
                                            </span>
                                        </div>
                                    </div>
                                    <div class="shrink-0" x-show="clienteId == c.id">
                                        <span class="flex h-4.5 w-4.5 items-center justify-center rounded-full bg-teal-600 text-white text-[10px] font-bold">✓</span>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Pill de Estado de Credenciales / Enlace Rápido --}}
                <div class="flex items-center gap-2">
                    <template x-if="clienteActual?.tiene_credenciales">
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 border border-emerald-200/80 px-3 py-2 text-xs font-bold text-emerald-800 shadow-2xs" title="API configurada y lista para consultas">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            API Conectada
                        </span>
                    </template>
                    <template x-if="!clienteActual?.tiene_credenciales">
                        <a :href="'/clientes/' + clienteId + '?tab=datos'"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition cursor-pointer"
                           title="Haga clic para configurar Id y Clave en la ficha del cliente">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            Configurar API SUNAT
                        </a>
                    </template>
                </div>
            </div>
        </div>

        {{-- Alerta Desplegable si la Empresa Actual no tiene credenciales --}}
        <template x-if="!clienteActual?.tiene_credenciales">
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-950 shadow-2xs">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 mt-0.5">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-amber-950 text-sm">Esta empresa aún no cuenta con Credenciales de API SUNAT para Validez de Comprobantes</p>
                        <p class="mt-1 text-amber-800 leading-relaxed">
                            Para consultar comprobantes con la API oficial de esta empresa, ingrese a SUNAT SOL en la ruta:
                            <span class="font-mono font-bold bg-amber-100 px-1.5 py-0.5 rounded text-[11px] text-amber-950">Empresas &gt; Comprobantes de Pago &gt; Consulta de Validez &gt; Credenciales de API SUNAT</span>,
                            genere el <strong>Client ID</strong> y <strong>Client Secret</strong>, y guárdelos en el expediente del cliente.
                        </p>
                        <div class="mt-2.5">
                            <a :href="'/clientes/' + clienteId + '?tab=datos'"
                               class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 px-3.5 py-1.5 font-bold text-white transition shadow-xs text-xs">
                                <span>Configurar Credenciales en Ficha del Cliente</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ═══════════════════════════ SELECTOR DE MODALIDAD (SEGMENTED CONTROL PREMIUM) ═══════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div class="inline-flex p-1.5 rounded-2xl bg-slate-100/90 border border-slate-200/80 shadow-2xs text-xs">
            <button type="button"
                    @click="tab = 'individual'"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 flex items-center gap-2 cursor-pointer outline-none focus:outline-none"
                    :class="tab === 'individual' ? 'bg-white text-slate-900 shadow-xs ring-1 ring-slate-200/80' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'">
                <svg class="h-4 w-4" :class="tab === 'individual' ? 'text-teal-600' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <span>Consulta Individual</span>
            </button>

            <button type="button"
                    @click="tab = 'masivo'"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 flex items-center gap-2 cursor-pointer outline-none focus:outline-none"
                    :class="tab === 'masivo' ? 'bg-white text-slate-900 shadow-xs ring-1 ring-slate-200/80' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'">
                <svg class="h-4 w-4" :class="tab === 'masivo' ? 'text-teal-600' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                </svg>
                <span>Validación Masiva (Excel / New Contasis / SIRE)</span>
                <template x-if="masivoFilas.length > 0">
                    <span class="rounded-full bg-teal-100 text-teal-800 text-[10px] font-black px-2 py-0.5" x-text="masivoFilas.length"></span>
                </template>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════ PESTAÑA 1: CONSULTA INDIVIDUAL ═══════════════════════════ --}}
    <div x-show="tab === 'individual'" x-cloak class="space-y-6">

        {{-- Formulario de Consulta Individual con Estructura Cohesiva y Anclada --}}
        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-xs">
            <div class="flex items-center justify-between pb-5 mb-6 border-b border-slate-100">
                <div class="flex items-center gap-3.5">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-50 text-teal-700 border border-teal-200/60 shadow-2xs">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900">
                            Auditoría Individual de Comprobante
                        </h2>
                        <p class="text-xs text-slate-500 font-medium">Parámetros tributarios oficiales consultados en tiempo real contra los servidores de SUNAT</p>
                    </div>
                </div>

                <span class="hidden sm:inline-flex items-center gap-1.5 text-xs text-slate-400 font-mono">
                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Resguardo Fiscal LIGV
                </span>
            </div>

            <form @submit.prevent="ejecutarConsultaIndividual()" class="space-y-6">

                {{-- CONTENEDORES ESTRUCTURADOS CON DISTRIBUCIÓN AMPLIA Y SIN DISTORSIÓN --}}
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

                    {{-- GRUPO 1: IDENTIDAD TRIBUTARIA DEL EMISOR Y DOCUMENTO (5 Columnas en XL) --}}
                    <div class="xl:col-span-5 rounded-2xl border border-slate-200/90 bg-slate-50/70 p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-600 pb-2.5 border-b border-slate-200/80">
                            <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" />
                            </svg>
                            <span>1. Emisor y Tipo de Comprobante</span>
                        </div>

                        <div class="space-y-4">
                            {{-- RUC Emisor --}}
                            <div>
                                <label for="ind_ruc" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>RUC Emisor (Proveedor) <span class="text-rose-500">*</span></span>
                                    <span class="text-[10px] text-slate-400 font-mono">11 dígitos</span>
                                </label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                        </svg>
                                    </div>
                                    <input type="text"
                                           id="ind_ruc"
                                           x-model="individual.numRuc"
                                           maxlength="11"
                                           placeholder="Ej: 20100047218"
                                           class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-3.5 text-sm font-mono font-bold text-slate-800 placeholder-slate-400 hover:border-slate-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 focus:outline-none transition-all shadow-2xs"
                                           required />
                                </div>
                            </div>

                            {{-- Tipo de CPE con Dropdown Flotante Limpio, Sin Duplicados, Súper Rápido y con Borde Íntegro --}}
                            <div class="relative z-30"
                                 @click.outside="openTipoCpe = false">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>Tipo de CPE <span class="text-rose-500">*</span></span>
                                    <span class="text-[10px] text-teal-700 font-mono font-bold bg-teal-50 px-1.5 py-0.2 rounded border border-teal-200"
                                          x-text="'Cód. ' + individual.codComp"></span>
                                </label>

                                {{-- Botón Disparador Flotante --}}
                                <button type="button"
                                        @click="openTipoCpe = !openTipoCpe"
                                        class="w-full flex items-center justify-between gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 hover:border-teal-500 hover:bg-slate-50/50 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 focus:outline-none transition-colors duration-75 shadow-2xs text-left cursor-pointer">
                                    <div class="flex items-center gap-2 truncate">
                                        <span class="inline-flex items-center justify-center rounded-lg bg-teal-100 text-teal-800 font-mono text-[10.5px] font-black px-2 py-0.5 shrink-0"
                                              x-text="individual.codComp"></span>
                                        <span class="truncate text-slate-900 font-bold text-xs"
                                              x-text="tiposCpe.find(t => t.codigo === individual.codComp)?.descripcion || 'Factura Electrónica'"></span>
                                    </div>
                                    <svg class="h-4 w-4 text-slate-400 shrink-0 transition-transform duration-100"
                                         :class="{ 'rotate-180 text-teal-600': openTipoCpe }"
                                         fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>

                                {{-- Caja Flotante Premium sin duplicar código, compacta, rápida y con contorno visible completo --}}
                                <div x-show="openTipoCpe"
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 translate-y-1 scale-98"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave-end="opacity-0 translate-y-1 scale-98"
                                     class="absolute left-0 top-full mt-2 w-full sm:w-84 rounded-2xl border border-slate-200 bg-white shadow-2xl z-50 p-2 ring-1 ring-black/10">

                                    <div class="px-2 py-1 border-b border-slate-100 mb-1 flex items-center justify-between text-[10.5px]">
                                        <span class="font-black text-slate-500 uppercase tracking-wider">Catálogo SUNAT</span>
                                        <span class="text-[10px] text-teal-700 font-bold">10 Tipos Oficiales</span>
                                    </div>

                                    <div class="max-h-60 overflow-y-auto space-y-1 p-1">
                                        <template x-for="t in tiposCpe" :key="t.codigo">
                                            <button type="button"
                                                    @click="seleccionarTipoCpe(t.codigo)"
                                                    class="w-full flex items-center justify-between gap-2.5 px-2.5 py-1.5 rounded-lg text-left text-xs transition-colors duration-75 cursor-pointer border"
                                                    :class="individual.codComp === t.codigo
                                                        ? 'bg-teal-50 text-teal-950 font-bold border-teal-500 ring-1 ring-inset ring-teal-400/60 shadow-2xs'
                                                        : 'border-transparent text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-medium'">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="font-mono text-[10.5px] font-bold px-1.5 py-0.2 rounded shrink-0"
                                                          :class="individual.codComp === t.codigo ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-700 border border-slate-200'"
                                                          x-text="t.codigo"></span>
                                                    <span class="truncate text-[11.5px] font-bold" x-text="t.descripcion"></span>
                                                </div>
                                                <span class="text-[9.5px] font-mono font-bold uppercase text-slate-400 shrink-0" x-text="t.abreviatura"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- GRUPO 2: IDENTIFICACIÓN DEL COMPROBANTE Y MONTO (7 Columnas en XL con Ancho Completo Holgado) --}}
                    <div class="xl:col-span-7 rounded-2xl border border-slate-200/90 bg-slate-50/70 p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-600 pb-2.5 border-b border-slate-200/80">
                            <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span>2. Identificación del Comprobante y Monto</span>
                        </div>

                        {{-- Cuadrícula Cómoda: Serie, Número y Fecha arriba; Total con campo ancho abajo para evitar distorsión --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4">
                            {{-- Serie --}}
                            <div class="lg:col-span-3">
                                <label for="ind_serie" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>Serie <span class="text-rose-500">*</span></span>
                                    <span class="text-[10px] text-slate-400">4 car.</span>
                                </label>
                                <input type="text"
                                       id="ind_serie"
                                       x-model="individual.numeroSerie"
                                       @input="individual.numeroSerie = individual.numeroSerie.toUpperCase()"
                                       maxlength="5"
                                       placeholder="Ej: F001"
                                       class="block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-mono font-bold uppercase text-slate-800 placeholder-slate-400 hover:border-slate-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 focus:outline-none transition-all shadow-2xs"
                                       required />
                            </div>

                            {{-- Número Correlativo --}}
                            <div class="lg:col-span-4">
                                <label for="ind_numero" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>Número <span class="text-rose-500">*</span></span>
                                    <span class="text-[10px] text-slate-400">Correlativo</span>
                                </label>
                                <input type="text"
                                       id="ind_numero"
                                       x-model="individual.numero"
                                       maxlength="12"
                                       placeholder="Ej: 1245"
                                       class="block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-mono font-bold text-slate-800 placeholder-slate-400 hover:border-slate-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 focus:outline-none transition-all shadow-2xs"
                                       required />
                            </div>

                            {{-- Fecha de Emisión --}}
                            <div class="lg:col-span-5">
                                <label for="ind_fecha" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>Fecha Emisión <span class="text-rose-500">*</span></span>
                                    <span class="text-[10px] text-slate-400">SUNAT</span>
                                </label>
                                <input type="date"
                                       id="ind_fecha"
                                       x-model="individual.fechaEmision"
                                       class="block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 hover:border-slate-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 focus:outline-none transition-all shadow-2xs cursor-pointer"
                                       required />
                            </div>

                            {{-- Importe Total (Moneda Original): Con todo el ancho necesario para que no se corte ni distorsione --}}
                            <div class="lg:col-span-12 pt-1">
                                <label for="ind_monto" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span>Total (Moneda Original)</span>
                                        <span class="text-[10px] text-slate-400 font-normal">(Opcional en Boletas, obligatorio para validar monto)</span>
                                    </span>
                                    <span class="text-[10px] font-bold text-teal-800 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded-lg">
                                        S/ Soles o US$ Dólares
                                    </span>
                                </label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 font-bold text-xs">
                                        S/ ó $
                                    </div>
                                    <input type="number"
                                           id="ind_monto"
                                           step="0.01"
                                           x-model="individual.monto"
                                           placeholder="0.00"
                                           class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-14 pr-3.5 text-sm font-mono font-bold text-slate-800 placeholder-slate-400 hover:border-slate-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 focus:outline-none transition-all shadow-2xs" />
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1.5 leading-snug">
                                    💡 Registre el importe total en la divisa original en que fue emitido el comprobante ante SUNAT.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Barra Inferior con Garantía de Rendimiento y Botón de Auditoría --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 pt-4 border-t border-slate-100">
                    <p class="text-xs text-slate-500 flex items-center gap-2">
                        <svg class="h-4.5 w-4.5 text-teal-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <span>Consulta en memoria en tiempo real. Cero huella o almacenamiento en base de datos.</span>
                    </p>

                    <button type="submit"
                            :disabled="consultandoIndividual || !clienteActual?.tiene_credenciales"
                            class="inline-flex items-center justify-center gap-2.5 rounded-xl bg-gradient-to-r from-teal-600 to-teal-700 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:from-teal-700 hover:to-teal-800 hover:shadow-md active:scale-[0.99] focus:outline-none focus:ring-4 focus:ring-teal-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer">
                        <template x-if="!consultandoIndividual">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </template>
                        <template x-if="consultandoIndividual">
                            <svg class="h-4.5 w-4.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <span x-text="consultandoIndividual ? 'Consultando en servidores SUNAT...' : 'Consultar Validez en SUNAT'"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- RESULTADO DE LA CONSULTA INDIVIDUAL (CERTIFICADO OFICIAL DE AUDITORÍA TRIBUTARIA) --}}
        <template x-if="resultadoIndividual">
            <div class="rounded-3xl border bg-white shadow-sm overflow-hidden transition-all duration-300"
                 :class="{
                     'border-emerald-300 border-t-4 border-t-emerald-600': resultadoIndividual.severidad === 'success',
                     'border-amber-300 border-t-4 border-t-amber-500': resultadoIndividual.severidad === 'warning',
                     'border-rose-300 border-t-4 border-t-rose-600': resultadoIndividual.severidad === 'danger'
                 }">

                {{-- Encabezado: Dictamen Superior y Metadatos Oficiales --}}
                <div class="px-6 sm:px-8 py-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
                     :class="{
                         'bg-emerald-50/50': resultadoIndividual.severidad === 'success',
                         'bg-amber-50/50': resultadoIndividual.severidad === 'warning',
                         'bg-rose-50/50': resultadoIndividual.severidad === 'danger'
                     }">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-black tracking-wide shadow-2xs"
                                  :class="{
                                      'bg-emerald-100 text-emerald-950 border border-emerald-300': resultadoIndividual.severidad === 'success',
                                      'bg-amber-100 text-amber-950 border border-amber-300': resultadoIndividual.severidad === 'warning',
                                      'bg-rose-100 text-rose-950 border border-rose-300': resultadoIndividual.severidad === 'danger'
                                  }">
                                <span class="h-2 w-2 rounded-full"
                                      :class="{
                                          'bg-emerald-600 animate-pulse': resultadoIndividual.severidad === 'success',
                                          'bg-amber-600 animate-pulse': resultadoIndividual.severidad === 'warning',
                                          'bg-rose-600 animate-pulse': resultadoIndividual.severidad === 'danger'
                                      }"></span>
                                <span x-text="resultadoIndividual.es_valido_credito_fiscal ? '✓ APTO PARA CRÉDITO FISCAL & DEDUCCIÓN DE GASTO' : (resultadoIndividual.severidad === 'warning' ? '⚠ OBSERVADO — RIESGO DE REPARO TRIBUTARIO' : '✕ COMPROBANTE NO VÁLIDO / NO REGISTRADO ANTE SUNAT')"></span>
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-600 font-medium">
                            Conforme a los <strong class="text-slate-900">Arts. 18° y 19° del TUO de la Ley del IGV</strong> y <strong class="text-slate-900">Art. 37° de la Ley del Impuesto a la Renta</strong>.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-teal-50 border border-teal-200 px-3 py-1.5 text-xs font-bold text-teal-800 shadow-2xs">
                            <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            Constancia Oficial SUNAT OAuth 2.0
                        </span>
                        <span class="text-xs font-mono text-slate-500 font-semibold" x-text="resultadoIndividual.consultado_at?.substring(0, 19).replace('T', ' ')"></span>
                    </div>
                </div>

                {{-- Las 3 Columnas Clave de Validación (Tiles Métricas de Alta Definición) --}}
                <div class="p-6 sm:p-8 bg-white border-b border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-5">
                    {{-- Tile 1: Estado CPE --}}
                    <div class="p-4 sm:p-5 rounded-2xl border border-slate-200/90 bg-slate-50/40 hover:bg-white hover:shadow-md transition-all duration-200">
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-2.5">
                            <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">1. Estado en SUNAT</span>
                            <span class="font-mono text-[10px] font-black px-2 py-0.5 rounded bg-slate-200/80 text-slate-800"
                                  x-text="'Cód. ' + (resultadoIndividual.estado_cp?.codigo ?? '—')"></span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <template x-if="resultadoIndividual.estado_cp?.codigo === '1'">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 shrink-0">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            </template>
                            <template x-if="resultadoIndividual.estado_cp?.codigo !== '1'">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100 text-rose-700 shrink-0">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            </template>
                            <span class="text-sm sm:text-base font-black tracking-tight"
                                  :class="resultadoIndividual.estado_cp?.codigo === '1' ? 'text-emerald-800' : 'text-rose-800'"
                                  x-text="resultadoIndividual.estado_cp?.label || 'DESCONOCIDO'"></span>
                        </div>
                        <p class="text-xs text-slate-500 mt-2.5 leading-snug font-medium" x-text="resultadoIndividual.estado_cp?.descripcion || '—'"></p>
                    </div>

                    {{-- Tile 2: Padrón RUC --}}
                    <div class="p-4 sm:p-5 rounded-2xl border border-slate-200/90 bg-slate-50/40 hover:bg-white hover:shadow-md transition-all duration-200">
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-2.5">
                            <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">2. Padrón RUC Emisor</span>
                            <span class="font-mono text-[10px] font-black px-2 py-0.5 rounded bg-slate-200/80 text-slate-800"
                                  x-text="'Cód. ' + (resultadoIndividual.estado_ruc?.codigo ?? '—')"></span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <template x-if="resultadoIndividual.estado_ruc?.es_activo">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 shrink-0">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                    </svg>
                                </div>
                            </template>
                            <template x-if="!resultadoIndividual.estado_ruc?.es_activo">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100 text-rose-700 shrink-0">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                            </template>
                            <span class="text-sm sm:text-base font-black tracking-tight"
                                  :class="resultadoIndividual.estado_ruc?.es_activo ? 'text-emerald-800' : 'text-rose-800'"
                                  x-text="resultadoIndividual.estado_ruc?.label || 'NO DISPONIBLE'"></span>
                        </div>
                        <p class="text-xs text-slate-500 mt-2.5 leading-snug font-medium" x-text="resultadoIndividual.estado_ruc?.descripcion || '—'"></p>
                    </div>

                    {{-- Tile 3: Condición de Domicilio --}}
                    <div class="p-4 sm:p-5 rounded-2xl border border-slate-200/90 bg-slate-50/40 hover:bg-white hover:shadow-md transition-all duration-200">
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-2.5">
                            <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">3. Condición de Domicilio</span>
                            <span class="font-mono text-[10px] font-black px-2 py-0.5 rounded bg-slate-200/80 text-slate-800"
                                  x-text="'Cód. ' + (resultadoIndividual.condicion_domicilio?.codigo ?? '—')"></span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <template x-if="resultadoIndividual.condicion_domicilio?.es_habido">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 shrink-0">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                </div>
                            </template>
                            <template x-if="!resultadoIndividual.condicion_domicilio?.es_habido">
                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-700 shrink-0">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                            </template>
                            <span class="text-sm sm:text-base font-black tracking-tight"
                                  :class="resultadoIndividual.condicion_domicilio?.es_habido ? 'text-emerald-800' : 'text-amber-800'"
                                  x-text="resultadoIndividual.condicion_domicilio?.label || 'NO DISPONIBLE'"></span>
                        </div>
                        <p class="text-xs text-slate-500 mt-2.5 leading-snug font-medium" x-text="resultadoIndividual.condicion_domicilio?.descripcion || '—'"></p>
                    </div>
                </div>

                {{-- Tira Minimalista de Atributos del Comprobante Consultado --}}
                <div class="px-6 sm:px-8 py-4 bg-slate-50/70 border-b border-slate-100 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Documento Auditado</span>
                        <p class="font-mono font-bold text-slate-900 mt-0.5 text-xs sm:text-sm"
                           x-text="(individual.codComp === '01' ? 'Factura ' : (individual.codComp === '03' ? 'Boleta ' : (individual.codComp === '07' ? 'Nota Crédito ' : 'Doc. '))) + individual.numeroSerie + '-' + individual.numero"></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">RUC Proveedor</span>
                        <p class="font-mono font-bold text-slate-900 mt-0.5 text-xs sm:text-sm" x-text="individual.numRuc"></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Fecha de Emisión</span>
                        <p class="font-mono font-bold text-slate-800 mt-0.5 text-xs sm:text-sm" x-text="individual.fechaEmision"></p>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Importe Validado</span>
                        <p class="font-mono font-bold text-slate-900 mt-0.5 text-xs sm:text-sm"
                           x-text="individual.monto ? ('S/ ' + individual.monto + ' (Moneda Orig.)') : 'No informado'"></p>
                    </div>
                </div>

                {{-- Dictamen y Efecto Tributario Oficial --}}
                <div class="p-6 sm:p-8">
                    <div class="rounded-2xl p-4 sm:p-5 flex items-start gap-4"
                         :class="{
                             'bg-emerald-50/90 border border-emerald-200 text-emerald-950': resultadoIndividual.severidad === 'success',
                             'bg-amber-50/90 border border-amber-200 text-amber-950': resultadoIndividual.severidad === 'warning',
                             'bg-rose-50/90 border border-rose-200 text-rose-950': resultadoIndividual.severidad === 'danger'
                         }">
                        <div class="mt-0.5 shrink-0">
                            <template x-if="resultadoIndividual.severidad === 'success'">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-white shadow-2xs">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            </template>
                            <template x-if="resultadoIndividual.severidad === 'warning'">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-500 text-white shadow-2xs">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>
                            </template>
                            <template x-if="resultadoIndividual.severidad === 'danger'">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-white shadow-2xs">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            </template>
                        </div>
                        <div class="flex-1 space-y-1">
                            <h4 class="text-xs font-black uppercase tracking-wider opacity-90">
                                Diagnóstico Tributario & Dictamen Contable
                            </h4>
                            <p class="text-xs sm:text-sm leading-relaxed font-normal" x-text="resultadoIndividual.diagnostico"></p>
                        </div>
                    </div>

                    {{-- Observaciones SUNAT si existen --}}
                    <template x-if="resultadoIndividual.observaciones && resultadoIndividual.observaciones.length > 0">
                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 sm:p-5 text-xs text-amber-900">
                            <h5 class="font-bold mb-1.5 flex items-center gap-1.5 text-xs text-amber-900">
                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008Z" />
                                </svg>
                                Observaciones oficiales emitidas por la API de SUNAT:
                            </h5>
                            <ul class="list-disc list-inside space-y-1 text-xs text-amber-950 font-mono mt-2">
                                <template x-for="(obs, idx) in resultadoIndividual.observaciones" :key="idx">
                                    <li x-text="obs"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- ═══════════════════════════ PESTAÑA 2: VALIDACIÓN MASIVA (EXCEL/CSV) ═══════════════════════════ --}}
    <div x-show="tab === 'masivo'" x-cloak class="space-y-6">

        {{-- Panel de Carga de Archivo con Zona de Arrastre Inteligente --}}
        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-600 border border-teal-200/60 shadow-2xs">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <span>Carga Masiva de Comprobantes (Excel / New Contasis / SIRE / CSV)</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1 font-medium">
                        Importe el Registro de Compras (~120 facturas) o Registro de Ventas (~780 comprobantes) exportado de su software contable. Validación concurrente en memoria sin tocar base de datos.
                    </p>
                </div>

                {{-- Enlaces de Descarga de Plantilla Oficial (XLSX y CSV) --}}
                <div class="shrink-0 flex items-center gap-2">
                    <a :href="rutas.plantilla + '?formato=xlsx'"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50/70 hover:bg-emerald-100 px-3.5 py-2 text-xs font-bold text-emerald-800 shadow-2xs transition cursor-pointer">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Plantilla Excel (.xlsx)</span>
                    </a>
                    <a :href="rutas.plantilla + '?formato=csv'"
                       class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 shadow-2xs transition cursor-pointer">
                        <span>CSV</span>
                    </a>
                </div>
            </div>

            {{-- Banner Clave Contable: Moneda Original --}}
            <div class="mb-5 rounded-2xl border border-sky-200 bg-sky-50/80 p-4 flex items-start gap-3.5 shadow-2xs">
                <div class="rounded-xl bg-sky-600 p-2 text-white shrink-0 mt-0.5 shadow-xs">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="text-xs text-sky-950 space-y-1">
                    <p class="font-bold text-sky-900 text-xs sm:text-sm">Regla Contable Clave: Validar en MONEDA ORIGINAL (Soles o Dólares)</p>
                    <p class="text-sky-800 leading-relaxed font-medium">
                        Al exportar el Registro de Compras o Ventas desde <strong>New Contasis</strong> (o su sistema contable), seleccione la casilla <strong>"Moneda Original"</strong>. La API de SUNAT valida el importe total exactamente en la divisa en que fue emitido el comprobante (ej: si una factura fue emitida por <strong>US$ 3,366.36</strong>, debe enviarse ese valor original y no su conversión a Soles).
                    </p>
                </div>
            </div>

            {{-- Selector de Tipo de Registro (Compras vs Ventas) --}}
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-2xl bg-slate-50/80 border border-slate-200 shadow-2xs">
                <div class="flex items-center gap-2.5 px-1">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-teal-100 text-teal-700">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-800">Tipo de Registro a Auditar</span>
                        <p class="text-[11px] text-slate-500">Define si los comprobantes corresponden a compras de terceros o ventas propias</p>
                    </div>
                </div>

                <div class="inline-flex p-1 rounded-xl bg-slate-200/70 border border-slate-300/50 text-xs shrink-0">
                    <button type="button"
                            @click="tipoRegistro = 'compras'"
                            class="relative px-4 py-2 rounded-lg font-bold transition-all duration-200 flex items-center gap-2 cursor-pointer outline-none focus:outline-none"
                            :class="tipoRegistro === 'compras' ? 'bg-teal-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span>Registro de Compras (Proveedores)</span>
                    </button>
                    <button type="button"
                            @click="tipoRegistro = 'ventas'"
                            class="relative px-4 py-2 rounded-lg font-bold transition-all duration-200 flex items-center gap-2 cursor-pointer outline-none focus:outline-none"
                            :class="tipoRegistro === 'ventas' ? 'bg-teal-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0 0a60.2 60.2 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V15M3.75 4.5H20.25a1.5 1.5 0 0 1 1.5 1.5v10.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V6a1.5 1.5 0 0 1 1.5-1.5Z" />
                        </svg>
                        <span>Registro de Ventas (Emisión Propia)</span>
                    </button>
                </div>
            </div>

            {{-- Notificación de Auto-Detección Inteligente del Archivo --}}
            <template x-if="notificacionTipo.visible">
                <div class="mb-5 rounded-2xl border border-teal-200 bg-teal-50/90 p-3.5 flex items-center justify-between text-xs text-teal-950 shadow-2xs transition-all">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-teal-600 text-white shrink-0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <span class="font-bold text-teal-900" x-text="notificacionTipo.mensaje"></span>
                    </div>
                    <button type="button" @click="notificacionTipo.visible = false" class="text-teal-700 hover:text-teal-950 font-bold p-1 cursor-pointer">✕</button>
                </div>
            </template>

            {{-- ZONA DE SUBIDA DRAG & DROP TOTALMENTE CLICABLE (CAJA ENTERA + ICONO + TEXTO) --}}
            <div class="relative group rounded-3xl border-2 border-dashed border-slate-300 bg-slate-50/60 p-8 sm:p-10 text-center hover:bg-teal-50/30 hover:border-teal-500 transition-all duration-200 cursor-pointer shadow-2xs"
                 :class="{ 'border-teal-500 bg-teal-50/50 ring-4 ring-teal-500/10': arrastrando }"
                 @dragover.prevent="arrastrando = true"
                 @dragleave.prevent="arrastrando = false"
                 @drop.prevent="arrastrando = false; manejarArchivoDrop($event)"
                 @click="$refs.fileInput.click()">

                {{-- Input Real Oculto --}}
                <input type="file"
                       x-ref="fileInput"
                       id="archivo_masivo"
                       accept=".xlsx,.csv,.txt"
                       class="sr-only"
                       @change="manejarArchivoInput($event)">

                <div class="space-y-3.5">
                    {{-- Icono Clicable e Interactivo con Efecto Hover --}}
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-100 text-teal-700 shadow-xs group-hover:scale-110 group-hover:bg-teal-600 group-hover:text-white transition-all duration-200">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>

                    {{-- Texto con Instrucción Clara y Clicable en Toda la Caja --}}
                    <div class="text-sm text-slate-700 space-y-1">
                        <p class="font-bold text-teal-700 group-hover:text-teal-800 text-base">
                            Haga clic en cualquier parte de este recuadro para seleccionar su archivo
                        </p>
                        <p class="text-slate-500 text-xs">
                            o arrastre y suelte su reporte de compras/ventas directamente aquí (.xlsx, .csv)
                        </p>
                    </div>

                    {{-- Badges de Compatibilidad con Softwares Contables Peruanos --}}
                    <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
                        <span class="inline-flex items-center gap-1 rounded-xl bg-teal-50 px-3 py-1 text-xs font-bold text-teal-800 border border-teal-200/80 shadow-2xs">
                            ✓ New Contasis (Compras y Ventas)
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-xl bg-sky-50 px-3 py-1 text-xs font-bold text-sky-800 border border-sky-200/80 shadow-2xs">
                            ✓ SIRE SUNAT (RCE / RVIE)
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 border border-slate-200 shadow-2xs">
                            ✓ Excel (.xlsx) / CSV
                        </span>
                    </div>

                    <p class="text-[11px] text-slate-500 max-w-xl mx-auto leading-relaxed">
                        Reconoce automáticamente series unificadas (ej: <code class="font-mono font-bold text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200">00F007-0000000472035</code>), convierte montos de Notas de Crédito e ignora filas de totales o subtotales.
                    </p>
                </div>
            </div>

            {{-- Estado de Archivo Seleccionado / Progreso de Lectura --}}
            <template x-if="cargandoArchivo">
                <div class="mt-4 flex items-center justify-center gap-3 p-4 text-xs font-bold text-teal-900 bg-teal-50/90 rounded-2xl border border-teal-200 shadow-2xs">
                    <svg class="h-5 w-5 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span>Analizando estructura contable y reconociendo columnas del archivo...</span>
                </div>
            </template>
        </div>

        {{-- PANEL DE RESUMEN Y ACCIONES DE VALIDACIÓN MASIVA --}}
        <template x-if="masivoFilas.length > 0">
            <div class="space-y-6">

                {{-- Barra Superior de Control y KPIs Ejecutivos --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
                    {{-- Total Filas --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total CPEs</span>
                        <p class="text-2xl font-black text-slate-900 mt-1" x-text="masivoFilas.length"></p>
                    </div>

                    {{-- Procesados --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <span class="text-[10px] font-black uppercase tracking-wider text-teal-600">Auditados</span>
                        <p class="text-2xl font-black text-teal-700 mt-1" x-text="kpis.procesados + ' / ' + masivoFilas.length"></p>
                    </div>

                    {{-- Válidos / Aceptados --}}
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-xs">
                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">✓ Válidos (Aptos)</span>
                        <p class="text-2xl font-black text-emerald-800 mt-1" x-text="kpis.validos"></p>
                    </div>

                    {{-- Observados --}}
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-xs">
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-700">⚠ Observados</span>
                        <p class="text-2xl font-black text-amber-800 mt-1" x-text="kpis.observados"></p>
                    </div>

                    {{-- Rechazados / Anulados / No Existen --}}
                    <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4 shadow-xs">
                        <span class="text-[10px] font-black uppercase tracking-wider text-rose-700">✕ Rechazados</span>
                        <p class="text-2xl font-black text-rose-800 mt-1" x-text="kpis.rechazados"></p>
                    </div>

                    {{-- Pendientes --}}
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 shadow-xs">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pendientes</span>
                        <p class="text-2xl font-black text-slate-700 mt-1" x-text="kpis.pendientes"></p>
                    </div>
                </div>

                {{-- Barra de Progreso Dinámica y Acciones Masivas --}}
                <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-700" x-text="procesandoMasivo ? 'Validando con API SUNAT... (' + kpis.procesados + ' de ' + masivoFilas.length + ')' : (kpis.pendientes === 0 ? '✓ Auditoría masiva completada al 100%' : 'Listo para iniciar validación masiva con SUNAT')"></span>
                        <span class="font-mono font-black text-teal-700 text-sm" x-text="porcentajeProgreso + '%'"></span>
                    </div>

                    <div class="w-full bg-slate-100 rounded-full h-3.5 overflow-hidden p-0.5 border border-slate-200/80">
                        <div class="bg-gradient-to-r from-teal-500 to-teal-700 h-2.5 rounded-full transition-all duration-300 shadow-2xs"
                             :style="'width: ' + porcentajeProgreso + '%'"></div>
                    </div>

                    {{-- Botonera de Acción Masiva --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <div class="flex items-center gap-2.5">
                            <template x-if="!procesandoMasivo && kpis.pendientes > 0">
                                <button type="button"
                                        @click="iniciarValidacionMasiva()"
                                        :disabled="!clienteActual?.tiene_credenciales"
                                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-teal-700 px-5 py-2.5 text-xs font-bold text-white shadow-xs hover:from-teal-700 hover:to-teal-800 transition cursor-pointer">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    <span x-text="kpis.procesados > 0 ? 'Reanudar Validación' : 'Iniciar Validación Masiva con SUNAT'"></span>
                                </button>
                            </template>

                            <template x-if="procesandoMasivo">
                                <button type="button"
                                        @click="detenerValidacionMasiva()"
                                        class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-amber-700 transition cursor-pointer">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                                    </svg>
                                    <span>Pausar Proceso</span>
                                </button>
                            </template>

                            <button type="button"
                                    @click="limpiarTodo()"
                                    :disabled="procesandoMasivo"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                <span>Limpiar Lista</span>
                            </button>
                        </div>

                        {{-- Botón Exportar Reporte a Excel / CSV --}}
                        <button type="button"
                                @click="exportarReporteAuditado()"
                                :disabled="kpis.procesados === 0 || exportando"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition cursor-pointer">
                            <template x-if="!exportando">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                            </template>
                            <template x-if="exportando">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="exportando ? 'Generando Reporte...' : 'Descargar Reporte Auditado en Excel (CSV)'"></span>
                        </button>
                    </div>
                </div>

                {{-- Tabla de Resultados con Filtro y Búsqueda --}}
                <div class="rounded-3xl border border-slate-200/90 bg-white overflow-hidden shadow-xs">
                    {{-- Barra de Búsqueda y Píldoras de Filtro --}}
                    <div class="p-4 sm:p-5 border-b border-slate-200/90 bg-slate-50/70 flex flex-col md:flex-row md:items-center md:justify-between gap-3.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[11px] font-black text-slate-500 uppercase tracking-wider">Filtrar por:</span>
                            <button type="button"
                                    @click="cambiarFiltro('todos')"
                                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition-all cursor-pointer outline-none focus:outline-none"
                                    :class="filtroEstado === 'todos' ? 'bg-slate-900 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100'">
                                Todos (<span x-text="masivoFilas.length"></span>)
                            </button>
                            <button type="button"
                                    @click="cambiarFiltro('validos')"
                                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition-all cursor-pointer outline-none focus:outline-none"
                                    :class="filtroEstado === 'validos' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100'">
                                ✓ Aptos (<span x-text="kpis.validos"></span>)
                            </button>
                            <button type="button"
                                    @click="cambiarFiltro('observados')"
                                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition-all cursor-pointer outline-none focus:outline-none"
                                    :class="filtroEstado === 'observados' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 border border-amber-200 text-amber-800 hover:bg-amber-100'">
                                ⚠ Observados (<span x-text="kpis.observados"></span>)
                            </button>
                            <button type="button"
                                    @click="cambiarFiltro('rechazados')"
                                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition-all cursor-pointer outline-none focus:outline-none"
                                    :class="filtroEstado === 'rechazados' ? 'bg-rose-600 text-white shadow-xs' : 'bg-rose-50 border border-rose-200 text-rose-800 hover:bg-rose-100'">
                                ✕ Rechazados (<span x-text="kpis.rechazados"></span>)
                            </button>
                        </div>

                        {{-- Input de Búsqueda Rápida --}}
                        <div class="relative w-full md:w-72">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                            </div>
                            <input type="text"
                                   x-model="busquedaTabla"
                                   @input="paginaActual = 1"
                                   placeholder="Buscar por RUC, serie, correlativo..."
                                   class="block w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs font-mono text-slate-800 placeholder-slate-400 shadow-2xs outline-none hover:border-slate-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 transition-all">
                        </div>
                    </div>

                    {{-- Data Table con Arquitectura Profesional: Columna N° Corregida --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-600">
                                <tr>
                                    {{-- Etiqueta oficial "N°" --}}
                                    <th class="py-3.5 px-3.5 w-14 text-center font-black">N°</th>
                                    <th class="py-3.5 px-4 w-64" x-text="tipoRegistro === 'ventas' ? 'Cliente / Adquirente' : 'Emisor / Proveedor'"></th>
                                    <th class="py-3.5 px-4 w-44">Comprobante</th>
                                    <th class="py-3.5 px-4 w-32 text-right">Total Validado</th>
                                    <th class="py-3.5 px-4 w-40 text-center">Estado SUNAT</th>
                                    <th class="py-3.5 px-4">Diagnóstico Tributario & Dictamen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <template x-for="(fila, idx) in filasPaginadas" :key="fila.item">
                                    <tr class="hover:bg-slate-50/90 transition-all duration-150"
                                        :class="{
                                            'bg-emerald-50/20': fila.resultado?.es_valido_credito_fiscal,
                                            'bg-amber-50/20': fila.resultado?.severidad === 'warning',
                                            'bg-rose-50/20': fila.resultado?.severidad === 'danger'
                                        }">
                                        {{-- 1. Index (N°) --}}
                                        <td class="py-3.5 px-3.5 text-center font-mono text-slate-500 font-bold" x-text="fila.item"></td>

                                        {{-- 2. Emisor / Proveedor o Cliente / Adquirente --}}
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-900 text-xs truncate max-w-[16rem]" :title="fila.razonSocial || '—'" x-text="fila.razonSocial || '—'"></div>
                                            <div class="font-mono text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                                <span class="text-slate-400 font-sans text-[10px] font-black uppercase" x-text="tipoRegistro === 'ventas' ? 'RECEPTOR' : 'RUC'"></span>
                                                <span class="font-bold text-slate-800 select-all" x-text="fila.numRuc"></span>
                                            </div>
                                        </td>

                                        {{-- 3. Comprobante (Serie-Número + Tipo + Fecha) --}}
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <div class="font-mono font-black text-slate-900 text-xs" x-text="fila.numeroSerie + '-' + fila.numero"></div>
                                            <div class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                                <span class="rounded bg-slate-100 border border-slate-200/80 px-1.5 py-0.2 font-mono text-[10px] font-black text-slate-700" x-text="'CPE ' + fila.codComp"></span>
                                                <span class="font-mono text-slate-500 text-[11px] font-semibold" x-text="fila.fechaEmision"></span>
                                            </div>
                                        </td>

                                        {{-- 4. Total Validado (Monto + Moneda Orig.) --}}
                                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                            <div class="font-mono font-black text-slate-900 text-xs" x-text="'S/ ' + fila.monto"></div>
                                            <div class="text-[10px] text-slate-400 font-medium mt-0.5">Moneda Orig.</div>
                                        </td>

                                        {{-- 5. Estado SUNAT (Pill principal + Micro-pills) --}}
                                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                            <span x-show="!fila.resultado" class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500 border border-slate-200">Pendiente</span>
                                            <template x-if="fila.resultado">
                                                <div class="flex flex-col items-center gap-1">
                                                    {{-- Estado CPE --}}
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider shadow-2xs"
                                                          :class="{
                                                              'bg-emerald-100 text-emerald-800 border border-emerald-300': fila.resultado?.estado_cp?.color === 'success',
                                                              'bg-rose-100 text-rose-800 border border-rose-300': fila.resultado?.estado_cp?.color === 'danger',
                                                              'bg-sky-100 text-sky-800 border border-sky-300': fila.resultado?.estado_cp?.color === 'info',
                                                              'bg-slate-100 text-slate-700 border border-slate-300': !fila.resultado?.estado_cp?.color
                                                          }"
                                                          x-text="fila.resultado?.estado_cp?.label || 'DESCONOCIDO'">
                                                    </span>

                                                    {{-- Micro-pills: Estado RUC & Condición --}}
                                                    <div class="flex items-center justify-center gap-1 text-[10px] font-bold">
                                                        <span x-show="fila.resultado?.estado_ruc"
                                                              class="px-1.5 py-0.2 rounded font-semibold"
                                                              :class="fila.resultado?.estado_ruc?.es_activo ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                                              x-text="fila.resultado?.estado_ruc?.label"></span>
                                                        <span x-show="fila.resultado?.condicion_domicilio"
                                                              class="px-1.5 py-0.2 rounded font-semibold"
                                                              :class="fila.resultado?.condicion_domicilio?.es_habido ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'"
                                                              x-text="fila.resultado?.condicion_domicilio?.label"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </td>

                                        {{-- 6. Diagnóstico Tributario & Dictamen --}}
                                        <td class="py-3.5 px-4">
                                            <template x-if="fila.resultado">
                                                <div class="p-3 rounded-2xl border text-xs leading-relaxed transition-all shadow-2xs"
                                                     :class="{
                                                         'bg-emerald-50/80 border-emerald-200 text-emerald-950': fila.resultado?.severidad === 'success',
                                                         'bg-amber-50/80 border-amber-200 text-amber-950': fila.resultado?.severidad === 'warning',
                                                         'bg-rose-50/80 border-rose-200 text-rose-950': fila.resultado?.severidad === 'danger'
                                                     }">
                                                    <div class="flex items-start gap-2.5">
                                                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-white text-[10px] font-black"
                                                              :class="{
                                                                  'bg-emerald-600': fila.resultado?.severidad === 'success',
                                                                  'bg-amber-50/80 border-amber-200 text-amber-950': fila.resultado?.severidad === 'warning',
                                                                  'bg-rose-600': fila.resultado?.severidad === 'danger'
                                                              }">
                                                            <span x-show="fila.resultado?.severidad === 'success'">✓</span>
                                                            <span x-show="fila.resultado?.severidad === 'warning'">!</span>
                                                            <span x-show="fila.resultado?.severidad === 'danger'">✕</span>
                                                        </span>
                                                        <div class="space-y-1 min-w-0">
                                                            <p class="font-medium text-xs leading-snug" x-text="fila.resultado?.diagnostico"></p>
                                                            <template x-if="fila.resultado?.observaciones && fila.resultado.observaciones.length > 0">
                                                                <div class="text-[10px] text-amber-900 font-mono mt-1 pt-1 border-t border-amber-200/60 space-y-0.5">
                                                                    <template x-for="(obs, oIdx) in fila.resultado.observaciones" :key="oIdx">
                                                                        <div x-text="'• ' + obs"></div>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <span x-show="!fila.resultado" class="text-slate-400 italic text-xs font-medium">En espera de validación</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Barra de Paginación Inteligente y Rendimiento --}}
                    <div class="px-5 py-4 border-t border-slate-200/90 bg-slate-50/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-600">
                        <div class="flex items-center gap-2">
                            <span>Mostrando</span>
                            <span class="font-bold text-slate-900" x-text="filasFiltradas.length > 0 ? ((paginaActual - 1) * (porPagina || filasFiltradas.length) + 1) : 0"></span>
                            <span>a</span>
                            <span class="font-bold text-slate-900" x-text="porPagina === 0 ? filasFiltradas.length : Math.min(paginaActual * porPagina, filasFiltradas.length)"></span>
                            <span>de</span>
                            <span class="font-bold text-slate-900" x-text="filasFiltradas.length"></span>
                            <span>comprobantes</span>
                            <span x-show="filasFiltradas.length < masivoFilas.length" class="text-slate-400">
                                (filtrados de <span x-text="masivoFilas.length"></span> en total)
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex items-center gap-1.5">
                                <span class="text-slate-500 font-medium">Filas por pág:</span>
                                <select x-model.number="porPagina" @change="paginaActual = 1" class="rounded-xl border border-slate-200 bg-white py-1.5 pl-2.5 pr-7 text-xs font-semibold text-slate-700 outline-none hover:border-slate-300 focus:border-teal-600 focus:ring-4 focus:ring-teal-600/10 transition-all cursor-pointer">
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                    <option :value="0">Todas</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-1" x-show="totalPaginas > 1">
                                <button type="button" @click="irAPagina(1)" :disabled="paginaActual <= 1" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-slate-700 transition cursor-pointer outline-none focus:outline-none">«</button>
                                <button type="button" @click="irAPagina(paginaActual - 1)" :disabled="paginaActual <= 1" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-slate-700 transition cursor-pointer outline-none focus:outline-none">Ant.</button>
                                <span class="px-2 font-semibold text-slate-700">
                                    Pág. <span x-text="paginaActual"></span> / <span x-text="totalPaginas"></span>
                                </span>
                                <button type="button" @click="irAPagina(paginaActual + 1)" :disabled="paginaActual >= totalPaginas" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-slate-700 transition cursor-pointer outline-none focus:outline-none">Sig.</button>
                                <button type="button" @click="irAPagina(totalPaginas)" :disabled="paginaActual >= totalPaginas" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-slate-700 transition cursor-pointer outline-none focus:outline-none">»</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- ═══════════════════════════ SCRIPT ALPINE.JS ═══════════════════════════ --}}
<script>
    function moduloValidezCpe(config) {
        // Normalización garantizada de tipos de CPE en lista ordenada
        let listaTipos = [];
        if (Array.isArray(config.tiposCpe)) {
            listaTipos = config.tiposCpe;
        } else if (config.tiposCpe && typeof config.tiposCpe === 'object') {
            listaTipos = Object.entries(config.tiposCpe).map(([k, v]) => ({
                codigo: k,
                descripcion: typeof v === 'string' ? v : (v.descripcion || k),
                abreviatura: typeof v === 'object' && v.abreviatura ? v.abreviatura : k
            }));
        }

        // Asegurar que 01 - Factura esté primero si por alguna razón no lo estuviera
        listaTipos.sort((a, b) => {
            const ordenPref = ['01', '03', '07', '08', '09', '31', '20', '40', 'R1', 'R7'];
            const idxA = ordenPref.indexOf(a.codigo);
            const idxB = ordenPref.indexOf(b.codigo);
            if (idxA !== -1 && idxB !== -1) return idxA - idxB;
            if (idxA !== -1) return -1;
            if (idxB !== -1) return 1;
            return a.codigo.localeCompare(b.codigo);
        });

        return {
            clientes: config.clientes || [],
            clienteId: config.clienteInicialId,
            tiposCpe: listaTipos,
            rutas: config.rutas,
            tab: 'individual',
            arrastrando: false,
            cargandoArchivo: false,
            procesandoMasivo: false,
            abortController: null,
            tipoRegistro: 'compras',
            notificacionTipo: {
                visible: false,
                mensaje: ''
            },
            exportando: false,

            // Control UI Dropdowns Flotantes y Filtro Reactivo Ultrarrápido
            openEmpresa: false,
            busquedaEmpresa: '',
            openTipoCpe: false,

            // Consulta individual
            individual: {
                numRuc: '',
                codComp: '01',
                numeroSerie: '',
                numero: '',
                fechaEmision: new Date().toISOString().substring(0, 10),
                monto: ''
            },
            consultandoIndividual: false,
            resultadoIndividual: null,

            // Masivo
            masivoFilas: [],
            filtroEstado: 'todos',
            busquedaTabla: '',
            paginaActual: 1,
            porPagina: 50,

            cambiarFiltro(estado) {
                this.filtroEstado = estado;
                this.paginaActual = 1;
            },

            irAPagina(p) {
                if (p < 1) p = 1;
                if (p > this.totalPaginas) p = this.totalPaginas;
                this.paginaActual = p;
            },

            get totalPaginas() {
                if (this.porPagina === 0) return 1;
                return Math.max(1, Math.ceil(this.filasFiltradas.length / this.porPagina));
            },

            get filasPaginadas() {
                if (this.porPagina === 0) return this.filasFiltradas;
                const inicio = (this.paginaActual - 1) * this.porPagina;
                return this.filasFiltradas.slice(inicio, inicio + this.porPagina);
            },

            get clienteActual() {
                return this.clientes.find(c => c.id == this.clienteId);
            },

            get clientesFiltrados() {
                const q = (this.busquedaEmpresa || '').trim().toLowerCase();
                if (!q) return this.clientes;
                return this.clientes.filter(c => 
                    (c.ruc && c.ruc.toLowerCase().includes(q)) || 
                    (c.razon_social && c.razon_social.toLowerCase().includes(q))
                );
            },

            get kpis() {
                let procesados = 0;
                let validos = 0;
                let observados = 0;
                let rechazados = 0;
                let pendientes = 0;

                this.masivoFilas.forEach(f => {
                    if (f.resultado) {
                        procesados++;
                        if (f.resultado.es_valido_credito_fiscal) {
                            validos++;
                        } else if (f.resultado.severidad === 'warning') {
                            observados++;
                        } else {
                            rechazados++;
                        }
                    } else {
                        pendientes++;
                    }
                });

                return { procesados, validos, observados, rechazados, pendientes };
            },

            get porcentajeProgreso() {
                if (this.masivoFilas.length === 0) return 0;
                return Math.round((this.kpis.procesados / this.masivoFilas.length) * 100);
            },

            get filasFiltradas() {
                return this.masivoFilas.filter(f => {
                    // Filtro de estado
                    if (this.filtroEstado === 'validos') {
                        if (!f.resultado?.es_valido_credito_fiscal) return false;
                    } else if (this.filtroEstado === 'observados') {
                        if (f.resultado?.severidad !== 'warning') return false;
                    } else if (this.filtroEstado === 'rechazados') {
                        if (f.resultado?.severidad !== 'danger') return false;
                    }

                    // Filtro de búsqueda
                    if (this.busquedaTabla.trim()) {
                        const q = this.busquedaTabla.toLowerCase();
                        const rucMatch = (f.numRuc || '').toLowerCase().includes(q);
                        const cpeMatch = (f.numeroSerie + '-' + f.numero).toLowerCase().includes(q);
                        const razonMatch = (f.razonSocial || '').toLowerCase().includes(q);
                        if (!rucMatch && !cpeMatch && !razonMatch) return false;
                    }

                    return true;
                });
            },

            cambiarCliente() {
                this.resultadoIndividual = null;
            },

            seleccionarEmpresa(id) {
                this.clienteId = id;
                this.resultadoIndividual = null;
                this.openEmpresa = false;
                this.busquedaEmpresa = '';
            },

            seleccionarTipoCpe(codigo) {
                this.individual.codComp = codigo;
                this.openTipoCpe = false;
            },

            async ejecutarConsultaIndividual() {
                if (this.consultandoIndividual) return;
                this.consultandoIndividual = true;
                this.resultadoIndividual = null;

                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    const resp = await fetch(this.rutas.validarIndividual, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            cliente_id: this.clienteId,
                            numRuc: this.individual.numRuc.trim(),
                            codComp: this.individual.codComp,
                            numeroSerie: this.individual.numeroSerie.trim().toUpperCase(),
                            numero: this.individual.numero.trim(),
                            fechaEmision: this.individual.fechaEmision,
                            monto: this.individual.monto || null
                        })
                    });

                    const data = await resp.json();

                    if (!resp.ok) {
                        throw new Error(data.message || 'Error al validar comprobante.');
                    }

                    this.resultadoIndividual = data;
                } catch (err) {
                    alert('Error: ' + err.message);
                } finally {
                    this.consultandoIndividual = false;
                }
            },

            manejarArchivoDrop(event) {
                const files = event.dataTransfer.files;
                if (files && files.length > 0) {
                    this.cargarArchivoServidor(files[0]);
                }
            },

            manejarArchivoInput(event) {
                const files = event.target.files;
                if (files && files.length > 0) {
                    this.cargarArchivoServidor(files[0]);
                }
            },

            async cargarArchivoServidor(archivo) {
                this.cargandoArchivo = true;
                const formData = new FormData();
                formData.append('archivo', archivo);
                formData.append('cliente_id', this.clienteId);
                formData.append('es_registro_ventas', this.tipoRegistro === 'ventas' ? '1' : '0');

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                try {
                    const resp = await fetch(this.rutas.cargarArchivo, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await resp.json();

                    if (!resp.ok) {
                        throw new Error(data.message || 'Error al procesar archivo.');
                    }

                    // Sincronización inteligente del tipo de registro detectado
                    if (data.tipo_registro) {
                        const anterior = this.tipoRegistro;
                        this.tipoRegistro = data.tipo_registro;
                        if (anterior !== data.tipo_registro) {
                            this.notificacionTipo = {
                                visible: true,
                                mensaje: data.tipo_registro === 'compras'
                                    ? '✓ Archivo reconocido automáticamente como Registro de Compras (New Contasis). Modo sincronizado a Compras.'
                                    : '✓ Archivo reconocido automáticamente como Registro de Ventas (New Contasis). Modo sincronizado a Ventas.'
                            };
                            setTimeout(() => { this.notificacionTipo.visible = false; }, 6000);
                        }
                    }

                    this.masivoFilas = data.filas.map(f => ({
                        ...f,
                        resultado: null
                    }));
                    this.paginaActual = 1;

                    if (this.masivoFilas.length === 0) {
                        alert('No se encontraron filas válidas en el archivo seleccionado.');
                    }
                } catch (err) {
                    alert('Error: ' + err.message);
                } finally {
                    this.cargandoArchivo = false;
                }
            },

            async iniciarValidacionMasiva() {
                if (this.procesandoMasivo) return;
                this.procesandoMasivo = true;
                this.abortController = new AbortController();

                const tamanoLote = 8;
                const pendientes = this.masivoFilas.filter(f => !f.resultado);

                for (let i = 0; i < pendientes.length; i += tamanoLote) {
                    if (!this.procesandoMasivo) break;

                    const chunk = pendientes.slice(i, i + tamanoLote);

                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        const resp = await fetch(this.rutas.validarLote, {
                            method: 'POST',
                            signal: this.abortController.signal,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                comprobantes: chunk.map(c => ({
                                    item: c.item,
                                    numRuc: c.numRuc,
                                    codComp: c.codComp,
                                    numeroSerie: c.numeroSerie,
                                    numero: c.numero,
                                    fechaEmision: c.fechaEmision,
                                    monto: c.monto,
                                    razonSocial: c.razonSocial
                                }))
                            })
                        });

                        const data = await resp.json();

                        if (resp.ok && data.resultados) {
                            data.resultados.forEach(res => {
                                const fila = this.masivoFilas.find(f => f.item === res.item);
                                if (fila) {
                                    fila.resultado = res;
                                }
                            });
                        }
                    } catch (err) {
                        if (err.name === 'AbortError') break;
                        console.error('Error en lote de validación:', err);
                    }
                }

                this.procesandoMasivo = false;
            },

            detenerValidacionMasiva() {
                this.procesandoMasivo = false;
                if (this.abortController) {
                    this.abortController.abort();
                }
            },

            limpiarTodo() {
                this.detenerValidacionMasiva();
                this.masivoFilas = [];
                this.paginaActual = 1;
                const inputEl = document.getElementById('archivo_masivo');
                if (inputEl) inputEl.value = '';
            },

            async exportarReporteAuditado() {
                if (this.exportando) return;
                this.exportando = true;

                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const resultados = this.masivoFilas
                        .filter(f => f.resultado !== null)
                        .map(f => f.resultado);

                    const resp = await fetch(this.rutas.exportarReporte, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            cliente_id: this.clienteId,
                            resultados: resultados
                        })
                    });

                    if (!resp.ok) {
                        throw new Error('Error al generar el reporte.');
                    }

                    const blob = await resp.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'Auditoria_CPE_' + (this.clienteActual?.ruc || 'SUNAT') + '_' + new Date().toISOString().substring(0, 10) + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                } catch (err) {
                    alert('Error al descargar reporte: ' + err.message);
                } finally {
                    this.exportando = false;
                }
            }
        };
    }
</script>
@endsection
