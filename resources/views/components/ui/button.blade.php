@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => false,
])

@php
    $variants = [
        'primary' => 'border border-transparent bg-brand-600 text-white shadow-xs hover:bg-brand-700 focus-visible:outline-brand-600',
        'secondary' => 'border border-gray-200 bg-white text-gray-700 shadow-xs hover:bg-gray-50 hover:text-gray-900',
        'danger' => 'border border-transparent bg-danger-600 text-white shadow-xs hover:bg-danger-700 focus-visible:outline-danger-600',
        'success' => 'border border-transparent bg-success-600 text-white shadow-xs hover:bg-success-700 focus-visible:outline-success-600',
        'subtle' => 'border border-transparent bg-brand-50 text-brand-700 hover:bg-brand-100',
        'ghost' => 'border border-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900',
    ];

    $sizes = $icon
        ? ['sm' => 'h-7 w-7', 'md' => 'h-9 w-9', 'lg' => 'h-10 w-10']
        : ['sm' => 'px-2.5 py-1 text-xs', 'md' => 'px-3.5 py-2 text-sm', 'lg' => 'px-5 py-2.5 text-sm'];

    $classes = implode(' ', [
        'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-control font-semibold',
        'transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
