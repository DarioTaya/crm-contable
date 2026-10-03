@php
    $marca = $identidadMarca ?? \App\Support\IdentidadMarca::porDefecto();
@endphp
<style data-identidad-marca>
{!! $marca->bloqueCss() !!}
</style>
