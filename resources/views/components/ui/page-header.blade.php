@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class('mb-6 flex flex-wrap items-start justify-between gap-4') }}>
    <div class="min-w-0">
        <h1 class="text-xl font-bold tracking-tight text-gray-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
