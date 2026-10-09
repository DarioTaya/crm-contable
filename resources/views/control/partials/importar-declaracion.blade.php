{{-- Importación de la constancia y del detalle oficial de SUNAT. Vive dentro del panel Alpine de la hoja. --}}
@php
    $esPlame = $tono === 'purple';
@endphp
<div x-show="importacion['{{ $formulario }}'].abierta" x-cloak
     class="space-y-2.5 rounded-xl border p-3 shadow-2xs {{ $esPlame ? 'border-purple-200 bg-purple-50/70' : 'border-blue-200 bg-blue-50/70' }}">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="flex items-center gap-1.5">
                <span class="flex h-5 w-5 items-center justify-center rounded-lg {{ $esPlame ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white' }} text-[10px] font-bold">PDF</span>
                <p class="text-xs font-bold {{ $esPlame ? 'text-purple-950' : 'text-blue-950' }}">Importar Declaración Oficial SUNAT</p>
            </div>
            <p class="text-[10px] leading-snug text-slate-600 mt-0.5">
                Arrastre constancia y detalle de casillas (y boletas 1662 si pagó con detracciones). El PDF solo se procesa en memoria; no se almacena en el servidor.
            </p>
        </div>
        <button type="button" @click="importacion['{{ $formulario }}'].abierta = false" class="px-1.5 py-0.5 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-200/50 text-base font-bold leading-none cursor-pointer" title="Cerrar">&times;</button>
    </div>

    {{-- Zona de carga Drag & Drop --}}
    <label class="group flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed bg-white p-3 text-center transition hover:bg-slate-50/80 {{ $esPlame ? 'border-purple-300 hover:border-purple-500' : 'border-blue-300 hover:border-blue-500' }}"
           @dragover.prevent
           @drop.prevent="soltarConstancia('{{ $formulario }}', $event)">
        <input type="file" class="sr-only" accept=".pdf,.txt,.csv,application/pdf,text/plain,text/csv" multiple
               @change="subirConstancia('{{ $formulario }}', $event)">
        <div class="flex items-center gap-2 text-xs font-semibold {{ $esPlame ? 'text-purple-800' : 'text-blue-800' }}">
            <svg class="h-4 w-4 shrink-0 transition group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            <span x-text="importacion['{{ $formulario }}'].cargando ? 'Analizando y cruzando declaración con SUNAT…' : 'Seleccionar o soltar PDFs de SUNAT (Constancia + Detalle)'"></span>
        </div>
        <span class="mt-0.5 text-[9.5px] text-slate-400">Archivos PDF oficiales de Clave SOL o Declaración Simplificada</span>
    </label>

    {{-- Error de lectura bloqueante --}}
    <div x-show="importacion['{{ $formulario }}'].error" x-cloak
         class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-[10px] leading-snug text-rose-900 flex items-start gap-1.5">
        <span class="text-rose-600 font-bold shrink-0">⛔</span>
        <span x-text="importacion['{{ $formulario }}'].error"></span>
    </div>

    {{-- Dashboard de Resultado de Conciliación --}}
    <div x-show="importacion['{{ $formulario }}'].resultado" x-cloak class="space-y-2">
        {{-- Fila de Metadatos de la Declaración --}}
        <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
            <span class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-0.5 text-[9.5px] font-bold text-slate-800 shadow-2xs">
                <span class="h-1.5 w-1.5 rounded-full" :class="importacion['{{ $formulario }}'].resultado?.identidad?.tipo === 'rectificatoria' ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                <span x-text="importacion['{{ $formulario }}'].resultado?.identidad?.tipo_etiqueta || 'Declaración'"></span>
            </span>
            <span class="rounded-md border border-slate-300 bg-white px-2 py-0.5 font-mono text-[9.5px] text-slate-700 shadow-2xs"
                  x-show="importacion['{{ $formulario }}'].resultado?.identidad?.numero_orden"
                  x-text="'N° Orden ' + (importacion['{{ $formulario }}'].resultado?.identidad?.numero_orden || '')"></span>
            <span class="rounded-md border border-slate-300 bg-white px-2 py-0.5 text-[9.5px] font-semibold text-slate-700 shadow-2xs"
                  x-show="importacion['{{ $formulario }}'].resultado?.identidad?.periodo_etiqueta"
                  x-text="importacion['{{ $formulario }}'].resultado?.identidad?.periodo_etiqueta || ''"></span>
            <span class="rounded-md border border-slate-300 bg-white px-2 py-0.5 font-mono text-[9.5px] text-slate-600 shadow-2xs"
                  x-show="importacion['{{ $formulario }}'].resultado?.identidad?.ruc"
                  x-text="'RUC ' + (importacion['{{ $formulario }}'].resultado?.identidad?.ruc || '')"></span>
            <span class="rounded-md border border-slate-300 bg-white px-2 py-0.5 text-[9.5px] text-slate-500 shadow-2xs"
                  x-show="importacion['{{ $formulario }}'].resultado?.identidad?.fecha_presentacion"
                  x-text="'Presentado: ' + (importacion['{{ $formulario }}'].resultado?.identidad?.fecha_presentacion || '')"></span>
        </div>

        {{-- 1. Tarjeta de Discrepancia de Régimen Tributario (con validación legal según Art. 119-121 TUO LIR) --}}
        <div x-show="importacion['{{ $formulario }}'].resultado?.regimen_discrepancia" x-cloak
             class="rounded-xl border border-amber-300 bg-amber-50/90 p-2.5 shadow-2xs">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="text-amber-600 text-xs font-bold">⚡</span>
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-900">
                            <span x-show="importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.puede_cambiar_ficha">Discrepancia de Régimen Tributario</span>
                            <span x-show="!importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.puede_cambiar_ficha">Régimen Histórico Declarado (Normativa SUNAT)</span>
                        </span>
                    </div>
                    <p class="text-[10.5px] text-amber-950 font-medium mt-1 leading-snug">
                        La Ficha del cliente figura con <span class="rounded px-1 py-0.2 bg-white border border-amber-300 font-bold font-mono text-amber-900" x-text="importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.ficha"></span>,
                        pero la DJ de SUNAT usó <span class="rounded px-1 py-0.2 bg-amber-200 border border-amber-400 font-bold font-mono text-amber-950" x-text="importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.dj + (importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.tasa_dj ? ' (' + importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.tasa_dj + '%)' : '')"></span>.
                    </p>
                    <p class="text-[9.5px] text-amber-800 mt-1"
                       x-text="importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.mensaje_normativo || 'Pulse el botón para sincronizar la ficha del cliente en el CRM y aplicar la tasa correspondiente.'">
                    </p>
                </div>
                <template x-if="importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.puede_cambiar_ficha">
                    <button type="button"
                            @click="actualizarRegimenFicha(importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.dj)"
                            class="shrink-0 px-2.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[10px] shadow-xs transition cursor-pointer flex items-center gap-1">
                        <span>⚡ Actualizar a</span>
                        <span x-text="importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.dj"></span>
                    </button>
                </template>
                <template x-if="!importacion['{{ $formulario }}'].resultado?.regimen_discrepancia?.puede_cambiar_ficha">
                    <span class="shrink-0 px-2 py-1 rounded-md bg-white border border-amber-300 text-amber-900 font-bold text-[9px] shadow-2xs whitespace-nowrap">
                        🔒 Ficha protegida (Art. 121 LIR)
                    </span>
                </template>
            </div>
        </div>

        {{-- 2. Tarjeta de Pagos Detectados y Registrados (Detracciones / Boletas 1662) --}}
        <div x-show="(importacion['{{ $formulario }}'].resultado?.pagos || []).length > 0" x-cloak
             class="rounded-xl border border-emerald-300 bg-emerald-50/80 p-2.5 space-y-1.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-white text-[9px] font-black">✓</span>
                    <span class="text-[10.5px] font-bold text-emerald-950">
                        <span x-text="(importacion['{{ $formulario }}'].resultado?.pagos || []).length"></span>
                        <span>Pago(s) detectado(s) e incorporado(s) al periodo</span>
                    </span>
                </div>
                <span class="text-[9.5px] font-bold font-mono text-emerald-900"
                      x-text="'Total: S/ ' + formatearNumeroConComas((importacion['{{ $formulario }}'].resultado?.pagos || []).reduce((acc, p) => acc + parseFloat(p.importe || 0), 0))">
                </span>
            </div>
            <div class="grid grid-cols-1 gap-1">
                <template x-for="(pago, pIdx) in (importacion['{{ $formulario }}'].resultado?.pagos || [])" :key="pIdx">
                    <div class="flex items-center justify-between rounded-lg bg-white/90 border border-emerald-200/80 px-2 py-1 text-[10px]">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-mono font-bold px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 text-[9px]"
                                  x-text="pago.tributo === '1011' ? '1011 · IGV' : (pago.tributo === '3111' ? '3111 · RER' : (pago.tributo === '3121' ? '3121 · RMT' : pago.tributo))"></span>
                            <span class="text-slate-600" x-show="pago.numero_operacion" x-text="'Op. ' + pago.numero_operacion"></span>
                            <span class="text-slate-400" x-show="pago.fecha" x-text="'· ' + pago.fecha"></span>
                            <span class="text-emerald-700 font-medium" x-show="pago.medio_codigo === 'detracciones'">· Cuenta Detracciones BN</span>
                        </div>
                        <span class="font-mono font-black text-emerald-950" x-text="'S/ ' + formatearNumeroConComas(pago.importe)"></span>
                    </div>
                </template>
            </div>
        </div>

        {{-- 3. Lista Limpia de Alertas y Observaciones Reales (sin duplicar pagos de detracciones) --}}
        <template x-for="alerta in ((importacion['{{ $formulario }}'].resultado?.alertas || []).filter(a => a.codigo !== 'pago_detracciones'))" :key="alerta.codigo + alerta.mensaje">
            <div class="flex items-start gap-1.5 rounded-lg border px-2.5 py-1.5 text-[10px] leading-snug shadow-2xs"
                 :class="alerta.nivel === 'error' ? 'border-rose-200 bg-rose-50 text-rose-950' : (alerta.nivel === 'advertencia' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-sky-200 bg-sky-50 text-sky-950')">
                <span class="shrink-0 text-xs" x-text="alerta.nivel === 'error' ? '⛔' : (alerta.nivel === 'advertencia' ? '⚠️' : 'ℹ️')"></span>
                <span class="flex-1 font-medium" x-text="alerta.mensaje"></span>
            </div>
        </template>

        {{-- Confirmación y Guardado --}}
        <div x-show="importacion['{{ $formulario }}'].aplicada && importacion['{{ $formulario }}'].resultado && !importacion['{{ $formulario }}'].resultado.bloqueado"
             class="flex items-center justify-between gap-2 pt-1 border-t border-slate-200/60">
            <p class="text-[10px] font-semibold text-emerald-800 flex items-center gap-1">
                <span>✓</span>
                <span>Montos conciliados con la determinación oficial de SUNAT.</span>
            </p>
            <label class="flex items-center gap-1.5 text-[10px] text-slate-700 cursor-pointer">
                <input type="checkbox" x-model="importacion['{{ $formulario }}'].marcarDeclarado" class="rounded border-slate-300 {{ $esPlame ? 'text-purple-600 focus:ring-purple-500' : 'text-blue-600 focus:ring-blue-500' }}">
                <span>Marcar como <strong>Declarada</strong> al guardar</span>
            </label>
        </div>
    </div>
</div>
