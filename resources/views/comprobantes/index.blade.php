@extends('layouts.app')

@section('title', 'Bóveda de Comprobantes SIRE')
@section('header', 'Bóveda de Comprobantes SIRE')

@section('content')
<div x-data="bovedaSireIndex({
    importarUrl: '{{ route('comprobantes.importar') }}',
    syncStatusUrl: '{{ route('comprobantes.sync-status') }}',
    sincronizarLoteUrl: '{{ route('comprobantes.sincronizar-lote') }}',
    reintentarUrl: '{{ url('/comprobantes') }}',
    initialState: {
        @foreach ($comprobantes as $comp)
        {{ $comp->id }}: {
            id: {{ $comp->id }},
            estado_descarga: '{{ $comp->estado_descarga ?: ($comp->esta_completamente_disponible ? 'disponible' : 'pendiente') }}',
            estado_xml: '{{ $comp->tiene_xml ? 'disponible' : ($comp->estado_xml ?: 'pendiente') }}',
            estado_cdr: '{{ $comp->tiene_cdr ? 'disponible' : ($comp->estado_cdr ?: 'pendiente') }}',
            estado_pdf: '{{ $comp->tiene_pdf ? 'disponible' : ($comp->estado_pdf ?: 'pendiente') }}',
            tiene_xml: {{ $comp->tiene_xml ? 'true' : 'false' }},
            tiene_cdr: {{ $comp->tiene_cdr ? 'true' : 'false' }},
            tiene_pdf: {{ $comp->tiene_pdf ? 'true' : 'false' }},
            url_xml: '{{ route('comprobantes.download', [$comp, 'xml']) }}',
            url_cdr: '{{ route('comprobantes.download', [$comp, 'cdr']) }}',
            url_pdf: '{{ route('comprobantes.download', [$comp, 'pdf']) }}',
            mensaje_soap: '{{ addslashes($comp->mensaje_soap ?? '') }}'
        },
        @endforeach
    }
})" class="space-y-6">

    {{-- ════════════════════ TOAST FLOTANTE DE NOTIFICACIÓN ════════════════════ --}}
    <div x-show="toast.mostrar"
         x-cloak
         x-transition:enter="transform ease-out duration-300 transition"
         x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
         x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-xl backdrop-blur max-w-md"
         :class="{
             'bg-emerald-900/90 text-white border-emerald-500/50': toast.tipo === 'success',
             'bg-rose-900/90 text-white border-rose-500/50': toast.tipo === 'error',
             'bg-sky-900/95 text-white border-sky-500/50': toast.tipo === 'info'
         }">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
             :class="{
                 'bg-emerald-500/20 text-emerald-300': toast.tipo === 'success',
                 'bg-rose-500/20 text-rose-300': toast.tipo === 'error',
                 'bg-sky-500/20 text-sky-300': toast.tipo === 'info'
             }">
            <template x-if="toast.tipo === 'info'">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
            </template>
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

    {{-- ════════════════════ ENCABEZADO Y ACCIONES PRINCIPALES ════════════════════ --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Bóveda SIRE</h1>
            <p class="text-xs text-gray-500 mt-1">Gestión documental centralizada de comprobantes electrónicos (RCE Compras y RVIE Ventas).</p>
        </div>

        {{-- BOTONERA DE ACCIONES --}}
        <div class="flex flex-wrap items-center gap-2.5">
            {{-- Botón Importar desde SUNAT --}}
            <button
                type="button"
                @click="importarDesdeSunat"
                :disabled="importando"
                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed transition"
            >
                <template x-if="!importando">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </template>
                <template x-if="importando">
                    <svg class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <span x-text="importando ? 'Importando desde SUNAT...' : '⬇️ Importar desde SUNAT'"></span>
            </button>

            {{-- Botón Sincronizar SOAP --}}
            <button
                type="button"
                @click="sincronizarPendientes"
                :disabled="sincronizandoSoap"
                class="inline-flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-50 px-3.5 py-2 text-xs font-semibold text-purple-800 shadow-xs hover:bg-purple-100 hover:border-purple-400 active:scale-95 transition disabled:opacity-60 disabled:cursor-not-allowed"
                title="Sincronizar comprobantes pendientes mediante servicio SOAP SUNAT"
            >
                <template x-if="!sincronizandoSoap">
                    <svg class="h-3.5 w-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </template>
                <template x-if="sincronizandoSoap">
                    <svg class="h-3.5 w-3.5 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <span x-text="sincronizandoSoap ? 'Sincronizando...' : '🔄 Sincronizar SOAP'"></span>
            </button>

            {{-- Botón Descargar Paquete ZIP --}}
            <a
                href="{{ route('comprobantes.zip', request()->query()) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 hover:border-gray-400 active:scale-95 transition"
                title="Descargar paquete ZIP con archivos y manifiesto"
            >
                <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
                <span>📦 Descargar Paquete ZIP</span>
            </a>

            {{-- Botón Exportar Excel / CSV --}}
            <a
                href="{{ route('comprobantes.export', request()->query()) }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-xs font-semibold text-emerald-800 shadow-xs hover:bg-emerald-100 hover:border-emerald-400 active:scale-95 transition"
                title="Exportar registros filtrados a formato Excel compatible"
            >
                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125" />
                </svg>
                <span>📊 Exportar Excel</span>
            </a>
        </div>
    </div>

    {{-- ════════════════════ GRID DE FILTROS COMBINADOS ════════════════════ --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <form method="GET" action="{{ route('comprobantes.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
            {{-- Filtro: Cliente --}}
            <div class="space-y-1">
                <label for="cliente_id" class="block text-xs font-semibold text-gray-700">Cliente</label>
                <select name="cliente_id" id="cliente_id" class="w-full rounded-lg border-gray-300 py-2 px-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Todos los Clientes</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c->id }}" {{ request('cliente_id') == $c->id ? 'selected' : '' }}>
                            N° {{ $c->numero }} - {{ $c->razon_social }} ({{ $c->ruc }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtro: Tipo de Registro --}}
            <div class="space-y-1">
                <label for="tipo_registro" class="block text-xs font-semibold text-gray-700">Tipo de Registro</label>
                <select name="tipo_registro" id="tipo_registro" class="w-full rounded-lg border-gray-300 py-2 px-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Todos los Registros</option>
                    <option value="RCE" {{ request('tipo_registro') === 'RCE' ? 'selected' : '' }}>RCE - Registro de Compras</option>
                    <option value="RVIE" {{ request('tipo_registro') === 'RVIE' ? 'selected' : '' }}>RVIE - Registro de Ventas</option>
                </select>
            </div>

            {{-- Filtro: Periodo (Input Month) --}}
            <div class="space-y-1">
                <label for="periodo" class="block text-xs font-semibold text-gray-700">Periodo Mensual</label>
                <input
                    type="month"
                    name="periodo"
                    id="periodo"
                    value="{{ request('periodo') }}"
                    class="w-full rounded-lg border-gray-300 py-1.5 px-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500"
                />
            </div>

            {{-- Filtro: Búsqueda Serie / RUC / Tercero --}}
            <div class="space-y-1">
                <label for="buscar" class="block text-xs font-semibold text-gray-700">Buscar Tercero / Serie</label>
                <div class="relative">
                    <input
                        type="text"
                        name="buscar"
                        id="buscar"
                        placeholder="Serie, RUC o Razón Social..."
                        value="{{ request('buscar') }}"
                        class="w-full rounded-lg border-gray-300 py-1.5 pl-8 pr-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500"
                    />
                    <svg class="h-3.5 w-3.5 absolute left-2.5 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
            </div>

            {{-- Botones de Acción de Filtros --}}
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 inline-flex justify-center items-center gap-1 rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20 active:scale-95 transition"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <span>Filtrar</span>
                </button>

                @if(request()->hasAny(['cliente_id', 'tipo_registro', 'periodo', 'buscar']))
                    <a
                        href="{{ route('comprobantes.index') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-xs font-semibold text-gray-600 shadow-xs hover:bg-gray-50 hover:text-gray-900 active:scale-95 transition"
                        title="Limpiar todos los filtros"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ════════════════════ TRES TARJETAS RESUMEN ════════════════════ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- Tarjeta 1: Total Base Imponible --}}
        <div class="relative overflow-hidden rounded-xl border border-blue-100 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Total Base Imponible</p>
                    <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($totalBaseImponible, 2) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11px] text-gray-500">
                <span class="inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                <span>Subtotal neto gravado según filtros</span>
            </div>
        </div>

        {{-- Tarjeta 2: Total IGV --}}
        <div class="relative overflow-hidden rounded-xl border border-emerald-100 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Total IGV</p>
                    <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($totalIgv, 2) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5 3a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-[11px] text-gray-500">
                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                <span>Impuesto General a las Ventas (18%)</span>
            </div>
        </div>

        {{-- Tarjeta 3: Total Facturado --}}
        <div class="relative overflow-hidden rounded-xl border border-purple-100 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-600">Total Facturado</p>
                    <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($totalFacturado, 2) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0-8.25a60.106 60.106 0 0 1 15.797-2.101c.727-.198 1.453.342 1.453 1.096V6m-17.25 0h17.25m-17.25 0v1.5m0 0h17.25m-17.25 0v9m17.25-9v9m0 0H2.25m17.25 0a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25m17.25 0v1.5" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-[11px] text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="inline-flex h-2 w-2 rounded-full bg-purple-500"></span>
                    <span>Importe total bruto</span>
                </span>
                <span class="font-semibold text-gray-700">{{ $totalRegistros }} {{ Str::plural('comprobante', $totalRegistros) }}</span>
            </div>
        </div>
    </div>

    {{-- ════════════════════ TABLA DE COMPROBANTES ════════════════════ --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/80 uppercase tracking-wider text-gray-500 font-semibold">
                    <tr>
                        <th scope="col" class="px-3.5 py-3 text-center">Periodo</th>
                        <th scope="col" class="px-3 py-3 text-center">Registro</th>
                        <th scope="col" class="px-4 py-3">Comprobante</th>
                        <th scope="col" class="px-4 py-3">Cliente</th>
                        <th scope="col" class="px-4 py-3">Tercero (Emisor/Receptor)</th>
                        <th scope="col" class="px-3 py-3 text-right">Base Imp.</th>
                        <th scope="col" class="px-3 py-3 text-right">IGV</th>
                        <th scope="col" class="px-3.5 py-3 text-right">Total</th>
                        <th scope="col" class="px-3 py-3 text-center">Estado SIRE</th>
                        <th scope="col" class="px-4 py-3 text-center">Archivos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($comprobantes as $comp)
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            {{-- Periodo --}}
                            <td class="whitespace-nowrap px-3.5 py-3 text-center font-mono font-medium text-gray-700">
                                {{ $comp->periodo }}
                            </td>

                            {{-- Tipo de Registro (RCE / RVIE) --}}
                            <td class="whitespace-nowrap px-3 py-3 text-center">
                                @if ($comp->tipo_registro === 'RCE')
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200">
                                        RCE Compras
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                        RVIE Ventas
                                    </span>
                                @endif
                            </td>

                            {{-- Comprobante (Tipo + Serie-Número) --}}
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $comp->descripcion_tipo_comprobante }}</div>
                                <div class="font-mono text-xs text-gray-500">{{ $comp->numero_comprobante }}</div>
                            </td>

                            {{-- Cliente del Estudio --}}
                            <td class="px-4 py-3">
                                @if ($comp->cliente)
                                    <a href="{{ route('clientes.show', ['cliente' => $comp->cliente_id, 'tab' => 'sire']) }}" class="font-medium text-brand-600 hover:text-brand-800 hover:underline">
                                        {{ $comp->cliente->razon_social }}
                                    </a>
                                    <div class="font-mono text-[11px] text-gray-400">RUC: {{ $comp->cliente->ruc }}</div>
                                @else
                                    <span class="text-gray-400 italic">Sin Cliente</span>
                                @endif
                            </td>

                            {{-- Tercero (RUC + Razón Social) --}}
                            <td class="px-4 py-3 max-w-xs truncate">
                                <div class="font-medium text-gray-900 truncate" title="{{ $comp->razon_social_tercero }}">
                                    {{ $comp->razon_social_tercero ?: 'Consumidor Final / Anónimo' }}
                                </div>
                                <div class="font-mono text-[11px] text-gray-500">
                                    {{ $comp->ruc_tercero ?: '-' }}
                                </div>
                            </td>

                            {{-- Base Imponible --}}
                            <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-gray-700">
                                {{ number_format((float) $comp->base_imponible, 2) }}
                            </td>

                            {{-- IGV --}}
                            <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-gray-700">
                                {{ number_format((float) $comp->igv, 2) }}
                            </td>

                            {{-- Total --}}
                            <td class="whitespace-nowrap px-3.5 py-3 text-right font-mono font-bold text-gray-900">
                                <span class="text-[10px] font-normal text-gray-400">{{ $comp->moneda }}</span>
                                {{ number_format((float) $comp->total, 2) }}
                            </td>

                            {{-- Estado SIRE --}}
                            <td class="whitespace-nowrap px-3 py-3 text-center">
                                @if ($comp->estado_sire === 'Aceptado')
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        ✓ Aceptado
                                    </span>
                                @elseif ($comp->estado_sire === 'Rechazado')
                                    <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                        ✗ Rechazado
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                        ● Propuesta
                                    </span>
                                @endif
                            </td>

                            {{-- Columna de Archivos con micro-botones reactivos --}}
                            <td class="whitespace-nowrap px-4 py-3 text-center" x-data="{ state: comprobantesState[{{ $comp->id }}] || {} }">
                                <div class="inline-flex items-center gap-1.5">
                                    {{-- Micro-botón XML --}}
                                    <template x-if="state.estado_xml === 'disponible' || state.tiene_xml">
                                        <a :href="state.url_xml"
                                           class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition shadow-xs"
                                           title="Descargar archivo XML oficial (UBL 2.1)">
                                            XML
                                        </a>
                                    </template>
                                    <template x-if="state.estado_xml === 'descargando' && !state.tiene_xml">
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-blue-700 bg-blue-50 border border-blue-200 shadow-xs"
                                              title="Descargando XML desde SUNAT...">
                                            <svg class="h-2.5 w-2.5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            XML
                                        </span>
                                    </template>
                                    <template x-if="state.estado_xml === 'pendiente' && !state.tiene_xml">
                                        <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-gray-500 bg-gray-100 border border-gray-200 shadow-xs cursor-not-allowed"
                                              title="En cola para descarga">
                                            XML
                                        </span>
                                    </template>
                                    <template x-if="state.estado_xml === 'error' && !state.tiene_xml">
                                        <button type="button" @click="reintentar({{ $comp->id }})"
                                                class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 hover:bg-rose-200 border border-rose-300 transition shadow-xs"
                                                :title="state.mensaje_soap || 'Error en descarga. Clic para reintentar.'">
                                            XML ⚠
                                        </button>
                                    </template>

                                    {{-- Micro-botón CDR --}}
                                    <template x-if="state.estado_cdr === 'disponible' || state.tiene_cdr">
                                        <a :href="state.url_cdr"
                                           class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition shadow-xs"
                                           title="Descargar Constancia de Recepción CDR (ZIP)">
                                            CDR
                                        </a>
                                    </template>
                                    <template x-if="state.estado_cdr === 'descargando' && !state.tiene_cdr">
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-blue-700 bg-blue-50 border border-blue-200 shadow-xs"
                                              title="Descargando CDR desde SUNAT...">
                                            <svg class="h-2.5 w-2.5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            CDR
                                        </span>
                                    </template>
                                    <template x-if="state.estado_cdr === 'pendiente' && !state.tiene_cdr">
                                        <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-gray-500 bg-gray-100 border border-gray-200 shadow-xs cursor-not-allowed"
                                              title="En cola para descarga">
                                            CDR
                                        </span>
                                    </template>
                                    <template x-if="state.estado_cdr === 'error' && !state.tiene_cdr">
                                        <button type="button" @click="reintentar({{ $comp->id }})"
                                                class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 hover:bg-rose-200 border border-rose-300 transition shadow-xs"
                                                :title="state.mensaje_soap || 'Error en descarga. Clic para reintentar.'">
                                            CDR ⚠
                                        </button>
                                    </template>

                                    {{-- Micro-botón PDF --}}
                                    <template x-if="state.estado_pdf === 'disponible' || state.tiene_pdf">
                                        <a :href="state.url_pdf"
                                           class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition shadow-xs"
                                           title="Descargar representación impresa en PDF">
                                            PDF
                                        </a>
                                    </template>
                                    <template x-if="state.estado_pdf === 'descargando' && !state.tiene_pdf">
                                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-blue-700 bg-blue-50 border border-blue-200 shadow-xs"
                                              title="Generando PDF...">
                                            <svg class="h-2.5 w-2.5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            PDF
                                        </span>
                                    </template>
                                    <template x-if="state.estado_pdf === 'pendiente' && !state.tiene_pdf">
                                        <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-semibold text-gray-500 bg-gray-100 border border-gray-200 shadow-xs cursor-not-allowed"
                                              title="En cola">
                                            PDF
                                        </span>
                                    </template>
                                    <template x-if="state.estado_pdf === 'error' && !state.tiene_pdf">
                                        <button type="button" @click="reintentar({{ $comp->id }})"
                                                class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 hover:bg-rose-200 border border-rose-300 transition shadow-xs"
                                                :title="state.mensaje_soap || 'Error al generar PDF. Clic para reintentar.'">
                                            PDF ⚠
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900">No se encontraron comprobantes</p>
                                    <p class="text-xs text-gray-500 max-w-sm">No hay comprobantes registrados que coincidan con los filtros seleccionados. Intenta modificando el periodo, el cliente o el tipo de registro.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($comprobantes->hasPages())
            <div class="border-t border-gray-200 bg-white px-4 py-3">
                {{ $comprobantes->links() }}
            </div>
        @endif
    </div>

</div>

<script>
    function bovedaSireIndex(config) {
        return {
            comprobantesState: config.initialState || {},
            pollingInterval: null,
            sincronizandoSoap: false,
            toast: {
                mostrar: false,
                titulo: '',
                mensaje: '',
                tipo: 'info'
            },
            importando: false,
            init() {
                this.verificarYIniciarPolling();
            },
            verificarYIniciarPolling() {
                const hayPendientes = Object.values(this.comprobantesState).some(item =>
                    item.estado_descarga === 'pendiente' ||
                    item.estado_descarga === 'descargando' ||
                    (!item.tiene_xml && item.estado_xml !== 'disponible')
                );
                if (hayPendientes && !this.pollingInterval) {
                    this.pollingInterval = setInterval(() => this.consultarEstadoSondeo(), 3000);
                }
            },
            async consultarEstadoSondeo() {
                const idsPendientes = Object.values(this.comprobantesState)
                    .filter(item =>
                        item.estado_descarga === 'pendiente' ||
                        item.estado_descarga === 'descargando' ||
                        (!item.tiene_xml && item.estado_xml !== 'disponible')
                    )
                    .map(item => item.id);

                if (idsPendientes.length === 0) {
                    if (this.pollingInterval) {
                        clearInterval(this.pollingInterval);
                        this.pollingInterval = null;
                    }
                    return;
                }

                try {
                    const response = await fetch(`${config.syncStatusUrl}?ids=${idsPendientes.join(',')}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.success && Array.isArray(data.comprobantes)) {
                        data.comprobantes.forEach(c => {
                            if (this.comprobantesState[c.id]) {
                                this.comprobantesState[c.id] = { ...this.comprobantesState[c.id], ...c };
                            }
                        });

                        // Si ya no queda ninguno pendiente, detener polling
                        const quedanPendientes = Object.values(this.comprobantesState).some(item =>
                            item.estado_descarga === 'pendiente' ||
                            item.estado_descarga === 'descargando'
                        );
                        if (!quedanPendientes && this.pollingInterval) {
                            clearInterval(this.pollingInterval);
                            this.pollingInterval = null;
                        }
                    }
                } catch (e) {
                    console.warn('Error en sondeo reactivo de comprobantes:', e);
                }
            },
            async reintentar(id) {
                if (!id) return;
                if (this.comprobantesState[id]) {
                    this.comprobantesState[id].estado_descarga = 'descargando';
                    this.comprobantesState[id].estado_xml = 'descargando';
                    this.comprobantesState[id].estado_cdr = 'descargando';
                    this.comprobantesState[id].estado_pdf = 'descargando';
                }
                this.verificarYIniciarPolling();
                try {
                    const tokenEl = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';
                    const response = await fetch(`${config.reintentarUrl}/${id}/reintentar-descarga`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json();
                    if (res.success) {
                        this.lanzarToast('Descarga Encolada', res.message, 'info');
                    }
                } catch (err) {
                    console.error('Error al reintentar descarga:', err);
                }
            },
            async sincronizarPendientes() {
                if (this.sincronizandoSoap) return;
                this.sincronizandoSoap = true;
                try {
                    const tokenEl = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';
                    const ids = Object.keys(this.comprobantesState).map(Number);
                    const response = await fetch(config.sincronizarLoteUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ ids: ids })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.lanzarToast('Sincronización Iniciada', data.message, 'success');
                        ids.forEach(id => {
                            if (this.comprobantesState[id] && !this.comprobantesState[id].tiene_xml) {
                                this.comprobantesState[id].estado_descarga = 'pendiente';
                            }
                        });
                        this.verificarYIniciarPolling();
                    }
                } catch (err) {
                    this.lanzarToast('Aviso SOAP', 'No se pudo iniciar la sincronización en lote.', 'error');
                } finally {
                    this.sincronizandoSoap = false;
                }
            },
            lanzarToast(titulo, mensaje, tipo = 'info') {
                this.toast.titulo = titulo;
                this.toast.mensaje = mensaje;
                this.toast.tipo = tipo;
                this.toast.mostrar = true;
                setTimeout(() => {
                    this.toast.mostrar = false;
                }, 5000);
            },
            async importarDesdeSunat() {
                if (this.importando) return;

                const clienteSelect = document.getElementById('cliente_id');
                const registroSelect = document.getElementById('tipo_registro');
                const periodoInput = document.getElementById('periodo');

                const clienteId = clienteSelect ? clienteSelect.value : '';
                const registro = (registroSelect && registroSelect.value) ? registroSelect.value : '';
                let periodo = periodoInput ? periodoInput.value : '';

                if (!clienteId) {
                    this.lanzarToast('Seleccione un Cliente', 'Debe seleccionar un cliente específico en el filtro para importar sus comprobantes desde SUNAT.', 'info');
                    if (clienteSelect) clienteSelect.focus();
                    return;
                }

                if (!registro || registro === 'Todos') {
                    this.lanzarToast('Seleccione un Registro', 'Por favor, selecciona específicamente RCE (Compras) o RVIE (Ventas) en el filtro para importar desde SUNAT.', 'info');
                    if (registroSelect) registroSelect.focus();
                    return;
                }

                if (!periodo) {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    periodo = `${year}-${month}`;
                    if (periodoInput) periodoInput.value = periodo;
                }

                this.importando = true;

                try {
                    const tokenEl = document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                    const response = await fetch(config.importarUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            cliente_id: clienteId,
                            registro: registro,
                            periodo: periodo
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Error al conectar con SUNAT.');
                    }

                    if (data.status === 'procesando' && data.ticket) {
                        this.lanzarToast('Procesando SIRE', data.mensaje || 'Ticket generado en SUNAT. Procesando descarga...', 'info');
                        await this.esperarTicket(data.ticket, clienteId, registro, periodo);
                        return;
                    }

                    this.importando = false;
                    this.lanzarToast('¡Importación Exitosa!', data.message || 'Comprobantes importados exitosamente.', 'success');

                    if (data.recarga) {
                        setTimeout(() => {
                            const params = new URLSearchParams(window.location.search);
                            params.set('cliente_id', clienteId);
                            params.set('tipo_registro', registro);
                            params.set('periodo', periodo);
                            window.location.href = window.location.pathname + '?' + params.toString();
                        }, 1200);
                    }
                } catch (err) {
                    this.importando = false;
                    console.error('Error importación SIRE:', err);
                    const isSunatError = err.message && err.message.startsWith('Error SUNAT');
                    const errorMsg = isSunatError ? err.message : (err.message || 'Error desconocido');
                    const title = isSunatError ? 'Error SUNAT' : 'Aviso SIRE';
                    this.lanzarToast(title, errorMsg, 'error');
                }
            },
            async esperarTicket(ticket, clienteId, registro, periodo) {
                let intentos = 0;
                const maxIntentos = 15;

                const sondeo = async () => {
                    if (intentos >= maxIntentos) {
                        this.importando = false;
                        this.lanzarToast('Proceso en cola', `El ticket ${ticket} continúa procesándose en SUNAT. Podrás volver a sincronizar en unos instantes.`, 'info');
                        return;
                    }

                    intentos++;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch(config.importarUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                cliente_id: clienteId,
                                registro: registro,
                                periodo: periodo,
                                ticket: ticket
                            })
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Error consultando estado del ticket en SUNAT.');
                        }

                        if (data.status === 'procesando') {
                            setTimeout(sondeo, 2500);
                        } else {
                            this.importando = false;
                            this.lanzarToast('¡Importación Exitosa!', data.message || 'Comprobantes importados exitosamente.', 'success');
                            if (data.recarga) {
                                setTimeout(() => {
                                    const params = new URLSearchParams(window.location.search);
                                    params.set('cliente_id', clienteId);
                                    params.set('tipo_registro', registro);
                                    params.set('periodo', periodo);
                                    window.location.href = window.location.pathname + '?' + params.toString();
                                }, 1200);
                            }
                        }
                    } catch (err) {
                        this.importando = false;
                        console.error('Error sondeo ticket SIRE:', err);
                        const isSunatError = err.message && err.message.startsWith('Error SUNAT:');
                        const errorMsg = isSunatError ? err.message : (err.message || 'Error al consultar ticket.');
                        this.lanzarToast('Error SIRE', errorMsg, 'error');
                    }
                };

                setTimeout(sondeo, 2000);
            }
        };
    }
</script>
@endsection
