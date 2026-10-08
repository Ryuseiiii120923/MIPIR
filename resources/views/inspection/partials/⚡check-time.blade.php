<?php


use App\Inspection\Actions\DraftAction;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;

new class extends \Livewire\Component
{
    #[Validate('required')]
    public string $checkTime = '';

    public array $checkTimes = [];

    public ?string $selectedCheckTime = null;
    public array $defectsByTime = [];
    public array $smallDefectsByTime = [];
    public array $ngpercentByTime = [];
    public array $judgementByTime = [];
    public array $dimensionsByTime = [];
    public array $remarksByTime = [];
    public array $dateEncodeByTime = [];
    public array $deletedCheckTime = [];
    public int $ppf = 0;
    public string $action = '';
    //Computation
    public array $dataFromMaster = [];
    public string $partNo = '';
    public string $lookupTime = '';
    public array $startTimeByTime = [];
    public array $endTimeByTime = [];
    public array $touchedThisSession = [];
    public array $controlLimitByTime = [];

    public bool $isMKA = false;

    public function mount(bool $isMKA = false): void
    {
        $this->isMKA = $isMKA;
    }

    public function addCheckTime(): void
    {
        $this->validate();

        $newCheckTime = $this->resolveCheckTimeLabel(str($this->checkTime)->upper());

        $this->checkTimes[] = $newCheckTime;
        $this->dateEncodeByTime[$newCheckTime] = now()->toDateTimeString();
        $this->startTimeByTime[$newCheckTime] = $this->lookupTime !== '' ? $this->lookupTime : now()->toDateTimeString();
        $this->endTimeByTime[$newCheckTime] = $this->startTimeByTime[$newCheckTime];
        $this->touchedThisSession[$newCheckTime] = true;

        $this->sortCheckTimesByDateEncode();

        $this->selectedCheckTime = $newCheckTime;

        $this->reset('checkTime');
        $this->resetErrorBag();

        $this->syncDraft();
    }

    private function sortCheckTimesByDateEncode(): void
    {
        usort($this->checkTimes, function (string $a, string $b) {
            $dateA = $this->dateEncodeByTime[$a] ?? '';
            $dateB = $this->dateEncodeByTime[$b] ?? '';
            return $dateA <=> $dateB;
        });
    }

    private function resolveCheckTimeLabel(string $base): string
    {
        if (!in_array($base, $this->checkTimes, true)) {
            return $base;
        }

        $suffix = 1;
        while (in_array($base . $suffix, $this->checkTimes, true)) {
            $suffix++;
        }

        return $base . $suffix;
    }


    public function syncDraft()
    {
        app(DraftAction::class)->put($this->ppf, 'check-time', [
            'check-time' => $this->checkTimes,
            'date-encode' => $this->dateEncodeByTime,
            'start-time' => $this->startTimeByTime,
            'end-time' => $this->endTimeByTime,
            'touched' => array_keys($this->touchedThisSession),
            'deleted' => $this->deletedCheckTime,
        ]);

        app(DraftAction::class)->put($this->ppf, 'defects', [
            'defects'    => $this->defectsByTime,
            'smallDefects' => $this->smallDefectsByTime,
            'ngPercent'  => $this->ngpercentByTime,
            'judgement'  => $this->judgementByTime,
        ]);

        app(DraftAction::class)->put($this->ppf, 'dimensions', $this->dimensionsByTime);
        app(DraftAction::class)->put($this->ppf, 'control-limit', $this->controlLimitByTime);

        app(DraftAction::class)->put($this->ppf, 'remarks', $this->remarksByTime);
    }

    public function selectCheckTime(string $time): void
    {
        $this->selectedCheckTime = $this->selectedCheckTime === $time ? null : $time;
    }

    public function removeCheckTime(string $time): void
    {
        $this->deletedCheckTime[] = [
            'time' => $time,
            'dimensions' => $this->dimensionsByTime[$time] ?? [],
        ];
        $this->checkTimes = array_values(array_diff($this->checkTimes, [$time]));
        unset($this->defectsByTime[$time]);
        unset($this->dimensionsByTime[$time]);
        unset($this->controlLimitByTime[$time]);
        unset($this->ngpercentByTime[$time]);
        unset($this->judgementByTime[$time]);
        unset($this->dateEncodeByTime[$time]);
        unset($this->remarksByTime[$time]);
        unset($this->startTimeByTime[$time]);
        unset($this->endTimeByTime[$time]);
        unset($this->touchedThisSession[$time]);
        if ($this->selectedCheckTime === $time) {
            $this->selectedCheckTime = null;
        }

        $this->syncDraft();
    }


    #[On('defects-synced')]
    public function onDefectsSynced(string $selectedCheckTime, array $defects, float $ngpercent, string $judgement, array $smallDefects): void
    {
        $this->defectsByTime[$selectedCheckTime] = $defects;
        $this->ngpercentByTime[$selectedCheckTime] = $ngpercent;
        $this->judgementByTime[$selectedCheckTime] = $judgement;
        $this->smallDefectsByTime[$selectedCheckTime] = $smallDefects;
        $this->endTimeByTime[$selectedCheckTime] = now()->toDateTimeString();
        $this->touchedThisSession[$selectedCheckTime] = true;
        $this->syncDraft();
    }

    #[On('dimensions-synced')]
    public function onDimensionsSynced(string $selectedCheckTime, array $rows): void
    {
        $this->dimensionsByTime[$selectedCheckTime] = $rows;
        $this->endTimeByTime[$selectedCheckTime] = now()->toDateTimeString();
        $this->touchedThisSession[$selectedCheckTime] = true;
        $this->syncDraft();
    }

    #[On('control-limit-synced')]
    public function onControlLimitSynced(array $draft): void
    {
        $this->controlLimitByTime[$this->selectedCheckTime] = [
            'dimItem' => $draft['dimItem'],
            'partNo'  => $draft['partNo'],
            'CSLx'    => $draft['CSLx'],
            'USLx'    => $draft['USLx'],
            'LSLx'    => $draft['LSLx'],
            'CCLx'    => $draft['CCLx'],
            'UCLx'    => $draft['UCLx'],
            'LCLx'    => $draft['LCLx'],
            'CSLr'    => $draft['CSLr'],
            'USLr'    => $draft['USLr'],
            'LSLr'    => $draft['LSLr'],
            'CCLr'    => $draft['CCLr'],
            'UCLr'    => $draft['UCLr'],
            'LCLr'    => $draft['LCLr'],
        ];
        $this->endTimeByTime[$this->selectedCheckTime] = now()->toDateTimeString();
        $this->touchedThisSession[$this->selectedCheckTime] = true;
        $this->syncDraft();
    }

    public function clear(): void
    {
        $this->reset([
            'checkTime',
            'checkTimes',
            'selectedCheckTime',
            'defectsByTime',
            'dimensionsByTime',
            'controlLimitByTime',
            'ngpercentByTime',
            'judgementByTime',
            'dateEncodeByTime',
            'remarksByTime'
        ]);
        $this->resetErrorBag();
    }

    #[On('action-changed')]
    public function onActionChanged(string $action): void
    {
        $this->action = $action;
        $this->ppf = 0;
        if ($action) {
            $this->clear();
        }
    }

    #[On('ppf-checked')]
    public function onPpfChecked(int $ppf): void
    {
        $this->clear();
        $this->ppf = $ppf;
        $this->lookupTime = now()->toDateTimeString(); // capture the moment this PPF was opened/looked up

        if ($this->action != 'add') {
            $result = app(PpfLookUpRepository::class)->getMainData($ppf);
            $this->checkTimes = $result['checkTime'] ?? [];
            $this->startTimeByTime = $result['startTime'] ?? [];
            $this->endTimeByTime = $result['endTime'] ?? [];
            $this->dateEncodeByTime = $result['dateEncode'] ?? [];

            foreach ($this->checkTimes as $time) {
                $this->defectsByTime[$time] = app(PpfLookUpRepository::class)->getDefectbyCheckTime($ppf, $time);
                $this->smallDefectsByTime[$time] = app(PpfLookUpRepository::class)->getSmallDefectbyCheckTime($ppf, $time);
                $this->dimensionsByTime[$time] = app(PpfLookUpRepository::class)->getDimensionbyCheckTime($ppf, $time);
                $this->remarksByTime[$time] = app(PpfLookUpRepository::class)->getRemarks($ppf, $time);
            }
            $this->sortCheckTimesByDateEncode();
        }
        $this->syncDraft();
    }
    #[On('remarks-synced')]
    public function onRemarksSynced(string $selectedCheckTime, string $remarks): void
    {
        $this->remarksByTime[$selectedCheckTime] = $remarks;
        $this->endTimeByTime[$selectedCheckTime] = now()->toDateTimeString();
        $this->touchedThisSession[$selectedCheckTime] = true;
        $this->syncDraft();
    }
    #[On('fromMaster')]
    public function fromMaster($data)
    {
        $this->dataFromMaster = [
            'cavity' => $data['noOfCavity'],
            'nqr' => $data['nqr']
        ];
    }

    #[On('fetchPartNo')]
    public function fetchPartNo(string $partNo)
    {
        $this->partNo = $partNo;
    }
} ?>

