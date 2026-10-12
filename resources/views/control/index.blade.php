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
                            rentaSugerida: @js($item['rentaSugerida'] ?? ''),
                        })"
                        @cliente-regimen-actualizado.window="if ($event.detail.clienteId === clienteId) { regimen = $event.detail.regimen; }"
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
                                            <span class="rounded border px-1 py-0.1 text-[9px] font-bold transition-colors"
                                                  :class="regimenBadgeColor(regimen)"
                                                  x-text="regimen">
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
                                        <div class="relative flex-1 flex items-center justify-center">
                                            <button
                                                type="button"
                                                @click="abrirMenuRH($event, {{ $idx }})"
                                                :title="'RH: ' + (meses[{{ $idx }}].honorarios === 'Pagado' ? 'Pagado' : 'Debe')"
                                                class="inline-flex h-6 w-6 min-w-6 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs transition-transform select-none hover:scale-110 focus:outline-none active:scale-95"
                                            >
                                                <span x-text="meses[{{ $idx }}].honorarios === 'Pagado' ? '🟢' : '⚪'">
                                                    {{ ($m['honorarios'] ?? '') === 'Pagado' ? '🟢' : '⚪' }}
                                                </span>
                                            </button>
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

    <div x-data="controlCeldaDJ(0, 0, [mesVacioPanel()], @js($catalogoTributos), '', '', '', @js($codigosLiquidacion ?? ['1011', '1016', '3121', '3111', '3031', '3011', '5210', '5310', '3042', '3052', '6098', 'AFP']))"
         @abrir-panel-dj.window="abrirDesde($event.detail)">
        @include('control.partials.menu-dj')
    </div>

    {{-- Tooltip Flotante Global de Deuda SUNAT (Matriz Anual) --}}
    <div x-data="tooltipDeudaMatriz()"
         x-show="visible"
         x-cloak
         data-menu-flotante
         data-abrir="arriba"
         data-alineacion="centro"
         class="fixed z-50 pointer-events-none rounded-2xl bg-white p-3 text-left text-gray-800 shadow-2xl border border-gray-200 w-72 transition-opacity duration-150"
         @mostrar-tooltip-deuda.window="mostrar($event.detail)"
         @ocultar-tooltip-deuda.window="ocultar()">
        <div class="mb-2 flex items-center justify-between border-b border-gray-100 pb-1.5">
            <div class="flex items-center gap-1.5">
                <span class="inline-block h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                <span class="text-[11px] font-black text-rose-700">Deuda SUNAT pendiente</span>
            </div>
            <span class="font-mono text-[10px] font-bold text-gray-600 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200" x-text="periodo"></span>
        </div>
        <template x-for="(deudaTip, dIdx) in deudas" :key="deudaTip.id || deudaTip.tributo_codigo || dIdx">
            <div class="mb-1 flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-2 py-1 text-xs border border-slate-100">
                <span class="truncate font-bold text-slate-800" x-text="deudaTip.tributo_nombre_corto || deudaTip.tributo_nombre || 'Tributo'"></span>
                <span class="font-mono font-bold text-rose-600 shrink-0" x-text="formatearMoneda(deudaTip.saldo_pendiente)"></span>
            </div>
        </template>
        <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white border-b border-r border-gray-200 rotate-45"></div>
    </div>

    {{-- Menú Flotante Global de Honorarios (RH) --}}
    <div x-data="menuFlotanteRH()"
         x-show="openRH"
         x-cloak
         data-menu-flotante
         @click.outside="if (!$el._clicInicioDentro) cerrar()"
         @abrir-menu-rh.window="abrir($event.detail)"
         class="fixed z-50 w-40 rounded-xl border border-gray-200 bg-white p-2 shadow-2xl text-left">
        <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 mb-1">
            Honorarios (<span x-text="corto"></span>)
        </div>
        <div class="space-y-1">
            <button
                type="button"
                @click="seleccionar('Pendiente')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-slate-100 text-gray-800"
                :class="honorarios !== 'Pagado' ? 'bg-slate-100 text-slate-800 ring-1 ring-slate-300' : ''"
            >
                <span class="text-sm">⚪</span>
                <span>Debe</span>
            </button>

            <button
                type="button"
                @click="seleccionar('Pagado')"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-emerald-50 text-gray-800"
                :class="honorarios === 'Pagado' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : ''"
            >
                <span class="text-sm">🟢</span>
                <span>Pagó</span>
            </button>
        </div>
    </div>
    {{-- Pie de Sábana --}}
    <div class="mt-3 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-400 gap-2 px-1">
        <p>Mostrando {{ $totalClientes }} clientes activos &bull; 13 columnas optimizadas para 100% de pantalla (DJ: Declaración Jurada &bull; RH: Recibo por Honorarios).</p>
        <p>Los cambios en los estados, honorarios y notas se guardan automáticamente por AJAX en tiempo real.</p>
    </div>

    {{-- ═══════════════════════════════ SCRIPTS ALPINE.JS OPTIMIZADOS ═══════════════════════════════ --}}
    @include('control.partials.scripts-matriz')

    @include('control.partials.carga-masiva')
@endsection
