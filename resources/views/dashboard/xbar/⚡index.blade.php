<?php

use App\Inspection\Repositories\XBarRepository;
use App\Inspection\Services\XBarChartService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    // protected XBarRepository $repository;
    // protected XBarChartService $chartService;

    public string $partSearch = '';
    public ?string $selectedPartNo = null;

    public string $dimensionSearch = '';
    public ?int $selectedDimensionId = null;
    public ?string $selectedDimensionName = null;

    public bool $generating = false;
    public array $chartData = [];

    // public function boot(XBarRepository $repository, XBarChartService $chartService): void
    // {
    //     $this->repository = $repository;
    //     $this->chartService = $chartService;
    // }

    // #[Computed]
    // public function partNumbers()
    // {
    //     return $this->repository->searchPartNumbers($this->partSearch);
    // }

    // #[Computed]
    // public function dimensions()
    // {
    //     if (! $this->selectedPartNo) {
    //         return collect();
    //     }

    //     return $this->repository->searchDimensionsForPart($this->selectedPartNo, $this->dimensionSearch);
    // }

    // public function selectPart(string $partNo): void
    // {
    //     $this->selectedPartNo = $partNo;
    //     $this->selectedDimensionId = null;
    //     $this->selectedDimensionName = null;
    //     $this->dimensionSearch = '';
    //     $this->chartData = [];
    // }

    // public function changePart(): void
    // {
    //     $this->reset([
    //         'selectedPartNo',
    //         'selectedDimensionId',
    //         'selectedDimensionName',
    //         'dimensionSearch',
    //         'chartData',
    //     ]);
    // }

    // public function selectDimension(int $dimensionId, string $dimensionName): void
    // {
    //     $this->selectedDimensionId = $dimensionId;
    //     $this->selectedDimensionName = $dimensionName;
    //     $this->chartData = [];
    // }

    // public function changeDimension(): void
    // {
    //     $this->reset(['selectedDimensionId', 'selectedDimensionName', 'chartData']);
    // }

    // public function generate(): void
    // {
    //     $this->validate([
    //         'selectedPartNo'       => 'required|string',
    //         'selectedDimensionId'  => 'required|integer',
    //     ], [
    //         'selectedPartNo.required'      => 'Please select a part number first.',
    //         'selectedDimensionId.required' => 'Please select a dimension first.',
    //     ]);

    //     $this->generating = true;

    //     $this->chartData = $this->chartService->generate(
    //         $this->selectedPartNo,
    //         $this->selectedDimensionId
    //     );

    //     $this->generating = false;
    // }
};
?>

<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-800">X-Bar Chart Generator</h2>
                @if ($selectedPartNo)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                        {{ $selectedPartNo }}
                    </span>
                @endif
                @if ($selectedDimensionName)
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">
                        {{ $selectedDimensionName }}
                    </span>
                @endif
            </div>
        </div>

        {{-- STEP 1: PART NUMBER --}}
        @if (! $selectedPartNo)
            <div class="p-4 border-b border-gray-100">
                <div class="relative w-full sm:w-72">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                    </svg>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="partSearch"
                        placeholder="Search part number..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                            <th class="p-3 font-medium">Part No</th>
                            <th class="p-3 font-medium">Description</th>
                            <th class="p-3 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->partNumbers as $part)
                            <tr wire:key="part-{{ $part->PartNo }}" class="hover:bg-emerald-50/60 transition-colors duration-150">
                                <td class="p-3 font-medium text-gray-800">{{ $part->PartNo }}</td>
                                <td class="p-3 text-gray-600">{{ $part->Description ?? '—' }}</td>
                                <td class="p-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="selectPart(@js($part->PartNo))"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors duration-150">
                                        Select
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-10 text-center">
                                    <div class="flex flex-col items-center gap-2 text-gray-400">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                                        </svg>
                                        <p class="text-sm">
                                            {{ $partSearch ? 'No part numbers match your search.' : 'Start typing to search part numbers.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- STEP 2: DIMENSION --}}
        @if ($selectedPartNo && ! $selectedDimensionId)
            <div class="flex items-center justify-between gap-3 p-4 border-b border-gray-100">
                <div class="relative w-full sm:w-72">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                    </svg>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="dimensionSearch"
                        placeholder="Search dimension..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>
                <button
                    type="button"
                    wire:click="changePart"
                    class="text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap">
                    ← Change part
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                            <th class="p-3 font-medium">Dimension</th>
                            <th class="p-3 font-medium">Device</th>
                            <th class="p-3 font-medium">Spec</th>
                            <th class="p-3 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->dimensions as $dimension)
                            <tr wire:key="dim-{{ $dimension->id }}" class="hover:bg-emerald-50/60 transition-colors duration-150">
                                <td class="p-3 font-medium text-gray-800">{{ $dimension->DimensionName }}</td>
                                <td class="p-3 text-gray-600">{{ $dimension->Device }}</td>
                                <td class="p-3 text-gray-600">
                                    {{ $dimension->Specification }}
                                    <span class="text-gray-400">({{ $dimension->LowerLimit }} ~ {{ $dimension->UpperLimit }})</span>
                                </td>
                                <td class="p-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="selectDimension({{ $dimension->id }}, @js($dimension->DimensionName))"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors duration-150">
                                        Select
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-10 text-center">
                                    <div class="flex flex-col items-center gap-2 text-gray-400">
                                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="text-sm">
                                            {{ $dimensionSearch ? 'No dimensions match your search.' : 'No dimensions found for this part.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- STEP 3: GENERATE --}}
        @if ($selectedPartNo && $selectedDimensionId)
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        Ready to generate the X-bar/R chart for
                        <span class="font-semibold text-gray-800">{{ $selectedPartNo }}</span> —
                        <span class="font-semibold text-gray-800">{{ $selectedDimensionName }}</span>.
                    </p>
                    <button
                        type="button"
                        wire:click="changeDimension"
                        class="text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap">
                        ← Change dimension
                    </button>
                </div>

                <button
                    type="button"
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors duration-150">
                    <svg wire:loading wire:target="generate" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Generate X-Bar Chart
                </button>

                @if (! empty($chartData))
                    <div class="mt-4 p-4 rounded-lg bg-gray-50 border border-gray-100 text-sm text-gray-600">
                        <p class="font-medium text-gray-800 mb-2">Result summary</p>
                        <p>Grand Mean (X̿): {{ $chartData['grandMean'] ?? '—' }}</p>
                        <p>Average Range (R̄): {{ $chartData['averageRange'] ?? '—' }}</p>
                        <p>X-bar UCL / LCL: {{ $chartData['xBarUcl'] ?? '—' }} / {{ $chartData['xBarLcl'] ?? '—' }}</p>
                        <p>R UCL / LCL: {{ $chartData['rUcl'] ?? '—' }} / {{ $chartData['rLcl'] ?? '—' }}</p>
                        {{-- Plug chartData['subgroups'] into your charting lib (Chart.js/Alpine canvas) here --}}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>