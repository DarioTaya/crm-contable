@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'neutral',
])

{{-- Tarjeta de indicador. Reemplaza los bloques KPI duplicados entre la boveda
     SIRE, el expediente del cliente y el dashboard. --}}
@php
    $tones = [
        'neutral' => 'text-gray-900',
        'brand' => 'text-brand-700',
        'success' => 'text-success-700',
        'warning' => 'text-warning-700',
        'danger' => 'text-danger-700',
    ];
@endphp

<div {{ $attributes->class('rounded-panel border border-gray-200 bg-surface p-5 shadow-card') }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
    <p class="mt-2 text-metric font-bold tabular {{ $tones[$tone] ?? $tones['neutral'] }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