<div class="w-full bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 sm:p-8 @if($this->ppf === 0) opacity-50 cursor-not-allowed @endif">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Surface Appearance Inspection</h2>
            <p class="text-sm text-gray-500">Add one or more inspection check times</p>
        </div>
    </div>

    {{-- Add check time field --}}
    <div class=" flex flex-col justify-center items-center" @if($action==='view' || $action==='delete' ) hidden @endif>
        <div class="@if($this->ppf === 0 || $this->action === 'view') cursor-not-allowed @endif">
            <label for="checkTime" class="block text-sm font-medium text-gray-700 mb-1.5">Check Time</label>
            <div class="flex justify-center gap-2">
                <input placeholder="Enter Check Time" @if($action==='view' || $action==='delete' ) disabled @endif name="checkTime" wire:model="checkTime" @if($this->ppf === 0) disabled @endif class="border rounded p-2">
            </div>
            <div class="flex flex-col justify-center items-center mt-2">
                <button
                    wire:click="addCheckTime"
                    type="button"
                    @if($this->ppf === 0 || $action === 'view' || $action === 'delete') disabled @endif
                    class="shrink-0 rounded-lg bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-medium px-4 py-2.5 transition">
                    Add
                </button>
            </div>
            @error('checkTime')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>


    {{-- Saved check times: bordered row, click to select --}}
    @if (count($checkTimes) > 0)
    <div class="mt-4 flex flex-wrap gap-2 sm:gap-3 @if ($action === 'view') flex flex-row justify-center items-center @endif">
        @foreach ($checkTimes as $time)
        <div
            wire:key="check-time-{{ $time }}"
            class="flex items-center gap-1 sm:gap-2 rounded-lg border pl-3 pr-1.5 sm:pl-4 sm:pr-2 py-1.5 sm:py-2.5 text-sm sm:text-base font-medium transition
                    {{ $selectedCheckTime === $time
                        ? 'border-emerald-600 bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-emerald-400 hover:bg-emerald-50' }}">
            <button
                wire:click="selectCheckTime('{{ $time }}')"
                type="button"
                class="flex items-center gap-1 whitespace-nowrap cursor-pointer">
                {{ $time }}
                @if(count($defectsByTime[$time] ?? []) > 0)
                <span class="text-xs sm:text-sm text-emerald-600">({{ count($defectsByTime[$time]) }})</span>
                @endif
            </button>
            <button
                @if($action==='view' || $action==='delete' ) disabled @endif
                @click.prevent="if (confirm('Are you sure you want to remove this check time?')) $wire.removeCheckTime('{{ $time }}')"
                type="button"
                class="flex-shrink-0 w-6 h-6 sm:w-8 sm:h-8 flex items-center justify-center rounded-md text-gray-400 text-sm sm:text-base
                   hover:text-red-600 hover:bg-red-50 transition
                   disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-gray-400">
                ✕
            </button>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Actions --}}
    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
        <button
            @if($action==='view' || $action==='delete' ) disabled @endif
            wire:click="clear"
            type="button"
            class="text-sm text-gray-500 hover:text-gray-700 font-medium px-2">
            Clear All
        </button>
    </div>

    {{-- Pressing a check time mounts the defects component, which auto-opens its modal --}}
    @if ($selectedCheckTime)
    @if($isMKA)
    <livewire:inspection::partials.mka-otherparts-encoding
        :key="'dimensions-' . $selectedCheckTime"
        :selectedCheckTime="$selectedCheckTime"
        :loaded-rows="$dimensionsByTime[$selectedCheckTime] ?? []"
        :ppfno="$ppf"
        :partNo="$partNo"
        :action="$action" />
    @else
    <livewire:inspection::partials.defects
        :key="'defects-' . $selectedCheckTime"
        :selectedCheckTime="$selectedCheckTime"
        :loaded-defects="$defectsByTime[$selectedCheckTime] ?? []"
        :loaded-small-defects="$smallDefectsByTime[$selectedCheckTime] ?? []"
        :action="$action"
        :fromMaster="$dataFromMaster" />

    <livewire:inspection::partials.dimensions
        :key="'dimensions-' . $selectedCheckTime"
        :selectedCheckTime="$selectedCheckTime"
        :loaded-rows="$dimensionsByTime[$selectedCheckTime] ?? []"
        :ppfno="$ppf"
        :partNo="$partNo"
        :action="$action" />
    <livewire:inspection::partials.remarks
        :key="'remarks-' . $selectedCheckTime"
        :selectedCheckTime="$selectedCheckTime"
        :loadedRemarks="$remarksByTime[$selectedCheckTime] ?? ''"
        :action="$action" />
    @endif
    @endif
</div>