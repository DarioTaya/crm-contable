@php
    $kpis = $reporteCreditosFiscales['kpis'];
    $anio = $reporteCreditosFiscales['anio'];
    $anios = $reporteCreditosFiscales['anios'];
@endphp

<div class="flex flex-col gap-4" x-data="{
    subtab: 'detracciones', // 'detracciones', 'retenciones', 'percepciones', 'credito_fiscal'
}">
    {{-- Barra Superior de Navegación y Ejercicio --}}
    <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-xs">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-800 text-sm font-bold">💳</span>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Control de Detracciones, Retenciones y Percepciones</h3>
                        <p class="text-[11px] text-gray-500">Créditos tributarios, pagos anticipados del IGV y aplicación de fondos SPOT (Banco de la Nación).</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <span class="text-xs font-semibold text-gray-500">Ejercicio:</span>
                <div class="flex flex-wrap gap-1">
                    @foreach ($anios as $anioOpcion)
                        <a href="{{ route('clientes.creditos', ['cliente' => $cliente, 'anio' => $anioOpcion]) }}"
                           class="rounded-full px-2.5 py-0.5 text-xs font-bold transition {{ (int) $anioOpcion === (int) $anio ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $anioOpcion }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Métricas KPI Ejecutivas del Ejercicio --}}
        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-2 lg:grid-cols-4 pt-1">
            {{-- KPI 1: Detracciones SPOT --}}
            <div class="rounded-xl border border-teal-200 bg-teal-50/70 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-teal-800">Pagos con Detracciones</span>
                    <span class="text-xs font-mono font-bold text-teal-700 bg-teal-100 px-1.5 py-0.2 rounded-full">{{ $kpis['detracciones_conteo_pagos'] }} op.</span>
                </div>
                <p class="mt-1 font-mono text-lg font-black text-teal-950">S/ {{ number_format($kpis['detracciones_total_pagado'], 2) }}</p>
                <p class="mt-0.5 text-[10px] text-teal-700">Fondos aplicados desde cuenta BN</p>
            </div>

            {{-- KPI 2: Retenciones IGV --}}
            <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-sky-800">Retenciones IGV (3%)</span>
                    <span class="text-[10px] font-semibold text-sky-700">Declarado: S/ {{ number_format($kpis['retenciones_acumuladas'], 2) }}</span>
                </div>
                <p class="mt-1 font-mono text-lg font-black text-sky-950">S/ {{ number_format($kpis['retenciones_saldo_disponible'], 2) }}</p>
                <p class="mt-0.5 text-[10px] text-sky-700">Saldo a favor actual no aplicado</p>
            </div>

            {{-- KPI 3: Percepciones IGV --}}
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Percepciones IGV</span>
                    <span class="text-[10px] font-semibold text-amber-700">Declarado: S/ {{ number_format($kpis['percepciones_acumuladas'], 2) }}</span>
                </div>
                <p class="mt-1 font-mono text-lg font-black text-amber-950">S/ {{ number_format($kpis['percepciones_saldo_disponible'], 2) }}</p>
                <p class="mt-0.5 text-[10px] text-amber-700">Saldo a favor actual no aplicado</p>
            </div>

            {{-- KPI 4: Crédito Fiscal IGV & ITAN --}}
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-800">Crédito Fiscal & ITAN</span>
                    <span class="text-[10px] font-semibold text-indigo-700">Arrastre vigente</span>
                </div>
                <p class="mt-1 font-mono text-lg font-black text-indigo-950">S/ {{ number_format($kpis['credito_fiscal_vigente'], 2) }}</p>
                <p class="mt-0.5 text-[10px] text-indigo-700">Crédito IGV (ITAN: S/ {{ number_format($kpis['itan_saldo_disponible'], 2) }})</p>
            </div>
        </div>

        {{-- Sub-navegación por Tipo de Mecanismo Tributario --}}
        <div class="flex border-b border-gray-200 text-xs font-semibold gap-2 pt-2">
            <button type="button"
                    @click="subtab = 'detracciones'"
                    :class="subtab === 'detracciones' ? 'border-b-2 border-teal-600 text-teal-700 font-bold' : 'text-gray-500 hover:text-gray-800'"
                    class="pb-2 px-1 transition flex items-center gap-1.5">
                <span>🏦 Detracciones SPOT (Banco de la Nación)</span>
                @if ($kpis['detracciones_conteo_pagos'] > 0)
                    <span class="rounded-full bg-teal-100 px-1.5 py-0.2 text-[10px] font-bold text-teal-800">{{ $kpis['detracciones_conteo_pagos'] }}</span>
                @endif
            </button>
            <button type="button"
                    @click="subtab = 'retenciones'"
                    :class="subtab === 'retenciones' ? 'border-b-2 border-sky-600 text-sky-700 font-bold' : 'text-gray-500 hover:text-gray-800'"
                    class="pb-2 px-1 transition flex items-center gap-1.5">
                <span>📑 Retenciones IGV (3%)</span>
                @if ($kpis['retenciones_saldo_disponible'] > 0)
                    <span class="rounded-full bg-sky-100 px-1.5 py-0.2 text-[10px] font-bold text-sky-800">S/ {{ number_format($kpis['retenciones_saldo_disponible'], 0) }}</span>
                @endif
            </button>
            <button type="button"
                    @click="subtab = 'percepciones'"
                    :class="subtab === 'percepciones' ? 'border-b-2 border-amber-600 text-amber-700 font-bold' : 'text-gray-500 hover:text-gray-800'"
                    class="pb-2 px-1 transition flex items-center gap-1.5">
                <span>📦 Percepciones IGV</span>
                @if ($kpis['percepciones_saldo_disponible'] > 0)
                    <span class="rounded-full bg-amber-100 px-1.5 py-0.2 text-[10px] font-bold text-amber-800">S/ {{ number_format($kpis['percepciones_saldo_disponible'], 0) }}</span>
                @endif
            </button>
            <button type="button"
                    @click="subtab = 'credito_fiscal'"
                    :class="subtab === 'credito_fiscal' ? 'border-b-2 border-indigo-600 text-indigo-700 font-bold' : 'text-gray-500 hover:text-gray-800'"
                    class="pb-2 px-1 transition flex items-center gap-1.5">
                <span>⚖️ Crédito Fiscal IGV & ITAN</span>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- SUB-TAB 1: DETRACCIONES SPOT (BANCO DE LA NACIÓN)               --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="subtab === 'detracciones'" class="space-y-4">
        {{-- Matriz Mensual de Pagos con Detracciones --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 bg-teal-50/50 px-4 py-2.5 flex items-center justify-between">
                <div>
                    <h4 class="text-xs font-bold text-teal-950 uppercase tracking-wider">Resumen Mensual de Pagos con Cuenta de Detracciones ({{ $anio }})</h4>
                    <p class="text-[11px] text-teal-800/80">Tributos cancelados a SUNAT con cargo a la cuenta corriente del Banco de la Nación (SPOT D. Leg. 940).</p>
                </div>
                <span class="font-mono text-xs font-bold text-teal-900 bg-teal-100 px-2 py-0.5 rounded-lg border border-teal-200">
                    Total: S/ {{ number_format($kpis['detracciones_total_pagado'], 2) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-collapse text-center">
                    <thead>
                        <tr class="border-b border-gray-200 text-[10px] font-bold uppercase tracking-wide text-gray-500 bg-gray-50">
                            <th class="sticky left-0 z-10 w-28 bg-gray-50 px-2 py-1.5 text-left">Concepto</th>
                            @foreach ($reporteCreditosFiscales['detracciones'] as $mes)
                                <th class="px-0.5 py-1.5 font-mono" title="{{ $mes['mes'] }}">{{ $mes['etiqueta'] }}</th>
                            @endforeach
                            <th class="w-24 px-2 py-1.5 text-right">Total Año</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t border-gray-100">
                            <th class="sticky left-0 z-10 bg-white px-2 py-2 text-left align-middle font-bold text-xs text-gray-800">
                                <span>Monto Debitador BN</span>
                                <span class="block text-[10px] font-mono font-normal text-gray-400">SPOT Cta Cte</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['detracciones'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-2 align-middle {{ $mes['monto'] > 0 ? 'bg-teal-50/80 font-bold text-teal-950' : 'bg-white text-gray-400' }}">
                                    @if ($mes['monto'] > 0)
                                        <span class="block font-mono text-[11px] tabular-nums">S/ {{ number_format($mes['monto'], 0) }}</span>
                                        <span class="block text-[9px] text-teal-700 font-semibold">{{ $mes['cantidad_pagos'] }} op.</span>
                                    @else
                                        <span class="font-mono text-[11px] text-gray-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-gray-50 px-2 py-2 text-right align-middle font-mono font-bold text-xs text-gray-900">
                                S/ {{ number_format($kpis['detracciones_total_pagado'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Detalle de Operaciones Individuales de Detracción --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 px-4 py-2.5 flex items-center justify-between bg-slate-50">
                <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Historial de Constancias y Abonos con Fondos de Detracción</h4>
                <span class="text-[11px] text-slate-500 font-medium">Registrados en Control de Impuestos</span>
            </div>

            @if (empty($reporteCreditosFiscales['pagos_detracciones']))
                <div class="p-6 text-center text-xs text-gray-500">
                    <p class="font-medium">No se registran pagos con cargo en cuenta de detracciones en el ejercicio {{ $anio }}.</p>
                    <p class="mt-1 text-[11px] text-gray-400">Al registrar abonos con medio "Detracciones" o importar constancias con cargo en cuenta BN, se listarán automáticamente aquí.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-[10px] font-bold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="px-3 py-2">Fecha Pago</th>
                                <th class="px-3 py-2">Periodo</th>
                                <th class="px-3 py-2">Tributo Cancelado</th>
                                <th class="px-3 py-2 text-right">Importe Debitador</th>
                                <th class="px-3 py-2">Constancia / Observación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-mono">
                            @foreach ($reporteCreditosFiscales['pagos_detracciones'] as $pago)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-3 py-2 font-bold text-gray-800">{{ $pago['fecha'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 font-bold">{{ $pago['periodo'] }}</td>
                                    <td class="px-3 py-2 font-sans font-bold text-teal-800">{{ $pago['tributo'] }}</td>
                                    <td class="px-3 py-2 text-right font-bold text-teal-950">S/ {{ number_format($pago['monto'], 2) }}</td>
                                    <td class="px-3 py-2 font-sans text-gray-600 text-[11px] truncate max-w-xs" title="{{ $pago['observacion'] }}">{{ $pago['observacion'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-200 bg-gray-50 font-mono font-bold text-xs text-gray-900">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right font-sans uppercase text-[10px] tracking-wider text-gray-500">Total Financiamiento SPOT:</td>
                                <td class="px-3 py-2 text-right text-teal-950">S/ {{ number_format($kpis['detracciones_total_pagado'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- SUB-TAB 2: RETENCIONES DEL IGV (3%)                             --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="subtab === 'retenciones'" class="space-y-4">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 bg-sky-50/50 px-4 py-2.5 flex items-center justify-between">
                <div>
                    <h4 class="text-xs font-bold text-sky-950 uppercase tracking-wider">Evolución y Arrastre de Retenciones del IGV ({{ $anio }})</h4>
                    <p class="text-[11px] text-sky-800/80">Régimen de Retenciones de SUNAT (Res. 037-2002/SUNAT - Casillas 168, 179, 171 y 169 del PDT 621).</p>
                </div>
                <span class="font-mono text-xs font-bold text-sky-900 bg-sky-100 px-2 py-0.5 rounded-lg border border-sky-200">
                    Saldo Disponible: S/ {{ number_format($kpis['retenciones_saldo_disponible'], 2) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-collapse text-center">
                    <thead>
                        <tr class="border-b border-gray-200 text-[10px] font-bold uppercase tracking-wide text-gray-500 bg-gray-50">
                            <th class="sticky left-0 z-10 w-36 bg-gray-50 px-2 py-1.5 text-left">Concepto</th>
                            @foreach ($reporteCreditosFiscales['retenciones'] as $mes)
                                <th class="px-0.5 py-1.5 font-mono" title="{{ $mes['mes'] }}">{{ $mes['etiqueta'] }}</th>
                            @endforeach
                            <th class="w-24 px-2 py-1.5 text-right">Total Año</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        {{-- Fila 1: Saldo Anterior --}}
                        <tr class="hover:bg-slate-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-gray-700">
                                <span>(+) Saldo Anterior</span>
                                <span class="block text-[9px] font-mono text-gray-400">Casilla 168</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['retenciones'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px]">
                                    {{ $mes['saldo_anterior'] > 0 ? 'S/ '.number_format($mes['saldo_anterior'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-gray-50 font-mono text-[11px] text-gray-400">—</td>
                        </tr>

                        {{-- Fila 2: Retenciones del Mes --}}
                        <tr class="bg-sky-50/30 hover:bg-sky-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-sky-900">
                                <span>(+) Retenciones del Mes</span>
                                <span class="block text-[9px] font-mono text-sky-600">Casilla 179</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['retenciones'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px] font-bold {{ $mes['declarado'] > 0 ? 'text-sky-950 bg-sky-100/50' : 'text-gray-400' }}">
                                    {{ $mes['declarado'] > 0 ? 'S/ '.number_format($mes['declarado'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-sky-50 font-mono font-bold text-sky-950 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['retenciones_acumuladas'], 2) }}
                            </td>
                        </tr>

                        {{-- Fila 3: Retenciones Aplicadas contra IGV --}}
                        <tr class="hover:bg-slate-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-emerald-800">
                                <span>(-) Aplicadas a IGV</span>
                                <span class="block text-[9px] font-mono text-emerald-600">Casilla 171</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['retenciones'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px] {{ $mes['aplicado'] > 0 ? 'text-emerald-900 font-bold' : 'text-gray-400' }}">
                                    {{ $mes['aplicado'] > 0 ? 'S/ '.number_format($mes['aplicado'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-emerald-50/60 font-mono font-bold text-emerald-950 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['retenciones_aplicadas'], 2) }}
                            </td>
                        </tr>

                        {{-- Fila 4: Saldo a Favor Trasladable --}}
                        <tr class="bg-gray-50/80 font-bold">
                            <th class="sticky left-0 z-10 bg-gray-50 px-2 py-1.5 text-left align-middle text-[11px] text-gray-900">
                                <span>(=) Saldo Siguiente</span>
                                <span class="block text-[9px] font-mono text-gray-500">Casilla 169</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['retenciones'] as $mes)
                                <td class="border-l border-gray-200 px-1 py-1.5 font-mono text-[11px] {{ $mes['saldo_siguiente'] > 0 ? 'text-sky-950 font-black' : 'text-gray-400' }}">
                                    {{ $mes['saldo_siguiente'] > 0 ? 'S/ '.number_format($mes['saldo_siguiente'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-sky-100 font-mono font-black text-sky-950 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['retenciones_saldo_disponible'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- SUB-TAB 3: PERCEPCIONES DEL IGV                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="subtab === 'percepciones'" class="space-y-4">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 bg-amber-50/50 px-4 py-2.5 flex items-center justify-between">
                <div>
                    <h4 class="text-xs font-bold text-amber-950 uppercase tracking-wider">Evolución y Arrastre de Percepciones del IGV ({{ $anio }})</h4>
                    <p class="text-[11px] text-amber-800/80">Régimen de Percepciones de SUNAT (Ley 29169 - Casillas 171, 173, 172 y 175 del PDT 621).</p>
                </div>
                <span class="font-mono text-xs font-bold text-amber-900 bg-amber-100 px-2 py-0.5 rounded-lg border border-amber-200">
                    Saldo Disponible: S/ {{ number_format($kpis['percepciones_saldo_disponible'], 2) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-collapse text-center">
                    <thead>
                        <tr class="border-b border-gray-200 text-[10px] font-bold uppercase tracking-wide text-gray-500 bg-gray-50">
                            <th class="sticky left-0 z-10 w-36 bg-gray-50 px-2 py-1.5 text-left">Concepto</th>
                            @foreach ($reporteCreditosFiscales['percepciones'] as $mes)
                                <th class="px-0.5 py-1.5 font-mono" title="{{ $mes['mes'] }}">{{ $mes['etiqueta'] }}</th>
                            @endforeach
                            <th class="w-24 px-2 py-1.5 text-right">Total Año</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        {{-- Fila 1: Saldo Anterior --}}
                        <tr class="hover:bg-slate-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-gray-700">
                                <span>(+) Saldo Anterior</span>
                                <span class="block text-[9px] font-mono text-gray-400">Casilla 171</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['percepciones'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px]">
                                    {{ $mes['saldo_anterior'] > 0 ? 'S/ '.number_format($mes['saldo_anterior'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-gray-50 font-mono text-[11px] text-gray-400">—</td>
                        </tr>

                        {{-- Fila 2: Percepciones del Mes --}}
                        <tr class="bg-amber-50/30 hover:bg-amber-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-amber-900">
                                <span>(+) Percepciones Mes</span>
                                <span class="block text-[9px] font-mono text-amber-600">Casilla 173</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['percepciones'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px] font-bold {{ $mes['declarado'] > 0 ? 'text-amber-950 bg-amber-100/50' : 'text-gray-400' }}">
                                    {{ $mes['declarado'] > 0 ? 'S/ '.number_format($mes['declarado'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-amber-50 font-mono font-bold text-amber-950 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['percepciones_acumuladas'], 2) }}
                            </td>
                        </tr>

                        {{-- Fila 3: Percepciones Aplicadas contra IGV --}}
                        <tr class="hover:bg-slate-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-emerald-800">
                                <span>(-) Aplicadas a IGV</span>
                                <span class="block text-[9px] font-mono text-emerald-600">Casilla 172</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['percepciones'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px] {{ $mes['aplicado'] > 0 ? 'text-emerald-900 font-bold' : 'text-gray-400' }}">
                                    {{ $mes['aplicado'] > 0 ? 'S/ '.number_format($mes['aplicado'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-emerald-50/60 font-mono font-bold text-emerald-950 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['percepciones_aplicadas'], 2) }}
                            </td>
                        </tr>

                        {{-- Fila 4: Saldo a Favor Trasladable --}}
                        <tr class="bg-gray-50/80 font-bold">
                            <th class="sticky left-0 z-10 bg-gray-50 px-2 py-1.5 text-left align-middle text-[11px] text-gray-900">
                                <span>(=) Saldo Siguiente</span>
                                <span class="block text-[9px] font-mono text-gray-500">Casilla 175</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['percepciones'] as $mes)
                                <td class="border-l border-gray-200 px-1 py-1.5 font-mono text-[11px] {{ $mes['saldo_siguiente'] > 0 ? 'text-amber-950 font-black' : 'text-gray-400' }}">
                                    {{ $mes['saldo_siguiente'] > 0 ? 'S/ '.number_format($mes['saldo_siguiente'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-amber-100 font-mono font-black text-amber-950 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['percepciones_saldo_disponible'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- SUB-TAB 4: CRÉDITO FISCAL IGV & COMPENSACIÓN ITAN               --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div x-show="subtab === 'credito_fiscal'" class="space-y-4">
        {{-- Crédito Fiscal IGV --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 bg-indigo-50/50 px-4 py-2.5 flex items-center justify-between">
                <div>
                    <h4 class="text-xs font-bold text-indigo-950 uppercase tracking-wider">Crédito Fiscal del IGV y Arrastre de Saldo a Favor ({{ $anio }})</h4>
                    <p class="text-[11px] text-indigo-800/80">Determinación mensual del Impuesto General a las Ventas (Casillas 101, 108, 140, 145 y 184).</p>
                </div>
                <span class="font-mono text-xs font-bold text-indigo-900 bg-indigo-100 px-2 py-0.5 rounded-lg border border-indigo-200">
                    Crédito Vigente: S/ {{ number_format($kpis['credito_fiscal_vigente'], 2) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-collapse text-center">
                    <thead>
                        <tr class="border-b border-gray-200 text-[10px] font-bold uppercase tracking-wide text-gray-500 bg-gray-50">
                            <th class="sticky left-0 z-10 w-36 bg-gray-50 px-2 py-1.5 text-left">Concepto</th>
                            @foreach ($reporteCreditosFiscales['credito_fiscal'] as $mes)
                                <th class="px-0.5 py-1.5 font-mono" title="{{ $mes['mes'] }}">{{ $mes['etiqueta'] }}</th>
                            @endforeach
                            <th class="w-24 px-2 py-1.5 text-right">Saldo Actual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <tr class="hover:bg-slate-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-gray-700">
                                <span>(+) Débito Fiscal (Ventas)</span>
                                <span class="block text-[9px] font-mono text-gray-400">Casilla 101</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['credito_fiscal'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px]">
                                    {{ $mes['debito_fiscal'] > 0 ? 'S/ '.number_format($mes['debito_fiscal'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-gray-50 font-mono text-[11px] text-gray-400">—</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-emerald-800">
                                <span>(-) Crédito Fiscal (Compras)</span>
                                <span class="block text-[9px] font-mono text-emerald-600">Casilla 108</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['credito_fiscal'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px] font-bold {{ $mes['credito_fiscal'] > 0 ? 'text-emerald-950 bg-emerald-50/50' : 'text-gray-400' }}">
                                    {{ $mes['credito_fiscal'] > 0 ? 'S/ '.number_format($mes['credito_fiscal'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-gray-50 font-mono text-[11px] text-gray-400">—</td>
                        </tr>
                        <tr class="bg-gray-50/80 font-bold">
                            <th class="sticky left-0 z-10 bg-gray-50 px-2 py-1.5 text-left align-middle text-[11px] text-indigo-950">
                                <span>(=) Saldo a Favor Mes Sig.</span>
                                <span class="block text-[9px] font-mono text-indigo-700">Casilla 184</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['credito_fiscal'] as $mes)
                                <td class="border-l border-gray-200 px-1 py-1.5 font-mono text-[11px] {{ $mes['saldo_favor_siguiente'] > 0 ? 'text-indigo-950 font-black bg-indigo-50/80' : 'text-gray-400' }}">
                                    {{ $mes['saldo_favor_siguiente'] > 0 ? 'S/ '.number_format($mes['saldo_favor_siguiente'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-indigo-100 font-mono font-black text-indigo-950 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['credito_fiscal_vigente'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Compensación de ITAN contra Renta --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="border-b border-gray-100 bg-slate-50 px-4 py-2 flex items-center justify-between">
                <div>
                    <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Compensación de ITAN (Impuesto Temporal a los Activos Netos - Ley 28424)</h4>
                    <p class="text-[11px] text-slate-500">Crédito aplicable contra los pagos a cuenta del Impuesto a la Renta del Régimen General y MYPE (Casilla 328).</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-800 bg-white px-2 py-0.5 rounded-lg border border-slate-200">
                    Remanente ITAN: S/ {{ number_format($kpis['itan_saldo_disponible'], 2) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-collapse text-center">
                    <thead>
                        <tr class="border-b border-gray-200 text-[10px] font-bold uppercase tracking-wide text-gray-500 bg-gray-50">
                            <th class="sticky left-0 z-10 w-36 bg-gray-50 px-2 py-1.5 text-left">Concepto</th>
                            @foreach ($reporteCreditosFiscales['itan'] as $mes)
                                <th class="px-0.5 py-1.5 font-mono" title="{{ $mes['mes'] }}">{{ $mes['etiqueta'] }}</th>
                            @endforeach
                            <th class="w-24 px-2 py-1.5 text-right">Saldo Remanente</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <tr class="hover:bg-slate-50/50">
                            <th class="sticky left-0 z-10 bg-white px-2 py-1.5 text-left align-middle font-bold text-[11px] text-gray-700">
                                <span>Compensado en Mes</span>
                                <span class="block text-[9px] font-mono text-gray-400">Casilla 328</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['itan'] as $mes)
                                <td class="border-l border-gray-100 px-1 py-1.5 font-mono text-[11px]">
                                    {{ $mes['compensado'] > 0 ? 'S/ '.number_format($mes['compensado'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-gray-50 font-mono text-[11px] text-gray-400">—</td>
                        </tr>
                        <tr class="bg-gray-50/80 font-bold">
                            <th class="sticky left-0 z-10 bg-gray-50 px-2 py-1.5 text-left align-middle text-[11px] text-gray-900">
                                <span>Saldo ITAN Siguiente</span>
                                <span class="block text-[9px] font-mono text-gray-500">Arrastre ejercicio</span>
                            </th>
                            @foreach ($reporteCreditosFiscales['itan'] as $mes)
                                <td class="border-l border-gray-200 px-1 py-1.5 font-mono text-[11px] {{ $mes['saldo_siguiente'] > 0 ? 'text-slate-900 font-bold' : 'text-gray-400' }}">
                                    {{ $mes['saldo_siguiente'] > 0 ? 'S/ '.number_format($mes['saldo_siguiente'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="border-l border-gray-200 bg-gray-100 font-mono font-black text-slate-900 text-right px-2 py-1.5">
                                S/ {{ number_format($kpis['itan_saldo_disponible'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
