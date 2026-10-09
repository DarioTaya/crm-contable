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
    $tonoEstado = function (string $situacion): string {
        return match ($situacion) {
            'pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_sin_fecha' => 'bg-emerald-100 text-emerald-800',
            'pagado_fuera_de_fecha', 'debe_parcial' => 'bg-amber-100 text-amber-900',
            'debe_vencido', 'debe_parcial_vencido', 'debe_sin_vencimiento' => 'bg-rose-100 text-rose-800',
            'debe_en_plazo' => 'bg-sky-100 text-sky-900',
            'baja' => 'bg-gray-200 text-gray-600',
            default => 'bg-gray-100 text-gray-600',
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
    $tributoTieneDatos = function (array $tributo): bool {
        if ($tributo['tiene_importes'] ?? false) {
            return true;
        }

        foreach ($tributo['periodos'] as $fila) {
            if (($fila['cantidad_pagos'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    };
    $iniciales = [];
    $conMonto = [];
    foreach ($reporteImpuestos['bloques'] as $bloqueInicial) {
        $iniciales[$bloqueInicial['id']] = null;
        $conMonto[$bloqueInicial['id']] = [];
        foreach ($bloqueInicial['tributos'] as $tributoInicial) {
            if ($tributoTieneDatos($tributoInicial)) {
                $conMonto[$bloqueInicial['id']][] = $tributoInicial['codigo'];
            }
            if ($iniciales[$bloqueInicial['id']] !== null) {
                continue;
            }
            foreach ($tributoInicial['periodos'] as $filaInicial) {
                if ($filaInicial['registrado'] || $filaInicial['cantidad_pagos'] > 0) {
                    $iniciales[$bloqueInicial['id']] = $bloqueInicial['id'].'|'.$tributoInicial['codigo'].'|'.$filaInicial['periodo'];
                    break;
                }
            }
        }
    }
@endphp

<div class="flex flex-col gap-3" x-data='{
    seleccionado: @json($iniciales),
    pagosAbiertos: null,
    tributosCompletos: {},
    conMonto: @json($conMonto),
    alternarTributos(bloque) {
        this.tributosCompletos[bloque] = !this.tributosCompletos[bloque];
        if (this.tributosCompletos[bloque]) {
            return;
        }
        const clave = this.seleccionado[bloque];
        const codigo = clave ? clave.split("|")[1] : null;
        if (codigo && !(this.conMonto[bloque] || []).includes(codigo)) {
            this.seleccionado[bloque] = null;
            this.pagosAbiertos = null;
        }
    },
    tributoVisible(bloque, codigo) {
        return !!this.tributosCompletos[bloque] || (this.conMonto[bloque] || []).includes(codigo);
    }
}'>
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
                <p class="text-[11px] text-gray-500">Un cero es un mes sin importe en Control de Impuestos.</p>
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
            $tributosConDatos = 0;
            $tributosSinDatos = 0;
            foreach ($bloque['tributos'] as $tributoConteo) {
                if ($tributoTieneDatos($tributoConteo)) {
                    $tributosConDatos++;
                } else {
                    $tributosSinDatos++;
                }
            }
            $mallaOculta = $tributosConDatos === 0 && $bloque['tributos'] !== [];
        @endphp
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-3 border-b px-3 py-2 {{ $encabezado }}">
                <h4 class="text-sm font-bold">
                    {{ $bloque['titulo'] }}
                    <span class="font-medium opacity-70">· {{ $bloque['detalle'] }}</span>
                </h4>
                @if ($tributosSinDatos > 0)
                    <button type="button"
                            @click="alternarTributos('{{ $bloque['id'] }}')"
                            :aria-expanded="!!tributosCompletos['{{ $bloque['id'] }}']"
                            class="shrink-0 text-[10px] font-medium opacity-50 hover:opacity-100"
                            title="Tributos sin importe en Control de Impuestos">
                        <span x-show="!tributosCompletos['{{ $bloque['id'] }}']">{{ $tributosSinDatos }} sin monto</span>
                        <span x-show="tributosCompletos['{{ $bloque['id'] }}']" x-cloak>ocultar</span>
                    </button>
                @endif
            </div>

            @if ($bloque['tributos'] === [])
                <p class="px-3 py-2 text-sm text-gray-500">{{ $bloque['mensaje'] }}</p>
            @else
                <div @if ($mallaOculta) data-malla-oculta x-show="tributosCompletos['{{ $bloque['id'] }}']" x-cloak @endif>
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
                                <tr class="border-t border-gray-100" data-tributo="{{ $tributo['codigo'] }}" @unless ($tributoTieneDatos($tributo)) data-sin-monto x-cloak @endunless x-show="tributoVisible('{{ $bloque['id'] }}', '{{ $tributo['codigo'] }}')">
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
                                                    <span class="absolute right-0.5 top-0.5 h-1.5 w-1.5 rounded-full bg-indigo-500" title="IGV Justo"></span>
                                                @endif
                                                @if (!empty($fila['tasa']))
                                                    <span class="absolute left-0.5 top-0.5 text-[8px] font-mono font-bold leading-none px-0.5 py-0.2 rounded {{ $fila['tasa'] > 1.0 ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}" title="Tasa: {{ $fila['tasa'] }}%">{{ rtrim(rtrim(number_format($fila['tasa'], 2, '.', ''), '0'), '.') }}%</span>
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

                <div class="border-t border-gray-100 bg-slate-50/70 px-3 py-3">
                    <p x-show="seleccionado['{{ $bloque['id'] }}'] === null" class="text-[11px] text-gray-500">Elija un mes para ver el importe según PDT, el vencimiento y los pagos.</p>
                    @foreach ($bloque['tributos'] as $tributo)
                        @foreach ($tributo['periodos'] as $fila)
                            @php
                                $clave = $bloque['id'].'|'.$tributo['codigo'].'|'.$fila['periodo'];
                                $saldoPendiente = $fila['debe'] && $fila['saldo'];
                            @endphp
                            <div x-show="seleccionado['{{ $bloque['id'] }}'] === '{{ $clave }}' && tributoVisible('{{ $bloque['id'] }}', '{{ $tributo['codigo'] }}')" x-cloak class="w-full max-w-xl rounded-xl border border-gray-200 bg-white p-3 shadow-xs">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900">{{ $fila['etiqueta'] }} · {{ $tributo['nombre'] }}</p>
                                        <p class="mt-0.5 text-[11px] font-semibold text-gray-500">{{ $bloque['titulo'] }} · {{ $bloque['detalle'] }} · {{ $tributo['codigo'] }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $tonoEstado($fila['situacion']) }}">{{ $fila['situacion_etiqueta'] }}</span>
                                        @if ($fila['es_igv_justo'])
                                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase text-indigo-800">IGV Justo</span>
                                        @endif
                                        @if (!empty($fila['tasa']))
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $fila['tasa'] > 1.0 ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                                Tasa: {{ rtrim(rtrim(number_format($fila['tasa'], 2, '.', ''), '0'), '.') }}%
                                                @if ($tributo['codigo'] === '3121')
                                                    {{ $fila['tasa'] > 1.0 ? '(>300 UIT)' : '(≤300 UIT)' }}
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <dl class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">A pagar · según PDT</dt>
                                        <dd class="mt-0.5 font-mono text-sm font-bold tabular-nums text-slate-950">{{ $fila['determinado'] ?? '—' }}</dd>
                                    </div>
                                    <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                                        <dt class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Vence</dt>
                                        <dd class="mt-0.5 font-mono text-sm font-bold tabular-nums text-gray-900">{{ $fila['vencimiento'] ?? '—' }}</dd>
                                    </div>
                                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                                        <dt class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Pagado</dt>
                                        <dd class="mt-0.5 font-mono text-sm font-bold tabular-nums text-emerald-950">{{ $fila['pagado'] ?? '—' }}</dd>
                                    </div>
                                    <div class="rounded-lg border px-3 py-2 {{ $saldoPendiente ? 'border-rose-200 bg-rose-50' : 'border-gray-200 bg-white' }}">
                                        <dt class="text-[10px] font-bold uppercase tracking-wide {{ $saldoPendiente ? 'text-rose-700' : 'text-gray-500' }}">Saldo</dt>
                                        <dd class="mt-0.5 font-mono text-sm font-bold tabular-nums {{ $saldoPendiente ? 'text-rose-950' : 'text-gray-900' }}">{{ $fila['saldo'] ?? '—' }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-3 border-t border-gray-100 pt-2.5">
                                    @if ($fila['cantidad_pagos'] === 0)
                                        <p class="text-[11px] font-semibold text-gray-500">Sin pagos</p>
                                    @else
                                        <button type="button"
                                                @click="pagosAbiertos = pagosAbiertos === '{{ $clave }}' ? null : '{{ $clave }}'"
                                                :aria-expanded="pagosAbiertos === '{{ $clave }}'"
                                                class="flex w-full items-center justify-between gap-2 rounded-lg py-1 text-left hover:bg-gray-50">
                                            <span class="text-[11px] font-bold text-gray-800">
                                                {{ $fila['cantidad_pagos'] }} {{ $fila['cantidad_pagos'] === 1 ? 'pago' : 'pagos' }}
                                                @if ($fila['fecha_cancelacion'])
                                                    <span class="font-semibold text-gray-500">· canceló {{ $fila['fecha_cancelacion'] }}</span>
                                                @endif
                                            </span>
                                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition" :class="pagosAbiertos === '{{ $clave }}' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                        <div x-show="pagosAbiertos === '{{ $clave }}'" x-cloak class="mt-2 overflow-hidden rounded-lg border border-gray-200">
                                            <table class="w-full text-left text-[11px]">
                                                <thead class="bg-gray-50 text-[10px] font-bold uppercase tracking-wide text-gray-500">
                                                    <tr>
                                                        <th class="px-2.5 py-1.5">Fecha</th>
                                                        <th class="px-2.5 py-1.5 text-right">Importe</th>
                                                        <th class="px-2.5 py-1.5">Medio</th>
                                                        <th class="px-2.5 py-1.5">Nota</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    @foreach ($fila['pagos'] as $pago)
                                                        <tr>
                                                            <td class="px-2.5 py-1.5 font-mono font-semibold text-gray-800">{{ $pago['fecha'] }}</td>
                                                            <td class="px-2.5 py-1.5 text-right font-mono font-bold tabular-nums text-gray-900">{{ $pago['monto'] }}</td>
                                                            <td class="px-2.5 py-1.5 font-semibold text-gray-700">{{ $pago['medio'] }}</td>
                                                            <td class="px-2.5 py-1.5 text-gray-500">{{ $pago['observacion'] !== '' ? $pago['observacion'] : '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
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
