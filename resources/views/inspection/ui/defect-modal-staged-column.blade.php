<?php

use Livewire\Component;

new class extends Component
{
    
};
?>

{{-- Column 1: list of defects staged so far. Expects: $staged --}}
<div class="w-1/3 border-r border-gray-200 px-4 py-4 flex flex-col gap-3">
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest shrink-0">
        Staged
        @if(count($staged) > 0)
        <span class="ml-1 text-blue-600">({{ count($staged) }})</span>
        @endif
    </p>

    @if(count($staged) > 0)
        @foreach($staged as $stage)
        <div class="flex items-start justify-between gap-2 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-gray-800">{{ $stage['type'] }}</span>
                    <span class="text-xs bg-blue-100 text-blue-700 font-bold px-2 py-0.5 rounded-full">qty: {{ $stage['qty'] }}</span>
                </div>
            </div>
            <div class="flex flex-col gap-1 shrink-0">
                <button
                    type="button"
                    wire:click="selectLargeDefect('{{ $stage['type'] }}')"
                    class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 rounded hover:bg-blue-100 transition">
                    Edit
                </button>
                <button
                    type="button"
                    wire:click="removeStagedDefect('{{ $stage['type'] }}')"
                    class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition">
                    ✕
                </button>
            </div>
        </div>
        @endforeach
    @else
        <div class="flex-1 flex items-center justify-center text-center text-gray-300 text-sm px-2">
            <p>No defects staged yet.</p>
        </div>
    @endif
</div>