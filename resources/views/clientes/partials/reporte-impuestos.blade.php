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
            'pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_sin_fecha' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'pagado_fuera_de_fecha', 'debe_parcial' => 'bg-amber-100 text-amber-900 border-amber-200',
            'debe_vencido', 'debe_parcial_vencido', 'debe_sin_vencimiento' => 'bg-rose-100 text-rose-800 border-rose-200',
            'debe_en_plazo' => 'bg-sky-100 text-sky-900 border-sky-200',
            'baja' => 'bg-gray-200 text-gray-600 border-gray-300',
            default => 'bg-gray-100 text-gray-600 border-gray-200',
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
    $analisisRegimen = $reporteImpuestos['analisis_regimen'] ?? [];
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

<div class="flex flex-col gap-4" x-data='{
    seleccionado: @json($iniciales),
    pagosAbiertos: null,
    tributosCompletos: {},
    conMonto: @json($conMonto),
    vistaActual: "dual",
    modalDetalle: {
        abierto: false,
        bloqueId: null,
        periodo: null,
        mesNombre: null,
        mesEtiqueta: null,
        regimen: null,
        situacion: null,
        situacionEtiqueta: null,
        determinado: null,
        pagado: null,
        saldo: null,
        vencimiento: null,
        vencimientoOficial: null,
        esIgvJusto: false,
        cantidadPagos: 0,
        fechaCancelacion: null,
        pagos: [],
        tributos: [],
        liquidacion: null,
        ventasAcumuladas: null
    },
    abrirModal(fila, bloqueId) {
        this.modalDetalle = {
            abierto: true,
            bloqueId: bloqueId,
            periodo: fila.periodo,
            mesNombre: fila.mes_nombre,
            mesEtiqueta: fila.mes_etiqueta,
            regimen: fila.regimen,
            situacion: fila.situacion,
            situacionEtiqueta: fila.situacion_etiqueta,
            determinado: fila.total_determinado,
            pagado: fila.total_pagado,
            saldo: fila.total_saldo,
            vencimiento: fila.vencimiento,
            vencimientoOficial: fila.vencimiento_oficial,
            esIgvJusto: fila.es_igv_justo,
            cantidadPagos: fila.cantidad_pagos,
            fechaCancelacion: fila.fecha_cancelacion,
            pagos: fila.pagos || [],
            tributos: fila.tributos || [],
            liquidacion: fila.datos_liquidacion || null,
            ventasAcumuladas: fila.ventas_acumuladas || null
        };
    },
    cerrarModal() {
        this.modalDetalle.abierto = false;
    },
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
    {{-- BARRA SUPERIOR DE RESUMEN Y EJERCICIOS --}}
    <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-xs">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2">
                    <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    <h3 class="text-sm font-bold text-gray-900">Impuestos del ejercicio {{ $reporteImpuestos['anio'] }}</h3>
                </div>
                <div class="flex flex-wrap gap-1">
                    @foreach ($reporteImpuestos['anios'] as $anioOpcion)
                        <a href="{{ route('clientes.operaciones', ['cliente' => $cliente, 'anio' => $anioOpcion]) }}"
                           class="rounded-full px-2.5 py-0.5 text-[11px] font-bold transition {{ (int) $anioOpcion === (int) $reporteImpuestos['anio'] ? 'bg-gray-900 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ $anioOpcion }}
                        </a>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-500">Un cero es un mes sin importe en Control de Impuestos.</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Selector de Vista Global --}}
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs font-semibold">
                    <button type="button"
                            @click="vistaActual = 'dual'"
                            :class="vistaActual === 'dual' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800'"
                            class="rounded-md px-2.5 py-1 text-[11px] font-bold transition">
                        Dividida (DJ vs Pagos)
                    </button>
                    <button type="button"
                            @click="vistaActual = 'matriz'"
                            :class="vistaActual === 'matriz' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800'"
                            class="rounded-md px-2.5 py-1 text-[11px] font-bold transition">
                        Matriz por Códigos
                    </button>
                </div>
                <a href="{{ route('control.index', ['anio' => $reporteImpuestos['anio']]) }}"
                   class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-xs hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Registrar en Control de Impuestos
                </a>
            </div>
        </div>

        {{-- 5 TARJETAS DE KPIS SUPERIORES --}}
        <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold text-slate-600">PDT 621</p>
                    <span class="rounded bg-slate-200/60 px-1 py-0.2 text-[9px] font-bold text-slate-700">IGV · Renta</span>
                </div>
                <p class="mt-0.5 font-mono text-base font-bold tabular-nums text-slate-950">{{ $resumen['determinado_621'] }}</p>
            </div>
            <div class="rounded-lg border border-indigo-200 bg-indigo-50/80 px-3 py-2">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold text-indigo-700">PDT 601 · PLAME</p>
                    <span class="rounded bg-indigo-200/60 px-1 py-0.2 text-[9px] font-bold text-indigo-800">Planilla</span>
                </div>
                <p class="mt-0.5 font-mono text-base font-bold tabular-nums text-indigo-950">{{ $resumen['determinado_601'] }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold text-emerald-700">Pagado</p>
                    <span class="rounded bg-emerald-200/60 px-1 py-0.2 text-[9px] font-bold text-emerald-800">Efectuado</span>
                </div>
                <p class="mt-0.5 font-mono text-base font-bold tabular-nums text-emerald-950">{{ $resumen['pagado'] }}</p>
            </div>
            <div class="rounded-lg border px-3 py-2 {{ $hayDeuda ? 'border-rose-200 bg-rose-50/80' : 'border-gray-200 bg-gray-50/80' }}">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold {{ $hayDeuda ? 'text-rose-700' : 'text-gray-500' }}">Por pagar</p>
                    <span class="rounded {{ $hayDeuda ? 'bg-rose-200/60 text-rose-800' : 'bg-gray-200/60 text-gray-700' }} px-1 py-0.2 text-[9px] font-bold">Saldo</span>
                </div>
                <p class="mt-0.5 font-mono text-base font-bold tabular-nums {{ $hayDeuda ? 'text-rose-950' : 'text-gray-900' }}">{{ $resumen['saldo'] }}</p>
            </div>
            <div class="col-span-2 rounded-lg border px-3 py-2 lg:col-span-1 {{ $hayAtraso ? 'border-amber-200 bg-amber-50/80' : 'border-gray-200 bg-gray-50/80' }}">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold {{ $hayAtraso ? 'text-amber-800' : 'text-gray-500' }}">Fuera de fecha</p>
                    <span class="rounded {{ $hayAtraso ? 'bg-amber-200/60 text-amber-800' : 'bg-gray-200/60 text-gray-700' }} px-1 py-0.2 text-[9px] font-bold">Atraso</span>
                </div>
                <p class="mt-0.5 font-mono text-base font-bold tabular-nums {{ $hayAtraso ? 'text-amber-950' : 'text-gray-900' }}">{{ $resumen['fuera_de_fecha'] }} <span class="font-sans text-[11px] font-semibold">{{ $resumen['fuera_de_fecha'] === 1 ? 'periodo' : 'periodos' }}</span></p>
            </div>
        </div>
    </div>

    {{-- ALERTA CONTABLE DE TRANSICIÓN DE RÉGIMEN TRIBUTARIO (SI APLICA) --}}
    @if (!empty($analisisRegimen['hubo_transicion']))
        <div class="rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50/90 via-purple-50/80 to-indigo-50/90 p-3.5 shadow-xs">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 shrink-0 rounded-lg bg-indigo-600 p-2 text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-indigo-700 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">
                            Transición de Régimen Tributario
                        </span>
                        <span class="text-xs font-bold text-indigo-950">
                            Corte en {{ $analisisRegimen['mes_transicion_nombre'] }} {{ $reporteImpuestos['anio'] }} ({{ $analisisRegimen['periodo_transicion'] }})
                        </span>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-indigo-900">
                        {{ $analisisRegimen['mensaje_transicion'] }}
                    </p>
                    <p class="mt-1 text-[11px] text-indigo-700">
                        <strong>Criterio Técnico:</strong> Al superar S/ 525,000 en ingresos brutos acumulados en el RER, el contribuyente debe declarar y pagar en el Régimen MYPE Tributario (o General) desde el periodo en que superó dicho tope (Art. 121 LIR). En la tabla paralela inferior se visualiza el régimen y tasa exacta mes a mes.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- BLOQUES PRINCIPALES: PDT 621 (IGV Y RENTA) Y PDT 601 (PLAME) --}}
    @foreach ($reporteImpuestos['bloques'] as $bloque)
        @php
            $esPlame = $bloque['id'] === 'pdt_601';
            $encabezado = $esPlame ? 'border-indigo-100 bg-indigo-50 text-indigo-950' : 'border-slate-200 bg-slate-50 text-slate-900';
            $meses = $bloque['tributos'][0]['periodos'] ?? [];
            $filasPeriodos = $bloque['filas_periodos'] ?? [];
            $totalesBloque = $bloque['totales'] ?? [];
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
            {{-- Encabezado del Bloque --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-2.5 {{ $encabezado }}">
                <div class="flex items-center gap-2">
                    <span class="flex h-2 w-2 rounded-full {{ $esPlame ? 'bg-indigo-600' : 'bg-slate-700' }}"></span>
                    <h4 class="text-sm font-bold">
                        {{ $bloque['titulo'] }}
                        <span class="font-medium opacity-70">· {{ $bloque['detalle'] }}</span>
                    </h4>
                    @if (!empty($totalesBloque['determinado']))
                        <span class="ml-1 rounded-md bg-white/70 px-2 py-0.5 font-mono text-[11px] font-bold shadow-2xs">
                            Determinado: {{ $totalesBloque['determinado'] }}
                        </span>
                        <span class="rounded-md bg-emerald-100/80 px-2 py-0.5 font-mono text-[11px] font-bold text-emerald-900 shadow-2xs">
                            Pagado: {{ $totalesBloque['pagado'] }}
                        </span>
                        @if (($totalesBloque['saldo_centavos'] ?? 0) > 0)
                            <span class="rounded-md bg-rose-100 px-2 py-0.5 font-mono text-[11px] font-bold text-rose-800 shadow-2xs">
                                Saldo: {{ $totalesBloque['saldo'] }}
                            </span>
                        @endif
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @if ($tributosSinDatos > 0)
                        <button type="button"
                                @click="alternarTributos('{{ $bloque['id'] }}')"
                                :aria-expanded="!!tributosCompletos['{{ $bloque['id'] }}']"
                                class="shrink-0 text-[10px] font-medium opacity-60 hover:opacity-100"
                                title="Tributos sin importe en Control de Impuestos">
                            <span x-show="!tributosCompletos['{{ $bloque['id'] }}']">{{ $tributosSinDatos }} sin monto</span>
                            <span x-show="tributosCompletos['{{ $bloque['id'] }}']" x-cloak>ocultar</span>
                        </button>
                    @endif
                </div>
            </div>

            @if ($bloque['tributos'] === [])
                <p class="px-4 py-3 text-sm text-gray-500">{{ $bloque['mensaje'] }}</p>
            @else
                {{-- ════════════════════════════════════════════════════════════════════════════════ --}}
                {{-- VISTA 1: TABLA DIVIDIDA EN 2 PARALELA (DJ VS PAGOS SEGÚN EXTRACTO SUNAT) --}}
                {{-- ════════════════════════════════════════════════════════════════════════════════ --}}
                <div x-show="vistaActual === 'dual'" class="overflow-x-auto">
                    @if ($bloque['id'] === 'pdt_621')
                        {{-- ──────────────────────────────────────────────────────────────────────────── --}}
                        {{-- TABLA PARALELA PDT 621 · IGV Y RENTA (COLUMNAS INDEPENDIENTES)                --}}
                        {{-- ──────────────────────────────────────────────────────────────────────────── --}}
                        <table class="w-full min-w-[1180px] table-auto border-collapse text-left text-xs">
                            <thead>
                                {{-- Fila Superior: Títulos de los 2 Bloques Paralelos --}}
                                <tr class="border-b border-gray-200 text-center font-bold">
                                    <th colspan="6" class="border-r border-slate-300 bg-slate-100/90 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wide text-slate-800">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <svg class="h-3.5 w-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span>1. Determinación según DJ · ¿Cuándo y cuánto se tenía que pagar? (PDT 621)</span>
                                        </div>
                                    </th>
                                    <th colspan="6" class="bg-emerald-50/90 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wide text-emerald-950">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>2. Cumplimiento y Pagos · ¿Cuándo y cuánto se pagó según DJ? (Extracto SUNAT)</span>
                                        </div>
                                    </th>
                                    <th class="w-16 bg-gray-50 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-gray-500">
                                        Detalle
                                    </th>
                                </tr>
                                {{-- Fila Inferior: Columnas de Cada Bloque --}}
                                <tr class="border-b border-gray-200 bg-gray-50/60 text-[10px] font-bold uppercase tracking-wider text-gray-600">
                                    {{-- Lado 1: DJ --}}
                                    <th class="w-24 px-3 py-2">Periodo</th>
                                    <th class="w-24 px-2 py-2">Régimen</th>
                                    <th class="w-24 px-2 py-2">Vence SUNAT</th>
                                    <th class="w-28 px-3 py-2 text-right">4011 IGV (18%)</th>
                                    <th class="w-28 px-3 py-2 text-right">4017 Renta</th>
                                    <th class="w-32 border-r border-slate-300 px-3 py-2 text-right text-slate-900 font-extrabold">Total PDT · A Pagar (DJ)</th>
                                    {{-- Lado 2: Pagos --}}
                                    <th class="w-28 px-3 py-2">Cuándo se Pagó</th>
                                    <th class="w-28 px-3 py-2 text-right text-emerald-900">4011 IGV Pag.</th>
                                    <th class="w-28 px-3 py-2 text-right text-emerald-900">4017 Rta Pag.</th>
                                    <th class="w-28 px-3 py-2 text-right text-emerald-950 font-extrabold">Total Pagado · Monto Pagado</th>
                                    <th class="w-28 px-3 py-2 text-right text-rose-800 font-extrabold">Saldo (Debe)</th>
                                    <th class="w-32 px-3 py-2 text-center">Situación</th>
                                    {{-- Acción --}}
                                    <th class="w-16 px-1 py-2 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($filasPeriodos as $fila)
                                    @php
                                        $saldoPositivo = !empty($fila['debe']) && ($fila['total_saldo_centavos'] ?? 0) > 0;
                                        $esTransicionMes = !empty($fila['regimen']['es_transicion']);
                                        $igvDetCent = $fila['igv']['determinado_centavos'] ?? 0;
                                        $rentaDetCent = $fila['renta']['determinado_centavos'] ?? 0;
                                        $igvPagCent = $fila['igv']['pagado_centavos'] ?? 0;
                                        $rentaPagCent = $fila['renta']['pagado_centavos'] ?? 0;
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50/80 cursor-pointer"
                                        @click="abrirModal({{ json_encode($fila) }}, '{{ $bloque['id'] }}')"
                                        title="Clic para ver desglose contable completo de {{ $fila['mes_nombre'] }} {{ $reporteImpuestos['anio'] }}">
                                        {{-- LADO 1: DETERMINACIÓN PDT 621 --}}
                                        <td class="px-3 py-2 align-middle">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono text-xs font-bold text-gray-900">{{ $fila['mes_etiqueta'] }}</span>
                                                <span class="text-[10px] text-gray-500 font-medium hidden sm:inline">({{ $fila['mes_nombre'] }})</span>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-mono text-[10px] font-bold {{ $fila['regimen']['sigla'] === 'RER' ? 'bg-amber-100 text-amber-900' : ($fila['regimen']['sigla'] === 'RG' ? 'bg-purple-100 text-purple-900' : 'bg-blue-100 text-blue-900') }}">
                                                {{ $fila['regimen']['nombre'] }}
                                                @if ($esTransicionMes)
                                                    <span class="text-[9px]" title="Mes de migración legal de RER a RMT">⚠️</span>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-2 py-2 align-middle">
                                            <div class="flex flex-col">
                                                <span class="font-mono text-[11px] font-medium text-gray-700">
                                                    {{ $fila['vencimiento'] }}
                                                </span>
                                                @if ($fila['es_igv_justo'])
                                                    <span class="inline-flex w-fit items-center rounded bg-indigo-100 px-1 py-0.2 text-[9px] font-bold text-indigo-800">
                                                        IGV Justo
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        {{-- 4011 IGV DETERMINADO --}}
                                        <td class="px-3 py-2 text-right align-middle">
                                            @if ($igvDetCent > 0)
                                                <span class="font-mono text-xs font-bold tabular-nums text-slate-900">{{ $fila['igv']['determinado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- 4017 RENTA DETERMINADA --}}
                                        <td class="px-3 py-2 text-right align-middle">
                                            @if ($rentaDetCent > 0)
                                                <span class="font-mono text-xs font-bold tabular-nums text-slate-900">{{ $fila['renta']['determinado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- TOTAL PDT 621 --}}
                                        <td class="border-r border-slate-300 px-3 py-2 text-right align-middle">
                                            @if (($fila['total_determinado_centavos'] ?? 0) > 0)
                                                <span class="font-mono text-xs font-black tabular-nums text-slate-950">{{ $fila['total_determinado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- LADO 2: CUMPLIMIENTO Y PAGOS --}}
                                        <td class="px-3 py-2 align-middle">
                                            <div class="flex flex-col">
                                                <span class="font-mono text-[11px] font-medium text-gray-800">
                                                    {{ $fila['fecha_pago'] }}
                                                </span>
                                                @if (!empty($fila['fecha_cancelacion']) && ($fila['cantidad_pagos'] ?? 0) > 0)
                                                    <span class="text-[10px] font-semibold text-emerald-700">
                                                        canceló {{ $fila['fecha_cancelacion'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        {{-- 4011 IGV PAGADO --}}
                                        <td class="px-3 py-2 text-right align-middle">
                                            @if ($igvPagCent > 0)
                                                <span class="font-mono text-xs font-bold tabular-nums text-emerald-900">{{ $fila['igv']['pagado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- 4017 RENTA PAGADA --}}
                                        <td class="px-3 py-2 text-right align-middle">
                                            @if ($rentaPagCent > 0)
                                                <span class="font-mono text-xs font-bold tabular-nums text-emerald-900">{{ $fila['renta']['pagado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- TOTAL PAGADO --}}
                                        <td class="px-3 py-2 text-right align-middle">
                                            @if (($fila['total_pagado_centavos'] ?? 0) > 0)
                                                <span class="font-mono text-xs font-black tabular-nums text-emerald-950">{{ $fila['total_pagado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- SALDO (DEBE) --}}
                                        <td class="px-3 py-2 text-right align-middle">
                                            @if ($saldoPositivo)
                                                <span class="font-mono text-xs font-black tabular-nums text-rose-700">
                                                    Debe {{ str_replace('S/ ', '', (string) $fila['total_saldo']) }}
                                                </span>
                                            @else
                                                <span class="font-mono text-xs font-medium tabular-nums text-gray-400">
                                                    {{ $fila['total_saldo'] }}
                                                </span>
                                            @endif
                                        </td>
                                        {{-- SITUACIÓN --}}
                                        <td class="px-3 py-2 text-center align-middle">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tonoEstado($fila['situacion']) }}">
                                                {{ $fila['situacion_etiqueta'] }}
                                            </span>
                                        </td>

                                        {{-- ACCIÓN --}}
                                        <td class="px-1 py-2 text-center align-middle">
                                            <button type="button"
                                                    class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-bold text-gray-700 shadow-2xs hover:bg-gray-100 hover:text-gray-900 transition"
                                                    @click.stop="abrirModal({{ json_encode($fila) }}, '{{ $bloque['id'] }}')">
                                                Detalle
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-300 bg-gray-50/95 font-bold text-xs divide-y divide-gray-200">
                                {{-- Fila 1: TOTALES --}}
                                <tr>
                                    <td colspan="3" class="px-3 py-2.5 text-gray-700 uppercase tracking-wide text-[11px] font-black">
                                        TOTALES DEL EJERCICIO {{ $reporteImpuestos['anio'] }} (PDT 621)
                                    </td>
                                    {{-- Total IGV Determinado --}}
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-900">
                                        {{ $totalesBloque['totales_columnas']['igv']['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Total Renta Determinada --}}
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-900">
                                        {{ $totalesBloque['totales_columnas']['renta']['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Total PDT Determinado --}}
                                    <td class="border-r border-slate-300 px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-950">
                                        {{ $totalesBloque['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Pagos Info --}}
                                    <td class="px-3 py-2.5 font-medium text-gray-500 text-[11px]">
                                        {{ $totalesBloque['cantidad_pagos'] ?? 0 }} pagos
                                    </td>
                                    {{-- Total IGV Pagado --}}
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-900">
                                        {{ $totalesBloque['totales_columnas']['igv']['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Total Renta Pagada --}}
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-900">
                                        {{ $totalesBloque['totales_columnas']['renta']['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Total Pagado --}}
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-950">
                                        {{ $totalesBloque['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Total Saldo --}}
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-700' }}">
                                        {{ $totalesBloque['saldo'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Situación General --}}
                                    <td class="px-3 py-2.5 text-center text-[10px] font-bold text-gray-600">
                                        {{ $totalesBloque['al_dia'] ?? 0 }} al día · {{ $totalesBloque['con_deuda'] ?? 0 }} con deuda
                                    </td>
                                    <td class="px-1 py-2.5"></td>
                                </tr>

                                {{-- Fila 2: DIFERENCIAS (Exactamente como en la hoja contable del contador senior) --}}
                                <tr class="bg-amber-50/50">
                                    <td colspan="3" class="px-3 py-2 text-rose-800 uppercase tracking-wide text-[10px] font-black">
                                        DIFERENCIAS / SALDOS PENDIENTES:
                                    </td>
                                    {{-- IGV Pendiente --}}
                                    <td class="px-3 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['diferencias']['igv_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['igv'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Renta Pendiente --}}
                                    <td class="px-3 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['diferencias']['renta_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['renta'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Total Diferencia PDT --}}
                                    <td class="border-r border-slate-300 px-3 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['total'] ?? 'S/ 0.00' }}
                                    </td>
                                    {{-- Aclaración Extracto --}}
                                    <td colspan="3" class="px-3 py-2 text-[10px] italic text-gray-500 font-medium">
                                        Saldos netos por amortizar ante SUNAT
                                    </td>
                                    <td class="px-3 py-2 text-right text-[10px] font-bold text-gray-400">—</td>
                                    {{-- Saldo Final --}}
                                    <td class="px-3 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['total'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-[10px] font-bold {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                        {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'Existen saldos por pagar' : 'Todo al día' }}
                                    </td>
                                    <td class="px-1 py-2"></td>
                                </tr>
                            </tfoot>
                        </table>

                    @elseif ($bloque['id'] === 'pdt_601')
                        {{-- ──────────────────────────────────────────────────────────────────────────── --}}
                        {{-- TABLA PARALELA PDT 601 · PLAME (COLUMNAS INDEPENDIENTES POR CONCEPTO)          --}}
                        {{-- ──────────────────────────────────────────────────────────────────────────── --}}
                        <table class="w-full min-w-[1320px] table-auto border-collapse text-left text-xs">
                            <thead>
                                {{-- Fila Superior: Títulos de los 2 Bloques Paralelos --}}
                                <tr class="border-b border-gray-200 text-center font-bold">
                                    <th colspan="8" class="border-r border-indigo-200 bg-indigo-50/90 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wide text-indigo-950">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <svg class="h-3.5 w-3.5 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                            <span>1. Determinación según DJ · ¿Cuándo y cuánto se tenía que pagar? (PDT 601 PLAME)</span>
                                        </div>
                                    </th>
                                    <th colspan="8" class="bg-emerald-50/90 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wide text-emerald-950">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>2. Cumplimiento y Pagos · ¿Cuándo y cuánto se pagó según DJ? (Extracto Bancos / SUNAT)</span>
                                        </div>
                                    </th>
                                    <th class="w-16 bg-gray-50 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-gray-500">
                                        Detalle
                                    </th>
                                </tr>
                                {{-- Fila Inferior: Columnas de Cada Bloque --}}
                                <tr class="border-b border-gray-200 bg-gray-50/60 text-[10px] font-bold uppercase tracking-wider text-gray-600">
                                    {{-- Lado 1: DJ PLAME --}}
                                    <th class="w-20 px-2.5 py-2">Periodo</th>
                                    <th class="w-24 px-2 py-2">Vence SUNAT</th>
                                    <th class="w-24 px-2 py-2 text-right">5210 EsSalud</th>
                                    <th class="w-24 px-2 py-2 text-right">5310 ONP</th>
                                    <th class="w-24 px-2 py-2 text-right">6098 AFP</th>
                                    <th class="w-24 px-2 py-2 text-right">3042 Rta 4ta</th>
                                    <th class="w-24 px-2 py-2 text-right">3052 Rta 5ta</th>
                                    <th class="w-28 border-r border-indigo-200 px-2.5 py-2 text-right text-indigo-950 font-extrabold">Total PLAME · A Pagar (DJ)</th>
                                    {{-- Lado 2: Pagos Extracto --}}
                                    <th class="w-28 px-2.5 py-2">Cuándo se Pagó</th>
                                    <th class="w-24 px-2 py-2 text-right text-emerald-900">EsSalud Pag.</th>
                                    <th class="w-24 px-2 py-2 text-right text-emerald-900">ONP Pag.</th>
                                    <th class="w-24 px-2 py-2 text-right text-emerald-900">AFP Pag.</th>
                                    <th class="w-24 px-2 py-2 text-right text-emerald-900">Rta 4ta Pag.</th>
                                    <th class="w-24 px-2 py-2 text-right text-emerald-900">Rta 5ta Pag.</th>
                                    <th class="w-28 px-2.5 py-2 text-right text-emerald-950 font-extrabold">Total Pagado · Monto Pagado</th>
                                    <th class="w-28 px-2.5 py-2 text-right text-rose-800 font-extrabold">Saldo (Debe)</th>
                                    <th class="w-32 px-2 py-2 text-center">Situación</th>
                                    {{-- Acción --}}
                                    <th class="w-16 px-1 py-2 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($filasPeriodos as $fila)
                                    @php
                                        $saldoPositivo = !empty($fila['debe']) && ($fila['total_saldo_centavos'] ?? 0) > 0;
                                        $plame = $fila['plame'] ?? [];
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50/80 cursor-pointer"
                                        @click="abrirModal({{ json_encode($fila) }}, '{{ $bloque['id'] }}')"
                                        title="Clic para ver detalle de planilla de {{ $fila['mes_nombre'] }} {{ $reporteImpuestos['anio'] }}">
                                        {{-- LADO 1: DETERMINACIÓN PLAME --}}
                                        <td class="px-2.5 py-2 align-middle">
                                            <span class="font-mono text-xs font-bold text-gray-900">{{ $fila['mes_etiqueta'] }}</span>
                                        </td>
                                        <td class="px-2 py-2 align-middle font-mono text-[11px] font-medium text-gray-700">
                                            {{ $fila['vencimiento'] }}
                                        </td>
                                        {{-- EsSalud --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['essalud']['determinado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-slate-900">{{ $plame['essalud']['determinado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- ONP --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['onp']['determinado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-slate-900">{{ $plame['onp']['determinado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- AFP --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['afp']['determinado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-slate-900">{{ $plame['afp']['determinado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Renta 4ta --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['renta_4ta']['determinado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-slate-900">{{ $plame['renta_4ta']['determinado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Renta 5ta --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['renta_5ta']['determinado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-slate-900">{{ $plame['renta_5ta']['determinado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Total PLAME --}}
                                        <td class="border-r border-indigo-200 px-2.5 py-2 text-right align-middle">
                                            @if (($fila['total_determinado_centavos'] ?? 0) > 0)
                                                <span class="font-mono text-xs font-black tabular-nums text-indigo-950">{{ $fila['total_determinado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- LADO 2: CUMPLIMIENTO EXTRACTO --}}
                                        <td class="px-2.5 py-2 align-middle font-mono text-[11px] font-medium text-gray-800">
                                            {{ $fila['fecha_pago'] }}
                                        </td>
                                        {{-- EsSalud Pagado --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['essalud']['pagado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-emerald-900">{{ $plame['essalud']['pagado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- ONP Pagado --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['onp']['pagado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-emerald-900">{{ $plame['onp']['pagado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- AFP Pagado --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['afp']['pagado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-emerald-900">{{ $plame['afp']['pagado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Renta 4ta Pagada --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['renta_4ta']['pagado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-emerald-900">{{ $plame['renta_4ta']['pagado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Renta 5ta Pagada --}}
                                        <td class="px-2 py-2 text-right align-middle font-mono text-xs tabular-nums">
                                            @if (($plame['renta_5ta']['pagado_centavos'] ?? 0) > 0)
                                                <span class="font-bold text-emerald-900">{{ $plame['renta_5ta']['pagado'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Total Pagado --}}
                                        <td class="px-2.5 py-2 text-right align-middle">
                                            @if (($fila['total_pagado_centavos'] ?? 0) > 0)
                                                <span class="font-mono text-xs font-black tabular-nums text-emerald-950">{{ $fila['total_pagado'] }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-300">—</span>
                                            @endif
                                        </td>
                                        {{-- Saldo --}}
                                        <td class="px-2.5 py-2 text-right align-middle">
                                            @if ($saldoPositivo)
                                                <span class="font-mono text-xs font-black tabular-nums text-rose-700">
                                                    Debe {{ str_replace('S/ ', '', (string) $fila['total_saldo']) }}
                                                </span>
                                            @else
                                                <span class="font-mono text-xs font-medium tabular-nums text-gray-400">
                                                    {{ $fila['total_saldo'] }}
                                                </span>
                                            @endif
                                        </td>
                                        {{-- Situación --}}
                                        <td class="px-2 py-2 text-center align-middle">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tonoEstado($fila['situacion']) }}">
                                                {{ $fila['situacion_etiqueta'] }}
                                            </span>
                                        </td>
                                        {{-- Acción --}}
                                        <td class="px-1 py-2 text-center align-middle">
                                            <button type="button"
                                                    class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-bold text-gray-700 shadow-2xs hover:bg-gray-100 hover:text-gray-900 transition"
                                                    @click.stop="abrirModal({{ json_encode($fila) }}, '{{ $bloque['id'] }}')">
                                                Detalle
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-300 bg-gray-50/95 font-bold text-xs divide-y divide-gray-200">
                                {{-- Fila 1: TOTALES --}}
                                <tr>
                                    <td colspan="2" class="px-2.5 py-2.5 text-gray-700 uppercase tracking-wide text-[10px] font-black">
                                        TOTALES {{ $reporteImpuestos['anio'] }} (PDT 601)
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-900">
                                        {{ $totalesBloque['totales_columnas']['essalud']['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-900">
                                        {{ $totalesBloque['totales_columnas']['onp']['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-900">
                                        {{ $totalesBloque['totales_columnas']['afp']['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-900">
                                        {{ $totalesBloque['totales_columnas']['renta_4ta']['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-900">
                                        {{ $totalesBloque['totales_columnas']['renta_5ta']['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="border-r border-indigo-200 px-2.5 py-2.5 text-right font-mono text-xs font-black tabular-nums text-indigo-950">
                                        {{ $totalesBloque['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2.5 py-2.5 font-medium text-gray-500 text-[10px]">
                                        {{ $totalesBloque['cantidad_pagos'] ?? 0 }} pagos
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-900">
                                        {{ $totalesBloque['totales_columnas']['essalud']['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-900">
                                        {{ $totalesBloque['totales_columnas']['onp']['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-900">
                                        {{ $totalesBloque['totales_columnas']['afp']['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-900">
                                        {{ $totalesBloque['totales_columnas']['renta_4ta']['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-900">
                                        {{ $totalesBloque['totales_columnas']['renta_5ta']['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2.5 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-950">
                                        {{ $totalesBloque['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2.5 py-2.5 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-700' }}">
                                        {{ $totalesBloque['saldo'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-center text-[10px] font-bold text-gray-600">
                                        {{ $totalesBloque['al_dia'] ?? 0 }} al día · {{ $totalesBloque['con_deuda'] ?? 0 }} con deuda
                                    </td>
                                    <td class="px-1 py-2.5"></td>
                                </tr>

                                {{-- Fila 2: DIFERENCIAS PLAME --}}
                                <tr class="bg-amber-50/50">
                                    <td colspan="2" class="px-2.5 py-2 text-rose-800 uppercase tracking-wide text-[10px] font-black">
                                        DIFERENCIAS:
                                    </td>
                                    <td class="px-2 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['diferencias']['essalud_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['essalud'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['diferencias']['onp_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['onp'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['diferencias']['afp_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['afp'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['diferencias']['renta_4ta_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['renta_4ta'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['diferencias']['renta_5ta_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['renta_5ta'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="border-r border-indigo-200 px-2.5 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['total'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td colspan="6" class="px-2.5 py-2 text-[10px] italic text-gray-500 font-medium">
                                        Saldos netos pendientes por pagar a ESSALUD, ONP, AFPs o Retenciones SUNAT
                                    </td>
                                    <td class="px-2.5 py-2 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                        {{ $totalesBloque['diferencias']['total'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-2 py-2 text-center text-[10px] font-bold {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                        {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'Con saldo pendiente' : 'Al día' }}
                                    </td>
                                    <td class="px-1 py-2"></td>
                                </tr>
                            </tfoot>
                        </table>

                    @else
                        {{-- ──────────────────────────────────────────────────────────────────────────── --}}
                        {{-- TABLA PARALELA GENÉRICA / OTROS FORMULARIOS                                    --}}
                        {{-- ──────────────────────────────────────────────────────────────────────────── --}}
                        <table class="w-full min-w-[960px] table-auto border-collapse text-left text-xs">
                            <thead>
                                <tr class="border-b border-gray-200 text-center font-bold">
                                    <th colspan="4" class="border-r border-slate-300 bg-slate-100/90 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wide text-slate-800">
                                        1. Determinación según DJ · ¿Cuánto y cuándo se tenía que pagar?
                                    </th>
                                    <th colspan="4" class="bg-emerald-50/90 px-3 py-2 text-[11px] font-extrabold uppercase tracking-wide text-emerald-950">
                                        2. Cumplimiento y Pagos · ¿Cuándo y cuánto se pagó según DJ?
                                    </th>
                                    <th class="w-16 bg-gray-50 px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-gray-500">
                                        Detalle
                                    </th>
                                </tr>
                                <tr class="border-b border-gray-200 bg-gray-50/50 text-[10px] font-bold uppercase tracking-wider text-gray-600">
                                    <th class="w-24 px-3 py-2">Periodo</th>
                                    <th class="w-24 px-2 py-2">Régimen</th>
                                    <th class="w-24 px-2 py-2">Vence SUNAT</th>
                                    <th class="w-36 border-r border-slate-300 px-3 py-2 text-right">A Pagar (DJ)</th>
                                    <th class="w-32 px-3 py-2">Cuándo se Pagó</th>
                                    <th class="w-32 px-3 py-2 text-right">Monto Pagado</th>
                                    <th class="w-28 px-3 py-2 text-right">Saldo (Debe)</th>
                                    <th class="w-36 px-3 py-2 text-center">Situación</th>
                                    <th class="w-16 px-1 py-2 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($filasPeriodos as $fila)
                                    @php
                                        $saldoPositivo = !empty($fila['debe']) && ($fila['total_saldo_centavos'] ?? 0) > 0;
                                    @endphp
                                    <tr class="transition-colors hover:bg-slate-50/80 cursor-pointer"
                                        @click="abrirModal({{ json_encode($fila) }}, '{{ $bloque['id'] }}')">
                                        <td class="px-3 py-2 align-middle font-mono text-xs font-bold text-gray-900">
                                            {{ $fila['mes_etiqueta'] }}
                                        </td>
                                        <td class="px-2 py-2 align-middle text-[10px] text-gray-600">
                                            {{ $fila['regimen']['nombre'] ?? '—' }}
                                        </td>
                                        <td class="px-2 py-2 align-middle font-mono text-[11px] text-gray-700">
                                            {{ $fila['vencimiento'] }}
                                        </td>
                                        <td class="border-r border-slate-300 px-3 py-2 text-right font-mono text-xs font-bold text-slate-950 align-middle">
                                            {{ $fila['total_determinado'] }}
                                        </td>
                                        <td class="px-3 py-2 align-middle font-mono text-[11px] text-gray-800">
                                            {{ $fila['fecha_pago'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono text-xs font-bold text-emerald-950 align-middle">
                                            {{ $fila['total_pagado'] }}
                                        </td>
                                        <td class="px-3 py-2 text-right align-middle">
                                            @if ($saldoPositivo)
                                                <span class="font-mono text-xs font-bold text-rose-700">Debe {{ str_replace('S/ ', '', (string) $fila['total_saldo']) }}</span>
                                            @else
                                                <span class="font-mono text-xs text-gray-400">{{ $fila['total_saldo'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center align-middle">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tonoEstado($fila['situacion']) }}">
                                                {{ $fila['situacion_etiqueta'] }}
                                            </span>
                                        </td>
                                        <td class="px-1 py-2 text-center align-middle">
                                            <button type="button"
                                                    class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-bold text-gray-700 shadow-2xs hover:bg-gray-100 transition"
                                                    @click.stop="abrirModal({{ json_encode($fila) }}, '{{ $bloque['id'] }}')">
                                                Detalle
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-300 bg-gray-50/90 font-bold text-xs">
                                <tr>
                                    <td colspan="3" class="px-3 py-2.5 text-gray-700 uppercase tracking-wide text-[11px]">
                                        Totales del Ejercicio {{ $reporteImpuestos['anio'] }} ({{ $bloque['titulo'] }})
                                    </td>
                                    <td class="border-r border-slate-300 px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-slate-950">
                                        {{ $totalesBloque['determinado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-3 py-2.5 font-medium text-gray-500 text-[11px]">
                                        {{ $totalesBloque['cantidad_pagos'] ?? 0 }} pagos registrados
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums text-emerald-950">
                                        {{ $totalesBloque['pagado'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs font-black tabular-nums {{ ($totalesBloque['saldo_centavos'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-700' }}">
                                        {{ $totalesBloque['saldo'] ?? 'S/ 0.00' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center text-[10px] font-bold text-gray-600">
                                        {{ $totalesBloque['al_dia'] ?? 0 }} al día · {{ $totalesBloque['con_deuda'] ?? 0 }} con deuda
                                    </td>
                                    <td class="px-1 py-2.5"></td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>

                {{-- ════════════════════════════════════════════════════════════════════════════════ --}}
                {{-- VISTA 2: MATRIZ POR CÓDIGOS (HISTÓRICA Y DE DETALLE TRIBUTARIO) --}}
                {{-- ════════════════════════════════════════════════════════════════════════════════ --}}
                <div x-show="vistaActual === 'matriz'" @if ($mallaOculta) data-malla-oculta x-show="tributosCompletos['{{ $bloque['id'] }}']" x-cloak @endif>
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

                    {{-- Tarjeta de celda seleccionada en la matriz --}}
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
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tonoEstado($fila['situacion']) }}">{{ $fila['situacion_etiqueta'] }}</span>
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

    {{-- MODAL / DRAWER INTERACTIVO DE DETALLE ESPECÍFICO --}}
    <div x-show="modalDetalle.abierto"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true"
         @keydown.escape.window="cerrarModal()">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
             x-show="modalDetalle.abierto"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="cerrarModal()"></div>

        <div class="flex min-h-full items-center justify-center p-3 text-center sm:p-5">
            <div class="relative w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all border border-gray-200"
                 x-show="modalDetalle.abierto"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @click.stop>
                
                {{-- Cabecera del Modal --}}
                <div class="flex items-start justify-between border-b border-gray-200 bg-slate-50 px-5 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-gray-900" id="modal-title">
                                Detalle Contable · <span x-text="modalDetalle.mesNombre"></span> {{ $reporteImpuestos['anio'] }}
                            </h3>
                            <span class="rounded-md bg-gray-200 px-2 py-0.5 font-mono text-xs font-bold text-gray-800" x-text="modalDetalle.periodo"></span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $cliente->razon_social }} (RUC {{ $cliente->ruc }}) · <span x-text="modalDetalle.bloqueId === 'pdt_621' ? 'PDT 621 (IGV y Renta)' : 'PDT 601 (PLAME)'"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold border"
                              :class="{
                                  'bg-emerald-100 text-emerald-800 border-emerald-200': ['pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_sin_fecha'].includes(modalDetalle.situacion),
                                  'bg-amber-100 text-amber-900 border-amber-200': ['pagado_fuera_de_fecha', 'debe_parcial'].includes(modalDetalle.situacion),
                                  'bg-rose-100 text-rose-800 border-rose-200': ['debe_vencido', 'debe_parcial_vencido', 'debe_sin_vencimiento'].includes(modalDetalle.situacion),
                                  'bg-sky-100 text-sky-900 border-sky-200': modalDetalle.situacion === 'debe_en_plazo',
                                  'bg-gray-100 text-gray-700 border-gray-200': !['pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_fuera_de_fecha', 'debe_vencido', 'debe_parcial_vencido', 'debe_en_plazo'].includes(modalDetalle.situacion)
                              }"
                              x-text="modalDetalle.situacionEtiqueta">
                        </span>
                        <button type="button"
                                @click="cerrarModal()"
                                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Cuerpo del Modal --}}
                <div class="max-h-[75vh] overflow-y-auto px-5 py-4 space-y-4">
                    {{-- 4 Métricas Principales del Periodo --}}
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">A Pagar · según PDT</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-slate-950" x-text="modalDetalle.determinado || 'S/ 0.00'"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Vence SUNAT</p>
                            <div class="mt-0.5 flex items-center gap-1">
                                <span class="font-mono text-xs font-bold text-gray-900" x-text="modalDetalle.vencimiento || '—'"></span>
                                <span x-show="modalDetalle.esIgvJusto" class="rounded bg-indigo-100 px-1 py-0.2 text-[9px] font-bold text-indigo-800">Justo</span>
                            </div>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Monto Pagado</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-emerald-950" x-text="modalDetalle.pagado || 'S/ 0.00'"></p>
                        </div>
                        <div class="rounded-xl border p-3"
                             :class="(modalDetalle.saldo && modalDetalle.saldo !== 'S/ 0.00' && modalDetalle.saldo !== 'S/ 0') ? 'border-rose-200 bg-rose-50' : 'border-gray-200 bg-gray-50'">
                            <p class="text-[10px] font-bold uppercase tracking-wider"
                               :class="(modalDetalle.saldo && modalDetalle.saldo !== 'S/ 0.00' && modalDetalle.saldo !== 'S/ 0') ? 'text-rose-700' : 'text-gray-500'">Saldo (Debe)</p>
                            <p class="mt-0.5 font-mono text-base font-bold"
                               :class="(modalDetalle.saldo && modalDetalle.saldo !== 'S/ 0.00' && modalDetalle.saldo !== 'S/ 0') ? 'text-rose-950' : 'text-gray-800'"
                               x-text="modalDetalle.saldo || 'S/ 0.00'"></p>
                        </div>
                    </div>

                    {{-- Alerta de Transición si este mes migró de RER a RMT --}}
                    <template x-if="modalDetalle.regimen?.es_transicion">
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-amber-900">
                            <div class="flex items-center gap-2">
                                <span class="text-base">⚠️</span>
                                <p class="text-xs font-bold">Mes de Transición de Régimen (RER ➔ RMT)</p>
                            </div>
                            <p class="mt-1 text-[11px] leading-relaxed text-amber-800">
                                En este mes el contribuyente superó el límite de ingresos anuales de S/ 525,000 en el RER y pasó a tributar bajo el Régimen MYPE Tributario (RMT) con pago a cuenta del 1.0% (Art. 121 LIR).
                            </p>
                        </div>
                    </template>

                    {{-- SECCIÓN: DETERMINACIÓN DE DEUDA Y DESGLOSE --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">
                            1. Determinación de la Deuda · Declaración Jurada (DJ)
                        </h4>

                        {{-- Cifras de Liquidación (si existen) --}}
                        <template x-if="modalDetalle.liquidacion">
                            <div class="mt-2.5 grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                                {{-- Ventas --}}
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 text-xs space-y-1">
                                    <p class="font-bold text-slate-800">Ventas del Mes</p>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Base Gravada:</span>
                                        <span class="font-mono font-bold" x-text="modalDetalle.liquidacion.ventas_base_formateado || 'S/ 0.00'"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>IGV Débito Fiscal:</span>
                                        <span class="font-mono font-bold text-gray-900" x-text="'S/ ' + Number(modalDetalle.liquidacion.igv_ventas || 0).toFixed(2)"></span>
                                    </div>
                                </div>
                                {{-- Compras --}}
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 text-xs space-y-1">
                                    <p class="font-bold text-slate-800">Compras del Mes</p>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Base Gravada:</span>
                                        <span class="font-mono font-bold" x-text="modalDetalle.liquidacion.compras_base_formateado || 'S/ 0.00'"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>IGV Crédito Fiscal:</span>
                                        <span class="font-mono font-bold text-gray-900" x-text="'S/ ' + Number(modalDetalle.liquidacion.igv_compras || 0).toFixed(2)"></span>
                                    </div>
                                    <template x-if="modalDetalle.liquidacion.igv_saldo_favor_anterior > 0">
                                        <div class="flex justify-between text-indigo-700 font-medium">
                                            <span>Saldo Favor Ant.:</span>
                                            <span class="font-mono" x-text="'S/ ' + Number(modalDetalle.liquidacion.igv_saldo_favor_anterior).toFixed(2)"></span>
                                        </div>
                                    </template>
                                </div>
                                {{-- Renta --}}
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2.5 text-xs space-y-1">
                                    <p class="font-bold text-slate-800">Renta Mensual</p>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Régimen:</span>
                                        <span class="font-bold text-gray-900" x-text="modalDetalle.regimen?.nombre || '—'"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Tasa / Coef.:</span>
                                        <span class="font-mono font-bold" x-text="modalDetalle.liquidacion.renta_tasa ? (modalDetalle.liquidacion.renta_tasa + '%') : (modalDetalle.regimen?.tasa ? (modalDetalle.regimen.tasa + '%') : '—')"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Resultante:</span>
                                        <span class="font-mono font-bold text-gray-900" x-text="'S/ ' + Number(modalDetalle.liquidacion.renta_resultante_pagar || 0).toFixed(2)"></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Desglose de Tributos Individuales --}}
                        <div class="mt-2.5 overflow-hidden rounded-lg border border-gray-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2">Código</th>
                                        <th class="px-3 py-2">Tributo SUNAT</th>
                                        <th class="px-3 py-2 text-right">Determinado (DJ)</th>
                                        <th class="px-3 py-2 text-right">Pagado</th>
                                        <th class="px-3 py-2 text-right">Saldo</th>
                                        <th class="px-3 py-2 text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="trib in modalDetalle.tributos" :key="trib.codigo">
                                        <tr>
                                            <td class="px-3 py-2 font-mono font-bold text-gray-600" x-text="trib.codigo"></td>
                                            <td class="px-3 py-2 font-semibold text-gray-900" x-text="trib.nombre"></td>
                                            <td class="px-3 py-2 text-right font-mono font-bold text-slate-900" x-text="trib.determinado"></td>
                                            <td class="px-3 py-2 text-right font-mono font-bold text-emerald-900" x-text="trib.pagado"></td>
                                            <td class="px-3 py-2 text-right font-mono font-bold"
                                                :class="(trib.saldo_centavos > 0) ? 'text-rose-700' : 'text-gray-500'"
                                                x-text="trib.saldo"></td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border"
                                                      :class="{
                                                          'bg-emerald-100 text-emerald-800 border-emerald-200': ['pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_sin_fecha'].includes(trib.situacion),
                                                          'bg-amber-100 text-amber-900 border-amber-200': ['pagado_fuera_de_fecha', 'debe_parcial'].includes(trib.situacion),
                                                          'bg-rose-100 text-rose-800 border-rose-200': ['debe_vencido', 'debe_parcial_vencido', 'debe_sin_vencimiento'].includes(trib.situacion),
                                                          'bg-sky-100 text-sky-900 border-sky-200': trib.situacion === 'debe_en_plazo',
                                                          'bg-gray-100 text-gray-700 border-gray-200': !['pagado_a_tiempo', 'pagado_sin_vencimiento', 'pagado_fuera_de_fecha', 'debe_vencido', 'debe_parcial_vencido', 'debe_en_plazo'].includes(trib.situacion)
                                                      }"
                                                      x-text="trib.situacion_etiqueta">
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- SECCIÓN: CUMPLIMIENTO Y PAGOS REALIZADOS --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">
                                2. Cumplimiento y Pagos Efectuados
                            </h4>
                            <span class="font-mono text-xs font-bold text-emerald-800"
                                  x-text="modalDetalle.cantidadPagos + (modalDetalle.cantidadPagos === 1 ? ' pago registrado' : ' pagos registrados')">
                            </span>
                        </div>

                        <template x-if="modalDetalle.cantidadPagos === 0">
                            <p class="mt-2 text-xs font-medium text-gray-500 italic">
                                Sin pagos registrados para las obligaciones de este periodo.
                            </p>
                        </template>

                        <template x-if="modalDetalle.cantidadPagos > 0">
                            <div class="mt-2.5 overflow-hidden rounded-lg border border-gray-200">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50 text-[10px] font-bold uppercase tracking-wider text-gray-500">
                                        <tr>
                                            <th class="px-3 py-2">Fecha de Pago</th>
                                            <th class="px-3 py-2 text-right">Monto Abonado</th>
                                            <th class="px-3 py-2">Medio de Pago</th>
                                            <th class="px-3 py-2">Tributo</th>
                                            <th class="px-3 py-2">Observación / N° OP</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="p in modalDetalle.pagos" :key="p.id || (p.fecha + p.monto)">
                                            <tr>
                                                <td class="px-3 py-2 font-mono font-bold text-gray-800" x-text="p.fecha"></td>
                                                <td class="px-3 py-2 text-right font-mono font-bold text-emerald-950" x-text="p.monto"></td>
                                                <td class="px-3 py-2 font-semibold text-gray-700" x-text="p.medio"></td>
                                                <td class="px-3 py-2 text-gray-600 font-medium" x-text="p.tributo_nombre || '—'"></td>
                                                <td class="px-3 py-2 text-gray-500" x-text="p.observacion || '—'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Pie del Modal con Acciones Rápidas --}}
                <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-5 py-3">
                    <a :href="'{{ route('control.index', ['anio' => $reporteImpuestos['anio']]) }}'"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-2xs hover:bg-gray-100 transition">
                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        Abrir en Control de Impuestos
                    </a>
                    <button type="button"
                            @click="cerrarModal()"
                            class="rounded-lg bg-gray-900 px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-gray-800 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- LEYENDA TRIBUTARIA INFERIOR --}}
    <p class="flex flex-wrap gap-x-4 gap-y-1.5 text-[10px] text-gray-500">
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-200 border border-emerald-300"></span> Pagado a tiempo</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-200 border border-amber-300"></span> Fuera de fecha o parcial</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-rose-200 border border-rose-300"></span> Debe vencido</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-sky-200 border border-sky-300"></span> En plazo</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-indigo-500"></span> IGV Justo (prórroga 3 meses)</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span> Transición RER a RMT</span>
    </p>
</div>
