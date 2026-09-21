@props([
    'variant' => 'neutral',
    'dot' => false,
])

@php
    $variants = [
        'neutral' => 'bg-gray-100 text-gray-700 ring-gray-200',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'success' => 'bg-success-50 text-success-700 ring-success-200',
        'warning' => 'bg-warning-50 text-warning-700 ring-warning-200',
        'danger' => 'bg-danger-50 text-danger-700 ring-danger-200',
        'info' => 'bg-info-50 text-info-700 ring-info-200',
        'purple' => 'bg-purple-50 text-purple-700 ring-purple-200',
    ];

    $dots = [
        'neutral' => 'bg-gray-400',
        'brand' => 'bg-brand-500',
        'success' => 'bg-success-500',
        'warning' => 'bg-warning-500',
        'danger' => 'bg-danger-500',
        'info' => 'bg-info-500',
        'purple' => 'bg-purple-500',
    ];

    $variant = isset($variants[$variant]) ? $variant : 'neutral';
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset whitespace-nowrap',
    $variants[$variant],
]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dots[$variant] }}"></span>
    @endif
    {{ $slot }}
</span>
