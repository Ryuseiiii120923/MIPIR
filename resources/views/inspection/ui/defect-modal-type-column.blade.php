<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

{{-- Column 2: pick a large defect type. Expects: $Largedefects, $modalSelectedLargeDefect, $staged, $locked --}}
<div class="w-1/3 border-r border-gray-200 px-4 py-4 flex flex-col gap-3">
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest shrink-0">
        {{ count($staged) > 0 ? 'Add Another Defect Type' : 'Defect Type' }}
    </p>
    <div class="flex flex-col gap-2">
        @foreach ($largeDefectMaster as $Ldefects)
        @php
            $isActive = $modalSelectedType=== $Ldefects->LargeDefect;
            $isStaged = collect($staged)->contains('type', $Ldefects->LargeDefect);
        @endphp
        <button
            type="button"
            wire:click="selectLargeDefect('{{ $Ldefects->LargeDefect }}')"
            class="w-full px-3 py-2 rounded-lg border text-sm font-medium text-left transition relative
            @if($isActive)
                border-blue-600 bg-blue-50 text-blue-700 ring-2 ring-blue-300
            @elseif($isStaged)
                border-green-500 bg-green-50 text-green-700
            @else
                border-gray-200 bg-gray-50 text-gray-700 hover:border-blue-400 hover:bg-blue-50
            @endif">
            {{ $Ldefects->LargeDefect }}
            @if($isStaged && !$isActive)
            <span class="absolute top-1 right-1 w-2 h-2 bg-green-500 rounded-full"></span>
            @endif
        </button>
        @endforeach
    </div>
    @error('modalSelectedType')
    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>