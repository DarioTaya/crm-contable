@extends('layouts.app')

@section('title', 'Control de Deudas Tributarias')
@section('header', 'Control de Deudas')

@push('head')
<style>
    /* Estilos de alta precisión para impresión y exportación a PDF */
    @media print {
        .app-sidebar,
        header,
        nav,
        .no-print,
        button,
        [data-menu-flotante],
        .action-cell,
        .filter-panel {
            display: none !important;
        }

        body, .app-main, main, .container {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .print-only {
            display: block !important;
        }

        .print-table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-size: 9.5pt !important;
        }

        .print-table th,
        .print-table td {
            border: 1px solid #cbd5e1 !important;
            padding: 5px 7px !important;
            color: #0f172a !important;
        }

        .print-table th {
            background-color: #f1f5f9 !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 8pt !important;
            letter-spacing: 0.05em !important;
        }

        .print-table tr {
            page-break-inside: avoid !important;
        }

        .print-badge {
            border: 1px solid #94a3b8 !important;
            background: #f8fafc !important;
            color: #0f172a !important;
            padding: 1px 4px !important;
            border-radius: 3px !important;
            font-size: 8pt !important;
            font-weight: 600 !important;
        }

        @page {
            margin: 1cm 0.8cm;
            size: landscape;
        }
    }
</style>
@endpush

