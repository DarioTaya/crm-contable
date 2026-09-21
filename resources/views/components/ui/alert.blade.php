@props([
    'variant' => 'info',
    'dismissible' => false,
    'timeout' => null,
])

@php
    $variants = [
        'success' => ['border-success-200 bg-success-50/90 text-success-700', 'text-success-600', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'danger' => ['border-danger-200 bg-danger-50/90 text-danger-700', 'text-danger-600', 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z'],
        'warning' => ['border-warning-200 bg-warning-50/90 text-warning-700', 'text-warning-600', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'],
        'info' => ['border-info-200 bg-info-50/90 text-info-700', 'text-info-600', 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z'],
    ];

    [$shell, $iconTone, $path] = $variants[$variant] ?? $variants['info'];
@endphp

<div
    @if ($dismissible || $timeout)
        x-data="{ show: true }"
        x-show="show"
        @if ($timeout) x-init="setTimeout(() => show = false, {{ $timeout }})" @endif
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-end="opacity-0 -translate-y-2"
    @endif
    role="alert"
    {{ $attributes->class(['flex items-center justify-between gap-3 rounded-panel border px-4 py-3.5 text-sm shadow-card backdrop-blur', $shell]) }}
>
    <div class="flex items-center gap-3">
        <svg class="h-5 w-5 shrink-0 {{ $iconTone }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
        </svg>
        <span class="font-medium leading-relaxed">{{ $slot }}</span>
    </div>

    @if ($dismissible || $timeout)
        <button type="button" @click="show = false"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-control transition hover:bg-black/5"
                aria-label="Cerrar notificación">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
