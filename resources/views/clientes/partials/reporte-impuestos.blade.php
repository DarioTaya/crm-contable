@php
    $tonoCelda = function (string $situacion): string {
        return match ($situacion) {
            'pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_sin_fecha' => 'bg-emerald-50 text-emerald-950',
            'pagado_fuera_de_fecha', 'debe_parcial' => 'bg-amber-50 text-amber-950',
            'debe_vencido', 'debe_parcial_vencido', 'debe_sin_vencimiento' => 'bg-rose-50 text-rose-950',
            'debe_en_plazo' => 'bg-sky-50 text-sky-950',
            'baja' => 'bg-gray-100 text-gray-500',
            default => 'bg-white text-gray-400',
        };
    };
    $lineaCelda = function (array $fila): string {
        if ($fila['situacion'] === 'baja') {
            return 'Baja';
        }
        if (! $fila['registrado'] && $fila['cantidad_pagos'] === 0) {
            return '';
        }
        if ($fila['debe'] && $fila['saldo']) {
            return 'Debe '.str_replace('S/ ', '', (string) $fila['saldo']);
        }
        if ($fila['cantidad_pagos'] === 1) {
            return substr((string) $fila['pagos'][0]['fecha'], 0, 5);
        }
        if ($fila['cantidad_pagos'] > 1) {
            return $fila['cantidad_pagos'].' pagos';
        }

        return match ($fila['situacion']) {
            'pagado_a_tiempo' => 'A tiempo',
            'pagado_fuera_de_fecha' => 'Fuera',
            'pagado_sin_vencimiento', 'pagado_sin_fecha' => 'Pagado',
            'debe_en_plazo' => 'En plazo',
            'debe_vencido' => 'Vencido',
            'debe_parcial', 'debe_parcial_vencido' => 'Parcial',
            'debe_sin_vencimiento' => 'Debe',
            default => '',
        };
    };
    $resumen = $reporteImpuestos['resumen'];
    $hayDeuda = ($resumen['con_deuda'] ?? 0) > 0;
    $hayAtraso = ($resumen['fuera_de_fecha'] ?? 0) > 0;
    $iniciales = [];
    foreach ($reporteImpuestos['bloques'] as $bloqueInicial) {
        $iniciales[$bloqueInicial['id']] = null;
        foreach ($bloqueInicial['tributos'] as $tributoInicial) {
            foreach ($tributoInicial['periodos'] as $filaInicial) {
                if ($filaInicial['registrado'] || $filaInicial['cantidad_pagos'] > 0) {
                    $iniciales[$bloqueInicial['id']] = $bloqueInicial['id'].'|'.$tributoInicial['codigo'].'|'.$filaInicial['periodo'];
                    break 2;
                }
            }
        }
    }
@endphp

