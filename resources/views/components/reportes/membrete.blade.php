@props([
    'titulo' => 'Reporte Oficial',
    'subtitulo' => null,
    'badge' => 'Documento Contable',
    'tipoDocumento' => null,
])

@php
    $estudio = auth()->user()?->estudio;
    // Prioriza el logotipo para documentos/fondo claro si fue cargado, de lo contrario usa el principal
    $logoUrl = $estudio?->logoDocumentosUrl();
    $tieneLogo = ! empty($logoUrl);
    $nombreEstudio = $estudio?->nombre ?? 'ESTUDIO CONTABLE';
    $nombreComercial = $estudio?->nombre_comercial;
    $ruc = $estudio?->ruc;
    $representante = $estudio?->representante;
    $colegiatura = $estudio?->colegiatura;
    $direccion = $estudio?->direccion;
    $ciudad = $estudio?->ciudad;
    $telefono = $estudio?->telefono;
    $correo = $estudio?->correo;
    $sitioWeb = $estudio?->sitio_web;
@endphp

<div {{ $attributes->merge(['class' => 'hidden print-only mb-2 border-b border-slate-300 pb-2']) }}>
    <div class="flex items-start justify-between gap-3">
        {{-- Identidad Corporativa e Información Fiscal del Estudio (Logo encima de los datos) --}}
        <div class="flex flex-col items-start gap-0.5 max-w-2xl">
            @if ($tieneLogo)
                <div class="shrink-0 mb-1">
                    <img src="{{ $logoUrl }}"
                         alt="{{ $nombreEstudio }}"
                         style="max-height: 24px; max-width: 120px; width: auto; height: auto;"
                         class="h-6 max-h-6 w-auto max-w-[120px] object-contain object-left">
                </div>
            @else
                <h1 class="text-sm font-black uppercase tracking-tight text-slate-900 leading-tight mb-0.5">
                    {{ $nombreEstudio }}
                </h1>
                @if ($nombreComercial && $nombreComercial !== $nombreEstudio)
                    <p class="text-[10px] font-bold text-slate-700 italic">{{ $nombreComercial }}</p>
                @endif
            @endif

            <div class="space-y-0.5">
                {{-- Línea 1: RUC, Titular y Colegiatura (DIRECTAMENTE DEBAJO DEL LOGOTIPO) --}}
                <div class="flex flex-wrap items-center gap-x-2 text-[9.5px] text-slate-700 font-medium leading-tight">
                    @if ($ruc)
                        <span class="inline-flex items-center gap-1">
                            <span class="font-bold text-slate-500">RUC:</span>
                            <span class="font-mono font-bold text-slate-900">{{ $ruc }}</span>
                        </span>
                    @endif
                    @if ($representante)
                        <span class="inline-flex items-center gap-1">
                            <span class="text-slate-300 font-bold">&bull;</span>
                            <span class="text-slate-500">Titular:</span>
                            <span class="font-semibold text-slate-800">{{ $representante }}</span>
                        </span>
                    @endif
                    @if ($colegiatura)
                        <span class="inline-flex items-center gap-1">
                            <span class="text-slate-300 font-bold">&bull;</span>
                            <span class="text-slate-500">Colegiatura:</span>
                            <span class="font-mono text-slate-800">{{ $colegiatura }}</span>
                        </span>
                    @endif
                </div>

                {{-- Línea 2: Domicilio fiscal y datos de contacto --}}
                <div class="flex flex-wrap items-center gap-x-2 text-[8.5px] text-slate-500 leading-tight">
                    @if ($direccion)
                        <span>{{ $direccion }}{{ $ciudad ? ' — '.$ciudad : '' }}</span>
                    @endif
                    @if ($telefono)
                        <span>&bull; Tel: <strong class="text-slate-700 font-mono">{{ $telefono }}</strong></span>
                    @endif
                    @if ($correo)
                        <span>&bull; {{ $correo }}</span>
                    @endif
                    @if ($sitioWeb)
                        <span>&bull; {{ $sitioWeb }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Metadatos del Documento y Emisión --}}
        <div class="text-right shrink-0">
            <span class="inline-block rounded border border-slate-300 bg-slate-50 px-2 py-0.5 text-[8.5px] font-bold uppercase tracking-wider text-slate-800">
                {{ $badge ?? ($tipoDocumento ?? 'Estado de Cuenta Tributario') }}
            </span>
            <p class="text-[9.5px] text-slate-600 mt-1 font-medium">
                Fecha de Emisión: <span class="font-bold font-mono text-slate-900" x-text="fechaHoraImpresion || '{{ now()->format('d/m/Y h:i A') }}'">{{ now()->format('d/m/Y h:i A') }}</span>
            </p>
            <p class="text-[9px] text-slate-500">
                Elaborado por: <span class="font-semibold text-slate-700">{{ auth()->user()?->name ?? 'Estudio Contable' }}</span>
            </p>
        </div>
    </div>

    {{-- Título y Subtítulo Central del Reporte --}}
    @if ($titulo)
        <div class="mt-2 pt-1.5 border-t border-slate-200 text-center">
            <h2 class="text-xs sm:text-sm font-black uppercase tracking-wide text-slate-900">
                {{ $titulo }}
            </h2>
            @if ($subtitulo)
                <p class="text-[9px] font-medium text-slate-600 mt-0.5">
                    {{ $subtitulo }}
                </p>
            @endif
        </div>
    @endif

    {{-- Contenido o resumen ejecutivo extra del membrete --}}
    {{ $slot }}
</div>
