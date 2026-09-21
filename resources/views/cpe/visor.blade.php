@extends('layouts.app')

@section('title', 'Visor XML & Generador Premium de CPE')

@section('content')
<div x-data="visorCpe()" class="space-y-6">

    {{-- ═══════════════════ ENCABEZADO Y CONTEXTO ═══════════════════ --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Visor XML & Generador Premium de CPE</h1>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                    ⚡ 100% Stateless & Memoria
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                Visualizador interactivo A4 y generador directo de PDF para cualquier comprobante electrónico de SUNAT (E001, F001, B001, Notas) sin almacenar archivos en el servidor ni crear registros en base de datos.
            </p>
        </div>

        {{-- Botones de Acción Global cuando hay un comprobante cargado --}}
        <template x-if="cpeData">
            <div class="flex flex-wrap items-center gap-2">
                {{-- Selector de Plantilla --}}
                <div class="flex rounded-lg border border-gray-300 bg-white p-0.5 text-xs shadow-xs">
                    <button
                        type="button"
                        @click="templateMode = 'sol'"
                        :class="templateMode === 'sol' ? 'bg-brand-600 text-white font-semibold shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                        class="rounded-md px-2.5 py-1.5 transition"
                        title="Ver en diseño Portal SUNAT SOL (E001)">
                        🏛️ Portal SOL
                    </button>
                    <button
                        type="button"
                        @click="templateMode = 'privado'"
                        :class="templateMode === 'privado' ? 'bg-indigo-600 text-white font-semibold shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                        class="rounded-md px-2.5 py-1.5 transition"
                        title="Ver en diseño Corporativo Facturación Privada (F001/OSE)">
                        🏢 Facturador Privado
                    </button>
                </div>

                {{-- Botón Imprimir --}}
                <button
                    type="button"
                    @click="imprimir()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 active:scale-95 transition"
                    title="Imprimir representación impresa en A4 exacto">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                    <span>Imprimir A4</span>
                </button>

                {{-- Botón Descargar PDF --}}
                <button
                    type="button"
                    @click="descargarPdf()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-transparent bg-brand-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs hover:bg-brand-700 active:scale-95 transition"
                    title="Descargar archivo PDF generado en memoria">
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Descargar PDF</span>
                </button>

                {{-- Botón Cargar Otro --}}
                <button
                    type="button"
                    @click="reset()"
                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200 transition"
                    title="Cargar un nuevo archivo XML">
                    <span>✕ Cerrar</span>
                </button>
            </div>
        </template>
    </div>

    {{-- Alerta de Error si ocurre --}}
    <div x-show="errorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 text-sm flex items-start gap-3 shadow-xs print:hidden">
        <svg class="h-5 w-5 text-rose-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>
        <div class="flex-1">
            <h4 class="font-bold text-rose-900">No se pudo procesar el archivo</h4>
            <p class="mt-0.5 text-xs" x-text="errorMessage"></p>
        </div>
        <button type="button" @click="errorMessage = null" class="text-rose-500 hover:text-rose-800 font-bold text-xs">✕</button>
    </div>

    {{-- ═══════════════════ ZONA DE CARGA DRAG & DROP ═══════════════════ --}}
    <div
        x-show="!cpeData"
        x-cloak
        class="rounded-2xl border-2 border-dashed transition-all p-10 text-center shadow-xs print:hidden"
        :class="isDragging ? 'border-brand-500 bg-brand-50/50 ring-4 ring-brand-100' : 'border-gray-300 bg-white hover:border-brand-400 hover:bg-gray-50/50'"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="isDragging = false; handleDrop($event)">

        <input
            type="file"
            x-ref="fileInput"
            accept=".xml,text/xml"
            class="hidden"
            @change="handleFileSelect($event)">

        <div class="mx-auto flex max-w-lg flex-col items-center">
            {{-- Icono animado o ilustrativo --}}
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-100 text-brand-600 mb-4 shadow-xs">
                <template x-if="!loading">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </template>
                <template x-if="loading">
                    <svg class="h-8 w-8 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
            </div>

            <h3 class="text-base font-bold text-gray-900">
                <span x-show="!loading">Arrastra aquí cualquier archivo XML de Comprobante SUNAT</span>
                <span x-show="loading">Procesando estructura UBL 2.1 en memoria...</span>
            </h3>
            <p class="mt-1 text-xs text-gray-500 max-w-md">
                Soporta Facturas, Boletas, Notas de Crédito y Débito tanto del Portal SUNAT SOL (E001) como de facturadores privados / OSE (F001, B001, etc.).
            </p>

            <div class="mt-5 flex items-center gap-3">
                <button
                    type="button"
                    @click="$refs.fileInput.click()"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 active:scale-95 transition disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Seleccionar archivo XML</span>
                </button>

                <button
                    type="button"
                    @click="mostrarModalTexto = true"
                    :disabled="loading"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
                    <span>Pegar texto XML</span>
                </button>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-[11px] text-gray-400">
                <span class="flex items-center gap-1">✓ Desglose rico de ítems y precios</span>
                <span class="flex items-center gap-1">✓ Detección de Detracciones SPOT</span>
                <span class="flex items-center gap-1">✓ Calendario de cuotas al crédito</span>
                <span class="flex items-center gap-1">✓ Hash UBL y código QR</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════ MODAL PEGAR TEXTO XML ═══════════════════ --}}
    <div x-show="mostrarModalTexto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-xs print:hidden">
        <div @click.away="mostrarModalTexto = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="text-sm font-bold text-gray-900">Pegar contenido XML de Comprobante</h3>
                <button type="button" @click="mostrarModalTexto = false" class="text-gray-400 hover:text-gray-600 font-bold">✕</button>
            </div>
            <textarea
                x-model="xmlPegado"
                rows="10"
                placeholder="Pegue aquí el contenido <Invoice ...> o <CreditNote ...>"
                class="w-full font-mono text-xs rounded-lg border-gray-300 shadow-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="mostrarModalTexto = false" class="rounded-lg border px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50">Cancelar</button>
                <button type="button" @click="procesarTextoXml()" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700">Procesar XML</button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════ LIENZO VISOR A4 INTERACTIVO ═══════════════════ --}}
    <div x-show="cpeData" x-cloak class="space-y-4">

        {{-- Barra de información de comprobante --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-xs print:hidden">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg text-white font-mono font-bold text-xs"
                     :class="cpeData?.es_portal_sol ? 'bg-blue-600' : 'bg-indigo-600'">
                    XML
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-900 text-sm" x-text="cpeData?.id_completo"></span>
                        <span class="rounded px-2 py-0.5 text-[10px] font-bold"
                              :class="cpeData?.es_portal_sol ? 'bg-blue-100 text-blue-800' : 'bg-indigo-100 text-indigo-800'"
                              x-text="cpeData?.es_portal_sol ? 'Portal SUNAT SOL (E001)' : 'Facturación Privada / OSE'">
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 truncate" x-text="'Archivo: ' + filename + ' • Emitido el ' + (cpeData?.fecha_emision || '-')"></p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 font-mono" x-text="'Hash: ' + (cpeData?.digest_value ? cpeData.digest_value.substring(0, 16) + '...' : '-')"></span>
            </div>
        </div>

        {{-- ══════════ HOJA A4 REALISTA ══════════ --}}
        <div id="cpe-hoja-a4" class="mx-auto max-w-4xl rounded-sm border border-gray-200 bg-white p-8 md:p-12 shadow-xl print:m-0 print:max-w-full print:p-0 print:border-0 print:shadow-none text-gray-900">

            {{-- ───────────────────── PLANTILLA: PORTAL SOL (SUNAT SEE-SOL) ───────────────────── --}}
            <div x-show="esModoSol()">
                {{-- Banda superior SOL --}}
                <div class="bg-blue-900 text-white px-4 py-2 rounded-t-sm flex items-center justify-between text-xs font-bold tracking-wide">
                    <span>SUNAT — SISTEMA DE EMISIÓN ELECTRÓNICA DESDE EL PORTAL DE SUNAT</span>
                    <span class="text-[10px] font-normal text-blue-200">SEE - SOL</span>
                </div>

                {{-- Header Grid: Emisor a la izquierda, Recuadro RUC a la derecha --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 border-x border-b border-gray-300 p-4 bg-gray-50/50">
                    {{-- Datos Emisor (8 cols) --}}
                    <div class="md:col-span-7 space-y-1.5">
                        <h2 class="text-base font-extrabold text-blue-950 uppercase tracking-tight" x-text="cpeData?.emisor?.razon_social"></h2>
                        <div class="text-xs text-gray-600 space-y-0.5">
                            <p><span class="font-semibold text-gray-800">R.U.C.:</span> <span class="font-mono font-bold text-gray-900" x-text="cpeData?.emisor?.ruc"></span></p>
                            <p><span class="font-semibold text-gray-800">Domicilio Fiscal:</span> <span x-text="cpeData?.emisor?.direccion || 'NO CONSIGNADO'"></span></p>
                            <p class="text-[11px] text-blue-800 font-medium pt-1">Emitido vía: PORTAL SUNAT OPERACIONES EN LÍNEA (SOL)</p>
                        </div>
                    </div>

                    {{-- Recuadro RUC Oficial SUNAT (5 cols) --}}
                    <div class="md:col-span-5 rounded border-2 border-blue-900 bg-white p-3 text-center flex flex-col justify-center shadow-xs">
                        <div class="text-xs font-bold text-gray-700 tracking-wider">R.U.C. <span class="font-mono text-sm" x-text="cpeData?.emisor?.ruc"></span></div>
                        <div class="my-1.5 text-sm font-black text-rose-700 uppercase tracking-tight" x-text="cpeData?.tipo_comprobante_descripcion"></div>
                        <div class="text-base font-extrabold font-mono text-blue-900" x-text="'N° ' + (cpeData?.id_completo || '-')"></div>
                    </div>
                </div>

                {{-- Datos Receptor / Cliente --}}
                <div class="mt-4 rounded border border-gray-300 p-4 bg-white text-xs space-y-2">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
                        <div class="md:col-span-8">
                            <span class="font-semibold text-gray-600">Señor(es):</span>
                            <span class="font-bold text-gray-900 uppercase ml-1" x-text="cpeData?.receptor?.razon_social"></span>
                        </div>
                        <div class="md:col-span-4">
                            <span class="font-semibold text-gray-600">RUC / Doc:</span>
                            <span class="font-mono font-bold text-gray-900 ml-1" x-text="cpeData?.receptor?.ruc"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 pt-1 border-t border-gray-100">
                        <div class="md:col-span-8">
                            <span class="font-semibold text-gray-600">Dirección:</span>
                            <span class="text-gray-800 ml-1" x-text="cpeData?.receptor?.direccion || '-'"></span>
                        </div>
                        <div class="md:col-span-4">
                            <span class="font-semibold text-gray-600">Fecha Emisión:</span>
                            <span class="font-mono font-medium text-gray-900 ml-1" x-text="cpeData?.fecha_emision"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-2 pt-1 border-t border-gray-100">
                        <div class="md:col-span-4">
                            <span class="font-semibold text-gray-600">Moneda:</span>
                            <span class="font-bold text-gray-900 ml-1" x-text="cpeData?.moneda === 'USD' ? 'DÓLARES AMERICANOS ($)' : 'SOLES (S/)'"></span>
                        </div>
                        <div class="md:col-span-4">
                            <span class="font-semibold text-gray-600">Forma de Pago:</span>
                            <span class="font-bold text-emerald-700 ml-1 uppercase" x-text="cpeData?.forma_pago || 'CONTADO'"></span>
                        </div>
                        <div class="md:col-span-4" x-show="cpeData?.fecha_vencimiento">
                            <span class="font-semibold text-gray-600">Vencimiento:</span>
                            <span class="font-mono font-medium text-gray-900 ml-1" x-text="cpeData?.fecha_vencimiento"></span>
                        </div>
                    </div>
                </div>

                {{-- Tabla de Ítems --}}
                <div class="mt-4 overflow-hidden rounded border border-gray-300">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-blue-900 text-white font-semibold">
                            <tr>
                                <th class="px-3 py-2 text-center w-12">Ítem</th>
                                <th class="px-3 py-2 text-right w-16">Cant.</th>
                                <th class="px-3 py-2 text-center w-16">Unidad</th>
                                <th class="px-3 py-2 text-left w-24">Código</th>
                                <th class="px-3 py-2 text-left">Descripción</th>
                                <th class="px-3 py-2 text-right w-24">V. Unit</th>
                                <th class="px-3 py-2 text-right w-24">IGV</th>
                                <th class="px-3 py-2 text-right w-28">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="(item, idx) in cpeData?.items || []" :key="idx">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-center font-mono text-gray-500" x-text="idx + 1"></td>
                                    <td class="px-3 py-2 text-right font-mono" x-text="formatearMoneda(item.cantidad)"></td>
                                    <td class="px-3 py-2 text-center font-mono text-gray-600" x-text="item.unidad"></td>
                                    <td class="px-3 py-2 font-mono text-gray-500 text-[11px]" x-text="item.codigo || '-'"></td>
                                    <td class="px-3 py-2 font-medium text-gray-900" x-text="item.descripcion"></td>
                                    <td class="px-3 py-2 text-right font-mono" x-text="formatearMoneda(item.valor_unitario)"></td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-600" x-text="formatearMoneda(item.igv)"></td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-gray-900" x-text="formatearMoneda(item.total)"></td>
                                </tr>
                            </template>
                            <template x-if="!cpeData?.items || cpeData.items.length === 0">
                                <tr>
                                    <td colspan="8" class="px-3 py-4 text-center text-gray-500 italic">
                                        Operación consignada según registro general
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Sección Inferior: SPOT, Cuotas y Cuadro de Totales --}}
                <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                    {{-- Izquierda: Detracciones & Cuotas (7 cols) --}}
                    <div class="md:col-span-7 space-y-3">
                        {{-- Detracción SPOT --}}
                        <template x-if="cpeData?.detraccion?.aplica || cpeData?.detraccion?.tiene_detraccion">
                            <div class="rounded border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 space-y-1">
                                <div class="font-bold flex items-center gap-1.5 text-amber-800">
                                    <span>⚠️</span> OPERACIÓN SUJETA AL SISTEMA DE PAGO DE OBLIGACIONES TRIBUTARIAS (SPOT)
                                </div>
                                <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
                                    <div>
                                        <span class="font-semibold">Porcentaje:</span> <span x-text="(cpeData.detraccion.porcentaje || 0) + '%'"></span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Monto Detracción:</span> <span class="font-mono font-bold" x-text="'S/ ' + formatearMoneda(cpeData.detraccion.monto)"></span>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="font-semibold">Cta. Cte. Banco de la Nación:</span>
                                        <span class="font-mono font-bold" x-text="cpeData.detraccion.cuenta_banco || cpeData.detraccion.cuenta_banco_nacion || 'No especificada'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Cuotas al crédito --}}
                        <template x-if="cpeData?.cuotas && cpeData.cuotas.length > 0">
                            <div class="rounded border border-gray-200 bg-gray-50 p-3 text-xs space-y-2">
                                <div class="font-bold text-gray-700">INFORMACIÓN DE CUOTAS AL CRÉDITO:</div>
                                <div class="space-y-1">
                                    <template x-for="(cuota, cIdx) in cpeData.cuotas" :key="cIdx">
                                        <div class="flex items-center justify-between text-[11px] font-mono border-b border-gray-200 pb-1">
                                            <span x-text="cuota.cuota || ('Cuota ' + (cIdx + 1))"></span>
                                            <span>Vence: <span class="font-bold" x-text="cuota.fecha_vencimiento || '-'"></span></span>
                                            <span class="font-bold text-gray-900" x-text="(cpeData.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cuota.monto)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Monto en Letras --}}
                        <div class="rounded border border-gray-200 bg-gray-100 p-2 text-xs font-semibold text-gray-800">
                            <span x-text="cpeData?.monto_letras"></span>
                        </div>
                    </div>

                    {{-- Derecha: Cuadro de Totales (5 cols) --}}
                    <div class="md:col-span-5 rounded border border-gray-300 bg-white p-3 space-y-1.5 text-xs">
                        <div class="flex justify-between py-0.5 text-gray-600">
                            <span>Op. Gravadas:</span>
                            <span class="font-mono font-medium text-gray-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.base_imponible)"></span>
                        </div>
                        <template x-if="cpeData?.op_exoneradas > 0">
                            <div class="flex justify-between py-0.5 text-gray-600">
                                <span>Op. Exoneradas:</span>
                                <span class="font-mono font-medium text-gray-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.op_exoneradas)"></span>
                            </div>
                        </template>
                        <template x-if="cpeData?.op_inafectas > 0">
                            <div class="flex justify-between py-0.5 text-gray-600">
                                <span>Op. Inafectas:</span>
                                <span class="font-mono font-medium text-gray-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.op_inafectas)"></span>
                            </div>
                        </template>
                        <div class="flex justify-between py-0.5 text-gray-600">
                            <span>I.G.V. (18%):</span>
                            <span class="font-mono font-medium text-gray-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.igv)"></span>
                        </div>
                        <div class="flex justify-between border-t-2 border-blue-900 pt-2 text-sm font-black text-blue-950">
                            <span>TOTAL A PAGAR:</span>
                            <span class="font-mono" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.total)"></span>
                        </div>
                    </div>
                </div>

                {{-- Pie de página Legal SOL --}}
                <div class="mt-6 border-t-2 border-gray-200 pt-3 text-[11px] text-gray-500 space-y-1">
                    <p class="font-medium text-gray-700">Representación impresa de la Factura Electrónica generada desde el SEE-SOL de SUNAT.</p>
                    <p>Autorizado mediante RS N° 188-2010/SUNAT y normas modificatorias.</p>
                    <p class="font-mono text-[10px] text-gray-400" x-text="'Hash UBL: ' + (cpeData?.digest_value || '-')"></p>
                    <p class="font-mono text-[9px] text-gray-400 truncate" x-text="'Cadena QR: ' + (cpeData?.qr_cadena || '-')"></p>
                </div>
            </div>

            {{-- ───────────────────── PLANTILLA: FACTURADOR PRIVADO / OSE ───────────────────── --}}
            <div x-show="!esModoSol()">
                {{-- Header Privado: Identidad Corporativa --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start border-b border-gray-200 pb-6">
                    {{-- Logo / Datos Emisor (7 cols) --}}
                    <div class="md:col-span-7 space-y-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-white font-bold text-base shadow-sm">
                                CPE
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-slate-900 uppercase tracking-tight" x-text="cpeData?.emisor?.razon_social"></h2>
                                <p class="text-xs text-slate-500 font-medium">EMISOR ELECTRÓNICO AUTORIZADO</p>
                            </div>
                        </div>

                        <div class="text-xs text-slate-600 space-y-0.5 pt-1">
                            <p><span class="font-semibold text-slate-800">R.U.C.:</span> <span class="font-mono font-bold text-slate-900" x-text="cpeData?.emisor?.ruc"></span></p>
                            <p><span class="font-semibold text-slate-800">Dirección:</span> <span x-text="cpeData?.emisor?.direccion || 'NO CONSIGNADO'"></span></p>
                            <p class="text-[11px] text-indigo-700 font-semibold">Sistema de Emisión Electrónica (SEE) Facturador Privado / OSE</p>
                        </div>
                    </div>

                    {{-- Recuadro RUC Corporativo Moderno (5 cols) --}}
                    <div class="md:col-span-5 rounded-xl border-2 border-slate-900 bg-white overflow-hidden shadow-sm">
                        <div class="bg-slate-900 py-1.5 text-center text-xs font-bold text-white tracking-wider">
                            R.U.C. <span class="font-mono text-sm" x-text="cpeData?.emisor?.ruc"></span>
                        </div>
                        <div class="p-3 text-center space-y-1">
                            <div class="text-xs font-black text-slate-800 uppercase tracking-tight" x-text="cpeData?.tipo_comprobante_descripcion"></div>
                            <div class="text-lg font-black font-mono text-indigo-600" x-text="'N° ' + (cpeData?.id_completo || '-')"></div>
                        </div>
                    </div>
                </div>

                {{-- Bloque de Cliente / Receptor --}}
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 text-xs space-y-2.5">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                        <div class="md:col-span-8">
                            <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider block">Adquirente / Cliente</span>
                            <span class="font-extrabold text-slate-900 uppercase text-sm" x-text="cpeData?.receptor?.razon_social"></span>
                        </div>
                        <div class="md:col-span-4">
                            <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider block">RUC / Documento</span>
                            <span class="font-mono font-bold text-slate-900 text-sm" x-text="cpeData?.receptor?.ruc"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 pt-2 border-t border-slate-200/80">
                        <div class="md:col-span-8">
                            <span class="font-semibold text-slate-600">Dirección Fiscal:</span>
                            <span class="text-slate-800 ml-1" x-text="cpeData?.receptor?.direccion || '-'"></span>
                        </div>
                        <div class="md:col-span-4">
                            <span class="font-semibold text-slate-600">Fecha de Emisión:</span>
                            <span class="font-mono font-semibold text-slate-900 ml-1" x-text="cpeData?.fecha_emision"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 pt-1 border-t border-slate-200/80">
                        <div class="md:col-span-4">
                            <span class="font-semibold text-slate-600">Moneda:</span>
                            <span class="font-bold text-slate-900 ml-1" x-text="cpeData?.moneda === 'USD' ? 'USD - DÓLARES AMERICANOS' : 'PEN - SOLES'"></span>
                        </div>
                        <div class="md:col-span-4">
                            <span class="font-semibold text-slate-600">Forma de Pago:</span>
                            <span class="font-extrabold text-emerald-700 ml-1 uppercase" x-text="cpeData?.forma_pago || 'CONTADO'"></span>
                        </div>
                        <div class="md:col-span-4" x-show="cpeData?.fecha_vencimiento">
                            <span class="font-semibold text-slate-600">Fecha Vence:</span>
                            <span class="font-mono font-semibold text-slate-900 ml-1" x-text="cpeData?.fecha_vencimiento"></span>
                        </div>
                    </div>
                </div>

                {{-- Tabla de Ítems Corporativa --}}
                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 shadow-xs">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-900 text-white font-semibold">
                            <tr>
                                <th class="px-3 py-2.5 text-center w-12">#</th>
                                <th class="px-3 py-2.5 text-right w-16">Cant.</th>
                                <th class="px-3 py-2.5 text-center w-16">U.M.</th>
                                <th class="px-3 py-2.5 text-left w-24">Código</th>
                                <th class="px-3 py-2.5 text-left">Descripción del Producto / Servicio</th>
                                <th class="px-3 py-2.5 text-right w-24">V. Unit</th>
                                <th class="px-3 py-2.5 text-right w-24">IGV</th>
                                <th class="px-3 py-2.5 text-right w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="(item, idx) in cpeData?.items || []" :key="idx">
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-3 py-2.5 text-center font-mono text-slate-400" x-text="idx + 1"></td>
                                    <td class="px-3 py-2.5 text-right font-mono font-medium" x-text="formatearMoneda(item.cantidad)"></td>
                                    <td class="px-3 py-2.5 text-center font-mono text-slate-600" x-text="item.unidad"></td>
                                    <td class="px-3 py-2.5 font-mono text-slate-400 text-[11px]" x-text="item.codigo || '-'"></td>
                                    <td class="px-3 py-2.5 font-medium text-slate-900" x-text="item.descripcion"></td>
                                    <td class="px-3 py-2.5 text-right font-mono text-slate-700" x-text="formatearMoneda(item.valor_unitario)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono text-slate-600" x-text="formatearMoneda(item.igv)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono font-bold text-slate-900" x-text="formatearMoneda(item.total)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Sección Inferior Privada: SPOT, Cuotas y Totales --}}
                <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
                    {{-- Izquierda: SPOT y Crédito (7 cols) --}}
                    <div class="md:col-span-7 space-y-3">
                        {{-- Detracción SPOT --}}
                        <template x-if="cpeData?.detraccion?.aplica || cpeData?.detraccion?.tiene_detraccion">
                            <div class="rounded-xl border border-amber-300 bg-amber-50/70 p-3.5 text-xs text-amber-950 space-y-1.5 shadow-xs">
                                <div class="font-black text-amber-900 flex items-center gap-1.5">
                                    <span>⚠️</span> OPERACIÓN SUJETA AL SISTEMA DE PAGO DE OBLIGACIONES TRIBUTARIAS (SPOT)
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-[11px] pt-1">
                                    <div><span class="font-semibold text-amber-800">Tasa:</span> <span class="font-bold" x-text="(cpeData.detraccion.porcentaje || 0) + '%'"></span></div>
                                    <div><span class="font-semibold text-amber-800">Monto SPOT:</span> <span class="font-mono font-bold" x-text="'S/ ' + formatearMoneda(cpeData.detraccion.monto)"></span></div>
                                    <div class="col-span-2"><span class="font-semibold text-amber-800">Cta Cte Banco de la Nación:</span> <span class="font-mono font-bold" x-text="cpeData.detraccion.cuenta_banco || cpeData.detraccion.cuenta_banco_nacion || 'No especificada'"></span></div>
                                </div>
                            </div>
                        </template>

                        {{-- Cuotas Crédito --}}
                        <template x-if="cpeData?.cuotas && cpeData.cuotas.length > 0">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs space-y-2">
                                <div class="font-bold text-slate-800 uppercase text-[11px]">Condiciones de Pago al Crédito</div>
                                <div class="space-y-1">
                                    <template x-for="(cuota, cIdx) in cpeData.cuotas" :key="cIdx">
                                        <div class="flex items-center justify-between text-[11px] font-mono border-b border-slate-200 pb-1">
                                            <span class="font-semibold text-slate-700" x-text="cuota.cuota || ('Cuota ' + (cIdx + 1))"></span>
                                            <span class="text-slate-500">Vence: <span class="font-bold text-slate-800" x-text="cuota.fecha_vencimiento || '-'"></span></span>
                                            <span class="font-bold text-indigo-700" x-text="(cpeData.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cuota.monto)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Leyenda Monto en Letras --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-800">
                            <span class="text-[10px] uppercase font-bold text-slate-500 block mb-0.5">Importe en letras</span>
                            <span x-text="cpeData?.monto_letras"></span>
                        </div>
                    </div>

                    {{-- Derecha: Totales Corporativo (5 cols) --}}
                    <div class="md:col-span-5 rounded-xl border border-slate-200 bg-slate-50/50 p-4 space-y-2 text-xs shadow-xs">
                        <div class="flex justify-between py-0.5 text-slate-600">
                            <span>Op. Gravadas:</span>
                            <span class="font-mono font-medium text-slate-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.base_imponible)"></span>
                        </div>
                        <template x-if="cpeData?.op_exoneradas > 0">
                            <div class="flex justify-between py-0.5 text-slate-600">
                                <span>Op. Exoneradas:</span>
                                <span class="font-mono font-medium text-slate-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.op_exoneradas)"></span>
                            </div>
                        </template>
                        <template x-if="cpeData?.op_inafectas > 0">
                            <div class="flex justify-between py-0.5 text-slate-600">
                                <span>Op. Inafectas:</span>
                                <span class="font-mono font-medium text-slate-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.op_inafectas)"></span>
                            </div>
                        </template>
                        <div class="flex justify-between py-0.5 text-slate-600">
                            <span>I.G.V. (18%):</span>
                            <span class="font-mono font-medium text-slate-900" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.igv)"></span>
                        </div>
                        <div class="flex justify-between border-t-2 border-slate-900 pt-2.5 text-base font-black text-slate-900">
                            <span>TOTAL:</span>
                            <span class="font-mono text-indigo-700" x-text="(cpeData?.simbolo_moneda || 'S/') + ' ' + formatearMoneda(cpeData?.total)"></span>
                        </div>
                    </div>
                </div>

                {{-- Footer Corporativo Privado --}}
                <div class="mt-8 border-t border-slate-200 pt-4 text-[11px] text-slate-500 space-y-1">
                    <p class="font-semibold text-slate-700">Representación impresa del Comprobante de Pago Electrónico bajo el estándar UBL 2.1 autorizado por SUNAT.</p>
                    <p>Consulte la validez y estado del comprobante con su proveedor de servicios electrónicos o en el portal SUNAT.</p>
                    <p class="font-mono text-[10px] text-slate-400" x-text="'Hash Digital: ' + (cpeData?.digest_value || '-')"></p>
                    <p class="font-mono text-[9px] text-slate-400 truncate" x-text="'Cadena QR: ' + (cpeData?.qr_cadena || '-')"></p>
                </div>
            </div>

        </div>

    </div>

    {{-- Formulario oculto para disparo de descarga de PDF --}}
    <form x-ref="formDescargaPdf" method="POST" action="{{ route('cpe.descargar-pdf') }}" class="hidden">
        @csrf
        <input type="hidden" name="xml_content" :value="rawXml">
    </form>

</div>

{{-- ═══════════════════ ESTILOS DE IMPRESIÓN EXACTA EN A4 ═══════════════════ --}}
<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 11pt !important;
    }
    /* Ocultar sidebar, topbar, botones y controles */
    aside, header, nav, .print\:hidden {
        display: none !important;
    }
    main {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }
    #cpe-hoja-a4 {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }
}
</style>

