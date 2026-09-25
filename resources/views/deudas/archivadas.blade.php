@extends('layouts.app')

@section('title', 'Historial archivado — Control de Deudas')
@section('header', 'Control de Deudas')

@section('content')
<div class="container mx-auto" x-data="historialArchivado()">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('control_deudas.index') }}"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 transition hover:text-gray-800">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Deudas pendientes
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-2.5">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Historial archivado</h2>
                <span class="inline-flex items-center gap-1 rounded-md border border-violet-200 bg-violet-50 px-2.5 py-0.5 text-xs font-black text-violet-800">
                    <span class="h-1.5 w-1.5 rounded-full bg-violet-600"></span>
                    Canceladas
                </span>
                <span class="rounded-full border border-gray-200 bg-gray-100 px-2.5 py-0.5 text-[11px] font-bold text-gray-600">
                    {{ $totalArchivadas }} {{ $totalArchivadas === 1 ? 'tributo archivado' : 'tributos archivados' }}
                </span>
            </div>
            <p class="mt-1 max-w-3xl text-xs text-gray-500">
                Mismos tributos, vencimientos y pagos de Control de Deudas. Cuando el saldo llega a cero, el tributo sale del tablero de pendientes y queda aquí, ordenado por ejercicio.
            </p>
        </div>
    </div>

    @if ($totalArchivadas === 0)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
            <p class="text-sm font-bold text-gray-900">Todavía no hay deudas archivadas</p>
            <p class="mx-auto mt-1 max-w-md text-xs text-gray-500">
                Cuando una empresa termine de pagar un tributo, desaparece de Control de Deudas y queda guardada en este historial.
            </p>
        </div>
    @else
        <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div class="rounded-2xl border border-violet-200 bg-white p-4 shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-violet-700">Tributos del filtro</p>
                <p class="mt-1 font-mono text-2xl font-black tracking-tight text-gray-900" x-text="resumen().cantidad">{{ $resumenInicial['cantidad'] }}</p>
                <p class="mt-1 text-[11px] text-gray-500" x-text="textoConteo(resumen().cantidad)">
                    {{ $resumenInicial['cantidad'] === 1 ? 'tributo cancelado' : 'tributos cancelados' }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Deuda original</p>
                <p class="mt-1 font-mono text-2xl font-black tracking-tight text-gray-900" x-text="formato(resumen().monto)">S/ {{ number_format($resumenInicial['monto'], 2) }}</p>
                <p class="mt-1 text-[11px] text-gray-500">Importe registrado en SUNAT</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Total pagado</p>
                <p class="mt-1 font-mono text-2xl font-black tracking-tight text-emerald-700" x-text="formato(resumen().pagado)">S/ {{ number_format($resumenInicial['pagado'], 2) }}</p>
                <p class="mt-1 text-[11px] text-emerald-600">Suma de los abonos aplicados</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Clientes</p>
                <p class="mt-1 font-mono text-2xl font-black tracking-tight text-gray-900" x-text="resumen().clientes">{{ $resumenInicial['clientes'] }}</p>
                <p class="mt-1 text-[11px] text-gray-500">Con tributos cancelados en el filtro</p>
            </div>
        </div>

        <div class="mb-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-12">
                <label class="block xl:col-span-2">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Ejercicio</span>
                    <select x-model="anio"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-800 shadow-xs focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20">
                        <option value="todos">Todos los ejercicios</option>
                        @foreach ($anios as $anio)
                            <option value="{{ $anio }}">{{ (int) $anio === 0 ? 'Sin periodo' : $anio }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block xl:col-span-2">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Mes</span>
                    <select x-model="mes"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-800 shadow-xs focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20">
                        <option value="">Todos los meses</option>
                        @foreach ($mesesOpciones as $mesOpcion)
                            <option value="{{ $mesOpcion['numero'] }}">{{ $mesOpcion['nombre'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block xl:col-span-3">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Cliente</span>
                    <select x-model="cliente"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-800 shadow-xs focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20">
                        <option value="">Todos los clientes</option>
                        @foreach ($clientesOpciones as $clienteOpcion)
                            <option value="{{ $clienteOpcion['id'] }}">{{ $clienteOpcion['codigo'] }} — {{ $clienteOpcion['razon_social'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block xl:col-span-3">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Tributo</span>
                    <select x-model="tributo"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-800 shadow-xs focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20">
                        <option value="">Todos los tributos</option>
                        @foreach ($tributosOpciones as $tributoOpcion)
                            <option value="{{ $tributoOpcion['codigo'] }}">{{ $tributoOpcion['codigo'] === 'sin-codigo' ? $tributoOpcion['nombre'] : $tributoOpcion['codigo'].' — '.$tributoOpcion['nombre'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block xl:col-span-2">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Buscar</span>
                    <span class="relative block">
                        <input type="search"
                               x-model="busqueda"
                               placeholder="Empresa, RUC, periodo o tributo"
                               class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-8 pr-3 text-xs font-semibold text-gray-800 shadow-xs placeholder:font-medium placeholder:text-gray-400 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20">
                        <svg class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
                <p class="text-[11px] text-gray-500">
                    Mostrando <span class="font-bold text-gray-800" x-text="resumen().cantidad">{{ $resumenInicial['cantidad'] }}</span>
                    de {{ $totalArchivadas }} {{ $totalArchivadas === 1 ? 'tributo archivado' : 'tributos archivados' }}.
                    Los ejercicios van del más reciente al más antiguo.
                </p>
                <button type="button"
                        x-show="hayFiltro()"
                        x-cloak
                        @click="limpiar()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-bold text-gray-700 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-800">
                    Limpiar filtros
                </button>
            </div>
        </div>

        <div x-show="resumen().cantidad === 0" x-cloak class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
            <p class="text-sm font-bold text-gray-900">Ningún tributo coincide con los filtros</p>
            <p class="mx-auto mt-1 max-w-md text-xs text-gray-500">Prueba otro ejercicio, mes, cliente o tributo. El historial completo sigue guardado.</p>
            <button type="button"
                    @click="limpiar()"
                    class="mt-4 inline-flex items-center rounded-xl bg-violet-600 px-3.5 py-2 text-xs font-bold text-white transition hover:bg-violet-700">
                Limpiar filtros
            </button>
        </div>

        <div class="space-y-6">
            @foreach ($historial as $anio => $meses)
                @php
                    $cantidadAnio = collect($meses)->sum(fn (array $mes): int => count($mes['items']));
                    $pagadoAnio = collect($meses)->sum(fn (array $mes): float => (float) $mes['pagado']);
                @endphp
                <div data-anio-bloque
                     x-show="tieneCoincidencias($el)"
                     class="space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-2 px-1">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-violet-700">Ejercicio</p>
                            <h3 class="text-lg font-black tracking-tight text-gray-900">
                                {{ (int) $anio === 0 ? 'Sin periodo' : $anio }}
                            </h3>
                        </div>
                        <p class="text-xs font-semibold text-gray-500" x-text="textoBloque($el.closest('[data-anio-bloque]'))">
                            {{ $cantidadAnio }} {{ $cantidadAnio === 1 ? 'tributo' : 'tributos' }} · S/ {{ number_format($pagadoAnio, 2) }}
                        </p>
                    </div>

                    @foreach ($meses as $mes)
                        <section data-mes-bloque
                                 x-show="tieneCoincidencias($el)"
                                 class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50/80 px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-violet-600 px-1.5 text-[11px] font-black text-white">
                                        {{ $mes['numero'] > 0 ? str_pad((string) $mes['numero'], 2, '0', STR_PAD_LEFT) : '—' }}
                                    </span>
                                    <div>
                                        <h4 class="text-sm font-black text-gray-900">{{ $mes['nombre'] }} {{ (int) $anio === 0 ? '' : $anio }}</h4>
                                        <p class="text-[11px] text-gray-500" x-text="textoCantidadBloque($el.closest('[data-mes-bloque]'))">
                                            {{ count($mes['items']) }} {{ count($mes['items']) === 1 ? 'tributo cancelado' : 'tributos cancelados' }}
                                        </p>
                                    </div>
                                </div>
                                <p class="font-mono text-sm font-black text-violet-800" x-text="formato(resumenDe($el.closest('[data-mes-bloque]')).pagado)">
                                    S/ {{ number_format($mes['pagado'], 2) }}
                                </p>
                            </div>

                            <div class="divide-y divide-gray-100">
                                @foreach ($mes['items'] as $item)
                                    <article data-fila
                                             data-anio="{{ $item['anio'] }}"
                                             data-mes="{{ $item['mes'] }}"
                                             data-cliente="{{ $item['cliente_id'] ?? '' }}"
                                             data-tributo="{{ $item['tributo_codigo'] !== '' ? $item['tributo_codigo'] : 'sin-codigo' }}"
                                             data-monto="{{ number_format($item['monto'], 2, '.', '') }}"
                                             data-pagado="{{ number_format($item['pagado'], 2, '.', '') }}"
                                             data-buscar="{{ $item['buscar'] }}"
                                             :hidden="!coincide($el)"
                                             class="px-4 py-3.5 transition-colors hover:bg-violet-50/30">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-md border border-gray-300 bg-gray-100 px-1.5 font-mono text-xs font-black text-gray-700">
                                                        {{ $item['codigo'] }}
                                                    </span>
                                                    @if ($item['cliente_url'])
                                                        <a href="{{ $item['cliente_url'] }}" class="font-bold text-gray-900 transition hover:text-brand-600">
                                                            {{ $item['cliente'] }}
                                                        </a>
                                                    @else
                                                        <span class="font-bold text-gray-900">{{ $item['cliente'] }}</span>
                                                    @endif
                                                    <span class="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-800">
                                                        {{ $item['periodo'] !== '' ? $item['periodo'] : 'Sin periodo' }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 font-mono text-[11px] text-gray-400">
                                                    RUC: {{ $item['ruc'] !== '' ? $item['ruc'] : '—' }}
                                                </p>
                                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                                                        @if ($item['tributo_codigo'] !== '')
                                                            <span class="font-mono">{{ $item['tributo_codigo'] }} -</span>
                                                        @endif
                                                        <span>{{ $item['tributo'] }}</span>
                                                        @if ($item['es_igv_justo'])
                                                            <span class="rounded bg-indigo-200/80 px-1 py-0.5 text-[8px] font-black uppercase text-indigo-900">IGV Justo</span>
                                                        @endif
                                                    </span>
                                                    <span class="text-[11px] text-gray-500">{{ $item['tributo_nombre'] }}</span>
                                                </div>
                                            </div>
                                            <button type="button"
                                                    @click="alternar('{{ $item['clave'] }}')"
                                                    class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-gray-700 shadow-xs transition hover:bg-gray-50"
                                                    :class="abierto === '{{ $item['clave'] }}' ? 'border-violet-500 bg-violet-50 text-violet-800' : ''">
                                                <svg class="h-3.5 w-3.5 text-gray-400 transition-transform" :class="abierto === '{{ $item['clave'] }}' ? 'rotate-180 text-violet-600' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                                Ver pagos
                                                <span class="rounded-full bg-gray-100 px-1.5 py-0.5 font-mono text-[9px] font-black text-gray-600">{{ count($item['abonos']) }}</span>
                                            </button>
                                        </div>

                                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-5">
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Vencimiento</dt>
                                                <dd class="mt-0.5 font-mono text-xs font-semibold text-gray-800">{{ $item['vencimiento'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Deuda original</dt>
                                                <dd class="mt-0.5 font-mono text-xs font-semibold text-gray-800">{{ $item['monto_formateado'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total abonado</dt>
                                                <dd class="mt-0.5 font-mono text-xs font-bold text-emerald-600">{{ $item['pagado_formateado'] }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Saldo</dt>
                                                <dd class="mt-0.5 font-mono text-xs font-black text-emerald-700">
                                                    {{ $item['saldo_formateado'] }}
                                                    <span class="ml-1 align-middle text-[10px] font-bold uppercase tracking-wide text-emerald-600">Cancelada</span>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Cancelado el</dt>
                                                <dd class="mt-0.5 font-mono text-xs font-semibold text-gray-800">{{ $item['ultimo_pago'] }}</dd>
                                            </div>
                                        </dl>

                                        <div x-show="abierto === '{{ $item['clave'] }}'" x-cloak class="mt-3 rounded-xl border border-gray-200 bg-slate-50 px-4 py-3">
                                            <div class="flex items-center gap-2 border-b border-gray-200/80 pb-3">
                                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-bold text-gray-900">
                                                        Historial de pagos — {{ $item['cliente'] }}
                                                        <span class="font-mono text-[11px] font-semibold text-gray-400">({{ $item['periodo'] !== '' ? $item['periodo'] : 'sin periodo' }} · {{ $item['tributo'] }})</span>
                                                    </p>
                                                    <p class="text-[10px] text-gray-500">Los mismos abonos registrados en Control de Deudas. Desde aquí solo se consultan.</p>
                                                </div>
                                            </div>

                                            @if (count($item['abonos']) === 0)
                                                <p class="mt-3 rounded-xl border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-xs font-semibold text-gray-500">
                                                    El tributo figura cancelado, sin detalle de cuotas.
                                                </p>
                                            @else
                                                <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 bg-white">
                                                    <table class="w-full text-left text-xs">
                                                        <thead>
                                                            <tr class="border-b border-gray-200 bg-gray-100/80 text-[10px] font-bold uppercase tracking-wider text-gray-600">
                                                                <th class="w-12 px-3 py-2 text-center">#</th>
                                                                <th class="px-3 py-2">Fecha de pago</th>
                                                                <th class="px-3 py-2 text-right">Monto abonado</th>
                                                                <th class="px-4 py-2">Observación / referencia</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100">
                                                            @foreach ($item['abonos'] as $indice => $abono)
                                                                <tr>
                                                                    <td class="px-3 py-2.5 text-center font-mono text-[10px] text-gray-400">{{ $indice + 1 }}</td>
                                                                    <td class="px-3 py-2.5 font-mono font-semibold text-gray-800">{{ $abono['fecha'] }}</td>
                                                                    <td class="px-3 py-2.5 text-right font-mono font-bold text-emerald-600">{{ $abono['monto_formateado'] }}</td>
                                                                    <td class="px-4 py-2.5 {{ $abono['observacion'] === '' ? 'italic text-gray-400' : 'text-gray-600' }}">
                                                                        {{ $abono['observacion'] !== '' ? $abono['observacion'] : 'Sin observación especificada' }}
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 text-xs">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600">Total del mes</p>
                                <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                                    <p class="text-gray-500">Deuda original <span class="ml-1 font-mono font-bold text-gray-900" x-text="formato(resumenDe($el.closest('[data-mes-bloque]')).monto)">S/ {{ number_format($mes['total'], 2) }}</span></p>
                                    <p class="text-gray-500">Total abonado <span class="ml-1 font-mono font-black text-emerald-700" x-text="formato(resumenDe($el.closest('[data-mes-bloque]')).pagado)">S/ {{ number_format($mes['pagado'], 2) }}</span></p>
                                    <p class="text-gray-500">Saldo <span class="ml-1 font-mono font-black text-emerald-700">S/ 0.00</span></p>
                                </div>
                            </div>
                        </section>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
    function historialArchivado() {
        return {
            anio: 'todos',
            mes: '',
            cliente: '',
            tributo: '',
            busqueda: '',
            abierto: null,
            coincide(fila) {
                if (!fila || !fila.dataset) {
                    return false;
                }
                if (this.anio !== 'todos' && fila.dataset.anio !== String(this.anio)) {
                    return false;
                }
                if (this.mes !== '' && fila.dataset.mes !== String(this.mes)) {
                    return false;
                }
                if (this.cliente !== '' && fila.dataset.cliente !== String(this.cliente)) {
                    return false;
                }
                if (this.tributo !== '' && fila.dataset.tributo !== String(this.tributo)) {
                    return false;
                }
                const consulta = this.busqueda.trim().toLowerCase();
                if (consulta !== '' && !(fila.dataset.buscar || '').includes(consulta)) {
                    return false;
                }

                return true;
            },
            tieneCoincidencias(bloque) {
                return [...bloque.querySelectorAll('[data-fila]')].some((fila) => this.coincide(fila));
            },
            resumenDe(contenedor) {
                const filas = [...contenedor.querySelectorAll('[data-fila]')].filter((fila) => this.coincide(fila));

                return {
                    cantidad: filas.length,
                    monto: filas.reduce((suma, fila) => suma + Number(fila.dataset.monto || 0), 0),
                    pagado: filas.reduce((suma, fila) => suma + Number(fila.dataset.pagado || 0), 0),
                    clientes: new Set(filas.map((fila) => fila.dataset.cliente).filter((id) => id !== '')).size,
                };
            },
            resumen() {
                return this.resumenDe(this.$root);
            },
            formato(valor) {
                return 'S/ ' + Number(valor || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },
            textoConteo(cantidad) {
                return cantidad === 1 ? '1 tributo cancelado' : cantidad + ' tributos cancelados';
            },
            textoBloque(bloque) {
                const resumen = this.resumenDe(bloque);

                return this.textoConteo(resumen.cantidad) + ' · ' + this.formato(resumen.pagado);
            },
            textoCantidadBloque(bloque) {
                return this.textoConteo(this.resumenDe(bloque).cantidad);
            },
            hayFiltro() {
                return this.anio !== 'todos'
                    || this.mes !== ''
                    || this.cliente !== ''
                    || this.tributo !== ''
                    || this.busqueda.trim() !== '';
            },
            limpiar() {
                this.anio = 'todos';
                this.mes = '';
                this.cliente = '';
                this.tributo = '';
                this.busqueda = '';
                this.abierto = null;
            },
            alternar(clave) {
                this.abierto = this.abierto === clave ? null : clave;
            },
        };
    }
</script>
@endsection
