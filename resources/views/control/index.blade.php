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
                <span class="text-gray-500">💬 Nota por Mes</span>
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
    <div x-data x-cloak class="rounded-xl border border-gray-300 bg-white shadow-md min-h-[200px]">
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
                                <span title="Declaración Jurada Mensual (SIRE / PDT / PLE)">DJ</span>
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
                                <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-extrabold"
                                      :class="calcAvance() === 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'"
                                      x-text="calcAvance() + '%'">
                                    {{ $item['porcentaje'] }}%
                                </span>
                            </div>
                        </td>

                        {{-- COLUMNAS 2 A 13: UNA CELDA MAESTRA POR MES (DJ + RH + NOTA) --}}
                        @foreach ($item['meses'] as $idx => $m)
                            <td class="w-[6.5%] p-0.5 border-r border-b border-gray-300 text-center transition-colors relative align-middle h-11"
                                :class="bgClass(meses[{{ $idx }}].estado)">

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
                                         @click="abrir()"
                                         :title="'Nota: ' + nota"
                                         class="absolute top-0 right-0 w-0 h-0 border-t-[8px] border-r-[8px] border-t-transparent border-r-rose-600 cursor-pointer z-10">
                                    </div>

                                    {{-- CONTROL 1: DJ (Declaración Jurada - Miniventana Flotante Amplia) --}}
                                    <div x-data="{ openDJ: false }" class="relative flex-1 flex items-center justify-center">
                                        <button
                                            type="button"
                                            @click="openDJ = !openDJ"
                                            :title="'DJ: ' + meses[{{ $idx }}].estado"
                                            class="inline-flex items-center justify-center w-6 h-6 text-xs rounded hover:scale-110 active:scale-95 transition-transform select-none focus:outline-none"
                                        >
                                            <span x-show="meses[{{ $idx }}].estado === 'Declarado'">🟢</span>
                                            <span x-show="meses[{{ $idx }}].estado === 'Procesando'">🟡</span>
                                            <span x-show="meses[{{ $idx }}].estado === 'Pendiente' || !meses[{{ $idx }}].estado" class="drop-shadow-xs">⚪</span>
                                        </button>

                                        {{-- Miniventana Flotante Espaciosa para DJ --}}
                                        <div
                                            x-show="openDJ"
                                            x-cloak
                                            @click.outside="openDJ = false"
                                            :class="{{ $idx }} > 6 ? 'right-0' : 'left-0'"
                                            class="absolute top-full mt-1.5 z-50 w-44 rounded-xl border border-gray-200 bg-white p-2 shadow-2xl text-left"
                                        >
                                            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 mb-1">
                                                Estado DJ ({{ $m['corto'] }})
                                            </div>
                                            <div class="space-y-1">
                                                <button
                                                    type="button"
                                                    @click="meses[{{ $idx }}].estado = 'Pendiente'; actualizarEstadoMaestro({{ $idx }}); openDJ = false"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-slate-100 text-gray-800"
                                                    :class="meses[{{ $idx }}].estado === 'Pendiente' ? 'bg-slate-100 text-slate-800 ring-1 ring-slate-300' : ''"
                                                >
                                                    <span class="text-sm">⚪</span>
                                                    <span>Pendiente</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    @click="meses[{{ $idx }}].estado = 'Procesando'; actualizarEstadoMaestro({{ $idx }}); openDJ = false"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-amber-50 text-gray-800"
                                                    :class="meses[{{ $idx }}].estado === 'Procesando' ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' : ''"
                                                >
                                                    <span class="text-sm">🟡</span>
                                                    <span>En Proceso</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    @click="meses[{{ $idx }}].estado = 'Declarado'; actualizarEstadoMaestro({{ $idx }}); openDJ = false"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-emerald-50 text-gray-800"
                                                    :class="meses[{{ $idx }}].estado === 'Declarado' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : ''"
                                                >
                                                    <span class="text-sm">🟢</span>
                                                    <span>Declarado</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Separador vertical micro --}}
                                    <div class="h-3.5 w-px bg-gray-300/60 shrink-0"></div>

                                    {{-- CONTROL 2: RH (Recibo por Honorarios - Miniventana Flotante Amplia) --}}
                                    <div x-data="{ openRH: false }" class="relative flex-1 flex items-center justify-center">
                                        <button
                                            type="button"
                                            @click="openRH = !openRH"
                                            :title="'RH: ' + (meses[{{ $idx }}].honorarios === 'Pagado' ? 'Pagado' : 'Debe')"
                                            class="inline-flex items-center justify-center w-6 h-6 text-xs rounded hover:scale-110 active:scale-95 transition-transform select-none focus:outline-none"
                                        >
                                            <span x-show="meses[{{ $idx }}].honorarios === 'Pagado'">🟢</span>
                                            <span x-show="meses[{{ $idx }}].honorarios !== 'Pagado'" class="drop-shadow-xs">⚪</span>
                                        </button>

                                        {{-- Miniventana Flotante Espaciosa para RH --}}
                                        <div
                                            x-show="openRH"
                                            x-cloak
                                            @click.outside="openRH = false"
                                            :class="{{ $idx }} > 6 ? 'right-0' : 'left-0'"
                                            class="absolute top-full mt-1.5 z-50 w-40 rounded-xl border border-gray-200 bg-white p-2 shadow-2xl text-left"
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
                                    <div x-show="openNote"
                                         x-cloak
                                         @click.outside="cerrar()"
                                         :class="{{ $idx }} > 6 ? 'right-0' : 'left-0'"
                                         class="absolute top-full mt-1 w-64 rounded-xl border border-amber-300 bg-amber-50 p-3 shadow-2xl z-50 text-left">

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
    </script>
@endsection
