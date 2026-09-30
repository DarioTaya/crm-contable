@props([
    'titulo' => 'Reporte Oficial',
    'subtitulo' => null,
    'badge' => 'Documento Contable',
    'tipoDocumento' => null,
])

@php
    $estudio = auth()->user()?->estudio;
    $logoUrl = $estudio?->logoUrl();
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

<div {{ $attributes->merge(['class' => 'hidden print-only mb-6 border-b-2 border-gray-900 pb-4']) }}>
    <div class="flex items-start justify-between gap-4">
        {{-- Identidad Visual e Información Fiscal --}}
        <div class="flex items-start gap-4 max-w-2xl">
            @if ($logoUrl)
                <div class="shrink-0">
                    <img src="{{ $logoUrl }}"
                         alt="{{ $nombreEstudio }}"
                         class="h-16 w-auto max-w-[130px] object-contain">
                </div>
            @endif

            <div class="space-y-0.5">
                <h1 class="text-lg font-black uppercase tracking-tight text-gray-900 leading-snug">
                    {{ $nombreEstudio }}
                </h1>
                @if ($nombreComercial && $nombreComercial !== $nombreEstudio)
                    <p class="text-xs font-semibold text-gray-700 italic">{{ $nombreComercial }}</p>
                @endif

                <div class="flex flex-wrap items-center gap-x-3 text-[11px] text-gray-700 pt-0.5 font-medium">
                    @if ($ruc)
                        <span><strong>RUC:</strong> <span class="font-mono">{{ $ruc }}</span></span>
                    @endif
                    @if ($colegiatura)
                        <span><strong>Colegiatura:</strong> {{ $colegiatura }}</span>
                    @endif
                    @if ($representante)
                        <span><strong>Titular:</strong> {{ $representante }}</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-x-3 text-[10px] text-gray-600">
                    @if ($direccion)
                        <span>{{ $direccion }}{{ $ciudad ? ' — '.$ciudad : '' }}</span>
                    @endif
                    @if ($telefono)
                        <span>Tel: {{ $telefono }}</span>
                    @endif
                    @if ($correo)
                        <span>Email: {{ $correo }}</span>
                    @endif
                    @if ($sitioWeb)
                        <span>Web: {{ $sitioWeb }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Metadatos del Documento y Emisión --}}
        <div class="text-right shrink-0">
            <span class="inline-block rounded border border-gray-400 bg-gray-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-800">
                {{ $badge ?? ($tipoDocumento ?? 'Documento de Auditoría') }}
            </span>
            <p class="text-xs text-gray-700 mt-1.5 font-medium">
                Fecha de Emisión: <span class="font-bold font-mono">{{ now()->format('d/m/Y H:i') }}</span>
            </p>
            <p class="text-[11px] text-gray-500">
                Generado por: <span class="font-semibold text-gray-700">{{ auth()->user()?->name ?? 'Sistema CRM Contable' }}</span>
            </p>
        </div>
    </div>

    {{-- Título y Subtítulo Central del Reporte --}}
    @if ($titulo)
        <div class="mt-4 pt-3 border-t border-gray-200 text-center">
            <h2 class="text-lg font-black uppercase tracking-wide text-gray-900">
                {{ $titulo }}
            </h2>
            @if ($subtitulo)
                <p class="text-xs text-gray-600 mt-0.5">
                    {{ $subtitulo }}
                </p>
            @endif
        </div>
    @endif

    {{-- Contenido o resumen extra del membrete --}}
    {{ $slot }}
</div>
