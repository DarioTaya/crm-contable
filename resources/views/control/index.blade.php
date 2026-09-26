@extends('layouts.app')

@section('title', "Control de Impuestos {$anio} — Matriz Anual")
@section('header', 'Control de Impuestos')

@section('content')
    {{-- ═══════════════════════════════ ENCABEZADO Y SELECTOR DE AÑO ═══════════════════════════════ --}}
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Control de Impuestos {{ $anio }}</h2>
                <span class="rounded bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-xs font-bold text-emerald-800">
                    Matriz 100%
                </span>
            </div>
            <p class="mt-0.5 text-xs text-gray-500">
                Matriz anual de control tributario (DJ: SIRE, PDT 621 y PDT 601) y cobro de honorarios (RH: Recibo por Honorarios) con notas flotantes.
            </p>
        </div>

        {{-- Barra de Herramientas y Selector de Ejercicio Fiscal --}}
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            {{-- Leyenda Rápida de Estados --}}
            <div class="hidden lg:flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-[11px] font-medium text-gray-600 shadow-xs">
                <span>⚪ Pendiente / Debe</span>
                <span class="text-gray-300">|</span>
                <span>🟡 En Proceso</span>
                <span class="text-gray-300">|</span>
                <span>🟢 Declarado / Pagado</span>
                <span class="text-gray-300">|</span>
                <span class="font-bold text-gray-700">DJ: Declaración &bull; RH: Honorarios</span>
                <span class="text-gray-300">|</span>
                <span class="text-gray-500">💬 Nota</span>
                <span class="text-gray-300">|</span>
                <span class="flex items-center gap-1 font-semibold text-gray-700">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500 border border-white"></span> Deuda SUNAT
                </span>
            </div>

            {{-- Enlace Directo a Cronograma SUNAT --}}
            <a href="{{ route('cronogramas.index', ['anio' => $anio, 'tipo' => 'pdt_621']) }}"
               title="Ver Cronograma Oficial SUNAT"
               class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700 shadow-xs transition hover:bg-brand-100">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span>Cronograma SUNAT</span>
            </a>

            {{-- Selector interactivo de Año --}}
            <div class="flex items-center gap-1.5">
                <a href="{{ route('control.index', ['anio' => $anio - 1]) }}"
                   title="Año {{ $anio - 1 }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-600 shadow-xs transition hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </a>

                <select
                    id="selectorAnio"
                    onchange="window.location.href = '{{ route('control.index') }}?anio=' + this.value"
                    class="rounded-lg border border-gray-300 bg-white py-1.5 pl-3 pr-7 text-xs font-bold text-gray-800 shadow-xs transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 cursor-pointer"
                >
                    @foreach ($aniosDisponibles as $y)
                        <option value="{{ $y }}" {{ $y === $anio ? 'selected' : '' }}>
                            Año {{ $y }}
                        </option>
                    @endforeach
                </select>

                <a href="{{ route('control.index', ['anio' => $anio + 1]) }}"
                   title="Año {{ $anio + 1 }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-600 shadow-xs transition hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ RESUMEN SUPERIOR (KPIS CON HONORARIOS - REACTIVO) ═══════════════════════════════ --}}
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-5"
         x-data="{
             totalClientes: {{ $totalClientes }},
             declaradasTotal: {{ $declaradasTotal }},
             procesandoTotal: {{ $procesandoTotal }},
             pendientesTotal: {{ $pendientesTotal }},
             porcentajeAvanceAnual: {{ $porcentajeAvanceAnual }},
             honorariosPagadosTotal: {{ $honorariosPagadosTotal }},
             totalHonorariosAnio: {{ $totalClientes * 12 }},
             init() {
                 window.addEventListener('kpis-actualizados', (e) => {
                     this.totalClientes = e.detail.totalClientes;
                     this.declaradasTotal = e.detail.declaradasTotal;
                     this.procesandoTotal = e.detail.procesandoTotal;
                     this.pendientesTotal = e.detail.pendientesTotal;
                     this.porcentajeAvanceAnual = e.detail.porcentajeAvanceAnual;
                     this.honorariosPagadosTotal = e.detail.honorariosPagadosTotal;
                     this.totalHonorariosAnio = e.detail.totalClientes * 12;
                 });
             }
         }">
        <div class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-gray-500 tracking-wider">Clientes Activos</p>
                <p class="text-lg font-black text-gray-900 leading-tight" x-text="totalClientes">{{ $totalClientes }}</p>
            </div>
            <span class="text-xl">🏢</span>
        </div>

        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">Declaradas (DJ)</p>
                <p class="text-lg font-black text-emerald-800 leading-tight">
                    <span x-text="declaradasTotal">{{ $declaradasTotal }}</span>
                    <span class="text-xs font-normal text-emerald-600">(<span x-text="porcentajeAvanceAnual">{{ $porcentajeAvanceAnual }}</span>%)</span>
                </p>
            </div>
            <span class="text-xl">🟢</span>
        </div>

        <div class="rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-amber-700 tracking-wider">En Proceso</p>
                <p class="text-lg font-black text-amber-800 leading-tight" x-text="procesandoTotal">{{ $procesandoTotal }}</p>
            </div>
            <span class="text-xl">🟡</span>
        </div>

        <div class="rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-600 tracking-wider">Pendientes (DJ)</p>
                <p class="text-lg font-black text-slate-800 leading-tight" x-text="pendientesTotal">{{ $pendientesTotal }}</p>
            </div>
            <span class="text-xl">⚪</span>
        </div>

        <div class="rounded-lg border border-purple-300 bg-purple-50 px-3.5 py-2 shadow-xs flex items-center justify-between col-span-2 sm:col-span-1">
            <div>
                <p class="text-[10px] uppercase font-bold text-purple-700 tracking-wider">Honorarios (RH)</p>
                <p class="text-lg font-black text-purple-800 leading-tight">
                    <span x-text="honorariosPagadosTotal">{{ $honorariosPagadosTotal }}</span>
                    <span class="text-xs font-normal text-purple-600">/ <span x-text="totalHonorariosAnio">{{ $totalClientes * 12 }}</span></span>
                </p>
            </div>
            <span class="text-xl" title="Honorarios cobrados en el año">💰</span>
        </div>
    </div>

    {{-- ═══════════════════════════════ TABLA MATRIZ 100% MONITOR (13 COLUMNAS: DJ + RH) ═══════════════════════════════ --}}
    <div class="overflow-x-auto rounded-xl border border-gray-300 bg-white shadow-md min-h-[200px]">
        <table class="w-full table-fixed border-collapse border border-gray-300 text-xs text-left">

            {{-- ═══════════════ CABECERA ÚNICA (THEAD - 13 COLUMNAS CON DJ Y RH) ═══════════════ --}}
            <thead class="bg-gray-100 text-gray-700 text-xs font-extrabold select-none shadow-xs">
                <tr class="border-b border-gray-300 bg-gray-200/90 text-center">
                    {{-- Columna 1: Cliente/RUC (Sticky Left) con N° Correlativo --}}
                    <th class="w-[22%] px-2.5 py-2 text-left border-r border-b border-gray-300 bg-gray-200 sticky left-0 z-20 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.15)]">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex h-5 min-w-[22px] px-1 items-center justify-center rounded bg-gray-300/80 border border-gray-400/60 text-[10px] font-mono font-black text-gray-800 shrink-0">N°</span>
                            <div class="min-w-0 flex-1">
                                <span class="block text-[11px] font-black uppercase tracking-wider text-gray-800 truncate">CLIENTE / RUC</span>
                                <span class="block text-[9px] font-normal text-gray-500">Régimen Tributario</span>
                            </div>
                        </div>
                    </th>

                    {{-- Columnas 2 a 13: 12 Meses con sub-etiquetas DJ y RH integradas en la misma celda --}}
                    @foreach ($mesesEncabezado as $mNum => $mInfo)
                        <th class="w-[6.5%] px-0.5 py-1.5 border-r border-b border-gray-300 text-center whitespace-nowrap bg-gray-100 text-gray-800 select-none">
                            <span class="block text-[11px] font-black tracking-tight">{{ $mInfo['corto'] }}-{{ substr($anio, -2) }}</span>
                            <div class="flex items-center justify-center gap-1 text-[8px] font-black text-gray-500 uppercase tracking-tighter leading-none mt-0.5">
                                <span title="Declaración Jurada Mensual (SIRE / PDT 621 / PLAME)">DJ</span>
                                <span class="text-gray-300 font-normal">|</span>
                                <span title="Recibo por Honorarios">RH</span>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>

            {{-- ═══════════════ CUERPO DE LA TABLA (TBODY) ═══════════════ --}}
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($matrizClientes as $item)
                    @php
                        $colores = [
                            'MYPE'    => 'bg-blue-100 text-blue-800 border-blue-200',
                            'RER'     => 'bg-amber-100 text-amber-800 border-amber-200',
                            'RUS'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'General' => 'bg-purple-100 text-purple-800 border-purple-200',
                        ];
                        $badgeColor = $colores[$item['cliente']->regimen_tributario?->value] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                    @endphp

                    {{-- Fila del Cliente (Reactividad Alpine.js) --}}
                    <tr x-data="filaClienteMatriz({
                            clienteId: {{ $item['cliente']->id }},
                            anio: {{ $anio }},
                            meses: @js($item['meses'])
                        })"
                        class="hover:bg-blue-50/20 transition-colors">

                        {{-- COLUMNA 1: CLIENTE / RUC (STICKY LEFT) CON N° CORRELATIVO --}}
                        <td class="w-[22%] sticky left-0 bg-white z-10 px-2.5 py-2 border-r border-b border-gray-300 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.12)] whitespace-nowrap overflow-hidden">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    {{-- N° Correlativo sincronizado con la lista de clientes --}}
                                    <span class="inline-flex h-5 min-w-[24px] px-1 items-center justify-center rounded-md bg-gray-100 border border-gray-300 text-[10px] font-mono font-black text-gray-700 shrink-0 shadow-xs"
                                          title="Cliente N° {{ $item['numero'] }}">
                                        {{ $item['numero'] }}
                                    </span>

                                    <div class="min-w-0 flex-1 truncate">
                                        <a href="{{ route('clientes.operaciones', $item['cliente']) }}"
                                           title="Ver expediente: {{ $item['cliente']->razon_social }}"
                                           class="block truncate text-xs font-bold text-gray-900 hover:text-brand-600 transition">
                                            {{ $item['cliente']->razon_social }}
                                        </a>
                                        <div class="flex items-center gap-1.5 text-[10px] font-mono text-gray-500">
                                            <span>{{ $item['cliente']->ruc }}</span>
                                            <span class="rounded border px-1 py-0.1 text-[9px] font-bold {{ $badgeColor }}">
                                                {{ $item['cliente']->regimen_tributario?->value }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Porcentaje de avance anual calculado reactivamente --}}
                                <span @class([
                                        'shrink-0 rounded px-1.5 py-0.5 text-[10px] font-extrabold',
                                        'bg-emerald-100 text-emerald-800' => (int) $item['porcentaje'] === 100,
                                        'bg-gray-100 text-gray-700' => (int) $item['porcentaje'] !== 100,
                                    ])
                                      x-effect="pintarAvance($el)"
                                      x-text="calcAvance() + '%'">
                                    {{ $item['porcentaje'] }}%
                                </span>
                            </div>
                        </td>

                        {{-- COLUMNAS 2 A 13: UNA CELDA MAESTRA POR MES (DJ + RH + NOTA) --}}
                        @foreach ($item['meses'] as $idx => $m)
                            <td @class([
                                    'w-[6.5%] p-0.5 border-r border-b text-center transition-colors relative align-middle h-11 border-gray-300',
                                    'bg-gray-100/80 text-gray-400' => ! empty($m['es_baja']) || ($m['estado'] ?? '') === 'Baja',
                                    'bg-emerald-100/70 hover:bg-emerald-100' => empty($m['es_baja']) && ($m['estado'] ?? '') === 'Declarado',
                                    'bg-amber-100/70 hover:bg-amber-100' => empty($m['es_baja']) && in_array($m['estado'] ?? '', ['Procesando', 'Proceso'], true),
                                    'bg-white hover:bg-slate-100/60' => empty($m['es_baja']) && ! in_array($m['estado'] ?? 'Pendiente', ['Declarado', 'Procesando', 'Proceso', 'Baja'], true),
                                ])
                                x-effect="pintarFondo($el, meses[{{ $idx }}])">

                                @if (!empty($m['es_baja']))
                                    <div class="flex items-center justify-center w-full h-full text-center py-1 select-none"
                                         title="{{ $m['motivo_baja'] ?? 'Cliente no activo en este periodo (Baja del Estudio Contable)' }}">
                                         <span class="inline-flex items-center gap-1 rounded bg-rose-50 border border-rose-200 px-1.5 py-0.5 text-[9px] font-bold text-rose-700 shadow-xs">
                                             <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                             Baja
                                         </span>
                                    </div>
                                @else
                                    {{-- Controles DJ + RH limpios y enfocados (Sin comentarios flotantes ni artefactos borrosos) --}}
                                    <div class="flex items-center justify-between gap-1 px-1 w-full h-full relative">

                                        {{-- CONTROL 1: DJ (Declaración Jurada + Control de Deudas) --}}
                                        <div x-data="controlCeldaDJ({{ $item['cliente']->id }}, {{ $idx }}, meses, @js($catalogoTributos), @js($item['cliente']->regimen_tributario?->value), @js(\App\Enums\TipoTributoSunat::rentaDelRegimen($item['cliente']->regimen_tributario)?->value))"
                                             class="relative flex-1 flex items-center justify-center">
                                            <button
                                                type="button"
                                                @click="abrirMenuDJ($el)"
                                                @mouseenter="mostrarHoverDeuda($el)"
                                                @mouseleave="ocultarHoverDeuda()"
                                                :title="!tieneDeudaConSaldo() ? ('DJ: ' + (meses[{{ $idx }}].estado || 'Pendiente')) : null"
                                                class="relative inline-flex h-6 w-6 min-w-6 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs transition-transform select-none hover:scale-110 focus:outline-none active:scale-95"
                                            >
                                                <span x-text="meses[{{ $idx }}].estado === 'Declarado' ? '🟢' : (meses[{{ $idx }}].estado === 'Procesando' ? '🟡' : '⚪')">
                                                    {{ match ($m['estado'] ?? 'Pendiente') { 'Declarado' => '🟢', 'Procesando' => '🟡', default => '⚪' } }}
                                                </span>
                                                <svg
                                                    x-show="tieneDeudaDeclarada() || deudaPagadaDeclarada()"
                                                    x-cloak
                                                    class="absolute inset-0 h-6 w-6 pointer-events-none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle cx="12" cy="12" r="6.8" fill="none" stroke-width="2.4" :stroke="tieneDeudaDeclarada() ? '#ef4444' : '#7c3aed'" />
                                                </svg>
                                            </button>

                                            {{-- Ventana flotante inteligente de información de deuda al pasar el mouse (sin hacer clic) --}}
                                            <div
                                                x-ref="tooltipDeuda"
                                                x-show="hoverDeuda && tieneDeudaConSaldo() && !openDJ"
                                                x-cloak
                                                data-menu-flotante
                                                class="fixed z-50 pointer-events-none rounded-2xl bg-white text-gray-800 p-3.5 shadow-2xl border border-gray-200 text-left min-w-[320px] max-w-sm ring-1 ring-black/5"
                                                style="background-color: #ffffff !important; opacity: 1 !important;"
                                            >
                                                {{-- Header: Estado de deuda y periodo --}}
                                                <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-gray-100">
                                                    <div class="flex items-center gap-1.5 rounded-full bg-rose-50 border border-rose-200/80 px-2.5 py-0.5 text-[11px] font-bold text-rose-700">
                                                        <span class="inline-block h-2 w-2 rounded-full bg-rose-600 animate-pulse"></span>
                                                        <span>Deuda SUNAT Pendiente</span>
                                                    </div>
                                                    <span class="text-[10px] font-mono font-bold text-gray-600 bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5" x-text="meses[{{ $idx }}].periodo"></span>
                                                </div>

                                                <div class="space-y-1.5 text-xs leading-normal">
                                                    <template x-for="deudaTip in deudasPorPagar()" :key="deudaTip.localKey || deudaTip.id || deudaTip.tributo_codigo">
                                                        <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-2.5 py-1.5">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <span class="font-bold text-gray-900 truncate" x-text="deudaTip.tributo_nombre_corto || deudaTip.tributo_nombre || 'Tributo'"></span>
                                                                <span class="font-mono font-bold text-rose-600 shrink-0" x-text="formatearMoneda(deudaTip.saldo_pendiente)"></span>
                                                            </div>
                                                            <div class="flex items-center justify-between text-[10px] text-gray-500 mt-0.5">
                                                                <span x-text="'Deuda ' + formatearMoneda(deudaTip.monto_deuda_inicial)"></span>
                                                                <span class="inline-flex items-center gap-1">
                                                                    <template x-if="deudaTip.es_igv_justo">
                                                                        <span class="font-bold text-indigo-700">IGV Justo</span>
                                                                    </template>
                                                                    <span class="font-mono" x-text="deudaTip.vencimiento_deuda_dmy || deudaTip.vencimiento_deuda || 'Sin fecha'"></span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                {{-- Footer: Saldo Pendiente --}}
                                                <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between rounded-xl bg-rose-50/70 border border-rose-100 p-2.5">
                                                    <span class="text-xs font-bold text-rose-900">Saldo Pendiente:</span>
                                                    <span class="text-rose-600 font-mono font-black text-sm" x-text="formatearMoneda(meses[{{ $idx }}].saldo_pendiente)"></span>
                                                </div>
                                            </div>

                                            {{-- Miniventana flotante de DJ y tributos. El ancho deja aire entre la leyenda IGV Justo y su interruptor. --}}
                                            <div
                                                x-ref="menuDJ"
                                                x-show="openDJ"
                                                x-cloak
                                                data-menu-flotante
                                                @click.outside="openDJ = false"
                                                class="fixed z-50 max-w-[95vw] rounded-2xl border border-gray-200 bg-white p-3.5 shadow-2xl text-left max-h-[calc(100vh-24px)] overflow-y-auto"
                                                style="width: 476px;"
                                            >
                                                {{-- Encabezado con Tabs en una sola línea --}}
                                                <div class="border-b border-gray-100 pb-2.5 mb-3">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-2 truncate pr-2">
                                                            <span class="inline-flex items-center justify-center rounded bg-gray-100 border border-gray-200 px-1.5 py-0.5 text-[10px] font-mono font-bold text-gray-700">
                                                                {{ $m['corto'] }}-{{ substr($anio, -2) }}
                                                            </span>
                                                            <span class="text-xs font-bold text-gray-900 truncate">
                                                                {{ $item['cliente']->razon_social }}
                                                            </span>
                                                        </div>
                                                        <button type="button" @click="openDJ = false" class="text-gray-400 hover:text-gray-700 text-base font-bold leading-none p-1">&times;</button>
                                                    </div>

                                                    {{-- 2 Pestañas perfectamente alineadas en una sola línea --}}
                                                    <div class="flex border-b border-gray-200 text-xs font-semibold">
                                                        <button
                                                            type="button"
                                                            @click="activeTab = 'declaracion'"
                                                            :class="activeTab === 'declaracion' ? 'border-b-2 border-brand-600 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700'"
                                                            class="flex-1 pb-2 text-center transition flex items-center justify-center gap-1.5 whitespace-nowrap"
                                                        >
                                                            <span>Estado Declaración</span>
                                                            <span class="text-[10px]" x-text="meses[mesIdx].estado === 'Declarado' ? '🟢' : (meses[mesIdx].estado === 'Procesando' ? '🟡' : '⚪')"></span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="activeTab = 'deuda'; if (!deudaCargada) cargarDeuda()"
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

                                                {{-- TAB 1: ESTADO DECLARACIÓN --}}
                                                <div x-show="activeTab === 'declaracion'" class="space-y-1.5 py-1">
                                                    <button
                                                        type="button"
                                                        @click="cambiarEstado('Pendiente')"
                                                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition hover:bg-slate-100 text-gray-800"
                                                        :class="meses[mesIdx].estado === 'Pendiente' ? 'bg-slate-100 text-slate-900 ring-1 ring-slate-300' : ''"
                                                    >
                                                        <div class="flex items-center gap-2.5">
                                                            <span class="text-sm">⚪</span>
                                                            <span>Pendiente</span>
                                                        </div>
                                                        <span class="text-[10px] text-gray-400">Sin iniciar</span>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        @click="cambiarEstado('Procesando')"
                                                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition hover:bg-amber-50 text-gray-800"
                                                        :class="meses[mesIdx].estado === 'Procesando' ? 'bg-amber-50 text-amber-900 ring-1 ring-amber-300' : ''"
                                                    >
                                                        <div class="flex items-center gap-2.5">
                                                            <span class="text-sm">🟡</span>
                                                            <span>En Proceso</span>
                                                        </div>
                                                        <span class="text-[10px] text-amber-600 font-medium">Revisando libros / SIRE</span>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        @click="cambiarEstado('Declarado')"
                                                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition hover:bg-emerald-50 text-gray-800"
                                                        :class="meses[mesIdx].estado === 'Declarado' ? 'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-300' : ''"
                                                    >
                                                        <div class="flex items-center gap-2.5">
                                                            <span class="text-sm">🟢</span>
                                                            <span>Declarado</span>
                                                        </div>
                                                        <span class="text-[10px] text-emerald-600 font-medium">Constancia generada</span>
                                                    </button>
                                                </div>

                                                {{-- TAB 2: TRIBUTOS Y PAGOS (PDT 621 + PDT 601) --}}
                                                <div x-show="activeTab === 'deuda'" class="space-y-3 py-1">
                                                    <p class="text-[11px] leading-snug text-gray-500">
                                                        Digite lo que salió a pagar en cada declaración. Los pagos siguen aquí. Solo el saldo por pagar entra a Control de Deudas.
                                                    </p>

                                                    <div class="grid grid-cols-3 gap-2">
                                                        <div class="rounded-xl bg-gray-50 border border-gray-200 p-2 text-center">
                                                            <p class="text-[9px] font-bold uppercase text-gray-500 tracking-wider">A pagar</p>
                                                            <p class="text-xs font-black text-gray-800 mt-0.5" x-text="formatearMoneda(meses[mesIdx].monto_deuda_inicial)"></p>
                                                        </div>
                                                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-2 text-center">
                                                            <p class="text-[9px] font-bold uppercase text-emerald-600 tracking-wider">Pagado</p>
                                                            <p class="text-xs font-black text-emerald-600 mt-0.5" x-text="formatearMoneda(meses[mesIdx].total_abonado)"></p>
                                                        </div>
                                                        <div class="rounded-xl p-2 text-center border"
                                                             :class="meses[mesIdx].saldo_pendiente > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'">
                                                            <p class="text-[9px] font-bold uppercase tracking-wider"
                                                               :class="meses[mesIdx].saldo_pendiente > 0 ? 'text-red-600' : 'text-gray-500'">Por pagar</p>
                                                            <p class="text-xs font-black mt-0.5"
                                                               :class="meses[mesIdx].saldo_pendiente > 0 ? 'text-red-600' : 'text-gray-600'"
                                                               x-text="formatearMoneda(meses[mesIdx].saldo_pendiente)"></p>
                                                        </div>
                                                    </div>

                                                    <template x-for="bloque in declaraciones" :key="bloque.id">
                                                        <section class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                                                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2">
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

                                                                <button type="button"
                                                                        x-show="tributosLibres(bloque).length && !borradorSinTributo(bloque.id)"
                                                                        @click="agregarTributo('', bloque.id)"
                                                                        class="flex w-full items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white px-2 py-1.5 text-[11px] font-bold text-slate-600 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700">
                                                                    + Nuevo tributo
                                                                </button>

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
                                                                                <select x-model="deuda.borrador_codigo" @change="alCambiarTributo(deuda)" class="w-full rounded-lg border-gray-300 py-1.5 px-2.5 text-xs text-gray-800 bg-white shadow-xs focus:ring-2 focus:ring-brand-500 focus:border-brand-500 font-medium">
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
                                                                                   class="w-full rounded-lg border-gray-300 py-1.5 px-2.5 text-xs font-semibold font-mono text-gray-800 bg-white" placeholder="0.00">
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
                                                                                   class="w-full rounded-lg border-gray-300 py-1.5 px-2.5 text-xs bg-white"
                                                                                   :class="igvJustoEnEdicion(deuda) ? 'bg-indigo-50 border-indigo-200 text-indigo-900 cursor-not-allowed font-bold' : ''">
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
                                                                                            <td class="py-1 px-2 text-gray-500 truncate max-w-[120px]" x-text="abono.observacion || '—'"></td>
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
                                                                                <input type="text" inputmode="decimal" x-model="deuda.nuevoMonto" @input="deuda.nuevoMonto = formatearMilesInput($event)" placeholder="Monto del pago" class="rounded-lg border-gray-300 py-1.5 px-2 text-xs font-mono bg-white">
                                                                                <input type="date" x-model="deuda.nuevaFecha" class="rounded-lg border-gray-300 py-1.5 px-2 text-xs bg-white">
                                                                            </div>
                                                                            <input type="text" x-model="deuda.nuevaObs" placeholder="N° operación o nota" class="w-full rounded-lg border-gray-300 py-1.5 px-2 text-xs bg-white">
                                                                            <button type="button" @click="registrarAbono(deuda)" :disabled="deuda.savingAbono || limpiarMoneda(deuda.nuevoMonto) <= 0"
                                                                                    class="w-full py-1.5 rounded-lg bg-emerald-600 text-white font-bold text-xs disabled:opacity-50"
                                                                                    x-text="deuda.savingAbono ? 'Registrando...' : (deuda.abonoGuardado ? '¡Pago registrado!' : '+ Registrar pago')"></button>
                                                                        </div>
                                                                    </div>
                                                                    <p x-show="!deuda.id" class="text-[10px] text-gray-500">Guarde el tributo para registrar sus pagos.</p>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </section>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Separador vertical micro --}}
                                        <div class="h-3.5 w-px bg-gray-300/60 shrink-0"></div>

                                        {{-- CONTROL 2: RH (Recibo por Honorarios) --}}
                                        <div x-data="{ openRH: false }" class="relative flex-1 flex items-center justify-center">
                                            <button
                                                type="button"
                                                @click="openRH = !openRH; if (openRH) $nextTick(() => window.ubicarMenuFlotante($refs.menuRH, $el))"
                                                :title="'RH: ' + (meses[{{ $idx }}].honorarios === 'Pagado' ? 'Pagado' : 'Debe')"
                                                class="inline-flex h-6 w-6 min-w-6 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs transition-transform select-none hover:scale-110 focus:outline-none active:scale-95"
                                            >
                                                <span x-text="meses[{{ $idx }}].honorarios === 'Pagado' ? '🟢' : '⚪'">
                                                    {{ ($m['honorarios'] ?? '') === 'Pagado' ? '🟢' : '⚪' }}
                                                </span>
                                            </button>

                                            <div
                                                x-ref="menuRH"
                                                x-show="openRH"
                                                x-cloak
                                                data-menu-flotante
                                                @click.outside="openRH = false"
                                                class="fixed z-50 w-40 rounded-xl border border-gray-200 bg-white p-2 shadow-2xl text-left"
                                            >
                                                <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 mb-1">
                                                    Honorarios ({{ $m['corto'] }})
                                                </div>
                                                <div class="space-y-1">
                                                    <button
                                                        type="button"
                                                        @click="meses[{{ $idx }}].honorarios = 'Pendiente'; actualizarHonorarios({{ $idx }}); openRH = false"
                                                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-slate-100 text-gray-800"
                                                        :class="meses[{{ $idx }}].honorarios !== 'Pagado' ? 'bg-slate-100 text-slate-800 ring-1 ring-slate-300' : ''"
                                                    >
                                                        <span class="text-sm">⚪</span>
                                                        <span>Debe</span>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        @click="meses[{{ $idx }}].honorarios = 'Pagado'; actualizarHonorarios({{ $idx }}); openRH = false"
                                                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-emerald-50 text-gray-800"
                                                        :class="meses[{{ $idx }}].honorarios === 'Pagado' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : ''"
                                                    >
                                                        <span class="text-sm">🟢</span>
                                                        <span>Pagó</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        @endforeach

                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="px-6 py-12 text-center text-gray-500">
                            No hay clientes activos para mostrar en la sábana del ejercicio fiscal {{ $anio }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pie de Sábana --}}
    <div class="mt-3 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-400 gap-2 px-1">
        <p>Mostrando {{ $totalClientes }} clientes activos &bull; 13 columnas optimizadas para 100% de pantalla (DJ: Declaración Jurada &bull; RH: Recibo por Honorarios).</p>
        <p>Los cambios en los estados, honorarios y notas se guardan automáticamente por AJAX en tiempo real.</p>
    </div>

    {{-- ═══════════════════════════════ SCRIPTS ALPINE.JS ═══════════════════════════════ --}}
    <script>
        function filaClienteMatriz(config) {
            return {
                clienteId: config.clienteId,
                anio: config.anio,
                meses: config.meses,

                fondosCelda: [
                    'bg-gray-100/80', 'text-gray-400',
                    'bg-emerald-100/70', 'hover:bg-emerald-100',
                    'bg-amber-100/70', 'hover:bg-amber-100',
                    'bg-white', 'hover:bg-slate-100/60'
                ],
                fondosAvance: ['bg-emerald-100', 'text-emerald-800', 'bg-gray-100', 'text-gray-700'],

                bgClass(mes) {
                    if (!mes) return 'bg-white hover:bg-slate-100/60';
                    if (mes.es_baja || mes.estado === 'Baja') {
                        return 'bg-gray-100/80 text-gray-400';
                    }
                    if (mes.estado === 'Declarado') {
                        return 'bg-emerald-100/70 hover:bg-emerald-100';
                    }
                    if (mes.estado === 'Procesando' || mes.estado === 'Proceso') {
                        return 'bg-amber-100/70 hover:bg-amber-100';
                    }
                    return 'bg-white hover:bg-slate-100/60';
                },

                pintarFondo(el, mes) {
                    this.fondosCelda.forEach((clase) => el.classList.remove(clase));
                    this.bgClass(mes).split(' ').forEach((clase) => {
                        if (clase.trim()) el.classList.add(clase.trim());
                    });
                },

                pintarAvance(el) {
                    this.fondosAvance.forEach((clase) => el.classList.remove(clase));
                    const clases = this.calcAvance() === 100
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-gray-100 text-gray-700';
                    clases.split(' ').forEach((clase) => el.classList.add(clase));
                },

                calcAvance() {
                    let declaradas = 0;
                    let exigibles = 0;
                    this.meses.forEach(m => {
                        if (m.estado === 'Baja' || m.es_baja) return;
                        exigibles++;
                        if (m.estado === 'Declarado') declaradas++;
                    });
                    return exigibles > 0 ? Math.round((declaradas / exigibles) * 100) : 100;
                },

                async actualizarEstadoMaestro(idx) {
                    const mes = this.meses[idx];
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('control.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: mes.periodo,
                                estado: mes.estado
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar estado.');
                        }
                        if (data.kpis) {
                            window.dispatchEvent(new CustomEvent('kpis-actualizados', { detail: data.kpis }));
                        }
                    } catch (err) {
                        console.error('Error al actualizar estado:', err);
                        alert('No se pudo actualizar el estado: ' + err.message);
                    }
                },

                async actualizarHonorarios(idx) {
                    const mes = this.meses[idx];
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('control.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: mes.periodo,
                                honorarios: mes.honorarios
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar honorarios.');
                        }
                        if (data.kpis) {
                            window.dispatchEvent(new CustomEvent('kpis-actualizados', { detail: data.kpis }));
                        }
                    } catch (err) {
                        console.error('Error al actualizar honorarios:', err);
                        alert('No se pudo actualizar honorarios: ' + err.message);
                    }
                }
            };
        }

        function controlCeldaDJ(clienteId, mesIdx, meses, declaraciones, regimen, rentaSugerida) {
            return {
                clienteId: clienteId,
                mesIdx: mesIdx,
                meses: meses,
                declaraciones: declaraciones || [],
                regimen: regimen || '',
                rentaSugerida: rentaSugerida || '',
                openDJ: false,
                hoverDeuda: false,
                activeTab: 'declaracion',
                tieneDeuda: !!meses[mesIdx].tiene_deuda,
                deudas: [],
                deudaAbiertaKey: null,
                tributoCodigo: meses[mesIdx].tributo_codigo || '1011',
                montoDeuda: '',
                vencimientoDeuda: meses[mesIdx].vencimiento_deuda || '',
                vencimientoOficialPdt: meses[mesIdx].vencimiento_oficial_pdt || '',
                editandoFechaManual: false,
                esIgvJusto: !!meses[mesIdx].es_igv_justo,
                calculandoIgv: false,
                igvJustoMensaje: '',
                cacheDeuda: null,
                deudaDescartada: false,
                deudaCargada: false,
                savingDeuda: false,
                guardadoDeudaExitoso: false,
                savingAbono: false,
                abonoGuardadoExitoso: false,
                abonos: [],
                nuevoMonto: '',
                nuevaFecha: new Date().toISOString().split('T')[0],
                nuevaObs: '',

                init() {
                    this.hidratarDeudas(this.meses[this.mesIdx].deudas || []);
                    this.abonos = this.meses[this.mesIdx].abonos || [];
                    this.vencimientoOficialPdt = this.meses[this.mesIdx].vencimiento_oficial_pdt || '';
                    if (this.meses[this.mesIdx].monto_deuda_inicial) {
                        this.montoDeuda = this.formatearNumeroConComas(this.meses[this.mesIdx].monto_deuda_inicial);
                    }
                    if (!this.vencimientoDeuda && !this.esIgvJusto && this.vencimientoOficialPdt) {
                        this.vencimientoDeuda = this.vencimientoOficialPdt;
                    }
                    if (this.vencimientoDeuda && this.vencimientoOficialPdt && this.vencimientoDeuda !== this.vencimientoOficialPdt && !this.esIgvJusto) {
                        this.editandoFechaManual = true;
                    }
                    const mesInicial = this.meses[this.mesIdx];
                    if (mesInicial.tiene_deuda && (mesInicial.monto_deuda_inicial || (this.abonos && this.abonos.length > 0))) {
                        this.cacheDeuda = {
                            montoDeuda: this.montoDeuda,
                            monto_deuda_inicial: parseFloat(this.meses[this.mesIdx].monto_deuda_inicial || 0),
                            vencimientoDeuda: this.vencimientoDeuda,
                            vencimiento_deuda_dmy: this.meses[this.mesIdx].vencimiento_deuda_dmy || '',
                            vencimiento_oficial_pdt: this.vencimientoOficialPdt,
                            tributoCodigo: this.meses[this.mesIdx].tributo_codigo || '1011',
                            esIgvJusto: !!this.meses[this.mesIdx].es_igv_justo,
                            editandoFechaManual: this.editandoFechaManual,
                            total_abonado: parseFloat(this.meses[this.mesIdx].total_abonado || 0),
                            saldo_pendiente: parseFloat(this.meses[this.mesIdx].saldo_pendiente || 0),
                            abonos: [...this.abonos]
                        };
                    } else if (!mesInicial.tiene_deuda && (mesInicial.monto_deuda_inicial || (this.abonos && this.abonos.length > 0))) {
                        this.descartarDeudaAlCerrar();
                    }
                    this.$watch('openDJ', (abierto) => {
                        if (!abierto) {
                            this.deudas.forEach((deuda) => this.revertirCampos(deuda));
                            this.deudas = this.deudas.filter((deuda) => this.deudaPersistida(deuda));
                        }
                    });
                    this.$watch('deudaAbiertaKey', (nueva, anterior) => {
                        if (!anterior || anterior === nueva) {
                            return;
                        }
                        const previa = this.deudas.find((deuda) => deuda.localKey === anterior);
                        if (previa) {
                            this.revertirCampos(previa);
                        }
                    });
                    this.$watch('tieneDeuda', () => {
                        this.reubicarMenuDeudaCompleto();
                    });
                    this.$watch('activeTab', () => {
                        this.$nextTick(() => {
                            if (this.$refs.menuDJ && this.$refs.menuDJ._anclaMenu) {
                                window.ubicarMenuFlotante(this.$refs.menuDJ, this.$refs.menuDJ._anclaMenu);
                            }
                        });
                    });
                },

                formatearMoneda(val) {
                    const n = parseFloat(val || 0);
                    return 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

                tieneDeudaConSaldo() {
                    const m = this.meses[this.mesIdx];
                    return !!m.tiene_deuda && parseFloat(m.saldo_pendiente ?? m.monto_deuda_inicial ?? 0) > 0;
                },

                tieneDeudaDeclarada() {
                    const m = this.meses[this.mesIdx];
                    return m.estado === 'Declarado' && this.tieneDeudaConSaldo();
                },

                deudaPagadaDeclarada() {
                    const m = this.meses[this.mesIdx];
                    if (m.estado !== 'Declarado' || !m.tiene_deuda || this.tieneDeudaConSaldo()) {
                        return false;
                    }
                    return parseFloat(m.total_abonado ?? 0) > 0;
                },

                mostrarHoverDeuda(el) {
                    if (!this.tieneDeudaConSaldo() || this.openDJ) {
                        return;
                    }
                    this.hoverDeuda = true;
                    this.$nextTick(() => {
                        if (this.$refs.tooltipDeuda) {
                            window.ubicarMenuFlotante(this.$refs.tooltipDeuda, el);
                        }
                    });
                },

                ocultarHoverDeuda() {
                    this.hoverDeuda = false;
                },

                abrirMenuDJ(ancla) {
                    this.hoverDeuda = false;
                    this.openDJ = !this.openDJ;
                    if (this.openDJ) {
                        this.sincronizarDesdeMes();
                        this.$nextTick(() => window.ubicarMenuFlotante(this.$refs.menuDJ, ancla));
                        if (this.tieneDeuda && !this.deudaCargada) {
                            this.cargarDeuda();
                        }
                    }
                },

                abrirMenuDJConTab(ancla, tabName) {
                    this.hoverDeuda = false;
                    this.activeTab = tabName;
                    this.openDJ = true;
                    this.sincronizarDesdeMes();
                    this.$nextTick(() => window.ubicarMenuFlotante(this.$refs.menuDJ, ancla));
                    this.cargarDeuda();
                },

                sincronizarDesdeMes() {
                    const m = this.meses[this.mesIdx];
                    this.vencimientoOficialPdt = m.vencimiento_oficial_pdt || '';
                    this.tieneDeuda = !!m.tiene_deuda;
                    this.tributoCodigo = m.tributo_codigo || '1011';
                    this.esIgvJusto = !!m.es_igv_justo;
                    if (m.monto_deuda_inicial) {
                        this.montoDeuda = this.formatearNumeroConComas(m.monto_deuda_inicial);
                    } else if (this.cacheDeuda && this.cacheDeuda.montoDeuda) {
                        this.montoDeuda = this.cacheDeuda.montoDeuda;
                    }
                    this.vencimientoDeuda = m.vencimiento_deuda || (this.cacheDeuda?.vencimientoDeuda || '');
                    if (!this.vencimientoDeuda && !this.esIgvJusto && this.vencimientoOficialPdt) {
                        this.vencimientoDeuda = this.vencimientoOficialPdt;
                    }
                    if (this.vencimientoDeuda && this.vencimientoOficialPdt && this.vencimientoDeuda !== this.vencimientoOficialPdt && !this.esIgvJusto) {
                        this.editandoFechaManual = true;
                    } else if (this.cacheDeuda?.editandoFechaManual !== undefined) {
                        this.editandoFechaManual = !!this.cacheDeuda.editandoFechaManual;
                    } else {
                        this.editandoFechaManual = false;
                    }
                },

                tooltipDJ() {
                    if (this.tieneDeudaConSaldo()) {
                        return null;
                    }
                    const m = this.meses[this.mesIdx];
                    return 'DJ: ' + (m.estado || 'Pendiente');
                },

                async cambiarEstado(nuevoEstado) {
                    this.meses[this.mesIdx].estado = nuevoEstado;
                    if (typeof this.actualizarEstadoMaestro === 'function') {
                        this.actualizarEstadoMaestro(this.mesIdx);
                    }
                    this.openDJ = false;
                },

                hayTributoGuardado() {
                    const locales = (this.deudas || []).some((deuda) => !!deuda.id);
                    const delMes = (this.meses[this.mesIdx].deudas || []).some((deuda) => !!deuda.id);
                    return locales || delMes;
                },

                reubicarMenuDeudaCompleto() {
                    const menu = this.$refs.menuDJ;
                    if (!menu || !menu._anclaMenu) {
                        return;
                    }
                    menu._reposicionPendiente = true;
                    menu.style.visibility = 'hidden';
                    let intentos = 0;
                    const mostrar = () => {
                        const formulario = menu.querySelector('[data-deuda-formulario]');
                        const formularioVisible = formulario && getComputedStyle(formulario).display !== 'none';
                        const listo = !this.tieneDeuda || formularioVisible;
                        if (!listo && intentos < 8) {
                            intentos += 1;
                            requestAnimationFrame(mostrar);
                            return;
                        }
                        requestAnimationFrame(() => {
                            menu._reposicionPendiente = false;
                            window.ubicarMenuFlotante(menu, menu._anclaMenu);
                        });
                    };
                    this.$nextTick(() => requestAnimationFrame(mostrar));
                },

                revertirDeudaNoGuardada() {
                    if (this.hayTributoGuardado()) {
                        this.deudas = this.deudas.filter((deuda) => !!deuda.id);
                        return;
                    }

                    const persistido = !!this.meses[this.mesIdx].tiene_deuda;
                    this.tieneDeuda = false;
                    this.meses[this.mesIdx].tiene_deuda = false;
                    this.deudas = [];
                    this.deudaAbierta = -1;
                    if (persistido) {
                        this.sincronizarToggleDeuda(false);
                    }
                },

                async toggleTieneDeuda() {
                    const activar = !this.tieneDeuda;

                    if (!activar) {
                        this.tieneDeuda = false;
                        this.meses[this.mesIdx].tiene_deuda = false;

                        const montoLimpio = this.limpiarMoneda(this.montoDeuda);
                        if (montoLimpio > 0 || this.vencimientoDeuda || (this.abonos && this.abonos.length > 0)) {
                            this.cacheDeuda = {
                                montoDeuda: this.montoDeuda,
                                monto_deuda_inicial: montoLimpio,
                                vencimientoDeuda: this.vencimientoDeuda,
                                vencimiento_deuda_dmy: this.meses[this.mesIdx].vencimiento_deuda_dmy || '',
                                vencimiento_oficial_pdt: this.vencimientoOficialPdt,
                                tributoCodigo: this.tributoCodigo,
                                esIgvJusto: this.esIgvJusto,
                                editandoFechaManual: this.editandoFechaManual,
                                total_abonado: this.meses[this.mesIdx].total_abonado || 0,
                                saldo_pendiente: this.meses[this.mesIdx].saldo_pendiente || 0,
                                abonos: [...(this.abonos || [])]
                            };
                        }

                        await this.sincronizarToggleDeuda(false);
                        return;
                    }

                    this.deudaDescartada = false;
                    if (this.cacheDeuda) {
                        this.montoDeuda = this.cacheDeuda.montoDeuda || '';
                        this.vencimientoDeuda = this.cacheDeuda.vencimientoDeuda || '';
                        this.tributoCodigo = this.cacheDeuda.tributoCodigo || '1011';
                        this.esIgvJusto = !!this.cacheDeuda.esIgvJusto;
                        this.editandoFechaManual = !!this.cacheDeuda.editandoFechaManual;
                        this.abonos = [...(this.cacheDeuda.abonos || [])];
                        this.meses[this.mesIdx].abonos = this.abonos;
                        this.meses[this.mesIdx].monto_deuda_inicial = this.cacheDeuda.monto_deuda_inicial;
                        this.meses[this.mesIdx].vencimiento_deuda = this.cacheDeuda.vencimientoDeuda;
                        this.meses[this.mesIdx].vencimiento_deuda_dmy = this.cacheDeuda.vencimiento_deuda_dmy;
                        this.meses[this.mesIdx].total_abonado = this.cacheDeuda.total_abonado;
                        this.meses[this.mesIdx].saldo_pendiente = this.cacheDeuda.saldo_pendiente;
                        this.meses[this.mesIdx].tributo_codigo = this.tributoCodigo;
                        this.meses[this.mesIdx].es_igv_justo = this.esIgvJusto;
                    }

                    if (!this.vencimientoDeuda && !this.esIgvJusto && this.vencimientoOficialPdt) {
                        this.vencimientoDeuda = this.vencimientoOficialPdt;
                    }

                    if (this.deudas.length === 0) {
                        this.agregarTributo();
                    }

                    this.tieneDeuda = true;
                    if (this.hayTributoGuardado()) {
                        this.meses[this.mesIdx].tiene_deuda = true;
                        await this.sincronizarToggleDeuda(true);
                    }

                    if (!this.cacheDeuda && !this.deudaCargada) {
                        await this.cargarDeuda();
                        if (this.deudas.length === 0) {
                            this.agregarTributo();
                        }
                    }
                },

                descartarDeudaAlCerrar(keepalive = false) {
                    if (this.tieneDeuda || this.deudaDescartada) {
                        return;
                    }

                    const mes = this.meses[this.mesIdx];
                    const hayDatos = !!(this.cacheDeuda || mes.monto_deuda_inicial || (mes.deudas && mes.deudas.length > 0) || this.deudas.length || (mes.abonos && mes.abonos.length > 0) || (this.abonos && this.abonos.length > 0));
                    if (!hayDatos) {
                        return;
                    }

                    this.deudaDescartada = true;
                    this.cacheDeuda = null;
                    this.abonos = [];
                    this.montoDeuda = '';
                    this.esIgvJusto = false;
                    this.editandoFechaManual = false;
                    this.vencimientoDeuda = (!this.esIgvJusto && this.vencimientoOficialPdt) ? this.vencimientoOficialPdt : '';
                    mes.tiene_deuda = false;
                    mes.monto_deuda_inicial = 0;
                    mes.vencimiento_deuda = '';
                    mes.vencimiento_deuda_dmy = '';
                    mes.abonos = [];
                    mes.total_abonado = 0;
                    mes.saldo_pendiente = 0;
                    mes.es_igv_justo = false;
                    mes.tributo_codigo = mes.tributo_codigo || '1011';
                    mes.deudas = [];
                    this.deudas = [];

                    const tokenEl = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                    window.fetch('{{ route('control.update') }}', {
                        method: 'POST',
                        keepalive: keepalive,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            cliente_id: this.clienteId,
                            periodo: mes.periodo,
                            tiene_deuda: false,
                            descartar_deuda: true,
                        }),
                    }).catch(function () {});
                },

                async sincronizarToggleDeuda(activo) {
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const montoLimpio = this.limpiarMoneda(this.montoDeuda);

                        const response = await fetch('{{ route('control.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: this.meses[this.mesIdx].periodo,
                                tiene_deuda: activo,
                                tributo_codigo: this.tributoCodigo || '1011',
                                es_igv_justo: this.tributoCodigo === '1011' ? this.esIgvJusto : false,
                                monto_deuda_inicial: montoLimpio > 0 ? montoLimpio : (this.cacheDeuda?.monto_deuda_inicial ?? null),
                                vencimiento_deuda: this.vencimientoDeuda || (this.cacheDeuda?.vencimientoDeuda || null),
                            }),
                        });

                        const data = await response.json();
                        if (this.deudaDescartada && !activo) {
                            return;
                        }
                        if (data.operacion_id) {
                            this.meses[this.mesIdx].operacion_id = data.operacion_id;
                        }
                        this.meses[this.mesIdx].tiene_deuda = !!data.tiene_deuda;
                        if (data.tributo_codigo) this.meses[this.mesIdx].tributo_codigo = data.tributo_codigo;
                        if (data.tributo_nombre) this.meses[this.mesIdx].tributo_nombre = data.tributo_nombre;
                        if (data.tributo_nombre_corto) this.meses[this.mesIdx].tributo_nombre_corto = data.tributo_nombre_corto;
                        if (data.tributo_etiqueta) this.meses[this.mesIdx].tributo_etiqueta = data.tributo_etiqueta;
                        this.meses[this.mesIdx].es_igv_justo = !!data.es_igv_justo;
                        if (data.monto_deuda_inicial !== undefined && data.monto_deuda_inicial !== null) {
                            this.meses[this.mesIdx].monto_deuda_inicial = parseFloat(data.monto_deuda_inicial || 0);
                        }
                        if (data.vencimiento_deuda) this.meses[this.mesIdx].vencimiento_deuda = data.vencimiento_deuda;
                        if (data.vencimiento_deuda_dmy) this.meses[this.mesIdx].vencimiento_deuda_dmy = data.vencimiento_deuda_dmy;
                        if (data.total_abonado !== undefined) this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado || 0);
                        if (data.saldo_pendiente !== undefined) this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente || 0);
                        if (data.abonos) {
                            this.abonos = data.abonos;
                            this.meses[this.mesIdx].abonos = this.abonos;
                        }

                        if (activo && !this.montoDeuda && data.monto_deuda_inicial) {
                            this.montoDeuda = this.formatearNumeroConComas(data.monto_deuda_inicial);
                        }
                    } catch (err) {
                        console.error('Error al sincronizar estado de deuda:', err);
                    }
                },

                deudasPorPagar() {
                    return (this.meses[this.mesIdx].deudas || []).filter((deuda) => parseFloat(deuda.saldo_pendiente || 0) > 0);
                },

                declaracionDeCodigo(codigo) {
                    for (const bloque of this.declaraciones || []) {
                        if ((bloque.tributos || []).some((tributo) => tributo.codigo === codigo)) {
                            return bloque.id;
                        }
                    }
                    if (codigo === '3041' || codigo === '3051') {
                        return 'pdt_601';
                    }
                    return 'pdt_621';
                },

                declaracionDe(deuda) {
                    if (deuda && deuda.declaracion) {
                        return deuda.declaracion;
                    }
                    return this.declaracionDeCodigo(deuda?.tributo_codigo);
                },

                deudasDelBloque(id) {
                    return this.deudas.filter((deuda) => this.declaracionDe(deuda) === id);
                },

                resumenBloque(id) {
                    const filas = this.deudasDelBloque(id).filter((deuda) => !!deuda.id);
                    const saldo = filas.reduce((suma, deuda) => suma + (parseFloat(deuda.saldo_pendiente) || 0), 0);
                    return { hay: filas.length > 0, saldo: Math.round(saldo * 100) / 100 };
                },

                tributosLibres(bloque) {
                    return (bloque.tributos || []).filter((tributo) => !this.codigoUsado(tributo.codigo));
                },

                borradorSinTributo(declaracionId) {
                    return this.deudas.some((deuda) => !deuda.tributo_codigo && this.declaracionDe(deuda) === declaracionId);
                },

                etiquetaTributo(opcion) {
                    if (!opcion) {
                        return '';
                    }
                    if (opcion.codigo === this.rentaSugerida) {
                        return opcion.etiqueta + ' · régimen';
                    }
                    return opcion.etiqueta;
                },

                codigoUsado(codigo) {
                    return this.deudas.some((deuda) => {
                        if (this.deudaPersistida(deuda)) {
                            return deuda.tributo_codigo === codigo;
                        }
                        return deuda.borrador_codigo === codigo;
                    });
                },

                deudaPersistida(deuda) {
                    return !!deuda.id || parseFloat(deuda.monto_deuda_inicial || 0) > 0 || (deuda.abonos || []).length > 0;
                },

                nombreTributo(codigo) {
                    for (const bloque of this.declaraciones || []) {
                        const tributo = (bloque.tributos || []).find((item) => item.codigo === codigo);
                        if (tributo) {
                            return tributo.nombre_corto || tributo.nombre;
                        }
                    }
                    return 'Tributo';
                },

                tributoUsadoPorOtra(codigo, deuda) {
                    return this.deudas.some((otra) => {
                        if (otra === deuda || !codigo) {
                            return false;
                        }
                        if (this.deudaPersistida(otra)) {
                            return otra.tributo_codigo === codigo;
                        }
                        return otra.borrador_codigo === codigo;
                    });
                },

                igvJustoEnEdicion(deuda) {
                    return !!deuda.borrador_igv && deuda.borrador_codigo === '1011';
                },

                prepararBorrador(deuda) {
                    deuda.borrador_codigo = deuda.tributo_codigo || '';
                    deuda.borrador_monto = deuda.monto || '';
                    deuda.borrador_vencimiento = deuda.vencimiento || '';
                    deuda.borrador_igv = !!deuda.es_igv_justo;
                },

                revertirCampos(deuda) {
                    if (!this.deudaPersistida(deuda)) {
                        return;
                    }
                    this.prepararBorrador(deuda);
                },

                normalizarDeuda(raw) {
                    const codigo = raw && Object.prototype.hasOwnProperty.call(raw, 'tributo_codigo')
                        ? (raw.tributo_codigo || '')
                        : '1011';
                    const deuda = {
                        id: raw?.id || null,
                        localKey: raw?.localKey || ('t-' + (raw?.id || (Date.now() + Math.random()))),
                        tributo_codigo: codigo,
                        declaracion: raw?.declaracion || this.declaracionDeCodigo(codigo),
                        tributo_nombre_corto: raw?.tributo_nombre_corto || (codigo ? this.nombreTributo(codigo) : 'Nuevo tributo'),
                        es_igv_justo: !!raw?.es_igv_justo,
                        monto: raw?.monto_deuda_inicial ? this.formatearNumeroConComas(raw.monto_deuda_inicial) : (raw?.monto || ''),
                        vencimiento: raw?.vencimiento_deuda || raw?.vencimiento || this.vencimientoOficialPdt || this.meses[this.mesIdx].vencimiento_oficial_pdt || '',
                        vencimiento_deuda_dmy: raw?.vencimiento_deuda_dmy || '',
                        monto_deuda_inicial: parseFloat(raw?.monto_deuda_inicial || 0),
                        saldo_pendiente: parseFloat(raw?.saldo_pendiente || 0),
                        total_abonado: parseFloat(raw?.total_abonado || 0),
                        abonos: raw?.abonos || [],
                        nuevoMonto: '',
                        nuevaFecha: new Date().toISOString().split('T')[0],
                        nuevaObs: '',
                        saving: false,
                        guardado: false,
                        savingAbono: false,
                        abonoGuardado: false,
                        calculandoIgv: false,
                    };
                    this.prepararBorrador(deuda);
                    return deuda;
                },

                hidratarDeudas(lista) {
                    const codigos = new Set((lista || []).map(d => d.tributo_codigo));
                    const borradores = (this.deudas || []).filter((deuda) => !deuda.id && !codigos.has(deuda.tributo_codigo) && !codigos.has(deuda.borrador_codigo));
                    const guardadas = (lista || []).filter(d => d && (d.id || d.monto_deuda_inicial || d.tributo_codigo)).map(d => this.normalizarDeuda(d));
                    this.deudas = guardadas.concat(borradores);
                    this.meses[this.mesIdx].deudas = guardadas;
                    if (!this.deudas.some((deuda) => deuda.localKey === this.deudaAbiertaKey)) {
                        this.deudaAbiertaKey = this.deudas[0]?.localKey || null;
                    }
                },

                aplicarRespuestaDeuda(data) {
                    const mes = this.meses[this.mesIdx];
                    if (data.operacion_id) {
                        mes.operacion_id = data.operacion_id;
                    }
                    mes.tiene_deuda = !!data.tiene_deuda;
                    this.tieneDeuda = !!data.tiene_deuda;
                    if (data.monto_deuda_inicial !== undefined && data.monto_deuda_inicial !== null) {
                        mes.monto_deuda_inicial = parseFloat(data.monto_deuda_inicial || 0);
                    }
                    if (data.total_abonado !== undefined) {
                        mes.total_abonado = parseFloat(data.total_abonado || 0);
                    }
                    if (data.saldo_pendiente !== undefined) {
                        mes.saldo_pendiente = parseFloat(data.saldo_pendiente || 0);
                    }
                    if (Array.isArray(data.deudas)) {
                        const abierta = this.deudas.find((deuda) => deuda.localKey === this.deudaAbiertaKey);
                        const codigo = abierta ? (abierta.borrador_codigo || abierta.tributo_codigo) : null;
                        const abiertaId = abierta ? abierta.id : null;
                        this.hidratarDeudas(data.deudas);
                        const nueva = this.deudas.find((deuda) => (abiertaId && deuda.id === abiertaId) || (codigo && deuda.tributo_codigo === codigo));
                        if (nueva) {
                            this.deudaAbiertaKey = nueva.localKey;
                        }
                    }
                },

                alCambiarTributo(deuda) {
                    if (!deuda.borrador_codigo) {
                        if (!this.deudaPersistida(deuda)) {
                            deuda.tributo_nombre_corto = 'Nuevo tributo';
                        }
                        deuda.borrador_igv = false;
                        return;
                    }
                    if (!this.deudaPersistida(deuda)) {
                        deuda.tributo_nombre_corto = this.nombreTributo(deuda.borrador_codigo);
                        deuda.declaracion = this.declaracionDeCodigo(deuda.borrador_codigo);
                    }
                    if (deuda.borrador_codigo !== '1011') {
                        deuda.borrador_igv = false;
                    }
                },

                agregarTributo(codigo, declaracionId) {
                    if (!codigo) {
                        if (!declaracionId) {
                            return;
                        }
                        const borrador = this.deudas.find((deuda) => !deuda.tributo_codigo && this.declaracionDe(deuda) === declaracionId);
                        if (borrador) {
                            this.deudaAbiertaKey = borrador.localKey;
                            return;
                        }
                        const bloque = (this.declaraciones || []).find((item) => item.id === declaracionId);
                        if (!bloque || this.tributosLibres(bloque).length === 0) {
                            return;
                        }
                        const deuda = this.normalizarDeuda({
                            tributo_codigo: '',
                            declaracion: declaracionId,
                            tributo_nombre_corto: 'Nuevo tributo',
                        });
                        this.deudas.push(deuda);
                        this.deudaAbiertaKey = deuda.localKey;
                        this.$nextTick(() => {
                            const paneles = this.$root.querySelectorAll('[data-deuda-formulario]');
                            for (const panel of paneles) {
                                if (panel.offsetParent !== null) {
                                    panel.querySelector('select')?.focus();
                                    break;
                                }
                            }
                        });
                        return;
                    }
                    const existente = this.deudas.find((deuda) => deuda.tributo_codigo === codigo);
                    if (existente) {
                        this.deudaAbiertaKey = existente.localKey;
                        return;
                    }
                    const deuda = this.normalizarDeuda({
                        tributo_codigo: codigo,
                        declaracion: this.declaracionDeCodigo(codigo),
                        tributo_nombre_corto: this.nombreTributo(codigo),
                    });
                    this.deudas.push(deuda);
                    this.deudaAbiertaKey = deuda.localKey;
                },

                async guardarTributo(deuda) {
                    deuda.saving = true;
                    deuda.guardado = false;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';
                        const response = await fetch('{{ route('control.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: this.meses[this.mesIdx].periodo,
                                tiene_deuda: true,
                                guardar_tributo: true,
                                deuda_id: deuda.id,
                                tributo_codigo: deuda.borrador_codigo,
                                es_igv_justo: deuda.borrador_codigo === '1011' && !!deuda.borrador_igv,
                                monto_deuda_inicial: this.limpiarMoneda(deuda.borrador_monto),
                                vencimiento_deuda: deuda.borrador_vencimiento || null,
                            }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            const detalle = data.errors ? Object.values(data.errors).flat()[0] : null;
                            throw new Error(detalle || data.message || 'Error al guardar el tributo.');
                        }
                        const codigoEnviado = deuda.borrador_codigo;
                        this.aplicarRespuestaDeuda(data);
                        const guardada = this.deudas.find(d => d.tributo_codigo === codigoEnviado && d.id);
                        if (guardada) {
                            guardada.guardado = true;
                            setTimeout(() => { guardada.guardado = false; }, 2200);
                        }
                    } catch (err) {
                        alert(err.message || 'No se pudo guardar el tributo.');
                    } finally {
                        deuda.saving = false;
                    }
                },

                async eliminarTributo(deuda) {
                    if (!deuda.id) {
                        this.deudas = this.deudas.filter(d => d !== deuda);
                        return;
                    }
                    if (!confirm('¿Eliminar este tributo y sus pagos?')) {
                        return;
                    }
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';
                        const response = await fetch('{{ route('control.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: this.meses[this.mesIdx].periodo,
                                tiene_deuda: true,
                                eliminar_deuda_id: deuda.id,
                            }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'No se pudo eliminar el tributo.');
                        }
                        this.deudas = this.deudas.filter(d => d.localKey !== deuda.localKey);
                        this.aplicarRespuestaDeuda(data);
                    } catch (err) {
                        alert(err.message || 'No se pudo eliminar el tributo.');
                    }
                },

                async toggleIgvJusto(deuda) {
                    if (!deuda) {
                        return;
                    }
                    deuda.borrador_igv = !deuda.borrador_igv;
                    if (!deuda.borrador_igv) {
                        deuda.borrador_vencimiento = this.vencimientoOficialPdt || this.meses[this.mesIdx].vencimiento_oficial_pdt || '';
                        return;
                    }
                    deuda.calculandoIgv = true;
                    const clave = deuda.localKey;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';
                        const response = await fetch('{{ route('control.calcular_igv_justo') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: this.meses[this.mesIdx].periodo,
                            }),
                        });
                        const data = await response.json();
                        if (this.deudaAbiertaKey === clave && deuda.borrador_igv && response.ok && data.fecha_vencimiento) {
                            deuda.borrador_vencimiento = data.fecha_vencimiento;
                        } else if (!(response.ok && data.fecha_vencimiento)) {
                            alert(data.message || 'No se pudo calcular el cronograma de IGV Justo.');
                        }
                    } catch (err) {
                        console.error(err);
                    } finally {
                        deuda.calculandoIgv = false;
                    }
                },

                habilitarEdicionFecha() {
                    this.editandoFechaManual = true;
                    this.$nextTick(() => {
                        if (this.$refs.inputVencimiento) {
                            this.$refs.inputVencimiento.focus();
                            if (typeof this.$refs.inputVencimiento.showPicker === 'function') {
                                try { this.$refs.inputVencimiento.showPicker(); } catch (e) {}
                            }
                        }
                    });
                },

                restablecerFechaSunat() {
                    const oficial = this.vencimientoOficialPdt || this.meses[this.mesIdx].vencimiento_oficial_pdt;
                    if (oficial) {
                        this.vencimientoDeuda = oficial;
                    }
                    this.editandoFechaManual = false;
                },

                async calcularVencimientoIgvJusto() {
                    this.calculandoIgv = true;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('control.calcular_igv_justo') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: this.meses[this.mesIdx].periodo,
                            }),
                        });

                        const data = await response.json();
                        if (response.ok && data.fecha_vencimiento) {
                            this.vencimientoDeuda = data.fecha_vencimiento;
                            this.igvJustoMensaje = data.mensaje || '';
                        } else {
                            alert(data.message || 'No se pudo calcular el cronograma de IGV Justo.');
                        }
                    } catch (err) {
                        console.error('Error al calcular IGV Justo:', err);
                    } finally {
                        this.calculandoIgv = false;
                    }
                },

                async cargarDeuda() {
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('control.deuda') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: this.meses[this.mesIdx].periodo,
                            }),
                        });

                        const data = await response.json();
                        this.deudaCargada = true;
                        this.abonos = data.abonos || [];
                        this.meses[this.mesIdx].abonos = this.abonos;
                        if (data.operacion_id) {
                            this.meses[this.mesIdx].operacion_id = data.operacion_id;
                        }
                        if (!this.tieneDeuda) {
                            this.meses[this.mesIdx].tiene_deuda = !!data.tiene_deuda;
                        }
                        this.meses[this.mesIdx].tributo_codigo = data.tributo_codigo || '1011';
                        this.meses[this.mesIdx].tributo_nombre = data.tributo_nombre;
                        this.meses[this.mesIdx].tributo_nombre_corto = data.tributo_nombre_corto;
                        this.meses[this.mesIdx].tributo_etiqueta = data.tributo_etiqueta;
                        this.meses[this.mesIdx].es_igv_justo = !!data.es_igv_justo;
                        this.meses[this.mesIdx].monto_deuda_inicial = parseFloat(data.monto_deuda_inicial || 0);
                        this.meses[this.mesIdx].vencimiento_deuda = data.vencimiento_deuda || '';
                        this.meses[this.mesIdx].vencimiento_deuda_dmy = data.vencimiento_deuda_dmy || '';
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado || 0);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente || 0);

                        if (!this.tieneDeuda) {
                            this.tieneDeuda = !!data.tiene_deuda;
                        }
                        if (data.vencimiento_oficial_pdt) {
                            this.vencimientoOficialPdt = data.vencimiento_oficial_pdt;
                            this.meses[this.mesIdx].vencimiento_oficial_pdt = data.vencimiento_oficial_pdt;
                            this.meses[this.mesIdx].vencimiento_oficial_pdt_dmy = data.vencimiento_oficial_pdt_dmy;
                        }
                        this.tributoCodigo = data.tributo_codigo || '1011';
                        this.esIgvJusto = !!data.es_igv_justo;
                        if (data.monto_deuda_inicial) {
                            this.montoDeuda = this.formatearNumeroConComas(data.monto_deuda_inicial);
                        } else if (this.cacheDeuda && this.cacheDeuda.montoDeuda) {
                            this.montoDeuda = this.cacheDeuda.montoDeuda;
                        }
                        if (data.vencimiento_deuda) {
                            this.vencimientoDeuda = data.vencimiento_deuda || '';
                        } else if (this.cacheDeuda && this.cacheDeuda.vencimientoDeuda) {
                            this.vencimientoDeuda = this.cacheDeuda.vencimientoDeuda;
                        } else if (!this.esIgvJusto && this.vencimientoOficialPdt) {
                            this.vencimientoDeuda = this.vencimientoOficialPdt;
                        }

                        if (this.vencimientoDeuda && this.vencimientoOficialPdt && this.vencimientoDeuda !== this.vencimientoOficialPdt && !this.esIgvJusto) {
                            this.editandoFechaManual = true;
                        }

                        if (data.tiene_deuda || data.monto_deuda_inicial || (this.abonos && this.abonos.length > 0)) {
                            this.cacheDeuda = {
                                montoDeuda: this.montoDeuda,
                                monto_deuda_inicial: parseFloat(data.monto_deuda_inicial || (this.cacheDeuda?.monto_deuda_inicial ?? 0)),
                                vencimientoDeuda: this.vencimientoDeuda,
                                vencimiento_deuda_dmy: data.vencimiento_deuda_dmy || (this.cacheDeuda?.vencimiento_deuda_dmy ?? ''),
                                vencimiento_oficial_pdt: this.vencimientoOficialPdt,
                                tributoCodigo: this.tributoCodigo,
                                esIgvJusto: this.esIgvJusto,
                                editandoFechaManual: this.editandoFechaManual,
                                total_abonado: parseFloat(data.total_abonado || 0),
                                saldo_pendiente: parseFloat(data.saldo_pendiente || 0),
                                abonos: [...(this.abonos || [])]
                            };
                        }
                        if (Array.isArray(data.deudas)) {
                            this.hidratarDeudas(data.deudas);
                        }
                        if (data.saldo_pendiente !== undefined) {
                            this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente || 0);
                        }
                        if (data.monto_deuda_inicial !== undefined && data.monto_deuda_inicial !== null) {
                            this.meses[this.mesIdx].monto_deuda_inicial = parseFloat(data.monto_deuda_inicial || 0);
                        }
                        if (data.total_abonado !== undefined) {
                            this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado || 0);
                        }
                    } catch (err) {
                        console.error('Error al cargar deuda:', err);
                    }
                },

                async guardarConfigDeuda() {
                    this.savingDeuda = true;
                    this.guardadoDeudaExitoso = false;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const montoLimpio = this.limpiarMoneda(this.montoDeuda);

                        const response = await fetch('{{ route('control.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                periodo: this.meses[this.mesIdx].periodo,
                                tiene_deuda: this.tieneDeuda,
                                tributo_codigo: this.tieneDeuda ? this.tributoCodigo : null,
                                es_igv_justo: this.tieneDeuda && this.tributoCodigo === '1011' ? this.esIgvJusto : false,
                                monto_deuda_inicial: this.tieneDeuda ? montoLimpio : null,
                                vencimiento_deuda: this.tieneDeuda ? (this.vencimientoDeuda || null) : null,
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar deuda.');
                        }

                        if (data.operacion_id) {
                            this.meses[this.mesIdx].operacion_id = data.operacion_id;
                        }
                        this.meses[this.mesIdx].tiene_deuda = !!data.tiene_deuda;
                        this.meses[this.mesIdx].tributo_codigo = data.tributo_codigo;
                        this.meses[this.mesIdx].tributo_nombre = data.tributo_nombre;
                        this.meses[this.mesIdx].tributo_nombre_corto = data.tributo_nombre_corto;
                        this.meses[this.mesIdx].tributo_etiqueta = data.tributo_etiqueta;
                        this.meses[this.mesIdx].es_igv_justo = !!data.es_igv_justo;
                        this.meses[this.mesIdx].monto_deuda_inicial = parseFloat(data.monto_deuda_inicial || 0);
                        this.meses[this.mesIdx].vencimiento_deuda = data.vencimiento_deuda || '';
                        this.meses[this.mesIdx].vencimiento_deuda_dmy = data.vencimiento_deuda_dmy || '';
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado || 0);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente || 0);

                        if (data.abonos) {
                            this.abonos = data.abonos;
                            this.meses[this.mesIdx].abonos = this.abonos;
                        }

                        this.montoDeuda = this.formatearNumeroConComas(data.monto_deuda_inicial);

                        this.cacheDeuda = {
                            montoDeuda: this.montoDeuda,
                            monto_deuda_inicial: parseFloat(data.monto_deuda_inicial || 0),
                            vencimientoDeuda: this.vencimientoDeuda,
                            vencimiento_deuda_dmy: data.vencimiento_deuda_dmy || '',
                            vencimiento_oficial_pdt: this.vencimientoOficialPdt,
                            tributoCodigo: this.tributoCodigo,
                            esIgvJusto: this.esIgvJusto,
                            editandoFechaManual: this.editandoFechaManual,
                            total_abonado: parseFloat(data.total_abonado || 0),
                            saldo_pendiente: parseFloat(data.saldo_pendiente || 0),
                            abonos: [...(this.abonos || [])]
                        };

                        this.guardadoDeudaExitoso = true;
                        setTimeout(() => { this.guardadoDeudaExitoso = false; }, 2200);
                    } catch (err) {
                        console.error('Error al guardar datos de deuda:', err);
                        alert('No se pudo guardar la configuración de deuda: ' + err.message);
                    } finally {
                        this.savingDeuda = false;
                    }
                },

                async registrarAbono(deuda) {
                    if (!deuda || !deuda.id || !this.meses[this.mesIdx].operacion_id) {
                        alert('Guarde el tributo antes de registrar un pago.');
                        return;
                    }

                    const montoLimpio = this.limpiarMoneda(deuda.nuevoMonto);
                    if (montoLimpio <= 0) {
                        alert('Ingrese un monto válido para el pago.');
                        return;
                    }

                    deuda.savingAbono = true;
                    deuda.abonoGuardado = false;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('abonos_deudas.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                operacion_id: this.meses[this.mesIdx].operacion_id,
                                deuda_tributaria_id: deuda.id,
                                monto: montoLimpio,
                                fecha_pago: deuda.nuevaFecha,
                                observacion: deuda.nuevaObs || null,
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar el pago.');
                        }

                        deuda.abonos.unshift(data.abono);
                        deuda.total_abonado = parseFloat(data.total_abonado);
                        deuda.saldo_pendiente = parseFloat(data.saldo_pendiente);
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado_mes ?? data.total_abonado);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente_mes ?? data.saldo_pendiente);
                        const enMes = (this.meses[this.mesIdx].deudas || []).find(d => d.id === deuda.id);
                        if (enMes) {
                            enMes.abonos = deuda.abonos;
                            enMes.saldo_pendiente = deuda.saldo_pendiente;
                            enMes.total_abonado = deuda.total_abonado;
                        }
                        deuda.nuevoMonto = '';
                        deuda.nuevaObs = '';
                        deuda.abonoGuardado = true;
                        setTimeout(() => { deuda.abonoGuardado = false; }, 2200);
                    } catch (err) {
                        alert('Error al registrar el pago: ' + err.message);
                    } finally {
                        deuda.savingAbono = false;
                    }
                },

                async eliminarAbono(deuda, abonoId) {
                    if (!confirm('¿Desea eliminar este pago?')) {
                        return;
                    }

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch(`/abonos-deudas/${abonoId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al eliminar el pago.');
                        }

                        if (deuda && deuda.abonos) {
                            deuda.abonos = deuda.abonos.filter(a => a.id !== abonoId);
                            deuda.total_abonado = parseFloat(data.total_abonado);
                            deuda.saldo_pendiente = parseFloat(data.saldo_pendiente);
                        }
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado_mes ?? data.total_abonado);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente_mes ?? data.saldo_pendiente);
                    } catch (err) {
                        alert('Error al eliminar el pago: ' + err.message);
                    }
                }
            };
        }
    </script>
@endsection
