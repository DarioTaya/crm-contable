@props(['head' => null])

{{-- Contenedor estandar de tabla: un solo tratamiento de borde, cabecera y
     scroll horizontal para toda la aplicacion. --}}
<div {{ $attributes->class('overflow-hidden rounded-panel border border-gray-200 bg-surface shadow-card') }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-data">
            @isset($head)
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ $head }}
                    </tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-gray-100 text-gray-700">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="border-t border-gray-200 bg-gray-50/70 px-4 py-3">{{ $footer }}</div>
    @endisset
</div>
