@php
    $anioActualExtracto = $extractoDetracciones['anio'] ?? now()->year;
    $mesActualExtracto = $extractoDetracciones['mes'] ?? null;
    $aniosDisponibles = $extractoDetracciones['anios_disponibles'] ?? [$anioActualExtracto];
    $movimientos = $extractoDetracciones['movimientos'] ?? collect();
    $saldoInicial = (float) ($extractoDetracciones['saldo_inicial'] ?? 0);
    $totalDepositos = (float) ($extractoDetracciones['total_depositos'] ?? 0);
    $totalRetiros = (float) ($extractoDetracciones['total_retiros'] ?? 0);
    $saldoFinal = (float) ($extractoDetracciones['saldo_final'] ?? 0);
    $mesesResumen = $extractoDetracciones['meses_resumen'] ?? [];
    $cuentaBn = $cliente->credencial?->cuenta_detracciones;
@endphp

<div x-data="extractoDetraccionesApp({
    clienteId: {{ $cliente->id }},
    clienteRuc: @js($cliente->ruc),
    clienteRazon: @js($cliente->razon_social),
    cuentaActualBn: @js($cuentaBn ?? ''),
    anio: {{ $anioActualExtracto }},
    mes: {{ $mesActualExtracto !== null ? $mesActualExtracto : 'null' }},
    urls: {
        cargarPdf: @js(route('clientes.detracciones.cargar-pdf', $cliente)),
        guardarMovimiento: @js(route('clientes.detracciones.movimiento.store', $cliente)),
        saldoInicial: @js(route('clientes.detracciones.saldo-inicial', $cliente)),
        saldoSugerido: @js(route('clientes.detracciones.saldo-sugerido', $cliente)),
        imprimir: @js(route('clientes.detracciones.imprimir', ['cliente' => $cliente, 'anio' => $anioActualExtracto, 'mes' => $mesActualExtracto])),
        exportar: @js(route('clientes.detracciones.exportar', ['cliente' => $cliente, 'anio' => $anioActualExtracto, 'mes' => $mesActualExtracto])),
    }
})" class="space-y-5">

    {{-- ═══════════════════════════ TARJETA DE IDENTIDAD BANCARIA BN ═══════════════════════════ --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                {{-- Escudo / Logotipo Banco de la Nación SPOT --}}
                <div style="background-color: #dc2626 !important; color: #ffffff !important;" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-red-600 text-white shadow-md shadow-red-600/30 border border-red-700">
                    <span class="text-xl font-black tracking-tight text-white select-none" style="color: #ffffff !important; font-family: ui-sans-serif, system-ui, sans-serif;">BN</span>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-xs font-bold text-rose-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                            Banco de la Nación - Cuenta SPOT (D. Leg. 940)
                        </span>
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700">
                            Moneda: Soles (S/)
                        </span>
                    </div>
                    <h3 class="mt-1 text-lg font-black text-slate-900 flex items-center gap-2">
                        <span>Extracto de Cuenta de Detracciones</span>
                        <span class="text-xs font-normal text-slate-400">|</span>
                        <span class="text-sm font-semibold text-slate-600">{{ $cliente->razon_social }}</span>
                    </h3>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <span class="font-mono">RUC: <strong class="text-slate-800">{{ $cliente->ruc }}</strong></span>
                        <span class="text-slate-300">•</span>
                        <span class="flex items-center gap-1.5">
                            <span>N° Cuenta BN:</span>
                            @if (!empty($cuentaBn))
                                <span class="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200" x-ref="ctaBnText">{{ $cuentaBn }}</span>
                                <button type="button"
                                        @click="copiarTexto('{{ $cuentaBn }}', 'Número de cuenta copiado')"
                                        class="text-slate-400 hover:text-slate-700 transition"
                                        title="Copiar número de cuenta de detracciones">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                    </svg>
                                </button>
                            @else
                                <span class="italic text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 text-[11px]">No registrada en ficha</span>
                                <button type="button"
                                        @click="abrirModalSaldoInicial()"
                                        class="text-xs text-blue-600 hover:underline font-semibold">
                                    Configurar
                                </button>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Selector de Ejercicio (Años) y Botonera Superior --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                {{-- Selector de Años --}}
                <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1 border border-slate-200 self-start sm:self-auto flex-wrap">
                    <span class="px-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Año:</span>
                    @foreach ($aniosDisponibles as $anioItem)
                        <a href="{{ route('clientes.detracciones', ['cliente' => $cliente, 'anio' => $anioItem]) }}"
                           class="rounded-lg px-2.5 py-1 text-xs font-bold transition {{ (int) $anioItem === (int) $anioActualExtracto ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            {{ $anioItem }}
                        </a>
                    @endforeach
                    <button type="button"
                            @click="abrirModalAperturaAnio({{ $anioActualExtracto + 1 }})"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition"
                            title="Abrir o configurar un nuevo ejercicio fiscal sin código">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Nuevo Año</span>
                    </button>
                </div>

                {{-- Acciones ejecutivas: Imprimir y Exportar --}}
                <div class="flex items-center gap-1.5">
                    <a href="{{ route('clientes.detracciones.imprimir', ['cliente' => $cliente, 'anio' => $anioActualExtracto, 'mes' => $mesActualExtracto]) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition active:scale-95"
                       title="Imprimir extracto oficial en formato Banco de la Nación">
                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.056-.84-2.029-1.72-2.829a8.966 8.966 0 0 1 12 0c-.88.8-1.48 1.773-1.72 2.829m-8.56 0A2.25 2.25 0 0 0 4.5 15.75v3.75a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V15.75a2.25 2.25 0 0 0-2.22-1.921m-8.56 0c.24.47.6.87 1.06 1.171m6.44-1.171c-.24.47-.6.87-1.06 1.171M6.75 6.75h10.5a2.25 2.25 0 0 1 2.25 2.25v1.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 10.5v-1.5a2.25 2.25 0 0 1 2.25-2.25Z" />
                        </svg>
                        <span>Imprimir</span>
                    </a>

                    <a href="{{ route('clientes.detracciones.exportar', ['cliente' => $cliente, 'anio' => $anioActualExtracto, 'mes' => $mesActualExtracto]) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition active:scale-95"
                       title="Exportar extracto a archivo CSV / Excel">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.5V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span>Excel</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Barra de Selección de Meses (Ene - Dic) tipo Pills bancarios --}}
        <div class="mt-4 border-t border-slate-100 pt-3 flex flex-wrap items-center gap-1">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Periodo:</span>
            <a href="{{ route('clientes.detracciones', ['cliente' => $cliente, 'anio' => $anioActualExtracto]) }}"
               class="rounded-lg px-2.5 py-1 text-xs font-bold transition {{ $mesActualExtracto === null ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                Todo el Año ({{ $anioActualExtracto }})
            </a>
            @foreach ($mesesResumen as $mIdx => $mInfo)
                @php
                    $esSeleccionado = ($mesActualExtracto === $mIdx);
                    $tieneMovimientos = ($mInfo['conteo'] > 0);
                @endphp
                <a href="{{ route('clientes.detracciones', ['cliente' => $cliente, 'anio' => $anioActualExtracto, 'detraccion_mes' => $mIdx]) }}"
                   class="relative rounded-lg px-2.5 py-1 text-xs font-bold transition flex items-center gap-1 {{ $esSeleccionado ? 'bg-rose-600 text-white shadow-xs' : ($tieneMovimientos ? 'bg-slate-100 text-slate-800 hover:bg-slate-200' : 'bg-slate-50/70 text-slate-400 hover:bg-slate-100') }}">
                    <span>{{ $mInfo['corto'] }}</span>
                    @if ($mInfo['conteo'] > 0)
                        <span class="h-1.5 w-1.5 rounded-full {{ $esSeleccionado ? 'bg-white' : 'bg-rose-500' }}"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- ═══════════════════════════ TARJETAS KPI DE SALDOS Y MOVIMIENTOS ═══════════════════════════ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {{-- KPI 1: Saldo Inicial --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Saldo Inicial</span>
                <button type="button"
                        @click="abrirModalSaldoInicial()"
                        class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-0.5"
                        title="Ajustar saldo inicial de apertura del ejercicio">
                    <span>Ajustar</span>
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                </button>
            </div>
            <p class="mt-2 font-mono text-xl font-black text-slate-900 tabular-nums">
                S/ {{ number_format($saldoInicial, 2) }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-400">
                {{ $mesActualExtracto !== null ? 'Saldo previo al 01/'.sprintf('%02d', $mesActualExtracto) : 'Saldo de apertura al 01/01/'.$anioActualExtracto }}
            </p>
        </div>

        {{-- KPI 2: Total Depósitos (Ingresos SPOT) --}}
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Depósitos (Abonos +)</span>
                <span class="rounded-full bg-emerald-100 border border-emerald-200 px-2 py-0.2 text-[10px] font-mono font-bold text-emerald-800">
                    {{ $extractoDetracciones['conteo_depositos'] }} constancias
                </span>
            </div>
            <p class="mt-2 font-mono text-xl font-black text-emerald-950 tabular-nums">
                S/ {{ number_format($totalDepositos, 2) }}
            </p>
            <p class="mt-0.5 text-[11px] text-emerald-700">
                Ingresos por SPOT facturas de venta
            </p>
        </div>

        {{-- KPI 3: Total Retiros (Cargos SUNAT) --}}
        <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-800">Retiros (Cargos -)</span>
                <span class="rounded-full bg-rose-100 border border-rose-200 px-2 py-0.2 text-[10px] font-mono font-bold text-rose-800">
                    {{ $extractoDetracciones['conteo_retiros'] }} pagos
                </span>
            </div>
            <p class="mt-2 font-mono text-xl font-black text-rose-950 tabular-nums">
                S/ {{ number_format($totalRetiros, 2) }}
            </p>
            <p class="mt-0.5 text-[11px] text-rose-700">
                Pagos de impuestos descontados
            </p>
        </div>

        {{-- KPI 4: Saldo Final Disponible --}}
        <div class="rounded-2xl border-2 {{ $saldoFinal >= 0 ? 'border-teal-500 bg-gradient-to-br from-teal-500/10 via-emerald-500/5 to-white' : 'border-rose-500 bg-rose-50' }} p-4 shadow-md">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider {{ $saldoFinal >= 0 ? 'text-teal-900' : 'text-rose-900' }}">Saldo Disponible</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $saldoFinal >= 0 ? 'bg-teal-100 text-teal-800 border border-teal-200' : 'bg-rose-100 text-rose-800' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $saldoFinal >= 0 ? 'bg-teal-600' : 'bg-rose-600' }}"></span>
                    {{ $saldoFinal >= 0 ? 'En Fondos' : 'En Descubierto' }}
                </span>
            </div>
            <p class="mt-2 font-mono text-2xl font-black {{ $saldoFinal >= 0 ? 'text-teal-950' : 'text-rose-950' }} tabular-nums">
                S/ {{ number_format($saldoFinal, 2) }}
            </p>
            <p class="mt-0.5 text-[11px] font-medium {{ $saldoFinal >= 0 ? 'text-teal-800' : 'text-rose-800' }}">
                Saldo contable neto resultante
            </p>
        </div>
    </div>

    {{-- ═══════════════════════════ BOTONERA PRINCIPAL DE OPERACIONES ═══════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5">
        <div class="flex flex-wrap items-center gap-2">
            {{-- Botón 1: Cargar Detracción Oficial (PDF) --}}
            <button type="button"
                    @click="abrirCargaPdf()"
                    style="background-color: #dc2626 !important; color: #ffffff !important;"
                    class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-red-600/30 hover:bg-red-700 transition active:scale-95 cursor-pointer">
                <svg class="h-4 w-4 text-white shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span class="text-white font-bold">Cargar Detracción Oficial (PDF)</span>
            </button>

            {{-- Botón 2: Registrar Depósito Manual --}}
            <button type="button"
                    @click="abrirModalMovimiento('deposito')"
                    class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2.5 text-xs font-bold text-emerald-800 shadow-xs hover:bg-emerald-100 transition active:scale-95 cursor-pointer">
                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>Depósito Manual</span>
            </button>

            {{-- Botón 3: Registrar Retiro Manual (Comisión BN, etc.) --}}
            <button type="button"
                    @click="abrirModalMovimiento('retiro')"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition active:scale-95 cursor-pointer">
                <svg class="h-4 w-4 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                </svg>
                <span>Retiro Bancario</span>
            </button>
        </div>

        <div class="text-xs text-slate-500 flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 border border-teal-200 px-2.5 py-0.5 font-medium text-teal-800 text-[11px]">
                <svg class="h-3 w-3 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                Sincronizado con Control de Impuestos
            </span>
        </div>
    </div>

    {{-- ═══════════════════════════ TABLA DEL EXTRACTO BANCARIO OFICIAL ═══════════════════════════ --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-3 flex items-center justify-between">
            <div>
                <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span>Libro de Movimientos - Cuenta de Detracciones</span>
                    <span class="rounded bg-slate-200 px-1.5 py-0.2 text-[10px] font-mono font-bold text-slate-700">
                        {{ $extractoDetracciones['conteo_total'] }} operaciones
                    </span>
                </h4>
                <p class="text-[11px] text-slate-500">
                    Extracto cronológico detallado con arrastre de saldos y conciliación de tributos SUNAT.
                </p>
            </div>
            <div class="text-right">
                <span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold block">Saldo al cierre:</span>
                <span class="font-mono text-sm font-black {{ $saldoFinal >= 0 ? 'text-teal-700' : 'text-rose-700' }}">
                    S/ {{ number_format($saldoFinal, 2) }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-600">
                        <th class="px-3.5 py-3 w-10 text-center">N°</th>
                        <th class="px-3.5 py-3 w-24">Fecha</th>
                        <th class="px-3.5 py-3 w-32">N° Operación / Const.</th>
                        <th class="px-3.5 py-3 w-28">Comprobante</th>
                        <th class="px-3.5 py-3">Descripción / Concepto</th>
                        <th class="px-3.5 py-3 w-40">Adquiriente / Cliente</th>
                        <th class="px-3.5 py-3 text-right w-28 text-emerald-800">Depósito (+)</th>
                        <th class="px-3.5 py-3 text-right w-28 text-rose-800">Retiro (-)</th>
                        <th class="px-3.5 py-3 text-right w-32 text-slate-900">Saldo (S/)</th>
                        <th class="px-3.5 py-3 text-center w-20">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    {{-- Fila destacada: Saldo Inicial / Apertura --}}
                    <tr class="bg-slate-50/60 font-semibold text-slate-600">
                        <td class="px-3.5 py-2.5 text-center text-slate-400 text-[11px]">—</td>
                        <td class="px-3.5 py-2.5 text-slate-500 font-sans text-[11px]">
                            {{ $mesActualExtracto !== null ? '01/'.sprintf('%02d', $mesActualExtracto).'/'.$anioActualExtracto : '01/01/'.$anioActualExtracto }}
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-400 text-[11px]">—</td>
                        <td class="px-3.5 py-2.5 text-slate-400 text-[11px]">—</td>
                        <td class="px-3.5 py-2.5 font-sans font-bold text-slate-800">
                            SALDO INICIAL / APERTURA DE CUENTA
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-400 font-sans text-[11px]">—</td>
                        <td class="px-3.5 py-2.5 text-right text-slate-300">—</td>
                        <td class="px-3.5 py-2.5 text-right text-slate-300">—</td>
                        <td class="px-3.5 py-2.5 text-right font-black text-slate-900 bg-slate-100/50">
                            S/ {{ number_format($saldoInicial, 2) }}
                        </td>
                        <td class="px-3.5 py-2.5 text-center">
                            <span class="inline-flex rounded bg-slate-200 px-1.5 py-0.5 text-[9px] font-bold text-slate-600 font-sans">
                                BASE
                            </span>
                        </td>
                    </tr>

                    @if ($movimientos->isEmpty())
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-500 font-sans">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 text-xl mb-2">
                                    💳
                                </div>
                                <p class="font-bold text-slate-700">No se registran movimientos en el periodo seleccionado.</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    Cargue las constancias oficiales de detracción SPOT (PDF) o registre abonos en Control de Impuestos.
                                </p>
                            </td>
                        </tr>
                    @else
                        @foreach ($movimientos as $index => $mov)
                            <tr class="hover:bg-slate-50/80 transition group {{ $mov->isDeposito() ? 'bg-white' : 'bg-slate-50/20' }}">
                                <td class="px-3.5 py-2.5 text-center text-[11px] text-slate-400">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-3.5 py-2.5 font-bold text-slate-800 text-[11px]">
                                    {{ $mov->fecha->format('d/m/Y') }}
                                </td>
                                <td class="px-3.5 py-2.5 text-[11px]">
                                    @if ($mov->numero_constancia)
                                        <span class="inline-block rounded bg-rose-50 border border-rose-200 px-1.5 py-0.2 text-[10px] font-bold text-rose-800" title="N° Constancia SPOT">
                                            C: {{ $mov->numero_constancia }}
                                        </span>
                                    @endif
                                    @if ($mov->numero_operacion)
                                        <span class="inline-block rounded bg-slate-100 px-1.5 py-0.2 text-[10px] text-slate-700 font-medium" title="N° Operación BN">
                                            Op: {{ $mov->numero_operacion }}
                                        </span>
                                    @endif
                                    @if (!$mov->numero_constancia && !$mov->numero_operacion)
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 font-sans">
                                    @if ($mov->comprobante_referencia)
                                        <span class="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-1.5 py-0.5 text-[11px] font-bold text-blue-800 font-mono">
                                            {{ $mov->comprobante_referencia }}
                                        </span>
                                    @else
                                        <span class="text-slate-300 font-mono">—</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 font-sans">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-slate-800 text-[12px]">{{ $mov->descripcion }}</span>
                                        @if ($mov->origen === 'control_impuestos')
                                            <span class="rounded bg-teal-100 border border-teal-200 px-1 py-0.2 text-[9px] font-bold text-teal-800" title="Generado desde liquidación de impuestos">
                                                SUNAT
                                            </span>
                                        @elseif ($mov->origen === 'constancia_pdf')
                                            <span class="rounded bg-rose-100 border border-rose-200 px-1 py-0.2 text-[9px] font-bold text-rose-800" title="Cargado desde constancia oficial PDF">
                                                SPOT PDF
                                            </span>
                                        @endif
                                    </div>
                                    @if ($mov->tipo_bien)
                                        <span class="block text-[10px] text-slate-400 truncate max-w-xs" title="{{ $mov->tipo_bien }}">
                                            {{ $mov->tipo_bien }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 font-sans text-slate-600 text-[11px]">
                                    @if ($mov->adquiriente_razon_social)
                                        <span class="font-bold text-slate-800 block truncate max-w-[180px]" title="{{ $mov->adquiriente_razon_social }}">
                                            {{ $mov->adquiriente_razon_social }}
                                        </span>
                                    @endif
                                    @if ($mov->adquiriente_ruc)
                                        <span class="font-mono text-[10px] text-slate-400">RUC: {{ $mov->adquiriente_ruc }}</span>
                                    @endif
                                    @if (!$mov->adquiriente_razon_social && !$mov->adquiriente_ruc)
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-bold text-emerald-700 tabular-nums">
                                    @if ($mov->isDeposito())
                                        S/ {{ number_format((float) $mov->monto, 2) }}
                                    @else
                                        <span class="text-slate-300 font-normal">—</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-bold text-rose-700 tabular-nums">
                                    @if ($mov->isRetiro())
                                        S/ {{ number_format((float) $mov->monto, 2) }}
                                    @else
                                        <span class="text-slate-300 font-normal">—</span>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right font-black {{ ($mov->saldo_acumulado ?? 0) >= 0 ? 'text-slate-900 bg-slate-50/40' : 'text-rose-700 bg-rose-50/50' }} tabular-nums">
                                    S/ {{ number_format((float) ($mov->saldo_acumulado ?? 0), 2) }}
                                </td>
                                <td class="px-3.5 py-2.5 text-center font-sans">
                                    @if ($mov->origen === 'control_impuestos')
                                        <a href="{{ route('clientes.operaciones', ['cliente' => $cliente, 'anio' => $mov->fecha->year]) }}"
                                           class="text-[10px] font-semibold text-teal-700 hover:underline"
                                           title="Ver abono en Control de Impuestos">
                                            Ver Pago
                                        </a>
                                    @else
                                        <button type="button"
                                                @click="eliminarMovimiento({{ $mov->id }}, '{{ addslashes($mov->descripcion) }}')"
                                                class="opacity-0 group-hover:opacity-100 rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                                                title="Eliminar este movimiento">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
                {{-- Pie de tabla con Totales --}}
                <tfoot class="border-t-2 border-slate-300 bg-slate-100 font-mono text-xs font-black text-slate-900">
                    <tr>
                        <td colspan="6" class="px-3.5 py-3 text-right font-sans uppercase text-[10px] tracking-wider text-slate-500">
                            TOTALES DEL EXTRACTO:
                        </td>
                        <td class="px-3.5 py-3 text-right text-emerald-800 tabular-nums">
                            S/ {{ number_format($totalDepositos, 2) }}
                        </td>
                        <td class="px-3.5 py-3 text-right text-rose-800 tabular-nums">
                            S/ {{ number_format($totalRetiros, 2) }}
                        </td>
                        <td class="px-3.5 py-3 text-right text-slate-900 tabular-nums bg-slate-200/60">
                            S/ {{ number_format($saldoFinal, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- ═══════════════════════════ MODAL: CARGAR DETRACCIÓN OFICIAL (PDF) ═══════════════════════════ --}}
    <div x-show="modalCargaPdf.abierto"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6 flex min-h-full items-center justify-center">
        
        <div class="relative w-full max-w-2xl max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl border border-slate-100 overflow-hidden my-auto"
             @click.away="if (!modalCargaPdf.procesando) modalCargaPdf.abierto = false">

            {{-- Encabezado Modal (Fijo arriba) --}}
            <div class="shrink-0 flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 border border-rose-200 shrink-0">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Cargar Constancia de Detracción (PDF)</h3>
                        <p class="text-xs text-slate-500">Lectura instantánea de constancias oficiales SPOT SUNAT / Banco de la Nación sin almacenar PDFs.</p>
                    </div>
                </div>
                <button type="button" @click="modalCargaPdf.abierto = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">&times;</button>
            </div>

            {{-- Cuerpo con Scroll Fluido --}}
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                {{-- Zona Drag & Drop / Selección de Archivo --}}
                <div x-show="!modalCargaPdf.previsualizando" class="space-y-4">
                    <div class="border-2 border-dashed border-slate-300 hover:border-rose-500 rounded-2xl p-8 text-center transition cursor-pointer bg-slate-50/50 hover:bg-rose-50/20"
                         @dragover.prevent="arrastrando = true"
                         @dragleave.prevent="arrastrando = false"
                         @drop.prevent="arrastrando = false; manejarArchivos($event.dataTransfer.files)"
                         @click="$refs.inputPdf.click()">
                        
                        <input type="file"
                               x-ref="inputPdf"
                               accept=".pdf,application/pdf"
                               multiple
                               @change="manejarArchivos($event.target.files)"
                               class="hidden">

                        <div class="flex flex-col items-center justify-center">
                            <div class="h-12 w-12 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-2xl mb-3 shadow-inner">
                                📄
                            </div>
                            <p class="text-sm font-bold text-slate-800">
                                Arrastra tu constancia en PDF aquí o <span class="text-rose-600 underline">haz clic para examinar</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Formatos soportados: Constancias oficiales SPOT emitidas por SUNAT / Banco de la Nación (PDF hasta 10 MB).
                            </p>
                        </div>
                    </div>

                    {{-- Estado de procesamiento / spinner --}}
                    <div x-show="modalCargaPdf.procesando" class="flex items-center justify-center gap-3 p-4 bg-slate-50 rounded-xl text-xs font-semibold text-slate-700">
                        <svg class="animate-spin h-5 w-5 text-rose-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Analizando y extrayendo campos fiscales en memoria...</span>
                    </div>
                </div>

                {{-- Previsualización y Confirmación de Datos Extraídos --}}
                <div x-show="modalCargaPdf.previsualizando" class="space-y-4">
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs text-emerald-800 font-semibold">
                        <span class="flex items-center gap-1.5">
                            <span>✓</span>
                            <span x-text="modalCargaPdf.mensajeExito"></span>
                        </span>
                        <button type="button" @click="reiniciarCargaPdf()" class="text-slate-500 hover:text-slate-800 underline text-[11px] cursor-pointer">
                            Subir otro PDF
                        </button>
                    </div>

                    {{-- Resumen inteligente si ninguna constancia pertenece a este periodo --}}
                    <template x-if="totalConstanciasValidas() === 0 && modalCargaPdf.itemsExtraidos.length > 0">
                        <div class="p-3 bg-rose-50 border border-rose-300 rounded-xl text-xs text-rose-950 flex items-start gap-2.5 shadow-xs">
                            <span class="text-xl leading-none">🛑</span>
                            <div>
                                <strong class="block font-black text-rose-900">Periodo No Coincidente para Registro Directo</strong>
                                <p class="text-[11px] text-rose-800 mt-0.5">
                                    Ninguna de las constancias cargadas corresponde al mes o año en el que estás ubicado actualmente. Para garantizar una conciliación bancaria limpia y sin descuadres contables en SUNAT, utiliza el botón <strong class="text-rose-900">"👉 Ir a [Periodo]"</strong> para trasladarte a su mes oficial y registrarlo allí.
                                </p>
                            </div>
                        </div>
                    </template>

                    {{-- Tarjetas de Constancias (Scroll fluido continuo sin topes fijos) --}}
                    <div class="space-y-3">
                        <template x-for="(item, idx) in modalCargaPdf.itemsExtraidos" :key="idx">
                            <div class="rounded-xl border p-4 shadow-xs space-y-3 transition"
                                 :class="{
                                     'border-rose-300 bg-rose-50/30': !item.coincide_periodo || !item.ruc_coincide || item.es_fecha_futura,
                                     'border-amber-300 bg-amber-50/20': item.coincide_periodo && item.ruc_coincide && item.ya_registrado && !item.ignorar_duplicado,
                                     'border-slate-200 bg-white': item.coincide_periodo && item.ruc_coincide && (!item.ya_registrado || item.ignorar_duplicado) && !item.es_fecha_futura
                                 }">
                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-slate-800" x-text="item.nombre_archivo"></span>
                                        <template x-if="item.datos.comprobante_formateado">
                                            <span class="rounded bg-blue-100 text-blue-800 px-1.5 py-0.2 font-mono text-[10px] font-bold" x-text="item.datos.comprobante_formateado"></span>
                                        </template>
                                        <template x-if="item.coincide_periodo && item.ruc_coincide && !item.ya_registrado && !item.es_fecha_futura">
                                            <span class="rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 px-2 py-0.2 text-[10px] font-bold flex items-center gap-1">
                                                <span>✓</span> Periodo Correcto
                                            </span>
                                        </template>
                                    </div>
                                    <span class="font-mono text-sm font-black text-emerald-700" x-text="item.datos.monto_formateado"></span>
                                </div>

                                {{-- ALERTA 1: DISCREPANCIA DE PERIODO (MES O AÑO) --}}
                                <template x-if="!item.coincide_periodo">
                                    <div class="p-3 bg-rose-50 border-2 border-rose-300 rounded-xl space-y-2">
                                        <div class="flex items-start gap-2 text-rose-900 font-bold text-xs">
                                            <span class="text-base leading-none">⛔</span>
                                            <div class="flex-1">
                                                <p class="font-black text-rose-950">Discrepancia de Periodo Contable</p>
                                                <p class="text-[11px] font-medium text-rose-800 mt-0.5" x-text="item.motivo_incompatibilidad"></p>
                                            </div>
                                        </div>
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-2 border-t border-rose-200">
                                            <span class="text-[11px] font-semibold text-rose-800">
                                                Para mantener la integridad contable, cámbiate a ese periodo:
                                            </span>
                                            <a :href="item.url_ir_periodo"
                                               class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-rose-700 transition shrink-0 cursor-pointer">
                                                <span>👉 Ir a <span x-text="item.periodo_texto"></span></span>
                                            </a>
                                        </div>
                                    </div>
                                </template>

                                {{-- ALERTA 2: TITULAR / RUC NO COINCIDE --}}
                                <template x-if="!item.ruc_coincide">
                                    <div class="p-3 bg-red-100 border border-red-300 rounded-xl text-xs text-red-950 flex items-start gap-2">
                                        <span class="text-base leading-none">🚨</span>
                                        <div>
                                            <strong class="block font-black text-red-900">Titular Incorrecto (RUC no coincide)</strong>
                                            <p class="text-[11px] text-red-800 mt-0.5">
                                                La constancia pertenece al RUC proveedor <strong class="font-mono font-bold" x-text="item.datos.proveedor_ruc"></strong>. Este expediente pertenece al cliente <strong>{{ $cliente->razon_social }}</strong> (RUC {{ $cliente->ruc }}). Por seguridad fiscal no se permite registrar constancias de terceros.
                                            </p>
                                        </div>
                                    </div>
                                </template>

                                {{-- ALERTA 3: FECHA FUTURA --}}
                                <template x-if="item.es_fecha_futura">
                                    <div class="p-2.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-900 flex items-center gap-2">
                                        <span>⚠️</span>
                                        <span class="text-[11px]">La fecha de pago en la constancia es posterior a la fecha actual. No se permiten registros futuros.</span>
                                    </div>
                                </template>

                                {{-- ALERTA 4: CONSTANCIA YA REGISTRADA ANTERIORMENTE --}}
                                <template x-if="item.ya_registrado">
                                    <div class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-xs text-amber-950 space-y-1.5">
                                        <div class="flex items-center gap-2 font-bold text-amber-900">
                                            <span>⚠️</span>
                                            <span>Constancia ya registrada anteriormente en este extracto</span>
                                        </div>
                                        <p class="text-[11px] text-amber-800">
                                            El N° de constancia <strong x-text="item.datos.numero_constancia"></strong> o la operación bancaria ya figura en los movimientos.
                                        </p>
                                        <div class="pt-1 flex items-center gap-2">
                                            <input type="checkbox" :id="'chk_dupl_' + idx" x-model="item.ignorar_duplicado" class="rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                                            <label :for="'chk_dupl_' + idx" class="text-[11px] font-semibold text-amber-900 cursor-pointer">
                                                Confirmar que no es duplicado y permitir forzar el registro
                                            </label>
                                        </div>
                                    </div>
                                </template>

                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Fecha de Pago</label>
                                        <input type="date" x-model="item.datos.fecha_pago" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 font-mono text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Monto Depósito (S/)</label>
                                        <input type="number" step="0.01" x-model="item.datos.monto" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 font-mono text-xs font-bold text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Descripción / Glosa en Extracto</label>
                                        <input type="text" x-model="item.datos.glosa_sugerida" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase">N° Constancia SPOT</label>
                                        <input type="text" x-model="item.datos.numero_constancia" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 font-mono text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase">N° Operación BN</label>
                                        <input type="text" x-model="item.datos.numero_operacion" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 font-mono text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase">RUC Adquiriente / Cliente</label>
                                        <input type="text" x-model="item.datos.adquiriente_ruc" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 font-mono text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Razón Social Adquiriente</label>
                                        <input type="text" x-model="item.datos.adquiriente_razon_social" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                                    </div>
                                </div>

                                {{-- Casilla para actualizar cuenta BN si no estaba en la ficha --}}
                                <template x-if="item.datos.cuenta_detracciones && (!cuentaActualBn || cuentaActualBn.trim() === '')">
                                    <div class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-2">
                                        <input type="checkbox" :id="'chk_cta_' + idx" x-model="item.actualizar_cuenta" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                        <label :for="'chk_cta_' + idx" class="text-[11px] text-slate-600 cursor-pointer">
                                            Actualizar N° de cuenta en la ficha del cliente con: <strong class="font-mono text-slate-800" x-text="item.datos.cuenta_detracciones"></strong>
                                        </label>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Botonera de Confirmación (Fija al pie del modal) --}}
            <div x-show="modalCargaPdf.previsualizando" class="shrink-0 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 px-6 py-3.5 border-t border-slate-100 bg-slate-50/90 backdrop-blur-xs">
                <div>
                    <template x-if="modalCargaPdf.itemsExtraidos.length > 0 && totalConstanciasValidas() === 0">
                        <span class="text-[11px] font-bold text-rose-700 flex items-center gap-1">
                            <span>⛔</span>
                            <span>Sin constancias válidas para este periodo. Usa "Ir a [Periodo]".</span>
                        </span>
                    </template>
                    <template x-if="totalConstanciasValidas() > 0">
                        <span class="text-[11px] font-semibold text-emerald-800 flex items-center gap-1">
                            <span>✓</span>
                            <span x-text="totalConstanciasValidas() + ' constancia(s) lista(s) para conciliar'"></span>
                        </span>
                    </template>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button"
                            @click="modalCargaPdf.abierto = false"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-white transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="button"
                            @click="confirmarRegistroLotePdf()"
                            :disabled="modalCargaPdf.guardando || totalConstanciasValidas() === 0"
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-2 text-xs font-bold text-white shadow-md shadow-emerald-600/20 hover:from-emerald-700 hover:to-teal-800 transition active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                        <template x-if="modalCargaPdf.guardando">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <span x-text="totalConstanciasValidas() === 0 ? 'Sin constancias para este periodo' : ('Confirmar y Registrar (' + totalConstanciasValidas() + ') en Extracto')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════ MODAL: REGISTRO MANUAL DE MOVIMIENTO ═══════════════════════════ --}}
    <div x-show="modalMovimiento.abierto"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6 flex min-h-full items-center justify-center">
        
        <div class="relative w-full max-w-lg max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl border border-slate-100 overflow-hidden my-auto"
             @click.away="modalMovimiento.abierto = false">

            {{-- Encabezado Modal (Fijo arriba) --}}
            <div class="shrink-0 flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl shrink-0"
                         :class="modalMovimiento.tipo === 'deposito' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200'">
                        <span x-text="modalMovimiento.tipo === 'deposito' ? '📥' : '📤'"></span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900" x-text="modalMovimiento.tipo === 'deposito' ? 'Registrar Depósito Manual' : 'Registrar Retiro Manual'"></h3>
                        <p class="text-xs text-slate-500" x-text="modalMovimiento.tipo === 'deposito' ? 'Ingreso a la cuenta de detracciones BN' : 'Cargo o comisión bancaria BN'"></p>
                    </div>
                </div>
                <button type="button" @click="modalMovimiento.abierto = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">&times;</button>
            </div>

            {{-- Formulario con scroll en el cuerpo y botones fijos abajo --}}
            <form @submit.prevent="guardarMovimientoManual()" class="flex flex-col flex-1 min-h-0">
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Fecha de Operación *</label>
                            <input type="date"
                                   x-model="modalMovimiento.fecha"
                                   required
                                   :class="{'border-rose-500 ring-1 ring-rose-500': errorPeriodoMovimiento.hayError || errorFechaFuturaMovimiento}"
                                   class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Monto (S/) *</label>
                            <input type="number" step="0.01" min="0.01" x-model="modalMovimiento.monto" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono font-bold text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="0.00">
                        </div>
                    </div>

                    {{-- ALERTA EN VIVO: PERIODO MISMATCH EN MOVIMIENTO MANUAL --}}
                    <template x-if="errorPeriodoMovimiento.hayError">
                        <div class="p-3 bg-rose-50 border border-rose-300 rounded-xl text-xs text-rose-900 space-y-2">
                            <div class="flex items-start gap-1.5 font-bold">
                                <span class="text-sm">⛔</span>
                                <span x-text="errorPeriodoMovimiento.mensaje"></span>
                            </div>
                            <template x-if="errorPeriodoMovimiento.urlDestino">
                                <div class="pt-1.5 flex items-center justify-between border-t border-rose-200">
                                    <span class="text-[11px] text-rose-800 font-medium">¿Cambiar al periodo correspondiente?</span>
                                    <a :href="errorPeriodoMovimiento.urlDestino"
                                       class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-rose-700 transition">
                                        <span>👉 Ir a <span x-text="errorPeriodoMovimiento.periodoTexto"></span></span>
                                    </a>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- ALERTA EN VIVO: FECHA FUTURA --}}
                    <template x-if="errorFechaFuturaMovimiento">
                        <div class="p-2.5 bg-rose-50 border border-rose-300 rounded-xl text-xs text-rose-800 flex items-center gap-1.5">
                            <span>⚠️</span>
                            <span>La fecha de operación no puede ser posterior a la fecha actual.</span>
                        </div>
                    </template>

                    {{-- ALERTA EN VIVO: RETIRO QUE EXCEDE SALDO DISPONIBLE --}}
                    <template x-if="modalMovimiento.tipo === 'retiro' && Number(modalMovimiento.monto || 0) > {{ $saldoFinal }}">
                        <div class="p-3 bg-amber-50 border border-amber-300 rounded-xl text-xs text-amber-900 flex items-start gap-2">
                            <span class="text-base leading-none">⚠️</span>
                            <div>
                                <strong class="font-bold">Advertencia de Saldo en Cuenta SPOT:</strong>
                                <p class="text-[11px] text-amber-800 mt-0.5">
                                    El monto del retiro (S/ <span x-text="Number(modalMovimiento.monto || 0).toFixed(2)"></span>) supera el saldo disponible actual (S/ {{ number_format($saldoFinal, 2) }}). Las cuentas de detracciones bancarias BN rechazan cargos sin fondos suficientes.
                                </p>
                            </div>
                        </div>
                    </template>

                    <div>
                        <label class="block text-xs font-bold text-slate-700">Descripción / Concepto *</label>
                        <input type="text" x-model="modalMovimiento.descripcion" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" :placeholder="modalMovimiento.tipo === 'deposito' ? 'Ej. Factura de Venta E001-34' : 'Ej. Comisión mantenimiento BN o Retiro'">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">N° Operación BN</label>
                            <input type="text" x-model="modalMovimiento.numero_operacion" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700">N° Constancia SPOT</label>
                            <input type="text" x-model="modalMovimiento.numero_constancia" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Comprobante de Referencia</label>
                            <input type="text" x-model="modalMovimiento.comprobante_referencia" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="Ej. E001-34">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700">RUC Adquiriente</label>
                            <input type="text" maxlength="11" x-model="modalMovimiento.adquiriente_ruc" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-mono text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="11 dígitos">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700">Razón Social Adquiriente</label>
                        <input type="text" x-model="modalMovimiento.adquiriente_razon_social" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="Nombre de la empresa cliente">
                    </div>
                </div>

                <div class="shrink-0 flex items-center justify-end gap-2 px-6 py-3.5 border-t border-slate-100 bg-slate-50/90">
                    <button type="button" @click="modalMovimiento.abierto = false" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-white transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit"
                            :disabled="modalMovimiento.guardando || errorPeriodoMovimiento.hayError || errorFechaFuturaMovimiento || !modalMovimiento.monto || Number(modalMovimiento.monto) <= 0 || !modalMovimiento.descripcion"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-slate-800 transition active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                        <template x-if="modalMovimiento.guardando">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <span>Guardar Movimiento</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════ MODAL: CONFIGURAR SALDO INICIAL Y APERTURA DE AÑO ═══════════════════════════ --}}
    <div x-show="modalSaldoInicial.abierto"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs p-3 sm:p-6 flex min-h-full items-center justify-center">
        
        <div class="relative w-full max-w-md max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl border border-slate-100 overflow-hidden my-auto"
             @click.away="modalSaldoInicial.abierto = false">

            <div class="shrink-0 flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 border border-blue-200 shrink-0">
                        <span>📅</span>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Apertura y Saldo de Ejercicio</h3>
                        <p class="text-xs text-slate-500">Gestión contable de saldos BN SPOT para el ejercicio <strong x-text="modalSaldoInicial.anio"></strong></p>
                    </div>
                </div>
                <button type="button" @click="modalSaldoInicial.abierto = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">&times;</button>
            </div>

            <form @submit.prevent="guardarSaldoInicial()" class="flex-1 overflow-y-auto flex flex-col">
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3.5">
                    {{-- Selector / Entrada de Año Fiscal --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700">Ejercicio Fiscal (Año) *</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="number"
                                   min="2020"
                                   max="2035"
                                   step="1"
                                   x-model.number="modalSaldoInicial.anio"
                                   @change="consultarSaldoSugerido(modalSaldoInicial.anio)"
                                   required
                                   class="w-32 rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono font-bold text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                            <span class="text-xs text-slate-500">
                                (Ej. 2026, 2027)
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">
                            El sistema arrastra saldos automáticamente, pero aquí puedes configurar o forzar cualquier año fiscal.
                        </p>
                    </div>

                    {{-- Tarjeta de Saldo Sugerido / Arrastrado del Año Anterior --}}
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl space-y-1.5 text-xs text-blue-900">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[11px] uppercase tracking-wider text-blue-800">Saldo de Cierre del Año Anterior:</span>
                            <template x-if="modalSaldoInicial.cargandoSugerido">
                                <span class="text-[11px] text-blue-600 animate-pulse font-medium">Calculando...</span>
                            </template>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Calculado al 31/12/<span x-text="modalSaldoInicial.anio - 1"></span>:</span>
                            <strong class="font-mono text-sm font-black text-blue-950" x-text="'S/ ' + Number(modalSaldoInicial.saldoSugerido || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></strong>
                        </div>
                        <div class="pt-1 flex items-center justify-between border-t border-blue-200/60">
                            <span class="text-[11px] text-slate-500">Arrastre contable acumulado</span>
                            <button type="button"
                                    @click="modalSaldoInicial.monto = modalSaldoInicial.saldoSugerido"
                                    class="text-[11px] font-bold text-blue-700 hover:text-blue-900 hover:underline cursor-pointer">
                                Copiar saldo sugerido
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700">Saldo Inicial al 01 de Enero (S/) *</label>
                        <input type="number" step="0.01" min="0" x-model="modalSaldoInicial.monto" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono font-bold text-slate-900 focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                        <p class="mt-1 text-[11px] text-slate-400">
                            Saldo con el que arranca la cuenta bancaria de detracciones en este ejercicio.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700">Observación / Glosa de Apertura</label>
                        <input type="text" x-model="modalSaldoInicial.observacion" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="Ej. Saldo de balance según extracto BN al 31/12">
                    </div>
                </div>

                <div class="shrink-0 flex items-center justify-end gap-2 px-6 py-3.5 border-t border-slate-100 bg-slate-50/90">
                    <button type="button" @click="modalSaldoInicial.abierto = false" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-white transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit"
                            :disabled="modalSaldoInicial.guardando"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2 text-xs font-bold text-white shadow-md hover:bg-slate-800 transition active:scale-95 disabled:opacity-50 cursor-pointer">
                        <template x-if="modalSaldoInicial.guardando">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <span>Guardar Ejercicio y Saldo</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════ LÓGICA ALPINE.JS DEL EXTRACTO BANCARIO ═══════════════════════════ --}}
<script>
function extractoDetraccionesApp(config) {
    return {
        clienteId: config.clienteId,
        clienteRuc: config.clienteRuc,
        clienteRazon: config.clienteRazon,
        cuentaActualBn: config.cuentaActualBn,
        anio: config.anio,
        mes: config.mes,
        urls: config.urls,

        arrastrando: false,

        modalCargaPdf: {
            abierto: false,
            procesando: false,
            previsualizando: false,
            guardando: false,
            mensajeExito: '',
            itemsExtraidos: [],
        },

        modalMovimiento: {
            abierto: false,
            guardando: false,
            tipo: 'deposito',
            fecha: new Date().toISOString().split('T')[0],
            monto: '',
            descripcion: '',
            numero_operacion: '',
            numero_constancia: '',
            comprobante_referencia: '',
            adquiriente_ruc: '',
            adquiriente_razon_social: '',
        },

        modalSaldoInicial: {
            abierto: false,
            guardando: false,
            cargandoSugerido: false,
            anio: config.anio,
            saldoSugerido: 0,
            monto: {{ $saldoInicial }},
            observacion: 'Saldo de apertura configurado por estudio contable',
        },

        get errorPeriodoMovimiento() {
            if (!this.modalMovimiento.fecha) {
                return { hayError: false };
            }
            const partes = this.modalMovimiento.fecha.split('-');
            if (partes.length !== 3) {
                return { hayError: false };
            }
            const fAnio = parseInt(partes[0], 10);
            const fMes = parseInt(partes[1], 10);

            const nombresMeses = {
                1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
                5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
                9: 'Setiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
            };
            const nombreF = nombresMeses[fMes] || ('Mes ' + fMes);

            if (this.mes !== null) {
                if (fAnio !== this.anio || fMes !== this.mes) {
                    const nombreAct = nombresMeses[this.mes] || ('Mes ' + this.mes);
                    return {
                        hayError: true,
                        mensaje: `La fecha (${partes[2]}/${partes[1]}/${fAnio}) pertenece al periodo ${nombreF} ${fAnio}, pero estás ubicado en ${nombreAct} ${this.anio}. Para mantener la integridad contable, el movimiento debe pertenecer a su periodo respectivo.`,
                        periodoTexto: `${nombreF} ${fAnio}`,
                        urlDestino: `/clientes/${this.clienteId}/cuenta-detracciones?anio=${fAnio}&detraccion_mes=${fMes}`
                    };
                }
            } else {
                if (fAnio !== this.anio) {
                    return {
                        hayError: true,
                        mensaje: `La fecha seleccionada corresponde al año ${fAnio}, pero estás en el ejercicio ${this.anio}.`,
                        periodoTexto: `Año ${fAnio}`,
                        urlDestino: `/clientes/${this.clienteId}/cuenta-detracciones?anio=${fAnio}`
                    };
                }
            }
            return { hayError: false };
        },

        get errorFechaFuturaMovimiento() {
            if (!this.modalMovimiento.fecha) return false;
            const hoyStr = new Date().toISOString().split('T')[0];
            return this.modalMovimiento.fecha > hoyStr;
        },

        totalConstanciasValidas() {
            return (this.modalCargaPdf.itemsExtraidos || []).filter(item => {
                return item.coincide_periodo && item.ruc_coincide && (!item.ya_registrado || item.ignorar_duplicado) && !item.es_fecha_futura;
            }).length;
        },

        abrirCargaPdf() {
            this.modalCargaPdf.abierto = true;
            this.modalCargaPdf.procesando = false;
            this.modalCargaPdf.previsualizando = false;
            this.modalCargaPdf.itemsExtraidos = [];
            this.modalCargaPdf.mensajeExito = '';
        },

        reiniciarCargaPdf() {
            this.modalCargaPdf.previsualizando = false;
            this.modalCargaPdf.itemsExtraidos = [];
            this.modalCargaPdf.mensajeExito = '';
        },

        async manejarArchivos(archivos) {
            if (!archivos || archivos.length === 0) return;

            const formData = new FormData();
            for (let i = 0; i < archivos.length; i++) {
                formData.append('archivos[]', archivos[i]);
            }
            formData.append('anio_contexto', this.anio);
            if (this.mes !== null) {
                formData.append('mes_contexto', this.mes);
            }

            this.modalCargaPdf.procesando = true;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await fetch(this.urls.cargarPdf, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Error al procesar el archivo PDF.');
                }

                const items = [];
                (data.resultados || []).forEach(r => {
                    if (r.exito && r.resultado) {
                        const res = r.resultado;
                        const per = res.periodo_info || {};
                        items.push({
                            nombre_archivo: r.nombre_archivo,
                            datos: res.datos,
                            ya_registrado: Boolean(res.ya_registrado),
                            ruc_coincide: typeof res.ruc_coincide !== 'undefined' ? Boolean(res.ruc_coincide) : true,
                            coincide_periodo: typeof per.coincide_periodo !== 'undefined' ? Boolean(per.coincide_periodo) : true,
                            motivo_incompatibilidad: per.motivo_incompatibilidad || '',
                            url_ir_periodo: per.url_ir_periodo || '',
                            periodo_texto: per.periodo_texto || '',
                            es_fecha_futura: Boolean(per.es_fecha_futura),
                            actualizar_cuenta: Boolean(res.datos.cuenta_detracciones && (!this.cuentaActualBn || this.cuentaActualBn.trim() === '')),
                            ignorar_duplicado: false,
                        });
                    }
                });

                if (items.length === 0) {
                    throw new Error('No se pudo extraer ninguna constancia válida.');
                }

                this.modalCargaPdf.itemsExtraidos = items;
                this.modalCargaPdf.mensajeExito = `Se leyó correctamente ${items.length} constancia(s) oficial(es) SPOT.`;
                this.modalCargaPdf.previsualizando = true;
            } catch (err) {
                alert('⚠️ ' + err.message);
            } finally {
                this.modalCargaPdf.procesando = false;
            }
        },

        async confirmarRegistroLotePdf() {
            const itemsValidos = this.modalCargaPdf.itemsExtraidos.filter(item => {
                return item.coincide_periodo && item.ruc_coincide && (!item.ya_registrado || item.ignorar_duplicado) && !item.es_fecha_futura;
            });

            if (itemsValidos.length === 0) {
                alert('⛔ No hay constancias válidas para el periodo actual. Utiliza el botón "Ir a [Periodo]" para abrir el mes correspondiente de la constancia y registrarla allí.');
                return;
            }

            this.modalCargaPdf.guardando = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                for (const item of itemsValidos) {
                    const payload = {
                        tipo: 'deposito',
                        fecha: item.datos.fecha_pago,
                        monto: parseFloat(item.datos.monto),
                        descripcion: item.datos.glosa_sugerida || 'Factura de Venta SPOT',
                        numero_operacion: item.datos.numero_operacion,
                        numero_constancia: item.datos.numero_constancia,
                        comprobante_referencia: item.datos.comprobante_formateado || item.datos.comprobante_numero,
                        adquiriente_ruc: item.datos.adquiriente_ruc,
                        adquiriente_razon_social: item.datos.adquiriente_razon_social,
                        proveedor_ruc: item.datos.proveedor_ruc,
                        tipo_bien: item.datos.tipo_bien,
                        origen: 'constancia_pdf',
                        actualizar_cuenta_cliente: item.actualizar_cuenta ? 1 : 0,
                        cuenta_detracciones: item.datos.cuenta_detracciones,
                        anio_contexto: this.anio,
                        mes_contexto: this.mes,
                        ignorar_duplicado: item.ignorar_duplicado ? 1 : 0,
                    };

                    const resp = await fetch(this.urls.guardarMovimiento, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const resJson = await resp.json();
                    if (!resp.ok) {
                        throw new Error(resJson.message || 'Error al guardar el depósito.');
                    }
                }

                window.location.reload();
            } catch (err) {
                alert('⚠️ Error al registrar los depósitos: ' + err.message);
                this.modalCargaPdf.guardando = false;
            }
        },

        abrirModalMovimiento(tipo) {
            this.modalMovimiento.tipo = tipo;

            // Selección inteligente de fecha dentro del periodo filtrado
            const hoy = new Date();
            const hoyAnio = hoy.getFullYear();
            const hoyMes = hoy.getMonth() + 1;
            const hoyDia = String(hoy.getDate()).padStart(2, '0');

            if (this.mes !== null) {
                if (hoyAnio === this.anio && hoyMes === this.mes) {
                    this.modalMovimiento.fecha = `${this.anio}-${String(this.mes).padStart(2, '0')}-${hoyDia}`;
                } else {
                    this.modalMovimiento.fecha = `${this.anio}-${String(this.mes).padStart(2, '0')}-01`;
                }
            } else {
                if (hoyAnio === this.anio) {
                    this.modalMovimiento.fecha = hoy.toISOString().split('T')[0];
                } else {
                    this.modalMovimiento.fecha = `${this.anio}-01-01`;
                }
            }

            this.modalMovimiento.monto = '';
            this.modalMovimiento.descripcion = '';
            this.modalMovimiento.numero_operacion = '';
            this.modalMovimiento.numero_constancia = '';
            this.modalMovimiento.comprobante_referencia = '';
            this.modalMovimiento.adquiriente_ruc = '';
            this.modalMovimiento.adquiriente_razon_social = '';
            this.modalMovimiento.guardando = false;
            this.modalMovimiento.abierto = true;
        },

        async guardarMovimientoManual() {
            if (this.errorPeriodoMovimiento.hayError) {
                alert('⛔ ' + this.errorPeriodoMovimiento.mensaje);
                return;
            }
            if (this.errorFechaFuturaMovimiento) {
                alert('⚠️ La fecha de operación no puede ser posterior a la fecha actual.');
                return;
            }

            this.modalMovimiento.guardando = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                const payload = {
                    tipo: this.modalMovimiento.tipo,
                    fecha: this.modalMovimiento.fecha,
                    monto: parseFloat(this.modalMovimiento.monto),
                    descripcion: this.modalMovimiento.descripcion,
                    numero_operacion: this.modalMovimiento.numero_operacion || null,
                    numero_constancia: this.modalMovimiento.numero_constancia || null,
                    comprobante_referencia: this.modalMovimiento.comprobante_referencia || null,
                    adquiriente_ruc: this.modalMovimiento.adquiriente_ruc || null,
                    adquiriente_razon_social: this.modalMovimiento.adquiriente_razon_social || null,
                    origen: 'manual',
                    anio_contexto: this.anio,
                    mes_contexto: this.mes,
                };

                const resp = await fetch(this.urls.guardarMovimiento, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await resp.json();
                if (!resp.ok) {
                    if (data.periodo_mismatch && data.url_ir_periodo) {
                        if (confirm(data.message + '\n\n¿Deseas ir ahora a ' + (data.periodo_detectado || 'ese periodo') + ' para registrarlo allí?')) {
                            window.location.href = data.url_ir_periodo;
                            return;
                        }
                    }
                    throw new Error(data.message || 'Error al registrar el movimiento.');
                }

                window.location.reload();
            } catch (err) {
                alert('⚠️ ' + err.message);
                this.modalMovimiento.guardando = false;
            }
        },

        abrirModalSaldoInicial(anioTarget) {
            const anioSel = anioTarget || this.anio;
            this.modalSaldoInicial.anio = anioSel;
            this.modalSaldoInicial.guardando = false;
            this.modalSaldoInicial.monto = (anioSel === this.anio) ? {{ $saldoInicial }} : 0;
            this.modalSaldoInicial.abierto = true;
            this.consultarSaldoSugerido(anioSel);
        },

        abrirModalAperturaAnio(anioSugerido) {
            this.abrirModalSaldoInicial(anioSugerido || (this.anio + 1));
        },

        async consultarSaldoSugerido(anioTarget) {
            if (!anioTarget) return;
            this.modalSaldoInicial.cargandoSugerido = true;
            try {
                const resp = await fetch(`${this.urls.saldoSugerido}?anio=${anioTarget}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                if (data && typeof data.saldo_sugerido !== 'undefined') {
                    this.modalSaldoInicial.saldoSugerido = parseFloat(data.saldo_sugerido);
                    if (!this.modalSaldoInicial.monto || this.modalSaldoInicial.monto == 0 || this.modalSaldoInicial.anio !== this.anio) {
                        this.modalSaldoInicial.monto = this.modalSaldoInicial.saldoSugerido;
                    }
                }
            } catch (err) {
                console.error('Error al consultar saldo sugerido:', err);
            } finally {
                this.modalSaldoInicial.cargandoSugerido = false;
            }
        },

        async guardarSaldoInicial() {
            this.modalSaldoInicial.guardando = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                const payload = {
                    anio: this.modalSaldoInicial.anio,
                    saldo_inicial: parseFloat(this.modalSaldoInicial.monto || 0),
                    observacion: this.modalSaldoInicial.observacion,
                };

                const resp = await fetch(this.urls.saldoInicial, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await resp.json();
                if (!resp.ok) {
                    throw new Error(data.message || 'Error al guardar el saldo inicial.');
                }

                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    window.location.reload();
                }
            } catch (err) {
                alert('⚠️ ' + err.message);
                this.modalSaldoInicial.guardando = false;
            }
        },

        async eliminarMovimiento(id, descripcion) {
            if (!confirm(`¿Estás seguro de eliminar el movimiento "${descripcion}"?\n\nEsta acción recalculará los saldos de la cuenta de detracciones.`)) {
                return;
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const resp = await fetch(`/clientes/${this.clienteId}/cuenta-detracciones/movimientos/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                });

                const data = await resp.json();
                if (!resp.ok) {
                    throw new Error(data.message || 'Error al eliminar el movimiento.');
                }

                window.location.reload();
            } catch (err) {
                alert('⚠️ ' + err.message);
            }
        },

        copiarTexto(texto, aviso) {
            if (!texto) return;
            navigator.clipboard.writeText(texto).then(() => {
                alert(aviso || 'Copiado al portapapeles');
            }).catch(() => {
                prompt('Copiar número:', texto);
            });
        }
    };
}
</script>
