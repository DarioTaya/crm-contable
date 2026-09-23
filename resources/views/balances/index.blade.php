@extends('layouts.app')

@section('title', "Control de Balances {$anio} — Régimen MYPE y General")
@section('header', 'Control de Balances')

@section('content')
    {{-- ═══════════════════════════════ ENCABEZADO Y SELECTOR DE AÑO ═══════════════════════════════ --}}
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Control de Balances {{ $anio }}</h2>
                <span class="rounded bg-indigo-100 border border-indigo-300 px-2 py-0.5 text-xs font-bold text-indigo-800">
                    MYPE &bull; General
                </span>
            </div>
            <p class="mt-0.5 text-xs text-gray-500">
                Monitoreo anual de balances mensuales y declaración jurada anual (DJ Anual {{ $anio - 1 }}) para empresas con contabilidad completa o simplificada.
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
                <span class="font-bold text-indigo-700">DJ Anual: Ejercicio {{ $anio - 1 }}</span>
                <span class="text-gray-300">|</span>
                <span class="text-gray-500">💬 Nota por Mes</span>
            </div>

            {{-- Enlace Directo a Cronograma SUNAT (Balances) --}}
            <a href="{{ route('cronogramas.index', ['anio' => $anio, 'tipo' => 'balance_mensual']) }}"
               title="Ver Cronograma de Balances y DJ Anual"
               class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 shadow-xs transition hover:bg-amber-100">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                </svg>
                <span>Cronograma Balances</span>
            </a>

            {{-- Selector interactivo de Año --}}
            <div class="flex items-center gap-1.5">
                <a href="{{ route('balances.index', ['anio' => $anio - 1]) }}"
                   title="Año {{ $anio - 1 }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-600 shadow-xs transition hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </a>

                <select
                    id="selectorAnioBalances"
                    onchange="window.location.href = '{{ route('balances.index') }}?anio=' + this.value"
                    class="rounded-lg border border-gray-300 bg-white py-1.5 pl-3 pr-7 text-xs font-bold text-gray-800 shadow-xs transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 cursor-pointer"
                >
                    @foreach ($aniosDisponibles as $y)
                        <option value="{{ $y }}" {{ $y === $anio ? 'selected' : '' }}>
                            Año {{ $y }}
                        </option>
                    @endforeach
                </select>

                <a href="{{ route('balances.index', ['anio' => $anio + 1]) }}"
                   title="Año {{ $anio + 1 }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-600 shadow-xs transition hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ RESUMEN SUPERIOR (KPIS DE BALANCES) ═══════════════════════════════ --}}
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-5"
         x-data="{
             totalClientes: {{ $totalClientes }},
             balancesDeclarados: {{ $balancesDeclarados }},
             balancesProcesando: {{ $balancesProcesando }},
             balancesPendientes: {{ $balancesPendientes }},
             porcentajeAvance: {{ $porcentajeAvance }},
             djAnualDeclaradas: {{ $djAnualDeclaradas }},
             init() {
                 window.addEventListener('kpis-balances-actualizados', (e) => {
                     this.totalClientes = e.detail.totalClientes;
                     this.balancesDeclarados = e.detail.balancesDeclarados;
                     this.balancesProcesando = e.detail.balancesProcesando;
                     this.balancesPendientes = e.detail.balancesPendientes;
                     this.porcentajeAvance = e.detail.porcentajeAvance;
                     this.djAnualDeclaradas = e.detail.djAnualDeclaradas;
                 });
             }
         }">
        {{-- KPI 1: Empresas MYPE / General --}}
        <div class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-gray-500 tracking-wider">Empresas MYPE/General</p>
                <p class="text-lg font-black text-gray-900 leading-tight" x-text="totalClientes">{{ $totalClientes }}</p>
            </div>
            <span class="text-xl">📊</span>
        </div>

        {{-- KPI 2: Balances Declarados --}}
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider">Balances Declarados</p>
                <p class="text-lg font-black text-emerald-800 leading-tight">
                    <span x-text="balancesDeclarados">{{ $balancesDeclarados }}</span>
                    <span class="text-xs font-normal text-emerald-600">(<span x-text="porcentajeAvance">{{ $porcentajeAvance }}</span>%)</span>
                </p>
            </div>
            <span class="text-xl">🟢</span>
        </div>

        {{-- KPI 3: Balances En Proceso --}}
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-amber-700 tracking-wider">En Proceso</p>
                <p class="text-lg font-black text-amber-800 leading-tight" x-text="balancesProcesando">{{ $balancesProcesando }}</p>
            </div>
            <span class="text-xl">🟡</span>
        </div>

        {{-- KPI 4: Balances Pendientes --}}
        <div class="rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-600 tracking-wider">Pendientes</p>
                <p class="text-lg font-black text-slate-800 leading-tight" x-text="balancesPendientes">{{ $balancesPendientes }}</p>
            </div>
            <span class="text-xl">⚪</span>
        </div>

        {{-- KPI 5: DJ Anual --}}
        <div class="rounded-lg border border-indigo-300 bg-indigo-50 px-3.5 py-2 shadow-xs flex items-center justify-between col-span-2 sm:col-span-1">
            <div>
                <p class="text-[10px] uppercase font-bold text-indigo-700 tracking-wider">DJ Anual {{ $anio - 1 }}</p>
                <p class="text-lg font-black text-indigo-800 leading-tight">
                    <span x-text="djAnualDeclaradas">{{ $djAnualDeclaradas }}</span>
                    <span class="text-xs font-normal text-indigo-600">/ <span x-text="totalClientes">{{ $totalClientes }}</span></span>
                </p>
            </div>
            <span class="text-xl" title="Declaración Jurada Anual del Ejercicio {{ $anio - 1 }}">📑</span>
        </div>
    </div>

    {{-- ═══════════════════════════════ TABLA MATRIZ 100% MONITOR (14 COLUMNAS) ═══════════════════════════════ --}}
    <div class="overflow-x-auto rounded-xl border border-gray-300 bg-white shadow-md min-h-[200px]">
        <table class="w-full table-fixed border-collapse border border-gray-300 text-xs text-left">

            {{-- ═══════════════ CABECERA ÚNICA (THEAD - 14 COLUMNAS) ═══════════════ --}}
            <thead class="bg-gray-100 text-gray-700 text-xs font-extrabold select-none shadow-xs">
                <tr class="border-b border-gray-300 bg-gray-200/90 text-center">
                    {{-- Columna 1: Cliente/RUC (Sticky Left) con N° Correlativo Original --}}
                    <th class="w-[20%] px-2.5 py-2 text-left border-r border-b border-gray-300 bg-gray-200 sticky left-0 z-20 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.15)]">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex h-5 min-w-[22px] px-1 items-center justify-center rounded bg-gray-300/80 border border-gray-400/60 text-[10px] font-mono font-black text-gray-800 shrink-0" title="Número Correlativo Original">N°</span>
                            <div class="min-w-0 flex-1">
                                <span class="block text-[11px] font-black uppercase tracking-wider text-gray-800 truncate">CLIENTE / RUC</span>
                                <span class="block text-[9px] font-normal text-gray-500">MYPE &bull; General</span>
                            </div>
                        </div>
                    </th>

                    {{-- Columnas 2 a 13: 12 Meses LIMPIOS (SIN SUB-ETIQUETAS INFERIORES 'DJ | RH') --}}
                    @foreach ($mesesEncabezado as $mNum => $mInfo)
                        <th class="w-[6%] px-0.5 py-2.5 border-r border-b border-gray-300 text-center whitespace-nowrap bg-gray-100 text-gray-800 select-none">
                            <span class="block text-[11px] font-black tracking-tight uppercase">{{ $mInfo['corto'] }}-{{ substr($anio, -2) }}</span>
                        </th>
                    @endforeach

                    {{-- Columna 14: DJ ANUAL AÑO ANTERIOR (DESTACADA) --}}
                    <th class="w-[8%] px-1 py-2 border-b border-indigo-300 text-center whitespace-nowrap bg-indigo-100/90 text-indigo-900 select-none shadow-inner">
                        <span class="block text-[10px] font-black tracking-tight uppercase">DJ ANUAL</span>
                        <span class="block text-[9px] font-extrabold text-indigo-700 leading-none mt-0.5">{{ $anio - 1 }}</span>
                    </th>
                </tr>
            </thead>

            {{-- ═══════════════ CUERPO DE LA TABLA (TBODY) ═══════════════ --}}
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($matrizClientes as $item)
                    @php
                        $badgeColor = $item['cliente']->regimen_tributario === \App\Enums\RegimenTributario::General
                            ? 'bg-purple-100 text-purple-800 border-purple-200'
                            : 'bg-blue-100 text-blue-800 border-blue-200';
                    @endphp

                    {{-- Fila del Cliente (Reactividad Alpine.js) --}}
                    <tr x-data="filaBalanceCliente({
                            clienteId: {{ $item['cliente']->id }},
                            anio: {{ $anio }},
                            meses: @js($item['meses']),
                            djAnual: '{{ $item['dj_anual'] }}'
                        })"
                        class="hover:bg-blue-50/20 transition-colors">

                        {{-- COLUMNA 1: CLIENTE / RUC (STICKY LEFT) CON CORRELATIVO ORIGINAL --}}
                        <td class="w-[20%] sticky left-0 bg-white z-10 px-2.5 py-2 border-r border-b border-gray-300 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.12)] whitespace-nowrap overflow-hidden">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    {{-- N° Correlativo Original respetando su ID/Posición general (ej. 2, 5, 10) --}}
                                    <span class="inline-flex h-5 min-w-[24px] px-1 items-center justify-center rounded-md bg-gray-100 border border-gray-300 text-[10px] font-mono font-black text-gray-700 shrink-0 shadow-xs"
                                          title="Cliente N° {{ $item['numero'] }} (Correlativo original)">
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

                                {{-- Porcentaje de balance anual calculado reactivamente --}}
                                <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-extrabold"
                                      :class="calcAvance() === 100 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'"
                                      x-text="calcAvance() + '%'">
                                    {{ $item['porcentaje'] }}%
                                </span>
                            </div>
                        </td>

                        {{-- COLUMNAS 2 A 13: 12 MESES LIMPIOS CON SELECTOR DE BALANCE Y NOTA --}}
                        @foreach ($item['meses'] as $idx => $m)
                            <td class="w-[6%] p-0.5 border-r border-b border-gray-300 text-center transition-colors relative align-middle h-11"
                                :class="bgClass(meses[{{ $idx }}].estado)">

                                @if (!empty($m['es_baja']))
                                    <div class="flex items-center justify-center w-full h-full text-center py-1 select-none"
                                         title="Cliente no activo en este periodo (Baja del Estudio Contable)">
                                        <span class="inline-flex items-center gap-1 rounded bg-rose-50 border border-rose-200 px-1 py-0.5 text-[9px] font-bold text-rose-700 shadow-xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            Baja
                                        </span>
                                    </div>
                                @else
                                    {{-- Componente Alpine por celda: Selector de Balance + Nota Flotante --}}
                                    <div x-data="popoverNotaBalance({
                                            clienteId: {{ $item['cliente']->id }},
                                            anio: {{ $anio }},
                                            mesNum: {{ $m['numero'] }},
                                            mesNombre: '{{ $m['nombre'] }}',
                                            notaInicial: @js($m['nota'])
                                         })"
                                         class="flex items-center justify-center px-0.5 w-full h-full relative">

                                    {{-- Triángulo Rojo en Esquina Superior Derecha (Google Sheets Comment Indicator) --}}
                                    <div x-show="tieneNota()"
                                         x-cloak
                                         @click="abrir()"
                                         :title="'Nota: ' + nota"
                                         class="absolute top-0 right-0 w-0 h-0 border-t-[8px] border-r-[8px] border-t-transparent border-r-rose-600 cursor-pointer z-10">
                                    </div>

                                    {{-- Selector de Estado de Balance Mensual --}}
                                    <div x-data="{ openMenu: false }" class="relative flex items-center justify-center w-full">
                                        <button
                                            type="button"
                                            @click="openMenu = !openMenu; if (openMenu) $nextTick(() => window.ubicarMenuFlotante($refs.menuMes, $el))"
                                            :title="'Mes ' + {{ $m['numero'] }} + ': ' + meses[{{ $idx }}].estado"
                                            class="inline-flex h-7 w-7 min-w-7 shrink-0 items-center justify-center overflow-hidden rounded text-xs transition-transform select-none hover:scale-110 focus:outline-none active:scale-95"
                                        >
                                            <span x-text="meses[{{ $idx }}].estado === 'Declarado' ? '🟢' : ((meses[{{ $idx }}].estado === 'Proceso' || meses[{{ $idx }}].estado === 'Procesando') ? '🟡' : '⚪')">{{ match ($m['estado'] ?? 'Pendiente') { 'Declarado' => '🟢', 'Proceso', 'Procesando' => '🟡', default => '⚪' } }}</span>
                                        </button>

                                        {{-- Botón micro de nota al hover si no tiene nota --}}
                                        <button
                                            type="button"
                                            x-show="!tieneNota()"
                                            x-cloak
                                            @click="abrir()"
                                            title="Agregar nota"
                                            class="absolute -right-0.5 top-0.5 opacity-0 hover:opacity-100 text-[9px] text-gray-400 hover:text-amber-700 transition"
                                        >
                                            💬
                                        </button>

                                        {{-- Miniventana Flotante Espaciosa para Estado del Mes --}}
                                        <div
                                            x-ref="menuMes"
                                            x-show="openMenu"
                                            x-cloak
                                            data-menu-flotante
                                            @click.outside="openMenu = false"
                                            class="fixed z-50 w-44 rounded-xl border border-gray-200 bg-white p-2 text-left shadow-2xl"
                                        >
                                            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 mb-1">
                                                Balance {{ $m['corto'] }}-{{ substr($anio, -2) }}
                                            </div>
                                            <div class="space-y-1">
                                                <button
                                                    type="button"
                                                    @click="meses[{{ $idx }}].estado = 'Pendiente'; actualizarMes({{ $idx }}); openMenu = false"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-slate-100 text-gray-800"
                                                    :class="meses[{{ $idx }}].estado === 'Pendiente' ? 'bg-slate-100 text-slate-800 ring-1 ring-slate-300' : ''"
                                                >
                                                    <span class="text-sm">⚪</span>
                                                    <span>Pendiente</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    @click="meses[{{ $idx }}].estado = 'Procesando'; actualizarMes({{ $idx }}); openMenu = false"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-amber-50 text-gray-800"
                                                    :class="meses[{{ $idx }}].estado === 'Procesando' || meses[{{ $idx }}].estado === 'Proceso' ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' : ''"
                                                >
                                                    <span class="text-sm">🟡</span>
                                                    <span>En Proceso</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    @click="meses[{{ $idx }}].estado = 'Declarado'; actualizarMes({{ $idx }}); openMenu = false"
                                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-emerald-50 text-gray-800"
                                                    :class="meses[{{ $idx }}].estado === 'Declarado' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : ''"
                                                >
                                                    <span class="text-sm">🟢</span>
                                                    <span>Declarado</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Popover Flotante de Nota estilo Google Sheets --}}
                                    <div x-ref="menuNota"
                                         x-show="openNote"
                                         x-cloak
                                         data-menu-flotante
                                         @click.outside="cerrar()"
                                         class="fixed z-50 w-64 rounded-xl border border-amber-300 bg-amber-50 p-3 text-left shadow-2xl">

                                        <div class="flex items-center justify-between pb-1.5 border-b border-amber-200 mb-2">
                                            <div class="truncate pr-2">
                                                <span class="block text-[11px] font-bold text-amber-900 truncate">
                                                    Nota: {{ $m['corto'] }}-{{ substr($anio, -2) }} &bull; {{ $item['cliente']->razon_social }}
                                                </span>
                                            </div>
                                            <button type="button" @click="cerrar()" class="text-gray-400 hover:text-gray-700 text-sm font-bold leading-none">&times;</button>
                                        </div>

                                        <textarea
                                            x-model="tempNota"
                                            rows="3"
                                            placeholder="Escribir nota sobre el balance (ej. falta depreciación, cierre pendiente)..."
                                            class="w-full rounded border border-amber-200 bg-white p-2 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-amber-500 shadow-inner"
                                        ></textarea>

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

                        {{-- COLUMNA 14: DJ ANUAL {{ $anio - 1 }} (COLUMNA DESTACADA) --}}
                        <td class="w-[8%] p-0.5 border-b border-indigo-200 text-center transition-colors relative align-middle h-11 bg-indigo-50/50 hover:bg-indigo-50"
                            :class="bgClass(djAnual)">

                            <div x-data="{ openDJAnual: false }" class="relative flex items-center justify-center w-full h-full">
                                <button
                                    type="button"
                                    @click="openDJAnual = !openDJAnual; if (openDJAnual) $nextTick(() => window.ubicarMenuFlotante($refs.menuDJAnual, $el))"
                                    :title="'DJ Anual {{ $anio - 1 }}: ' + djAnual"
                                    class="inline-flex items-center justify-center gap-1 px-2 py-1 text-xs font-bold rounded-lg border transition shadow-xs hover:scale-105 active:scale-95 focus:outline-none"
                                    :class="{
                                        'border-emerald-300 bg-emerald-100/90 text-emerald-900': djAnual === 'Declarado',
                                        'border-amber-300 bg-amber-100/90 text-amber-900': djAnual === 'Procesando' || djAnual === 'Proceso',
                                        'border-slate-300 bg-white text-slate-700': djAnual === 'Pendiente' || !djAnual
                                    }"
                                >
                                    <span x-text="djAnual === 'Declarado' ? '🟢' : ((djAnual === 'Proceso' || djAnual === 'Procesando') ? '🟡' : '⚪')">{{ match ($item['dj_anual'] ?? 'Pendiente') { 'Declarado' => '🟢', 'Proceso', 'Procesando' => '🟡', default => '⚪' } }}</span>
                                    <span class="text-[10px]" x-text="djAnual === 'Declarado' ? 'Declarado' : (djAnual === 'Procesando' || djAnual === 'Proceso' ? 'Proceso' : 'Pendiente')">{{ match ($item['dj_anual'] ?? 'Pendiente') { 'Declarado' => 'Declarado', 'Proceso', 'Procesando' => 'Proceso', default => 'Pendiente' } }}</span>
                                </button>

                                {{-- Dropdown DJ Anual --}}
                                <div
                                    x-ref="menuDJAnual"
                                    x-show="openDJAnual"
                                    x-cloak
                                    data-menu-flotante
                                    @click.outside="openDJAnual = false"
                                    class="fixed z-50 w-44 rounded-xl border border-indigo-200 bg-white p-2 text-left shadow-2xl"
                                >
                                    <div class="px-2 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-700 border-b border-indigo-100 mb-1">
                                        DJ Anual {{ $anio - 1 }}
                                    </div>
                                    <div class="space-y-1">
                                        <button
                                            type="button"
                                            @click="djAnual = 'Pendiente'; actualizarDJAnual(); openDJAnual = false"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-slate-100 text-gray-800"
                                            :class="djAnual === 'Pendiente' ? 'bg-slate-100 text-slate-800 ring-1 ring-slate-300' : ''"
                                        >
                                            <span class="text-sm">⚪</span>
                                            <span>Pendiente</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="djAnual = 'Procesando'; actualizarDJAnual(); openDJAnual = false"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-amber-50 text-gray-800"
                                            :class="djAnual === 'Procesando' || djAnual === 'Proceso' ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' : ''"
                                        >
                                            <span class="text-sm">🟡</span>
                                            <span>En Proceso</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="djAnual = 'Declarado'; actualizarDJAnual(); openDJAnual = false"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition hover:bg-emerald-50 text-gray-800"
                                            :class="djAnual === 'Declarado' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : ''"
                                        >
                                            <span class="text-sm">🟢</span>
                                            <span>Declarado</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="px-6 py-12 text-center text-gray-500">
                            No hay clientes de régimen MYPE o General activos en el ejercicio fiscal {{ $anio }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pie de Sábana --}}
    <div class="mt-3 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-400 gap-2 px-1">
        <p>Mostrando {{ $totalClientes }} empresas con régimen MYPE o General &bull; 12 meses de balances + columna destacada DJ Anual {{ $anio - 1 }}.</p>
        <p>Los cambios se guardan automáticamente por AJAX en tiempo real.</p>
    </div>

    {{-- ═══════════════════════════════ SCRIPTS ALPINE.JS ═══════════════════════════════ --}}
    <script>
        function filaBalanceCliente(config) {
            return {
                clienteId: config.clienteId,
                anio: config.anio,
                meses: config.meses,
                djAnual: config.djAnual,

                bgClass(estado) {
                    if (estado === 'Baja') {
                        return 'bg-gray-100/80 text-gray-400';
                    }
                    if (estado === 'Declarado') {
                        return 'bg-emerald-100/70 hover:bg-emerald-100';
                    }
                    if (estado === 'Proceso' || estado === 'Procesando') {
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

                async actualizarMes(idx) {
                    const mes = this.meses[idx];
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('balances.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                anio: this.anio,
                                mes: mes.numero,
                                estado: mes.estado
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar estado de balance.');
                        }
                        if (data.kpis) {
                            window.dispatchEvent(new CustomEvent('kpis-balances-actualizados', { detail: data.kpis }));
                        }
                    } catch (err) {
                        console.error('Error al actualizar balance:', err);
                        alert('No se pudo actualizar el balance: ' + err.message);
                    }
                },

                async actualizarDJAnual() {
                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('balances.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                anio: this.anio,
                                dj_anual: this.djAnual
                            }),
                        });

                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al guardar DJ Anual.');
                        }
                        if (data.kpis) {
                            window.dispatchEvent(new CustomEvent('kpis-balances-actualizados', { detail: data.kpis }));
                        }
                    } catch (err) {
                        console.error('Error al actualizar DJ Anual:', err);
                        alert('No se pudo actualizar la DJ Anual: ' + err.message);
                    }
                }
            };
        }

        function popoverNotaBalance(config) {
            return {
                clienteId: config.clienteId,
                anio: config.anio,
                mesNum: config.mesNum,
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

                        const response = await fetch('{{ route('balances.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                cliente_id: this.clienteId,
                                anio: this.anio,
                                mes: this.mesNum,
                                nota: this.tempNota
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
