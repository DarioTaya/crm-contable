@extends('layouts.app')

@section('title', 'Centro de Comando — Dashboard')
@section('header', 'Centro de Comando')

@section('content')
    {{-- ═══════════════════════════════ ENCABEZADO Y PERIODO ═══════════════════════════════ --}}
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Panel de Control Tributario</h2>
            <p class="mt-1 text-sm text-gray-500">
                Monitoreo en tiempo real de obligaciones mensuales y alertas de vencimiento SUNAT.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm">
                <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                Periodo a declarar: <strong class="font-semibold text-gray-900">{{ $nombreMesActual }}</strong>
                <span class="rounded bg-brand-50 px-1.5 py-0.5 text-xs font-semibold text-brand-700">{{ $periodoActual }}</span>
                <span class="hidden sm:inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-800 border border-amber-200">
                    Vence en {{ $mesVencimientoNombre }}
                </span>
            </span>

            <a href="{{ route('cronogramas.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                Cronograma SUNAT
            </a>

            <a href="{{ route('clientes.index') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                Ver Clientes
            </a>
        </div>
    </div>

    {{-- ═══════════════════════════════ FILA 1: TARJETAS KPI ═══════════════════════════════ --}}
    <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

        {{-- KPI 1: Clientes Activos --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Clientes Activos</p>
                    <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $totalClientesActivos }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-brand-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs text-gray-500">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                <span>{{ $totalClientesInactivos }} clientes inactivos en el sistema</span>
            </div>
        </div>

        {{-- KPI 2: Buzón SOL Pendiente --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Buzón SOL Pendiente</p>
                    <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $notificacionesPendientes }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $notificacionesImportantes > 0 ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600' }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs">
                @if ($notificacionesImportantes > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-0.5 font-semibold text-red-700">
                        <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                        {{ $notificacionesImportantes }} de alta prioridad
                    </span>
                @else
                    <span class="text-gray-500">Sin mensajes críticos pendientes</span>
                @endif
            </div>
        </div>

        {{-- KPI 3: Declaraciones Realizadas --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Avance Declarado</p>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-emerald-600">{{ $declarados }}</span>
                        <span class="text-sm font-medium text-gray-400">/ {{ $totalObligaciones }}</span>
                    </div>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            {{-- Mini barra de progreso --}}
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                    <span>Progreso total</span>
                    <span class="font-semibold text-emerald-600">{{ $porcentajeAvance }}%</span>
                </div>
                <div class="h-1.5 w-full rounded-full bg-gray-100">
                    <div class="h-1.5 rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $porcentajeAvance }}%"></div>
                </div>
            </div>
        </div>

        {{-- KPI 4: Tareas Pendientes / En Proceso --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Obligaciones Pendientes</p>
                    <p class="mt-2 text-3xl font-extrabold text-rose-600">{{ $pendientes }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 text-xs text-gray-500">
                <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                <span>{{ $procesando }} obligaciones en proceso</span>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════ FILA 2: GRÁFICO + ALERTAS DE VENCIMIENTO ═══════════════════════════════ --}}
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 items-start">

        {{-- COLUMNA IZQUIERDA: GRÁFICO DINÁMICO (CHART.JS) --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-5">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Estado Tributario Mensual</h3>
                    <p class="text-xs text-gray-500 mt-0.5">SIRE, PDT 621 y PLE del periodo actual</p>
                </div>
                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">
                    {{ $porcentajeAvance }}% Listo
                </span>
            </div>

            {{-- Contenedor del Gráfico Doughnut --}}
            <div class="relative my-6 flex items-center justify-center">
                <div class="relative h-64 w-64 max-w-full">
                    <canvas id="obligacionesChart"></canvas>
                    {{-- Texto al centro de la dona --}}
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-3xl font-black text-gray-900">{{ $porcentajeAvance }}%</span>
                        <span class="text-[11px] font-medium uppercase tracking-wider text-gray-400">Declarado</span>
                    </div>
                </div>
            </div>

            {{-- Desglose y Leyenda --}}
            <div class="grid grid-cols-3 gap-2 border-t border-gray-100 pt-4 text-center">
                <div class="rounded-lg bg-emerald-50/50 p-2.5">
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Declaradas
                    </span>
                    <p class="mt-1 text-lg font-bold text-emerald-800">{{ $declarados }}</p>
                </div>

                <div class="rounded-lg bg-amber-50/50 p-2.5">
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span> En Proceso
                    </span>
                    <p class="mt-1 text-lg font-bold text-amber-800">{{ $procesando }}</p>
                </div>

                <div class="rounded-lg bg-rose-50/50 p-2.5">
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-700">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span> Pendientes
                    </span>
                    <p class="mt-1 text-lg font-bold text-rose-800">{{ $pendientes }}</p>
                </div>
            </div>

            <p class="mt-4 text-center text-xs text-gray-400">
                Total: {{ $totalObligaciones }} obligaciones controladas en este periodo
            </p>
        </div>

        {{-- COLUMNA DERECHA: PANEL DE ALERTAS DE VENCIMIENTO (ORDENADO 0-9 SUNAT) --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-7">
            <div class="flex flex-col gap-2 border-b border-gray-100 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-900">Alertas de Vencimiento de Impuestos</h3>
                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700">
                            {{ count($alertasVencimiento) }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Período tributario <strong>{{ $nombreMesActual }}</strong> (vencimientos en {{ $mesVencimientoNombre }} según cronograma oficial SUNAT).
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('cronogramas.index') }}"
                       class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700 transition">
                        <span>Ver Cronograma Completo</span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Listado de Clientes con Tareas Pendientes --}}
            @if (count($alertasVencimiento) === 0)
                <div class="flex flex-col items-center justify-center p-12 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 mb-3">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <h4 class="text-base font-bold text-gray-900">¡Todo al día!</h4>
                    <p class="mt-1 text-sm text-gray-500 max-w-sm">
                        Todos los clientes activos han completado sus declaraciones de SIRE, PDT 621 y PLE correspondientes a este mes.
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100 max-h-[580px] overflow-y-auto">
                    @foreach ($alertasVencimiento as $item)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 transition hover:bg-gray-50/80">

                            {{-- Información del Cliente y Dígito --}}
                            <div class="flex items-start gap-3.5">
                                {{-- Badge destacado de Último Dígito RUC --}}
                                <div class="flex flex-col items-center justify-center rounded-xl bg-gradient-to-b from-blue-50 to-indigo-100 border border-blue-200 px-3 py-1.5 text-center shrink-0 min-w-[52px] shadow-sm">
                                    <span class="text-[9px] uppercase font-bold text-blue-600 tracking-wider">Dígito</span>
                                    <span class="text-2xl font-black text-blue-800 leading-none mt-0.5">{{ $item['ultimo_digito_str'] }}</span>
                                </div>

                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('clientes.show', $item['cliente']) }}"
                                           class="text-sm font-semibold text-gray-900 hover:text-brand-600 transition">
                                            {{ $item['cliente']->razon_social }}
                                        </a>

                                        @php
                                            $colores = [
                                                'MYPE'    => 'bg-blue-100 text-blue-700',
                                                'RER'     => 'bg-amber-100 text-amber-700',
                                                'RUS'     => 'bg-emerald-100 text-emerald-700',
                                                'General' => 'bg-purple-100 text-purple-700',
                                            ];
                                            $colorBadge = $colores[$item['cliente']->regimen] ?? 'bg-gray-100 text-gray-700';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $colorBadge }}">
                                            {{ $item['cliente']->regimen }}
                                        </span>

                                        @if ($item['cliente']->es_buen_contribuyente)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                                ⭐ Buen Contribuyente
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 font-mono">
                                        <span>RUC: {{ substr($item['cliente']->ruc, 0, -1) }}<strong class="text-blue-700 font-extrabold underline decoration-blue-400 decoration-2">{{ $item['ultimo_digito_str'] }}</strong></span>
                                    </div>

                                    {{-- Vencimientos Oficiales SUNAT por Dígito --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        {{-- Semáforo Dinámico según Cronograma --}}
                                        <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $item['estado_critico']['badge_clase'] }}">
                                            {{ $item['estado_critico']['badge_texto'] }}
                                        </span>

                                        @if ($item['fecha_sire'])
                                            <span class="inline-flex items-center rounded-md bg-purple-50 border border-purple-200 px-2 py-0.5 text-[10px] font-semibold text-purple-700" title="SIRE vence 1 día hábil antes del PDT">
                                                SIRE: {{ $item['fecha_sire']->format('d/m') }}
                                            </span>
                                        @endif

                                        @if ($item['fecha_pdt'])
                                            <span class="inline-flex items-center rounded-md bg-gray-50 border border-gray-200 px-2 py-0.5 text-[10px] font-medium text-gray-600">
                                                PDT 621: {{ $item['fecha_pdt']->format('d/m') }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Etiquetas de obligaciones faltantes --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="text-[11px] font-medium text-gray-400 mr-0.5">Falta:</span>
                                        @foreach ($item['faltantes'] as $faltante)
                                            @if ($faltante['estado'] === 'Procesando')
                                                <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 border border-amber-200 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                    {{ $faltante['nombre'] }} (En Proceso)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 border border-rose-200 px-2 py-0.5 text-[11px] font-medium text-rose-700">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                    {{ $faltante['nombre'] }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Botón de Acción Rápida (Directo a Operaciones) --}}
                            <div class="shrink-0 flex items-center justify-end sm:self-center">
                                <a href="{{ route('clientes.show', ['cliente' => $item['cliente']->id, 'tab' => 'operaciones']) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    <span>Declarar</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </a>
                            </div>

                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- ═══════════════════════════════ SCRIPTS: CHART.JS ═══════════════════════════════ --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('obligacionesChart');
            if (!ctx) return;

            const declarados = {{ $declarados }};
            const procesando = {{ $procesando }};
            const pendientes = {{ $pendientes }};
            const total = {{ $totalObligaciones }};

            const data = (total === 0)
                ? [0, 0, 1]
                : [declarados, procesando, pendientes];

            const backgroundColors = (total === 0)
                ? ['#e5e7eb', '#e5e7eb', '#e5e7eb']
                : ['#10b981', '#f59e0b', '#ef4444'];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Declaradas', 'En Proceso', 'Pendientes'],
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        hoverBackgroundColor: (total === 0)
                            ? ['#d1d5db', '#d1d5db', '#d1d5db']
                            : ['#059669', '#d97706', '#dc2626'],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: total > 0,
                            callbacks: {
                                label: function (context) {
                                    const val = context.raw || 0;
                                    const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                    return ` ${context.label}: ${val} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endsection
