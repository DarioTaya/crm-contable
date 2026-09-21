@props([
    'label' => null,
    'name',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-xs font-semibold text-gray-700">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->class([
            'block w-full rounded-control border bg-white px-3 py-2 text-sm text-gray-900 shadow-xs',
            'placeholder:text-gray-400 focus:ring-2 focus:ring-brand-500/30',
            $error
                ? 'border-danger-300 focus:border-danger-500'
                : 'border-gray-300 focus:border-brand-500',
        ]) }}
    >

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1 text-xs font-medium text-danger-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