<div class="flex flex-col gap-3" x-data='{ seleccionado: @json($iniciales) }'>
    <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-xs">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-sm font-bold text-gray-900">Impuestos del ejercicio {{ $reporteImpuestos['anio'] }}</h3>
                <div class="flex flex-wrap gap-1">
                    @foreach ($reporteImpuestos['anios'] as $anioOpcion)
                        <a href="{{ route('clientes.operaciones', ['cliente' => $cliente, 'anio' => $anioOpcion]) }}"
                           class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ (int) $anioOpcion === (int) $reporteImpuestos['anio'] ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $anioOpcion }}
                        </a>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-500">Un mes en blanco no es saldo cero.</p>
            </div>
            <a href="{{ route('control.index', ['anio' => $reporteImpuestos['anio']]) }}"
               class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-xs hover:bg-gray-50">
                Registrar en Control de Impuestos
            </a>
        </div>

        <div class="grid grid-cols-2 gap-2 lg:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-semibold text-slate-600">PDT 621</p>
                <p class="font-mono text-base font-bold tabular-nums text-slate-950">{{ $resumen['determinado_621'] }}</p>
            </div>
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                <p class="text-[11px] font-semibold text-indigo-700">PDT 601 · PLAME</p>
                <p class="font-mono text-base font-bold tabular-nums text-indigo-950">{{ $resumen['determinado_601'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                <p class="text-[11px] font-semibold text-emerald-700">Pagado</p>
                <p class="font-mono text-base font-bold tabular-nums text-emerald-950">{{ $resumen['pagado'] }}</p>
            </div>
            <div class="rounded-lg border px-3 py-2 {{ $hayDeuda ? 'border-rose-200 bg-rose-50' : 'border-gray-200 bg-gray-50' }}">
                <p class="text-[11px] font-semibold {{ $hayDeuda ? 'text-rose-700' : 'text-gray-500' }}">Por pagar</p>
                <p class="font-mono text-base font-bold tabular-nums {{ $hayDeuda ? 'text-rose-950' : 'text-gray-900' }}">{{ $resumen['saldo'] }}</p>
            </div>
            <div class="col-span-2 rounded-lg border px-3 py-2 lg:col-span-1 {{ $hayAtraso ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50' }}">
                <p class="text-[11px] font-semibold {{ $hayAtraso ? 'text-amber-800' : 'text-gray-500' }}">Fuera de fecha</p>
                <p class="font-mono text-base font-bold tabular-nums {{ $hayAtraso ? 'text-amber-950' : 'text-gray-900' }}">{{ $resumen['fuera_de_fecha'] }} <span class="font-sans text-[11px] font-semibold">{{ $resumen['fuera_de_fecha'] === 1 ? 'periodo' : 'periodos' }}</span></p>
            </div>
        </div>
    </div>

    @foreach ($reporteImpuestos['bloques'] as $bloque)
        @php
            $esPlame = $bloque['id'] === 'pdt_601';
            $encabezado = $esPlame ? 'border-indigo-100 bg-indigo-50 text-indigo-950' : 'border-slate-200 bg-slate-50 text-slate-900';
            $meses = $bloque['tributos'][0]['periodos'] ?? [];
        @endphp
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-3 border-b px-3 py-2 {{ $encabezado }}">
                <h4 class="text-sm font-bold">
                    {{ $bloque['titulo'] }}
                    <span class="font-medium opacity-70">· {{ $bloque['detalle'] }}</span>
                </h4>
            </div>

            @if ($bloque['tributos'] === [])
                <p class="px-3 py-2 text-sm text-gray-500">{{ $bloque['mensaje'] }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full table-fixed border-collapse text-center">
                        <thead>
                            <tr class="border-b border-gray-200 text-[10px] font-bold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-20 w-36 bg-white px-2 py-1.5 text-left">Tributo</th>
                                @foreach ($meses as $mes)
                                    <th class="px-0.5 py-1.5 font-mono" title="{{ $mes['mes'] }}">{{ $mes['etiqueta'] }}</th>
                                @endforeach
                                <th class="w-24 px-1 py-1.5 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bloque['tributos'] as $tributo)
                                <tr class="border-t border-gray-100">
                                    <th class="sticky left-0 z-10 bg-white px-2 py-1 text-left align-middle" title="{{ $tributo['codigo'] }} {{ $tributo['descripcion'] }}">
                                        <span class="block text-xs font-bold leading-tight text-gray-900">{{ $tributo['nombre'] }}</span>
                                        <span class="block font-mono text-[10px] font-medium text-gray-400">{{ $tributo['codigo'] }}</span>
                                    </th>
                                    @foreach ($tributo['periodos'] as $fila)
                                        @php $clave = $bloque['id'].'|'.$tributo['codigo'].'|'.$fila['periodo']; @endphp
                                        <td class="border-l border-gray-100 p-0 align-middle {{ $tonoCelda($fila['situacion']) }}">
                                            <button type="button"
                                                    @click="seleccionado['{{ $bloque['id'] }}'] = '{{ $clave }}'"
                                                    :class="seleccionado['{{ $bloque['id'] }}'] === '{{ $clave }}' ? 'ring-2 ring-inset ring-gray-900' : ''"
                                                    class="relative flex h-full min-h-9 w-full flex-col items-center justify-center px-0.5 py-1 focus:outline-none"
                                                    title="{{ $fila['etiqueta'] }} {{ $tributo['nombre'] }} {{ $fila['situacion_etiqueta'] }}">
                                                @if ($fila['es_igv_justo'])
                                                    <span class="absolute right-0.5 top-0.5 h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                @endif
                                                <span class="block w-full truncate font-mono text-[11px] font-bold leading-none tabular-nums">
                                                    {{ $fila['determinado'] ? str_replace('S/ ', '', $fila['determinado']) : '—' }}
                                                </span>
                                                <span class="mt-0.5 block w-full truncate text-[10px] font-medium leading-none">
                                                    {{ $lineaCelda($fila) }}
                                                </span>
                                            </button>
                                        </td>
                                    @endforeach
                                    <td class="border-l border-gray-200 bg-gray-50 px-1.5 py-1 text-right align-middle">
                                        @if ($tributo['totales']['determinado'])
                                            <span class="block truncate font-mono text-[11px] font-bold tabular-nums text-gray-900">{{ str_replace('S/ ', '', $tributo['totales']['determinado']) }}</span>
                                            @if (($tributo['totales']['con_deuda'] ?? 0) > 0)
                                                <span class="mt-0.5 block truncate text-[10px] font-semibold leading-none text-rose-700">Debe {{ str_replace('S/ ', '', (string) $tributo['totales']['saldo']) }}</span>
                                            @endif
                                        @else
                                            <span class="font-mono text-[11px] text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="min-h-12 border-t border-gray-100 px-3 py-2">
                    <p x-show="seleccionado['{{ $bloque['id'] }}'] === null" class="text-[11px] text-gray-500">Elija un mes para ver vencimiento, situación y pagos.</p>
                    @foreach ($bloque['tributos'] as $tributo)
                        @foreach ($tributo['periodos'] as $fila)
                            @php $clave = $bloque['id'].'|'.$tributo['codigo'].'|'.$fila['periodo']; @endphp
                            <div x-show="seleccionado['{{ $bloque['id'] }}'] === '{{ $clave }}'" x-cloak class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5 text-[11px] leading-5 text-gray-700">
                                <p class="text-xs font-bold text-gray-900">{{ $fila['etiqueta'] }} · {{ $tributo['nombre'] }}</p>
                                <span class="text-gray-400">{{ $bloque['titulo'] }} · {{ $tributo['codigo'] }}</span>
                                <span>A pagar <strong class="font-mono">{{ $fila['determinado'] ?? '—' }}</strong></span>
                                <span>Vence <strong class="font-mono">{{ $fila['vencimiento'] ?? '—' }}</strong></span>
                                @if ($fila['es_igv_justo'])
                                    <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold uppercase text-indigo-700">IGV Justo</span>
                                @endif
                                <span class="font-bold text-gray-900">{{ $fila['situacion_etiqueta'] }}</span>
                                @if ($fila['saldo'])
                                    <span>Saldo <strong class="font-mono">{{ $fila['saldo'] }}</strong></span>
                                @endif
                                @if ($fila['cantidad_pagos'] === 0)
                                    <span class="text-gray-400">Sin pagos</span>
                                @else
                                    <span>
                                        <strong>{{ $fila['cantidad_pagos'] }} {{ $fila['cantidad_pagos'] === 1 ? 'pago' : 'pagos' }}</strong>
                                        @if ($fila['fecha_cancelacion'])
                                            · canceló {{ $fila['fecha_cancelacion'] }}
                                        @endif
                                        @foreach ($fila['pagos'] as $pago)
                                            · {{ $pago['fecha'] }} {{ $pago['monto'] }} {{ $pago['medio'] }}@if ($pago['observacion'] !== '') · {{ $pago['observacion'] }}@endif
                                        @endforeach
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <p class="flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-400">
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-200"></span> Pagado a tiempo</span>
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-amber-200"></span> Fuera de fecha o parcial</span>
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-rose-200"></span> Debe vencido</span>
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-sky-200"></span> En plazo</span>
        <span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span> IGV Justo</span>
    </p>
</div>
