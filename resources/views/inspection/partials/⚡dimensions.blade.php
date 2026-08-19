<?php

use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;
use App\Inspection\Services\Dimensions\DimensionsService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $ppfNumber = '';
    public array $rows = [];
    public array $itemSuggestions = [];
    public string $partNo = '';
    public ?string $selectedCheckTime = null;
    public bool $readonly = false;
    public string $action = '';

    public ?int $activeModalIndex = null;
    public string $modalStep = 'choose';
    public int $pendingSets = 1;

    public function mount(
        ?string $selectedCheckTime = null,
        array $loadedRows = [],
        string $action,
        int $ppfno,
        string $partNo
    ): void {
        $this->selectedCheckTime = $selectedCheckTime;
        $this->ppfNumber = $ppfno;
        $this->partNo = $partNo;
        $this->rows = !empty($loadedRows) ? $loadedRows : [
            [
                'item' => '',
                'editable' => true,
                'specification' => '',
                'CL' => '',
                'judge' => '',
                'measurements' => [],
                'mode' => null,
                'sets' => null,
                'revealed' => false,
                'specType' => null,        
                'specNominal' => '',      
                'specTolerance' => '',
                'specUpper' => '',
                'specLower' => ''
            ],
            [
                'item' => 'Flash Thickness',
                'editable' => false,
                'specification' => '',
                'CL' => '',
                'judge' => '',
                'measurements' => [],
                'mode' => null,
                'sets' => null,
                'revealed' => false,
                'specType' => null,
                'specNominal' => '',
                'specTolerance' => '',
                'specUpper' => '',
                'specLower' => ''
            ],
            [
                'item' => 'Gap-Offset',
                'editable' => false,
                'specification' => '',
                'CL' => '',
                'judge' => '',
                'measurements' => [],
                'measurements_y' => [],
                'mode' => null,
                'sets' => null,
                'revealed' => false,
                'specType' => null,
                'specNominal' => '',
                'specTolerance' => '',
                'specUpper' => '',
                'specLower' => ''
            ],
        ];

        foreach ($this->rows as $i => $row) {
            if (!array_key_exists('revealed', $row)) {
                $count = count($row['measurements'] ?? []);
                $this->rows[$i]['revealed'] = $count > 0;
                $this->rows[$i]['mode'] = $count > 5 ? 'tightened' : ($count > 0 ? 'normal' : null);
                $this->rows[$i]['sets'] = $count > 0 ? (int) ceil($count / 5) : null;
            }
        }

        $this->resolveFixedSpecifications();
        $this->action = $action;

        if ($this->action === 'view' || $this->action === 'delete') {
            $this->readonly = true;
        }
    }

    private function service(): DimensionsService
    {
        return app(DimensionsService::class);
    }

    private function syncToParent(): void
    {
        if ($this->selectedCheckTime !== null) {
            $this->dispatch('dimensions-synced', selectedCheckTime: $this->selectedCheckTime, rows: $this->rows);
        }
    }

   public function updated(string $property, mixed $value): void
{
    if ($property === 'partNo') {
        $this->resolveFixedSpecifications();
    }

    if ($property === 'rows.0.item') {
        $this->itemSuggestions = app(DimensionMasterRepositoryInterface::class)->search($value, $this->partNo);
    }

    if (preg_match('/^rows\.(\d+)\.(specType|specNominal|specTolerance|specUpper|specLower|measurements|measurements_y)(\..+)?$/', $property, $matches)) {
        $this->evaluateRow((int) $matches[1]);
    }
    if (str_starts_with($property, 'rows.')) {
        $this->syncToParent();
    }
}

    private function resolveFixedSpecifications(): void
    {
        $repo = app(DimensionMasterRepositoryInterface::class);

        foreach ($this->rows as $i => $row) {
            if (!$row['editable']) {
                $this->applyMasterSpecification($i, $repo->getMasterSpecification($this->partNo, $row['item']));
            }
        }
    }

    private function evaluateRow(int $i): void
    {
        $row = $this->rows[$i];
        $limits = $this->service()->computeLimits($row);

        $this->rows[$i]['upperLimit'] = $limits['upperLimit'] ?? null;
        $this->rows[$i]['lowerLimit'] = $limits['lowerLimit'] ?? null;
        $this->rows[$i]['judge'] = $this->service()->judgeRow($row, $limits);
    }

    public function persistSpecification(int $i): void
    {
        if ($this->readonly) {
            return;
        }

        $this->service()->persistSpecification($this->partNo, $this->rows[$i]['item'] ?? '', $this->rows[$i]);
    }

    public function toggleJudge(int $rowIndex, int $slot): void
    {
        $current = $this->rows[$rowIndex]['judges'][$slot];
        $this->rows[$rowIndex]['judges'][$slot] = match ($current) {
            null => 'O',
            'O' => 'X',
            'X' => null,
        };
        $this->syncToParent();
    }

    public function initItem(): void
    {
        $itemName = trim($this->rows[0]['item'] ?? '');

        if ($itemName === '') {
            return;
        }

        $master = app(DimensionMasterRepositoryInterface::class)
            ->getMasterSpecification($this->partNo, $itemName);

        $this->applyMasterSpecification(0, $master);
        $this->syncToParent();
    }
    // ------------------------------------------------------------------
    // Dimension config modal
    // ------------------------------------------------------------------

    public function openDimensionModal(int $index): void
    {
        if ($this->readonly) {
            return;
        }

        $this->activeModalIndex = $index;
        $this->modalStep = 'choose';
        $this->pendingSets = 1;
    }

    public function closeDimensionModal(): void
    {
        $this->activeModalIndex = null;
        $this->modalStep = 'choose';
        $this->pendingSets = 1;
    }

    public function chooseNormal(): void
    {
        if ($this->activeModalIndex === null) {
            return;
        }

        $this->applyMeasurementCount($this->activeModalIndex, 5);
        $this->rows[$this->activeModalIndex]['mode'] = 'normal';
        $this->rows[$this->activeModalIndex]['sets'] = 1;
        $this->rows[$this->activeModalIndex]['revealed'] = true;

        $this->syncToParent();
        $this->closeDimensionModal();
    }

    public function chooseTightened(): void
    {
        $this->modalStep = 'sets';
    }

    public function confirmTightenedSets(): void
    {
        if ($this->activeModalIndex === null) {
            return;
        }

        $sets = max(1, (int) $this->pendingSets);
        $count = $sets * 5;

        $this->applyMeasurementCount($this->activeModalIndex, $count);
        $this->rows[$this->activeModalIndex]['mode'] = 'tightened';
        $this->rows[$this->activeModalIndex]['sets'] = $sets;
        $this->rows[$this->activeModalIndex]['revealed'] = true;

        $this->syncToParent();
        $this->closeDimensionModal();
    }

    private function applyMeasurementCount(int $index, int $count): void
    {
        $row = $this->rows[$index];

        $existing = $row['measurements'] ?? [];
        $this->rows[$index]['measurements'] = array_pad(array_slice($existing, 0, $count), $count, '');

        if (array_key_exists('measurements_y', $row)) {
            $existingY = $row['measurements_y'] ?? [];
            $this->rows[$index]['measurements_y'] = array_pad(array_slice($existingY, 0, $count), $count, '');
        }
    }

    public function reconfigureRow(int $index): void
    {
        $this->openDimensionModal($index);
    }

    private function applyMasterSpecification(int $i, ?array $master): void
    {
        $spec = $this->service()->resolveSpecFromMaster($master);

        if ($spec === null) {
            return; 
        }

        $this->rows[$i] = array_merge($this->rows[$i], $spec);

        $this->evaluateRow($i);
    }
}
?>

