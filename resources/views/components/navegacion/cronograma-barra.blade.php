@props(['cronograma' => []])

@php
    $anio = (int) ($cronograma['anio'] ?? now()->year);
    $bloques = [
        'pdt_621' => $cronograma['pdt_621'] ?? collect(),
        'sire' => $cronograma['sire'] ?? collect(),
    ];
    $periodoActivo = [
        'pdt_621' => (string) ($cronograma['periodo_pdt'] ?? ''),
        'sire' => (string) ($cronograma['periodo_sire'] ?? ''),
    ];
    $meses = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];
    $fechaSunat = function ($fecha) use ($meses): string {
        if (! $fecha) {
            return '—';
        }

        $mes = (int) $fecha->format('n');

        return $fecha->format('j').' '.($meses[$mes] ?? '');
    };
    $columnas = [
        'digito_0' => '0',
        'digito_1' => '1',
        'digito_2_3' => '2 y 3',
        'digito_4_5' => '4 y 5',
        'digito_6_7' => '6 y 7',
        'digito_8_9' => '8 y 9',
    ];
@endphp

<div
    x-data="{
        abierto: false,
        tipo: 'pdt_621',
        alternar() {
            this.abierto = !this.abierto;
            if (this.abierto) {
                this.$dispatch('topbar-panel', { panel: 'cronograma' });
                this.$nextTick(() => this.ubicar());
            }
        },
        elegir(tipo) {
            this.tipo = tipo;
            this.$nextTick(() => this.ubicar());
        },
        ubicar() {
            if (window.ubicarMenuFlotante && this.$refs.panel && this.$refs.boton) {
                window.ubicarMenuFlotante(this.$refs.panel, this.$refs.boton);
            }
        }
    }"
    class="relative shrink-0"
    @keydown.escape.window="abierto = false"
    @topbar-panel.window="if ($event.detail.panel !== 'cronograma') abierto = false"
>
    <button
        type="button"
        x-ref="boton"
        data-cronograma-boton
        @click="alternar()"
        :class="abierto ? 'bg-gray-100 text-brand-700 ring-2 ring-brand-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'"
        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200/80 bg-white shadow-2xs transition cursor-pointer"
        title="Cronograma SUNAT"
        aria-label="Cronograma SUNAT"
        :aria-expanded="abierto ? 'true' : 'false'"
    >
        <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
        </svg>
    </button>

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="abierto"
            x-cloak
            data-menu-flotante
            data-abrir="abajo"
            data-separacion="8"
            @click.outside="if (!$event.target.closest('[data-cronograma-boton]') && !$el._clicInicioDentro) abierto = false"
            role="dialog"
            aria-label="Cronograma SUNAT {{ $anio }}"
            class="fixed z-50 w-[min(34rem,calc(100vw-2rem))] overflow-auto rounded-xl border border-slate-200 bg-white text-slate-800 shadow-2xl"
            style="display: none;"
        >
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-3 py-2">
                <div class="min-w-0">
                    <p class="text-[13px] font-semibold leading-none text-slate-900">Cronograma SUNAT {{ $anio }}</p>
                    <p class="mt-1 text-[10px] leading-none text-slate-500" x-show="tipo === 'pdt_621'">PDT 621 · IGV y Renta mensual</p>
                    <p class="mt-1 text-[10px] leading-none text-slate-500" x-show="tipo === 'sire'" x-cloak>SIRE · RVIE y RCE</p>
                </div>
                <div class="flex shrink-0 rounded-lg bg-slate-100 p-0.5" role="tablist" aria-label="Tipo de cronograma">
                    <button
                        type="button"
                        role="tab"
                        @click="elegir('pdt_621')"
                        :aria-selected="tipo === 'pdt_621' ? 'true' : 'false'"
                        :class="tipo === 'pdt_621' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                        class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition cursor-pointer"
                    >PDT 621</button>
                    <button
                        type="button"
                        role="tab"
                        @click="elegir('sire')"
                        :aria-selected="tipo === 'sire' ? 'true' : 'false'"
                        :class="tipo === 'sire' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
                        class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition cursor-pointer"
                    >SIRE</button>
                </div>
            </div>

            @foreach ($bloques as $clave => $filas)
                <div x-show="tipo === '{{ $clave }}'" @if ($clave !== 'pdt_621') x-cloak @endif>
                    <table class="w-full border-collapse text-[11px] leading-none">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-slate-800 text-white">
                                <th rowspan="2" scope="col" class="border-r border-slate-700 px-2 py-1.5 text-left text-[10px] font-semibold">Período</th>
                                <th colspan="6" scope="colgroup" class="border-b border-slate-700 px-1 py-1.5 text-center text-[10px] font-semibold tracking-wide">Último dígito del RUC</th>
                                <th rowspan="2" scope="col" class="border-l border-slate-700 px-1.5 py-1.5 text-center text-[10px] font-semibold leading-tight">Buenos<br>contribuyentes</th>
                            </tr>
                            <tr class="bg-slate-700 text-white">
                                @foreach ($columnas as $etiqueta)
                                    <th scope="col" class="px-1 py-1 text-center text-[10px] font-medium">{{ $etiqueta }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                @php
                                    $esActivo = $fila->periodo === $periodoActivo[$clave];
                                @endphp
                                <tr @class([
                                    'border-t border-slate-200',
                                    'bg-brand-50' => $esActivo,
                                    'odd:bg-white even:bg-slate-50' => ! $esActivo,
                                ]) @if ($esActivo) title="Período en liquidación" @endif>
                                    <th scope="row" @class([
                                        'whitespace-nowrap border-r border-slate-200 px-2 py-1.5 text-left font-semibold',
                                        'text-brand-800' => $esActivo,
                                        'text-slate-800' => ! $esActivo,
                                    ])>{{ $fila->nombre_mes }}</th>
                                    @foreach (array_keys($columnas) as $campo)
                                        <td class="px-1 py-1.5 text-center tabular-nums text-slate-700">{{ $fechaSunat($fila->{$campo}) }}</td>
                                    @endforeach
                                    <td class="border-l border-slate-200 px-1 py-1.5 text-center font-medium tabular-nums text-slate-800">{{ $fechaSunat($fila->buenos_contribuyentes) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-8 text-center text-xs text-slate-400">Sin fechas cargadas para este ejercicio.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach

            <div class="flex shrink-0 items-center justify-end border-t border-slate-200 px-3 py-1.5">
                <a
                    :href="tipo === 'sire' ? @js(route('cronogramas.index', ['anio' => $anio, 'tipo' => 'sire'])) : @js(route('cronogramas.index', ['anio' => $anio, 'tipo' => 'pdt_621']))"
                    class="text-[10px] font-medium text-slate-400 hover:text-brand-700"
                >Abrir ficha</a>
            </div>
        </div>
    </template>
</div>
