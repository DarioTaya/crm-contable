@props([
    'name',
    'title' => null,
    'maxWidth' => '2xl',
])

{{--
    Modal accesible controlado por eventos de Alpine. Se abre desde cualquier
    punto de la pagina con $dispatch('open-modal', 'nombre') y se cierra con
    Escape, clic en el fondo o $dispatch('close-modal', 'nombre').
--}}
@php
    $widths = [
        'sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg',
        'xl' => 'max-w-xl', '2xl' => 'max-w-2xl', '4xl' => 'max-w-4xl',
    ];
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') { open = true }"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') { open = false }"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif
>
    <div x-show="open" x-transition.opacity
         x-on:click="open = false"
         class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs"
         aria-hidden="true"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 sm:scale-95"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-end="opacity-0 translate-y-3 sm:scale-95"
         {{ $attributes->class(['relative my-8 w-full rounded-overlay bg-surface shadow-overlay', $widths[$maxWidth] ?? $widths['2xl']]) }}>

        @if ($title)
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4">
                <h2 id="modal-{{ $name }}-title" class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                <x-ui.button variant="ghost" size="sm" icon x-on:click="open = false" aria-label="Cerrar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </x-ui.button>
            </div>
        @endif

        <div class="px-6 py-5">{{ $slot }}</div>

        @isset($footer)
            <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50/70 px-6 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
