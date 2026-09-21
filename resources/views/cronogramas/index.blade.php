@extends('layouts.app')

@section('title', "Cronograma de Vencimientos SUNAT {$anio}")
@section('header', 'Cronograma SUNAT')

@section('content')
    <div x-data="cronogramaSunat({
            anio: {{ $anio }},
            tipo: '{{ $tipo }}',
            csrfToken: '{{ csrf_token() }}'
         })"
         x-cloak
         class="space-y-6 max-w-7xl mx-auto">

        {{-- ════════════════════ TOAST FLOTANTE DE NOTIFICACIÓN ════════════════════ --}}
        <div x-show="toast.mostrar"
             x-transition:enter="transform ease-out duration-300 transition"
             x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
             x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-xl backdrop-blur"
             :class="toast.tipo === 'success' ? 'bg-emerald-900/90 text-white border-emerald-500/50' : 'bg-rose-900/90 text-white border-rose-500/50'">
            <div class="flex h-7 w-7 items-center justify-center rounded-full"
                 :class="toast.tipo === 'success' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'">
                <template x-if="toast.tipo === 'success'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </template>
                <template x-if="toast.tipo === 'error'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </template>
            </div>
            <div>
                <p class="text-xs font-bold" x-text="toast.titulo"></p>
                <p class="text-[11px] opacity-90" x-text="toast.mensaje"></p>
            </div>
        </div>

        {{-- ════════════════════ ENCABEZADO Y SELECTOR DE AÑO ════════════════════ --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 pb-5">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 border border-brand-200 text-brand-600 shadow-sm">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-3.75h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Cronograma Oficial SUNAT {{ $anio }}</h2>
                            <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                Base Tributaria Activa
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Base de datos centralizada de vencimientos mensuales, registros SIRE y balances según último dígito del RUC.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Botones de Acción y Selector de Ejercicio Fiscal --}}
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                {{-- Selector interactivo de Año --}}
                <div class="flex items-center gap-1.5 bg-white p-1 rounded-xl border border-gray-200 shadow-sm">
                    <a href="{{ route('cronogramas.index', ['anio' => $anio - 1, 'tipo' => $tipo]) }}"
                       title="Año {{ $anio - 1 }}"
                       class="inline-flex items-center justify-center rounded-lg p-1.5 text-gray-600 hover:bg-gray-100 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>

                    <select
                        onchange="window.location.href = '{{ route('cronogramas.index') }}?anio=' + this.value + '&tipo={{ $tipo }}'"
                        class="rounded-lg border-0 bg-transparent py-1 pl-2 pr-6 text-xs font-bold text-gray-800 focus:ring-0 cursor-pointer"
                    >
                        @foreach ($aniosDisponibles as $y)
                            <option value="{{ $y }}" {{ $y === $anio ? 'selected' : '' }}>
                                Ejercicio {{ $y }}
                            </option>
                        @endforeach
                    </select>

                    <a href="{{ route('cronogramas.index', ['anio' => $anio + 1, 'tipo' => $tipo]) }}"
                       title="Año {{ $anio + 1 }}"
                       class="inline-flex items-center justify-center rounded-lg p-1.5 text-gray-600 hover:bg-gray-100 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>

                {{-- Acciones Globales --}}
                @if ($tipo === 'sire')
                    <button type="button"
                            @click="autogenerarSire()"
                            :disabled="guardando"
                            class="inline-flex items-center gap-2 rounded-xl bg-purple-50 border border-purple-200 px-3.5 py-2 text-xs font-bold text-purple-700 hover:bg-purple-100 shadow-sm transition disabled:opacity-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                        </svg>
                        <span>⚡ Auto-generar desde PDT 621</span>
                    </button>
                @endif

                @if ($anio === 2026)
                    <button type="button"
                            @click="restablecerOficial()"
                            :disabled="guardando"
                            class="inline-flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-200 px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 shadow-sm transition disabled:opacity-50"
                            title="Restablece las fechas oficiales según Resolución SUNAT para 2026">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Restaurar Oficial 2026</span>
                    </button>
                @endif

                <button type="button"
                        @click="guardarCambios()"
                        :disabled="guardando"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white hover:bg-brand-700 shadow-sm transition disabled:opacity-50">
                    <svg x-show="!guardando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <svg x-show="guardando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="guardando ? 'Guardando...' : 'Guardar Cronograma'"></span>
                </button>
            </div>
        </div>

        {{-- ════════════════════ PESTAÑAS DE MODALIDAD / TIPO DE OBLIGACIÓN ════════════════════ --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-6 overflow-x-auto pb-1" aria-label="Tabs">
                <a href="{{ route('cronogramas.index', ['anio' => $anio, 'tipo' => 'pdt_621']) }}"
                   class="whitespace-nowrap flex items-center gap-2 border-b-2 py-3 px-1 text-sm font-semibold transition-colors
                          {{ $tipo === 'pdt_621' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>Obligaciones Mensuales (PDT 621 / IGV - Renta)</span>
                    <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-bold text-brand-700 border border-brand-200">
                        {{ $totales['pdt_621'] }} períodos
                    </span>
                </a>

                <a href="{{ route('cronogramas.index', ['anio' => $anio, 'tipo' => 'sire']) }}"
                   class="whitespace-nowrap flex items-center gap-2 border-b-2 py-3 px-1 text-sm font-semibold transition-colors
                          {{ $tipo === 'sire' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    <span>Registros Electrónicos (SIRE - RCE y RVIE)</span>
                    <span class="rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-bold text-purple-700 border border-purple-200">
                        {{ $totales['sire'] }} períodos
                    </span>
                </a>

                <a href="{{ route('cronogramas.index', ['anio' => $anio, 'tipo' => 'balance_mensual']) }}"
                   class="whitespace-nowrap flex items-center gap-2 border-b-2 py-3 px-1 text-sm font-semibold transition-colors
                          {{ $tipo === 'balance_mensual' ? 'border-amber-600 text-amber-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                    </svg>
                    <span>Balances Mensuales (Atraso Máximo 3 Meses)</span>
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700 border border-amber-200">
                        {{ $totales['balance_mensual'] }}
                    </span>
                </a>

                <a href="{{ route('cronogramas.index', ['anio' => $anio, 'tipo' => 'balance_anual']) }}"
                   class="whitespace-nowrap flex items-center gap-2 border-b-2 py-3 px-1 text-sm font-semibold transition-colors
                          {{ $tipo === 'balance_anual' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                    </svg>
                    <span>Balance Anual (DJ Anual Renta)</span>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                        Anual
                    </span>
                </a>
            </nav>
        </div>

        {{-- ════════════════════ BANNER INFORMATIVO CONTEXTUAL ════════════════════ --}}
        <div class="rounded-2xl border p-4 bg-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
             :class="{
                 'border-brand-200 bg-gradient-to-r from-brand-50/50 to-white': '{{ $tipo }}' === 'pdt_621',
                 'border-purple-200 bg-gradient-to-r from-purple-50/50 to-white': '{{ $tipo }}' === 'sire',
                 'border-amber-200 bg-gradient-to-r from-amber-50/50 to-white': '{{ $tipo }}' === 'balance_mensual',
                 'border-emerald-200 bg-gradient-to-r from-emerald-50/50 to-white': '{{ $tipo }}' === 'balance_anual'
             }">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 rounded-lg p-2 shrink-0"
                     :class="{
                         'bg-brand-100 text-brand-700': '{{ $tipo }}' === 'pdt_621',
                         'bg-purple-100 text-purple-700': '{{ $tipo }}' === 'sire',
                         'bg-amber-100 text-amber-700': '{{ $tipo }}' === 'balance_mensual',
                         'bg-emerald-100 text-emerald-700': '{{ $tipo }}' === 'balance_anual'
                     }">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">
                        @if ($tipo === 'pdt_621')
                            Cronograma Oficial de Obligaciones Tributarias Mensuales — SUNAT {{ $anio }}
                        @elseif ($tipo === 'sire')
                            Cronograma de Generación de Registros Electrónicos SIRE (RVIE y RCE) — SUNAT {{ $anio }}
                        @elseif ($tipo === 'balance_mensual')
                            Plazo Máximo de Atraso de Balances y Libros Contables (R.S. 234-2006/SUNAT)
                        @else
                            Declaración Jurada Anual del Impuesto a la Renta {{ $anio }}
                        @endif
                    </h3>
                    <p class="text-xs text-gray-600 mt-0.5">
                        @if ($tipo === 'pdt_621')
                            Vencimientos del IGV y Pagos a Cuenta de Renta (PDT 621 y PLAME). Cada contribuyente vence según el último dígito del RUC.
                        @elseif ($tipo === 'sire')
                            Por normativa oficial de SUNAT, la presentación de los registros electrónicos RVIE y RCE vence <strong>exactamente un (1) día hábil antes</strong> de la fecha del PDT 621.
                        @elseif ($tipo === 'balance_mensual')
                            El atraso máximo permitido por SUNAT para los libros y registros contables es de <strong>tres (3) meses</strong> siguientes al mes de las operaciones.
                        @else
                            Vencimiento general y por último dígito para la Declaración Anual de Impuesto a la Renta.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0 self-end md:self-center">
                <div class="rounded-xl border border-blue-200 bg-blue-50/80 px-3 py-1.5 text-xs text-blue-900 flex items-center gap-2">
                    <span class="flex h-2 w-2 rounded-full bg-blue-600 animate-pulse"></span>
                    <span>Período en curso de liquidación: <strong>{{ $periodoActivo }}</strong></span>
                </div>
            </div>
        </div>

        {{-- ════════════════════ MATRIZ INTERACTIVA EDITABLE ════════════════════ --}}
        <form id="formCronograma" @submit.prevent="guardarCambios">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-600">
                            <tr>
                                <th scope="col" class="py-3 px-4 sticky left-0 bg-gray-50 z-10 w-44">
                                    Período Tributario
                                </th>

                                @if ($tipo === 'balance_mensual')
                                    <th scope="col" class="py-3 px-4 text-center">
                                        Fecha Límite Legal (Atraso Máximo 3 Meses)
                                    </th>
                                    <th scope="col" class="py-3 px-4">
                                        Detalle de Obligación
                                    </th>
                                @else
                                    <th scope="col" class="py-3 px-3 text-center w-28 bg-gray-100/60">Dígito 0</th>
                                    <th scope="col" class="py-3 px-3 text-center w-28">Dígito 1</th>
                                    <th scope="col" class="py-3 px-3 text-center w-32 bg-gray-100/60">Dígitos 2 y 3</th>
                                    <th scope="col" class="py-3 px-3 text-center w-32">Dígitos 4 y 5</th>
                                    <th scope="col" class="py-3 px-3 text-center w-32 bg-gray-100/60">Dígitos 6 y 7</th>
                                    <th scope="col" class="py-3 px-3 text-center w-32">Dígitos 8 y 9</th>
                                    <th scope="col" class="py-3 px-4 text-center w-36 bg-emerald-50 text-emerald-800">
                                        Buenos Contrib. / UESP
                                    </th>
                                    @if ($tipo === 'balance_anual')
                                        <th scope="col" class="py-3 px-4 text-center">Vencimiento General</th>
                                    @endif
                                @endif
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 font-mono">
                            @forelse ($cronogramas as $index => $cronograma)
                                @php
                                    $esPeriodoActivo = ($cronograma->periodo === $periodoActivo);
                                @endphp
                                <tr class="transition-colors hover:bg-gray-50/80 {{ $esPeriodoActivo ? 'bg-blue-50/40 ring-1 ring-inset ring-blue-300' : '' }}">
                                    {{-- ID oculto --}}
                                    <input type="hidden" name="cronogramas[{{ $index }}][id]" value="{{ $cronograma->id }}">

                                    {{-- Columna Período --}}
                                    <td class="py-3 px-4 sticky left-0 z-10 font-sans {{ $esPeriodoActivo ? 'bg-blue-50/90 font-bold text-brand-900' : 'bg-white font-semibold text-gray-900' }}">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $cronograma->periodo }}</span>
                                            <span class="text-xs font-normal text-gray-500">({{ $cronograma->mes_nombre }})</span>
                                            @if ($esPeriodoActivo)
                                                <span class="inline-flex items-center rounded bg-blue-600 px-1.5 py-0.5 text-[9px] font-black uppercase text-white shadow-xs">
                                                    Activo
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    @if ($tipo === 'balance_mensual')
                                        {{-- Fecha Límite General para Balances Mensuales --}}
                                        <td class="py-2.5 px-4 text-center">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][fecha_vencimiento_general]"
                                                   value="{{ $cronograma->fecha_vencimiento_general ? $cronograma->fecha_vencimiento_general->format('Y-m-d') : '' }}"
                                                   class="w-44 rounded-lg border border-gray-300 py-1.5 px-2 text-center text-xs font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>
                                        <td class="py-2.5 px-4 font-sans text-xs text-gray-600">
                                            <input type="text"
                                                   name="cronogramas[{{ $index }}][descripcion]"
                                                   value="{{ $cronograma->descripcion }}"
                                                   placeholder="Descripción legal o nota..."
                                                   class="w-full rounded-lg border border-gray-200 py-1.5 px-3 text-xs text-gray-700 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>
                                    @else
                                        {{-- Dígito 0 --}}
                                        <td class="py-2.5 px-2 text-center bg-gray-50/50">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][digito_0]"
                                                   value="{{ $cronograma->digito_0 ? $cronograma->digito_0->format('Y-m-d') : '' }}"
                                                   class="w-full rounded-lg border border-gray-300 py-1 px-1.5 text-center text-[11px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>

                                        {{-- Dígito 1 --}}
                                        <td class="py-2.5 px-2 text-center">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][digito_1]"
                                                   value="{{ $cronograma->digito_1 ? $cronograma->digito_1->format('Y-m-d') : '' }}"
                                                   class="w-full rounded-lg border border-gray-300 py-1 px-1.5 text-center text-[11px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>

                                        {{-- Dígitos 2 y 3 --}}
                                        <td class="py-2.5 px-2 text-center bg-gray-50/50">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][digito_2_3]"
                                                   value="{{ $cronograma->digito_2_3 ? $cronograma->digito_2_3->format('Y-m-d') : '' }}"
                                                   class="w-full rounded-lg border border-gray-300 py-1 px-1.5 text-center text-[11px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>

                                        {{-- Dígitos 4 y 5 --}}
                                        <td class="py-2.5 px-2 text-center">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][digito_4_5]"
                                                   value="{{ $cronograma->digito_4_5 ? $cronograma->digito_4_5->format('Y-m-d') : '' }}"
                                                   class="w-full rounded-lg border border-gray-300 py-1 px-1.5 text-center text-[11px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>

                                        {{-- Dígitos 6 y 7 --}}
                                        <td class="py-2.5 px-2 text-center bg-gray-50/50">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][digito_6_7]"
                                                   value="{{ $cronograma->digito_6_7 ? $cronograma->digito_6_7->format('Y-m-d') : '' }}"
                                                   class="w-full rounded-lg border border-gray-300 py-1 px-1.5 text-center text-[11px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>

                                        {{-- Dígitos 8 y 9 --}}
                                        <td class="py-2.5 px-2 text-center">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][digito_8_9]"
                                                   value="{{ $cronograma->digito_8_9 ? $cronograma->digito_8_9->format('Y-m-d') : '' }}"
                                                   class="w-full rounded-lg border border-gray-300 py-1 px-1.5 text-center text-[11px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                        </td>

                                        {{-- Buenos Contribuyentes / UESP --}}
                                        <td class="py-2.5 px-2 text-center bg-emerald-50/40">
                                            <input type="date"
                                                   name="cronogramas[{{ $index }}][buenos_contribuyentes]"
                                                   value="{{ $cronograma->buenos_contribuyentes ? $cronograma->buenos_contribuyentes->format('Y-m-d') : '' }}"
                                                   class="w-full rounded-lg border border-emerald-300 py-1 px-1.5 text-center text-[11px] font-semibold text-emerald-900 bg-emerald-50/60 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                        </td>

                                        @if ($tipo === 'balance_anual')
                                            <td class="py-2.5 px-2 text-center">
                                                <input type="date"
                                                       name="cronogramas[{{ $index }}][fecha_vencimiento_general]"
                                                       value="{{ $cronograma->fecha_vencimiento_general ? $cronograma->fecha_vencimiento_general->format('Y-m-d') : '' }}"
                                                       class="w-full rounded-lg border border-gray-300 py-1 px-1.5 text-center text-[11px] font-medium text-gray-800 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                            </td>
                                        @endif
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-8 text-center text-gray-500">
                                        No hay vencimientos configurados para este ejercicio. Presione "Restaurar Oficial 2026" o guarde nuevos datos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Barra de Acciones Inferior --}}
                <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50/70 px-4 py-3 sm:px-6">
                    <p class="text-xs text-gray-500">
                        * Los cambios guardados actualizan automáticamente los semáforos y alertas del <strong>Dashboard</strong>, <strong>Control de Impuestos</strong> y <strong>Control de Balances</strong>.
                    </p>
                    <button type="submit"
                            :disabled="guardando"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white hover:bg-brand-700 shadow-sm transition disabled:opacity-50">
                        <svg x-show="!guardando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <span x-text="guardando ? 'Guardando...' : 'Guardar Todos los Cambios'"></span>
                    </button>
                </div>
            </div>
        </form>

        {{-- ════════════════════ ACCESOS DIRECTOS RELACIONADOS ════════════════════ --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <a href="{{ route('control.index', ['anio' => $anio]) }}"
               class="group flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-sm hover:border-brand-300 hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-brand-50 p-2.5 text-brand-600 group-hover:bg-brand-600 group-hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-gray-900 group-hover:text-brand-600 transition">Control de Impuestos {{ $anio }}</h4>
                        <p class="text-[11px] text-gray-500">Matriz mensual y estado de liquidaciones</p>
                    </div>
                </div>
                <svg class="h-4 w-4 text-gray-400 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </a>

            <a href="{{ route('balances.index', ['anio' => $anio]) }}"
               class="group flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-sm hover:border-amber-300 hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-gray-900 group-hover:text-amber-600 transition">Control de Balances {{ $anio }}</h4>
                        <p class="text-[11px] text-gray-500">Atraso de libros y balances anuales</p>
                    </div>
                </div>
                <svg class="h-4 w-4 text-gray-400 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </a>

            <a href="{{ route('dashboard') }}"
               class="group flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 shadow-sm hover:border-purple-300 hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-purple-50 p-2.5 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-gray-900 group-hover:text-purple-600 transition">Panel Dashboard</h4>
                        <p class="text-[11px] text-gray-500">Alertas y semáforo de vencimientos</p>
                    </div>
                </div>
                <svg class="h-4 w-4 text-gray-400 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </a>
        </div>
    </div>

    {{-- ════════════════════ COMPONENTE ALPINE.JS ════════════════════ --}}
    <script>
        function cronogramaSunat(config) {
            return {
                anio: config.anio,
                tipo: config.tipo,
                csrfToken: config.csrfToken,
                guardando: false,
                toast: {
                    mostrar: false,
                    tipo: 'success',
                    titulo: '',
                    mensaje: '',
                    timer: null
                },

                notificar(tipo, titulo, mensaje) {
                    if (this.toast.timer) clearTimeout(this.toast.timer);
                    this.toast.tipo = tipo;
                    this.toast.titulo = titulo;
                    this.toast.mensaje = mensaje;
                    this.toast.mostrar = true;
                    this.toast.timer = setTimeout(() => {
                        this.toast.mostrar = false;
                    }, 4000);
                },

                async guardarCambios() {
                    this.guardando = true;
                    const form = document.getElementById('formCronograma');
                    const formData = new FormData(form);

                    try {
                        const response = await fetch('{{ route('cronogramas.update') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json',
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.notificar('success', 'Guardado Exitoso', data.message || 'Cronograma actualizado correctamente.');
                        } else {
                            this.notificar('error', 'Error al Guardar', data.message || 'Verifique los datos ingresados.');
                        }
                    } catch (error) {
                        this.notificar('error', 'Error de Red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        this.guardando = false;
                    }
                },

                async autogenerarSire() {
                    if (!confirm('¿Desea auto-generar las fechas del cronograma SIRE para el año ' + this.anio + '?\nSe calculará 1 día hábil antes de cada fecha del PDT 621 correspondiente.')) {
                        return;
                    }

                    this.guardando = true;
                    try {
                        const response = await fetch('{{ route('cronogramas.autogenerar-sire') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ anio: this.anio })
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.notificar('success', 'SIRE Auto-generado', data.message);
                            setTimeout(() => window.location.reload(), 900);
                        } else {
                            this.notificar('error', 'Error', data.message || 'No se pudo generar el cronograma SIRE.');
                        }
                    } catch (error) {
                        this.notificar('error', 'Error', 'Ocurrió un fallo en la solicitud.');
                    } finally {
                        this.guardando = false;
                    }
                },

                async restablecerOficial() {
                    if (!confirm('¿Está seguro de restablecer el Cronograma Oficial SUNAT 2026?\nSe reescribirán las fechas oficiales de la Resolución de Superintendencia.')) {
                        return;
                    }

                    this.guardando = true;
                    try {
                        const response = await fetch('{{ route('cronogramas.restablecer') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json',
                            }
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.notificar('success', 'Cronograma Restablecido', data.message);
                            setTimeout(() => window.location.reload(), 900);
                        } else {
                            this.notificar('error', 'Error', data.message || 'No se pudo restablecer.');
                        }
                    } catch (error) {
                        this.notificar('error', 'Error', 'Ocurrió un fallo en la solicitud.');
                    } finally {
                        this.guardando = false;
                    }
                }
            }
        }
    </script>
@endsection
