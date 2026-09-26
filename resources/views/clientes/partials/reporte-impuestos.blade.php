@php
    $tonoSituacion = function (string $situacion): string {
        return match ($situacion) {
            'pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_sin_fecha' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            'pagado_fuera_de_fecha', 'debe_parcial' => 'bg-amber-50 text-amber-900 ring-amber-200',
            'debe_vencido', 'debe_parcial_vencido', 'debe_sin_vencimiento' => 'bg-rose-50 text-rose-800 ring-rose-200',
            'debe_en_plazo' => 'bg-sky-50 text-sky-900 ring-sky-200',
            'baja' => 'bg-gray-100 text-gray-500 ring-gray-200',
            default => 'bg-white text-gray-500 ring-gray-200',
        };
    };
    $bordeSituacion = function (string $situacion): string {
        return match ($situacion) {
            'pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_sin_fecha' => 'border-l-emerald-500',
            'pagado_fuera_de_fecha', 'debe_parcial' => 'border-l-amber-500',
            'debe_vencido', 'debe_parcial_vencido', 'debe_sin_vencimiento' => 'border-l-rose-500',
            'debe_en_plazo' => 'border-l-sky-500',
            'baja' => 'border-l-gray-300',
            default => 'border-l-transparent',
        };
    };
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-gray-200/90 bg-white p-5 shadow-xs">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h3 class="text-base font-bold text-gray-900">Impuestos del ejercicio {{ $reporteImpuestos['anio'] }}</h3>
                <p class="mt-1 max-w-3xl text-xs leading-relaxed text-gray-500">
                    Libro por tributo y por periodo para el balance. El importe, el vencimiento y cada pago salen de Control de Impuestos.
                    Un mes sin importe queda en blanco: no se asume que salió S/ 0.00. Control de Deudas sigue mostrando solo lo que aún está por pagar.
                </p>
            </div>
            <a href="{{ route('control.index', ['anio' => $reporteImpuestos['anio']]) }}"
               class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50">
                Registrar en Control de Impuestos
            </a>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @foreach ($reporteImpuestos['anios'] as $anioOpcion)
                <a href="{{ route('clientes.operaciones', ['cliente' => $cliente, 'anio' => $anioOpcion]) }}"
                   class="rounded-full px-3 py-1 text-xs font-bold transition {{ (int) $anioOpcion === (int) $reporteImpuestos['anio'] ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $anioOpcion }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200/90 bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">PDT 621</p>
            <p class="mt-1 font-mono text-xl font-black tracking-tight text-gray-900">{{ $reporteImpuestos['resumen']['determinado_621'] }}</p>
            <p class="mt-1 text-[11px] text-gray-500">IGV y renta determinados</p>
        </div>
        <div class="rounded-2xl border border-gray-200/90 bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">PLAME</p>
            <p class="mt-1 font-mono text-xl font-black tracking-tight text-gray-900">{{ $reporteImpuestos['resumen']['determinado_601'] }}</p>
            <p class="mt-1 text-[11px] text-gray-500">Planilla y retenciones</p>
        </div>
        <div class="rounded-2xl border border-emerald-200/90 bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Pagado</p>
            <p class="mt-1 font-mono text-xl font-black tracking-tight text-emerald-700">{{ $reporteImpuestos['resumen']['pagado'] }}</p>
            <p class="mt-1 text-[11px] text-gray-500">{{ $reporteImpuestos['resumen']['fuera_de_fecha'] }} {{ $reporteImpuestos['resumen']['fuera_de_fecha'] === 1 ? 'periodo fuera de fecha' : 'periodos fuera de fecha' }}</p>
        </div>
        <div class="rounded-2xl border {{ $reporteImpuestos['resumen']['con_deuda'] > 0 ? 'border-rose-200/90' : 'border-gray-200/90' }} bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider {{ $reporteImpuestos['resumen']['con_deuda'] > 0 ? 'text-rose-600' : 'text-gray-500' }}">Por pagar</p>
            <p class="mt-1 font-mono text-xl font-black tracking-tight {{ $reporteImpuestos['resumen']['con_deuda'] > 0 ? 'text-rose-700' : 'text-gray-900' }}">{{ $reporteImpuestos['resumen']['saldo'] }}</p>
            <p class="mt-1 text-[11px] text-gray-500">{{ $reporteImpuestos['resumen']['con_deuda'] }} {{ $reporteImpuestos['resumen']['con_deuda'] === 1 ? 'periodo con deuda' : 'periodos con deuda' }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach ($reporteImpuestos['bloques'] as $bloque)
            <a href="#reporte-{{ $bloque['id'] }}" class="rounded-full border border-gray-200 bg-white px-3 py-1 text-[11px] font-bold text-gray-700 hover:border-gray-300">
                {{ $bloque['titulo'] }} · {{ $bloque['detalle'] }}
            </a>
        @endforeach
    </div>

    @foreach ($reporteImpuestos['bloques'] as $bloque)
        <section id="reporte-{{ $bloque['id'] }}" class="space-y-4">
            <div class="flex items-baseline justify-between gap-3">
                <h4 class="text-sm font-black tracking-wide text-gray-900">
                    {{ $bloque['titulo'] }}
                    <span class="font-semibold text-gray-400">· {{ $bloque['detalle'] }}</span>
                </h4>
            </div>

            @if ($bloque['tributos'] === [])
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-5 py-8 text-center text-sm text-gray-500">
                    {{ $bloque['mensaje'] }}
                </div>
            @endif

            @foreach ($bloque['tributos'] as $tributo)
                <article class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-xs print:break-inside-avoid">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900">{{ $tributo['nombre'] }}</p>
                            <p class="truncate text-[11px] text-gray-500">{{ $tributo['codigo'] }} · {{ $tributo['descripcion'] }}</p>
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px]">
                            <span class="text-gray-500">A pagar <strong class="font-mono text-gray-900">{{ $tributo['totales']['determinado'] ?? '—' }}</strong></span>
                            <span class="text-gray-500">Pagado <strong class="font-mono text-emerald-700">{{ $tributo['totales']['pagado'] ?? '—' }}</strong></span>
                            <span class="text-gray-500">Saldo <strong class="font-mono {{ ($tributo['totales']['con_deuda'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-900' }}">{{ $tributo['totales']['saldo'] ?? '—' }}</strong></span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[920px] w-full text-left">
                            <thead class="bg-gray-50 text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="px-4 py-2.5">Periodo</th>
                                    <th class="px-3 py-2.5 text-right">A pagar</th>
                                    <th class="px-3 py-2.5">Vencimiento</th>
                                    <th class="px-3 py-2.5">Pagos</th>
                                    <th class="px-4 py-2.5">Situación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($tributo['periodos'] as $fila)
                                    <tr class="border-l-2 {{ $bordeSituacion($fila['situacion']) }} {{ $fila['registrado'] ? 'bg-white' : 'bg-gray-50/60' }}">
                                        <td class="whitespace-nowrap px-4 py-2.5">
                                            <span class="block text-xs font-bold text-gray-900">{{ $fila['etiqueta'] }}</span>
                                            <span class="block text-[10px] text-gray-400">{{ $fila['mes'] }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-xs font-semibold text-gray-900">
                                            {{ $fila['determinado'] ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5">
                                            <span class="font-mono text-xs text-gray-800">{{ $fila['vencimiento'] ?? '—' }}</span>
                                            @if ($fila['es_igv_justo'])
                                                <span class="mt-1 block w-fit rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700 ring-1 ring-indigo-200">IGV Justo</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5">
                                            @if ($fila['cantidad_pagos'] === 0)
                                                <span class="text-[11px] text-gray-400">Sin pagos</span>
                                            @else
                                                <p class="text-[11px] font-bold text-gray-800">
                                                    {{ $fila['cantidad_pagos'] }} {{ $fila['cantidad_pagos'] === 1 ? 'pago' : 'pagos' }}
                                                    @if ($fila['fecha_cancelacion'])
                                                        <span class="font-semibold text-gray-500">· canceló {{ $fila['fecha_cancelacion'] }}</span>
                                                    @endif
                                                </p>
                                                <ul class="mt-1 space-y-0.5">
                                                    @foreach ($fila['pagos'] as $pago)
                                                        <li class="text-[11px] leading-snug text-gray-600">
                                                            <span class="font-mono text-gray-800">{{ $pago['fecha'] }}</span>
                                                            · {{ $pago['monto'] }}
                                                            · <span class="font-semibold text-gray-800">{{ $pago['medio'] }}</span>
                                                            @if ($pago['observacion'] !== '')
                                                                <span class="text-gray-400">· {{ $pago['observacion'] }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 {{ $tonoSituacion($fila['situacion']) }}">
                                                {{ $fila['situacion_etiqueta'] }}
                                            </span>
                                            @if ($fila['registrado'] && $fila['saldo'])
                                                <span class="mt-1 block font-mono text-[10px] text-gray-500">Saldo {{ $fila['saldo'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @unless ($tributo['tiene_importes'])
                        <p class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-400">Sin importes registrados en este ejercicio. Los meses en blanco no equivalen a saldo cero.</p>
                    @endunless
                </article>
            @endforeach
        </section>
    @endforeach
</div>
