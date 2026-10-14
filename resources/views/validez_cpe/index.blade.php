@extends('layouts.app')

@section('title', 'Validez de Comprobantes (API SUNAT)')

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6" x-data="moduloValidezCpe({
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

    {{-- ═══════════════════════════ HEADER DEL MÓDULO ═══════════════════════════ --}}
    <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 border border-teal-200/60 shadow-2xs">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">
                        Consulta de Validez de Comprobantes de Pago
                    </h1>
                    <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 border border-teal-200 px-2.5 py-0.5 text-xs font-semibold text-teal-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-500 animate-pulse"></span>
                        API SUNAT OAuth 2.0
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-gray-500 max-w-3xl">
                    Verificación preventiva oficial de comprobantes electrónicos (CPE) y físicos en SUNAT. Valida existencia, estado, anulación y condición de <strong class="text-gray-700">Habido</strong> del proveedor para resguardo del <strong class="text-gray-700">Crédito Fiscal (LIGV)</strong> y costo/gasto tributario.
                </p>
            </div>

            {{-- Selector de Cliente / Empresa Consultante --}}
            <div class="shrink-0 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 bg-gray-50/80 p-3 rounded-xl border border-gray-200/80">
                <div class="text-left">
                    <label for="cliente_selector" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">
                        Empresa Consultante:
                    </label>
                    <div class="relative">
                        <select id="cliente_selector"
                                x-model="clienteId"
                                @change="cambiarCliente()"
                                class="block w-full sm:w-72 rounded-lg border-gray-300 bg-white py-1.5 pl-3 pr-8 text-xs font-medium text-gray-900 shadow-2xs focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500">
                            <template x-for="c in clientes" :key="c.id">
                                <option :value="c.id" x-text="c.ruc + ' — ' + c.razon_social + (c.tiene_credenciales ? ' ✓' : ' (Sin API)')"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Pill de Estado de Credenciales --}}
                <div class="sm:pt-4">
                    <template x-if="clienteActual?.tiene_credenciales">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-800" title="API configurada y lista para consultas">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            API Conectada
                        </span>
                    </template>
                    <template x-if="!clienteActual?.tiene_credenciales">
                        <a :href="'/clientes/' + clienteId + '?tab=datos'"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 border border-amber-300 px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition"
                           title="Haga clic para configurar Id y Clave en la ficha del cliente">
                            <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            Configurar API
                        </a>
                    </template>
                </div>
            </div>
        </div>

        {{-- Alerta si la empresa actual no tiene credenciales --}}
        <template x-if="!clienteActual?.tiene_credenciales">
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-xs text-amber-900">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-amber-950 text-sm">Esta empresa no tiene configuradas las Credenciales de API SUNAT para Validez de Comprobantes</p>
                        <p class="mt-1 text-amber-800">
                            Para consultar comprobantes con la API de esta empresa, debe ingresar a SUNAT SOL en la ruta:
                            <span class="font-mono font-semibold bg-amber-100/90 px-1 py-0.5 rounded text-[11px]">Empresas &gt; Comprobantes de Pago &gt; Consulta de Validez de Comprobante de Pago &gt; Credenciales de API SUNAT</span>,
                            generar el <strong>Id</strong> y la <strong>Clave</strong>, y guardarlos en el expediente del cliente.
                        </p>
                        <div class="mt-2.5">
                            <a :href="'/clientes/' + clienteId + '?tab=datos'"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 font-semibold text-white hover:bg-amber-700 transition shadow-xs text-xs">
                                <span>Ir a Configurar Credenciales de esta Empresa</span>
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

    {{-- ═══════════════════════════ PESTAÑAS: INDIVIDUAL / MASIVO ═══════════════════════════ --}}
    <div class="flex border-b border-gray-200 bg-white rounded-t-xl px-4 pt-3 shadow-2xs">
        <nav class="flex gap-4">
            <button type="button"
                    @click="tab = 'individual'"
                    class="pb-3 px-3 text-sm font-semibold border-b-2 transition flex items-center gap-2 cursor-pointer"
                    :class="tab === 'individual' ? 'border-teal-600 text-teal-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <span>Consulta Individual</span>
            </button>

            <button type="button"
                    @click="tab = 'masivo'"
                    class="pb-3 px-3 text-sm font-semibold border-b-2 transition flex items-center gap-2 cursor-pointer"
                    :class="tab === 'masivo' ? 'border-teal-600 text-teal-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                </svg>
                <span>Validación Masiva (Excel / New Contasis / SIRE)</span>
                <template x-if="masivoFilas.length > 0">
                    <span class="rounded-full bg-teal-100 text-teal-800 text-[10px] font-bold px-2 py-0.5" x-text="masivoFilas.length"></span>
                </template>
            </button>
        </nav>
    </div>

    {{-- ═══════════════════════════ PESTAÑA 1: CONSULTA INDIVIDUAL ═══════════════════════════ --}}
    <div x-show="tab === 'individual'" x-cloak class="space-y-6">
        <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-xs">
            <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                Datos del Comprobante a Verificar
            </h2>

            <form @submit.prevent="ejecutarConsultaIndividual()" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                {{-- RUC Emisor --}}
                <div class="xl:col-span-2">
                    <label for="ind_ruc" class="block text-xs font-semibold text-gray-700 mb-1">
                        RUC Emisor (Proveedor o Empresa) <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="ind_ruc"
                           x-model="individual.numRuc"
                           maxlength="11"
                           placeholder="Ej: 20100047218"
                           class="block w-full rounded-lg border-gray-300 shadow-2xs focus:border-teal-500 focus:ring-teal-500 text-sm font-mono"
                           required />
                </div>

                {{-- Tipo Comprobante --}}
                <div class="xl:col-span-1">
                    <label for="ind_tipo" class="block text-xs font-semibold text-gray-700 mb-1">
                        Tipo de CPE <span class="text-rose-500">*</span>
                    </label>
                    <select id="ind_tipo"
                            x-model="individual.codComp"
                            class="block w-full rounded-lg border-gray-300 shadow-2xs focus:border-teal-500 focus:ring-teal-500 text-sm"
                            required>
                        <template x-for="(label, cod) in tiposCpe" :key="cod">
                            <option :value="cod" x-text="cod + ' - ' + label"></option>
                        </template>
                    </select>
                </div>

                {{-- Serie --}}
                <div>
                    <label for="ind_serie" class="block text-xs font-semibold text-gray-700 mb-1">
                        Serie <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="ind_serie"
                           x-model="individual.numeroSerie"
                           maxlength="5"
                           placeholder="Ej: F001"
                           class="block w-full rounded-lg border-gray-300 shadow-2xs focus:border-teal-500 focus:ring-teal-500 text-sm uppercase font-mono"
                           required />
                </div>

                {{-- Número Correlativo --}}
                <div>
                    <label for="ind_numero" class="block text-xs font-semibold text-gray-700 mb-1">
                        Número <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="ind_numero"
                           x-model="individual.numero"
                           maxlength="12"
                           placeholder="Ej: 1245"
                           class="block w-full rounded-lg border-gray-300 shadow-2xs focus:border-teal-500 focus:ring-teal-500 text-sm font-mono"
                           required />
                </div>

                {{-- Fecha de Emisión --}}
                <div>
                    <label for="ind_fecha" class="block text-xs font-semibold text-gray-700 mb-1">
                        Fecha Emisión <span class="text-rose-500">*</span>
                    </label>
                    <input type="date"
                           id="ind_fecha"
                           x-model="individual.fechaEmision"
                           class="block w-full rounded-lg border-gray-300 shadow-2xs focus:border-teal-500 focus:ring-teal-500 text-sm font-mono"
                           required />
                </div>

                {{-- Importe Total --}}
                <div>
                    <label for="ind_monto" class="block text-xs font-semibold text-gray-700 mb-1">
                        Monto Total (S/)
                    </label>
                    <input type="number"
                           id="ind_monto"
                           step="0.01"
                           x-model="individual.monto"
                           placeholder="0.00"
                           class="block w-full rounded-lg border-gray-300 shadow-2xs focus:border-teal-500 focus:ring-teal-500 text-sm font-mono" />
                </div>

                {{-- Botón de Acción --}}
                <div class="sm:col-span-2 lg:col-span-3 xl:col-span-6 flex items-center justify-end gap-3 pt-2">
                    <button type="submit"
                            :disabled="consultandoIndividual || !clienteActual?.tiene_credenciales"
                            class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition cursor-pointer">
                        <template x-if="!consultandoIndividual">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </template>
                        <template x-if="consultandoIndividual">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <span x-text="consultandoIndividual ? 'Consultando en SUNAT...' : 'Consultar Validez en SUNAT'"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- RESULTADO DE LA CONSULTA INDIVIDUAL --}}
        <template x-if="resultadoIndividual">
            <div class="rounded-2xl border bg-white p-6 shadow-xs space-y-6 transition"
                 :class="{
                     'border-emerald-300': resultadoIndividual.severidad === 'success',
                     'border-amber-300': resultadoIndividual.severidad === 'warning',
                     'border-rose-300': resultadoIndividual.severidad === 'danger'
                 }">

                {{-- Banner Principal con Veredicto Tributario --}}
                <div class="rounded-xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
                     :class="{
                         'bg-emerald-50 text-emerald-950 border border-emerald-200': resultadoIndividual.severidad === 'success',
                         'bg-amber-50 text-amber-950 border border-amber-200': resultadoIndividual.severidad === 'warning',
                         'bg-rose-50 text-rose-950 border border-rose-200': resultadoIndividual.severidad === 'danger'
                     }">
                    <div class="flex items-start gap-3.5">
                        <template x-if="resultadoIndividual.severidad === 'success'">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-xs">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </template>
                        <template x-if="resultadoIndividual.severidad === 'warning'">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                        </template>
                        <template x-if="resultadoIndividual.severidad === 'danger'">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500 text-white shadow-xs">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </div>
                        </template>

                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs uppercase font-extrabold tracking-wider"
                                      :class="{
                                          'text-emerald-700': resultadoIndividual.severidad === 'success',
                                          'text-amber-700': resultadoIndividual.severidad === 'warning',
                                          'text-rose-700': resultadoIndividual.severidad === 'danger'
                                      }">Diagnóstico Tributario</span>
                                <span class="text-xs font-mono text-gray-500" x-text="resultadoIndividual.consultado_at?.substring(0, 19).replace('T', ' ')"></span>
                            </div>
                            <h3 class="text-lg font-bold mt-0.5" x-text="resultadoIndividual.diagnostico"></h3>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"
                              :class="{
                                  'bg-emerald-200/80 text-emerald-900': resultadoIndividual.severidad === 'success',
                                  'bg-amber-200/80 text-amber-900': resultadoIndividual.severidad === 'warning',
                                  'bg-rose-200/80 text-rose-900': resultadoIndividual.severidad === 'danger'
                              }">
                            <template x-if="resultadoIndividual.es_valido_credito_fiscal">
                                <span>✓ Crédito Fiscal Apto</span>
                            </template>
                            <template x-if="!resultadoIndividual.es_valido_credito_fiscal">
                                <span>⚠ Sin Crédito Fiscal</span>
                            </template>
                        </span>
                    </div>
                </div>

                {{-- Métricas Técnicas de SUNAT (3 Tarjetas) --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- 1. Estado CPE --}}
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Estado del Comprobante (CPE)</span>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-lg font-black text-gray-900" x-text="resultadoIndividual.estado_cp?.label || 'DESCONOCIDO'"></span>
                            <span class="rounded px-2 py-0.5 text-xs font-mono font-bold"
                                  :class="{
                                      'bg-emerald-100 text-emerald-800': resultadoIndividual.estado_cp?.color === 'success',
                                      'bg-rose-100 text-rose-800': resultadoIndividual.estado_cp?.color === 'danger',
                                      'bg-blue-100 text-blue-800': resultadoIndividual.estado_cp?.color === 'info',
                                      'bg-gray-200 text-gray-800': !resultadoIndividual.estado_cp?.color
                                  }"
                                  x-text="'Código: ' + (resultadoIndividual.estado_cp?.codigo ?? '—')">
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" x-text="resultadoIndividual.estado_cp?.descripcion || '—'"></p>
                    </div>

                    {{-- 2. Estado RUC --}}
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Estado RUC Emisor</span>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-lg font-black text-gray-900" x-text="resultadoIndividual.estado_ruc?.label || 'NO DISPONIBLE'"></span>
                            <template x-if="resultadoIndividual.estado_ruc?.codigo">
                                <span class="rounded px-2 py-0.5 text-xs font-mono font-bold"
                                      :class="resultadoIndividual.estado_ruc?.es_activo ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="'Código: ' + resultadoIndividual.estado_ruc.codigo">
                                </span>
                            </template>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" x-text="resultadoIndividual.estado_ruc?.descripcion || 'Información provista por el padrón SUNAT.'"></p>
                    </div>

                    {{-- 3. Condición de Domicilio --}}
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Condición de Domicilio</span>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-lg font-black text-gray-900" x-text="resultadoIndividual.condicion_domicilio?.label || 'NO DISPONIBLE'"></span>
                            <template x-if="resultadoIndividual.condicion_domicilio?.codigo">
                                <span class="rounded px-2 py-0.5 text-xs font-mono font-bold"
                                      :class="resultadoIndividual.condicion_domicilio?.es_habido ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                      x-text="'Código: ' + resultadoIndividual.condicion_domicilio.codigo">
                                </span>
                            </template>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" x-text="resultadoIndividual.condicion_domicilio?.descripcion || 'Exigencia sustancial para deducción de costo/gasto.'"></p>
                    </div>
                </div>

                {{-- Observaciones devueltas por SUNAT si existen --}}
                <template x-if="resultadoIndividual.observaciones && resultadoIndividual.observaciones.length > 0">
                    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 text-xs text-amber-900">
                        <h4 class="font-bold mb-1 flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            Observaciones Oficiales de SUNAT:
                        </h4>
                        <ul class="list-disc list-inside space-y-0.5">
                            <template x-for="(obs, idx) in resultadoIndividual.observaciones" :key="idx">
                                <li x-text="obs"></li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- ═══════════════════════════ PESTAÑA 2: VALIDACIÓN MASIVA (EXCEL/CSV) ═══════════════════════════ --}}
    <div x-show="tab === 'masivo'" x-cloak class="space-y-6">

        {{-- Panel de Carga de Archivo --}}
        <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        Carga Masiva de Comprobantes (Excel / New Contasis / SIRE / CSV)
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Importe el Registro de Compras (~120 facturas) o Registro de Ventas (~780 comprobantes) exportado de su sistema contable. No se almacena nada en base de datos.
                    </p>
                </div>

                {{-- Enlace de Descarga de Plantilla --}}
                <div class="shrink-0 flex items-center gap-2">
                    <a :href="rutas.plantilla"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition">
                        <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Descargar Plantilla (.csv)
                    </a>
                </div>
            </div>

            {{-- Selector de Tipo de Registro (Compras vs Ventas) --}}
            <div class="mb-4 flex items-center gap-4 bg-gray-50 p-3 rounded-xl border border-gray-200">
                <span class="text-xs font-bold text-gray-700">Tipo de Registro a Auditar:</span>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                    <input type="radio" name="tipo_registro" value="compras" x-model="tipoRegistro" class="text-teal-600 focus:ring-teal-500">
                    <span>Registro de Compras (Emisores son Proveedores)</span>
                </label>
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                    <input type="radio" name="tipo_registro" value="ventas" x-model="tipoRegistro" class="text-teal-600 focus:ring-teal-500">
                    <span>Registro de Ventas (Emisor es <strong x-text="clienteActual?.ruc || 'la empresa'"></strong>)</span>
                </label>
            </div>

            {{-- Drag & Drop Dropzone --}}
            <div class="relative rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50/60 p-8 text-center hover:bg-gray-50/90 transition"
                 :class="{ 'border-teal-500 bg-teal-50/40': arrastrando }"
                 @dragover.prevent="arrastrando = true"
                 @dragleave.prevent="arrastrando = false"
                 @drop.prevent="arrastrando = false; manejarArchivoDrop($event)">

                <input type="file"
                       id="archivo_masivo"
                       accept=".xlsx,.csv,.txt"
                       class="sr-only"
                       @change="manejarArchivoInput($event)">

                <div class="space-y-2">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-100 text-teal-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>

                    <div class="text-sm text-gray-600">
                        <label for="archivo_masivo" class="font-bold text-teal-600 hover:text-teal-700 cursor-pointer underline">
                            Haga clic aquí para seleccionar su archivo
                        </label>
                        <span>o arrástrelo y suéltelo en este recuadro</span>
                    </div>

                    <p class="text-[11px] text-gray-500">
                        Formatos soportados: Excel (.xlsx), CSV delimitado por comas o punto y coma. Tamaño máximo: 15 MB.
                    </p>
                </div>
            </div>

            {{-- Estado de Lectura / Carga del Archivo --}}
            <template x-if="cargandoArchivo">
                <div class="mt-4 flex items-center justify-center gap-2 p-4 text-xs font-semibold text-teal-800 bg-teal-50 rounded-xl border border-teal-200">
                    <svg class="h-4 w-4 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span>Analizando estructura y reconociendo columnas del archivo...</span>
                </div>
            </template>
        </div>

        {{-- PANEL DE RESUMEN Y ACCIONES DE VALIDACIÓN MASIVA --}}
        <template x-if="masivoFilas.length > 0">
            <div class="space-y-6">

                {{-- Barra Superior de Control y KPIs --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    {{-- Total Filas --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Total Comprobantes</span>
                        <p class="text-xl font-black text-gray-900 mt-1" x-text="masivoFilas.length"></p>
                    </div>

                    {{-- Procesados --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Procesados</span>
                        <p class="text-xl font-black text-teal-600 mt-1" x-text="kpis.procesados + ' / ' + masivoFilas.length"></p>
                    </div>

                    {{-- Válidos / Aceptados --}}
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3 shadow-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">✓ Válidos (Aptos)</span>
                        <p class="text-xl font-black text-emerald-800 mt-1" x-text="kpis.validos"></p>
                    </div>

                    {{-- Observados --}}
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3 shadow-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">⚠ Observados</span>
                        <p class="text-xl font-black text-amber-800 mt-1" x-text="kpis.observados"></p>
                    </div>

                    {{-- Rechazados / Anulados / No Existen --}}
                    <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3 shadow-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700">✕ Rechazados</span>
                        <p class="text-xl font-black text-rose-800 mt-1" x-text="kpis.rechazados"></p>
                    </div>

                    {{-- Pendientes --}}
                    <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-3 shadow-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Pendientes</span>
                        <p class="text-xl font-black text-gray-700 mt-1" x-text="kpis.pendientes"></p>
                    </div>
                </div>

                {{-- Barra de Progreso Dinámica --}}
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-gray-700" x-text="procesandoMasivo ? 'Validando con API SUNAT... (' + kpis.procesados + ' de ' + masivoFilas.length + ')' : (kpis.pendientes === 0 ? '✓ Proceso completado al 100%' : 'Listo para validar')"></span>
                        <span class="font-mono font-bold text-teal-700" x-text="porcentajeProgreso + '%'"></span>
                    </div>

                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div class="bg-teal-600 h-3 rounded-full transition-all duration-300"
                             :style="'width: ' + porcentajeProgreso + '%'"></div>
                    </div>

                    {{-- Botonera de Acción Masiva --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                        <div class="flex items-center gap-2">
                            <template x-if="!procesandoMasivo && kpis.pendientes > 0">
                                <button type="button"
                                        @click="iniciarValidacionMasiva()"
                                        :disabled="!clienteActual?.tiene_credenciales"
                                        class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-teal-700 transition cursor-pointer">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    <span x-text="kpis.procesados > 0 ? 'Reanudar Validación' : 'Iniciar Validación Masiva con SUNAT'"></span>
                                </button>
                            </template>

                            <template x-if="procesandoMasivo">
                                <button type="button"
                                        @click="detenerValidacionMasiva()"
                                        class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-amber-700 transition cursor-pointer">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                                    </svg>
                                    <span>Pausar Proceso</span>
                                </button>
                            </template>

                            <button type="button"
                                    @click="limpiarTodo()"
                                    :disabled="procesandoMasivo"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition cursor-pointer">
                                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                <span>Limpiar Lista</span>
                            </button>
                        </div>

                        {{-- Botón Exportar Reporte a Excel / CSV --}}
                        <button type="button"
                                @click="exportarReporteAuditado()"
                                :disabled="kpis.procesados === 0 || exportando"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition cursor-pointer">
                            <template x-if="!exportando">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
                <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-xs">
                    {{-- Barra de Búsqueda y Píldoras de Filtro --}}
                    <div class="p-4 border-b border-gray-200 bg-gray-50/50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold text-gray-500 uppercase">Filtrar por:</span>
                            <button type="button"
                                    @click="filtroEstado = 'todos'"
                                    class="rounded-lg px-2.5 py-1 text-xs font-bold transition cursor-pointer"
                                    :class="filtroEstado === 'todos' ? 'bg-gray-800 text-white' : 'bg-white border text-gray-700 hover:bg-gray-100'">
                                Todos (<span x-text="masivoFilas.length"></span>)
                            </button>
                            <button type="button"
                                    @click="filtroEstado = 'validos'"
                                    class="rounded-lg px-2.5 py-1 text-xs font-bold transition cursor-pointer"
                                    :class="filtroEstado === 'validos' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100'">
                                ✓ Aptos (<span x-text="kpis.validos"></span>)
                            </button>
                            <button type="button"
                                    @click="filtroEstado = 'observados'"
                                    class="rounded-lg px-2.5 py-1 text-xs font-bold transition cursor-pointer"
                                    :class="filtroEstado === 'observados' ? 'bg-amber-600 text-white' : 'bg-amber-50 border border-amber-200 text-amber-800 hover:bg-amber-100'">
                                ⚠ Observados (<span x-text="kpis.observados"></span>)
                            </button>
                            <button type="button"
                                    @click="filtroEstado = 'rechazados'"
                                    class="rounded-lg px-2.5 py-1 text-xs font-bold transition cursor-pointer"
                                    :class="filtroEstado === 'rechazados' ? 'bg-rose-600 text-white' : 'bg-rose-50 border border-rose-200 text-rose-800 hover:bg-rose-100'">
                                ✕ Rechazados (<span x-text="kpis.rechazados"></span>)
                            </button>
                        </div>

                        {{-- Input de Búsqueda Rápida --}}
                        <div class="w-full md:w-64">
                            <input type="text"
                                   x-model="busquedaTabla"
                                   placeholder="Buscar por RUC, serie, número..."
                                   class="block w-full rounded-lg border-gray-300 py-1.5 pl-3 pr-3 text-xs shadow-2xs focus:border-teal-500 focus:ring-teal-500 font-medium">
                        </div>
                    </div>

                    {{-- Data Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="py-3 px-3 w-12 text-center">#</th>
                                    <th class="py-3 px-3">RUC Emisor</th>
                                    <th class="py-3 px-3">Razón Social</th>
                                    <th class="py-3 px-3">Comprobante</th>
                                    <th class="py-3 px-3">Fecha</th>
                                    <th class="py-3 px-3 text-right">Total</th>
                                    <th class="py-3 px-3 text-center">Estado CPE</th>
                                    <th class="py-3 px-3 text-center">Estado RUC</th>
                                    <th class="py-3 px-3 text-center">Condición</th>
                                    <th class="py-3 px-3">Diagnóstico Tributario</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template x-for="(fila, idx) in filasFiltradas" :key="fila.item">
                                    <tr class="hover:bg-gray-50/80 transition"
                                        :class="{
                                            'bg-emerald-50/20': fila.resultado?.es_valido_credito_fiscal,
                                            'bg-amber-50/20': fila.resultado?.severidad === 'warning',
                                            'bg-rose-50/20': fila.resultado?.severidad === 'danger'
                                        }">
                                        <td class="py-2.5 px-3 text-center font-mono text-gray-400" x-text="fila.item"></td>
                                        <td class="py-2.5 px-3 font-mono font-semibold text-gray-900" x-text="fila.numRuc"></td>
                                        <td class="py-2.5 px-3 max-w-[12rem] truncate text-gray-700" :title="fila.razonSocial || '—'" x-text="fila.razonSocial || '—'"></td>
                                        <td class="py-2.5 px-3 whitespace-nowrap">
                                            <span class="font-mono font-bold text-gray-900" x-text="fila.numeroSerie + '-' + fila.numero"></span>
                                            <span class="ml-1 text-[10px] text-gray-500 font-mono" x-text="'(' + fila.codComp + ')'"></span>
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-gray-600 whitespace-nowrap" x-text="fila.fechaEmision"></td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-gray-900 text-right whitespace-nowrap" x-text="'S/ ' + fila.monto"></td>

                                        {{-- Estado CPE --}}
                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                            <template x-if="!fila.resultado">
                                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600">Pendiente</span>
                                            </template>
                                            <template x-if="fila.resultado">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase"
                                                      :class="{
                                                          'bg-emerald-100 text-emerald-800': fila.resultado.estado_cp?.color === 'success',
                                                          'bg-rose-100 text-rose-800': fila.resultado.estado_cp?.color === 'danger',
                                                          'bg-blue-100 text-blue-800': fila.resultado.estado_cp?.color === 'info',
                                                          'bg-gray-100 text-gray-700': !fila.resultado.estado_cp?.color
                                                      }"
                                                      x-text="fila.resultado.estado_cp?.label || 'ERROR'">
                                                </span>
                                            </template>
                                        </td>

                                        {{-- Estado RUC --}}
                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                            <template x-if="fila.resultado?.estado_ruc">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase"
                                                      :class="fila.resultado.estado_ruc.es_activo ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                                      x-text="fila.resultado.estado_ruc.label">
                                                </span>
                                            </template>
                                            <template x-if="!fila.resultado?.estado_ruc">
                                                <span class="text-gray-400">—</span>
                                            </template>
                                        </td>

                                        {{-- Condición Domicilio --}}
                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                            <template x-if="fila.resultado?.condicion_domicilio">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase"
                                                      :class="fila.resultado.condicion_domicilio.es_habido ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                      x-text="fila.resultado.condicion_domicilio.label">
                                                </span>
                                            </template>
                                            <template x-if="!fila.resultado?.condicion_domicilio">
                                                <span class="text-gray-400">—</span>
                                            </template>
                                        </td>

                                        {{-- Diagnóstico Tributario --}}
                                        <td class="py-2.5 px-3 max-w-[20rem]">
                                            <template x-if="fila.resultado">
                                                <p class="text-[11px] leading-tight"
                                                   :class="{
                                                       'text-emerald-800 font-semibold': fila.resultado.severidad === 'success',
                                                       'text-amber-800 font-medium': fila.resultado.severidad === 'warning',
                                                       'text-rose-800 font-medium': fila.resultado.severidad === 'danger'
                                                   }"
                                                   x-text="fila.resultado.diagnostico"
                                                   :title="fila.resultado.diagnostico">
                                                </p>
                                            </template>
                                            <template x-if="!fila.resultado">
                                                <span class="text-gray-400 italic">En espera de validación</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- ═══════════════════════════ SCRIPT ALPINE.JS ═══════════════════════════ --}}
<script>
    function moduloValidezCpe(config) {
        return {
            clientes: config.clientes || [],
            clienteId: config.clienteInicialId,
            tiposCpe: config.tiposCpe || {},
            rutas: config.rutas,
            tab: 'individual',
            arrastrando: false,
            cargandoArchivo: false,
            procesandoMasivo: false,
            abortController: null,
            tipoRegistro: 'compras',
            exportando: false,

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

            get clienteActual() {
                return this.clientes.find(c => c.id == this.clienteId);
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

                    this.masivoFilas = data.filas.map(f => ({
                        ...f,
                        resultado: null
                    }));

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