<div x-data="{
    focusNextCard(currentIndex) {
        const next = document.querySelector(`[data-card-index='${currentIndex + 1}'] input[data-first-measurement]`);
        if (next) next.focus();
    },
    focusY(cardIndex, slotIndex) {
        const y = document.querySelector(`[data-card-index='${cardIndex}'] input[data-y-index='${slotIndex}']`);
        if (y) y.focus();
    },
    focusX(cardIndex, slotIndex) {
        const x = document.querySelector(`[data-card-index='${cardIndex}'] input[data-x-index='${slotIndex}']`);
        if (x) x.focus();
    },
    focusMeasurement(cardIndex, slotIndex) {
        const el = document.querySelector(`[data-card-index='${cardIndex}'] input[data-measurement-index='${slotIndex}']`);
        if (el) el.focus();
    }
}">
    <div class="bg-gray-700 w-full">
        <p class="text-4xl font-extrabold text-center text-white p-4 mt-4">Dimensions</p>
    </div>

    <div class="w-full mx-auto mt-3 @if($readonly) opacity-50 cursor-not-allowed @endif">
        @foreach ($rows as $i => $row)
        <div wire:key="dim-row-{{ $i }}" data-card-index="{{ $i }}" class="w-full mb-4">

            @if (!$row['revealed'])
          
            <button
                type="button"
                wire:click="openDimensionModal({{ $i }})"
                @if($readonly) disabled @endif
                class="w-full flex items-center justify-between bg-white border border-gray-200 rounded-2xl p-5 hover:border-blue-300 hover:bg-blue-50/40 transition-all text-left">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center">
                        <i class="ti ti-ruler-2 text-xl text-green-700"></i>
                    </div>
                    <div>
                        <p class="font-medium text-base">Dimension entry</p>
                        <p class="text-sm text-gray-500">{{ $row['item'] ?: 'Enter item' }}</p>
                    </div>
                </div>
                <span class="text-sm text-blue-600 font-medium flex items-center gap-1">
                    Set up <i class="ti ti-chevron-right"></i>
                </span>
            </button>
            @else

            <div class="bg-white border border-gray-200 rounded-2xl p-6 w-full">

                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center">
                            <i class="ti ti-ruler-2 text-xl text-green-700"></i>
                        </div>
                        <div>
                            <p class="font-medium text-base">Dimension entry</p>
                            <p class="text-sm text-gray-500">
                                {{ $row['item'] ?: 'Enter item' }}
                                <span class="ml-1 text-xs font-medium px-2 py-0.5 rounded-full {{ $row['mode'] === 'tightened' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $row['mode'] === 'tightened' ? 'Tightened · ' . $row['sets'] . ' set' . ($row['sets'] > 1 ? 's' : '') : 'Normal' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="reconfigureRow({{ $i }})" @if($readonly) disabled @endif
                        class="text-sm text-gray-500 hover:text-blue-600 flex items-center gap-1">
                        <i class="ti ti-settings text-base"></i> Reconfigure
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="text-sm font-medium block mb-1.5">Item</label>
                        @if ($row['editable'])
                        <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.item"
                            wire:blur="initItem"
                            list="item-suggestions"
                            class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2"
                            placeholder="Enter item"
                            @if($readonly) disabled @endif>

                        <datalist id="item-suggestions">
                            @foreach ($itemSuggestions as $suggestion)
                            <option value="{{ $suggestion }}"></option>
                            @endforeach
                        </datalist>
                        @else
                        <div class="w-full bg-gray-50 rounded-lg px-3 py-2 font-medium">{{ $row['item'] }}</div>
                        @endif
                    </div>

                    <div>
                        <label class="text-sm font-medium block mb-1.5">Specification</label>
                        <div class="flex items-center gap-2">
                            <select wire:model.live="rows.{{ $i }}.specType"
                                class="bg-gray-50 border-0 rounded-lg px-2 py-2 text-sm"
                                @if($readonly) disabled @endif>
                                <option value="">Select</option>
                                <option value="max">MAX</option>
                                <option value="min">MIN</option>
                                <option value="tolerance">±</option>
                                <option value="tolerance_diff">TOLERANCE DIFF</option>
                            </select>

                            @if(($row['specType'] ?? '') === 'max')
                            <span class="text-sm text-gray-500 font-medium">MAX</span>
                            <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.specNominal"
                                class="w-24 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                                placeholder="1.20" @if($readonly) disabled @endif>

                            @elseif(($row['specType'] ?? '') === 'min')
                            <span class="text-sm text-gray-500 font-medium">MIN</span>
                            <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.specNominal"
                                class="w-24 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                                placeholder="1.20" @if($readonly) disabled @endif>

                            @elseif(($row['specType'] ?? '') === 'tolerance')
                            <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.specNominal"
                                class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                                placeholder="1.20" @if($readonly) disabled @endif>
                            <span class="text-sm text-gray-500 font-medium">±</span>
                            <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.specTolerance"
                                class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                                placeholder="0.10" @if($readonly) disabled @endif>

                            @elseif(($row['specType'] ?? '') === 'tolerance_diff')
                            <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.specNominal"
                                class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                                placeholder="1.20" @if($readonly) disabled @endif>
                            <span class="text-sm text-gray-500 font-medium">+</span>
                            <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.specUpper"
                                class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                                placeholder="0.10" @if($readonly) disabled @endif>
                            <span class="text-sm text-gray-500 font-medium">-</span>
                            <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.specLower"
                                class="w-20 bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                                placeholder="0.10" @if($readonly) disabled @endif>
                            @endif

                            <button
                                type="button"
                                @click.prevent="if (confirm('Are you sure you want to update this dimension?')) $wire.persistSpecification({{ $i }})"
                                class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 rounded hover:bg-blue-100 transition">
                                Update Dimension
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-sm font-medium block mb-1.5">Note</label>
                    <input type="text" wire:model="rows.{{ $i }}.CL"
                        class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2 text-gray-500"
                        placeholder="Refer to parts WI" @if($readonly) disabled @endif>
                </div>

                <hr class="border-gray-200 my-4">

                <div class="mb-1">
                    <label class="text-sm font-medium block mb-1.5">
                        Measurements
                        <span class="text-gray-400 font-normal">({{ count($row['measurements']) }} total)</span>
                    </label>

                    @if($row['item'] === 'Gap-Offset')
                    @php $setsCount = (int) ceil(count($row['measurements']) / 5); @endphp
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                        @for ($s = 0; $s < $setsCount; $s++)
                            <div wire:key="dim-{{ $i }}-set-{{ $s }}" class="flex flex-col gap-1.5">
                            <p class="text-xs text-gray-400">Set {{ $s + 1 }}</p>
                            <div class="flex flex-wrap gap-2">
                                @for ($k = 0; $k < 5; $k++)
                                    @php $j=$s * 5 + $k; @endphp
                                    @if($j < count($row['measurements']))
                                    <input @if($readonly) disabled @endif type="text"
                                    wire:model.live.debounce.150ms="rows.{{ $i }}.measurements.{{ $j }}"
                                    wire:key="dim-{{ $i }}-x-{{ $j }}"
                                    data-x-index="{{ $j }}"
                                    @if($j===0) data-first-measurement @endif
                                    @keyup="if($event.target.value.trim() !== '') focusY({{ $i }}, {{ $j }})"
                                    class="w-16 bg-gray-50 border-0 rounded-lg text-center py-2"
                                    placeholder="x{{ $j + 1 }}">
                                    @endif
                                    @endfor
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @for ($k = 0; $k < 5; $k++)
                                    @php
                                    $j=$s * 5 + $k;
                                    $isLastOverall=$j===count($row['measurements_y'] ?? []) - 1;
                                    @endphp
                                    @if($j < count($row['measurements_y'] ?? []))
                                    <input @if($readonly) disabled @endif type="text"
                                    wire:model.live.debounce.150ms="rows.{{ $i }}.measurements_y.{{ $j }}"
                                    wire:key="dim-{{ $i }}-y-{{ $j }}"
                                    data-y-index="{{ $j }}"
                                    @if(!$isLastOverall)
                                    @keyup="if($event.target.value.trim() !== '') focusX({{ $i }}, {{ $j + 1 }})"
                                    @else
                                    @keyup="if($event.target.value.trim() !== '') focusNextCard({{ $i }})"
                                    @endif
                                    class="w-16 bg-gray-50 border-0 rounded-lg text-center py-2"
                                    placeholder="y{{ $j + 1 }}">
                                    @endif
                                    @endfor
                            </div>
                    </div>
                    @endfor
                </div>
                @else
                @php $setsCount = (int) ceil(count($row['measurements']) / 5); @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                    @for ($s = 0; $s < $setsCount; $s++)
                        <div wire:key="dim-{{ $i }}-mset-{{ $s }}" class="flex flex-col gap-1.5">
                        @if($setsCount > 1)
                        <p class="text-xs text-gray-400">Set {{ $s + 1 }}</p>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            @for ($k = 0; $k < 5; $k++)
                                @php
                                $j=$s * 5 + $k;
                                $isLastOverall=$j===count($row['measurements']) - 1;
                                @endphp
                                @if($j < count($row['measurements']))
                                <input @if($readonly) disabled @endif type="text"
                                wire:model.live.debounce="rows.{{ $i }}.measurements.{{ $j }}"
                                wire:key="dim-{{ $i }}-m-{{ $j }}"
                                data-measurement-index="{{ $j }}"
                                @if($j===0) data-first-measurement @endif
                                @if(!$isLastOverall)
                                @keyup="if($event.target.value.trim() !== '') focusMeasurement({{ $i }}, {{ $j + 1 }})"
                                @else
                                @keyup="if($event.target.value.trim() !== '') focusNextCard({{ $i }})"
                                @endif
                                class="w-16 bg-gray-50 border-0 rounded-lg text-center py-2"
                                placeholder="{{ $j + 1 }}">
                                @endif
                                @endfor
                        </div>
                </div>
                @endfor
            </div>
            @endif
        </div>

        <hr class="border-gray-200 my-4">

        <div class="flex items-center justify-between">
            <div class="w-40">
                <label class="text-sm font-medium block mb-1.5">Judgement</label>
                <input type="text" wire:model="rows.{{ $i }}.judge"
                    class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2 text-gray-500 text-center"
                    readonly>
            </div>
            <button @if($readonly) disabled @endif type="button" class="text-sm text-gray-500 hover:text-gray-700">Clear</button>
        </div>
    </div>
    @endif
</div>
@endforeach
</div>

@if($activeModalIndex !== null)
<div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-sm shadow-lg">

        @if($modalStep === 'choose')
        <h3 class="text-lg font-semibold mb-1">
            Configure {{ $rows[$activeModalIndex]['item'] ?: 'this dimension' }}
        </h3>
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
            <button wire:click="closeDimensionModal" type="button" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
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