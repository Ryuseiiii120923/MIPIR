@php
    $hasData = count($defects) > 0 || collect($shots)->contains(fn ($s) => count($s['defects'] ?? []) > 0);
@endphp

<div class="flex items-center justify-center gap-2">
    @foreach (['normal' => 'Normal', 'tightened' => 'Tightened'] as $value => $label)
    <button type="button"
        wire:key="mode-{{ $value }}"
        wire:click="setMode('{{ $value }}')"
        @if($readonly) disabled @endif
        @if($mode !== $value && $hasData) wire:confirm="Changing the mode will clear the defects of this check time. Continue?" @endif
        class="px-5 py-2 rounded-lg border text-sm font-semibold transition disabled:cursor-not-allowed
            {{ $mode === $value
                ? 'border-[#0F3C89] bg-[#0F3C89] text-white'
                : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50' }}">
        {{ $label }}
    </button>
    @endforeach
</div>