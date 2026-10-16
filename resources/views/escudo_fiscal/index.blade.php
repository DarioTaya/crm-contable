@extends('layouts.app')

@section('title', 'Escudo Fiscal / Auditoría SSCO y Detección de Facturas Compradas')

@section('content')
<div class="max-w-[1700px] mx-auto space-y-6" x-data="moduloEscudoFiscal({
    clientes: @js($clientesData),
    clienteInicialId: {{ $clienteSeleccionado?->id ?? 'null' }},
    estadisticasIniciales: @js($estadisticasPadron),
    rutas: {
        auditarRuc: '{{ route('escudo-fiscal.auditar-ruc') }}',
        sincronizar: '{{ route('escudo-fiscal.sincronizar') }}',
        padron: '{{ route('escudo-fiscal.padron') }}',
        exportar: '{{ route('escudo-fiscal.exportar') }}'
    }
})">

    {{-- ═══════════════════════════ CABECERA EJECUTIVA DEL MÓDULO ═══════════════════════════ --}}
    <div class="relative rounded-panel border border-gray-200 bg-surface p-6 shadow-card">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div class="space-y-3 max-w-4xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-danger-50 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-danger-700 ring-1 ring-inset ring-danger-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-danger-600"></span>
                        Escudo Forense Anti-Fraude Tributario
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-bold text-brand-700 ring-1 ring-inset ring-brand-200">
                        D.Leg. N.º 1532 &amp; DS N.º 319-2023-EF (SSCO)
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-semibold text-gray-700">
                        <span class="h-2 w-2 rounded-full bg-success-500 animate-pulse"></span>
                        Sincronización Automática SUNAT (Diaria 06:15 AM)
                    </span>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-panel bg-brand-800 text-white shadow-card">
                        <svg class="h-6 w-6 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black tracking-tight text-gray-900">
                            Escudo Fiscal 360° — Auditoría de Proveedores SSCO y Detección de Facturas Compradas
                        </h1>
                        <p class="text-xs sm:text-sm text-gray-600 mt-1">
                            Audita en segundos cualquier RUC proveedor cruzando el Padrón Oficial de <strong>Sujetos sin Capacidad Operativa (SSCO)</strong>, Empresas Vinculadas (Art. 12°), Representantes Legales clonados, Planilla PLAME, Deuda Coactiva, Actividad CIIU y Bancarización.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Bloque Derecho: Estado del Padrón y Botón de Sincronización en Vivo --}}
            <div class="shrink-0 flex flex-col sm:flex-row xl:flex-col items-stretch sm:items-center xl:items-end gap-2.5">
                <div class="rounded-control border border-gray-200 bg-gray-50 px-3.5 py-2 text-right">
                    <div class="flex items-center justify-end gap-1.5 text-[11px] font-bold text-gray-700">
                        <span>Corte Oficial SUNAT SSCO:</span>
                        <span class="text-brand-700 font-black" x-text="estadisticas.corte_ssco || '30 de setiembre de 2026'"></span>
                    </div>
                    <div class="text-[11px] text-gray-500 mt-0.5">
                        Última sincronización CRM:
                        <span class="font-mono font-semibold text-gray-700" x-text="estadisticas.sincronizado_at || 'Pendiente'"></span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="sincronizarConSunat(true)"
                            :disabled="sincronizando"
                            class="inline-flex items-center justify-center gap-2 rounded-control bg-brand-600 hover:bg-brand-700 disabled:opacity-60 px-3.5 py-2 text-xs font-bold text-white shadow-card transition cursor-pointer">
                        <svg class="h-4 w-4" :class="{ 'animate-spin': sincronizando }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span x-text="sincronizando ? 'Sincronizando Padrones desde SUNAT...' : 'Sincronizar Padrones SUNAT Ahora'"></span>
                    </button>

                    <a href="https://www.sunat.gob.pe/padronesnotificaciones/sujeSinCapacidadOperativa.html"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="Abrir portal oficial de SUNAT"
                       class="inline-flex items-center gap-1.5 rounded-control border border-gray-200 bg-white hover:bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-700 shadow-card transition">
                        <span>Portal SUNAT</span>
                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Tarjetas KPI de los Padrones Oficiales Cargados en el CRM --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 mt-5 pt-5 border-t border-gray-100">
            <div class="rounded-control border border-danger-200 bg-danger-50/50 p-3.5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-danger-700">SSCO Resolución Firme (Art. 7°)</p>
                    <p class="text-2xl font-black text-gray-900 tabular mt-0.5" x-text="estadisticas.ssco_firme_count || 0"></p>
                    <p class="text-[11px] text-gray-600 mt-0.5">Sin derecho a IGV ni Gasto Renta</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-danger-100 text-danger-700 font-black text-xs">
                    SSCO
                </div>
            </div>

            <div class="rounded-control border border-warning-200 bg-warning-50/50 p-3.5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-warning-700">EIRL / Sociedades Vinculadas (Art. 12°)</p>
                    <p class="text-2xl font-black text-gray-900 tabular mt-0.5" x-text="estadisticas.ssco_vinculado_count || 0"></p>
                    <p class="text-[11px] text-gray-600 mt-0.5">Socios o titulares sancionados como SSCO</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-warning-100 text-warning-700 font-black text-xs">
                    VINC
                </div>
            </div>

            <div class="rounded-control border border-info-200 bg-info-50/50 p-3.5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-info-700">Padrón Mayor Deuda Coactiva</p>
                    <p class="text-2xl font-black text-gray-900 tabular mt-0.5" x-text="estadisticas.deuda_coactiva_count || 0"></p>
                    <p class="text-[11px] text-gray-600 mt-0.5" x-text="estadisticas.corte_coactiva || 'Deudores > 5 UIT (Art. 85° CT)'"></p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-info-100 text-info-700 font-black text-xs">
                    COAC
                </div>
            </div>

            <div class="rounded-control border border-gray-200 bg-gray-50 p-3.5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600">Estado de su Cartera CRM</p>
                    <p class="text-lg font-black mt-0.5"
                       :class="(estadisticas.alertas_cartera?.length || 0) > 0 ? 'text-danger-700' : 'text-success-700'"
                       x-text="(estadisticas.alertas_cartera?.length || 0) > 0 ? (estadisticas.alertas_cartera.length + ' Clientes Observados') : '0 Clientes en Lista SSCO'"></p>
                    <p class="text-[11px] text-gray-500 mt-0.5">Cruce automático contra clientes del estudio</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-control"
                     :class="(estadisticas.alertas_cartera?.length || 0) > 0 ? 'bg-danger-100 text-danger-700' : 'bg-success-100 text-success-700'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════ NAVEGACIÓN DE PESTAÑAS DEL MÓDULO ═══════════════════════════ --}}
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 pb-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    @click="tabActiva = 'auditor'"
                    :class="tabActiva === 'auditor' ? 'bg-brand-800 text-white shadow-card' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'"
                    class="inline-flex items-center gap-2 rounded-control px-4 py-2 text-xs font-bold transition cursor-pointer">
                <svg class="h-4 w-4" :class="tabActiva === 'auditor' ? 'text-brand-400' : 'text-gray-500'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <span>1. Escáner Forense de RUC y Facturas (Anti-Estafa 1%)</span>
            </button>

            <button type="button"
                    @click="cambiarTabPadron('ssco_firme')"
                    :class="tabActiva === 'padron' && filtroPadron.tipo === 'ssco_firme' ? 'bg-danger-600 text-white shadow-card' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'"
                    class="inline-flex items-center gap-2 rounded-control px-4 py-2 text-xs font-bold transition cursor-pointer">
                <span>2. Padrón Oficial SSCO Firme</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black"
                      :class="tabActiva === 'padron' && filtroPadron.tipo === 'ssco_firme' ? 'bg-white/20 text-white' : 'bg-danger-50 text-danger-700'"
                      x-text="estadisticas.ssco_firme_count || 0"></span>
            </button>

            <button type="button"
                    @click="cambiarTabPadron('ssco_vinculado')"
                    :class="tabActiva === 'padron' && filtroPadron.tipo === 'ssco_vinculado' ? 'bg-warning-600 text-white shadow-card' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'"
                    class="inline-flex items-center gap-2 rounded-control px-4 py-2 text-xs font-bold transition cursor-pointer">
                <span>3. EIRL y Sociedades Vinculadas a SSCO (Art. 12°)</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black"
                      :class="tabActiva === 'padron' && filtroPadron.tipo === 'ssco_vinculado' ? 'bg-white/20 text-white' : 'bg-warning-50 text-warning-700'"
                      x-text="estadisticas.ssco_vinculado_count || 0"></span>
            </button>

            <button type="button"
                    @click="cambiarTabPadron('deuda_coactiva')"
                    :class="tabActiva === 'padron' && filtroPadron.tipo === 'deuda_coactiva' ? 'bg-info-600 text-white shadow-card' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'"
                    class="inline-flex items-center gap-2 rounded-control px-4 py-2 text-xs font-bold transition cursor-pointer">
                <span>4. Padrón Mayor Deuda Coactiva</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black"
                      :class="tabActiva === 'padron' && filtroPadron.tipo === 'deuda_coactiva' ? 'bg-white/20 text-white' : 'bg-info-50 text-info-700'"
                      x-text="estadisticas.deuda_coactiva_count || 0"></span>
            </button>

            <button type="button"
                    @click="tabActiva = 'normativa'"
                    :class="tabActiva === 'normativa' ? 'bg-brand-800 text-white shadow-card' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'"
                    class="inline-flex items-center gap-2 rounded-control px-4 py-2 text-xs font-bold transition cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                </svg>
                <span>Base Legal y Por Qué dice 30/09/2026</span>
            </button>
        </div>
    </div>

    {{-- Mensaje Flash Dinámico --}}
    <div x-show="mensajeFlash.texto" x-cloak
         class="rounded-control border p-3.5 text-xs font-semibold flex items-center justify-between"
         :class="mensajeFlash.tipo === 'error' ? 'bg-danger-50 border-danger-200 text-danger-700' : 'bg-success-50 border-success-200 text-success-700'">
        <span x-text="mensajeFlash.texto"></span>
        <button type="button" @click="mensajeFlash.texto = ''" class="text-gray-500 hover:text-gray-800 font-bold">✕</button>
    </div>

    {{-- ═══════════════════════════ PESTAÑA 1: ESCÁNER FORENSE 360° DE RUC Y FACTURAS ═══════════════════════════ --}}
    <div x-show="tabActiva === 'auditor'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- Panel Izquierdo: Formulario de Auditoría Forense --}}
            <div class="lg:col-span-5 rounded-panel border border-gray-200 bg-surface p-5 shadow-card space-y-4">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wide text-gray-900">Datos del Proveedor y Operación a Auditar</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Ingrese solo el RUC para auditar al proveedor, o agregue los datos de la factura para el cruce integral.</p>
                    </div>
                </div>

                <form @submit.prevent="ejecutarAuditoria(false)" class="space-y-4">
                    {{-- RUC del Proveedor --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            RUC del Proveedor Emisor <span class="text-danger-600">*</span>
                        </label>
                        <div class="flex gap-2">
                            <input type="text"
                                   x-model="form.ruc"
                                   maxlength="11"
                                   placeholder="Ej: 20610602771 o 20609720400"
                                   class="w-full rounded-control border border-gray-300 bg-white px-3 py-2 text-sm font-mono font-bold text-gray-900 focus:border-brand-600 focus:outline-none">
                            <button type="submit"
                                    :disabled="auditando"
                                    class="shrink-0 inline-flex items-center gap-2 rounded-control bg-brand-600 hover:bg-brand-700 disabled:opacity-60 px-4 py-2 text-xs font-bold text-white shadow-card transition cursor-pointer">
                                <svg class="h-4 w-4" :class="{ 'animate-spin': auditando }" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                <span x-text="auditando ? 'Auditando SUNAT...' : 'Auditar RUC'"></span>
                            </button>
                        </div>
                        {{-- Botones de Prueba Rápida con RUCs Reales --}}
                        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                            <span class="text-gray-400 font-semibold">Ejemplos reales:</span>
                            <button type="button" @click="cargarEjemplo('20610602771', 'Compra de cemento y fierro de construcción', 8500)"
                                    class="rounded bg-danger-50 hover:bg-danger-100 text-danger-700 px-2 py-0.5 font-mono font-semibold border border-danger-200 cursor-pointer">
                                20610602771 (SSCO Firme + Coactiva)
                            </button>
                            <button type="button" @click="cargarEjemplo('20616529961', 'Servicio de consultoría contable y auditoría', 5200)"
                                    class="rounded bg-warning-50 hover:bg-warning-100 text-warning-700 px-2 py-0.5 font-mono font-semibold border border-warning-200 cursor-pointer">
                                20616529961 (Vinculado SSCO Reciente)
                            </button>
                            <button type="button" @click="cargarEjemplo('20609720400', 'Servicio de transporte de carga por carretera', 1800)"
                                    class="rounded bg-success-50 hover:bg-success-100 text-success-700 px-2 py-0.5 font-mono font-semibold border border-success-200 cursor-pointer">
                                20609720400 (Proveedor Operativo)
                            </button>
                        </div>
                    </div>

                    {{-- Concepto / Giro de la Compra (Cruza contra el CIIU de SUNAT) --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">
                            Concepto / Descripción de lo Facturado <span class="text-gray-400 font-normal">(Para validar congruencia con CIIU)</span>
                        </label>
                        <input type="text"
                               x-model="form.concepto_operacion"
                               placeholder="Ej: Compra de materiales de ferretería, transporte de carga, asesoría legal..."
                               class="w-full rounded-control border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-600 focus:outline-none">
                    </div>

                    {{-- Monto, Moneda y CCI Bancario (Para validar Bancarización Ley 28194 y Detracción) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Importe Total</label>
                            <input type="number"
                                   step="0.01"
                                   x-model="form.monto"
                                   placeholder="Ej: 3500.00"
                                   class="w-full rounded-control border border-gray-300 bg-white px-3 py-2 text-xs font-mono font-semibold text-gray-900 focus:border-brand-600 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Moneda</label>
                            <select x-model="form.moneda"
                                    class="w-full rounded-control border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-900 focus:border-brand-600 focus:outline-none">
                                <option value="PEN">Soles (S/)</option>
                                <option value="USD">Dólares (US$)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">CCI Proveedor (20 díg.)</label>
                            <input type="text"
                                   maxlength="23"
                                   x-model="form.cci_proveedor"
                                   placeholder="Opcional: 002191..."
                                   class="w-full rounded-control border border-gray-300 bg-white px-3 py-2 text-xs font-mono text-gray-900 focus:border-brand-600 focus:outline-none">
                        </div>
                    </div>

                    {{-- Acordeón Opcional: Validar también existencia del Comprobante Electrónico en API SUNAT --}}
                    <div class="rounded-control border border-gray-200 bg-gray-50 p-3.5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-800">Validación simultánea del CPE en API SUNAT (Opcional)</span>
                            <span class="text-[10px] font-semibold text-gray-500">Si tiene la factura a la mano</span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Tipo CPE</label>
                                <select x-model="form.cod_comp" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs font-semibold text-gray-800">
                                    <option value="01">01 - Factura</option>
                                    <option value="03">03 - Boleta</option>
                                    <option value="R1">R1 - R. Honorarios</option>
                                    <option value="07">07 - Nota Crédito</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Serie</label>
                                <input type="text" x-model="form.numero_serie" placeholder="F001 / E001" maxlength="6"
                                       class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs font-mono uppercase text-gray-800">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Número</label>
                                <input type="text" x-model="form.numero" placeholder="1245" maxlength="10"
                                       class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs font-mono text-gray-800">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Fecha Emisión</label>
                                <input type="text" x-model="form.fecha_emision" placeholder="DD/MM/AAAA" maxlength="10"
                                       class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs font-mono text-gray-800">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Cliente Consultante (Credenciales API CPE)</label>
                            <select x-model="form.cliente_id" class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-800">
                                <template x-for="c in clientes" :key="c.id">
                                    <option :value="c.id" x-text="c.ruc + ' — ' + c.razon_social + (c.tiene_credenciales ? ' (API Activa)' : ' (Sin clave API)')"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="pt-1 flex items-center justify-between gap-3">
                        <button type="button"
                                @click="limpiarFormulario()"
                                class="rounded-control border border-gray-200 bg-white hover:bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600 cursor-pointer">
                            Limpiar campos
                        </button>

                        <button type="submit"
                                :disabled="auditando"
                                class="flex-1 inline-flex items-center justify-center gap-2 rounded-control bg-brand-800 hover:bg-brand-900 disabled:opacity-60 px-4 py-2.5 text-xs font-bold text-white shadow-card transition cursor-pointer">
                            <svg class="h-4 w-4 text-brand-400" :class="{ 'animate-spin': auditando }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span x-text="auditando ? 'Ejecutando Escáner Forense en SUNAT...' : 'Ejecutar Auditoría Forense 360°'"></span>
                        </button>
                    </div>
                </form>
            </div>

            {{-- Panel Derecho: Resultados del Dictamen Forense 360° --}}
            <div class="lg:col-span-7 space-y-5">

                {{-- Estado Inicial Vacío --}}
                <div x-show="!resultadoAuditoria && !auditando"
                     class="rounded-panel border border-dashed border-gray-300 bg-surface p-10 text-center space-y-3">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-900">Listo para Auditar Proveedores y Detectar Facturas al 1%</h3>
                    <p class="text-xs text-gray-500 max-w-xl mx-auto leading-relaxed">
                        Lo que manualmente toma 15 minutos entrando a múltiples pantallas de SUNAT, el <strong>Escudo Fiscal 360°</strong> lo ejecuta automáticamente en una sola consulta: verifica si está en el padrón SSCO, si sus gerentes están en otras empresas dadas de baja, su antigüedad, su planilla PLAME de los últimos 12 meses, sus establecimientos anexos, deudas coactivas y congruencia de giro.
                    </p>
                </div>

                {{-- Estado Cargando --}}
                <div x-show="auditando" x-cloak
                     class="rounded-panel border border-gray-200 bg-surface p-10 text-center space-y-3 shadow-card">
                    <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-brand-200 border-t-brand-600"></div>
                    <p class="text-sm font-bold text-gray-900">Inspeccionando el RUC en los servidores de SUNAT y cruzando Padrones SSCO...</p>
                    <p class="text-xs text-gray-500">Extrayendo fecha de inicio de actividades, representantes legales, planilla PLAME (12 períodos), deuda coactiva, anexos y CIIU.</p>
                </div>

                {{-- Resultado Completo de la Auditoría --}}
                <template x-if="resultadoAuditoria && !auditando">
                    <div class="space-y-5">

                        {{-- 1. TARJETA DE VEREDICTO Y SEMAFORIZACIÓN DE RIESGO --}}
                        <div class="rounded-panel border-2 p-5 shadow-card"
                             :class="{
                                 'border-danger-500 bg-danger-50/40': resultadoAuditoria.scoring.color === 'danger',
                                 'border-warning-500 bg-warning-50/40': resultadoAuditoria.scoring.color === 'warning',
                                 'border-success-500 bg-success-50/40': resultadoAuditoria.scoring.color === 'success'
                             }">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200/80 pb-4">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-0.5 text-xs font-black uppercase tracking-wider text-white"
                                              :class="{
                                                  'bg-danger-600': resultadoAuditoria.scoring.color === 'danger',
                                                  'bg-warning-600': resultadoAuditoria.scoring.color === 'warning',
                                                  'bg-success-600': resultadoAuditoria.scoring.color === 'success'
                                              }"
                                              x-text="resultadoAuditoria.scoring.etiqueta"></span>
                                        <span class="text-[11px] font-mono text-gray-500" x-text="'Auditado: ' + resultadoAuditoria.auditado_at"></span>
                                    </div>
                                    <h2 class="text-lg font-black text-gray-900 mt-2" x-text="resultadoAuditoria.ficha.ruc + ' — ' + resultadoAuditoria.ficha.razon_social"></h2>
                                    <p class="text-xs font-medium text-gray-600" x-text="resultadoAuditoria.ficha.tipo_contribuyente + ' • Domicilio: ' + resultadoAuditoria.ficha.domicilio_fiscal"></p>
                                </div>

                                {{-- Velocímetro / Puntaje de Riesgo Forense --}}
                                <div class="shrink-0 text-center rounded-panel bg-white border border-gray-200 px-4 py-2.5 shadow-2xs">
                                    <span class="block text-[10px] font-black uppercase tracking-wider text-gray-400">Score de Riesgo</span>
                                    <span class="text-3xl font-black tabular"
                                          :class="{
                                              'text-danger-600': resultadoAuditoria.scoring.color === 'danger',
                                              'text-warning-600': resultadoAuditoria.scoring.color === 'warning',
                                              'text-success-600': resultadoAuditoria.scoring.color === 'success'
                                          }"
                                          x-text="resultadoAuditoria.scoring.score + '/100'"></span>
                                </div>
                            </div>

                            {{-- Dictamen del Auditor Senior --}}
                            <div class="mt-3.5 text-xs leading-relaxed font-semibold text-gray-800 bg-white/90 rounded-control p-3.5 border border-gray-200">
                                <span class="font-black uppercase text-gray-900">Dictamen Forense: </span>
                                <span x-text="resultadoAuditoria.scoring.dictamen"></span>
                            </div>

                            {{-- Botón para forzar re-consulta en vivo a SUNAT --}}
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-500">
                                <span x-text="resultadoAuditoria.ficha.fuente_completa ? '✓ Ficha extendida extraída directamente de Consulta RUC SUNAT' : 'ℹ️ Datos obtenidos de Padrón Básico y Listas Locales'"></span>
                                <button type="button"
                                        @click="ejecutarAuditoria(true)"
                                        class="font-bold text-brand-700 hover:underline cursor-pointer">
                                    ↻ Refrescar en vivo desde SUNAT
                                </button>
                            </div>
                        </div>

                        {{-- 2. BANDERAS ROJAS Y HALLAZGOS DETECTADOS --}}
                        <div class="rounded-panel border border-gray-200 bg-surface p-5 shadow-card space-y-3">
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-800">
                                Hallazgos de Auditoría Forense (<span x-text="resultadoAuditoria.scoring.hallazgos.length"></span> indicadores evaluados)
                            </h3>

                            <div class="space-y-2.5">
                                <template x-for="(h, idx) in resultadoAuditoria.scoring.hallazgos" :key="idx">
                                    <div class="rounded-control border p-3.5"
                                         :class="{
                                             'border-danger-200 bg-danger-50/60': h.severidad === 'critico',
                                             'border-warning-200 bg-warning-50/60': h.severidad === 'alto' || h.severidad === 'medio',
                                             'border-success-200 bg-success-50/50': h.severidad === 'positivo'
                                         }">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-xs font-black"
                                                  :class="{
                                                      'text-danger-700': h.severidad === 'critico',
                                                      'text-warning-700': h.severidad === 'alto' || h.severidad === 'medio',
                                                      'text-success-700': h.severidad === 'positivo'
                                                  }"
                                                  x-text="h.titulo"></span>
                                            <span x-show="h.puntos > 0"
                                                  class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-black bg-white/80 text-gray-800 border border-gray-200"
                                                  x-text="'+' + h.puntos + ' pts riesgo'"></span>
                                        </div>
                                        <p class="text-xs text-gray-700 mt-1 leading-relaxed" x-text="h.detalle"></p>
                                        <p class="text-[10px] font-semibold text-gray-500 mt-1" x-text="'Base Legal: ' + h.normativa"></p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- 3. FICHA RADIOGRÁFICA DEL PROVEEDOR (INICIO ACTIVIDADES, CIIU, PLANILLA, REPRESENTANTES, ANEXOS) --}}
                        <div class="rounded-panel border border-gray-200 bg-surface p-5 shadow-card space-y-5">
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-800 border-b border-gray-100 pb-2.5">
                                Radiografía Tributaria y Operativa en SUNAT
                            </h3>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="rounded-control border border-gray-200 p-2.5 bg-gray-50">
                                    <span class="block text-[10px] font-bold uppercase text-gray-500">Estado RUC</span>
                                    <span class="text-xs font-black"
                                          :class="resultadoAuditoria.ficha.es_activo ? 'text-success-700' : 'text-danger-700'"
                                          x-text="resultadoAuditoria.ficha.estado + (resultadoAuditoria.ficha.fecha_baja ? ' (' + resultadoAuditoria.ficha.fecha_baja + ')' : '')"></span>
                                </div>
                                <div class="rounded-control border border-gray-200 p-2.5 bg-gray-50">
                                    <span class="block text-[10px] font-bold uppercase text-gray-500">Condición Domicilio</span>
                                    <span class="text-xs font-black"
                                          :class="resultadoAuditoria.ficha.es_habido ? 'text-success-700' : 'text-danger-700'"
                                          x-text="resultadoAuditoria.ficha.condicion"></span>
                                </div>
                                <div class="rounded-control border border-gray-200 p-2.5 bg-gray-50">
                                    <span class="block text-[10px] font-bold uppercase text-gray-500">Inicio Actividades</span>
                                    <span class="text-xs font-black text-gray-900"
                                          x-text="(resultadoAuditoria.ficha.fecha_inicio_actividades || '—') + (resultadoAuditoria.ficha.antiguedad_meses !== null ? ' (' + resultadoAuditoria.ficha.antiguedad_meses + 'm)' : '')"></span>
                                </div>
                                <div class="rounded-control border border-gray-200 p-2.5 bg-gray-50">
                                    <span class="block text-[10px] font-bold uppercase text-gray-500">Trabajadores PLAME</span>
                                    <span class="text-xs font-black"
                                          :class="resultadoAuditoria.ficha.max_trabajadores_12m > 0 ? 'text-success-700' : 'text-danger-700'"
                                          x-text="resultadoAuditoria.ficha.max_trabajadores_12m + ' trabajador(es) máx.'"></span>
                                </div>
                            </div>

                            {{-- Actividades Económicas CIIU --}}
                            <div>
                                <h4 class="text-[11px] font-bold uppercase text-gray-500 mb-1.5">Actividades Económicas (CIIU Declarados en SUNAT)</h4>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="(act, i) in resultadoAuditoria.ficha.actividades_economicas" :key="i">
                                        <span class="rounded-control bg-gray-100 border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-800" x-text="act"></span>
                                    </template>
                                    <span x-show="!resultadoAuditoria.ficha.actividades_economicas?.length" class="text-xs text-gray-400">No disponible</span>
                                </div>
                            </div>

                            {{-- Representantes Legales y Cruce de Gerentes --}}
                            <div>
                                <h4 class="text-[11px] font-bold uppercase text-gray-500 mb-1.5">Representantes Legales Declarados</h4>
                                <template x-if="resultadoAuditoria.ficha.representantes_legales?.length">
                                    <div class="overflow-x-auto rounded-control border border-gray-200">
                                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead class="bg-gray-50 text-[10px] font-bold uppercase text-gray-500">
                                                <tr>
                                                    <th class="px-3 py-2 text-left">Doc. Identidad</th>
                                                    <th class="px-3 py-2 text-left">Apellidos y Nombres</th>
                                                    <th class="px-3 py-2 text-left">Cargo</th>
                                                    <th class="px-3 py-2 text-left">Desde</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                <template x-for="(rep, idx) in resultadoAuditoria.ficha.representantes_legales" :key="idx">
                                                    <tr>
                                                        <td class="px-3 py-2 font-mono font-semibold text-gray-800" x-text="rep.tipo_doc + ' ' + rep.num_doc"></td>
                                                        <td class="px-3 py-2 font-bold text-gray-900" x-text="rep.nombre"></td>
                                                        <td class="px-3 py-2 text-gray-600" x-text="rep.cargo"></td>
                                                        <td class="px-3 py-2 font-mono text-gray-600" x-text="rep.fecha_desde"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                                <p x-show="!resultadoAuditoria.ficha.representantes_legales?.length" class="text-xs text-gray-400">Sin representantes legales listados (Persona Natural o no disponible).</p>
                            </div>

                            {{-- Evolución de Planilla Electrónica PLAME (Últimos Períodos) y Deuda Coactiva --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-[11px] font-bold uppercase text-gray-500 mb-1.5">Planilla PLAME (Últimos Períodos)</h4>
                                    <template x-if="resultadoAuditoria.ficha.trabajadores_planilla?.length">
                                        <div class="max-h-44 overflow-y-auto rounded-control border border-gray-200">
                                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                                <thead class="bg-gray-50 text-[10px] font-bold uppercase text-gray-500 sticky top-0">
                                                    <tr>
                                                        <th class="px-2.5 py-1.5 text-left">Período</th>
                                                        <th class="px-2.5 py-1.5 text-right">Trabajadores</th>
                                                        <th class="px-2.5 py-1.5 text-right">Prestadores</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 bg-white">
                                                    <template x-for="(tr, idx) in resultadoAuditoria.ficha.trabajadores_planilla.slice().reverse()" :key="idx">
                                                        <tr>
                                                            <td class="px-2.5 py-1 font-mono text-gray-700" x-text="tr.periodo"></td>
                                                            <td class="px-2.5 py-1 text-right font-mono font-bold text-gray-900" x-text="tr.trabajadores"></td>
                                                            <td class="px-2.5 py-1 text-right font-mono text-gray-600" x-text="tr.prestadores"></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                    <p x-show="!resultadoAuditoria.ficha.trabajadores_planilla?.length"
                                       class="rounded-control border border-danger-200 bg-danger-50/50 p-2.5 text-xs text-danger-700 font-semibold">
                                        No registra declaraciones de Planilla Electrónica (PLAME) en los últimos 12 períodos.
                                    </p>
                                </div>

                                <div>
                                    <h4 class="text-[11px] font-bold uppercase text-gray-500 mb-1.5">Deuda en Cobranza Coactiva y Anexos</h4>
                                    <template x-if="resultadoAuditoria.ficha.deudas_coactivas?.length">
                                        <div class="max-h-44 overflow-y-auto rounded-control border border-danger-200">
                                            <table class="min-w-full divide-y divide-danger-100 text-xs">
                                                <thead class="bg-danger-50 text-[10px] font-bold uppercase text-danger-700 sticky top-0">
                                                    <tr>
                                                        <th class="px-2.5 py-1.5 text-left">Período</th>
                                                        <th class="px-2.5 py-1.5 text-left">Entidad</th>
                                                        <th class="px-2.5 py-1.5 text-right">Monto (S/)</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 bg-white">
                                                    <template x-for="(d, idx) in resultadoAuditoria.ficha.deudas_coactivas" :key="idx">
                                                        <tr>
                                                            <td class="px-2.5 py-1 font-mono text-gray-800" x-text="d.periodo"></td>
                                                            <td class="px-2.5 py-1 text-gray-600" x-text="d.entidad"></td>
                                                            <td class="px-2.5 py-1 text-right font-mono font-bold text-danger-700" x-text=" d.monto.toFixed(2)"></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                    <div x-show="!resultadoAuditoria.ficha.deudas_coactivas?.length"
                                         class="rounded-control border border-success-200 bg-success-50/50 p-2.5 text-xs text-success-700 font-semibold mb-2">
                                        Sin deuda coactiva reportada en Consulta RUC.
                                    </div>

                                    <div class="text-xs text-gray-600 mt-2">
                                        <span class="font-bold text-gray-800">Establecimientos Anexos:</span>
                                        <span x-text="(resultadoAuditoria.ficha.establecimientos_anexos?.length || 0) + ' local(es) / depósito(s) declarados.'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 4. CHECKLIST DE FEHACIENCIA PARA EL CONTADOR SENIOR --}}
                        <div class="rounded-panel border border-gray-200 bg-surface p-5 shadow-card space-y-3">
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-800">
                                Protocolo de Blindaje y Fehaciencia Tributaria (Para evitar reparos de SUNAT)
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <template x-for="(item, idx) in resultadoAuditoria.scoring.checklist_fehaciencia" :key="idx">
                                    <label class="flex items-start gap-2.5 rounded-control border border-gray-200 p-3 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" class="mt-0.5 rounded border-gray-300">
                                        <div class="text-xs">
                                            <span class="font-bold text-gray-900 block" x-text="item.titulo"></span>
                                            <span class="text-gray-600 mt-0.5 block leading-relaxed text-[11px]" x-text="item.descripcion"></span>
                                        </div>
                                    </label>
                                </template>
                            </div>
                        </div>

                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════ PESTAÑA 2, 3, 4: EXPLORADOR DE PADRONES OFICIALES SUNAT ═══════════════════════════ --}}
    <div x-show="tabActiva === 'padron'" x-cloak class="space-y-4">
        <div class="rounded-panel border border-gray-200 bg-surface p-5 shadow-card space-y-4">

            {{-- Barra de búsqueda y exportación --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex-1 flex items-center gap-2">
                    <div class="relative flex-1 max-w-lg">
                        <input type="text"
                               x-model="filtroPadron.buscar"
                               @keydown.enter="cargarPadron(1)"
                               placeholder="Buscar por RUC, Razón Social, DNI o Nombre de Representante Legal..."
                               class="w-full rounded-control border border-gray-300 bg-white pl-3 pr-20 py-2 text-xs font-medium text-gray-900 focus:border-brand-600 focus:outline-none">
                        <button type="button"
                                @click="cargarPadron(1)"
                                class="absolute right-1.5 top-1.5 rounded bg-brand-600 hover:bg-brand-700 px-2.5 py-1 text-[11px] font-bold text-white cursor-pointer">
                            Buscar
                        </button>
                    </div>
                    <button type="button"
                            x-show="filtroPadron.buscar !== ''"
                            @click="filtroPadron.buscar = ''; cargarPadron(1)"
                            class="text-xs font-semibold text-gray-500 hover:text-gray-800 cursor-pointer">
                        Limpiar
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-600" x-text="'Total: ' + padronData.total + ' registros'"></span>
                    <a :href="rutas.exportar + '?tipo=' + encodeURIComponent(filtroPadron.tipo) + '&buscar=' + encodeURIComponent(filtroPadron.buscar)"
                       class="inline-flex items-center gap-1.5 rounded-control border border-gray-200 bg-white hover:bg-gray-50 px-3 py-2 text-xs font-bold text-gray-700 shadow-2xs">
                        <svg class="h-4 w-4 text-success-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Exportar Excel (CSV)</span>
                    </a>
                </div>
            </div>

            {{-- Tabla Dinámica del Padrón --}}
            <div class="overflow-x-auto rounded-control border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-brand-800 text-white text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-3 py-2.5 text-left">RUC</th>
                            <th class="px-3 py-2.5 text-left">Razón Social / Contribuyente</th>
                            <th class="px-3 py-2.5 text-left" x-show="filtroPadron.tipo !== 'deuda_coactiva'">Representante Legal / Titular</th>
                            <th class="px-3 py-2.5 text-left" x-show="filtroPadron.tipo === 'ssco_firme'">Resolución Firme / Domicilio</th>
                            <th class="px-3 py-2.5 text-left" x-show="filtroPadron.tipo === 'ssco_vinculado'">SSCO Origen y Causal (Art. 12°)</th>
                            <th class="px-3 py-2.5 text-right" x-show="filtroPadron.tipo === 'deuda_coactiva'">Monto Deuda (S/)</th>
                            <th class="px-3 py-2.5 text-center">Publicación / Corte</th>
                            <th class="px-3 py-2.5 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template x-if="cargandoPadron">
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 font-semibold">Cargando registros del padrón oficial...</td>
                            </tr>
                        </template>
                        <template x-if="!cargandoPadron && padronData.items.length === 0">
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    No se encontraron registros. Puede hacer clic en <strong>"Sincronizar Padrones SUNAT Ahora"</strong> arriba a la derecha.
                                </td>
                            </tr>
                        </template>
                        <template x-for="row in padronData.items" :key="row.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2.5 font-mono font-bold text-gray-900 whitespace-nowrap" x-text="row.ruc"></td>
                                <td class="px-3 py-2.5 font-bold text-gray-900" x-text="row.razon_social"></td>

                                <td class="px-3 py-2.5" x-show="filtroPadron.tipo !== 'deuda_coactiva'">
                                    <div class="font-semibold text-gray-800" x-text="row.nombre_representante || '—'"></div>
                                    <div class="font-mono text-[11px] text-gray-500" x-text="row.doc_representante ? ('Doc: ' + row.doc_representante) : ''"></div>
                                </td>

                                <td class="px-3 py-2.5" x-show="filtroPadron.tipo === 'ssco_firme'">
                                    <div class="font-semibold text-danger-700" x-text="row.resolucion_atribucion"></div>
                                    <div class="text-[11px] text-gray-500 truncate max-w-md" x-text="'Firme: ' + (row.fecha_firme_resolucion || '—') + ' • ' + (row.domicilio_fiscal || '')"></div>
                                </td>

                                <td class="px-3 py-2.5" x-show="filtroPadron.tipo === 'ssco_vinculado'">
                                    <div class="font-semibold text-warning-700" x-text="(row.ruc_ssco_origen || '') + ' — ' + (row.razon_social_ssco_origen || '')"></div>
                                    <div class="text-[11px] text-gray-600 max-w-md" x-text="row.supuesto_inclusion"></div>
                                </td>

                                <td class="px-3 py-2.5 text-right font-mono font-bold text-danger-700" x-show="filtroPadron.tipo === 'deuda_coactiva'">
                                    <span x-text="row.monto_deuda ? Number(row.monto_deuda).toLocaleString('es-PE', { minimumFractionDigits: 2 }) : '0.00'"></span>
                                </td>

                                <td class="px-3 py-2.5 text-center font-mono text-[11px] text-gray-600 whitespace-nowrap"
                                    x-text="row.fecha_publicacion || row.corte_sunat || '—'"></td>

                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <button type="button"
                                            @click="auditarDesdePadron(row.ruc)"
                                            class="inline-flex items-center gap-1 rounded bg-brand-50 hover:bg-brand-100 border border-brand-200 px-2.5 py-1 text-[11px] font-bold text-brand-700 cursor-pointer">
                                        <span>Auditar 360°</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            <div class="flex items-center justify-between pt-2 text-xs text-gray-600" x-show="padronData.last_page > 1">
                <div>
                    Página <span class="font-bold text-gray-900" x-text="padronData.current_page"></span> de <span class="font-bold text-gray-900" x-text="padronData.last_page"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="cargarPadron(padronData.current_page - 1)"
                            :disabled="padronData.current_page <= 1"
                            class="rounded border border-gray-300 bg-white px-3 py-1 font-semibold disabled:opacity-40 cursor-pointer">
                        Anterior
                    </button>
                    <button type="button"
                            @click="cargarPadron(padronData.current_page + 1)"
                            :disabled="padronData.current_page >= padronData.last_page"
                            class="rounded border border-gray-300 bg-white px-3 py-1 font-semibold disabled:opacity-40 cursor-pointer">
                        Siguiente
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════ PESTAÑA 5: EXPLICACIÓN LEGAL Y TÉCNICA (15/10/2026) ═══════════════════════════ --}}
    <div x-show="tabActiva === 'normativa'" x-cloak class="space-y-4">
        <div class="rounded-panel border border-gray-200 bg-surface p-6 shadow-card space-y-5 text-xs leading-relaxed text-gray-700">
            <div>
                <h2 class="text-base font-black text-gray-900">
                    ¿Por qué la página de SUNAT indica "Información actualizada al 30 de setiembre de 2026" siendo hoy 15/10/2026?
                </h2>
                <p class="mt-2">
                    De acuerdo con el <strong>Artículo 7° del Decreto Legislativo N.º 1532</strong> y el <strong>Artículo 9° de su Reglamento (Decreto Supremo N.º 319-2023-EF)</strong>, la SUNAT publica la relación de Sujetos sin Capacidad Operativa (SSCO) cuyas resoluciones de atribución han quedado firmes <strong>el último día calendario de cada mes</strong> (en simultáneo en el portal institucional de SUNAT y en el Diario Oficial <em>El Peruano</em>).
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Corte de Setiembre 2026:</strong> Fue publicado el <strong>30 de setiembre de 2026</strong> (con 765 contribuyentes con resolución firme y 121 registros de empresas vinculadas).</li>
                    <li><strong>Próximo Corte Oficial (Octubre 2026):</strong> Por mandato legal del DS N.º 319-2023-EF, las resoluciones que adquieran firmeza durante octubre de 2026 se publican el <strong>31 de octubre de 2026</strong>.</li>
                    <li><strong>Padrón de Mayor Deuda Coactiva:</strong> A diferencia del padrón SSCO (que es mensual), el padrón de grandes deudores en cobranza coactiva se actualiza quincenal/semanalmente (su último corte en vivo en SUNAT es del <strong>12 de octubre de 2026</strong> con 9,056 deudores).</li>
                </ul>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <h3 class="text-sm font-black text-gray-900">¿Existe una API de SUNAT para el Padrón SSCO o cómo se actualiza en automático en el CRM?</h3>
                <p class="mt-1.5">
                    SUNAT no publica un endpoint REST OAuth2 para el listado SSCO, sino que su página web (<code>sujeSinCapacidadOperativa.html</code> y <code>relacion-ssco.html</code>) descarga en segundo plano dos archivos Excel oficiales en vivo:
                </p>
                <ul class="list-disc pl-5 mt-1.5 space-y-1 font-mono text-[11px] text-gray-800">
                    <li>https://www.sunat.gob.pe/padronesnotificaciones/ssco/sujesincapacidadOperativa.xlsx</li>
                    <li>https://www.sunat.gob.pe/padronesnotificaciones/relacion-ssco/descarga-relacion-ssco.xlsx</li>
                    <li>https://www.sunat.gob.pe/padronesnotificaciones/deudaExigibleControlDeuda/pgdt-deudaPadron.xlsx</li>
                </ul>
                <p class="mt-2">
                    <strong>Solución implementada en su CRM Contable:</strong> Hemos conectado su CRM directamente a esos 3 archivos oficiales de SUNAT mediante el servicio <code>SunatSscoPadronService</code> y la tarea programada automática <code>php artisan sunat:sincronizar-ssco</code> (que corre todos los días a las 06:15 AM hora Lima, además del botón <strong>"Sincronizar Padrones SUNAT Ahora"</strong>). Así, apenas SUNAT suba una actualización, su CRM la descarga, convierte las fechas y cruza los DNI de los representantes legales automáticamente.
                </p>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('moduloEscudoFiscal', (config) => ({
        clientes: config.clientes || [],
        estadisticas: config.estadisticasIniciales || {},
        rutas: config.rutas || {},
        tabActiva: 'auditor',
        sincronizando: false,
        auditando: false,
        cargandoPadron: false,
        mensajeFlash: { texto: '', tipo: 'success' },

        form: {
            ruc: '',
            cliente_id: config.clienteInicialId || (config.clientes?.[0]?.id ?? ''),
            cod_comp: '01',
            numero_serie: '',
            numero: '',
            fecha_emision: '',
            monto: '',
            moneda: 'PEN',
            concepto_operacion: '',
            cci_proveedor: ''
        },

        resultadoAuditoria: null,

        filtroPadron: {
            tipo: 'ssco_firme',
            buscar: ''
        },

        padronData: {
            items: [],
            total: 0,
            current_page: 1,
            last_page: 1
        },

        cargarEjemplo(ruc, concepto, monto) {
            this.form.ruc = ruc;
            this.form.concepto_operacion = concepto;
            this.form.monto = monto;
            this.ejecutarAuditoria(false);
        },

        limpiarFormulario() {
            this.form.ruc = '';
            this.form.numero_serie = '';
            this.form.numero = '';
            this.form.fecha_emision = '';
            this.form.monto = '';
            this.form.concepto_operacion = '';
            this.form.cci_proveedor = '';
            this.resultadoAuditoria = null;
        },

        async ejecutarAuditoria(forzarRefresh = false) {
            const rucLimpio = (this.form.ruc || '').replace(/\D/g, '');
            if (rucLimpio.length !== 11) {
                this.mostrarMensaje('El RUC debe contener exactamente 11 dígitos numéricos.', 'error');
                return;
            }

            this.auditando = true;
            this.mensajeFlash.texto = '';

            try {
                const res = await fetch(this.rutas.auditarRuc, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        ...this.form,
                        ruc: rucLimpio,
                        forzar_refresh: forzarRefresh
                    })
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    this.mostrarMensaje(data.message || 'No se pudo completar la auditoría del RUC.', 'error');
                } else {
                    this.resultadoAuditoria = data;
                }
            } catch (e) {
                this.mostrarMensaje('Error de red al ejecutar la auditoría forense.', 'error');
            } finally {
                this.auditando = false;
            }
        },

        async sincronizarConSunat(incluirCoactiva = true) {
            this.sincronizando = true;
            this.mensajeFlash.texto = '';

            try {
                const res = await fetch(this.rutas.sincronizar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ incluir_coactiva: incluirCoactiva })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    this.estadisticas = data.estadisticas;
                    this.mostrarMensaje(data.message, 'success');
                    if (this.tabActiva === 'padron') {
                        this.cargarPadron(1);
                    }
                } else {
                    this.mostrarMensaje(data.message || 'Error al sincronizar con SUNAT.', 'error');
                }
            } catch (e) {
                this.mostrarMensaje('No se pudo conectar con SUNAT para sincronizar los padrones.', 'error');
            } finally {
                this.sincronizando = false;
            }
        },

        cambiarTabPadron(tipo) {
            this.tabActiva = 'padron';
            this.filtroPadron.tipo = tipo;
            this.cargarPadron(1);
        },

        async cargarPadron(page = 1) {
            this.cargandoPadron = true;
            try {
                const url = `${this.rutas.padron}?tipo=${encodeURIComponent(this.filtroPadron.tipo)}&buscar=${encodeURIComponent(this.filtroPadron.buscar)}&page=${page}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.padronData = data;
                }
            } catch (e) {
                this.mostrarMensaje('Error al cargar el listado del padrón.', 'error');
            } finally {
                this.cargandoPadron = false;
            }
        },

        auditarDesdePadron(ruc) {
            this.form.ruc = ruc;
            this.tabActiva = 'auditor';
            this.ejecutarAuditoria(false);
        },

        mostrarMensaje(texto, tipo = 'success') {
            this.mensajeFlash = { texto, tipo };
        }
    }));
});
</script>
@endsection
