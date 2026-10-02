@props([
    'titulo' => 'Reporte Oficial',
    'subtitulo' => null,
    'badge' => 'Documento Contable',
    'tipoDocumento' => null,
])

@php
    $estudio = auth()->user()?->estudio;
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
    $slogan = $estudio?->slogan;
    $elaboradoPor = auth()->user()?->name ?? 'Estudio Contable';
    $fechaEmision = now()->format('d/m/Y h:i A');

    $sitioWebVisible = null;
    if (is_string($sitioWeb) && trim($sitioWeb) !== '') {
        $sinProtocolo = preg_replace('#^https?://#i', '', trim($sitioWeb));
        $sitioWebVisible = rtrim(is_string($sinProtocolo) ? $sinProtocolo : trim($sitioWeb), '/');
    }

    $lineaFiscal = [];
    if (! empty($ruc)) {
        $lineaFiscal[] = ['etiqueta' => 'RUC', 'valor' => $ruc];
    }
    if (! empty($representante)) {
        $lineaFiscal[] = ['etiqueta' => 'Titular', 'valor' => $representante];
    }
    if (! empty($colegiatura)) {
        $lineaFiscal[] = ['etiqueta' => 'Colegiatura', 'valor' => $colegiatura];
    }

    $lineaContacto = [];
    if (! empty($direccion)) {
        $lineaContacto[] = [
            'etiqueta' => null,
            'valor' => $direccion.(! empty($ciudad) ? ' — '.$ciudad : ''),
        ];
    }
    if (! empty($telefono)) {
        $lineaContacto[] = ['etiqueta' => 'Tel.', 'valor' => $telefono];
    }
    if (! empty($correo)) {
        $lineaContacto[] = ['etiqueta' => null, 'valor' => $correo];
    }
    if (! empty($sitioWebVisible)) {
        $lineaContacto[] = ['etiqueta' => null, 'valor' => $sitioWebVisible];
    }

    $lineasIdentidad = array_values(array_filter(
        [$lineaFiscal, $lineaContacto],
        fn (array $linea): bool => $linea !== []
    ));
@endphp

<div {{ $attributes->merge(['class' => 'reporte-membrete hidden print-only']) }}>
    <div class="reporte-membrete__cabecera">
        <div class="reporte-membrete__marca">
            @if ($tieneLogo)
                <img src="{{ $logoUrl }}" alt="{{ $nombreEstudio }}" class="reporte-membrete__logo">
            @else
                <p class="reporte-membrete__nombre">{{ $nombreEstudio }}</p>
                @if ($nombreComercial && $nombreComercial !== $nombreEstudio)
                    <p class="reporte-membrete__secundario">{{ $nombreComercial }}</p>
                @endif
            @endif

            @if (! empty($slogan))
                <p class="reporte-membrete__secundario">{{ $slogan }}</p>
            @endif

            @if ($lineasIdentidad !== [])
                <div class="reporte-membrete__identidad">
                    @foreach ($lineasIdentidad as $linea)
                        <p class="reporte-membrete__linea">
                            @foreach ($linea as $indice => $dato)
                                <span class="reporte-membrete__dato">
                                    @if ($indice > 0)
                                        <span class="reporte-membrete__sep" aria-hidden="true">&middot;</span>
                                    @endif
                                    @if (! empty($dato['etiqueta']))
                                        <span class="reporte-membrete__etiqueta">{{ $dato['etiqueta'] }}</span>
                                    @endif
                                    <span class="reporte-membrete__valor">{{ $dato['valor'] }}</span>
                                </span>
                            @endforeach
                        </p>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="reporte-membrete__emision">
            <p class="reporte-membrete__tipo">{{ $badge ?? ($tipoDocumento ?? 'Documento Contable') }}</p>
            <dl class="reporte-membrete__ficha">
                <dt>Fecha de emisión</dt>
                <dd x-text="fechaHoraImpresion || '{{ $fechaEmision }}'">{{ $fechaEmision }}</dd>
                <dt>Elaborado por</dt>
                <dd>{{ $elaboradoPor }}</dd>
            </dl>
        </div>
    </div>

    <div class="reporte-membrete__filete" aria-hidden="true"></div>

    @if ($titulo)
        <div class="reporte-membrete__documento">
            <h2 class="reporte-membrete__titulo">{{ $titulo }}</h2>
            @if ($subtitulo)
                <p class="reporte-membrete__secundario">{{ $subtitulo }}</p>
            @endif
        </div>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="reporte-membrete__anexo">
            {{ $slot }}
        </div>
    @endif
</div>