@section('content')
<div class="container mx-auto"
     x-data="controlDeudas(
         {{ Js::from($deudasData) }},
         {{ Js::from($clientesConDeuda) }},
         {{ $totalDeudaHistorica }},
         {{ $totalRecaudado }},
         {{ $totalSaldoPorCobrar }}
     )">

    {{-- ═══════════════════════════════ MEMBRETE OFICIAL PARA IMPRESIÓN (PDF) ═══════════════════════════════ --}}
    <div class="hidden print-only mb-6 border-b-2 border-gray-900 pb-4">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-xl font-black uppercase tracking-tight text-gray-900">
                    {{ auth()->user()?->estudio?->nombre ?? 'ESTUDIO CONTABLE' }}
                </h1>
                @if(auth()->user()?->estudio?->ruc)
                    <p class="text-xs font-mono font-bold text-gray-700">RUC: {{ auth()->user()->estudio->ruc }}</p>
                @endif
                @if(auth()->user()?->estudio?->direccion)
                    <p class="text-[11px] text-gray-600">{{ auth()->user()->estudio->direccion }}</p>
                @endif
                @if(auth()->user()?->estudio?->telefono)
                    <p class="text-[11px] text-gray-600">Tel: {{ auth()->user()->estudio->telefono }}</p>
                @endif
            </div>
            <div class="text-right">
                <span class="inline-block rounded border border-gray-400 bg-gray-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-800">
                    Documento de Auditoría y Cobranza
                </span>
                <p class="text-xs text-gray-700 mt-1.5 font-medium">
                    Fecha de Emisión: <span class="font-bold font-mono">{{ now()->format('d/m/Y H:i') }}</span>
                </p>
                <p class="text-[11px] text-gray-500">
                    Generado por: {{ auth()->user()?->name ?? 'Sistema CRM Contable' }}
                </p>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-gray-200 text-center">
            <h2 class="text-lg font-black uppercase tracking-wide text-gray-900">
                Reporte Consolidado de Deudas Tributarias (SUNAT)
            </h2>
            <p class="text-xs text-gray-600 mt-0.5">
                Seguimiento de Fraccionamientos, Cuotas de IGV Justo y Obligaciones Mensuales Pendientes
            </p>
        </div>

        {{-- Resumen ejecutivo en impresión --}}
        <div class="mt-4 grid grid-cols-3 gap-3 border border-gray-300 rounded p-2.5 text-center bg-gray-50">
            <div>
                <p class="text-[9px] uppercase font-bold text-gray-500 tracking-wider">Deuda Histórica Registrada</p>
                <p class="text-sm font-black text-gray-900 font-mono mt-0.5" x-text="formatoSoles(totalDeudaFiltrada)">
                    S/ {{ number_format($totalDeudaHistorica, 2) }}
                </p>
            </div>
            <div>
                <p class="text-[9px] uppercase font-bold text-gray-600 tracking-wider">Total Recaudado / Amortizado</p>
                <p class="text-sm font-black text-gray-900 font-mono mt-0.5" x-text="formatoSoles(totalRecaudadoFiltrado)">
                    S/ {{ number_format($totalRecaudado, 2) }}
                </p>
            </div>
            <div>
                <p class="text-[9px] uppercase font-bold text-red-700 tracking-wider">Saldo Pendiente por Cobrar</p>
                <p class="text-sm font-black text-red-700 font-mono mt-0.5" x-text="formatoSoles(totalSaldoFiltrado)">
                    S/ {{ number_format($totalSaldoPorCobrar, 2) }}
                </p>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ ENCABEZADO DE PANTALLA (Stripe / Mercury Style) ═══════════════════════════════ --}}
    <div class="no-print mb-6 relative z-40 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Control de Deudas</h2>
                <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-xs font-black text-rose-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                    SUNAT
                </span>
                <span class="rounded-full bg-gray-100 border border-gray-200 px-2.5 py-0.5 text-[11px] font-bold text-gray-600"
                      x-text="deudasVisiblesCount + ' ' + (deudasVisiblesCount === 1 ? 'deuda pendiente' : 'deudas pendientes')">
                    {{ $deudas->count() }} {{ $deudas->count() === 1 ? 'deuda pendiente' : 'deudas pendientes' }}
                </span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                Auditoría financiera de cuotas tributarias con amortizaciones parciales y seguimiento de saldos en tiempo real.
            </p>
        </div>

        {{-- Botonera Superior --}}
        <div class="flex items-center flex-wrap gap-2">
            {{-- Botón: Filtrar por Cliente (Multi-Select Popover) --}}
            <div class="relative z-50" @click.away="filterOpen = false">
                <button
                    @click="filterOpen = !filterOpen"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 hover:border-gray-400 active:scale-95"
                    :class="{'ring-2 ring-brand-500/20 border-brand-500 text-brand-700': isFilteringClients}">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.539.092.917.56.917 1.107v2.304a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.785c0-.547.378-1.015.917-1.107a48.51 48.51 0 0 1 8.083-.678Z" />
                    </svg>
                    <span>Filtrar Clientes</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-black"
                          :class="isFilteringClients ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-600'"
                          x-text="filterLabel">
                        Todos ({{ count($clientesConDeuda) }})
                    </span>
                    <svg class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200" :class="{'rotate-180': filterOpen}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                {{-- Panel Desplegable del Filtro Multi-Cliente --}}
                <div x-show="filterOpen" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="filter-panel absolute right-0 mt-2 z-50 w-80 rounded-2xl border border-gray-200 bg-white p-3.5 shadow-xl ring-1 ring-black/5">
                    <div class="flex items-center justify-between pb-2.5 border-b border-gray-100">
                        <p class="text-xs font-bold text-gray-900">Seleccionar Clientes</p>
                        <div class="flex items-center gap-2">
                            <button @click="seleccionarTodos()" type="button" class="text-[11px] font-bold text-brand-600 hover:text-brand-700">Todos</button>
                            <span class="text-gray-300">|</span>
                            <button @click="deseleccionarTodos()" type="button" class="text-[11px] font-bold text-gray-500 hover:text-gray-700">Limpiar</button>
                        </div>
                    </div>

                    {{-- Buscador rápido de clientes dentro del desplegable --}}
                    <div class="mt-2.5 relative">
                        <input type="text"
                               x-model="searchClientFilter"
                               placeholder="Buscar por razón social o RUC..."
                               class="w-full rounded-lg border border-gray-200 bg-gray-50/70 px-2.5 py-1.5 pl-8 text-xs text-gray-800 placeholder-gray-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <svg class="absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>

                    {{-- Lista de clientes con checkbox y contador de deudas --}}
                    <div class="mt-2 max-h-56 overflow-y-auto space-y-1 divide-y divide-gray-50 pr-1">
                        <template x-for="cli in clientesFiltradosEnDropdown" :key="cli.id">
                            <label class="flex items-center justify-between gap-2 rounded-lg p-1.5 hover:bg-gray-50 cursor-pointer select-none transition">
                                <div class="flex items-center gap-2 min-w-0">
                                    <input type="checkbox"
                                           :value="String(cli.id)"
                                           x-model="selectedClientes"
                                           class="h-3.5 w-3.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span class="font-mono text-[10px] font-bold text-gray-500 shrink-0" x-text="cli.numero_formateado"></span>
                                    <span class="truncate text-xs font-semibold text-gray-800" x-text="cli.razon_social"></span>
                                </div>
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-gray-600 shrink-0"
                                      x-text="cli.total_deudas"></span>
                            </label>
                        </template>
                        <div x-show="clientesFiltradosEnDropdown.length === 0" class="p-3 text-center text-xs text-gray-400">
                            No se encontraron clientes
                        </div>
                    </div>

                    <div class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-[11px] text-gray-500" x-text="selectedClientes.length + ' de ' + clientes.length + ' seleccionados'"></span>
                        <button @click="filterOpen = false"
                                type="button"
                                class="rounded-lg bg-gray-900 px-3 py-1 text-xs font-bold text-white hover:bg-black transition">
                            Listo
                        </button>
                    </div>
                </div>
            </div>

            {{-- Botón: Exportar a PDF --}}
            <button
                @click="imprimirReporte()"
                type="button"
                title="Exportar a PDF con membrete oficial"
                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 hover:border-gray-400 active:scale-95">
                <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span>Exportar a PDF</span>
            </button>

            {{-- Botón: Imprimir Reporte --}}
            <button
                @click="imprimirReporte()"
                type="button"
                title="Imprimir reporte en hoja carta u oficio"
                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 hover:border-gray-400 active:scale-95">
                <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                </svg>
                <span>Imprimir Reporte</span>
            </button>

            {{-- Volver a Impuestos --}}
            <a href="{{ route('control.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 active:scale-95">
                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                </svg>
                <span>Volver a Impuestos</span>
            </a>
        </div>
    </div>

    {{-- ═══════════════════════════════ TARJETAS DE RESUMEN FINANCIERO (Mercury / Stripe) ═══════════════════════════════ --}}
    <div class="mb-6 relative z-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- Total Deuda Histórica --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-200/90 bg-white p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] uppercase font-bold text-gray-500 tracking-wider">Deuda Histórica Total</p>
                    <p class="text-2xl sm:text-3xl font-black text-gray-900 mt-1 font-mono tracking-tight">
                        <span x-text="formatoSoles(totalDeudaFiltrada)">S/ {{ number_format($totalDeudaHistorica, 2) }}</span>
                    </p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 border border-blue-100 text-blue-600 shadow-xs">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-gray-500 border-t border-gray-100 pt-2.5">
                <span>Total pasivo registrado</span>
                <span class="font-bold text-gray-700" x-text="deudasVisiblesCount + ' obligaciones'"></span>
            </div>
        </div>

        {{-- Total Recaudado / Amortizado --}}
        <div class="relative overflow-hidden rounded-2xl border border-emerald-200/90 bg-white p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] uppercase font-bold text-emerald-600 tracking-wider">Total Recaudado / Pagado</p>
                    <p class="text-2xl sm:text-3xl font-black text-emerald-700 mt-1 font-mono tracking-tight">
                        <span x-text="formatoSoles(totalRecaudadoFiltrado)">S/ {{ number_format($totalRecaudado, 2) }}</span>
                    </p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 shadow-xs">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-emerald-600 border-t border-emerald-100/70 pt-2.5">
                <span>Porcentaje amortizado</span>
                <span class="font-black font-mono" x-text="porcentajeRecaudadoGeneral + '%'"></span>
            </div>
        </div>

        {{-- Saldo Pendiente Por Cobrar --}}
        <div class="relative overflow-hidden rounded-2xl border border-rose-200/90 bg-white p-5 shadow-xs transition hover:shadow-md ring-1 ring-rose-500/10">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] uppercase font-bold text-rose-600 tracking-wider">Saldo Pendiente Por Cobrar</p>
                    <p class="text-2xl sm:text-3xl font-black text-rose-700 mt-1 font-mono tracking-tight">
                        <span x-text="formatoSoles(totalSaldoFiltrado)">S/ {{ number_format($totalSaldoPorCobrar, 2) }}</span>
                    </p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 shadow-xs">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-rose-600 border-t border-rose-100/70 pt-2.5">
                <span>Riesgo en cobranza</span>
                <span class="font-black font-mono" x-text="(100 - porcentajeRecaudadoGeneral) + '% por amortizar'"></span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ BARRA DE CHIPS / TAGS MULTI-SELECT (Vercel / Stripe Style) ═══════════════════════════════ --}}
    <div x-show="isFilteringClients" x-cloak
         class="no-print mb-5 flex flex-wrap items-center gap-2 p-3 rounded-2xl bg-slate-50 border border-slate-200/90 text-xs shadow-2xs">
        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mr-1 flex items-center gap-1.5 shrink-0">
            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.539.092.917.56.917 1.107v2.304a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.785c0-.547.378-1.015.917-1.107a48.51 48.51 0 0 1 8.083-.678Z" />
            </svg>
            Filtrando (<span x-text="selectedClientes.length"></span>/<span x-text="clientes.length"></span>):
        </span>

        <template x-for="cId in selectedClientes" :key="cId">
            <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-lg bg-white border border-gray-300 text-gray-800 font-semibold shadow-2xs text-[11px] hover:border-gray-400 transition group">
                <span class="font-mono font-bold text-brand-600 text-[10px]" x-text="getClienteNumero(cId)"></span>
                <span class="truncate max-w-[170px]" x-text="getClienteNombre(cId)"></span>
                <button
                    type="button"
                    @click="removerClienteFiltro(cId)"
                    class="h-4 w-4 rounded-full flex items-center justify-center text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition"
                    title="Quitar este cliente del filtro">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </template>

        <span x-show="selectedClientes.length === 0" class="text-xs text-rose-600 italic">
            Ningún cliente seleccionado. No se mostrarán deudas.
        </span>

        <button
            type="button"
            @click="limpiarFiltroClientes()"
            class="text-[11px] font-bold text-brand-700 hover:text-brand-900 ml-2 hover:underline transition shrink-0">
            Restablecer todos
        </button>
    </div>

    {{-- ═══════════════════════════════ TABLA DE CONTROL DE DEUDAS (Enterprise Grade) ═══════════════════════════════ --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        {{-- Barra de Herramientas de la Tabla --}}
        <div class="no-print px-5 py-3.5 border-b border-gray-200 bg-gray-50/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="flex h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                <h3 class="text-sm font-black text-gray-800 tracking-tight">Obligaciones Tributarias y Pagos Parciales</h3>
            </div>

            {{-- Buscador en tiempo real dentro de la tabla --}}
            <div class="relative w-full sm:w-72">
                <input type="text"
                       x-model="searchTable"
                       placeholder="Buscar por cliente, RUC, periodo o tributo..."
                       class="w-full rounded-xl border border-gray-300 bg-white px-3 py-1.5 pl-8 text-xs text-gray-800 placeholder-gray-400 shadow-2xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <svg class="absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <button x-show="searchTable" @click="searchTable = ''" class="absolute right-2.5 top-2 text-gray-400 hover:text-gray-600">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        @if($deudas->isEmpty())
            <div class="p-12 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <p class="text-gray-800 text-base font-bold mt-3.5">¡No hay deudas pendientes!</p>
                <p class="text-gray-500 text-xs mt-1">Todas las obligaciones tributarias con SUNAT se encuentran debidamente canceladas.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left print-table">
                    <thead>
                        <tr class="bg-gray-50/90 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Cliente (ID / Razón Social)</th>
                            <th class="py-3 px-3 text-center">Periodo</th>
                            <th class="py-3 px-3 text-left">Tipo de Tributo (SUNAT)</th>
                            <th class="py-3 px-3 text-center">Vencimiento</th>
                            <th class="py-3 px-3 text-right">Deuda Original</th>
                            <th class="py-3 px-3 text-right">Total Abonado</th>
                            <th class="py-3 px-4 text-right">Saldo Pendiente</th>
                            <th class="no-print py-3 px-3 text-center action-cell">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        @foreach($deudas as $deuda)
                        @php
                            $numCli = $deuda->cliente?->numero ?? $deuda->cliente?->id ?? 0;
                            $vence = $deuda->vencimiento_deuda;
                            $esVencido = $vence && $vence->isPast();
                            $porVencer = $vence && !$vence->isPast() && $vence->diffInDays(now()) <= 7;
                        @endphp
                        {{-- Fila Principal de la Deuda --}}
                        <tr x-show="esDeudaVisible({{ $deuda->id }})"
                            x-cloak
                            class="transition-colors hover:bg-blue-50/40"
                            :class="{'bg-blue-50/20': isExpanded({{ $deuda->id }})}">

                            {{-- Columna 1: Código + Cliente + RUC --}}
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex h-6 min-w-[26px] px-1.5 items-center justify-center rounded-md bg-gray-100 border border-gray-300 text-xs font-mono font-black text-gray-700 shadow-xs print-badge"
                                          title="Cliente N° {{ $numCli }}">
                                        {{ $numCli }}
                                    </span>
                                    <a href="{{ route('clientes.show', $deuda->cliente) }}"
                                       class="font-bold text-gray-900 hover:text-brand-600 transition truncate max-w-[220px]">
                                        {{ $deuda->cliente->razon_social }}
                                    </a>
                                </div>
                                <p class="text-[10px] font-mono text-gray-400 mt-0.5 ml-9">
                                    RUC: {{ $deuda->cliente->ruc }}
                                </p>
                            </td>

                            {{-- Columna 2: Periodo --}}
                            <td class="py-3 px-3 text-center">
                                <span class="inline-block rounded-md bg-slate-100 border border-slate-200 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-800 print-badge">
                                    {{ $deuda->periodo }}
                                </span>
                            </td>

                            {{-- Columna 3: Tipo de Tributo con Código SUNAT --}}
                            <td class="py-3 px-3">
                                <div class="flex flex-col">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-[10px] font-bold text-indigo-700 w-fit print-badge">
                                        <span class="font-mono">{{ $deuda->tributo_codigo ?? '1011' }} -</span>
                                        <span>{{ $deuda->tributo_nombre_corto }}</span>
                                        @if($deuda->es_igv_justo)
                                            <span class="rounded bg-indigo-200/80 px-1 py-0.2 text-[8px] font-black text-indigo-900 uppercase">IGV Justo</span>
                                        @endif
                                    </span>
                                    <span class="text-[9px] text-gray-400 mt-0.5 truncate max-w-[180px]" title="{{ $deuda->tributo_nombre }}">
                                        {{ $deuda->tributo_nombre }}
                                    </span>
                                </div>
                            </td>

                            {{-- Columna 4: Vencimiento --}}
                            <td class="py-3 px-3 text-center">
                                @if($vence)
                                    <span @class([
                                        'inline-flex items-center gap-1 font-mono text-xs font-semibold px-2 py-0.5 rounded',
                                        'bg-rose-50 text-rose-700 border border-rose-200 font-bold' => $esVencido,
                                        'bg-amber-50 text-amber-700 border border-amber-200 font-bold' => $porVencer,
                                        'text-gray-700' => !$esVencido && !$porVencer,
                                    ]) title="{{ $esVencido ? 'Obligación vencida' : ($porVencer ? 'Vence pronto' : 'Dentro del plazo') }}">
                                        @if($esVencido)
                                            <svg class="h-3 w-3 text-rose-500 no-print" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                            </svg>
                                        @endif
                                        {{ $vence->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400 font-mono">—</span>
                                @endif
                            </td>

                            {{-- Columna 5: Deuda Original --}}
                            <td class="py-3 px-3 text-right font-mono font-medium text-gray-800">
                                S/ {{ number_format($deuda->monto_deuda_inicial, 2) }}
                            </td>

                            {{-- Columna 6: Total Abonado --}}
                            <td class="py-3 px-3 text-right">
                                <span class="font-mono font-bold text-emerald-600"
                                      x-text="formatoSoles(getDeudaLive({{ $deuda->id }})?.total_abonado)">
                                    S/ {{ number_format($deuda->total_abonado, 2) }}
                                </span>
                            </td>

                            {{-- Columna 7: Saldo Pendiente --}}
                            <td class="py-3 px-4 text-right">
                                <span class="font-mono font-black text-rose-600 text-[13px]"
                                      x-text="formatoSoles(getDeudaLive({{ $deuda->id }})?.saldo_pendiente)">
                                    S/ {{ number_format($deuda->saldo_pendiente, 2) }}
                                </span>
                            </td>

                            {{-- Columna 8: Botones de Acción y Acordeón --}}
                            <td class="no-print py-3 px-3 text-center action-cell">
                                <div class="inline-flex items-center gap-1.5">
                                    {{-- Botón Toggle Acordeón de Historial --}}
                                    <button
                                        @click="toggleAccordion({{ $deuda->id }})"
                                        type="button"
                                        title="Ver historial de amortizaciones y pagos"
                                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-[11px] font-bold text-gray-700 shadow-2xs hover:bg-gray-50 active:scale-95 transition"
                                        :class="{'border-brand-500 bg-brand-50/50 text-brand-700': isExpanded({{ $deuda->id }})}">
                                        <svg class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200"
                                             :class="{'rotate-180 text-brand-600': isExpanded({{ $deuda->id }})}"
                                             fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                        <span>Historial</span>
                                        <span class="rounded-full bg-gray-100 px-1.5 py-0.2 font-mono text-[9px] font-black text-gray-600"
                                              x-text="(getDeudaLive({{ $deuda->id }})?.abonos?.length || {{ $deuda->abonosDeuda->count() }})">
                                            {{ $deuda->abonosDeuda->count() }}
                                        </span>
                                    </button>

                                    {{-- Botón Registrar Abono --}}
                                    <button
                                        @click="abrirModalAbono({{ $deuda->id }})"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white shadow-xs hover:bg-emerald-700 active:scale-95 transition">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        <span>Abonar</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- ═══════════════ ACORDEÓN: HISTORIAL DE PAGOS DE ESTA DEUDA ═══════════════ --}}
                        <tr x-show="isExpanded({{ $deuda->id }}) && esDeudaVisible({{ $deuda->id }})"
                            x-cloak
                            class="no-print bg-slate-50/80 border-b border-gray-200 transition-all">
                            <td colspan="8" class="p-0">
                                <div class="px-6 py-4 border-t border-dashed border-gray-200">
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-200/80">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="text-xs font-bold text-gray-900">
                                                    Historial de Amortizaciones — {{ $deuda->cliente->razon_social }}
                                                    <span class="text-gray-400 font-mono text-[11px]">({{ $deuda->periodo }} &bull; {{ $deuda->tributo_nombre_corto }})</span>
                                                </h4>
                                                <p class="text-[10px] text-gray-500">
                                                    Registro cronológico de pagos parciales aplicados a esta deuda tributaria.
                                                </p>
                                            </div>
                                        </div>

                                        <button
                                            @click="abrirModalAbono({{ $deuda->id }})"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1 text-xs font-bold text-white shadow-2xs hover:bg-emerald-700 active:scale-95 transition">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            <span>Nuevo Abono</span>
                                        </button>
                                    </div>

                                    {{-- Tabla interna de abonos --}}
                                    <div class="mt-3">
                                        <template x-if="(getDeudaLive({{ $deuda->id }})?.abonos?.length || 0) === 0">
                                            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center bg-white">
                                                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                                                </svg>
                                                <p class="text-xs font-bold text-gray-700 mt-2">Aún no se registran abonos para esta deuda</p>
                                                <p class="text-[11px] text-gray-400 mt-0.5">El importe total de S/ {{ number_format($deuda->monto_deuda_inicial, 2) }} permanece como saldo pendiente.</p>
                                            </div>
                                        </template>

                                        <template x-if="(getDeudaLive({{ $deuda->id }})?.abonos?.length || 0) > 0">
                                            <div class="rounded-xl border border-gray-200 overflow-hidden bg-white shadow-2xs">
                                                <table class="w-full text-left text-xs">
                                                    <thead>
                                                        <tr class="bg-gray-100/80 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10px] tracking-wider">
                                                            <th class="py-2 px-3 text-center w-12">#</th>
                                                            <th class="py-2 px-3">Fecha de Pago</th>
                                                            <th class="py-2 px-3 text-right">Monto Abonado</th>
                                                            <th class="py-2 px-4">Observación / Referencia</th>
                                                            <th class="py-2 px-3 text-center w-24">Acción</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100">
                                                        <template x-for="(abono, index) in (getDeudaLive({{ $deuda->id }})?.abonos || [])" :key="abono.id">
                                                            <tr class="hover:bg-gray-50/70 transition">
                                                                <td class="py-2.5 px-3 text-center font-mono text-[10px] text-gray-400" x-text="index + 1"></td>
                                                                <td class="py-2.5 px-3 font-mono font-semibold text-gray-800" x-text="abono.fecha_pago"></td>
                                                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-600" x-text="abono.monto_formateado"></td>
                                                                <td class="py-2.5 px-4 text-gray-600">
                                                                    <span x-text="abono.observacion || 'Sin observación especificada'"
                                                                          :class="{'text-gray-400 italic': !abono.observacion}"></span>
                                                                </td>
                                                                <td class="py-2.5 px-3 text-center">
                                                                    <button
                                                                        @click="eliminarAbono({{ $deuda->id }}, abono.id, abono.monto_formateado)"
                                                                        type="button"
                                                                        title="Anular este abono"
                                                                        class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold text-rose-600 hover:bg-rose-50 hover:text-rose-800 transition">
                                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                                        </svg>
                                                                        <span>Anular</span>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    {{-- Pie de tabla con totales --}}
                    <tfoot>
                        <tr class="bg-gray-100/90 border-t-2 border-gray-300 font-bold text-xs text-gray-900">
                            <td colspan="4" class="py-3 px-4 uppercase text-[11px] tracking-wider text-gray-700">
                                Total General Consolidado (Visible)
                            </td>
                            <td class="py-3 px-3 text-right font-mono" x-text="formatoSoles(totalDeudaFiltrada)">
                                S/ {{ number_format($totalDeudaHistorica, 2) }}
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-700 font-black" x-text="formatoSoles(totalRecaudadoFiltrado)">
                                S/ {{ number_format($totalRecaudado, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-rose-700 font-black text-sm" x-text="formatoSoles(totalSaldoFiltrado)">
                                S/ {{ number_format($totalSaldoPorCobrar, 2) }}
                            </td>
                            <td class="no-print action-cell"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════ PIE DE PÁGINA PARA IMPRESIÓN (FIRMAS) ═══════════════════════════════ --}}
    <div class="hidden print-only mt-12 pt-6">
        <div class="grid grid-cols-2 gap-16 text-center text-xs">
            <div>
                <div class="border-t border-gray-900 mx-12 pt-2">
                    <p class="font-bold uppercase text-gray-900">{{ auth()->user()?->name ?? 'Contador Responsable' }}</p>
                    <p class="text-[10px] text-gray-600">Área de Contabilidad y Tributación</p>
                </div>
            </div>
            <div>
                <div class="border-t border-gray-900 mx-12 pt-2">
                    <p class="font-bold uppercase text-gray-900">Revisión / Auditoría Externa</p>
                    <p class="text-[10px] text-gray-600">Conformidad de Obligaciones Tributarias</p>
                </div>
            </div>
        </div>
        <p class="text-center text-[9px] text-gray-400 mt-8">
            Reporte generado automáticamente desde CRM Contable &bull; Validez interna sujeta a cotejo con fichas SOL y buzón SUNAT.
        </p>
    </div>

    {{-- ═══════════════════════════════ MODAL ENTERPRISE: REGISTRAR ABONO ═══════════════════════════════ --}}
    <div x-show="showModal" x-cloak
         class="no-print fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
         @keydown.escape.window="cerrarModal()">
        <div @click.away="cerrarModal()"
             class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all border border-gray-200"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            {{-- Header Modal --}}
            <div class="bg-gradient-to-r from-emerald-600 to-teal-700 px-6 py-4.5 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15">
                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-black tracking-tight text-white leading-tight">Registrar Abono Parcial</h3>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="inline-flex h-5 min-w-[22px] px-1.5 items-center justify-center rounded-md bg-white/20 border border-white/30 text-[10px] font-mono font-black text-white shadow-xs"
                                      x-text="modalClienteCodigo"
                                      title="Número de Cliente"></span>
                                <span class="text-xs font-bold text-white drop-shadow-xs truncate max-w-[250px]"
                                      x-text="modalClienteRazon"></span>
                            </div>
                        </div>
                    </div>
                    <button @click="cerrarModal()" class="rounded-lg p-1 text-white/70 hover:bg-white/10 hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Resumen Financiero Dinámico en Modal --}}
            <div class="px-6 pt-4">
                <div class="grid grid-cols-3 gap-2.5 text-center">
                    <div class="bg-gray-50 border border-gray-200/80 rounded-xl p-2.5">
                        <p class="text-[9px] font-bold uppercase text-gray-500 tracking-wider">Deuda Original</p>
                        <p class="text-xs font-black text-gray-800 font-mono mt-0.5" x-text="formatoSoles(modalDeuda)"></p>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200/80 rounded-xl p-2.5">
                        <p class="text-[9px] font-bold uppercase text-emerald-700 tracking-wider">Abonado</p>
                        <p class="text-xs font-black text-emerald-700 font-mono mt-0.5" x-text="formatoSoles(modalAbonado)"></p>
                    </div>
                    <div class="rounded-xl p-2.5 border" :class="modalSaldo > 0 ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-gray-50 border-gray-200 text-gray-500'">
                        <p class="text-[9px] font-bold uppercase tracking-wider">Saldo Restante</p>
                        <p class="text-xs font-black font-mono mt-0.5" x-text="formatoSoles(modalSaldo)"></p>
                    </div>
                </div>
            </div>

            {{-- Formulario de Registro --}}
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Monto a Abonar (PEN) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                            <span class="text-gray-400 text-xs font-bold font-mono select-none">S/</span>
                        </div>
                        <input type="text"
                               inputmode="decimal"
                               x-model="abonoMonto"
                               @input="abonoMonto = formatearMilesInput($event)"
                               class="w-full pl-11 pr-3 py-2 rounded-xl border border-gray-300 text-sm font-mono font-bold text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 leading-normal"
                               placeholder="0.00"
                               required>
                    </div>
                    <div class="flex items-center justify-between mt-1 text-[11px] text-gray-400">
                        <span>Pago parcial o liquidación total</span>
                        <button type="button"
                                @click="abonoMonto = formatearNumeroConComas(modalSaldo)"
                                class="font-bold text-emerald-600 hover:underline">
                            Pagar todo el saldo (S/ <span x-text="formatoSoles(modalSaldo).replace('S/ ', '')"></span>)
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Fecha de Operación / Pago <span class="text-rose-500">*</span>
                    </label>
                    <input type="date"
                           x-model="abonoFecha"
                           class="w-full rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                           required>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Observación / N° de Operación Bancaria
                    </label>
                    <input type="text"
                           x-model="abonoObs"
                           placeholder="Ej. Transferencia BCP Op. #98124, Cheque, Pago en ventanilla..."
                           class="w-full rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-900 placeholder-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>

            {{-- Footer Modal con botones --}}
            <div class="bg-gray-50/90 px-6 py-3.5 flex items-center justify-between border-t border-gray-200">
                <button @click="cerrarModal()"
                        type="button"
                        class="px-4 py-2 text-xs font-bold text-gray-600 hover:text-gray-900 transition">
                    Cancelar
                </button>
                <button @click="guardarAbono()"
                        type="button"
                        :disabled="saving || !abonoMonto || limpiarMoneda(abonoMonto) <= 0 || !abonoFecha"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition disabled:opacity-50 active:scale-95">
                    <svg x-show="saving" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="saving ? 'Procesando Pago...' : 'Registrar Abono'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════ SCRIPT REACTIVO ALPINE.JS (Enterprise UX) ═══════════════════════════════ --}}
<script>
function controlDeudas(initialDeudas, clientesList, totalDeudaHistorica, totalRecaudado, totalSaldoPorCobrar) {
    return {
        // Datos reactivos de deudas y clientes
        deudas: initialDeudas || [],
        clientes: clientesList || [],
        selectedClientes: (clientesList || []).map(c => String(c.id)),
        searchClientFilter: '',
        searchTable: '',
        filterOpen: false,
        expandedRows: [],

        // Estado del Modal de Abonos
        showModal: false,
        saving: false,
        modalDeudaId: null,
        modalClienteCodigo: '',
        modalClienteRazon: '',
        modalPeriodo: '',
        modalDeuda: 0,
        modalAbonado: 0,
        modalSaldo: 0,
        abonoMonto: '',
        abonoFecha: new Date().toISOString().split('T')[0],
        abonoObs: '',

        // Formateador de moneda en Soles (PEN) con 2 decimales estándar peruano
        formatoSoles(valor) {
            const num = parseFloat(valor) || 0;
            return 'S/ ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatearNumeroConComas(val) {
            if (val === null || val === undefined || val === '') return '';
            const num = parseFloat(String(val).replace(/,/g, ''));
            if (isNaN(num) || num === 0) return '';
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        limpiarMoneda(val) {
            if (val === null || val === undefined || val === '') return 0;
            const cleanStr = String(val).replace(/,/g, '').trim();
            const num = parseFloat(cleanStr);
            return isNaN(num) ? 0 : num;
        },

        formatearMilesInput(event) {
            const inputVal = event.target.value;
            const cleaned = inputVal.replace(/[^\d.]/g, '');
            const parts = cleaned.split('.');
            let integerPart = parts[0] || '';
            let decimalPart = parts.length > 1 ? parts.slice(1).join('') : null;

            if (decimalPart !== null) {
                decimalPart = decimalPart.substring(0, 2);
            }

            if (integerPart) {
                integerPart = integerPart.replace(/^0+(?=\d)/, '');
                integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            let formatted = decimalPart !== null ? `${integerPart}.${decimalPart}` : integerPart;
            if (cleaned.endsWith('.') && decimalPart === '') {
                formatted = integerPart ? `${integerPart}.` : '0.';
            }

            event.target.value = formatted;
            return formatted;
        },

        getClienteNumero(cId) {
            const c = this.clientes.find(cli => String(cli.id) === String(cId));
            return c ? c.numero_formateado : String(cId);
        },

        getClienteNombre(cId) {
            const c = this.clientes.find(cli => String(cli.id) === String(cId));
            return c ? c.razon_social : 'Cliente ' + cId;
        },

        removerClienteFiltro(cId) {
            this.selectedClientes = this.selectedClientes.filter(id => String(id) !== String(cId));
        },

        limpiarFiltroClientes() {
            this.seleccionarTodos();
        },

        // Helper para obtener la deuda live
        getDeudaLive(id) {
            return this.deudas.find(d => d.id === id);
        },

        // Determina si una deuda cumple los filtros de cliente y texto
        esDeudaVisible(deudaId) {
            const deuda = this.getDeudaLive(deudaId);
            if (!deuda) return false;

            // Filtro por selección de clientes (comparación segura de IDs sin error de tipo string vs integer)
            if (!this.selectedClientes.map(String).includes(String(deuda.cliente_id))) {
                return false;
            }

            // Filtro por búsqueda de texto
            if (this.searchTable.trim() !== '') {
                const q = this.searchTable.toLowerCase().trim();
                const matchCliente = (deuda.cliente_razon || '').toLowerCase().includes(q);
                const matchRuc = (deuda.cliente_ruc || '').toLowerCase().includes(q);
                const matchCodigo = (deuda.cliente_codigo || '').toLowerCase().includes(q);
                const matchPeriodo = (deuda.periodo || '').toLowerCase().includes(q);
                const matchTributo = (deuda.tributo_nombre || '').toLowerCase().includes(q) || (deuda.tributo_codigo || '').toLowerCase().includes(q);

                return matchCliente || matchRuc || matchCodigo || matchPeriodo || matchTributo;
            }

            return true;
        },

        // Lista de deudas actualmente visibles
        get deudasVisibles() {
            return this.deudas.filter(d => this.esDeudaVisible(d.id));
        },

        get deudasVisiblesCount() {
            return this.deudasVisibles.length;
        },

        // Recalculo reactivo de KPIs
        get totalDeudaFiltrada() {
            return this.deudasVisibles.reduce((sum, d) => sum + (parseFloat(d.monto_deuda_inicial) || 0), 0);
        },

        get totalRecaudadoFiltrado() {
            return this.deudasVisibles.reduce((sum, d) => sum + (parseFloat(d.total_abonado) || 0), 0);
        },

        get totalSaldoFiltrado() {
            return this.deudasVisibles.reduce((sum, d) => sum + (parseFloat(d.saldo_pendiente) || 0), 0);
        },

        get porcentajeRecaudadoGeneral() {
            if (this.totalDeudaFiltrada <= 0) return 0;
            return Math.min(100, Math.round((this.totalRecaudadoFiltrado / this.totalDeudaFiltrada) * 100));
        },

        get isFilteringClients() {
            return this.selectedClientes.length < this.clientes.length;
        },

        get filterLabel() {
            if (this.selectedClientes.length === this.clientes.length) {
                return 'Todos (' + this.clientes.length + ')';
            }
            return this.selectedClientes.length + ' de ' + this.clientes.length;
        },

        get clientesFiltradosEnDropdown() {
            if (!this.searchClientFilter.trim()) {
                return this.clientes;
            }
            const q = this.searchClientFilter.toLowerCase().trim();
            return this.clientes.filter(c =>
                (c.razon_social || '').toLowerCase().includes(q) ||
                (c.ruc || '').includes(q) ||
                (c.numero_formateado || '').includes(q)
            );
        },

        seleccionarTodos() {
            this.selectedClientes = this.clientes.map(c => String(c.id));
        },

        deseleccionarTodos() {
            this.selectedClientes = [];
        },

        // Toggle para expandir / contraer el acordeón de historial
        toggleAccordion(deudaId) {
            const index = this.expandedRows.indexOf(deudaId);
            if (index > -1) {
                this.expandedRows.splice(index, 1);
            } else {
                this.expandedRows.push(deudaId);
            }
        },

        isExpanded(deudaId) {
            return this.expandedRows.includes(deudaId);
        },

        // Apertura del modal de abono
        abrirModalAbono(deudaId) {
            const deuda = this.getDeudaLive(deudaId);
            if (!deuda) return;

            this.modalDeudaId = deuda.id;
            this.modalClienteCodigo = deuda.cliente_codigo;
            this.modalClienteRazon = deuda.cliente_razon;
            this.modalPeriodo = deuda.periodo;
            this.modalDeuda = deuda.monto_deuda_inicial;
            this.modalAbonado = deuda.total_abonado;
            this.modalSaldo = deuda.saldo_pendiente;
            this.abonoMonto = '';
            this.abonoFecha = new Date().toISOString().split('T')[0];
            this.abonoObs = '';
            this.showModal = true;
        },

        cerrarModal() {
            this.showModal = false;
        },

        // Guardar nuevo abono vía AJAX y actualizar el estado sin recargar
        async guardarAbono() {
            const montoLimpio = this.limpiarMoneda(this.abonoMonto);
            if (montoLimpio <= 0 || !this.abonoFecha) {
                alert('Por favor ingrese un monto y una fecha válidos.');
                return;
            }

            this.saving = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await fetch('{{ route("abonos_deudas.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        operacion_id: this.modalDeudaId,
                        monto: montoLimpio,
                        fecha_pago: this.abonoFecha,
                        observacion: this.abonoObs || null,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    const deuda = this.getDeudaLive(this.modalDeudaId);
                    if (deuda) {
                        deuda.total_abonado = parseFloat(data.total_abonado);
                        deuda.saldo_pendiente = parseFloat(data.saldo_pendiente);
                        if (!deuda.abonos) deuda.abonos = [];
                        deuda.abonos.unshift(data.abono);
                    }
                    this.cerrarModal();
                } else {
                    alert(data.message || 'Error al procesar el abono.');
                }
            } catch (error) {
                console.error('Error registrando abono:', error);
                alert('Ocurrió un error de red al procesar el abono.');
            } finally {
                this.saving = false;
            }
        },

        // Anular abono vía AJAX con confirmación
        async eliminarAbono(deudaId, abonoId, montoFormateado) {
            if (!confirm('¿Confirma la anulación de este abono por ' + montoFormateado + '?')) {
                return;
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const url = '{{ route("abonos_deudas.destroy", ":id") }}'.replace(':id', abonoId);

                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    const deuda = this.getDeudaLive(deudaId);
                    if (deuda) {
                        deuda.total_abonado = parseFloat(data.total_abonado);
                        deuda.saldo_pendiente = parseFloat(data.saldo_pendiente);
                        deuda.abonos = (deuda.abonos || []).filter(a => a.id !== abonoId);
                    }
                } else {
                    alert(data.message || 'No fue posible anular el abono.');
                }
            } catch (error) {
                console.error('Error anulando abono:', error);
                alert('Ocurrió un error al intentar anular el abono.');
            }
        },

        // Imprimir / Exportar a PDF
        imprimirReporte() {
            window.print();
        }
    };
}
</script>
@endsection
