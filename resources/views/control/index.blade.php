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
                <span>⚪ Pendiente</span>
                <span class="text-gray-300">|</span>
                <span>🟡 En Proceso</span>
                <span class="text-gray-300">|</span>
                <span>🟢 Declarado</span>
                <span class="text-gray-300">|</span>
                <span class="flex items-center gap-1 font-semibold text-gray-700">
                    <span class="relative inline-block shrink-0" style="width:15px;height:15px" aria-hidden="true">
                        <span class="absolute inline-flex h-6 w-6 items-center justify-center overflow-hidden rounded-full text-xs" style="left:50%;top:50%;transform:translate(-50%,-50%) scale(0.75)">
                            <span>🟢</span>
                            <svg class="absolute inset-0 h-6 w-6 pointer-events-none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="6.8" fill="none" stroke="#ef4444" stroke-width="2.4" />
                            </svg>
                        </span>
                    </span>
                    Deuda SUNAT
                </span>
                <span class="text-gray-300">|</span>
                <span class="flex items-center gap-1 font-semibold text-gray-700">
                    <span class="relative inline-block shrink-0" style="width:15px;height:15px" aria-hidden="true">
                        <span class="absolute inline-flex h-6 w-6 items-center justify-center overflow-hidden rounded-full text-xs" style="left:50%;top:50%;transform:translate(-50%,-50%) scale(0.75)">
                            <span>🟢</span>
                            <svg class="absolute inset-0 h-6 w-6 pointer-events-none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="6.8" fill="none" stroke="#7c3aed" stroke-width="2.4" />
                            </svg>
                        </span>
                    </span>
                    Declarado / Pagado
                </span>
                <span class="text-gray-300">|</span>
                <span class="font-bold text-gray-700">DJ: Declaración &bull; RH: Honorarios</span>
            </div>

            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('abrir-carga-masiva'))"
                    title="Cargar declaraciones y montos de varios meses. El llenado manual no cambia."
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                <span>Carga masiva</span>
            </button>

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

    @include('partials.marca-cliente-en-trabajo', [
        'idsEnMatriz' => array_map(
            fn (array $fila): int => (int) $fila['cliente']->id,
            $matrizClientes
        ),
    ])

    {{-- ═══════════════════════════════ TABLA MATRIZ 100% MONITOR (13 COLUMNAS: DJ + RH) ═══════════════════════════════ --}}
    <div class="overflow-x-auto rounded-xl border border-gray-300 bg-white shadow-md min-h-[200px]">
        <table class="w-full table-fixed border-collapse border border-gray-300 text-xs text-left">

            {{-- ═══════════════ CABECERA ÚNICA (THEAD - 13 COLUMNAS CON DJ Y RH) ═══════════════ --}}
            <thead class="bg-gray-100 text-gray-700 text-xs font-extrabold select-none shadow-xs">
                <tr class="border-b border-gray-300 bg-gray-200/90 text-center">
                    {{-- Columna 1: Cliente/RUC (Sticky Left) con N° Correlativo --}}
                    <th class="w-[22%] px-2.5 py-2 text-left border-r border-b border-gray-300 bg-gray-200 sticky left-0 z-20 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.15)]">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex h-5 w-5 shrink-0" title="Marca la empresa en la que estás trabajando" aria-hidden="true"></span>
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
                            'RMT'     => 'bg-blue-100 text-blue-800 border-blue-200',
                            'MYPE'    => 'bg-blue-100 text-blue-800 border-blue-200',
                            'RER'     => 'bg-amber-100 text-amber-800 border-amber-200',
                            'RUS'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'RG'      => 'bg-purple-100 text-purple-800 border-purple-200',
                            'General' => 'bg-purple-100 text-purple-800 border-purple-200',
                        ];
                        $badgeColor = $colores[$item['cliente']->regimen_tributario?->value] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                    @endphp

                    {{-- Fila del Cliente (Reactividad Alpine.js) --}}
                    <tr x-data="filaClienteMatriz({
                            clienteId: {{ $item['cliente']->id }},
                            anio: {{ $anio }},
                            meses: @js($item['meses']),
                            razonSocial: @js($item['cliente']->razon_social),
                            regimen: @js($item['cliente']->regimen_tributario?->value),
                            departamento: @js($item['cliente']->departamento),
                            rentaSugerida: @js(app(\App\Services\Catalogos\CatalogoSunat::class)->codigoRentaDelRegimen($item['cliente']->regimen_tributario)),
                        })"
                        class="group hover:bg-blue-50/20 transition-colors"
                        :class="{ 'marca-en-trabajo': Number($store.enTrabajo.id) === {{ $item['cliente']->id }} }"
                        x-init="if (Number($store.enTrabajo.id) === {{ $item['cliente']->id }}) { $nextTick(() => $el.scrollIntoView({ block: 'nearest' })) }">

                        {{-- COLUMNA 1: CLIENTE / RUC (STICKY LEFT) CON N° CORRELATIVO --}}
                        <td class="w-[22%] sticky left-0 bg-white z-10 px-2.5 py-2 border-r border-b border-gray-300 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.12)] whitespace-nowrap overflow-hidden">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                    <x-marca-en-trabajo :cliente-id="$item['cliente']->id" />
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
                            <div
                                x-ref="tooltipDeuda"
                                x-show="hoverMes !== null"
                                x-cloak
                                data-menu-flotante
                                class="fixed z-50 pointer-events-none rounded-2xl bg-white p-3.5 text-left text-gray-800 shadow-2xl border border-gray-200 min-w-[320px] max-w-sm"
                            >
                                <div class="mb-2 flex items-center justify-between border-b border-gray-100 pb-2">
                                    <span class="text-[11px] font-bold text-rose-700">Deuda SUNAT pendiente</span>
                                    <span class="font-mono text-[10px] font-bold text-gray-600" x-text="hoverMes === null ? '' : meses[hoverMes].periodo"></span>
                                </div>
                                <template x-for="deudaTip in deudasDelTip()" :key="deudaTip.localKey || deudaTip.id || deudaTip.tributo_codigo">
                                    <div class="mb-1 flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-2 py-1 text-xs">
                                        <span class="truncate font-bold" x-text="deudaTip.tributo_nombre_corto || deudaTip.tributo_nombre || 'Tributo'"></span>
                                        <span class="font-mono font-bold text-rose-600" x-text="moneda(deudaTip.saldo_pendiente)"></span>
                                    </div>
                                </template>
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
                                        <div class="relative flex-1 flex items-center justify-center">
                                            <button
                                                type="button"
                                                data-abre-dj
                                                @click="abrirPanelDj($event, {{ $idx }})"
                                                @mouseenter="mostrarTipDeuda($event, {{ $idx }})"
                                                @mouseleave="ocultarTipDeuda()"
                                                :title="!saldoPendienteMes({{ $idx }}) ? ('DJ: ' + (meses[{{ $idx }}].estado || 'Pendiente')) : null"
                                                class="relative inline-flex h-6 w-6 min-w-6 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs transition-transform select-none hover:scale-110 focus:outline-none active:scale-95"
                                            >
                                                <span x-text="meses[{{ $idx }}].estado === 'Declarado' ? '🟢' : (meses[{{ $idx }}].estado === 'Procesando' ? '🟡' : '⚪')">
                                                    {{ match ($m['estado'] ?? 'Pendiente') { 'Declarado' => '🟢', 'Procesando' => '🟡', default => '⚪' } }}
                                                </span>
                                                <svg
                                                    x-show="anilloRojo({{ $idx }}) || anilloVioleta({{ $idx }})"
                                                    x-cloak
                                                    class="absolute inset-0 h-6 w-6 pointer-events-none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle cx="12" cy="12" r="6.8" fill="none" stroke-width="2.4" :stroke="anilloRojo({{ $idx }}) ? '#ef4444' : '#7c3aed'" />
                                                </svg>
                                            </button>
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
                                                @click.outside="if (!$el._clicInicioDentro) openRH = false"
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

    <div x-data="controlCeldaDJ(0, 0, [mesVacioPanel()], @js($catalogoTributos), '', '', '', @js(app(\App\Services\Catalogos\CatalogoSunat::class)->codigosDeLiquidacion()))"
         @abrir-panel-dj.window="abrirDesde($event.detail)">
        @include('control.partials.menu-dj')
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
                razonSocial: config.razonSocial || '',
                regimen: config.regimen || '',
                departamento: config.departamento || '',
                rentaSugerida: config.rentaSugerida || '',
                hoverMes: null,

                saldoPendienteMes(idx) {
                    const mes = this.meses[idx];
                    return !!mes && !!mes.tiene_deuda && parseFloat(mes.saldo_pendiente ?? mes.monto_deuda_inicial ?? 0) > 0;
                },

                anilloRojo(idx) {
                    const mes = this.meses[idx];
                    return !!mes && (mes.estado === 'Declarado' || mes.estado === 'Procesando') && this.saldoPendienteMes(idx);
                },

                anilloVioleta(idx) {
                    const mes = this.meses[idx];
                    if (!mes || (mes.estado !== 'Declarado' && mes.estado !== 'Procesando') || !mes.tiene_deuda || this.saldoPendienteMes(idx)) {
                        return false;
                    }
                    return parseFloat(mes.total_abonado ?? 0) > 0;
                },

                deudasDelTip() {
                    if (this.hoverMes === null) {
                        return [];
                    }
                    return (this.meses[this.hoverMes].deudas || []).filter((deuda) => parseFloat(deuda.saldo_pendiente || 0) > 0);
                },

                moneda(val) {
                    const n = parseFloat(val || 0);
                    return 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                mostrarTipDeuda(event, idx) {
                    if (!this.saldoPendienteMes(idx)) {
                        return;
                    }
                    this.hoverMes = idx;
                    this.$nextTick(() => {
                        if (this.$refs.tooltipDeuda && typeof window.ubicarMenuFlotante === 'function') {
                            window.ubicarMenuFlotante(this.$refs.tooltipDeuda, event.currentTarget);
                        }
                    });
                },

                ocultarTipDeuda() {
                    this.hoverMes = null;
                },

                abrirPanelDj(event, idx) {
                    this.hoverMes = null;
                    const mes = this.meses[idx];
                    window.dispatchEvent(new CustomEvent('abrir-panel-dj', {
                        detail: {
                            clienteId: this.clienteId,
                            mesIdx: idx,
                            meses: this.meses,
                            regimen: this.regimen,
                            rentaSugerida: this.rentaSugerida,
                            departamento: this.departamento,
                            razonSocial: this.razonSocial,
                            etiquetaPeriodo: (mes.corto || '') + '-' + String(this.anio).slice(-2),
                            anchor: event.currentTarget,
                        },
                    }));
                },

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
                                estado_compras_ventas: mes.sire || 'Pendiente',
                                estado_pdt621: mes.pdt || 'Pendiente',
                                estado_ple: mes.ple || 'Pendiente',
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar estado.');
                        }
                        if (data.operacion_id) {
                            mes.operacion_id = data.operacion_id;
                        }
                        if (data.estado) {
                            mes.estado = data.estado;
                        }
                        if (data.sire) {
                            mes.sire = data.sire;
                        }
                        if (data.pdt) {
                            mes.pdt = data.pdt;
                        }
                        if (data.ple) {
                            mes.ple = data.ple;
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

        function mesVacioPanel() {
            return {
                tiene_deuda: false,
                tributo_codigo: '1011',
                vencimiento_deuda: '',
                vencimiento_oficial_pdt: '',
                es_igv_justo: false,
                monto_deuda_inicial: 0,
                abonos: [],
                deudas: [],
                saldo_pendiente: 0,
                total_abonado: 0,
                estado: 'Pendiente',
                sire: 'Pendiente',
                pdt: 'Pendiente',
                ple: 'Pendiente',
                periodo: '',
                corto: '',
                liquidacion: null,
            };
        }

        function controlCeldaDJ(clienteId, mesIdx, meses, declaraciones, regimen, rentaSugerida, departamento, codigosLiquidacion) {
            return {
                clienteId: clienteId,
                mesIdx: mesIdx,
                meses: meses,
                declaraciones: declaraciones || [],
                regimen: regimen || '',
                rentaSugerida: rentaSugerida || '',
                departamento: departamento || '',
                razonSocial: '',
                etiquetaPeriodo: '',
                codigosLiquidacion: codigosLiquidacion || ['1011', '1016', '3121', '3111', '3031', '3011', '5210', '5310', '3042', '3052', '6098', 'AFP'],
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

                // --- Hoja de Liquidación Tributaria (PDT 621 & PLAME 601) ---
                panelLiquidacionAbierto: false,
                panelLiquidacionPlameAbierto: false,
                tabLiquidacion: 'igv',
                savingLiquidacion: false,
                guardadoLiquidacionExitoso: false,
                saldoFavorSugerido: 0,
                retencionesSugeridas: 0,
                percepcionesSugeridas: 0,
                rentaSugeridaM1: 0,
                itanSugeridoM1: 0,
                liquidacion: {
                    es_zona_selva: false,
                    ventas_base: '',
                    igv_ventas: '',
                    igv_descuentos_base: '',
                    igv_descuentos: '',
                    ventas_no_gravadas: '',
                    igv_ventas_ley_base: '',
                    igv_ventas_ley: '',
                    compras_base: '',
                    igv_compras: '',
                    igv_compras_ley_base: '',
                    igv_compras_ley: '',
                    igv_compras_mixtas_base: '',
                    igv_compras_mixtas: '',
                    igv_importaciones_base: '',
                    igv_importaciones: '',
                    compras_destino_no_gravadas: '',
                    igv_importaciones_mixtas_base: '',
                    igv_importaciones_mixtas: '',
                    importaciones_destino_no_gravadas: '',
                    compras_internas_no_gravadas: '',
                    compras_importadas_no_gravadas: '',
                    igv_saldo_favor_anterior: '',
                    igv_retenciones_saldo_anterior: '',
                    igv_retenciones: '',
                    igv_retenciones_saldo_siguiente: 0,
                    igv_percepciones_saldo_anterior: '',
                    igv_percepciones: '',
                    igv_percepciones_saldo_siguiente: 0,
                    igv_saldo_favor_siguiente: 0,
                    igv_resultante_pagar: 0,
                    renta_base_ingresos: '',
                    renta_tasa: 1.0,
                    renta_calculada: '',
                    renta_saldo_favor_anterior: '',
                    renta_itan_saldo_anterior: '',
                    renta_compensacion_itan: '',
                    renta_itan_saldo_siguiente: 0,
                    renta_retenciones: '',
                    renta_saldo_favor_siguiente: 0,
                    renta_resultante_pagar: 0,
                    plame_essalud_base: '',
                    plame_essalud_a_pagar: '',
                    plame_onp_a_pagar: '',
                    plame_renta_4ta_base: '',
                    plame_renta_4ta_a_pagar: '',
                    plame_renta_5ta_a_pagar: '',
                    plame_afp_a_pagar: '',
                    notas_liquidacion: '',
                },
                importacion: {
                    '621': { abierta: false, cargando: false, error: '', resultado: null, aplicada: false, marcarDeclarado: true },
                    '601': { abierta: false, cargando: false, error: '', resultado: null, aplicada: false, marcarDeclarado: true },
                },
                preservarSunat: null,
                determinacionSunat: { igv: false, renta: false },
                otrasCasillasIgv: false,
                cruceDj: { igv: null, renta: null },

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
                    this.inicializarLiquidacion();
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
                            this.panelLiquidacionAbierto = false;
                            this.panelLiquidacionPlameAbierto = false;
                            this.deudas.forEach((deuda) => this.revertirCampos(deuda));
                            this.deudas = this.deudas.filter((deuda) => this.deudaPersistida(deuda));
                            this.deudaAbiertaKey = null;
                        }
                    });
                    this.$watch('deudaAbiertaKey', (nueva, anterior) => {
                        if (anterior && anterior !== nueva) {
                            const previa = this.deudas.find((deuda) => deuda.localKey === anterior);
                            if (previa) {
                                this.revertirCampos(previa);
                            }
                        }
                        if (nueva !== anterior) {
                            this.encuadrarMenuDJ();
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
                    return (m.estado === 'Declarado' || m.estado === 'Procesando') && this.tieneDeudaConSaldo();
                },

                deudaPagadaDeclarada() {
                    const m = this.meses[this.mesIdx];
                    if ((m.estado !== 'Declarado' && m.estado !== 'Procesando') || !m.tiene_deuda || this.tieneDeudaConSaldo()) {
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

                abrirDesde(detail) {
                    const mismo = this.openDJ && this.clienteId === detail.clienteId && this.mesIdx === detail.mesIdx;
                    this.clienteId = detail.clienteId;
                    this.mesIdx = detail.mesIdx;
                    this.meses = detail.meses;
                    this.regimen = detail.regimen || '';
                    this.rentaSugerida = detail.rentaSugerida || '';
                    this.departamento = detail.departamento || '';
                    this.razonSocial = detail.razonSocial || '';
                    this.etiquetaPeriodo = detail.etiquetaPeriodo || '';
                    if (mismo) {
                        this.openDJ = false;
                        return;
                    }
                    this.hoverDeuda = false;
                    this.panelLiquidacionAbierto = false;
                    this.panelLiquidacionPlameAbierto = false;
                    this.deudaAbiertaKey = null;
                    this.deudaCargada = false;
                    this.deudas = [];
                    this.activeTab = 'declaracion';
                    this.importacion['621'] = { abierta: false, cargando: false, error: '', resultado: null, aplicada: false, marcarDeclarado: true };
                    this.importacion['601'] = { abierta: false, cargando: false, error: '', resultado: null, aplicada: false, marcarDeclarado: true };
                    this.cruceDj = { igv: null, renta: null };
                    this.preservarSunat = null;
                    this.openDJ = true;
                    this.sincronizarDesdeMes();
                    this.hidratarDeudas(this.meses[this.mesIdx].deudas || []);
                    this.abonos = this.meses[this.mesIdx].abonos || [];
                    this.$nextTick(() => {
                        if (this.$refs.menuDJ && detail.anchor) {
                            window.ubicarMenuFlotante(this.$refs.menuDJ, detail.anchor);
                        }
                    });
                    if (this.tieneDeuda && !this.deudaCargada) {
                        this.cargarDeuda();
                    }
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
                                estado_compras_ventas: mes.sire || 'Pendiente',
                                estado_pdt621: mes.pdt || 'Pendiente',
                                estado_ple: mes.ple || 'Pendiente',
                            }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar estado.');
                        }
                        if (data.operacion_id) {
                            mes.operacion_id = data.operacion_id;
                        }
                        if (data.estado) {
                            mes.estado = data.estado;
                        }
                        if (data.kpis) {
                            window.dispatchEvent(new CustomEvent('kpis-actualizados', { detail: data.kpis }));
                        }
                    } catch (err) {
                        alert('No se pudo actualizar el estado: ' + err.message);
                    }
                },

                abrirMenuDJ(ancla) {
                    this.hoverDeuda = false;
                    this.panelLiquidacionAbierto = false;
                    this.panelLiquidacionPlameAbierto = false;
                    this.deudaAbiertaKey = null;
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
                    this.panelLiquidacionAbierto = false;
                    this.panelLiquidacionPlameAbierto = false;
                    this.deudaAbiertaKey = null;
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
                    this.inicializarLiquidacion();
                },

                tooltipDJ() {
                    if (this.tieneDeudaConSaldo()) {
                        return null;
                    }
                    const m = this.meses[this.mesIdx];
                    return 'DJ: ' + (m.estado || 'Pendiente');
                },

                calcularEstadoMaestro(sire, pdt, ple) {
                    const s = sire || 'Pendiente';
                    const p = pdt || 'Pendiente';
                    const l = ple || 'Pendiente';
                    if (s === 'Declarado' && p === 'Declarado' && l === 'Declarado') {
                        return 'Declarado';
                    }
                    if (s === 'Procesando' || p === 'Procesando' || l === 'Procesando'
                        || s === 'Declarado' || p === 'Declarado' || l === 'Declarado') {
                        return 'Procesando';
                    }
                    return 'Pendiente';
                },

                async cambiarEstadoIndividual(campo, nuevoEstado) {
                    const m = this.meses[this.mesIdx];
                    m[campo] = nuevoEstado;
                    m.estado = this.calcularEstadoMaestro(m.sire, m.pdt, m.ple);
                    if (typeof this.actualizarEstadoMaestro === 'function') {
                        await this.actualizarEstadoMaestro(this.mesIdx);
                    }
                },

                async marcarTodoEstado(nuevoEstado) {
                    const m = this.meses[this.mesIdx];
                    m.sire = nuevoEstado;
                    m.pdt = nuevoEstado;
                    m.ple = nuevoEstado;
                    m.estado = nuevoEstado;
                    if (typeof this.actualizarEstadoMaestro === 'function') {
                        await this.actualizarEstadoMaestro(this.mesIdx);
                    }
                },

                async cambiarEstado(nuevoEstado) {
                    await this.marcarTodoEstado(nuevoEstado);
                    this.openDJ = false;
                },

                hayTributoGuardado() {
                    const locales = (this.deudas || []).some((deuda) => !!deuda.id);
                    const delMes = (this.meses[this.mesIdx].deudas || []).some((deuda) => !!deuda.id);
                    return locales || delMes;
                },

                encuadrarMenuDJ() {
                    const menu = this.$refs.menuDJ;
                    if (!menu || !menu._anclaMenu || typeof window.ubicarMenuFlotante !== 'function') {
                        return;
                    }
                    this.$nextTick(() => {
                        if (!this.openDJ || !menu._anclaMenu) {
                            return;
                        }
                        window.ubicarMenuFlotante(menu, menu._anclaMenu);
                    });
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
                        nuevoMedio: '',
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
                    const guardadas = (lista || [])
                        .filter(d => d && (d.id || (parseFloat(d.monto_deuda_inicial) || 0) > 0 || (d.abonos && d.abonos.length > 0)))
                        .map(d => this.normalizarDeuda(d));
                    const codigos = new Set(guardadas.map(d => d.tributo_codigo));
                    const borradores = (this.deudas || []).filter((deuda) => !deuda.id && (parseFloat(deuda.borrador_monto || deuda.monto_deuda_inicial || 0) > 0 || (deuda.abonos && deuda.abonos.length > 0)) && !codigos.has(deuda.tributo_codigo) && !codigos.has(deuda.borrador_codigo));
                    this.deudas = guardadas.concat(borradores);
                    this.meses[this.mesIdx].deudas = guardadas;
                    if (!this.deudas.some((deuda) => deuda.localKey === this.deudaAbiertaKey)) {
                        this.deudaAbiertaKey = null;
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
                        this.montoDeuda = this.formatearNumeroConComas(data.monto_deuda_inicial);
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

                    this.cacheDeuda = {
                        montoDeuda: this.montoDeuda,
                        monto_deuda_inicial: parseFloat(mes.monto_deuda_inicial || 0),
                        vencimientoDeuda: this.vencimientoDeuda,
                        vencimiento_deuda_dmy: mes.vencimiento_deuda_dmy || '',
                        vencimiento_oficial_pdt: this.vencimientoOficialPdt,
                        tributoCodigo: this.tributoCodigo,
                        esIgvJusto: this.esIgvJusto,
                        editandoFechaManual: this.editandoFechaManual,
                        total_abonado: parseFloat(mes.total_abonado || 0),
                        saldo_pendiente: parseFloat(mes.saldo_pendiente || 0),
                        abonos: [...(this.abonos || [])]
                    };
                },

                alCambiarTributo(deuda) {
                    if (!deuda.borrador_codigo) {
                        if (!this.deudaPersistida(deuda)) {
                            deuda.tributo_nombre_corto = 'Nuevo tributo';
                        }
                        deuda.borrador_igv = false;
                        this.encuadrarMenuDJ();
                        return;
                    }
                    if (!this.deudaPersistida(deuda)) {
                        deuda.tributo_nombre_corto = this.nombreTributo(deuda.borrador_codigo);
                        deuda.declaracion = this.declaracionDeCodigo(deuda.borrador_codigo);
                    }
                    if (deuda.borrador_codigo !== '1011') {
                        deuda.borrador_igv = false;
                    }
                    this.encuadrarMenuDJ();
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
                        this.encuadrarMenuDJ();
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
                        this.encuadrarMenuDJ();
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
                    if (!deuda.nuevoMedio) {
                        alert('Seleccione el medio de pago: detracciones, caja, bancos, compensación de renta anual o ITAN.');
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
                                medio_pago: deuda.nuevoMedio,
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
                        deuda.nuevoMedio = '';
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
                },

                // --- Métodos de Hoja de Liquidación Tributaria (PDT 621 & PLAME 601) ---
                inicializarLiquidacion() {
                    const mes = this.meses[this.mesIdx];
                    this.olvidarImportacionSunat();
                    this.saldoFavorSugerido = parseFloat(mes?.saldo_favor_anterior_sugerido || 0);
                    this.retencionesSugeridas = parseFloat(mes?.retenciones_saldo_anterior_sugerido || 0);
                    this.percepcionesSugeridas = parseFloat(mes?.percepciones_saldo_anterior_sugerido || 0);
                    this.rentaSugeridaM1 = parseFloat(mes?.renta_saldo_anterior_sugerido || 0);
                    this.itanSugeridoM1 = parseFloat(mes?.itan_saldo_anterior_sugerido || 0);

                    let tasaPorDefecto = 1.0;
                    const reg = (this.regimen || '').toUpperCase();
                    if (reg === 'RER' || reg === 'GENERAL' || reg === 'RG') {
                        tasaPorDefecto = 1.5;
                    }

                    const depto = (this.departamento || '').toUpperCase().trim();
                    const esSelvaSugerido = ['LORETO', 'UCAYALI', 'SAN MARTIN', 'SAN MARTÍN', 'AMAZONAS', 'MADRE DE DIOS'].includes(depto);
                    const guardada = mes?.liquidacion || null;
                    const marcaSunat = guardada ? {
                        igv: !!guardada.igv_determinado_por_sunat,
                        renta: !!guardada.renta_determinado_por_sunat,
                        igv_resultante_pagar: parseFloat(guardada.igv_resultante_pagar || 0),
                        igv_saldo_favor_siguiente: parseFloat(guardada.igv_saldo_favor_siguiente || 0),
                        igv_retenciones_saldo_siguiente: parseFloat(guardada.igv_retenciones_saldo_siguiente || 0),
                        igv_percepciones_saldo_siguiente: parseFloat(guardada.igv_percepciones_saldo_siguiente || 0),
                        renta_calculada: parseFloat(guardada.renta_calculada || 0),
                        renta_resultante_pagar: parseFloat(guardada.renta_resultante_pagar || 0),
                        renta_saldo_favor_siguiente: parseFloat(guardada.renta_saldo_favor_siguiente || 0),
                        renta_tributo_codigo: guardada.renta_tributo_codigo || null,
                    } : null;

                    if (mes && mes.liquidacion) {
                        const l = mes.liquidacion;
                        // Arrastre 100% automático: si en base de datos el saldo anterior guardado es 0 o vacío, pero existe saldo sugerido M-1, jalarlo automáticamente
                        const sfAnterior = (parseFloat(l.igv_saldo_favor_anterior || 0) > 0)
                            ? l.igv_saldo_favor_anterior
                            : this.saldoFavorSugerido;

                        const retAnterior = (parseFloat(l.igv_retenciones_saldo_anterior || 0) > 0)
                            ? l.igv_retenciones_saldo_anterior
                            : this.retencionesSugeridas;

                        const percAnterior = (parseFloat(l.igv_percepciones_saldo_anterior || 0) > 0)
                            ? l.igv_percepciones_saldo_anterior
                            : this.percepcionesSugeridas;

                        const rentaAnt = (parseFloat(l.renta_saldo_favor_anterior || 0) > 0)
                            ? l.renta_saldo_favor_anterior
                            : this.rentaSugeridaM1;

                        const itanAnt = (parseFloat(l.renta_itan_saldo_anterior || 0) > 0)
                            ? l.renta_itan_saldo_anterior
                            : this.itanSugeridoM1;

                        this.liquidacion = {
                            es_zona_selva: !!l.es_zona_selva,
                            ventas_base: l.ventas_base > 0 ? this.formatearNumeroConComas(l.ventas_base) : '',
                            igv_ventas: l.igv_ventas > 0 ? this.formatearNumeroConComas(l.igv_ventas) : (l.es_zona_selva ? '0.00' : ''),
                            igv_descuentos_base: l.igv_descuentos_base > 0 ? this.formatearNumeroConComas(l.igv_descuentos_base) : '',
                            igv_descuentos: l.igv_descuentos > 0 ? this.formatearNumeroConComas(l.igv_descuentos) : '',
                            ventas_no_gravadas: l.ventas_no_gravadas > 0 ? this.formatearNumeroConComas(l.ventas_no_gravadas) : '',
                            igv_ventas_ley_base: l.igv_ventas_ley_base > 0 ? this.formatearNumeroConComas(l.igv_ventas_ley_base) : '',
                            igv_ventas_ley: l.igv_ventas_ley > 0 ? this.formatearNumeroConComas(l.igv_ventas_ley) : '',
                            compras_base: l.compras_base > 0 ? this.formatearNumeroConComas(l.compras_base) : '',
                            igv_compras: l.igv_compras > 0 ? this.formatearNumeroConComas(l.igv_compras) : (l.es_zona_selva ? '0.00' : ''),
                            igv_compras_ley_base: l.igv_compras_ley_base > 0 ? this.formatearNumeroConComas(l.igv_compras_ley_base) : '',
                            igv_compras_ley: l.igv_compras_ley > 0 ? this.formatearNumeroConComas(l.igv_compras_ley) : '',
                            igv_compras_mixtas_base: l.igv_compras_mixtas_base > 0 ? this.formatearNumeroConComas(l.igv_compras_mixtas_base) : '',
                            igv_compras_mixtas: l.igv_compras_mixtas > 0 ? this.formatearNumeroConComas(l.igv_compras_mixtas) : '',
                            igv_importaciones_base: l.igv_importaciones_base > 0 ? this.formatearNumeroConComas(l.igv_importaciones_base) : '',
                            igv_importaciones: l.igv_importaciones > 0 ? this.formatearNumeroConComas(l.igv_importaciones) : '',
                            compras_destino_no_gravadas: l.compras_destino_no_gravadas > 0 ? this.formatearNumeroConComas(l.compras_destino_no_gravadas) : '',
                            igv_importaciones_mixtas_base: l.igv_importaciones_mixtas_base > 0 ? this.formatearNumeroConComas(l.igv_importaciones_mixtas_base) : '',
                            igv_importaciones_mixtas: l.igv_importaciones_mixtas > 0 ? this.formatearNumeroConComas(l.igv_importaciones_mixtas) : '',
                            importaciones_destino_no_gravadas: l.importaciones_destino_no_gravadas > 0 ? this.formatearNumeroConComas(l.importaciones_destino_no_gravadas) : '',
                            compras_internas_no_gravadas: l.compras_internas_no_gravadas > 0 ? this.formatearNumeroConComas(l.compras_internas_no_gravadas) : '',
                            compras_importadas_no_gravadas: l.compras_importadas_no_gravadas > 0 ? this.formatearNumeroConComas(l.compras_importadas_no_gravadas) : '',
                            igv_saldo_favor_anterior: sfAnterior > 0 ? this.formatearNumeroConComas(sfAnterior) : '',
                            igv_retenciones_saldo_anterior: retAnterior > 0 ? this.formatearNumeroConComas(retAnterior) : '',
                            igv_retenciones: l.igv_retenciones > 0 ? this.formatearNumeroConComas(l.igv_retenciones) : '',
                            igv_retenciones_saldo_siguiente: parseFloat(l.igv_retenciones_saldo_siguiente || 0),
                            igv_percepciones_saldo_anterior: percAnterior > 0 ? this.formatearNumeroConComas(percAnterior) : '',
                            igv_percepciones: l.igv_percepciones > 0 ? this.formatearNumeroConComas(l.igv_percepciones) : '',
                            igv_percepciones_saldo_siguiente: parseFloat(l.igv_percepciones_saldo_siguiente || 0),
                            igv_saldo_favor_siguiente: parseFloat(l.igv_saldo_favor_siguiente || 0),
                            igv_resultante_pagar: parseFloat(l.igv_resultante_pagar || 0),
                            renta_base_ingresos: l.renta_base_ingresos > 0 ? this.formatearNumeroConComas(l.renta_base_ingresos) : '',
                            renta_tasa: l.renta_tasa > 0 ? l.renta_tasa : tasaPorDefecto,
                            renta_calculada: l.renta_calculada > 0 ? this.formatearNumeroConComas(l.renta_calculada) : '',
                            renta_saldo_favor_anterior: rentaAnt > 0 ? this.formatearNumeroConComas(rentaAnt) : '',
                            renta_itan_saldo_anterior: itanAnt > 0 ? this.formatearNumeroConComas(itanAnt) : '',
                            renta_compensacion_itan: l.renta_compensacion_itan > 0 ? this.formatearNumeroConComas(l.renta_compensacion_itan) : '',
                            renta_itan_saldo_siguiente: parseFloat(l.renta_itan_saldo_siguiente || 0),
                            renta_retenciones: l.renta_retenciones > 0 ? this.formatearNumeroConComas(l.renta_retenciones) : '',
                            renta_saldo_favor_siguiente: parseFloat(l.renta_saldo_favor_siguiente || 0),
                            renta_resultante_pagar: parseFloat(l.renta_resultante_pagar || 0),
                            plame_essalud_base: l.plame_essalud_base > 0 ? this.formatearNumeroConComas(l.plame_essalud_base) : '',
                            plame_essalud_a_pagar: l.plame_essalud_a_pagar > 0 ? this.formatearNumeroConComas(l.plame_essalud_a_pagar) : '',
                            plame_onp_a_pagar: l.plame_onp_a_pagar > 0 ? this.formatearNumeroConComas(l.plame_onp_a_pagar) : '',
                            plame_renta_4ta_base: l.plame_renta_4ta_base > 0 ? this.formatearNumeroConComas(l.plame_renta_4ta_base) : '',
                            plame_renta_4ta_a_pagar: l.plame_renta_4ta_a_pagar > 0 ? this.formatearNumeroConComas(l.plame_renta_4ta_a_pagar) : '',
                            plame_renta_5ta_a_pagar: l.plame_renta_5ta_a_pagar > 0 ? this.formatearNumeroConComas(l.plame_renta_5ta_a_pagar) : '',
                            plame_afp_a_pagar: l.plame_afp_a_pagar > 0 ? this.formatearNumeroConComas(l.plame_afp_a_pagar) : '',
                            notas_liquidacion: l.notas_liquidacion || '',
                        };
                    } else {
                        // Mes no inicializado: Arrastre 100% automático desde M-1
                        this.liquidacion = {
                            es_zona_selva: esSelvaSugerido,
                            ventas_base: '',
                            igv_ventas: esSelvaSugerido ? '0.00' : '',
                            igv_descuentos_base: '',
                            igv_descuentos: '',
                            ventas_no_gravadas: '',
                            igv_ventas_ley_base: '',
                            igv_ventas_ley: '',
                            compras_base: '',
                            igv_compras: esSelvaSugerido ? '0.00' : '',
                            igv_compras_ley_base: '',
                            igv_compras_ley: '',
                            igv_compras_mixtas_base: '',
                            igv_compras_mixtas: '',
                            igv_importaciones_base: '',
                            igv_importaciones: '',
                            compras_destino_no_gravadas: '',
                            igv_importaciones_mixtas_base: '',
                            igv_importaciones_mixtas: '',
                            importaciones_destino_no_gravadas: '',
                            compras_internas_no_gravadas: '',
                            compras_importadas_no_gravadas: '',
                            igv_saldo_favor_anterior: this.saldoFavorSugerido > 0 ? this.formatearNumeroConComas(this.saldoFavorSugerido) : '',
                            igv_retenciones_saldo_anterior: this.retencionesSugeridas > 0 ? this.formatearNumeroConComas(this.retencionesSugeridas) : '',
                            igv_retenciones: '',
                            igv_retenciones_saldo_siguiente: 0,
                            igv_percepciones_saldo_anterior: this.percepcionesSugeridas > 0 ? this.formatearNumeroConComas(this.percepcionesSugeridas) : '',
                            igv_percepciones: '',
                            igv_percepciones_saldo_siguiente: 0,
                            igv_saldo_favor_siguiente: 0,
                            igv_resultante_pagar: 0,
                            renta_base_ingresos: '',
                            renta_tasa: tasaPorDefecto,
                            renta_calculada: '',
                            renta_saldo_favor_anterior: this.rentaSugeridaM1 > 0 ? this.formatearNumeroConComas(this.rentaSugeridaM1) : '',
                            renta_itan_saldo_anterior: this.itanSugeridoM1 > 0 ? this.formatearNumeroConComas(this.itanSugeridoM1) : '',
                            renta_compensacion_itan: '',
                            renta_itan_saldo_siguiente: 0,
                            renta_retenciones: '',
                            renta_saldo_favor_siguiente: 0,
                            renta_resultante_pagar: 0,
                            plame_essalud_base: '',
                            plame_essalud_a_pagar: '',
                            plame_onp_a_pagar: '',
                            plame_renta_4ta_base: '',
                            plame_renta_4ta_a_pagar: '',
                            plame_renta_5ta_a_pagar: '',
                            plame_afp_a_pagar: '',
                            notas_liquidacion: '',
                        };
                    }
                    this.otrasCasillasIgv = [
                        'igv_ventas_ley', 'igv_compras_ley', 'igv_compras_mixtas', 'igv_importaciones',
                        'igv_importaciones_mixtas', 'compras_destino_no_gravadas', 'importaciones_destino_no_gravadas',
                    ].some((campo) => this.limpiarMoneda(this.liquidacion[campo]) > 0);
                    this.recalcularTotalesLiquidacion();
                    this.anclarDeterminacionGuardada(marcaSunat);
                },

                onVentasBaseInput() {
                    const base = this.limpiarMoneda(this.liquidacion.ventas_base);
                    if (this.liquidacion.es_zona_selva) {
                        this.liquidacion.igv_ventas = '0.00';
                    } else if (base > 0) {
                        const igv = Math.round(base * 0.18 * 100) / 100;
                        this.liquidacion.igv_ventas = this.formatearNumeroConComas(igv);
                    } else {
                        this.liquidacion.igv_ventas = '';
                    }

                    this.sincronizarBaseRenta();
                    this.recalcularTotalesLiquidacion();
                },

                onDescuentosBaseInput() {
                    const base = this.limpiarMoneda(this.liquidacion.igv_descuentos_base);
                    if (!this.liquidacion.es_zona_selva && base > 0) {
                        this.liquidacion.igv_descuentos = this.formatearNumeroConComas(Math.round(base * 0.18 * 100) / 100);
                    } else if (base <= 0) {
                        this.liquidacion.igv_descuentos = '';
                    }
                    this.sincronizarBaseRenta();
                    this.recalcularTotalesLiquidacion();
                },

                sincronizarBaseRenta() {
                    const base = this.limpiarMoneda(this.liquidacion.ventas_base);
                    const descuentos = this.limpiarMoneda(this.liquidacion.igv_descuentos_base);
                    const noGravadas = this.limpiarMoneda(this.liquidacion.ventas_no_gravadas);
                    const totalIngresos = Math.max(0, Math.round((base - descuentos + noGravadas) * 100) / 100);
                    this.liquidacion.renta_base_ingresos = totalIngresos > 0 ? this.formatearNumeroConComas(totalIngresos) : '';
                },

                onVentasNoGravadasInput() {
                    this.sincronizarBaseRenta();
                    this.recalcularTotalesLiquidacion();
                },

                onComprasBaseInput() {
                    if (this.liquidacion.es_zona_selva) {
                        this.liquidacion.igv_compras = '0.00';
                        this.recalcularTotalesLiquidacion();
                        return;
                    }
                    const base = this.limpiarMoneda(this.liquidacion.compras_base);
                    if (base > 0) {
                        const igv = Math.round(base * 0.18 * 100) / 100;
                        this.liquidacion.igv_compras = this.formatearNumeroConComas(igv);
                    } else {
                        this.liquidacion.igv_compras = '';
                    }
                    this.recalcularTotalesLiquidacion();
                },

                toggleZonaSelva() {
                    this.liquidacion.es_zona_selva = !this.liquidacion.es_zona_selva;
                    if (this.liquidacion.es_zona_selva) {
                        this.liquidacion.igv_ventas = '0.00';
                        this.liquidacion.igv_compras = '0.00';
                        this.liquidacion.igv_resultante_pagar = 0;
                        this.liquidacion.igv_saldo_favor_siguiente = 0;
                        this.liquidacion.igv_retenciones_saldo_siguiente = 0;
                        this.liquidacion.igv_percepciones_saldo_siguiente = 0;
                    } else {
                        const base = this.limpiarMoneda(this.liquidacion.ventas_base);
                        this.liquidacion.igv_ventas = base > 0 ? this.formatearNumeroConComas(Math.round(base * 0.18 * 100) / 100) : '';
                        const compras = this.limpiarMoneda(this.liquidacion.compras_base);
                        this.liquidacion.igv_compras = compras > 0 ? this.formatearNumeroConComas(Math.round(compras * 0.18 * 100) / 100) : '';
                    }
                    this.recalcularTotalesLiquidacion();
                },

                recalcularTotalesLiquidacion(origen = 'usuario') {
                    if (origen !== 'sunat') {
                        this.determinacionSunat.igv = false;
                        this.determinacionSunat.renta = false;
                    }
                    if (this.liquidacion.es_zona_selva) {
                        this.liquidacion.igv_ventas = '0.00';
                        this.liquidacion.igv_compras = '0.00';
                        this.liquidacion.igv_resultante_pagar = 0;
                        this.liquidacion.igv_saldo_favor_siguiente = 0;
                        this.liquidacion.igv_retenciones_saldo_siguiente = 0;
                        this.liquidacion.igv_percepciones_saldo_siguiente = 0;
                    } else {
                        const debito = Math.round((
                            this.limpiarMoneda(this.liquidacion.igv_ventas)
                            + this.limpiarMoneda(this.liquidacion.igv_ventas_ley)
                            - this.limpiarMoneda(this.liquidacion.igv_descuentos)
                        ) * 100) / 100;
                        const credito = Math.round((
                            this.limpiarMoneda(this.liquidacion.igv_compras)
                            + this.limpiarMoneda(this.liquidacion.igv_compras_ley)
                            + this.limpiarMoneda(this.liquidacion.igv_compras_mixtas)
                            + this.limpiarMoneda(this.liquidacion.igv_importaciones)
                            + this.limpiarMoneda(this.liquidacion.igv_importaciones_mixtas)
                        ) * 100) / 100;
                        const saldoAnterior = this.limpiarMoneda(this.liquidacion.igv_saldo_favor_anterior);

                        const retencionesMes = this.limpiarMoneda(this.liquidacion.igv_retenciones);
                        const retencionesAnt = this.limpiarMoneda(this.liquidacion.igv_retenciones_saldo_anterior);
                        const totalRetenciones = Math.round((retencionesAnt + retencionesMes) * 100) / 100;

                        const percepcionesMes = this.limpiarMoneda(this.liquidacion.igv_percepciones);
                        const percepcionesAnt = this.limpiarMoneda(this.liquidacion.igv_percepciones_saldo_anterior);
                        const totalPercepciones = Math.round((percepcionesAnt + percepcionesMes) * 100) / 100;

                        const netoFiscal = Math.round((debito - credito - saldoAnterior) * 100) / 100;

                        if (netoFiscal < 0) {
                            this.liquidacion.igv_saldo_favor_siguiente = Math.round(Math.abs(netoFiscal) * 100) / 100;
                            this.liquidacion.igv_resultante_pagar = 0;
                            this.liquidacion.igv_retenciones_saldo_siguiente = totalRetenciones;
                            this.liquidacion.igv_percepciones_saldo_siguiente = totalPercepciones;
                        } else if (netoFiscal === 0) {
                            this.liquidacion.igv_saldo_favor_siguiente = 0;
                            this.liquidacion.igv_resultante_pagar = 0;
                            this.liquidacion.igv_retenciones_saldo_siguiente = totalRetenciones;
                            this.liquidacion.igv_percepciones_saldo_siguiente = totalPercepciones;
                        } else {
                            this.liquidacion.igv_saldo_favor_siguiente = 0;
                            if (totalRetenciones >= netoFiscal) {
                                this.liquidacion.igv_retenciones_saldo_siguiente = Math.round((totalRetenciones - netoFiscal) * 100) / 100;
                                this.liquidacion.igv_percepciones_saldo_siguiente = totalPercepciones;
                                this.liquidacion.igv_resultante_pagar = 0;
                            } else {
                                const resto = Math.round((netoFiscal - totalRetenciones) * 100) / 100;
                                this.liquidacion.igv_retenciones_saldo_siguiente = 0;
                                if (totalPercepciones >= resto) {
                                    this.liquidacion.igv_percepciones_saldo_siguiente = Math.round((totalPercepciones - resto) * 100) / 100;
                                    this.liquidacion.igv_resultante_pagar = 0;
                                } else {
                                    this.liquidacion.igv_percepciones_saldo_siguiente = 0;
                                    this.liquidacion.igv_resultante_pagar = Math.round((resto - totalPercepciones) * 100) / 100;
                                }
                            }
                        }
                    }

                    const reg = (this.regimen || '').toUpperCase();
                    const esRus = reg === 'RUS';
                    const esRer = reg === 'RER';

                    if (esRus) {
                        this.liquidacion.renta_base_ingresos = '';
                        this.liquidacion.renta_calculada = '';
                        this.liquidacion.renta_resultante_pagar = 0;
                        this.liquidacion.renta_saldo_favor_siguiente = 0;
                        this.liquidacion.renta_itan_saldo_siguiente = 0;
                    } else {
                        const ingresos = this.limpiarMoneda(this.liquidacion.renta_base_ingresos);
                        const tasa = parseFloat(this.liquidacion.renta_tasa || (esRer ? 1.5 : 1.0));
                        const rentaCalc = Math.round(ingresos * (tasa / 100));
                        this.liquidacion.renta_calculada = rentaCalc > 0 ? this.formatearNumeroConComas(rentaCalc) : (ingresos > 0 ? '0.00' : '');

                        if (esRer) {
                            const ret3ra = this.limpiarMoneda(this.liquidacion.renta_retenciones);
                            const itan = this.limpiarMoneda(this.liquidacion.renta_compensacion_itan);
                            const itanSaldoAnt = this.limpiarMoneda(this.liquidacion.renta_itan_saldo_anterior);
                            this.liquidacion.renta_resultante_pagar = Math.max(0, Math.round((rentaCalc - ret3ra - itan) * 100) / 100);
                            this.liquidacion.renta_saldo_favor_siguiente = 0;
                            this.liquidacion.renta_itan_saldo_siguiente = Math.max(0, Math.round((itanSaldoAnt - itan) * 100) / 100);
                        } else {
                            const saldoAnt = this.limpiarMoneda(this.liquidacion.renta_saldo_favor_anterior);
                            const itanSaldoAnt = this.limpiarMoneda(this.liquidacion.renta_itan_saldo_anterior);
                            const itanComp = this.limpiarMoneda(this.liquidacion.renta_compensacion_itan);
                            const ret3ra = this.limpiarMoneda(this.liquidacion.renta_retenciones);

                            // ITAN remanente para M+1 (Ley 28424 Art. 8)
                            this.liquidacion.renta_itan_saldo_siguiente = Math.max(0, Math.round((itanSaldoAnt - itanComp) * 100) / 100);

                            // Orden legal de aplicación de compensaciones en PDT 621:
                            const totalComp = Math.round((saldoAnt + itanComp + ret3ra) * 100) / 100;

                            if (totalComp >= rentaCalc) {
                                this.liquidacion.renta_resultante_pagar = 0;
                                const remanente = Math.round((totalComp - rentaCalc) * 100) / 100;
                                // Casilla 303: saldo a favor de renta que pasa al siguiente mes del ejercicio
                                this.liquidacion.renta_saldo_favor_siguiente = Math.min(saldoAnt, remanente);
                            } else {
                                this.liquidacion.renta_resultante_pagar = Math.round((rentaCalc - totalComp) * 100) / 100;
                                this.liquidacion.renta_saldo_favor_siguiente = 0;
                            }
                        }
                    }
                },

                aplicarSaldoAnteriorSugerido() {
                    if (this.saldoFavorSugerido > 0) {
                        this.liquidacion.igv_saldo_favor_anterior = this.formatearNumeroConComas(this.saldoFavorSugerido);
                        this.recalcularTotalesLiquidacion();
                    }
                },

                aplicarRetencionesSugeridas() {
                    if (this.retencionesSugeridas > 0) {
                        this.liquidacion.igv_retenciones_saldo_anterior = this.formatearNumeroConComas(this.retencionesSugeridas);
                        this.recalcularTotalesLiquidacion();
                    }
                },

                aplicarPercepcionesSugeridas() {
                    if (this.percepcionesSugeridas > 0) {
                        this.liquidacion.igv_percepciones_saldo_anterior = this.formatearNumeroConComas(this.percepcionesSugeridas);
                        this.recalcularTotalesLiquidacion();
                    }
                },

                aplicarRentaSugeridaM1() {
                    if (this.rentaSugeridaM1 > 0) {
                        this.liquidacion.renta_saldo_favor_anterior = this.formatearNumeroConComas(this.rentaSugeridaM1);
                        this.recalcularTotalesLiquidacion();
                    }
                },

                aplicarItanSugeridoM1() {
                    if (this.itanSugeridoM1 > 0) {
                        this.liquidacion.renta_itan_saldo_anterior = this.formatearNumeroConComas(this.itanSugeridoM1);
                        this.recalcularTotalesLiquidacion();
                    }
                },

                aplicarCompensacionItanTotal() {
                    const itanDisp = this.limpiarMoneda(this.liquidacion.renta_itan_saldo_anterior);
                    const rentaCalc = this.limpiarMoneda(this.liquidacion.renta_calculada);
                    const saldoAnt = this.limpiarMoneda(this.liquidacion.renta_saldo_favor_anterior);
                    const ret3ra = this.limpiarMoneda(this.liquidacion.renta_retenciones);

                    // Deuda de renta pendiente antes de aplicar ITAN
                    const deudaPrevia = Math.max(0, Math.round((rentaCalc - saldoAnt - ret3ra) * 100) / 100);
                    const compensar = Math.min(itanDisp, deudaPrevia > 0 ? deudaPrevia : itanDisp);
                    this.liquidacion.renta_compensacion_itan = compensar > 0 ? this.formatearNumeroConComas(compensar) : '';
                    this.recalcularTotalesLiquidacion();
                },

                onPlameEssaludBaseInput() {
                    const base = this.limpiarMoneda(this.liquidacion.plame_essalud_base);
                    if (base > 0) {
                        // En 2026, la RMV es S/ 1,130.00. El aporte legal mínimo de EsSalud (9%) es S/ 101.70
                        const essalud = Math.round(Math.max(base * 0.09, 101.70) * 100) / 100;
                        this.liquidacion.plame_essalud_a_pagar = this.formatearNumeroConComas(essalud);
                    } else {
                        this.liquidacion.plame_essalud_a_pagar = '';
                    }
                },

                onPlameRenta4taBaseInput() {
                    const base = this.limpiarMoneda(this.liquidacion.plame_renta_4ta_base);
                    if (base > 0) {
                        const renta4ta = Math.round(base * 0.08 * 100) / 100;
                        this.liquidacion.plame_renta_4ta_a_pagar = this.formatearNumeroConComas(renta4ta);
                    } else {
                        this.liquidacion.plame_renta_4ta_a_pagar = '';
                    }
                },

                totalPlamePagar() {
                    return Math.round((
                        this.limpiarMoneda(this.liquidacion.plame_essalud_a_pagar) +
                        this.limpiarMoneda(this.liquidacion.plame_onp_a_pagar) +
                        this.limpiarMoneda(this.liquidacion.plame_renta_4ta_a_pagar) +
                        this.limpiarMoneda(this.liquidacion.plame_renta_5ta_a_pagar) +
                        this.limpiarMoneda(this.liquidacion.plame_afp_a_pagar)
                    ) * 100) / 100;
                },

                rentaCodigoSugerido() {
                    if (this.rentaSugerida) {
                        return this.rentaSugerida;
                    }
                    const reg = (this.regimen || '').toUpperCase();
                    if (reg === 'RER') return '3111';
                    if (reg === 'GENERAL' || reg === 'RG') return '3031';
                    return '3121';
                },

                olvidarImportacionSunat() {
                    this.preservarSunat = null;
                    this.determinacionSunat.igv = false;
                    this.determinacionSunat.renta = false;
                    this.cruceDj = { igv: null, renta: null };
                    this.otrasCasillasIgv = false;
                    ['621', '601'].forEach((formulario) => {
                        this.importacion[formulario].abierta = false;
                        this.importacion[formulario].cargando = false;
                        this.importacion[formulario].error = '';
                        this.importacion[formulario].resultado = null;
                        this.importacion[formulario].aplicada = false;
                    });
                },

                anclarDeterminacionGuardada(marca) {
                    if (!marca || (!marca.igv && !marca.renta)) {
                        return;
                    }

                    const preservar = {};
                    if (marca.igv) {
                        preservar.igv_resultante_pagar = marca.igv_resultante_pagar;
                        preservar.igv_saldo_favor_siguiente = marca.igv_saldo_favor_siguiente;
                        preservar.igv_retenciones_saldo_siguiente = marca.igv_retenciones_saldo_siguiente;
                        preservar.igv_percepciones_saldo_siguiente = marca.igv_percepciones_saldo_siguiente;
                        this.determinacionSunat.igv = true;
                    }
                    if (marca.renta) {
                        preservar.renta_calculada = marca.renta_calculada;
                        preservar.renta_resultante_pagar = marca.renta_resultante_pagar;
                        preservar.renta_saldo_favor_siguiente = marca.renta_saldo_favor_siguiente;
                        if (marca.renta_tributo_codigo) {
                            preservar.renta_tributo_codigo = marca.renta_tributo_codigo;
                        }
                        this.determinacionSunat.renta = true;
                    }
                    this.preservarSunat = preservar;
                    this.restaurarOficiales(preservar);
                },

                soltarConstancia(formulario, event) {
                    const archivos = event.dataTransfer && event.dataTransfer.files
                        ? Array.from(event.dataTransfer.files)
                        : [];
                    this.enviarConstancia(formulario, archivos);
                },

                subirConstancia(formulario, event) {
                    const archivos = event.target.files ? Array.from(event.target.files) : [];
                    event.target.value = '';
                    this.enviarConstancia(formulario, archivos);
                },

                async enviarConstancia(formulario, archivos) {
                    if (!archivos.length) {
                        return;
                    }

                    const estado = this.importacion[formulario];
                    estado.cargando = true;
                    estado.error = '';
                    estado.resultado = null;
                    estado.aplicada = false;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';
                        const body = new FormData();
                        body.append('cliente_id', this.clienteId);
                        body.append('periodo', this.meses[this.mesIdx].periodo);
                        body.append('formulario', formulario);
                        archivos.slice(0, 3).forEach((archivo) => body.append('archivos[]', archivo));

                        const response = await fetch('{{ route('control.liquidacion.importar') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body,
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            const detalle = data.errors ? Object.values(data.errors).flat()[0] : null;
                            throw new Error(detalle || data.message || 'No se pudo leer la declaración.');
                        }

                        estado.resultado = data;
                        if (!data.bloqueado) {
                            await this.aplicarConstancia(formulario, data);
                        }
                    } catch (err) {
                        estado.error = err.message || 'No se pudo leer la declaración.';
                    } finally {
                        estado.cargando = false;
                    }
                },

                async aplicarConstancia(formulario, resultado) {
                    if (!resultado || resultado.bloqueado) {
                        return;
                    }

                    const campos = resultado.campos || {};
                    const montos = [
                        'ventas_base', 'igv_ventas', 'igv_descuentos_base', 'igv_descuentos',
                        'ventas_no_gravadas', 'igv_ventas_ley_base', 'igv_ventas_ley',
                        'compras_base', 'igv_compras', 'igv_compras_ley_base', 'igv_compras_ley',
                        'igv_compras_mixtas_base', 'igv_compras_mixtas', 'igv_importaciones_base', 'igv_importaciones',
                        'compras_destino_no_gravadas', 'igv_importaciones_mixtas_base', 'igv_importaciones_mixtas',
                        'importaciones_destino_no_gravadas', 'compras_internas_no_gravadas', 'compras_importadas_no_gravadas',
                        'igv_saldo_favor_anterior', 'igv_retenciones_saldo_anterior', 'igv_retenciones',
                        'igv_percepciones_saldo_anterior', 'igv_percepciones',
                        'renta_base_ingresos', 'renta_saldo_favor_anterior', 'renta_itan_saldo_anterior',
                        'renta_compensacion_itan', 'renta_retenciones',
                        'plame_essalud_base', 'plame_essalud_a_pagar', 'plame_onp_a_pagar',
                        'plame_renta_4ta_base', 'plame_renta_4ta_a_pagar', 'plame_renta_5ta_a_pagar', 'plame_afp_a_pagar',
                    ];
                    montos.forEach((campo) => {
                        if (Object.prototype.hasOwnProperty.call(campos, campo)) {
                            this.liquidacion[campo] = this.formatearNumeroConComas(campos[campo]);
                        }
                    });

                    if (Object.prototype.hasOwnProperty.call(campos, 'renta_tasa')) {
                        this.liquidacion.renta_tasa = campos.renta_tasa;
                    }
                    if (Object.prototype.hasOwnProperty.call(campos, 'es_zona_selva')) {
                        this.liquidacion.es_zona_selva = !!campos.es_zona_selva;
                    }
                    if (campos.notas_liquidacion && !String(this.liquidacion.notas_liquidacion || '').trim()) {
                        this.liquidacion.notas_liquidacion = campos.notas_liquidacion;
                    }

                    const preservar = resultado.preservar || {};
                    this.recalcularTotalesLiquidacion('sunat');

                    if (formulario === '621') {
                        this.preservarSunat = preservar.tributo_1016 !== undefined ? preservar : null;
                        if (preservar.renta_tributo_codigo) {
                            this.rentaSugerida = String(preservar.renta_tributo_codigo);
                        }
                        const control = resultado.control || {};
                        this.cruceDj = {
                            igv: control.igv === null || control.igv === undefined ? null : parseFloat(control.igv),
                            renta: control.renta === null || control.renta === undefined ? null : parseFloat(control.renta),
                        };
                        this.otrasCasillasIgv = [
                            'igv_ventas_ley', 'igv_compras_ley', 'igv_compras_mixtas', 'igv_importaciones',
                            'igv_importaciones_mixtas', 'compras_destino_no_gravadas', 'importaciones_destino_no_gravadas',
                        ].some((campo) => this.limpiarMoneda(this.liquidacion[campo]) > 0);
                    }

                    this.importacion[formulario].aplicada = true;
                    if ((resultado.pagos_registrados || 0) > 0) {
                        this.deudaCargada = false;
                        await this.cargarDeuda();
                    }
                },

                cruceCuadra(impuesto) {
                    const oficial = this.cruceDj[impuesto];
                    if (oficial === null || oficial === undefined || Number.isNaN(oficial)) {
                        return false;
                    }
                    const calculado = impuesto === 'igv'
                        ? parseFloat(this.liquidacion.igv_resultante_pagar || 0)
                        : parseFloat(this.liquidacion.renta_resultante_pagar || 0);

                    return Math.abs(calculado - oficial) < 0.05;
                },

                restaurarOficiales(preservar) {
                    const numericos = [
                        'igv_resultante_pagar', 'igv_saldo_favor_siguiente',
                        'igv_retenciones_saldo_siguiente', 'igv_percepciones_saldo_siguiente',
                        'renta_resultante_pagar', 'renta_saldo_favor_siguiente',
                    ];
                    numericos.forEach((campo) => {
                        if (Object.prototype.hasOwnProperty.call(preservar, campo) && preservar[campo] !== null) {
                            this.liquidacion[campo] = parseFloat(preservar[campo]);
                        }
                    });
                    if (Object.prototype.hasOwnProperty.call(preservar, 'renta_calculada') && preservar.renta_calculada !== null) {
                        this.liquidacion.renta_calculada = this.formatearNumeroConComas(preservar.renta_calculada);
                    }
                },

                async marcarDeclaradoSiCorresponde() {
                    const mes = this.meses[this.mesIdx];
                    let cambio = false;
                    if (this.importacion['621'].aplicada && this.importacion['621'].marcarDeclarado && mes.pdt !== 'Declarado') {
                        mes.pdt = 'Declarado';
                        cambio = true;
                    }
                    if (this.importacion['601'].aplicada && this.importacion['601'].marcarDeclarado && mes.ple !== 'Declarado') {
                        mes.ple = 'Declarado';
                        cambio = true;
                    }
                    if (!cambio) {
                        return;
                    }

                    mes.estado = this.calcularEstadoMaestro(mes.sire, mes.pdt, mes.ple);
                    await this.actualizarEstadoMaestro(this.mesIdx);
                },

                async guardarLiquidacion(actualizarDeudas = true) {
                    this.savingLiquidacion = true;
                    this.guardadoLiquidacionExitoso = false;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const payload = {
                            cliente_id: this.clienteId,
                            periodo: this.meses[this.mesIdx].periodo,
                            es_zona_selva: this.liquidacion.es_zona_selva,
                            ventas_base: this.limpiarMoneda(this.liquidacion.ventas_base),
                            igv_ventas: this.limpiarMoneda(this.liquidacion.igv_ventas),
                            igv_descuentos_base: this.limpiarMoneda(this.liquidacion.igv_descuentos_base),
                            igv_descuentos: this.limpiarMoneda(this.liquidacion.igv_descuentos),
                            ventas_no_gravadas: this.limpiarMoneda(this.liquidacion.ventas_no_gravadas),
                            igv_ventas_ley_base: this.limpiarMoneda(this.liquidacion.igv_ventas_ley_base),
                            igv_ventas_ley: this.limpiarMoneda(this.liquidacion.igv_ventas_ley),
                            compras_base: this.limpiarMoneda(this.liquidacion.compras_base),
                            igv_compras: this.limpiarMoneda(this.liquidacion.igv_compras),
                            igv_compras_ley_base: this.limpiarMoneda(this.liquidacion.igv_compras_ley_base),
                            igv_compras_ley: this.limpiarMoneda(this.liquidacion.igv_compras_ley),
                            igv_compras_mixtas_base: this.limpiarMoneda(this.liquidacion.igv_compras_mixtas_base),
                            igv_compras_mixtas: this.limpiarMoneda(this.liquidacion.igv_compras_mixtas),
                            igv_importaciones_base: this.limpiarMoneda(this.liquidacion.igv_importaciones_base),
                            igv_importaciones: this.limpiarMoneda(this.liquidacion.igv_importaciones),
                            compras_destino_no_gravadas: this.limpiarMoneda(this.liquidacion.compras_destino_no_gravadas),
                            igv_importaciones_mixtas_base: this.limpiarMoneda(this.liquidacion.igv_importaciones_mixtas_base),
                            igv_importaciones_mixtas: this.limpiarMoneda(this.liquidacion.igv_importaciones_mixtas),
                            importaciones_destino_no_gravadas: this.limpiarMoneda(this.liquidacion.importaciones_destino_no_gravadas),
                            compras_internas_no_gravadas: this.limpiarMoneda(this.liquidacion.compras_internas_no_gravadas),
                            compras_importadas_no_gravadas: this.limpiarMoneda(this.liquidacion.compras_importadas_no_gravadas),
                            igv_saldo_favor_anterior: this.limpiarMoneda(this.liquidacion.igv_saldo_favor_anterior),
                            igv_retenciones_saldo_anterior: this.limpiarMoneda(this.liquidacion.igv_retenciones_saldo_anterior),
                            igv_retenciones: this.limpiarMoneda(this.liquidacion.igv_retenciones),
                            igv_percepciones_saldo_anterior: this.limpiarMoneda(this.liquidacion.igv_percepciones_saldo_anterior),
                            igv_percepciones: this.limpiarMoneda(this.liquidacion.igv_percepciones),
                            renta_base_ingresos: this.limpiarMoneda(this.liquidacion.renta_base_ingresos),
                            renta_tasa: parseFloat(this.liquidacion.renta_tasa || 0),
                            renta_calculada: this.limpiarMoneda(this.liquidacion.renta_calculada),
                            renta_saldo_favor_anterior: this.limpiarMoneda(this.liquidacion.renta_saldo_favor_anterior),
                            renta_itan_saldo_anterior: this.limpiarMoneda(this.liquidacion.renta_itan_saldo_anterior),
                            renta_compensacion_itan: this.limpiarMoneda(this.liquidacion.renta_compensacion_itan),
                            renta_retenciones: this.limpiarMoneda(this.liquidacion.renta_retenciones),
                            plame_essalud_base: this.limpiarMoneda(this.liquidacion.plame_essalud_base),
                            plame_essalud_a_pagar: this.limpiarMoneda(this.liquidacion.plame_essalud_a_pagar),
                            plame_onp_a_pagar: this.limpiarMoneda(this.liquidacion.plame_onp_a_pagar),
                            plame_renta_4ta_base: this.limpiarMoneda(this.liquidacion.plame_renta_4ta_base),
                            plame_renta_4ta_a_pagar: this.limpiarMoneda(this.liquidacion.plame_renta_4ta_a_pagar),
                            plame_renta_5ta_a_pagar: this.limpiarMoneda(this.liquidacion.plame_renta_5ta_a_pagar),
                            plame_afp_a_pagar: this.limpiarMoneda(this.liquidacion.plame_afp_a_pagar),
                            renta_tributo_codigo: this.rentaCodigoSugerido(),
                            vencimiento_deuda: this.vencimientoDeuda || null,
                            notas_liquidacion: this.liquidacion.notas_liquidacion || null,
                            actualizar_deudas: actualizarDeudas,
                        };

                        if (this.determinacionSunat.igv && this.preservarSunat) {
                            payload.respetar_igv_sunat = true;
                            const igvOficial = {
                                igv_resultante_pagar: 'igv_resultante_oficial',
                                igv_saldo_favor_siguiente: 'igv_saldo_favor_oficial',
                                igv_retenciones_saldo_siguiente: 'igv_retenciones_siguiente_oficial',
                                igv_percepciones_saldo_siguiente: 'igv_percepciones_siguiente_oficial',
                            };
                            Object.entries(igvOficial).forEach(([origen, destino]) => {
                                if (Object.prototype.hasOwnProperty.call(this.preservarSunat, origen)) {
                                    payload[destino] = this.preservarSunat[origen];
                                }
                            });
                            if (Object.prototype.hasOwnProperty.call(this.preservarSunat, 'tributo_1016')) {
                                payload.tributo_igv_105 = this.preservarSunat.tributo_1016;
                            }
                        }
                        if (this.preservarSunat && Object.prototype.hasOwnProperty.call(this.preservarSunat, 'tributo_1016')) {
                            payload.tributo_igv_105 = this.preservarSunat.tributo_1016;
                        }
                        if (this.determinacionSunat.renta && this.preservarSunat) {
                            payload.respetar_renta_sunat = true;
                            const rentaOficial = {
                                renta_calculada: 'renta_calculada_oficial',
                                renta_resultante_pagar: 'renta_resultante_oficial',
                                renta_saldo_favor_siguiente: 'renta_saldo_favor_oficial',
                            };
                            Object.entries(rentaOficial).forEach(([origen, destino]) => {
                                if (Object.prototype.hasOwnProperty.call(this.preservarSunat, origen)) {
                                    payload[destino] = this.preservarSunat[origen];
                                }
                            });
                            if (this.preservarSunat.renta_tributo_codigo) {
                                payload.renta_tributo_codigo = this.preservarSunat.renta_tributo_codigo;
                            }
                        }

                        const response = await fetch('{{ route('control.liquidacion') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            const detalle = data.errors ? Object.values(data.errors).flat()[0] : null;
                            throw new Error(detalle || data.message || 'Error al guardar la liquidación.');
                        }

                        this.meses[this.mesIdx].liquidacion = data.liquidacion;
                        if (data.liquidacion) {
                            this.liquidacion.renta_itan_saldo_siguiente = parseFloat(data.liquidacion.renta_itan_saldo_siguiente || 0);
                            this.liquidacion.renta_saldo_favor_siguiente = parseFloat(data.liquidacion.renta_saldo_favor_siguiente || 0);
                            this.liquidacion.igv_saldo_favor_siguiente = parseFloat(data.liquidacion.igv_saldo_favor_siguiente || 0);
                            this.liquidacion.igv_retenciones_saldo_siguiente = parseFloat(data.liquidacion.igv_retenciones_saldo_siguiente || 0);
                            this.liquidacion.igv_percepciones_saldo_siguiente = parseFloat(data.liquidacion.igv_percepciones_saldo_siguiente || 0);
                        }
                        if (data.operacion_id) {
                            this.meses[this.mesIdx].operacion_id = data.operacion_id;
                        }
                        if (actualizarDeudas) {
                            this.aplicarRespuestaDeuda(data);
                        }
                        if (data.kpis) {
                            window.dispatchEvent(new CustomEvent('kpis-actualizados', { detail: data.kpis }));
                        }

                        this.guardadoLiquidacionExitoso = true;
                        await this.marcarDeclaradoSiCorresponde();
                        this.encuadrarMenuDJ();
                        setTimeout(() => { this.guardadoLiquidacionExitoso = false; }, 2500);
                    } catch (err) {
                        console.error('Error al guardar liquidación:', err);
                        alert(err.message || 'No se pudo guardar la liquidación.');
                    } finally {
                        this.savingLiquidacion = false;
                    }
                },

                async limpiarHojaLiquidacion() {
                    const tieneLiquidacionGuardada = !!this.meses[this.mesIdx]?.liquidacion;
                    const deudasLiquidacion = (this.deudas || []).filter(d => this.codigosLiquidacion.includes(d.tributo_codigo));
                    const abonosTotales = deudasLiquidacion.reduce((acc, d) => acc + (parseFloat(d.monto_abonado || 0)), 0);
                    const deudasPendientes = deudasLiquidacion.filter(d => parseFloat(d.saldo_pendiente || d.monto_deuda_inicial || 0) > 0);

                    // Escenario 1: Solo está digitada en pantalla (no guardada en base de datos ni con deudas)
                    if (!tieneLiquidacionGuardada && deudasPendientes.length === 0) {
                        this.resetearCamposLiquidacionLocal();
                        return;
                    }

                    // Escenario 2: Hay datos guardados en base de datos o deudas PDT / PLAME
                    let mensajeConfirmacion = '¿Deseas restablecer la Hoja de Liquidación a cero?';
                    if (abonosTotales > 0) {
                        mensajeConfirmacion = '⚠️ Esta liquidación tiene tributos con pagos/abonos realizados (S/ ' + this.formatearNumeroConComas(abonosTotales) + '). Limpiar la hoja restablecerá los valores a cero pero conservará el historial de los pagos. ¿Deseas continuar?';
                    } else if (deudasPendientes.length > 0) {
                        mensajeConfirmacion = '¿Deseas restablecer a cero la Hoja de Liquidación y eliminar los tributos pendientes de pago (IGV, Renta y PLAME) generados para este mes?';
                    }

                    if (!confirm(mensajeConfirmacion)) {
                        return;
                    }

                    this.savingLiquidacion = true;
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('control.liquidacion.limpiar') }}', {
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
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al limpiar la liquidación.');
                        }

                        this.meses[this.mesIdx].liquidacion = null;
                        this.resetearCamposLiquidacionLocal();
                        this.aplicarRespuestaDeuda(data);

                        if (data.kpis) {
                            window.dispatchEvent(new CustomEvent('kpis-actualizados', { detail: data.kpis }));
                        }

                        this.encuadrarMenuDJ();
                    } catch (err) {
                        console.error('Error al limpiar liquidación:', err);
                        alert(err.message || 'No se pudo limpiar la liquidación.');
                    } finally {
                        this.savingLiquidacion = false;
                    }
                },

                resetearCamposLiquidacionLocal() {
                    const reg = (this.regimen || '').toUpperCase();
                    let tasaPorDefecto = (reg === 'RER' || reg === 'GENERAL' || reg === 'RG') ? 1.5 : 1.0;
                    const depto = (this.departamento || '').toUpperCase().trim();
                    const esSelvaSugerido = ['LORETO', 'UCAYALI', 'SAN MARTIN', 'SAN MARTÍN', 'AMAZONAS', 'MADRE DE DIOS'].includes(depto);

                    this.liquidacion = {
                        es_zona_selva: esSelvaSugerido,
                        ventas_base: '',
                        igv_ventas: esSelvaSugerido ? '0.00' : '',
                        igv_descuentos_base: '',
                        igv_descuentos: '',
                        ventas_no_gravadas: '',
                        igv_ventas_ley_base: '',
                        igv_ventas_ley: '',
                        compras_base: '',
                        igv_compras: esSelvaSugerido ? '0.00' : '',
                        igv_compras_ley_base: '',
                        igv_compras_ley: '',
                        igv_compras_mixtas_base: '',
                        igv_compras_mixtas: '',
                        igv_importaciones_base: '',
                        igv_importaciones: '',
                        compras_destino_no_gravadas: '',
                        igv_importaciones_mixtas_base: '',
                        igv_importaciones_mixtas: '',
                        importaciones_destino_no_gravadas: '',
                        compras_internas_no_gravadas: '',
                        compras_importadas_no_gravadas: '',
                        igv_saldo_favor_anterior: '',
                        igv_retenciones_saldo_anterior: '',
                        igv_retenciones: '',
                        igv_retenciones_saldo_siguiente: 0,
                        igv_percepciones_saldo_anterior: '',
                        igv_percepciones: '',
                        igv_percepciones_saldo_siguiente: 0,
                        igv_saldo_favor_siguiente: 0,
                        igv_resultante_pagar: 0,
                        renta_base_ingresos: '',
                        renta_tasa: tasaPorDefecto,
                        renta_calculada: '',
                        renta_saldo_favor_anterior: '',
                        renta_itan_saldo_anterior: '',
                        renta_compensacion_itan: '',
                        renta_itan_saldo_siguiente: 0,
                        renta_retenciones: '',
                        renta_saldo_favor_siguiente: 0,
                        renta_resultante_pagar: 0,
                        plame_essalud_base: '',
                        plame_essalud_a_pagar: '',
                        plame_onp_a_pagar: '',
                        plame_renta_4ta_base: '',
                        plame_renta_4ta_a_pagar: '',
                        plame_renta_5ta_a_pagar: '',
                        plame_afp_a_pagar: '',
                        notas_liquidacion: '',
                    };
                    this.olvidarImportacionSunat();
                    this.recalcularTotalesLiquidacion();
                }
            };
        }
    </script>

    @include('control.partials.carga-masiva')
@endsection
