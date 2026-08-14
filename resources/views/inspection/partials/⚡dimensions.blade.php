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

    public function mount(?string $selectedCheckTime = null, array $loadedRows = [], string $action): void
    {
        $this->selectedCheckTime = $selectedCheckTime;
        $this->rows = !empty($loadedRows) ? $loadedRows : [
            [
                'item' => '',
                'editable' => true,
                'specification' => '',
                'CL' => '',
                'judge' => '',
                'measurements' => array_fill(0, 5, ''),
            ],
            [
                'item' => 'Flash Thickness',
                'editable' => false,
                'specification' => '',
                'CL' => '',
                'judge' => '',
                'measurements' => array_fill(0, 5, ''),
            ],
            [
                'item' => 'Gap-Offset',
                'editable' => false,
                'specification' => '',
                'CL' => '',
                'judge' => '',
                'measurements' => array_fill(0, 5, ''),
                'measurements_y' => array_fill(0, 5, ''),
            ],
        ];

        $this->resolveFixedSpecifications();
        $this->action = $action;

        if ($this->action === 'view' || $this->action === 'delete') {
            $this->readonly = true;
        }
    }

    private function syncToParent(): void
    {
        if ($this->selectedCheckTime !== null) {
            $this->dispatch('dimensions-synced', selectedCheckTime: $this->selectedCheckTime, rows: $this->rows);
        }
    }

    #[On('fetchPartNo')]
    public function fetchPartNo(string $partNo)
    {
        $this->partNo = $partNo;
        $this->initItem();
    }

    public function updated(string $property, mixed $value): void
    {
        if ($property === 'partNo') {
            $this->resolveFixedSpecifications();
        }

        if ($property === 'rows.0.item') {
            $this->itemSuggestions = app(DimensionMasterRepositoryInterface::class)->search($value, $this->partNo);
        }

        if (preg_match('/^rows\.(\d+)\.measurements(_y)?\.\d+$/', $property, $matches)) {
            $this->evaluateRow((int) $matches[1]);
        }
        if (str_starts_with($property, 'rows.')) {
            $this->syncToParent();
        }
    }

    private function resolveFixedSpecifications(): void
    {
        $service = app(DimensionsService::class);

        foreach ($this->rows as $i => $row) {
            if (!$row['editable']) {
                $result = $service->displaySpecification($this->partNo, $row['item']);
                $this->rows[$i]['specification'] = $result['specification'] ?? '';
            }
        }
    }

    /**
     * Parses "2.9 - 3.1" into a range, or "MAX 0.20" into a ceiling.
     */
    private function parseSpecification(string $specification): ?array
    {
        $specification = trim($specification);

        if ($specification === '') {
            return null;
        }

        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*-\s*(-?\d+(?:\.\d+)?)$/', $specification, $m)) {
            return ['type' => 'range', 'min' => (float) $m[1], 'max' => (float) $m[2]];
        }

        if (preg_match('/^MAX\s+(-?\d+(?:\.\d+)?)$/i', $specification, $m)) {
            return ['type' => 'max', 'max' => (float) $m[1]];
        }

        return null;
    }

    private function judgeValue(float $value, array $spec): string
    {
        return match ($spec['type']) {
            'range' => ($value >= $spec['min'] && $value <= $spec['max']) ? 'O' : 'X',
            'max'   => $value <= $spec['max'] ? 'O' : 'X',
            default => '-',
        };
    }

    /**
     * Recomputes judgement for a row after one of its measurement inputs changes.
     */
    private function evaluateRow(int $i): void
    {
        $row  = $this->rows[$i];
        $spec = $this->parseSpecification($row['specification'] ?? '');

        if ($spec === null) {
            return;
        }

        // Editable row: only judge once every measurement slot for this card is filled.
        $measurements = $row['measurements'] ?? [];
        $filled = array_filter($measurements, fn($v) => $v !== null && trim((string) $v) !== '');

        if (count($filled) === 0) {
            $this->rows[$i]['judge'] = '-';
            return;
        }


        $overall = 'O';
        foreach ($filled as $val) {
            if (is_numeric($val) && $this->judgeValue((float) $val, $spec) === 'X') {
                $overall = 'X';
                break;
            }
        }

        $this->rows[$i]['judge'] = $overall;
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
        $result = app(DimensionsService::class)->displaySpecification($this->partNo, $itemName);

        $this->rows[0]['specification'] = $result['specification'] ?? '';
        $this->syncToParent();
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
    }
         focusMeasurement(cardIndex, slotIndex) {
        const el = document.querySelector(`[data-card-index='${cardIndex}'] input[data-measurement-index='${slotIndex}']`);
        if (el) el.focus();
    }
}">
    <div class="bg-gray-700 w-full">
        <p class="text-4xl font-extrabold text-center text-white p-4 mt-4">Dimensions</p>
    </div>
    <div class="flex flex-col md:flex-row gap-4 sm:gap-2 mx-auto justify-center items-center mt-3 @if($readonly) opacity-50 cursor-not-allowed @endif">
        @foreach ($rows as $i => $row)
        <div wire:key="dim-row-{{ $i }}" data-card-index="{{ $i }}" class="bg-white border border-gray-200 rounded-2xl p-6 mb-4 max-w-md">

            <div class="flex items-center gap-3 mb-5">
                <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center">
                    <i class="ti ti-ruler-2 text-xl text-green-700"></i>
                </div>
                <div>
                    <p class="font-medium text-base">Dimension entry</p>
                    <p class="text-sm text-gray-500">{{ $row['item'] ?: 'Enter item' }}</p>
                </div>
            </div>

            <div class="mb-4">
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

            <div class="mb-4">
                <label class="text-sm font-medium block mb-1.5">Specification</label>
                <input type="text" wire:model="rows.{{ $i }}.specification"
                    class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2"
                    placeholder="Enter specification" @if($readonly) disabled @endif>
            </div>

            <hr class="border-gray-200 my-4">

            <div class="mb-4">
                <label class="text-sm font-medium block mb-1.5">Note</label>
                <input type="text" wire:model="rows.{{ $i }}.CL"
                    class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2 text-gray-500"
                    placeholder="Refer to parts WI" @if($readonly) disabled @endif>
            </div>

            <div class="mb-1">
                <label class="text-sm font-medium block mb-1.5">Measurements</label>
                @if($row['item'] === 'Gap-Offset')
                <div class="class flex flex-col">
                    <div class="flex gap-2">
                        @for ($j = 0; $j < 5; $j++)
                            <input @if($readonly) disabled @endif type="text" wire:model.live.debounce.150ms="rows.{{ $i }}.measurements.{{ $j }}"
                            wire:key="dim-{{ $i }}-x-{{ $j }}"
                            data-x-index="{{ $j }}"
                            @if($j===0) data-first-measurement @endif
                            @keyup="if($event.target.value.trim() !== '') focusY({{ $i }}, {{ $j }})"
                            class="w-full bg-gray-50 border-0 rounded-lg text-center py-2"
                            placeholder="x{{ $j + 1 }}">
                            @endfor
                    </div>
                    <div class="flex gap-2">
                        @for ($j = 0; $j < 5; $j++)
                            <input @if($readonly) disabled @endif type="text" wire:model.live.debounce.150ms="rows.{{ $i }}.measurements_y.{{ $j }}"
                            wire:key="dim-{{ $i }}-y-{{ $j }}"
                            data-y-index="{{ $j }}"
                            @if($j < 4)
                            @keyup="if($event.target.value.trim() !== '') focusX({{ $i }}, {{ $j + 1 }})"
                            @else
                            @keyup="if($event.target.value.trim() !== '') focusNextCard({{ $i }})"
                            @endif
                            class="w-full bg-gray-50 border-0 rounded-lg text-center py-2"
                            placeholder="y{{ $j + 1 }}">
                            @endfor
                    </div>
                </div>
                @else
                <div class="flex gap-2">
                    @for ($j = 0; $j < 5; $j++)
                        <input @if($readonly) disabled @endif type="text" wire:model.live.debounce="rows.{{ $i }}.measurements.{{ $j }}"
                        @if($j===0) data-first-measurement @endif
                        @if($j < 4)
                        @keyup="if($event.target.value.trim() !== '') focusMeasurement({{ $i }}, {{ $j + 1 }})"
                        @else
                        @keyup="if($event.target.value.trim() !== '') focusNextCard({{ $i }})"
                        @endif
                        class="w-full bg-gray-50 border-0 rounded-lg text-center py-2"
                        placeholder="{{ $j + 1 }}">
                        @endfor
                </div>
                @endif

            </div>

            <hr class="border-gray-200 my-4">

            <div class="flex justify-end">
                <button @if($readonly) disabled @endif type="button" class="text-sm text-gray-500 hover:text-gray-700">Clear</button>
            </div>
            <div class="mb-4">
                <label class="text-sm font-medium block mb-1.5">Judgement</label>
                <input type="text" wire:model="rows.{{ $i }}.judge"
                    class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2 text-gray-500 text-center"
                    readonly>
            </div>
        </div>
        @endforeach
    </div>
</div>