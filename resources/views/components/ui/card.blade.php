@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
])

<div {{ $attributes->class('rounded-panel border border-gray-200 bg-surface shadow-card') }}>
    @if ($title || isset($actions))
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="truncate text-sm font-semibold text-gray-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding ? 'p-5' : '' }}">{{ $slot }}</div>
</div>