<script>
function visorCpe() {
    return {
        isDragging: false,
        loading: false,
        errorMessage: null,
        cpeData: null,
        rawXml: '',
        filename: '',
        mostrarModalTexto: false,
        xmlPegado: '',
        templateMode: 'auto', // 'auto', 'sol', 'privado'

        esModoSol() {
            if (this.templateMode === 'sol') return true;
            if (this.templateMode === 'privado') return false;
            return Boolean(this.cpeData && this.cpeData.es_portal_sol);
        },

        handleDrop(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.procesarArchivo(files[0]);
            }
        },

        handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                this.procesarArchivo(files[0]);
            }
        },

        async procesarArchivo(file) {
            if (!file.name.toLowerCase().endsWith('.xml')) {
                this.errorMessage = 'Por favor selecciona un archivo con extensión .xml';
                return;
            }

            this.loading = true;
            this.errorMessage = null;

            const formData = new FormData();
            formData.append('xml_file', file);

            try {
                const res = await fetch("{{ route('cpe.parse') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.error || 'No se pudo leer el archivo XML.');
                }

                this.cpeData = json.data;
                this.rawXml = json.raw_xml;
                this.filename = json.filename || file.name;
                this.templateMode = this.cpeData.es_portal_sol ? 'sol' : 'privado';
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
                if (this.$refs.fileInput) {
                    this.$refs.fileInput.value = '';
                }
            }
        },

        async procesarTextoXml() {
            if (!this.xmlPegado.trim()) {
                alert('Ingresa el código XML primero');
                return;
            }

            this.loading = true;
            this.errorMessage = null;
            this.mostrarModalTexto = false;

            const formData = new FormData();
            formData.append('xml_content', this.xmlPegado);

            try {
                const res = await fetch("{{ route('cpe.parse') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.error || 'No se pudo procesar el XML pegado.');
                }

                this.cpeData = json.data;
                this.rawXml = json.raw_xml;
                this.filename = json.filename || 'comprobante_pegado.xml';
                this.templateMode = this.cpeData.es_portal_sol ? 'sol' : 'privado';
                this.xmlPegado = '';
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        descargarPdf() {
            if (!this.rawXml && !this.cpeData) {
                alert('No hay comprobante cargado para descargar');
                return;
            }
            this.$refs.formDescargaPdf.submit();
        },

        imprimir() {
            window.print();
        },

        reset() {
            this.cpeData = null;
            this.rawXml = '';
            this.filename = '';
            this.errorMessage = null;
            this.templateMode = 'auto';
        },

        formatearMoneda(valor) {
            const num = parseFloat(valor || 0);
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}
</script>
@endsection
