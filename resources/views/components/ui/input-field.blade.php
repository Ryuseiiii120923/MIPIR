@props([
    'id',
    'label',
    'type' => 'text',
])

<div>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1.5">{{ $label }}</label>
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 focus:ring-2 focus:ring-offset-0 text-sm py-2.5 px-3.5 transition']) }}
    >
</div>