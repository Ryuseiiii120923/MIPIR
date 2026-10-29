<?php

use App\Dashboard\Exports\CycleTimeReportExport;
use App\Dashboard\Services\CycleTime\CycleTimeReportService;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $plant = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $mode = 'ppf'; // 'ppf' | 'part'

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->toDateString();
    }

    public function updatedDateFrom(): void
    {
        $this->validate($this->dateRules());
    }

    public function updatedDateTo(): void
    {
        $this->validate($this->dateRules());
    }

    #[Computed]
    public function plants()
    {
        return app(CycleTimeReportService::class)->plants();
    }

    #[Computed]
    public function rows()
    {
        if (Validator::make($this->filters(), $this->dateRules())->fails()) {
            return collect();
        }

        $service = app(CycleTimeReportService::class);

        return $this->mode === 'part'
            ? $service->partRows($this->filters())
            : $service->ppfRows($this->filters());
    }

    public function exportExcel()
    {
        $this->validate($this->dateRules());

        $rows = $this->rows;
        $mode = $this->mode;
        $name = 'cycle-time-manhr_' . $mode . '_' . $this->dateFrom . '_to_' . $this->dateTo . '.xlsx';

        return response()->streamDownload(
            fn () => app(CycleTimeReportExport::class)->write($rows, $mode),
            $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function filters(): array
    {
        return [
            'dateFrom' => $this->dateFrom,
            'dateTo'   => $this->dateTo,
            'plant'    => $this->plant,
            'search'   => $this->search,
        ];
    }

    private function dateRules(): array
    {
        return [
            'dateFrom' => ['required', 'date'],
            'dateTo'   => ['required', 'date', 'after_or_equal:dateFrom'],
        ];
    }
};
?>

<div class="w-full mx-auto p-6">
    <div>
        <h2 class="text-lg font-semibold">Cycle Time / Man-hr</h2>
        <p class="text-sm text-gray-400">Cycle time and man-hours per finished PPF or part number.</p>
    </div>

    {{-- Search + filters --}}
    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div>
            <label for="ct-search" class="block text-sm font-medium text-gray-700 mb-1">Search PPF or Part Number</label>
            <input type="text" id="ct-search" wire:model.live.debounce.400ms="search"
                class="w-80 rounded-full border border-gray-300 bg-gray-100 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="PPF or part no">
        </div>
        <div>
            <label for="ct-plant" class="block text-sm font-medium text-gray-700 mb-1">Plant</label>
            <select id="ct-plant" wire:model.live="plant"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All plants</option>
                @foreach ($this->plants as $p)
                <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ct-from" class="block text-sm font-medium text-gray-700 mb-1">PPF End (from)</label>
            <input type="date" id="ct-from" wire:model.live="dateFrom"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 focus:border-indigo-500 focus:ring-indigo-500">
            @error('dateFrom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="ct-to" class="block text-sm font-medium text-gray-700 mb-1">PPF End (to)</label>
            <input type="date" id="ct-to" wire:model.live="dateTo"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 focus:border-indigo-500 focus:ring-indigo-500">
            @error('dateTo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="ct-mode" class="block text-sm font-medium text-gray-700 mb-1">View</label>
            <select id="ct-mode" wire:model.live="mode"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="ppf">Per PPF</option>
                <option value="part">Per Part No</option>
            </select>
        </div>
        <div>
            <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                class="px-4 py-2 text-sm font-medium text-white bg-green-700 rounded-full hover:bg-green-600 disabled:opacity-50">
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Exporting...</span>
            </button>
        </div>
    </div>

    {{-- Report table --}}
    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm text-center text-gray-700">
            <thead class="bg-gray-50 border-b border-gray-200">
                @if ($mode === 'part')
                <tr>
                    <th class="px-4 py-3 font-medium">Part No</th>
                    <th class="px-4 py-3 font-medium">No. of PPF</th>
                    <th class="px-4 py-3 font-medium">Check Times</th>
                    <th class="px-4 py-3 font-medium">Total Cycle (hr)</th>
                    <th class="px-4 py-3 font-medium">Avg Cycle (hr)</th>
                    <th class="px-4 py-3 font-medium">Total Man-hr</th>
                    <th class="px-4 py-3 font-medium">Avg Man-hr</th>
                    <th class="px-4 py-3 font-medium">Last PPF End</th>
                </tr>
                @else
                <tr>
                    <th class="px-4 py-3 font-medium">PPF</th>
                    <th class="px-4 py-3 font-medium">Part No</th>
                    <th class="px-4 py-3 font-medium">Plant</th>
                    <th class="px-4 py-3 font-medium">Machine</th>
                    <th class="px-4 py-3 font-medium">Date Start</th>
                    <th class="px-4 py-3 font-medium">PPF End</th>
                    <th class="px-4 py-3 font-medium">Check Times</th>
                    <th class="px-4 py-3 font-medium">Inspectors</th>
                    <th class="px-4 py-3 font-medium">Cycle Time(PER PPF)</th>
                    <th class="px-4 py-3 font-medium">Man-hr</th>
                </tr>
                @endif
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($this->rows as $r)
                @if ($mode === 'part')
                <tr wire:key="part-{{ $r['part_no'] }}" class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $r['part_no'] }}</td>
                    <td class="px-4 py-3">{{ $r['ppf_count'] }}</td>
                    <td class="px-4 py-3">{{ $r['checks_done'] }}</td>
                    <td class="px-4 py-3">{{ $r['total_cycle_hr'] }}</td>
                    <td class="px-4 py-3">{{ $r['avg_cycle_hr'] }}</td>
                    <td class="px-4 py-3">{{ $r['total_man_hr'] }}</td>
                    <td class="px-4 py-3">{{ $r['avg_man_hr'] }}</td>
                    <td class="px-4 py-3">{{ $r['last_ended_at'] }}</td>
                </tr>
                @else
                <tr wire:key="ppf-{{ $r['ppf_no'] }}" class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $r['ppf_no'] }}</td>
                    <td class="px-4 py-3">{{ $r['part_no'] }}</td>
                    <td class="px-4 py-3">{{ $r['plant'] ?: '-' }}</td>
                    <td class="px-4 py-3">{{ $r['machine_no'] ?: '-' }}</td>
                    <td class="px-4 py-3">{{ $r['started_at'] }}</td>
                    <td class="px-4 py-3">{{ $r['ended_at'] }}</td>
                    <td class="px-4 py-3">{{ $r['checks_done'] }}</td>
                    <td class="px-4 py-3">{{ $r['inspectors'] }}</td>
                    <td class="px-4 py-3">
                        <span class="font-medium text-gray-900">{{ $r['cycle_hr'] }} hr</span>
                        <span class="block text-xs text-gray-400">{{ $r['cycle_hms'] }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-medium text-gray-900">{{ $r['man_hr'] }} hr</span>
                        <span class="block text-xs text-gray-400">{{ $r['man_hms'] }}</span>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">No finished PPF found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>