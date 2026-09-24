@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- ═══════════════════════════════ ENCABEZADO Y PERIODO ═══════════════════════════════ --}}
    <div class="mb-8 flex w-full items-center justify-between">
        <div class="min-w-0 shrink-0">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">
                <time class="inline-flex items-baseline gap-2">
                    <span>{{ $fechaHoy }}</span>
                    <span aria-hidden="true">·</span>
                    <span data-reloj-lima class="tabular-nums">{{ $horaHoy }}</span>
                </time>
            </p>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $saludo }}@if ($nombreUsuario !== ''), {{ $nombreUsuario }}@endif 👋</h2>
            <p class="mt-1 text-sm text-gray-500">Radiografía en tiempo real del estado de tus clientes.</p>
        </div>

        <div class="flex min-w-0 flex-1 flex-wrap items-center justify-end gap-4">
            <div class="flex shrink-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-xs">
                <div>
                    <p class="whitespace-nowrap text-[11px] font-medium leading-none text-gray-500">Periodo a declarar:</p>
                    <p class="mt-1 whitespace-nowrap text-sm font-semibold leading-none text-gray-900">{{ $nombreMesActual }}</p>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <span class="rounded bg-brand-50 px-1.5 py-0.5 text-xs font-semibold leading-none text-brand-700">{{ $periodoActual }}</span>
                    <span class="rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium leading-none text-amber-800">
                        Vence en {{ $mesVencimientoNombre }}
                    </span>
                </div>
            </div>

            <a href="{{ route('tipo-cambio.index') }}"
               data-tipo-cambio
               data-tc-url="{{ route('tipo-cambio.actual') }}"
               title="Ver tipo de cambio"
               class="flex shrink-0 flex-col justify-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-xs transition hover:border-brand-300 hover:shadow-md">
                <p class="whitespace-nowrap text-[11px] font-medium leading-none text-gray-500">
                    Tipo de cambio<span data-tc-fecha class="font-semibold tabular-nums text-gray-700">@if ($tipoCambio) · {{ $tipoCambio['fecha_corta'] }}@endif</span>
                </p>
                <div data-tc-lleno @unless ($tipoCambio) hidden @endunless class="flex items-center gap-2 whitespace-nowrap leading-none">
                    <span class="text-[11px] font-medium text-emerald-700">Compra <span data-tc-compra class="text-xs font-bold tabular-nums text-gray-900">{{ $tipoCambio['compra_texto'] ?? '' }}</span></span>
                    <span class="text-[11px] font-medium text-sky-700">Venta <span data-tc-venta class="text-xs font-bold tabular-nums text-sky-800">{{ $tipoCambio['venta_texto'] ?? '' }}</span></span>
                </div>
                <p data-tc-vacio @if ($tipoCambio) hidden @endif class="whitespace-nowrap text-xs font-semibold leading-none text-gray-900">Sin cotización</p>
            </a>

            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('cronogramas.index') }}"
                   class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    Cronograma SUNAT
                </a>

                <a href="{{ route('clientes.index') }}"
                   class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    Ver Clientes
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════ FILA 1: TARJETAS KPI ═══════════════════════════════ --}}
    <div class="mb-8 grid grid-cols-1 items-stretch gap-4 md:grid-cols-2 xl:grid-cols-5">

        {{-- KPI 1: Clientes Activos --}}
        <div class="relative flex h-full min-w-0 flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-xs transition hover:border-brand-300 hover:shadow-md">
            <div class="flex-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Clientes Activos</p>
                <a href="{{ route('clientes.index', ['estado' => 'activo']) }}"
                   title="Ver clientes activos"
                   class="mt-2 flex items-center justify-between">
                    <p class="text-3xl font-extrabold text-gray-900">{{ $totalClientesActivos }}</p>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-brand-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                </a>
                <a href="{{ route('clientes.create') }}" class="mt-3 w-full flex items-center justify-center gap-1.5 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg text-[13px] font-semibold transition-all duration-200">+ Registrar cliente nuevo</a>
            </div>
            <div class="mt-4 flex items-center gap-2 overflow-hidden whitespace-nowrap border-t border-gray-100 pt-3">
                <span class="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-600">Suspendidos ({{ $totalClientesSuspendidos }})</span>
                <span class="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-600">Retirados ({{ $totalClientesRetirados }})</span>
            </div>
        </div>

        {{-- KPI 2: Avance de impuestos del periodo en curso --}}
        <a href="{{ route('control.index', ['anio' => (int) explode('-', $periodoActual)[1]]) }}"
           title="Ver control de impuestos"
           class="relative flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-xs transition hover:border-emerald-300 hover:shadow-md">
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Avance Impuestos</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold text-emerald-600">{{ $declarados }}</span>
                            <span class="text-sm font-medium text-gray-400">/ {{ $totalObligaciones }}</span>
                        </div>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                        <span>Progreso total</span>
                        <span class="font-semibold text-emerald-600">{{ $porcentajeAvance }}%</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-gray-100">
                        <div class="h-1.5 rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $porcentajeAvance }}%"></div>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 overflow-hidden whitespace-nowrap border-t border-gray-100 pt-3">
                <span class="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-600">Pendientes ({{ $pendientes ?? 0 }})</span>
                <span class="rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800">En Proceso ({{ $procesando ?? 0 }})</span>
            </div>
        </a>

        {{-- KPI: Avance de balances mensuales PLE (plazo máximo de atraso: 3 meses) --}}
        <a href="{{ route('balances.index', ['anio' => $avanceBalances['anio']]) }}"
           class="relative flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-xs transition hover:border-emerald-300 hover:shadow-md">
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Avance Balances</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold text-emerald-600">{{ $avanceBalances['declarados'] }}</span>
                            <span class="text-sm font-medium text-gray-400">/ {{ $avanceBalances['total'] }}</span>
                        </div>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                        <span>{{ $avanceBalances['nombre'] }}</span>
                        <span class="font-semibold text-emerald-600">{{ $avanceBalances['porcentaje'] }}%</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-gray-100">
                        <div class="h-1.5 rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $avanceBalances['porcentaje'] }}%"></div>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500">
                        @if ($avanceBalances['vence'])
                            Vence el {{ $avanceBalances['vence'] }} · plazo PLE 3 meses
                        @else
                            Plazo máximo de atraso PLE: 3 meses
                        @endif
                    </p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 overflow-hidden whitespace-nowrap border-t border-gray-100 pt-3">
                <span class="rounded-md bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-600">Pendientes ({{ $avanceBalances['pendientes'] ?? 0 }})</span>
                <span class="rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800">En Proceso ({{ $avanceBalances['procesando'] ?? 0 }})</span>
            </div>
        </a>

        {{-- KPI 4: Buzón SOL Pendiente --}}
        <a href="{{ route('alertas.index', $notificacionesPendientes > 0 ? ['estado' => 'Pendiente'] : []) }}"
           title="{{ $notificacionesPendientes > 0 ? 'Ver alertas pendientes del buzón SOL' : 'Abrir buzón de alertas' }}"
           class="relative flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-xs transition hover:border-amber-300 hover:shadow-md">
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Buzón SOL Pendiente</p>
                        <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ $notificacionesPendientes }}</p>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $notificacionesImportantes > 0 ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600' }}">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke-currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 overflow-hidden whitespace-nowrap border-t border-gray-100 pt-3">
                <span class="rounded-md bg-red-50 px-2 py-1 text-[11px] font-medium text-red-700">SUNAT ({{ $notificacionesSunat }})</span>
                <span class="rounded-md bg-red-50 px-2 py-1 text-[11px] font-medium text-red-700">SUNAFIL ({{ $notificacionesSunafil }})</span>
            </div>
        </a>

        {{-- KPI 5: Honorarios (RH) de los meses ya cerrados --}}
        <a href="{{ route('control.index', ['anio' => $honorariosAtrasados['anio']]) }}"
           title="Ver control de honorarios"
           class="relative flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-xs transition hover:border-rose-300 hover:shadow-md">
            <div class="flex-1">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Control Honorarios</p>
                        @if (($honorariosAtrasados['total'] ?? 0) === 0)
                            <p class="mt-2 text-3xl font-extrabold text-emerald-500">0</p>
                            <p class="mt-1 text-xs font-medium text-gray-500">Cartera al día, sin deudas</p>
                        @else
                            <p class="mt-2 text-3xl font-extrabold text-rose-600">{{ $honorariosAtrasados['total'] }}</p>
                            <p class="mt-1 text-xs font-medium text-gray-500">Pendientes de cobro</p>
                        @endif
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 overflow-hidden whitespace-nowrap border-t border-gray-100 pt-3">
                @if (($honorariosAtrasados['total'] ?? 0) === 0)
                    <span class="rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-600">✨ 100% Cobrado</span>
                @else
                    @foreach ($honorariosAtrasados['meses'] as $mesHonorario)
                        <span class="rounded-md bg-red-50 px-2 py-1 text-[11px] font-medium text-red-700">{{ $mesHonorario['etiqueta'] }} ({{ $mesHonorario['cantidad'] }})</span>
                    @endforeach
                @endif
            </div>
        </a>

    </div>

    {{-- ═══════════════════════════════ FILA 2: GRÁFICO + ALERTAS DE VENCIMIENTO ═══════════════════════════════ --}}
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 items-start">

        {{-- COLUMNA IZQUIERDA: GRÁFICO Y, DEBAJO, RADAR DE VENCIMIENTOS --}}
        <div class="flex h-full min-w-0 flex-col gap-8 self-stretch lg:col-span-5">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-xs">
            <div class="border-b border-gray-100 pb-4">
                <h3 class="text-base font-semibold text-gray-900">Estado General del Estudio</h3>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-6 divide-x divide-gray-100">
                <div class="min-w-0 pr-1">
                    <p class="text-sm font-semibold text-gray-900">Obligaciones Mensuales</p>
                    <p class="mt-0.5 text-xs text-gray-500">SIRE, PDT 621 y PLAME</p>
                    <div class="relative my-4 flex items-center justify-center">
                        <div class="relative h-36 w-36 max-w-full">
                            <canvas id="obligacionesChart"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="text-2xl font-black text-gray-900">{{ $porcentajeAvance }}%</span>
                                <span class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Declarado</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-1.5 border-t border-gray-100 pt-3">
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50/50 px-2 py-1">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Declaradas
                            </span>
                            <span class="text-sm font-bold text-emerald-800">{{ $declarados }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-amber-50/50 px-2 py-1">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> En Proceso
                            </span>
                            <span class="text-sm font-bold text-amber-800">{{ $procesando }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-rose-50/50 px-2 py-1">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-rose-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Pendientes
                            </span>
                            <span class="text-sm font-bold text-rose-800">{{ $pendientes }}</span>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 pl-5">
                    <p class="text-sm font-semibold text-gray-900">Avance Balances</p>
                    <p class="mt-0.5 text-xs text-gray-500">PLE y DJ Anual</p>
                    <div class="relative my-4 flex items-center justify-center">
                        <div class="relative h-36 w-36 max-w-full">
                            <canvas id="balancesChart"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="text-2xl font-black text-gray-900">{{ $avanceBalances['porcentaje'] }}%</span>
                                <span class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Declarado</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-1.5 border-t border-gray-100 pt-3">
                        <div class="flex items-center justify-between rounded-lg bg-emerald-50/50 px-2 py-1">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Declaradas
                            </span>
                            <span class="text-sm font-bold text-emerald-800">{{ $avanceBalances['declarados'] }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-amber-50/50 px-2 py-1">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> En Proceso
                            </span>
                            <span class="text-sm font-bold text-amber-800">{{ $avanceBalances['procesando'] }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-rose-50/50 px-2 py-1">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-rose-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Pendientes
                            </span>
                            <span class="text-sm font-bold text-rose-800">{{ $avanceBalances['pendientes'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col rounded-xl border border-gray-200 bg-white p-6 shadow-xs">
            <h3 class="text-base font-semibold text-gray-900">Radar de Vencimientos (Mes Activo)</h3>
            <p class="mt-0.5 text-xs text-gray-500">Dígito de RUC y buenos contribuyentes, según el cronograma SUNAT.</p>

            <div class="mt-5 flex flex-1 flex-col justify-between gap-6">
                <div>
                    <div class="flex flex-wrap items-center">
                        <p class="text-xs font-semibold text-gray-700">Impuestos (SIRE y PDT 621)</p>
                        <span class="ml-2 inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700">{{ $radarVencimientos['impuestos']['periodo'] }}</span>
                    </div>
                    @include('dashboard.partials.radar-fechas', ['fechas' => $radarVencimientos['impuestos']['fechas'], 'conSire' => true])
                </div>

                <div>
                    <div class="flex flex-wrap items-center">
                        <p class="text-xs font-semibold text-gray-700">Balances Mensuales (PLE)</p>
                        <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">{{ $radarVencimientos['balances']['periodo'] }}</span>
                    </div>
                    @include('dashboard.partials.radar-fechas', ['fechas' => $radarVencimientos['balances']['fechas'], 'conSire' => false])
                </div>
            </div>
        </div>
        </div>

        {{-- COLUMNA DERECHA: ALERTAS DE IMPUESTOS Y, DEBAJO, ALERTAS DE BALANCES --}}
        <div class="flex flex-col gap-8 lg:col-span-7">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
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
                        Todos los clientes activos han completado sus declaraciones de SIRE, PDT 621 y PLAME correspondientes a este mes.
                    </p>
                </div>
            @else
                <div class="custom-scrollbar max-h-[432px] divide-y divide-gray-100 overflow-y-auto pr-2">
                    @foreach ($alertasVencimiento as $item)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 transition hover:bg-gray-50/80">

                            {{-- Información del Cliente y Dígito --}}
                            <div class="flex items-start gap-3.5">
                                {{-- Badge destacado de Último Dígito RUC --}}
                                <div class="flex flex-col items-center justify-center rounded-xl bg-gradient-to-b from-blue-50 to-indigo-100 border border-blue-200 px-3 py-1.5 text-center shrink-0 min-w-[52px] shadow-xs">
                                    <span class="text-[9px] uppercase font-bold text-blue-600 tracking-wider">Dígito</span>
                                    <span class="text-2xl font-black text-blue-800 leading-none mt-0.5">{{ $item['ultimo_digito_str'] }}</span>
                                </div>

                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="{{ route('clientes.show', $item['cliente']) }}"
                                           class="text-sm font-semibold text-gray-900 hover:text-brand-600 transition">
                                            {{ $item['cliente']->razon_social }}
                                        </a>

                                        <x-ui.badge-regimen :regimen="$item['cliente']->regimen_tributario" />

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
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
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

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
        <div class="flex flex-col gap-2 border-b border-gray-100 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-gray-900">Alertas de Vencimiento de Balances</h3>
                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700">
                        {{ count($alertasBalances) }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-gray-500">
                    Balances mensuales del PLE. SUNAT da un plazo máximo de 3 meses; aquí aparecen los periodos ya exigibles que todavía no están declarados.
                </p>
            </div>
            <a href="{{ route('balances.index', ['anio' => $avanceBalances['anio']]) }}"
               class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-bold text-brand-600 transition hover:text-brand-700">
                <span>Ver Control de Balances</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </a>
        </div>

        @if (count($alertasBalances) === 0)
            <div class="flex flex-col items-center justify-center p-12 text-center">
                <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <h4 class="text-base font-bold text-gray-900">Balances al día</h4>
                <p class="mt-1 max-w-sm text-sm text-gray-500">
                    Los clientes de MYPE y Régimen General no tienen balances mensuales vencidos ni pendientes en el plazo del PLE.
                </p>
            </div>
        @else
            <div class="custom-scrollbar max-h-[432px] divide-y divide-gray-100 overflow-y-auto pr-2">
                @foreach ($alertasBalances as $item)
                    <div class="flex flex-col justify-between gap-4 p-4 transition hover:bg-gray-50/80 sm:flex-row sm:items-center">
                        <div class="flex items-start gap-3.5">
                            <div class="flex min-w-[52px] shrink-0 flex-col items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-center shadow-xs">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-amber-700">Meses</span>
                                <span class="mt-0.5 text-2xl font-black leading-none text-amber-900">{{ count($item['meses']) }}</span>
                            </div>
                            <div>
                                <a href="{{ route('clientes.show', $item['cliente']) }}"
                                   class="text-sm font-semibold text-gray-900 transition hover:text-brand-600">
                                    {{ $item['cliente']->razon_social }}
                                </a>
                                <div class="mt-1 flex items-center gap-2">
                                    <x-ui.badge-regimen :regimen="$item['cliente']->regimen_tributario" />
                                    <span class="font-mono text-xs text-gray-500">RUC: {{ $item['cliente']->ruc }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $item['estado_critico']['badge_clase'] }}">
                                        {{ $item['estado_critico']['badge_texto'] }}
                                    </span>
                                    @foreach ($item['meses'] as $mesBalance)
                                        @if ($mesBalance['estado'] === 'Procesando' || $mesBalance['estado'] === 'Proceso')
                                            <span class="inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                {{ $mesBalance['nombre'] }} (En proceso)
                                            </span>
                                        @elseif ($mesBalance['estado_critico']['estado'] === 'vencido')
                                            <span class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                {{ $mesBalance['nombre'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                                                {{ $mesBalance['nombre'] }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center justify-end sm:self-center">
                            <a href="{{ route('balances.index', ['anio' => $item['anio']]) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                <span>Registrar</span>
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

    </div>

    {{-- ═══════════════════════════════ SCRIPTS: CHART.JS ═══════════════════════════════ --}}
    {{-- Chart.js se carga aparte y solo en esta pagina. Ver resources/js/app.js. --}}
    <script>
        function renderDoughnut(canvasId, declarados, procesando, pendientes, total) {
            const ctx = document.getElementById(canvasId);
            if (!ctx || !window.Chart || ctx.dataset.rendered === '1') return;
            ctx.dataset.rendered = '1';

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
                        legend: { display: false },
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
        }

        function renderDashboardCharts() {
            renderDoughnut('obligacionesChart', {{ $declarados }}, {{ $procesando }}, {{ $pendientes }}, {{ $totalObligaciones }});
            renderDoughnut('balancesChart', {{ $avanceBalances['declarados'] }}, {{ $avanceBalances['procesando'] }}, {{ $avanceBalances['pendientes'] }}, {{ $avanceBalances['total'] }});
        }

        if (window.Chart) {
            renderDashboardCharts();
        } else {
            document.addEventListener('crm:chart-ready', renderDashboardCharts, { once: true });
        }

        (function () {
            const reloj = document.querySelector('[data-reloj-lima]');
            if (!reloj) {
                return;
            }

            const pintar = function () {
                const partes = new Intl.DateTimeFormat('en-US', {
                    timeZone: 'America/Lima',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true,
                }).formatToParts(new Date());
                const hora = partes.find(function (parte) { return parte.type === 'hour'; }).value;
                const minuto = partes.find(function (parte) { return parte.type === 'minute'; }).value;
                const periodo = partes.find(function (parte) { return parte.type === 'dayPeriod'; }).value.toUpperCase();

                reloj.textContent = hora + ':' + minuto + ' ' + periodo;
            };

            pintar();
            window.setInterval(pintar, 10000);
        })();

        (function () {
            const raiz = document.querySelector('[data-tipo-cambio]');
            if (!raiz) {
                return;
            }

            const lleno = raiz.querySelector('[data-tc-lleno]');
            const vacio = raiz.querySelector('[data-tc-vacio]');
            const fecha = raiz.querySelector('[data-tc-fecha]');
            const compra = raiz.querySelector('[data-tc-compra]');
            const venta = raiz.querySelector('[data-tc-venta]');

            const pintar = function (dato) {
                if (!dato || !dato.disponible) {
                    if (lleno) lleno.hidden = true;
                    if (vacio) vacio.hidden = false;
                    if (fecha) fecha.textContent = '';
                    return;
                }

                if (fecha) fecha.textContent = dato.fecha_corta ? ' · ' + dato.fecha_corta : '';
                if (compra) compra.textContent = dato.compra;
                if (venta) venta.textContent = dato.venta;
                if (lleno) lleno.hidden = false;
                if (vacio) vacio.hidden = true;
            };

            const consultar = function () {
                if (document.hidden) {
                    return;
                }

                window.fetch(raiz.dataset.tcUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (respuesta) {
                    if (!respuesta.ok) {
                        return null;
                    }

                    return respuesta.json();
                }).then(function (dato) {
                    if (dato) {
                        pintar(dato);
                    }
                }).catch(function () {});
            };

            window.setInterval(consultar, 60000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    consultar();
                }
            });
        })();
    </script>
@endsection
