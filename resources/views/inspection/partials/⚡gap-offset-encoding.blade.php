<?php
// resources/views/livewire/inspection/partials/gap-offset-encoding.blade.php

use App\Inspection\Actions\DeleteInspection;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use App\Inspection\Services\Dimensions\DimensionsService;
use App\Inspection\Services\Excel\MIPIRRecordCycleTracker;
use App\Inspection\Services\PPFLookUp\PpfLookUpService;
use App\Inspection\Services\Saving\CreateInspectionService;
use App\Traits\HasNotifications;
use App\Traits\WithLoading;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

new class extends Component
{
    use WithFileUploads;
    use HasNotifications;
    use WithLoading;

    // Master info (from PPF Lookup)
    public int $ppf = 0;
    public string $partNo = '';
    public string $moldNo = '';
    public string $machineNo = '';

    // Encoding context
    #[Validate('required|string|max:50')]
    public string $prodLotNo = '';

    // Check time — add/select/remove
    #[Validate('required')]
    public string $checkTime = '';
    public array $checkTimes = [];
    public ?string $selectedCheckTime = null;
    public array $dateEncodeByTime = [];

    // Upload
    #[Validate('nullable|file|mimes:xlsx,xls,csv|max:5120')]
    public $excelFile = null;

    // Parsed data, keyed by check time
    public array $measurementsXByTime = [];
    public array $measurementsYByTime = [];
    public array $judgeByTime = [];
    public array $modeByTime = [];
    public array $setsByTime = [];

    // Specification + device (shared: same partNo/item across every check time)
    public string $device = '';
    public ?string $specType = null;
    public string $specNominal = '';
    public string $specTolerance = '';
    public string $specUpper = '';
    public string $specLower = '';

    // Normal/Tightened modal (mirrors dimensions.blade.php)
    public bool $showModeModal = false;
    public string $modalStep = 'choose';
    public int $pendingSets = 1;
    public ?string $pendingCheckTime = null;

    public string $action = '';

    public string $controlLimit = '';

    #[On('ppf-checked')]
    public function onPpfChecked(int $ppf): void
    {
        $this->ppf = $ppf;

        $result = app(PpfLookUpService::class)->findByPpfNo((string) $ppf);
        $gapOffsetChecktime = app(PpfLookUpRepository::class)->getGapOffsetChecktime((string) $ppf);

        if (! $result) {
            return;
        }

        $this->partNo = $result['partNo'] ?? '';
        $this->moldNo = $result['moldNo'] ?? '';
        $this->prodLotNo = $result['prodLotNo'] ?? '';
        $this->machineNo = (string) (int) ($result['machineNo'] ?? 0);
        $this->checkTimes = $gapOffsetChecktime;
        $this->measurementsXByTime = $result['measurementsXByTime'] ?? [];
        $this->measurementsYByTime = $result['measurementsYByTime'] ?? [];
        $this->judgeByTime = $result['judgementByTime'] ?? [];
        $this->resolveSpecification();
    }

    #[On('action-changed-gapoffset')]
    public function onActionChanged(string $action): void
    {
        if ($action) {
            $this->reset([
                'checkTimes',
                'selectedCheckTime',
                'dateEncodeByTime',
                'measurementsXByTime',
                'measurementsYByTime',
                'judgeByTime',
                'modeByTime',
                'setsByTime',
                'machineNo',
                'prodLotNo',
                'device',
                'specType',
                'specNominal',
                'specTolerance',
                'specUpper',
                'specLower',
                'controlLimit',
            ]);
        }
        $this->action = $action;

        $this->dispatch('gap-offset-action', action: $action, mode: 'gap-offset');
    }

    /*
    |--------------------------------------------------------------------------
    | Check Time — add / select / remove
    |--------------------------------------------------------------------------
    */

    public function addCheckTime(): void
    {
        $this->validate();

        $newCheckTime = $this->resolveCheckTimeLabel(str($this->checkTime)->upper());

        $this->checkTimes[] = $newCheckTime;
        $this->dateEncodeByTime[$newCheckTime] = now()->toDateTimeString();

        $this->sortCheckTimesByDateEncode();

        $this->selectedCheckTime = $newCheckTime;

        $this->reset('checkTime');
        $this->resetErrorBag();
    }

    private function resolveCheckTimeLabel(string $base): string
    {
        if (! in_array($base, $this->checkTimes, true)) {
            return $base;
        }

        $suffix = 1;
        while (in_array($base . $suffix, $this->checkTimes, true)) {
            $suffix++;
        }

        return $base . $suffix;
    }

    private function sortCheckTimesByDateEncode(): void
    {
        usort($this->checkTimes, function (string $a, string $b) {
            $dateA = $this->dateEncodeByTime[$a] ?? '';
            $dateB = $this->dateEncodeByTime[$b] ?? '';
            return $dateA <=> $dateB;
        });
    }

    public function selectCheckTime(string $time): void
    {
        $this->selectedCheckTime = $this->selectedCheckTime === $time ? null : $time;
    }

    public function removeCheckTime(string $time): void
    {
        $this->checkTimes = array_values(array_diff($this->checkTimes, [$time]));
        unset($this->dateEncodeByTime[$time]);
        unset($this->measurementsXByTime[$time]);
        unset($this->measurementsYByTime[$time]);
        unset($this->judgeByTime[$time]);
        unset($this->modeByTime[$time]);
        unset($this->setsByTime[$time]);

        if ($this->selectedCheckTime === $time) {
            $this->selectedCheckTime = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Excel upload — parses into the currently selected check time,
    | then opens the Normal/Tightened modal to confirm the set count.
    |--------------------------------------------------------------------------
    */

    public function updatedExcelFile(): void
    {
        $this->validateOnly('excelFile');

        if ($this->selectedCheckTime === null) {
            $this->addError('excelFile', 'Please add and select a check time first.');
            $this->reset('excelFile');
            return;
        }

        $spreadsheet = IOFactory::load($this->excelFile->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $parsed = $this->parseRows($rows);

        $time = $this->selectedCheckTime;

        $this->measurementsXByTime[$time] = $parsed['x'];
        $this->measurementsYByTime[$time] = $parsed['y'];

        $count = count($parsed['x']);
        $sets = max(1, (int) ceil($count / 5));
        $mode = $count <= 5 ? 'normal' : 'tightened';

        $this->applyMeasurementCount($time, $sets * 5);
        $this->modeByTime[$time] = $mode;
        $this->setsByTime[$time] = $sets;

        $this->resolveSpecification();
        $this->evaluateJudge($time);

        $this->reset('excelFile');
    }

    private function parseRows(array $rows): array
    {
        if (empty($rows)) {
            return ['x' => [], 'y' => []];
        }

        $values = [];

        foreach ($rows as $row) {
            if (!is_numeric($row[3] ?? null)) {
                continue;
            }

            // Measurement is always the last column
            $value = $row[count($row) - 1] ?? null;

            if (!is_numeric($value)) {
                continue;
            }

            $values[] = (float) $value;
        }
        $half = intdiv(count($values), 2);

        return [
            'x' => array_slice($values, 0, $half),
            'y' => array_slice($values, $half),
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | Normal / Tightened modal
    |--------------------------------------------------------------------------
    */

    public function chooseNormal(): void
    {
        if ($this->pendingCheckTime === null) {
            return;
        }

        $this->applyMeasurementCount($this->pendingCheckTime, 5);
        $this->modeByTime[$this->pendingCheckTime] = 'normal';
        $this->setsByTime[$this->pendingCheckTime] = 1;

        $this->evaluateJudge($this->pendingCheckTime);
        $this->closeModeModal();
    }

    public function chooseTightened(): void
    {
        $this->modalStep = 'sets';
    }

    public function confirmTightenedSets(): void
    {
        if ($this->pendingCheckTime === null) {
            return;
        }

        $sets = max(1, (int) $this->pendingSets);
        $count = $sets * 5;

        $this->applyMeasurementCount($this->pendingCheckTime, $count);
        $this->modeByTime[$this->pendingCheckTime] = 'tightened';
        $this->setsByTime[$this->pendingCheckTime] = $sets;

        $this->evaluateJudge($this->pendingCheckTime);
        $this->closeModeModal();
    }

    public function closeModeModal(): void
    {
        $this->showModeModal = false;
        $this->modalStep = 'choose';
        $this->pendingSets = 1;
        $this->pendingCheckTime = null;
    }

    private function applyMeasurementCount(string $time, int $count): void
    {
        $existingX = $this->measurementsXByTime[$time] ?? [];
        $existingY = $this->measurementsYByTime[$time] ?? [];

        $this->measurementsXByTime[$time] = array_pad(array_slice($existingX, 0, $count), $count, '');
        $this->measurementsYByTime[$time] = array_pad(array_slice($existingY, 0, $count), $count, '');
    }

    public function reconfigure(string $time): void
    {
        $this->pendingCheckTime = $time;
        $this->pendingSets = $this->setsByTime[$time] ?? 1;
        $this->modalStep = 'choose';
        $this->showModeModal = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Specification + Device
    |--------------------------------------------------------------------------
    */

    private function resolveSpecification(): void
    {
        if ($this->partNo === '') {
            return;
        }

        $repo = app(DimensionMasterRepositoryInterface::class);
        $master = $repo->getMasterSpecification($this->partNo, 'Gap-Offset');

        if ($master === null) {
            $master = $repo->getTempMaster($this->partNo, 'Gap-Offset');
        }

        $spec = app(DimensionsService::class)->resolveSpecFromMaster($master);

        if ($spec === null) {
            return;
        }

        $this->device         = $master['Device'];
        $this->controlLimit   = (string) ($master['CL'] ?? '');
        $this->specType       = $spec['specType'];
        $this->specNominal    = $spec['specNominal'];
        $this->specTolerance  = $spec['specTolerance'];
        $this->specUpper      = $spec['specUpper'];
        $this->specLower      = $spec['specLower'];
    }

    /** Fires on every wire:model.live update — recomputes limits/judge live, same as dimensions.blade.php. */
    public function updated(string $property): void
    {
        if (in_array($property, ['specType', 'specNominal', 'specTolerance', 'specUpper', 'specLower'], true)) {
            foreach ($this->checkTimes as $time) {
                if (! empty($this->measurementsXByTime[$time])) {
                    $this->evaluateJudge($time);
                }
            }
        }
    }

    public function persistSpecification(): void
    {
        app(DimensionsService::class)->persistSpecification($this->partNo, 'Gap-Offset', [
            'specType'      => $this->specType,
            'specNominal'   => $this->specNominal,
            'specTolerance' => $this->specTolerance,
            'specUpper'     => $this->specUpper,
            'specLower'     => $this->specLower,
            'device'        => $this->device,
            'controlLimit'  => $this->controlLimit,
        ]);

        $this->notifySuccess('Saved', 'Gap-Offset specification updated.');
    }

    private function evaluateJudge(string $time): void
    {
        $row = [
            'specType' => $this->specType,
            'specNominal' => $this->specNominal,
            'specTolerance' => $this->specTolerance,
            'specUpper' => $this->specUpper,
            'specLower' => $this->specLower,
            'measurements' => $this->measurementsXByTime[$time] ?? [],
        ];

        $service = app(DimensionsService::class);
        $limits = $service->computeLimits($row);

        $this->judgeByTime[$time] = $service->judgeRow($row, $limits);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit — writes every added check time's measurements
    |--------------------------------------------------------------------------
    */

    #[On('submit-gapoffset')]
    public function submit(): void
    {
        $this->validate([
            'prodLotNo' => 'required|string|max:50',
        ]);

        if ($this->ppf === 0) {
            $this->addError('ppf', 'Please look up a PPF No. first.');
            return;
        }

        if (empty($this->checkTimes)) {
            $this->addError('checkTime', 'Please add at least one check time.');
            return;
        }

        // $missing = array_filter(
        //     $this->checkTimes,
        //     fn($time) => empty($this->measurementsXByTime[$time]) || empty($this->modeByTime[$time])
        // );

        // if (! empty($missing)) {
        //     $this->addError('excelFile', 'Every check time needs an uploaded file and confirmed Normal/Tightened mode: ' . implode(', ', $missing));
        //     return;
        // }

        DB::transaction(function () {
            foreach ($this->checkTimes as $time) {
                $this->writeCheckTime($time);
            }
        });
        app(MIPIRRecordCycleTracker::class)->checkAndRecordLotComplete($this->ppf, $this->partNo);
        app(PpfLookUpRepository::class)->forgetMainData($this->ppf);
        $this->notifyReload('success', 'Updated Successfully');

        $this->reset([
            'checkTimes',
            'selectedCheckTime',
            'dateEncodeByTime',
            'measurementsXByTime',
            'measurementsYByTime',
            'judgeByTime',
            'modeByTime',
            'setsByTime',
        ]);
    }

    #[On('delete-gapoffset')]
    public function delete()
    {
        if ($this->ppf === 0) {
            $this->addError('ppf', 'Please look up a PPF No. first.');
            return;
        }
        try {
            DB::transaction(function () {
                app(DeleteInspection::class)->executeGapOffset($this->ppf);
            });

            app(PpfLookUpRepository::class)->forgetMainData($this->ppf);

            $this->notifyReload('success', 'Gap-Offset measurements deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Gap-Offset delete failed', [
                'ppf' => $this->ppf,
                'checkTime' => $this->selectedCheckTime,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->notifyReload('failed', 'Failed to delete Gap-Offset measurements. Please try again.');
        }
    }

    private function writeCheckTime(string $time): void
    {
        $measurementsX = $this->measurementsXByTime[$time] ?? [];
        $measurementsY = $this->measurementsYByTime[$time] ?? [];
        $mode = $this->modeByTime[$time] ?? 'normal';
        $judge = ($this->judgeByTime[$time] ?? '-') === 'O' ? 0 : 1;

        $setsCount = (int) ceil(count($measurementsX) / 5);
        MIPIRDimensionMeasure::where('PPFNo', $this->ppf)
            ->where('Checktime', $time)
            ->where('DimItem', 'like', '%Gap-Offset%')
            ->delete();
        for ($s = 0; $s < $setsCount; $s++) {
            $chunk = array_slice($measurementsX, $s * 5, 5);

            app(CreateInspectionService::class)->createDimensionMeasure([
                'PPFNo'     => $this->ppf,
                'MDNo'      => $this->moldNo,
                'PartNo'    => $this->partNo,
                'ProdLotNo' => $this->prodLotNo,
                'MachineNo' => $this->machineNo,
                'Checktime' => $time,
                'CL'     => $this->controlLimit,
                'Mode'      => $mode,
                'Set'       => $s + 1,
                'Specs'     => $this->specNominal,
                'DimItem'   => 'Gap-Offset',
                'Judge'     => $judge,
                '1' => number_format((float) ($chunk[0] ?? 0), 4, '.', ''),
                '2' => number_format((float) ($chunk[1] ?? 0), 4, '.', ''),
                '3' => number_format((float) ($chunk[2] ?? 0), 4, '.', ''),
                '4' => number_format((float) ($chunk[3] ?? 0), 4, '.', ''),
                '5' => number_format((float) ($chunk[4] ?? 0), 4, '.', ''),
                'InspectedBy' => Auth::user()->InspectorNo ?? null
            ]);
        }

        $ySetsCount = (int) ceil(count($measurementsY) / 5);

        for ($s = 0; $s < $ySetsCount; $s++) {
            $yChunk = array_slice($measurementsY, $s * 5, 5);

            app(CreateInspectionService::class)->createDimensionMeasure([
                'PPFNo'     => $this->ppf,
                'MDNo'      => $this->moldNo,
                'PartNo'    => $this->partNo,
                'ProdLotNo' => $this->prodLotNo,
                'MachineNo' => $this->machineNo,
                'Checktime' => $time,
                'CL'     => $this->controlLimit,
                'Mode'      => $mode,
                'Set'       => $s + 1,
                'Specs'     => $this->specNominal,
                'DimItem'   => 'Gap-Offset (Y)',
                'Judge'     => $judge,
                '1' => number_format((float) ($yChunk[0] ?? 0), 4, '.', ''),
                '2' => number_format((float) ($yChunk[1] ?? 0), 4, '.', ''),
                '3' => number_format((float) ($yChunk[2] ?? 0), 4, '.', ''),
                '4' => number_format((float) ($yChunk[3] ?? 0), 4, '.', ''),
                '5' => number_format((float) ($yChunk[4] ?? 0), 4, '.', ''),
                'InspectedBy' => Auth::user()->InspectorNo ?? null
            ]);
        }
    }
};
?>

<div class="w-full mx-auto flex flex-col gap-6">
    <x-ui.round-notification />
    <div class="w-full mx-auto bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 sm:p-8 @if($ppf === 0) opacity-50 cursor-not-allowed @endif">

        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Gap-Offset Encoding</h2>
                <p class="text-sm text-gray-500">Add a check time, then upload its Gap-Offset measurement file</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <x-ui.input-field id="machineNo" label="Machine No." type="text" wire:model="machineNo" readonly />
            <div>
                <label for="prodLotNo" class="block text-sm font-medium text-gray-700 mb-1.5">Production Lot No.</label>
                <input id="prodLotNo" wire:model="prodLotNo" type="text" placeholder="Enter production lot no."
                    class="w-full rounded-lg border-gray-300 text-sm py-2.5 px-3.5">
                @error('prodLotNo') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Measuring Device + Specification (shared for the Gap-Offset item on this part) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 pb-4 border-b border-gray-100">
            <div>
                <label class="text-sm font-medium block mb-1.5">Measuring Device</label>
                <input type="text" wire:model.live.debounce.400ms="device"
                    class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2"
                    placeholder="Enter the measuring device used">
            </div>

            <div>
                <label class="text-sm font-medium block mb-1.5">Specification</label>
                <div class="flex items-center gap-2 flex-wrap">
                    <select wire:model.live="specType" class="bg-gray-50 border-0 rounded-lg px-2 py-2 text-sm" disabled>
                        <option value="">Select</option>
                        <option value="max">MAX</option>
                        <option value="min">MIN</option>
                        <option value="tolerance">±</option>
                        <option value="tolerance_diff">TOLERANCE DIFF</option>
                    </select>

                    @if($specType === 'max')
                    <span class="text-sm text-gray-500 font-medium">MAX</span>
                    <input type="text" wire:model.live.debounce.400ms="specNominal"
                        class="w-24 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center" placeholder="1.20">

                    @elseif($specType === 'min')
                    <span class="text-sm text-gray-500 font-medium">MIN</span>
                    <input type="text" wire:model.live.debounce.400ms="specNominal"
                        class="w-24 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center" placeholder="1.20">

                    @elseif($specType === 'tolerance')
                    <input type="text" wire:model.live.debounce.400ms="specNominal"
                        class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center" placeholder="1.20">
                    <span class="text-sm text-gray-500 font-medium">±</span>
                    <input type="text" wire:model.live.debounce.400ms="specTolerance"
                        class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center" placeholder="0.10">

                    @elseif($specType === 'tolerance_diff')
                    <input type="text" wire:model.live.debounce.400ms="specNominal"
                        class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center" placeholder="1.20">
                    <span class="text-sm text-gray-500 font-medium">+</span>
                    <input type="text" wire:model.live.debounce.400ms="specUpper"
                        class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center" placeholder="0.10">
                    <span class="text-sm text-gray-500 font-medium">-</span>
                    <input type="text" wire:model.live.debounce.400ms="specLower"
                        class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center" placeholder="0.10">
                    @endif
                </div>
            </div>

            <div class="mb-4">
                <label for="controlLimit" class="text-sm font-medium block mb-1.5">Control Limit</label>
                <input id="controlLimit" type="text" wire:model.live.debounce.400ms="controlLimit"
                    class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2 text-gray-700"
                    placeholder="Refer to parts WI"
                    @if($ppf===0) disabled @endif>
            </div>
        </div>

        {{-- Add check time --}}
        <div class="flex flex-col items-center justify-center mb-4">
            <label for="checkTime" class="block text-sm font-medium text-gray-700 mb-1.5">Check Time</label>
            <div class="flex justify-center gap-2">
                <input placeholder="Enter Check Time" wire:model="checkTime" @if($ppf===0) disabled @endif
                    class="border rounded p-2">
            </div>
            <div class="flex flex-col items-center mt-2">
                <button wire:click="addCheckTime" type="button" @if($ppf===0) disabled @endif
                    class="shrink-0 rounded-lg bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-sm font-medium px-4 py-2.5 transition">
                    Add
                </button>
            </div>
            @error('checkTime') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Check time chips --}}
        @if(count($checkTimes) > 0)
        <div class="flex flex-wrap gap-2 justify-center mb-6">
            @foreach($checkTimes as $time)
            <div wire:key="check-time-{{ $time }}"
                class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition cursor-pointer
                    {{ $selectedCheckTime === $time
                        ? 'border-emerald-600 bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-emerald-400 hover:bg-emerald-50' }}">
                <button wire:click="selectCheckTime('{{ $time }}')" type="button" class="flex-1 text-left">
                    {{ $time }}
                    @if(!empty($measurementsXByTime[$time]))
                    <span class="ml-1 text-xs text-emerald-600">({{ count($measurementsXByTime[$time]) }})</span>
                    @endif
                    @if(!empty($modeByTime[$time]))
                    <span class="ml-1 text-xs font-medium px-1.5 py-0.5 rounded-full {{ $modeByTime[$time] === 'tightened' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $modeByTime[$time] === 'tightened' ? 'Tightened · ' . $setsByTime[$time] . ' set' . ($setsByTime[$time] > 1 ? 's' : '') : 'Normal' }}
                    </span>
                    @endif
                </button>
                @if(!empty($modeByTime[$time]))
                <button wire:click="reconfigure('{{ $time }}')" type="button" class="text-gray-400 hover:text-blue-600" title="Reconfigure sets">
                    <i class="ti ti-settings text-sm"></i>
                </button>
                @endif
                <button
                    @click.prevent="if (confirm('Are you sure you want to remove this check time?')) $wire.removeCheckTime('{{ $time }}')"
                    type="button"
                    class="text-gray-400 hover:text-red-600">
                    ✕
                </button>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Upload zone — only active once a check time is selected --}}
        @if($selectedCheckTime)
        <div
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            x-on:livewire-upload-finish="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:dragover.prevent="dragging = true"
            x-on:dragleave.prevent="dragging = false"
            x-on:drop.prevent="dragging = false; if (!uploading) { $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change')) }"
            :class="uploading ? 'border-emerald-400 bg-emerald-50 cursor-wait' : (dragging ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300 cursor-pointer')"
            class="border-2 border-dashed rounded-xl p-8 text-center transition"
            @click="if (!uploading) $refs.fileInput.click()">

            <input type="file" x-ref="fileInput" wire:model="excelFile" accept=".xlsx,.xls,.csv" class="hidden" :disabled="uploading">

            <template x-if="!uploading">
                <p class="text-sm text-gray-500">Drop the Excel file for <strong>{{ $selectedCheckTime }}</strong>, or click to browse</p>
            </template>

            <template x-if="uploading">
                <div class="flex flex-col items-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="text-sm text-emerald-700 font-medium">Uploading… <span x-text="progress"></span>%</p>
                    <div class="w-full bg-emerald-100 rounded-full h-1.5 max-w-xs">
                        <div class="bg-emerald-600 h-1.5 rounded-full transition-all" :style="`width: ${progress}%`"></div>
                    </div>
                </div>
            </template>

            <div wire:loading wire:target="excelFile" class="text-sm text-emerald-600 mt-2">Parsing file...</div>
        </div>
        @error('excelFile') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror

        @if(!empty($measurementsXByTime[$selectedCheckTime]))
        <div class="mt-6 pt-4 border-t border-gray-100">
            @php $judge = $judgeByTime[$selectedCheckTime] ?? '-'; @endphp
            <p class="text-sm font-medium text-gray-700 mb-2">
                Parsed {{ count($measurementsXByTime[$selectedCheckTime]) }} measurement(s) for {{ $selectedCheckTime }} —
                <span class="{{ $judge === 'O' ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $judge === 'O' ? 'Passed' : 'Failed' }}
                </span>
            </p>
            <div class="flex flex-wrap gap-2 text-sm text-gray-600">
                @foreach($measurementsXByTime[$selectedCheckTime] as $i => $x)
                <span class="border rounded-lg px-2 py-1">X{{ $i + 1 }}: {{ $x }} / Y{{ $i + 1 }}: {{ $measurementsYByTime[$selectedCheckTime][$i] ?? '-' }}</span>
                @endforeach
            </div>
        </div>
        @endif
        @else
        <p class="text-sm text-gray-400 text-center">Select or add a check time to upload its file.</p>
        @endif
    </div>

    {{-- Normal/Tightened modal --}}
    @if($showModeModal)
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-sm shadow-lg">

            @if($modalStep === 'choose')
            <h3 class="text-lg font-semibold mb-1">Configure {{ $pendingCheckTime }}</h3>
            <p class="text-sm text-gray-500 mb-5">Is this a normal or tightened inspection?</p>

            <div class="flex gap-3">
                <button type="button" wire:click="chooseNormal"
                    class="flex-1 border-2 border-gray-200 hover:border-blue-500 hover:bg-blue-50 rounded-xl py-4 text-sm font-medium text-gray-700 hover:text-blue-700 transition-all">
                    <i class="ti ti-target text-2xl block mx-auto mb-1"></i>
                    Normal
                    <span class="block text-xs text-gray-400 font-normal mt-0.5">5 measurements</span>
                </button>
                <button type="button" wire:click="chooseTightened"
                    class="flex-1 border-2 border-gray-200 hover:border-amber-500 hover:bg-amber-50 rounded-xl py-4 text-sm font-medium text-gray-700 hover:text-amber-700 transition-all">
                    <i class="ti ti-adjustments text-2xl block mx-auto mb-1"></i>
                    Tightened
                    <span class="block text-xs text-gray-400 font-normal mt-0.5">Multiple sets</span>
                </button>
            </div>

            <div class="flex justify-end mt-5">
                <button wire:click="closeModeModal" type="button" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
            </div>
            @else
            <h3 class="text-lg font-semibold mb-1">Tightened inspection</h3>
            <p class="text-sm text-gray-500 mb-4">How many sets of 5 measurements?</p>

            <input type="number" min="1" wire:model="pendingSets"
                class="w-full border rounded-lg px-3 py-2 text-sm text-center" />
            <p class="text-xs text-gray-400 mt-1.5 text-center">
                = {{ max(1, (int) $pendingSets) * 5 }} total measurements
            </p>

            <div class="flex justify-end gap-2 mt-5">
                <button wire:click="$set('modalStep', 'choose')" type="button" class="px-4 py-2 text-sm text-gray-500">Back</button>
                <button wire:click="confirmTightenedSets" type="button"
                    class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg">Confirm</button>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>