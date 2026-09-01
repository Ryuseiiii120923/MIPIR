<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

{{-- Column 3: qty + small defects for the currently selected large defect.
     Expects: $modalSelectedType, $modalLargeQty, $modalSmallDefects --}}
<div class="w-1/3 px-4 py-4 flex flex-col gap-3 h-full">
    @if($modalSelectedType)
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest shrink-0">
            Configure: <span class="text-blue-600 normal-case font-bold">{{ $modalSelectedType }}</span>
        </p>

        {{-- Stage this defect button (pinned, does not scroll) --}}
        <button
            type="button"
            wire:click="stageDefect"
            class="w-full px-4 py-2 rounded-lg border-2 border-blue-500 text-blue-600 text-sm font-semibold hover:bg-blue-50 transition shrink-0">
            + Stage This Defect
        </button>

        {{-- Scrollable content area --}}
        <div class="flex-1 min-h-0 overflow-y-auto flex flex-col gap-3">
            {{-- Large defect qty --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Quantity for <span class="text-blue-600">{{ $modalSelectedType }}</span>
                </label>
                <input
                    type="number"
                    min="1"
                    wire:model="modalLargeQty"
                    class="block w-full border border-gray-300 rounded-md px-3 py-2 text-gray-900 focus:ring-2 focus:ring-blue-400 focus:outline-none bg-white"
                    placeholder="Enter quantity">
                @error('modalLargeQty')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Small defects --}}
            @if($modalSmallDefects && count($modalSmallDefects) > 0)
            <div class="flex flex-col gap-2">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">
                    Small Defects <span class="normal-case font-normal">(optional)</span>
                </p>
                @foreach($modalSmallDefects as $index => $small)
                <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-lg px-3 py-2"
                    wire:key="modal-small-{{ $index }}">
                    <span class="flex-1 text-sm text-gray-700">{{ $small['type'] }}</span>
                    <input
                        type="number"
                        min="0"
                        wire:model="modalSmallDefects.{{ $index }}.qty"
                        class="w-16 border border-gray-300 rounded-md px-2 py-1 text-sm text-gray-900 focus:ring-2 focus:ring-blue-400 focus:outline-none"
                        placeholder="Qty">
                </div>
                @endforeach

                {{-- Live small total indicator --}}
                <div class="text-right text-xs text-gray-500">
                    Small total:
                    <span class="font-semibold {{ collect($modalSmallDefects)->sum(fn($s) => (int)($s['qty'] ?: 0)) > (int)($modalLargeQty ?: 0) ? 'text-red-500' : 'text-green-600' }}">
                        {{ collect($modalSmallDefects)->sum(fn($s) => (int)($s['qty'] ?: 0)) }}
                    </span>
                    / {{ $modalLargeQty ?: '—' }}
                </div>
                @error('modalSmallDefects')
                <p class="text-red-500 text-sm">{{ $message }}</p>
                @enderror
            </div>
            @endif
        </div>

    @else
        <div class="flex-1 flex items-center justify-center text-center text-gray-300 text-sm px-2">
            <p>Select a defect type in the middle to configure quantity.</p>
        </div>
    @endif
</div>