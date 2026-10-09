<?php

use App\Dashboard\Services\DashboardSummaryService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $mode = '';   // '' | normal | tightened
    public string $judge = '';  // '' | OK | NG
    public ?int $selectedPpf = null;
    public ?string $selectedCheckTime = null;

    #[Computed]
    public function ppfs()
    {
        return app(DashboardSummaryService::class)->ppfs($this->search, $this->mode, $this->judge);
    }

    #[Computed]
    public function checkTimes(): array
    {
        return $this->selectedPpf
            ? app(DashboardSummaryService::class)->checkTimes($this->selectedPpf)
            : [];
    }

    #[Computed]
    public function detail(): ?array
    {
        return ($this->selectedPpf && $this->selectedCheckTime)
            ? app(DashboardSummaryService::class)->detail($this->selectedPpf, $this->selectedCheckTime)
            : null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMode(): void
    {
        $this->resetPage();
    }

    public function updatedJudge(): void
    {
        $this->resetPage();
    }

    public function checkPpf(int $ppf): void
    {
        $this->selectedPpf = $ppf;
        $this->selectedCheckTime = $this->checkTimes[0]['checkTime'] ?? null;
    }

    public function selectCheckTime(string $checkTime): void
    {
        $this->selectedCheckTime = $checkTime;
    }
};
?>

@php
    $judgeBadge = [
        'OK' => 'bg-green-100 text-green-700',
        'NG' => 'bg-red-100 text-red-700',
    ];
    $modeBadge = [
        'normal'    => 'bg-blue-100 text-blue-700',
        'tightened' => 'bg-amber-100 text-amber-700',
    ];
@endphp

