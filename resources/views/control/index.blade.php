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
                                        <a href="{{ route('clientes.show', ['cliente' => $item['cliente'], 'tab' => 'operaciones']) }}"
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
                                    'w-[6.5%] p-0.5 border-r border-b border-gray-300 text-center transition-colors relative align-middle h-11',
                                    'bg-gray-100/80 text-gray-400' => ! empty($m['es_baja']) || ($m['estado'] ?? '') === 'Baja',
                                    'bg-emerald-100/70 hover:bg-emerald-100' => empty($m['es_baja']) && ($m['estado'] ?? '') === 'Declarado',
                                    'bg-amber-100/70 hover:bg-amber-100' => empty($m['es_baja']) && in_array($m['estado'] ?? '', ['Procesando', 'Proceso'], true),
                                    'bg-white hover:bg-slate-100/60' => empty($m['es_baja']) && ! in_array($m['estado'] ?? 'Pendiente', ['Declarado', 'Procesando', 'Proceso', 'Baja'], true),
                                ])
                                x-effect="pintarFondo($el, meses[{{ $idx }}].estado)">

                                @if (!empty($m['es_baja']))
                                    <div class="flex items-center justify-center w-full h-full text-center py-1 select-none"
                                         title="{{ $m['motivo_baja'] ?? 'Cliente no activo en este periodo (Baja del Estudio Contable)' }}">
                                        <span class="inline-flex items-center gap-1 rounded bg-rose-50 border border-rose-200 px-1.5 py-0.5 text-[9px] font-bold text-rose-700 shadow-xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            Baja
                                        </span>
                                    </div>
                                @else
                                    {{-- Componente Alpine por celda: DJ + RH + Nota Flotante Google Sheets --}}
                                    <div x-data="popoverNotaMes({
                                            clienteId: {{ $item['cliente']->id }},
                                            periodo: '{{ $m['periodo'] }}',
                                            mesNombre: '{{ $m['nombre'] }}',
                                            notaInicial: @js($m['notas'])
                                         })"
                                         class="flex items-center justify-between gap-0.5 px-0.5 w-full h-full relative">

                                    {{-- Triángulo Rojo en Esquina Superior Derecha (Google Sheets Comment Indicator) --}}
                                    <div x-show="tieneNota()"
                                         x-cloak
                                         @click="abrir()"
                                         :title="'Nota: ' + nota"
                                         class="absolute top-0 right-0 w-0 h-0 border-t-[8px] border-r-[8px] border-t-transparent border-r-rose-600 cursor-pointer z-10">
                                    </div>

                                    {{-- CONTROL 1: DJ (Declaración Jurada + Control de Deudas con Pestañas) --}}
                                    <div x-data="controlCeldaDJ({{ $item['cliente']->id }}, {{ $idx }}, meses)" class="relative flex-1 flex items-center justify-center">
                                        <div class="relative inline-flex items-center justify-center">
                                            <button
                                                type="button"
                                                @click="abrirMenuDJ($el)"
                                                :title="tooltipDJ()"
                                                class="inline-flex h-6 w-6 min-w-6 shrink-0 items-center justify-center overflow-hidden rounded text-xs transition-transform select-none hover:scale-110 focus:outline-none active:scale-95"
                                            >
                                                <span x-text="meses[{{ $idx }}].estado === 'Declarado' ? '🟢' : (meses[{{ $idx }}].estado === 'Procesando' ? '🟡' : '⚪')">{{ match ($m['estado'] ?? 'Pendiente') { 'Declarado' => '🟢', 'Procesando' => '🟡', default => '⚪' } }}</span>
                                            </button>

                                            {{-- Indicador Premium de Deuda (Punto Rojo si saldo > 0 / Check Verde si saldo == 0) --}}
                                            <template x-if="meses[{{ $idx }}].estado === 'Declarado' && meses[{{ $idx }}].tiene_deuda">
                                                <div class="pointer-events-none">
                                                    {{-- Saldo pendiente > 0: Punto Rojo --}}
                                                    <span x-show="meses[{{ $idx }}].saldo_pendiente > 0"
                                                          class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white shadow-xs pointer-events-auto cursor-pointer"
                                                          :title="'Deuda Inicial: S/ ' + parseFloat(meses[{{ $idx }}].monto_deuda_inicial || 0).toFixed(2) + ' | Abonado: S/ ' + parseFloat(meses[{{ $idx }}].total_abonado || 0).toFixed(2) + ' | Saldo: S/ ' + parseFloat(meses[{{ $idx }}].saldo_pendiente || 0).toFixed(2)"
                                                          @click.stop="abrirMenuDJConTab($el, 'deuda')">
                                                    </span>
                                                    {{-- Deuda saldada: Check Verde --}}
                                                    <span x-show="meses[{{ $idx }}].saldo_pendiente <= 0"
                                                          class="absolute -top-1 -right-1 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white flex items-center justify-center text-[7px] text-white font-black shadow-xs pointer-events-auto cursor-pointer"
                                                          :title="'Deuda Inicial: S/ ' + parseFloat(meses[{{ $idx }}].monto_deuda_inicial || 0).toFixed(2) + ' | Abonado: S/ ' + parseFloat(meses[{{ $idx }}].total_abonado || 0).toFixed(2) + ' | Saldo: S/ 0.00'"
                                                          @click.stop="abrirMenuDJConTab($el, 'deuda')">
                                                        ✓
                                                    </span>
                                                </div>
                                            </template>
                                        </div>

                                        {{-- Miniventana Flotante con 2 Pestañas para DJ y Deudas --}}
                                        <div
                                            x-ref="menuDJ"
                                            x-show="openDJ"
                                            x-cloak
                                            data-menu-flotante
                                            @click.outside="openDJ = false"
                                            class="fixed z-50 w-80 sm:w-96 rounded-2xl border border-gray-200 bg-white p-3 shadow-2xl text-left max-h-[90vh] overflow-y-auto"
                                        >
                                            {{-- Encabezado con Tabs --}}
                                            <div class="border-b border-gray-100 pb-2 mb-3">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-[11px] font-bold text-gray-800 truncate">
                                                        {{ $m['corto'] }}-{{ substr($anio, -2) }} &bull; {{ $item['cliente']->razon_social }}
                                                    </span>
                                                    <button type="button" @click="openDJ = false" class="text-gray-400 hover:text-gray-700 text-sm font-bold">&times;</button>
                                                </div>

                                                {{-- 2 Pestañas: 'Estado Declaración' y 'Control de Deuda y Pagos' --}}
                                                <div class="flex border-b border-gray-200 text-xs font-semibold">
                                                    <button
                                                        type="button"
                                                        @click="activeTab = 'declaracion'"
                                                        :class="activeTab === 'declaracion' ? 'border-b-2 border-brand-600 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700'"
                                                        class="flex-1 pb-2 text-center transition"
                                                    >
                                                        Estado Declaración
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="activeTab = 'deuda'; if (!deudaCargada) cargarDeuda()"
                                                        :class="activeTab === 'deuda' ? 'border-b-2 border-brand-600 text-brand-700 font-bold' : 'text-gray-500 hover:text-gray-700'"
                                                        class="flex-1 pb-2 text-center transition relative"
                                                    >
                                                        Control de Deuda y Pagos
                                                        <template x-if="meses[mesIdx].tiene_deuda && meses[mesIdx].saldo_pendiente > 0">
                                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500 ml-1"></span>
                                                        </template>
                                                        <template x-if="meses[mesIdx].tiene_deuda && meses[mesIdx].saldo_pendiente <= 0">
                                                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 ml-1"></span>
                                                        </template>
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- TAB 1: ESTADO DECLARACIÓN --}}
                                            <div x-show="activeTab === 'declaracion'" class="space-y-1 py-1">
                                                <button
                                                    type="button"
                                                    @click="cambiarEstado('Pendiente')"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-slate-100 text-gray-800"
                                                    :class="meses[mesIdx].estado === 'Pendiente' ? 'bg-slate-100 text-slate-800 ring-1 ring-slate-300' : ''"
                                                >
                                                    <span class="text-sm">⚪</span>
                                                    <span>Pendiente</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    @click="cambiarEstado('Procesando')"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-amber-50 text-gray-800"
                                                    :class="meses[mesIdx].estado === 'Procesando' ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' : ''"
                                                >
                                                    <span class="text-sm">🟡</span>
                                                    <span>En Proceso</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    @click="cambiarEstado('Declarado')"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-emerald-50 text-gray-800"
                                                    :class="meses[mesIdx].estado === 'Declarado' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : ''"
                                                >
                                                    <span class="text-sm">🟢</span>
                                                    <span>Declarado</span>
                                                </button>
                                            </div>

                                            {{-- TAB 2: CONTROL DE DEUDA Y PAGOS --}}
                                            <div x-show="activeTab === 'deuda'" class="space-y-3 py-1">
                                                {{-- Switch: ¿Quedó deuda con SUNAT? --}}
                                                <div class="flex items-center justify-between rounded-xl bg-gray-50 p-2.5 border border-gray-200">
                                                    <div>
                                                        <span class="block text-xs font-bold text-gray-800">¿Quedó deuda con SUNAT?</span>
                                                        <span class="block text-[10px] text-gray-500">IGV Justo, Fraccionamiento, etc.</span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        @click="toggleTieneDeuda()"
                                                        :class="tieneDeuda ? 'bg-brand-600' : 'bg-gray-300'"
                                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                                    >
                                                        <span
                                                            :class="tieneDeuda ? 'translate-x-4' : 'translate-x-0'"
                                                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                        ></span>
                                                    </button>
                                                </div>

                                                {{-- Contenido cuando tiene_deuda es true --}}
                                                <div x-show="tieneDeuda" class="space-y-3">
                                                    {{-- Formulario de Monto Inicial y Vencimiento --}}
                                                    <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2 rounded-lg border border-slate-200">
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-gray-600 uppercase">Monto Inicial (S/)</label>
                                                            <div class="relative mt-0.5">
                                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">S/</span>
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    x-model="montoDeuda"
                                                                    class="w-full pl-6 rounded border-gray-300 py-1 text-xs text-gray-800 bg-white shadow-xs focus:ring-1 focus:ring-brand-500"
                                                                    placeholder="0.00"
                                                                >
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label class="block text-[10px] font-bold text-gray-600 uppercase">Vencimiento</label>
                                                            <input
                                                                type="date"
                                                                x-model="vencimientoDeuda"
                                                                class="w-full rounded border-gray-300 py-1 text-xs text-gray-800 bg-white shadow-xs mt-0.5 focus:ring-1 focus:ring-brand-500"
                                                            >
                                                        </div>
                                                        <div class="col-span-2 flex items-center justify-between pt-1">
                                                            <span x-show="guardadoDeudaExitoso" class="text-[10px] font-bold text-emerald-600 animate-pulse">
                                                                ✓ Guardado
                                                            </span>
                                                            <span x-show="!guardadoDeudaExitoso"></span>
                                                            <button
                                                                type="button"
                                                                @click="guardarConfigDeuda()"
                                                                :disabled="savingDeuda"
                                                                class="px-3 py-1 rounded bg-brand-600 text-white font-bold text-xs hover:bg-brand-700 transition shadow-xs disabled:opacity-50"
                                                            >
                                                                <span x-text="savingDeuda ? 'Guardando...' : 'Guardar Datos Deuda'"></span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- Resumen Financiero Dinámico --}}
                                                    <div class="grid grid-cols-3 gap-2">
                                                        <div class="rounded-xl bg-gray-50 border border-gray-200 p-2 text-center">
                                                            <p class="text-[9px] font-bold uppercase text-gray-500 tracking-wider">Deuda Total</p>
                                                            <p class="text-xs font-black text-gray-800 mt-0.5" x-text="'S/ ' + parseFloat(meses[mesIdx].monto_deuda_inicial || 0).toFixed(2)"></p>
                                                        </div>
                                                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-2 text-center">
                                                            <p class="text-[9px] font-bold uppercase text-emerald-600 tracking-wider">Total Abonado</p>
                                                            <p class="text-xs font-black text-emerald-600 mt-0.5" x-text="'S/ ' + parseFloat(meses[mesIdx].total_abonado || 0).toFixed(2)"></p>
                                                        </div>
                                                        <div class="rounded-xl p-2 text-center border transition-colors"
                                                             :class="meses[mesIdx].saldo_pendiente > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'">
                                                            <p class="text-[9px] font-bold uppercase tracking-wider"
                                                               :class="meses[mesIdx].saldo_pendiente > 0 ? 'text-red-600' : 'text-gray-500'">Saldo</p>
                                                            <p class="text-xs font-black mt-0.5"
                                                               :class="meses[mesIdx].saldo_pendiente > 0 ? 'text-red-600' : 'text-gray-600'"
                                                               x-text="'S/ ' + parseFloat(meses[mesIdx].saldo_pendiente || 0).toFixed(2)"></p>
                                                        </div>
                                                    </div>

                                                    {{-- Mini-Sistema de Pagos --}}
                                                    <div class="space-y-2 border-t border-gray-100 pt-2">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-[11px] font-bold text-gray-700">Abonos Registrados</span>
                                                            <span class="text-[10px] text-gray-400" x-text="abonos.length + ' pagos'"></span>
                                                        </div>

                                                        {{-- Tabla de Abonos --}}
                                                        <div class="max-h-28 overflow-y-auto rounded-lg border border-gray-200 bg-white">
                                                            <table class="w-full text-[10px]">
                                                                <thead class="bg-gray-50 text-gray-500 border-b border-gray-200 sticky top-0">
                                                                    <tr>
                                                                        <th class="py-1 px-2 text-left">Fecha</th>
                                                                        <th class="py-1 px-2 text-right">Monto</th>
                                                                        <th class="py-1 px-2 text-left">Nota</th>
                                                                        <th class="py-1 px-1 text-center w-5"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-gray-100">
                                                                    <template x-for="abono in abonos" :key="abono.id">
                                                                        <tr class="hover:bg-gray-50">
                                                                            <td class="py-1 px-2 text-gray-600 font-mono" x-text="abono.fecha_pago"></td>
                                                                            <td class="py-1 px-2 text-right font-bold text-emerald-600" x-text="'S/ ' + parseFloat(abono.monto).toFixed(2)"></td>
                                                                            <td class="py-1 px-2 text-gray-500 truncate max-w-[80px]" x-text="abono.observacion || '—'" :title="abono.observacion"></td>
                                                                            <td class="py-1 px-1 text-center">
                                                                                <button type="button" @click="eliminarAbono(abono.id)" class="text-gray-300 hover:text-red-600 font-bold" title="Eliminar abono">&times;</button>
                                                                            </td>
                                                                        </tr>
                                                                    </template>
                                                                    <tr x-show="abonos.length === 0">
                                                                        <td colspan="4" class="py-2.5 text-center text-gray-400">Sin abonos registrados</td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        {{-- Mini-Formulario Flotante/Inline para Registrar 'Nuevo Abono' --}}
                                                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-2.5 space-y-2">
                                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-800">+ Registrar Nuevo Abono</span>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-gray-600 uppercase">Monto</label>
                                                                    <div class="relative mt-0.5">
                                                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">S/</span>
                                                                        <input
                                                                            type="number"
                                                                            step="0.01"
                                                                            min="0.01"
                                                                            :max="meses[mesIdx].saldo_pendiente"
                                                                            x-model="nuevoMonto"
                                                                            placeholder="0.00"
                                                                            class="w-full pl-6 rounded border-gray-300 py-1 text-xs text-gray-800 bg-white focus:ring-1 focus:ring-emerald-500"
                                                                        >
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-gray-600 uppercase">Fecha Pago</label>
                                                                    <input
                                                                        type="date"
                                                                        x-model="nuevaFecha"
                                                                        class="w-full rounded border-gray-300 py-1 text-xs text-gray-800 bg-white mt-0.5 focus:ring-1 focus:ring-emerald-500"
                                                                    >
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-gray-600 uppercase">Nota / Referencia</label>
                                                                <input
                                                                    type="text"
                                                                    x-model="nuevaObs"
                                                                    placeholder="Ej. Abono cuota 1"
                                                                    class="w-full rounded border-gray-300 py-1 text-xs text-gray-800 bg-white mt-0.5 focus:ring-1 focus:ring-emerald-500"
                                                                >
                                                            </div>
                                                            <button
                                                                type="button"
                                                                @click="registrarAbono()"
                                                                :disabled="savingAbono || !nuevoMonto || parseFloat(nuevoMonto) <= 0"
                                                                class="w-full py-1.5 rounded-lg bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition shadow-xs disabled:opacity-50 flex items-center justify-center gap-1.5"
                                                            >
                                                                <span x-text="savingAbono ? 'Registrando...' : 'Registrar Abono'"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Separador vertical micro --}}
                                    <div class="h-3.5 w-px bg-gray-300/60 shrink-0"></div>

                                    {{-- CONTROL 2: RH (Recibo por Honorarios - Miniventana Flotante Amplia) --}}
                                    <div x-data="{ openRH: false }" class="relative flex-1 flex items-center justify-center">
                                        <button
                                            type="button"
                                            @click="openRH = !openRH; if (openRH) $nextTick(() => window.ubicarMenuFlotante($refs.menuRH, $el))"
                                            :title="'RH: ' + (meses[{{ $idx }}].honorarios === 'Pagado' ? 'Pagado' : 'Debe')"
                                            class="inline-flex h-6 w-6 min-w-6 shrink-0 items-center justify-center overflow-hidden rounded text-xs transition-transform select-none hover:scale-110 focus:outline-none active:scale-95"
                                        >
                                            <span x-text="meses[{{ $idx }}].honorarios === 'Pagado' ? '🟢' : '⚪'">{{ ($m['honorarios'] ?? '') === 'Pagado' ? '🟢' : '⚪' }}</span>
                                        </button>

                                        {{-- Miniventana Flotante Espaciosa para RH --}}
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

                                    {{-- Botón Icono de Nota (💬) para desplegar el Popover Flotante --}}
                                    <button
                                        type="button"
                                        @click="abrir()"
                                        :class="tieneNota() ? 'text-amber-700 opacity-100' : 'text-gray-400 opacity-25 hover:opacity-100'"
                                        title="Nota / Cobranza del mes"
                                        class="shrink-0 p-0.5 text-[10px] leading-none rounded hover:bg-black/5 transition"
                                    >
                                        💬
                                    </button>

                                    {{-- Popover Flotante de Nota estilo Google Sheets / Excel --}}
                                    <div x-ref="menuNota"
                                         x-show="openNote"
                                         x-cloak
                                         data-menu-flotante
                                         @click.outside="cerrar()"
                                         class="fixed z-50 w-64 rounded-xl border border-amber-300 bg-amber-50 p-3 text-left shadow-2xl">

                                        {{-- Cabecera de la Nota --}}
                                        <div class="flex items-center justify-between pb-1.5 border-b border-amber-200 mb-2">
                                            <div class="truncate pr-2">
                                                <span class="block text-[11px] font-bold text-amber-900 truncate">
                                                    Nota: {{ $m['corto'] }}-{{ substr($anio, -2) }} &bull; {{ $item['cliente']->razon_social }}
                                                </span>
                                            </div>
                                            <button type="button" @click="cerrar()" class="text-gray-400 hover:text-gray-700 text-sm font-bold leading-none">&times;</button>
                                        </div>

                                        {{-- Campo de Texto --}}
                                        <textarea
                                            x-model="tempNota"
                                            rows="3"
                                            placeholder="Escribir nota contable (ej. DEBE RH, falta factura venta)..."
                                            class="w-full rounded border border-amber-200 bg-white p-2 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-amber-500 shadow-inner"
                                        ></textarea>

                                        {{-- Pie del Popover y Acciones --}}
                                        <div class="mt-2 flex items-center justify-between">
                                            <span x-show="guardadoExitoso" class="text-[10px] text-emerald-700 font-bold animate-pulse">✅ Guardada</span>
                                            <span x-show="!guardadoExitoso"></span>

                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    @click="cerrar()"
                                                    class="px-2 py-0.5 text-[10px] text-gray-600 hover:bg-amber-100 rounded transition"
                                                >
                                                    Cerrar
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="guardarNota()"
                                                    :disabled="guardando"
                                                    class="px-2.5 py-0.5 text-[10px] font-bold text-white bg-amber-600 hover:bg-amber-700 rounded shadow-xs transition active:scale-95 disabled:opacity-50"
                                                >
                                                    <span x-show="!guardando">Guardar</span>
                                                    <span x-show="guardando">...</span>
                                                </button>
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

                fondosCelda: ['bg-gray-100/80', 'text-gray-400', 'bg-emerald-100/70', 'hover:bg-emerald-100', 'bg-amber-100/70', 'hover:bg-amber-100', 'bg-white', 'hover:bg-slate-100/60'],
                fondosAvance: ['bg-emerald-100', 'text-emerald-800', 'bg-gray-100', 'text-gray-700'],

                bgClass(estado) {
                    if (estado === 'Baja') {
                        return 'bg-gray-100/80 text-gray-400';
                    }
                    if (estado === 'Declarado') {
                        return 'bg-emerald-100/70 hover:bg-emerald-100';
                    }
                    if (estado === 'Procesando') {
                        return 'bg-amber-100/70 hover:bg-amber-100';
                    }
                    return 'bg-white hover:bg-slate-100/60';
                },

                pintarFondo(el, estado) {
                    this.fondosCelda.forEach((clase) => el.classList.remove(clase));
                    this.bgClass(estado).split(' ').forEach((clase) => el.classList.add(clase));
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

        function popoverNotaMes(config) {
            return {
                clienteId: config.clienteId,
                periodo: config.periodo,
                mesNombre: config.mesNombre,
                nota: config.notaInicial || '',
                tempNota: config.notaInicial || '',
                openNote: false,
                guardando: false,
                guardadoExitoso: false,

                tieneNota() {
                    return this.nota && this.nota.trim().length > 0;
                },

                abrir() {
                    this.tempNota = this.nota;
                    this.openNote = true;
                    this.$nextTick(() => window.ubicarMenuFlotante(this.$refs.menuNota, this.$el));
                },

                cerrar() {
                    this.openNote = false;
                },

                async guardarNota() {
                    this.guardando = true;
                    this.guardadoExitoso = false;

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
                                periodo: this.periodo,
                                notas: this.tempNota
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar nota.');
                        }

                        this.nota = this.tempNota;
                        this.guardando = false;
                        this.guardadoExitoso = true;

                        setTimeout(() => {
                            this.guardadoExitoso = false;
                            this.openNote = false;
                        }, 700);
                    } catch (err) {
                        this.guardando = false;
                        alert('No se pudo guardar la nota: ' + err.message);
                    }
                }
            };
        }

        function controlCeldaDJ(clienteId, mesIdx, meses) {
            return {
                clienteId: clienteId,
                mesIdx: mesIdx,
                meses: meses,
                openDJ: false,
                activeTab: 'declaracion',
                tieneDeuda: !!meses[mesIdx].tiene_deuda,
                montoDeuda: meses[mesIdx].monto_deuda_inicial || '',
                vencimientoDeuda: meses[mesIdx].vencimiento_deuda || '',
                deudaCargada: false,
                savingDeuda: false,
                guardadoDeudaExitoso: false,
                savingAbono: false,
                abonos: [],
                nuevoMonto: '',
                nuevaFecha: new Date().toISOString().split('T')[0],
                nuevaObs: '',

                abrirMenuDJ(ancla) {
                    this.openDJ = !this.openDJ;
                    if (this.openDJ) {
                        this.tieneDeuda = !!this.meses[this.mesIdx].tiene_deuda;
                        this.montoDeuda = this.meses[this.mesIdx].monto_deuda_inicial || '';
                        this.vencimientoDeuda = this.meses[this.mesIdx].vencimiento_deuda || '';
                        this.$nextTick(() => window.ubicarMenuFlotante(this.$refs.menuDJ, ancla));
                        if (this.tieneDeuda && !this.deudaCargada) {
                            this.cargarDeuda();
                        }
                    }
                },

                abrirMenuDJConTab(ancla, tabName) {
                    this.activeTab = tabName;
                    this.openDJ = true;
                    this.tieneDeuda = !!this.meses[this.mesIdx].tiene_deuda;
                    this.montoDeuda = this.meses[this.mesIdx].monto_deuda_inicial || '';
                    this.vencimientoDeuda = this.meses[this.mesIdx].vencimiento_deuda || '';
                    this.$nextTick(() => window.ubicarMenuFlotante(this.$refs.menuDJ, ancla));
                    this.cargarDeuda();
                },

                tooltipDJ() {
                    const m = this.meses[this.mesIdx];
                    if (m.estado === 'Declarado' && m.tiene_deuda) {
                        return 'Deuda Inicial: S/ ' + parseFloat(m.monto_deuda_inicial || 0).toFixed(2) + ' | Abonado: S/ ' + parseFloat(m.total_abonado || 0).toFixed(2) + ' | Saldo: S/ ' + parseFloat(m.saldo_pendiente || 0).toFixed(2);
                    }
                    return 'DJ: ' + m.estado;
                },

                async cambiarEstado(nuevoEstado) {
                    this.meses[this.mesIdx].estado = nuevoEstado;
                    if (typeof this.actualizarEstadoMaestro === 'function') {
                        this.actualizarEstadoMaestro(this.mesIdx);
                    }
                    this.openDJ = false;
                },

                async toggleTieneDeuda() {
                    this.tieneDeuda = !this.tieneDeuda;
                    if (!this.tieneDeuda) {
                        await this.guardarConfigDeuda();
                    } else if (!this.deudaCargada) {
                        await this.cargarDeuda();
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
                        if (data.operacion_id) {
                            this.meses[this.mesIdx].operacion_id = data.operacion_id;
                        }
                        this.meses[this.mesIdx].tiene_deuda = !!data.tiene_deuda;
                        this.meses[this.mesIdx].monto_deuda_inicial = parseFloat(data.monto_deuda_inicial || 0);
                        this.meses[this.mesIdx].vencimiento_deuda = data.vencimiento_deuda || '';
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado || 0);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente || 0);

                        this.tieneDeuda = !!data.tiene_deuda;
                        this.montoDeuda = data.monto_deuda_inicial || '';
                        this.vencimientoDeuda = data.vencimiento_deuda || '';
                    } catch (err) {
                        console.error('Error al cargar deuda:', err);
                    }
                },

                async guardarConfigDeuda() {
                    this.savingDeuda = true;
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
                                tiene_deuda: this.tieneDeuda,
                                monto_deuda_inicial: this.tieneDeuda ? parseFloat(this.montoDeuda || 0) : null,
                                vencimiento_deuda: this.tieneDeuda ? this.vencimientoDeuda : null,
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
                        this.meses[this.mesIdx].monto_deuda_inicial = parseFloat(data.monto_deuda_inicial || 0);
                        this.meses[this.mesIdx].vencimiento_deuda = data.vencimiento_deuda || '';
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado || 0);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente || 0);

                        this.guardadoDeudaExitoso = true;
                        setTimeout(() => { this.guardadoDeudaExitoso = false; }, 1500);
                    } catch (err) {
                        console.error('Error al guardar datos de deuda:', err);
                        alert('No se pudo guardar la configuración de deuda: ' + err.message);
                    } finally {
                        this.savingDeuda = false;
                    }
                },

                async registrarAbono() {
                    if (!this.meses[this.mesIdx].operacion_id) {
                        await this.guardarConfigDeuda();
                    }

                    const operacionId = this.meses[this.mesIdx].operacion_id;
                    if (!operacionId) {
                        alert('Debe guardar primero la deuda antes de añadir un abono.');
                        return;
                    }

                    this.savingAbono = true;
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
                                operacion_id: operacionId,
                                monto: parseFloat(this.nuevoMonto),
                                fecha_pago: this.nuevaFecha,
                                observacion: this.nuevaObs || null,
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar abono.');
                        }

                        this.abonos.unshift(data.abono);
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente);
                        this.nuevoMonto = '';
                        this.nuevaObs = '';
                    } catch (err) {
                        console.error('Error al registrar abono:', err);
                        alert('Error al registrar abono: ' + err.message);
                    } finally {
                        this.savingAbono = false;
                    }
                },

                async eliminarAbono(abonoId) {
                    if (!confirm('¿Desea eliminar este abono?')) {
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
                            throw new Error(data.message || 'Error al eliminar abono.');
                        }

                        this.abonos = this.abonos.filter(a => a.id !== abonoId);
                        this.meses[this.mesIdx].total_abonado = parseFloat(data.total_abonado);
                        this.meses[this.mesIdx].saldo_pendiente = parseFloat(data.saldo_pendiente);
                    } catch (err) {
                        console.error('Error al eliminar abono:', err);
                        alert('Error al eliminar abono: ' + err.message);
                    }
                }
            };
        }
    </script>
@endsection
