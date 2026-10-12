        {{-- ════════════════════ PESTAÑA 4: SIRE / COMPROBANTES ════════════════════ --}}
        <div x-show="tab === 'sire'" x-cloak class="mt-6 space-y-6">
            {{-- Encabezado de la pestaña y Botonera SIRE --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        Bóveda SIRE — Expediente Digital
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Comprobantes electrónicos de compras (RCE) y ventas (RVIE) asociados exclusivamente a este cliente.</p>
                </div>

                {{-- Botonera de Acciones --}}
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Botón Importar desde SUNAT --}}
                    <button
                        type="button"
                        @click="importarSireDesdeSunat"
                        :disabled="sireImportando"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed transition"
                    >
                        <template x-if="!sireImportando">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </template>
                        <template x-if="sireImportando">
                            <svg class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <span x-text="sireImportando ? 'Importando...' : '⬇️ Importar desde SUNAT'"></span>
                    </button>

                    {{-- Botón Descargar Paquete ZIP --}}
                    <a
                        href="{{ route('comprobantes.zip', ['cliente_id' => $cliente->id, 'tipo_registro' => request('sire_tipo'), 'periodo' => request('sire_periodo')]) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 hover:border-gray-400 active:scale-95 transition"
                        title="Descargar paquete ZIP del cliente"
                    >
                        <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                        </svg>
                        <span>📦 Descargar Paquete ZIP</span>
                    </a>

                    {{-- Botón Exportar Excel --}}
                    <a
                        href="{{ route('comprobantes.export', ['cliente_id' => $cliente->id, 'tipo_registro' => request('sire_tipo'), 'periodo' => request('sire_periodo')]) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 shadow-xs hover:bg-emerald-100 hover:border-emerald-400 active:scale-95 transition"
                        title="Exportar comprobantes del cliente a Excel compatible"
                    >
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125" />
                        </svg>
                        <span>📊 Exportar Excel</span>
                    </a>
                </div>
            </div>

            {{-- Filtros Rápidos Locales --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
                <form method="GET" action="{{ route('clientes.sire', $cliente) }}" class="flex flex-wrap items-end gap-3">

                    {{-- Filtro Registro --}}
                    <div class="space-y-1">
                        <label for="sire_tipo" class="block text-[11px] font-semibold text-gray-700">Tipo de Registro</label>
                        <select name="sire_tipo" id="sire_tipo" class="rounded-lg border-gray-300 py-1.5 px-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Todos los Registros</option>
                            <option value="RCE" {{ request('sire_tipo') === 'RCE' ? 'selected' : '' }}>RCE - Registro de Compras</option>
                            <option value="RVIE" {{ request('sire_tipo') === 'RVIE' ? 'selected' : '' }}>RVIE - Registro de Ventas</option>
                        </select>
                    </div>

                    {{-- Filtro Periodo --}}
                    <div class="space-y-1">
                        <label for="sire_periodo" class="block text-[11px] font-semibold text-gray-700">Periodo Mensual</label>
                        <input
                            type="month"
                            name="sire_periodo"
                            id="sire_periodo"
                            value="{{ request('sire_periodo') }}"
                            class="rounded-lg border-gray-300 py-1.5 px-2.5 text-xs text-gray-800 shadow-xs focus:border-brand-500 focus:ring-brand-500"
                        />
                    </div>

                    {{-- Botón Filtrar --}}
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1 rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-gray-800 active:scale-95 transition"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <span>Filtrar</span>
                    </button>

                    @if (request()->hasAny(['sire_tipo', 'sire_periodo']))
                        <a
                            href="{{ route('clientes.sire', $cliente) }}"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white p-2 text-xs font-semibold text-gray-600 shadow-xs hover:bg-gray-50 active:scale-95 transition"
                            title="Limpiar filtros"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </form>
            </div>

            {{-- Tres Tarjetas Resumen Pre-filtradas para el Cliente --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{-- Base Imponible --}}
                <div class="relative overflow-hidden rounded-xl border border-blue-100 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Total Base Imponible</p>
                            <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($sireTotalBase, 2) }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-400">Subtotal acumulado para este cliente</div>
                </div>

                {{-- IGV --}}
                <div class="relative overflow-hidden rounded-xl border border-emerald-100 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Total IGV</p>
                            <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($sireTotalIgv, 2) }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5 3a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-400">Impuesto General a las Ventas (18%)</div>
                </div>

                {{-- Total Facturado --}}
                <div class="relative overflow-hidden rounded-xl border border-purple-100 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-purple-600">Total Facturado</p>
                            <p class="mt-2 text-2xl font-black text-gray-900">S/ {{ number_format($sireTotalFacturado, 2) }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0-8.25a60.106 60.106 0 0 1 15.797-2.101c.727-.198 1.453.342 1.453 1.096V6m-17.25 0h17.25m-17.25 0v1.5m0 0h17.25m-17.25 0v9m17.25-9v9m0 0H2.25m17.25 0a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25m17.25 0v1.5" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
                        <span>Total bruto comprobantes</span>
                        <span class="font-semibold text-gray-700">{{ $sireTotalRegistros }} {{ Str::plural('registro', $sireTotalRegistros) }}</span>
                    </div>
                </div>
            </div>

            {{-- Tabla de Comprobantes del Cliente --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-gray-50/80 uppercase tracking-wider text-gray-500 font-semibold">
                            <tr>
                                <th scope="col" class="px-3.5 py-3 text-center">Periodo</th>
                                <th scope="col" class="px-3 py-3 text-center">Registro</th>
                                <th scope="col" class="px-4 py-3">Comprobante</th>
                                <th scope="col" class="px-4 py-3">Tercero (Emisor/Receptor)</th>
                                <th scope="col" class="px-3 py-3 text-right">Base Imp.</th>
                                <th scope="col" class="px-3 py-3 text-right">IGV</th>
                                <th scope="col" class="px-3.5 py-3 text-right">Total</th>
                                <th scope="col" class="px-3 py-3 text-center">Estado SIRE</th>
                                <th scope="col" class="px-4 py-3 text-center">Archivos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($comprobantesCliente as $comp)
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    {{-- Periodo --}}
                                    <td class="whitespace-nowrap px-3.5 py-3 text-center font-mono font-medium text-gray-700">
                                        {{ $comp->periodo }}
                                    </td>

                                    {{-- Tipo de Registro --}}
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

                                    {{-- Comprobante --}}
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="font-semibold text-gray-900">{{ $comp->descripcion_tipo_comprobante }}</span>
                                        <div class="font-mono text-xs text-gray-500">{{ $comp->numero_comprobante }}</div>
                                    </td>

                                    {{-- Tercero --}}
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
                                     <td class="whitespace-nowrap px-4 py-3 text-center" x-data="{
                                         state: {
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
                                         }
                                     }">
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
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 border border-rose-300 shadow-xs"
                                                       :title="state.mensaje_soap || 'Error en descarga.'">
                                                     XML ⚠
                                                 </span>
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
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 border border-rose-300 shadow-xs"
                                                       :title="state.mensaje_soap || 'Error en descarga.'">
                                                     CDR ⚠
                                                 </span>
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
                                                 <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-[10px] font-mono font-bold text-rose-700 bg-rose-100 border border-rose-300 shadow-xs"
                                                       :title="state.mensaje_soap || 'Error al generar PDF.'">
                                                     PDF ⚠
                                                 </span>
                                             </template>
                                         </div>
                                     </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <svg class="mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                            </svg>
                                            <p class="text-sm font-semibold text-gray-700">Sin comprobantes para este cliente</p>
                                            <p class="mt-1 text-xs text-gray-400">No se encontraron registros de compras (RCE) ni ventas (RVIE) con los filtros actuales.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