<div class="w-full mx-auto p-6">
    <div>
        <h2 class="text-lg font-semibold">Dashboard Summary</h2>
        <p class="text-sm text-gray-400">Check times, judgement, inspector and measurements per PPF.</p>
    </div>

    {{-- Search + filters --}}
    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div>
            <label for="ppf-search" class="block text-sm font-medium text-gray-700 mb-1">Search PPF or Part Number</label>
            <input type="text" id="ppf-search" wire:model.live.debounce.400ms="search"
                class="w-80 rounded-full border border-gray-300 bg-gray-100 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="PPF or part no">
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700 mb-1">Inspection</span>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm">
                @foreach (['' => 'All', 'normal' => 'Normal', 'tightened' => 'Tightened'] as $value => $label)
                <button type="button" wire:click="$set('mode', '{{ $value }}')"
                    @class(['px-3 py-2', 'bg-indigo-600 text-white' => $mode === $value, 'bg-white text-gray-600 hover:bg-gray-50' => $mode !== $value])>
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700 mb-1">Judgement</span>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm">
                @foreach (['' => 'All', 'OK' => 'OK', 'NG' => 'NG'] as $value => $label)
                <button type="button" wire:click="$set('judge', '{{ $value }}')"
                    @class(['px-3 py-2', 'bg-indigo-600 text-white' => $judge === $value, 'bg-white text-gray-600 hover:bg-gray-50' => $judge !== $value])>
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- PPF table --}}
    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm text-center text-gray-700">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 font-medium">PPF</th>
                    <th class="px-4 py-3 font-medium">Part No</th>
                    <th class="px-4 py-3 font-medium">Date Start</th>
                    <th class="px-4 py-3 font-medium">Date End</th>
                    <th class="px-4 py-3 font-medium">Check Times</th>
                    <th class="px-4 py-3 font-medium">Inspection</th>
                    <th class="px-4 py-3 font-medium">Judgement</th>
                    <th class="px-4 py-3 font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($this->ppfs as $ppf)
                <tr wire:key="ppf-{{ $ppf->PPFNo }}" @class(['hover:bg-gray-50', 'bg-indigo-50/60' => $selectedPpf === (int) $ppf->PPFNo])>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $ppf->PPFNo }}</td>
                    <td class="px-4 py-3">{{ $ppf->PartNo }}</td>
                    <td class="px-4 py-3">{{ $ppf->dateStart }}</td>
                    <td class="px-4 py-3">{{ $ppf->dateEnd }}</td>
                    <td class="px-4 py-3">{{ $ppf->checkTimes ?: '-' }}</td>
                    <td class="px-4 py-3">
                        @if ($ppf->mode)
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $modeBadge[$ppf->mode] }}">{{ ucfirst($ppf->mode) }}</span>
                        @else - @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($ppf->judgement)
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $judgeBadge[$ppf->judgement] }}">{{ $ppf->judgement }}</span>
                        @else - @endif
                    </td>
                    <td class="px-4 py-3">
                        <button type="button" wire:click="checkPpf({{ $ppf->PPFNo }})"
                            wire:loading.attr="disabled" wire:target="checkPpf({{ $ppf->PPFNo }})"
                            class="px-3 py-1 text-xs font-medium text-white bg-gray-800 rounded-full hover:bg-gray-700 disabled:opacity-50">
                            Check
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">No PPF found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $this->ppfs->links() }}</div>
    </div>

    {{-- Check times + detail --}}
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Check Time / Date Encode --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white self-start">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="bg-gray-100 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 font-medium">Check Time</th>
                        <th class="px-4 py-3 font-medium">Date Encode</th>
                        <th class="px-4 py-3 font-medium">Inspection</th>
                        <th class="px-4 py-3 font-medium">Judgement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($this->checkTimes as $ct)
                    <tr wire:key="ct-{{ $ct['checkTime'] }}" wire:click="selectCheckTime('{{ $ct['checkTime'] }}')"
                        @class(['cursor-pointer hover:bg-gray-50', 'bg-indigo-50/60' => $selectedCheckTime === $ct['checkTime']])>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $ct['checkTime'] }}</td>
                        <td class="px-4 py-3">{{ $ct['dateEnc'] }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $modeBadge[$ct['mode']] ?? $modeBadge['normal'] }}">{{ ucfirst($ct['mode']) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($ct['judgement'])
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $judgeBadge[$ct['judgement']] }}">{{ $ct['judgement'] }}</span>
                            @else - @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                            {{ $selectedPpf ? 'No check time recorded for this PPF.' : 'Press Check on a PPF to see its check times.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Detail panel --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 min-h-48">
            @if ($this->detail)
            @php $d = $this->detail; @endphp

            <div class="flex flex-wrap items-center gap-2 mb-4">
                <h3 class="font-semibold text-gray-800">PPF {{ $selectedPpf }} · {{ $d['checkTime'] }}</h3>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $modeBadge[$d['mode']] }}">{{ ucfirst($d['mode']) }}</span>
                @if ($d['judgement'])
                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $judgeBadge[$d['judgement']] }}">{{ $d['judgement'] }}</span>
                @endif
            </div>

            <dl class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm mb-5">
                <div><dt class="text-gray-400">Time Start</dt><dd class="font-medium text-gray-800">{{ $d['timeStart'] }}</dd></div>
                <div><dt class="text-gray-400">Time End</dt><dd class="font-medium text-gray-800">{{ $d['timeEnd'] }}</dd></div>
                <div><dt class="text-gray-400">Inspected By</dt><dd class="font-medium text-gray-800">{{ $d['inspector'] }}</dd></div>
                <div><dt class="text-gray-400">Part No</dt><dd class="font-medium text-gray-800">{{ $d['partNo'] }}</dd></div>
                <div><dt class="text-gray-400">Lot No</dt><dd class="font-medium text-gray-800">{{ $d['lotNo'] }}</dd></div>
                <div><dt class="text-gray-400">Machine No</dt><dd class="font-medium text-gray-800">{{ $d['machineNo'] }}</dd></div>
            </dl>

            <div class="space-y-4">
                @foreach ($d['dimensions'] as $dim)
                <div wire:key="dim-{{ $loop->index }}" class="rounded-lg border border-gray-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <div>
                            <p class="text-sm font-medium text-gray-800">
                                {{ $dim['item'] }}
                                @if ($dim['forXBar'])
                                <span class="ms-1 text-xs font-medium px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">X-Bar</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">Spec: {{ $dim['specification'] }} · CL: {{ $dim['cl'] }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $modeBadge[$dim['mode']] }}">{{ ucfirst($dim['mode']) }}</span>
                            @if ($dim['judgement'])
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $judgeBadge[$dim['judgement']] }}">{{ $dim['judgement'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        @foreach ($dim['sets'] as $set)
                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                            @if (count($dim['sets']) > 1)
                            <span class="w-12 text-xs text-gray-400">Set {{ $set['set'] }}</span>
                            @endif

                            @forelse ($set['values'] as $position => $value)
                            <span class="border rounded-lg px-2 py-1">{{ $position }}: {{ $value }}</span>
                            @empty
                            <span class="text-gray-400">No measurements.</span>
                            @endforelse

                            @if ($set['judgement'])
                            <span class="ms-auto text-xs font-medium px-2 py-0.5 rounded-full {{ $judgeBadge[$set['judgement']] }}">{{ $set['judgement'] }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400">Select a check time to see the details and measurements.</p>
            @endif
        </div>
    </div>
</div>