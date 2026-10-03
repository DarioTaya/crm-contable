@extends('layouts.app')

@section('title', 'Tipo de Cambio SUNAT')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 border-b border-gray-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 text-brand-600 shadow-xs">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Tipo de cambio SUNAT</h2>
                        <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">USD/PEN</span>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Cotización de cierre de la SBS del día anterior, publicada por SUNAT. Si un día no hay publicación, se usa la del día inmediato anterior.
                    </p>
                </div>
            </div>
        </div>

        <section data-tipo-cambio
                 data-tc-url="{{ route('tipo-cambio.actual') }}"
                 data-compra="{{ $vigente['compra'] ?? '' }}"
                 data-venta="{{ $vigente['venta'] ?? '' }}"
                 class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div data-tc-lleno @unless ($vigente) hidden @endunless class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs lg:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tipo de cambio del día</p>
                <p data-tc-fecha class="mt-1 text-sm font-medium text-gray-900">{{ $vigente['fecha'] ?? '' }}</p>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Compra</p>
                        <p data-tc-compra class="mt-1 text-3xl font-bold tabular-nums tracking-tight text-gray-900">{{ $vigente['compra_texto'] ?? '' }}</p>
                        <p class="mt-1 text-[11px] text-gray-500">Soles por dólar que vendes</p>
                    </div>
                    <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-brand-700">Venta</p>
                        <p data-tc-venta class="mt-1 text-3xl font-bold tabular-nums tracking-tight text-brand-800">{{ $vigente['venta_texto'] ?? '' }}</p>
                        <p class="mt-1 text-[11px] text-brand-700/80">Soles por dólar que compras</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Diferencia</p>
                        <p data-tc-diferencia class="mt-1 text-3xl font-bold tabular-nums tracking-tight text-gray-900">{{ $vigente['diferencia_texto'] ?? '' }}</p>
                        <p class="mt-1 text-[11px] text-gray-500">Venta menos compra</p>
                    </div>
                </div>
            </div>

            <div data-tc-vacio @if ($vigente) hidden @endif class="flex flex-col justify-center rounded-xl border border-amber-200 bg-amber-50 p-5 lg:col-span-2">
                <h3 class="text-base font-semibold text-amber-950">SUNAT no publicó el tipo de cambio</h3>
                <p class="mt-1 text-sm text-amber-900">La consulta al archivo oficial no respondió. Vuelve a abrir esta página en unos minutos.</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Conversión</p>
                <label for="monto-dolares" class="mt-3 block text-sm font-medium text-gray-700">Dólares</label>
                <input id="monto-dolares" data-tc-monto type="number" min="0" step="0.01" value="100"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tabular-nums text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                <dl class="mt-4 space-y-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-gray-500">Si vendes esos dólares</dt>
                        <dd data-tc-recibes class="text-sm font-semibold tabular-nums text-gray-900">—</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-gray-500">Si compras esos dólares</dt>
                        <dd data-tc-pagas class="text-sm font-semibold tabular-nums text-brand-800">—</dd>
                    </div>
                </dl>
            </div>
        </section>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="flex flex-col gap-4 border-b border-gray-100 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-gray-900">Historial de {{ $nombreMes }}</h3>
                    @if ($esMesActual)
                        <p class="mt-0.5 text-xs text-gray-500">Este mes se completa solo, día a día, con cada publicación de SUNAT.</p>
                    @else
                        <p class="mt-0.5 max-w-sm text-xs text-gray-500">Este mes no se descarga solo. Sincroniza para copiar el tipo de cambio oficial.</p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5">
                        @if ($mesAnterior)
                            <a href="{{ route('tipo-cambio.index', ['anio' => $mesAnterior['anio'], 'mes' => $mesAnterior['mes']]) }}"
                               title="Mes anterior"
                               aria-label="Mes anterior"
                               class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-xs transition hover:bg-gray-50">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                </svg>
                            </a>
                        @else
                            <span aria-hidden="true" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-300">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                </svg>
                            </span>
                        @endif

                        @if ($mesSiguiente)
                            <a href="{{ route('tipo-cambio.index', ['anio' => $mesSiguiente['anio'], 'mes' => $mesSiguiente['mes']]) }}"
                               title="Mes siguiente"
                               aria-label="Mes siguiente"
                               class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-xs transition hover:bg-gray-50">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        @else
                            <span aria-hidden="true" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-300">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </span>
                        @endif

                        @unless ($esMesActual)
                            <a href="{{ route('tipo-cambio.index') }}"
                               class="inline-flex h-9 items-center rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                                Mes actual
                            </a>
                        @endunless
                    </div>

                    <form method="GET" action="{{ route('tipo-cambio.index') }}" class="flex flex-wrap items-center gap-2">
                        <label class="flex items-center gap-1.5">
                            <span class="text-xs font-medium leading-none text-gray-700">Mes</span>
                            <select name="mes" onchange="this.form.submit()"
                                    class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 text-sm font-medium text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                @foreach ($meses as $numero => $nombre)
                                    <option value="{{ $numero }}" @selected($numero === $mes)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-center gap-1.5">
                            <span class="text-xs font-medium leading-none text-gray-700">Año</span>
                            <select name="anio" onchange="this.form.submit()"
                                    class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 text-sm font-medium text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                @foreach ($anios as $opcion)
                                    <option value="{{ $opcion }}" @selected($opcion === $anio)>{{ $opcion }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                    <form method="POST" action="{{ route('tipo-cambio.sincronizar') }}" data-tc-sincronizar>
                        @csrf
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <input type="hidden" name="mes" value="{{ $mes }}">
                        <button type="submit"
                                class="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-80">
                            <svg data-tc-sincronizar-icono class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span data-tc-sincronizar-texto>Sincronizar mes</span>
                        </button>
                    </form>
                </div>
            </div>

            @if ($historial->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">
                    @if ($esMesActual)
                        SUNAT todavía no publicó cotizaciones de este mes.
                    @else
                        Este mes está vacío. Pulsa Sincronizar mes para copiar el tipo de cambio oficial.
                    @endif
                </p>
            @else
                <div class="overflow-x-auto p-3 sm:p-4">
                    <table class="w-full min-w-[640px] table-fixed border-collapse text-left">
                        <thead>
                            <tr>
                                @foreach ($diasSemana as $diaSemana)
                                    <th class="border border-gray-200 px-1 py-2 text-center text-xs font-medium text-gray-500">{{ $diaSemana }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($semanas as $semana)
                                <tr>
                                    @foreach ($semana as $celda)
                                        <td @class([
                                            'border border-gray-200 align-top p-1.5',
                                            'bg-gray-100' => $celda['hoy'],
                                            'bg-white' => ! $celda['hoy'],
                                        ])>
                                            <p @class([
                                                'text-xs tabular-nums',
                                                'font-semibold text-gray-800' => $celda['del_mes'],
                                                'text-gray-300' => ! $celda['del_mes'],
                                            ])>{{ $celda['dia'] }}</p>
                                            @if ($celda['compra'] && $celda['venta'])
                                                <p class="mt-1.5 whitespace-nowrap rounded-md bg-emerald-100 px-1 py-1 text-center text-[11px] font-semibold text-emerald-800">Compra {{ $celda['compra'] }}</p>
                                                <p class="mt-1 whitespace-nowrap rounded-md bg-sky-100 px-1 py-1 text-center text-[11px] font-semibold text-sky-800">Venta {{ $celda['venta'] }}</p>
                                            @elseif ($celda['del_mes'])
                                                <p class="invisible mt-1.5 whitespace-nowrap rounded-md px-1 py-1 text-center text-[11px] font-semibold" aria-hidden="true" data-reserva-dia>&nbsp;</p>
                                                <p class="invisible mt-1 whitespace-nowrap rounded-md px-1 py-1 text-center text-[11px] font-semibold" aria-hidden="true">&nbsp;</p>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-xs" data-tc-grafico data-hoy="{{ $hoyIso }}">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-gray-900">Comportamiento del Dólar</h3>
                    <p data-tc-rango class="mt-0.5 text-xs text-gray-500">Compra y venta publicadas por SUNAT.</p>
                </div>
                <div class="inline-flex shrink-0 rounded-lg border border-gray-200 bg-gray-50 p-0.5" role="tablist" aria-label="Periodo del gráfico">
                    @foreach (['1m' => '1M', '3m' => '3M', '6m' => '6M', '1a' => '1A'] as $clave => $etiqueta)
                        <button type="button" role="tab" data-periodo="{{ $clave }}" aria-pressed="{{ $clave === '1m' ? 'true' : 'false' }}"
                                @class([
                                    'rounded-md px-3 py-1.5 text-xs font-bold transition hover:text-gray-800',
                                    'bg-white text-gray-900 shadow-xs' => $clave === '1m',
                                    'text-gray-500' => $clave !== '1m',
                                ])>{{ $etiqueta }}</button>
                    @endforeach
                </div>
            </div>

            <div data-tc-resumen class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700">Compra</p>
                    <p class="mt-0.5 flex items-baseline gap-2">
                        <span data-tc-compra-cierre class="text-lg font-bold tabular-nums text-emerald-900">—</span>
                        <span data-tc-compra-delta class="text-xs font-semibold tabular-nums text-emerald-700"></span>
                    </p>
                </div>
                <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-sky-700">Venta</p>
                    <p class="mt-0.5 flex items-baseline gap-2">
                        <span data-tc-venta-cierre class="text-lg font-bold tabular-nums text-sky-900">—</span>
                        <span data-tc-venta-delta class="text-xs font-semibold tabular-nums text-sky-700"></span>
                    </p>
                </div>
            </div>

            <p data-tc-nota hidden class="mt-3 text-xs text-gray-500"></p>
            <p data-tc-vacio-grafico hidden class="mt-6 text-center text-sm text-gray-500">No hay cotizaciones guardadas en este periodo. Sincroniza los meses desde el historial.</p>

            <div data-tc-lienzo class="relative mt-4 h-72">
                <canvas id="tipoCambioChart" aria-label="Evolución del tipo de cambio de compra y venta"></canvas>
            </div>
            <script type="application/json" id="tipo-cambio-serie">@json($serie)</script>
        </section>
    </div>

    <script>
        (function () {
            const formulario = document.querySelector('[data-tc-sincronizar]');
            if (!formulario) {
                return;
            }
            formulario.addEventListener('submit', function () {
                const boton = formulario.querySelector('button');
                const icono = formulario.querySelector('[data-tc-sincronizar-icono]');
                const texto = formulario.querySelector('[data-tc-sincronizar-texto]');
                if (!boton || boton.disabled) {
                    return;
                }
                boton.disabled = true;
                boton.setAttribute('aria-busy', 'true');
                if (icono) {
                    icono.classList.add('animate-spin');
                }
                if (texto) {
                    texto.textContent = 'Sincronizando...';
                }
            });
        })();

        (function () {
            const raiz = document.querySelector('[data-tipo-cambio]');
            const monto = document.querySelector('[data-tc-monto]');
            if (!raiz || !monto) {
                return;
            }

            const recibes = document.querySelector('[data-tc-recibes]');
            const pagas = document.querySelector('[data-tc-pagas]');
            const soles = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

            const convertir = function () {
                const compra = parseFloat(raiz.dataset.compra);
                const venta = parseFloat(raiz.dataset.venta);
                const cantidad = parseFloat(monto.value);

                if (!Number.isFinite(compra) || !Number.isFinite(venta) || !Number.isFinite(cantidad)) {
                    if (recibes) recibes.textContent = '—';
                    if (pagas) pagas.textContent = '—';
                    return;
                }

                if (recibes) recibes.textContent = soles.format(cantidad * compra);
                if (pagas) pagas.textContent = soles.format(cantidad * venta);
            };

            const aplicar = function (dato) {
                if (!dato || !dato.disponible) {
                    return;
                }

                raiz.dataset.compra = dato.compra_valor;
                raiz.dataset.venta = dato.venta_valor;
                const fecha = raiz.querySelector('[data-tc-fecha]');
                const compra = raiz.querySelector('[data-tc-compra]');
                const venta = raiz.querySelector('[data-tc-venta]');
                const diferencia = raiz.querySelector('[data-tc-diferencia]');
                const lleno = raiz.querySelector('[data-tc-lleno]');
                const vacio = raiz.querySelector('[data-tc-vacio]');
                if (fecha) fecha.textContent = dato.fecha;
                if (compra) compra.textContent = dato.compra;
                if (venta) venta.textContent = dato.venta;
                if (diferencia) diferencia.textContent = dato.diferencia;
                if (lleno) lleno.hidden = false;
                if (vacio) vacio.hidden = true;
                convertir();
            };

            monto.addEventListener('input', convertir);
            convertir();

            const consultar = function () {
                if (document.hidden) {
                    return;
                }

                window.fetch(raiz.dataset.tcUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (respuesta) {
                    return respuesta.ok ? respuesta.json() : null;
                }).then(function (dato) {
                    if (dato) {
                        aplicar(dato);
                    }
                }).catch(function () {});
            };

            window.setInterval(consultar, 60000);
        })();

        (function () {
            const raiz = document.querySelector('[data-tc-grafico]');
            const serieNodo = document.getElementById('tipo-cambio-serie');
            const lienzo = document.getElementById('tipoCambioChart');
            if (!raiz || !serieNodo || !lienzo) {
                return;
            }

            const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
            const mesesCortos = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'set', 'oct', 'nov', 'dic'];
            const periodos = { '1m': 1, '3m': 3, '6m': 6, '1a': 12 };
            const puntos = JSON.parse(serieNodo.textContent).map(function (fila) {
                const partes = fila.fecha.split('-').map(Number);
                return {
                    t: Date.UTC(partes[0], partes[1] - 1, partes[2]),
                    compra: fila.compra,
                    venta: fila.venta,
                };
            });
            const hoyPartes = raiz.dataset.hoy.split('-').map(Number);
            const hoy = Date.UTC(hoyPartes[0], hoyPartes[1] - 1, hoyPartes[2]);
            const rango = raiz.querySelector('[data-tc-rango]');
            const nota = raiz.querySelector('[data-tc-nota]');
            const vacio = raiz.querySelector('[data-tc-vacio-grafico]');
            const caja = raiz.querySelector('[data-tc-lienzo]');
            const resumen = raiz.querySelector('[data-tc-resumen]');
            const compraCierre = raiz.querySelector('[data-tc-compra-cierre]');
            const ventaCierre = raiz.querySelector('[data-tc-venta-cierre]');
            const compraDelta = raiz.querySelector('[data-tc-compra-delta]');
            const ventaDelta = raiz.querySelector('[data-tc-venta-delta]');
            const botones = raiz.querySelectorAll('[data-periodo]');
            let grafico = null;
            let marcasX = [];
            let marcasY = [];

            const redondear = function (valor) {
                return Math.round(valor * 1000) / 1000;
            };

            const ejeVertical = function (minimo, maximo) {
                const rango = Math.max(maximo - minimo, 0.01);
                const alto = Math.max(180, (caja && caja.clientHeight) || 288);
                const cupo = Math.max(4, Math.min(8, Math.floor(alto / 40)));
                const pasos = [0.005, 0.01, 0.02, 0.025, 0.05, 0.1, 0.2, 0.25, 0.5];
                let paso = pasos[pasos.length - 1];
                for (let indice = 0; indice < pasos.length; indice++) {
                    if ((rango / pasos[indice]) + 2 <= cupo) {
                        paso = pasos[indice];
                        break;
                    }
                }
                let yMin = redondear(Math.floor(minimo / paso) * paso);
                let yMax = redondear(Math.ceil(maximo / paso) * paso);
                if (minimo - yMin < paso * 0.35) {
                    yMin = redondear(yMin - paso);
                }
                if (yMax - maximo < paso * 0.35) {
                    yMax = redondear(yMax + paso);
                }
                const marcas = [];
                for (let valor = yMin; valor <= yMax + paso * 0.001; valor = redondear(valor + paso)) {
                    marcas.push(valor);
                }
                return {
                    min: marcas[0],
                    max: marcas[marcas.length - 1],
                    marcas: marcas,
                };
            };

            const ejeHorizontal = function (desde, hasta) {
                const ancho = Math.max(320, (caja && caja.clientWidth) || 720);
                const cupo = Math.max(5, Math.min(14, Math.floor((ancho - 48) / 64)));
                const dias = Math.max(1, Math.round((hasta - desde) / 86400000));
                const ideal = Math.max(1, Math.ceil(dias / (cupo - 1)));
                const grilla = [1, 2, 3, 4, 5, 7, 10, 14, 15, 21, 30, 45, 60, 90];
                let paso = grilla[grilla.length - 1];
                for (let indice = 0; indice < grilla.length; indice++) {
                    if (grilla[indice] >= ideal) {
                        paso = grilla[indice];
                        break;
                    }
                }
                const marcas = [desde];
                const salto = paso * 86400000;
                let cursor = desde + salto;
                while (cursor < hasta - salto * 0.55) {
                    marcas.push(cursor);
                    cursor += salto;
                }
                marcas.push(hasta);
                return marcas;
            };

            const fechaUtc = function (marca) {
                return new Date(marca);
            };

            const restarMeses = function (marca, cantidad) {
                const fecha = fechaUtc(marca);
                fecha.setUTCMonth(fecha.getUTCMonth() - cantidad);
                return Date.UTC(fecha.getUTCFullYear(), fecha.getUTCMonth(), fecha.getUTCDate());
            };

            const textoFecha = function (marca) {
                const fecha = fechaUtc(marca);
                return fecha.getUTCDate() + ' de ' + meses[fecha.getUTCMonth()] + ' de ' + fecha.getUTCFullYear();
            };

            const textoCorto = function (marca) {
                const fecha = fechaUtc(marca);
                return fecha.getUTCDate() + ' ' + mesesCortos[fecha.getUTCMonth()];
            };

            const tasa = function (valor) {
                return Number(valor).toFixed(3);
            };

            const variacion = function (cierre, inicio) {
                const delta = cierre - inicio;
                return (delta > 0 ? '+' : '') + delta.toFixed(3);
            };

            const recortar = function (visibles, clave) {
                const datos = [];
                visibles.forEach(function (punto, indice) {
                    if (indice > 0 && punto.t - visibles[indice - 1].t > 36 * 60 * 60 * 1000) {
                        datos.push({ x: visibles[indice - 1].t + 86400000, y: null });
                    }
                    datos.push({ x: punto.t, y: punto[clave] });
                });
                return datos;
            };

            const pintar = function (clave) {
                const desde = restarMeses(hoy, periodos[clave]);
                const visibles = puntos.filter(function (punto) {
                    return punto.t >= desde && punto.t <= hoy;
                });

                botones.forEach(function (boton) {
                    const activo = boton.dataset.periodo === clave;
                    boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
                    boton.classList.toggle('bg-white', activo);
                    boton.classList.toggle('text-gray-900', activo);
                    boton.classList.toggle('shadow-xs', activo);
                    boton.classList.toggle('text-gray-500', !activo);
                });

                if (rango) {
                    rango.textContent = textoFecha(desde) + ' — ' + textoFecha(hoy);
                }

                if (visibles.length === 0) {
                    if (resumen) resumen.hidden = true;
                    if (caja) caja.hidden = true;
                    if (nota) nota.hidden = true;
                    if (vacio) vacio.hidden = false;
                    return;
                }

                if (resumen) resumen.hidden = false;
                if (caja) caja.hidden = false;
                if (vacio) vacio.hidden = true;

                const primero = visibles[0];
                const ultimo = visibles[visibles.length - 1];
                if (compraCierre) compraCierre.textContent = tasa(ultimo.compra);
                if (ventaCierre) ventaCierre.textContent = tasa(ultimo.venta);
                if (compraDelta) compraDelta.textContent = variacion(ultimo.compra, primero.compra);
                if (ventaDelta) ventaDelta.textContent = variacion(ultimo.venta, primero.venta);

                if (nota) {
                    const incompleto = primero.t > desde + 2 * 86400000;
                    nota.hidden = !incompleto;
                    nota.textContent = incompleto
                        ? 'Hay cotizaciones desde el ' + textoFecha(primero.t) + '. Sincroniza los meses anteriores para completar este periodo.'
                        : '';
                }

                const valores = [];
                visibles.forEach(function (punto) {
                    valores.push(punto.compra, punto.venta);
                });
                const radio = visibles.length === 1 ? 3 : 0;
                const minimo = Math.min.apply(null, valores);
                const maximo = Math.max.apply(null, valores);
                const vertical = ejeVertical(minimo, maximo);
                marcasY = vertical.marcas;
                marcasX = ejeHorizontal(desde, hoy);

                const datos = {
                    datasets: [
                        {
                            label: 'Compra',
                            data: recortar(visibles, 'compra'),
                            borderColor: '#059669',
                            backgroundColor: '#059669',
                            fill: false,
                            borderWidth: 2,
                            pointRadius: radio,
                            pointHoverRadius: 4,
                            pointHoverBackgroundColor: '#059669',
                            tension: 0,
                            spanGaps: false,
                        },
                        {
                            label: 'Venta',
                            data: recortar(visibles, 'venta'),
                            borderColor: '#0284c7',
                            backgroundColor: '#0284c7',
                            fill: false,
                            borderWidth: 2,
                            pointRadius: radio,
                            pointHoverRadius: 4,
                            pointHoverBackgroundColor: '#0284c7',
                            tension: 0,
                            spanGaps: false,
                        },
                    ],
                };

                const opciones = {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#111827',
                            titleFont: { size: 12 },
                            bodyFont: { size: 12 },
                            padding: 10,
                            callbacks: {
                                title: function (items) {
                                    return textoFecha(items[0].parsed.x);
                                },
                                label: function (item) {
                                    if (item.parsed.y == null) {
                                        return null;
                                    }
                                    return ' ' + item.dataset.label + ' ' + tasa(item.parsed.y);
                                },
                            },
                        },
                    },
                    layout: { padding: { left: 4, right: 8, top: 8, bottom: 0 } },
                    scales: {
                        x: {
                            type: 'linear',
                            min: desde,
                            max: hoy,
                            offset: false,
                            grid: { display: false },
                            border: { display: false },
                            afterBuildTicks: function (escala) {
                                escala.ticks = marcasX.map(function (valor) {
                                    return { value: valor };
                                });
                            },
                            ticks: {
                                autoSkip: false,
                                source: 'data',
                                maxRotation: 0,
                                minRotation: 0,
                                color: '#6b7280',
                                font: { size: 11 },
                                callback: function (valor) {
                                    return textoCorto(valor);
                                },
                            },
                        },
                        y: {
                            min: vertical.min,
                            max: vertical.max,
                            grid: { color: '#f3f4f6' },
                            border: { display: false },
                            afterBuildTicks: function (escala) {
                                escala.ticks = marcasY.map(function (valor) {
                                    return { value: valor };
                                });
                            },
                            ticks: {
                                autoSkip: false,
                                source: 'data',
                                padding: 8,
                                color: '#6b7280',
                                font: { size: 11 },
                                callback: function (valor) {
                                    return tasa(valor);
                                },
                            },
                        },
                    },
                };

                if (!grafico) {
                    grafico = new window.Chart(lienzo, { type: 'line', data: datos, options: opciones });
                    return;
                }

                grafico.data.datasets[0].data = datos.datasets[0].data;
                grafico.data.datasets[1].data = datos.datasets[1].data;
                grafico.data.datasets[0].pointRadius = radio;
                grafico.data.datasets[1].pointRadius = radio;
                grafico.options.scales.x.min = desde;
                grafico.options.scales.x.max = hoy;
                grafico.options.scales.y.min = vertical.min;
                grafico.options.scales.y.max = vertical.max;
                grafico.update();
            };

            const arrancar = function () {
                if (!window.Chart || lienzo.dataset.rendered === '1') {
                    return;
                }
                lienzo.dataset.rendered = '1';
                botones.forEach(function (boton) {
                    boton.addEventListener('click', function () {
                        pintar(boton.dataset.periodo);
                    });
                });
                pintar('1m');
            };

            if (window.Chart) {
                arrancar();
            } else {
                document.addEventListener('crm:chart-ready', arrancar, { once: true });
            }
        })();
    </script>
@endsection
