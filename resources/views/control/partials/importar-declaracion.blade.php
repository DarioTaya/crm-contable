{{-- Importación de la constancia y del detalle oficial de SUNAT. Vive dentro del panel Alpine de la hoja. --}}
@php
    $esPlame = $tono === 'purple';
@endphp
<div x-show="importacion['{{ $formulario }}'].abierta" x-cloak
     class="space-y-2 rounded-lg border p-2.5 {{ $esPlame ? 'border-purple-200 bg-purple-50' : 'border-blue-200 bg-blue-50' }}">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-[11px] font-bold {{ $esPlame ? 'text-purple-950' : 'text-blue-950' }}">Importar declaración SUNAT</p>
            <p class="text-[10px] leading-snug text-slate-600">
                Suelte la constancia y el detalle juntos. El PDF solo se lee: no queda guardado. Si el pago fue con detracciones, se registra solo.
            </p>
        </div>
        <button type="button" @click="importacion['{{ $formulario }}'].abierta = false" class="px-1 font-bold leading-none text-slate-500 hover:text-slate-800" title="Cerrar">&times;</button>
    </div>

    <label class="flex cursor-pointer items-center justify-center rounded-lg border border-dashed bg-white px-2 py-2 text-[11px] font-semibold hover:bg-slate-50 {{ $esPlame ? 'border-purple-300 text-purple-800' : 'border-blue-300 text-blue-800' }}"
           @dragover.prevent
           @drop.prevent="soltarConstancia('{{ $formulario }}', $event)">
        <input type="file" class="sr-only" accept=".pdf,.txt,.csv,application/pdf,text/plain,text/csv" multiple
               @change="subirConstancia('{{ $formulario }}', $event)">
        <span x-text="importacion['{{ $formulario }}'].cargando ? 'Leyendo la declaración…' : 'Seleccionar PDF de SUNAT'"></span>
    </label>

    <p x-show="importacion['{{ $formulario }}'].error" x-cloak class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] leading-snug text-rose-800" x-text="importacion['{{ $formulario }}'].error"></p>

    <div x-show="importacion['{{ $formulario }}'].resultado" x-cloak class="space-y-1.5">
        <div class="flex flex-wrap gap-1">
            <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[9px] font-bold text-slate-700" x-text="importacion['{{ $formulario }}'].resultado?.identidad?.tipo_etiqueta || ''"></span>
            <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[9px] text-slate-700" x-show="importacion['{{ $formulario }}'].resultado?.identidad?.numero_orden" x-text="'Orden ' + (importacion['{{ $formulario }}'].resultado?.identidad?.numero_orden || '')"></span>
            <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[9px] text-slate-600" x-show="importacion['{{ $formulario }}'].resultado?.identidad?.periodo_etiqueta" x-text="importacion['{{ $formulario }}'].resultado?.identidad?.periodo_etiqueta || ''"></span>
            <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[9px] text-slate-600" x-show="importacion['{{ $formulario }}'].resultado?.identidad?.ruc" x-text="importacion['{{ $formulario }}'].resultado?.identidad?.ruc || ''"></span>
        </div>

        <template x-for="alerta in (importacion['{{ $formulario }}'].resultado?.alertas || [])" :key="alerta.codigo + alerta.mensaje">
            <p class="rounded-md border px-2 py-1 text-[10px] leading-snug"
               :class="alerta.nivel === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : (alerta.nivel === 'advertencia' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-sky-200 bg-sky-50 text-sky-950')"
               x-text="alerta.mensaje"></p>
        </template>

        <p x-show="importacion['{{ $formulario }}'].aplicada && importacion['{{ $formulario }}'].resultado && !importacion['{{ $formulario }}'].resultado.bloqueado" class="text-[10px] font-semibold text-emerald-800">
            Montos leídos. Revise el cruce y pulse Guardar.
        </p>

        <label x-show="importacion['{{ $formulario }}'].resultado && !importacion['{{ $formulario }}'].resultado.bloqueado" class="flex items-center gap-1.5 text-[10px] text-slate-700">
            <input type="checkbox" x-model="importacion['{{ $formulario }}'].marcarDeclarado" class="rounded border-slate-300 {{ $esPlame ? 'text-purple-600 focus:ring-purple-500' : 'text-blue-600 focus:ring-blue-500' }}">
            <span>Al guardar, marcar esta DJ como Declarada</span>
        </label>
    </div>
</div>
