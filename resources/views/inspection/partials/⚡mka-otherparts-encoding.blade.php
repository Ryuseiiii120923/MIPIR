<?php

use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;
use App\Inspection\Services\Dimensions\DimensionsService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

new class extends Component
{
    use WithFileUploads;
    use HasNotifications;

    // Shared upload target — the row it belongs to is passed to importMeasurements()
    public $excelFile = null;

    public string $ppfNumber = '';
    public array $rows = [];
    public array $itemSuggestions = [];
    public string $partNo = '';
    public ?string $selectedCheckTime = null;
    public bool $readonly = false;
    public string $action = '';

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
        $this->rows = $loadedRows ?? [];

        foreach ($this->rows as $i => $row) {
            $this->rows[$i]['forXBar'] ??= false;

            if (!array_key_exists('revealed', $row)) {
                $count = count($row['measurements'] ?? []);
                $this->rows[$i]['revealed'] = $count > 0;
                $this->rows[$i]['mode'] = $count > 5 ? 'tightened' : ($count > 0 ? 'normal' : null);
                $this->rows[$i]['sets'] = $count > 0 ? (int) ceil($count / 5) : null;
            }
        }

        $this->resolveFixedSpecifications();
        $this->action = $action;
        $this->syncToParent();

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
                continue;
            }

            // Editable row already has an item name (loaded from saved data)
            // but no spec resolved yet — fetch it now instead of waiting for blur.
            if (trim($row['item'] ?? '') !== '' && empty($row['specType'])) {
                $master = $repo->getMasterSpecification($this->partNo, $row['item'])
                    ?? $repo->getTempMaster($this->partNo, $row['item']);

                $this->applyMasterSpecification($i, $master);
            }
        }
    }

    private function evaluateRow(int $i): void
    {
        $row = $this->rows[$i];
        $limits = $this->service()->computeLimits($row);
        $this->rows[$i]['specification'] = $this->service()->formatSpecification($row);
        $this->rows[$i]['upperLimit'] = $limits['upperLimit'] ?? null;
        $this->rows[$i]['lowerLimit'] = $limits['lowerLimit'] ?? null;
        $this->rows[$i]['judge'] = $this->service()->judgeRow($row, $limits);

        if ($limits !== null) {
            $this->dispatch(
                'dimension-spec-limits-changed',
                rowIndex: $i,
                CSLx: (float) ($row['specNominal'] ?? 0),
                USLx: (float) $limits['judgingUpperLimit'],
                LSLx: (float) $limits['judgingLowerLimit'],
            );
        }
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

    public function initItem(int $i = 0): void
    {
        $itemName = trim($this->rows[$i]['item'] ?? '');

        if ($itemName === '') {
            return;
        }

        $this->dispatch('dimension-item-changed', dimItem: $itemName);

        $master = app(DimensionMasterRepositoryInterface::class)
            ->getMasterSpecification($this->partNo, $itemName);

        if ($master === null) {
            unset($this->rows[$i]['specType'], $this->rows[$i]['specNominal'], $this->rows[$i]['specTolerance'], $this->rows[$i]['specUpper'], $this->rows[$i]['specLower']);
        }

        $this->applyMasterSpecification($i, $master);
        $this->syncToParent();
    }

    public function revealRow(int $index): void
    {
        if ($this->readonly || ! array_key_exists($index, $this->rows)) {
            return;
        }

        $this->initItem($index);
        $this->rows[$index]['revealed'] = true;

        $this->syncToParent();
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

    public function importMeasurements(int $i): void
    {
        $errorKey = "rows.{$i}.upload";
        $this->resetErrorBag($errorKey);

        if (empty($this->rows[$i]['item'])) {
            $this->notifyFail(
                'Dimension Item Empty',
                'Please insert a Dimension Item first.'
            );
            return;
        }

        if ($this->readonly || ! array_key_exists($i, $this->rows)) {
            $this->reset('excelFile');
            return;
        }

        try {
            $this->validate([
                'excelFile' => 'required|file|mimes:xlsx,xls,csv|max:5120',
            ]);
        } catch (ValidationException $e) {
            $this->addError($errorKey, $e->validator->errors()->first('excelFile'));
            $this->reset('excelFile');
            return;
        }

        try {
            $sheet  = IOFactory::load($this->excelFile->getRealPath())->getActiveSheet();
            $values = $this->parseMeasurements($sheet->toArray(null, true, true, false));
        } catch (\Throwable $e) {
            Log::error('Dimension measurement import failed', [
                'partNo'  => $this->partNo,
                'item'    => $this->rows[$i]['item'] ?? '',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            $this->addError($errorKey, 'Unable to read the file. Please check the format and try again.');
            $this->reset('excelFile');
            return;
        }

        if (empty($values)) {
            $this->addError($errorKey, 'No measurements were found in the file.');
            $this->reset('excelFile');
            return;
        }

        $count = count($values);
        $sets  = max(1, (int) ceil($count / 5));

        $this->rows[$i]['measurements'] = $values;
        $this->applyMeasurementCount($i, $sets * 5);
        $this->rows[$i]['mode']     = $count <= 5 ? 'normal' : 'tightened';
        $this->rows[$i]['sets']     = $sets;
        $this->rows[$i]['revealed'] = true;

        $this->evaluateRow($i);
        $this->syncToParent();

        $this->reset('excelFile');
    }

    private function parseMeasurements(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            if (! is_numeric($row[3] ?? null)) {
                continue;
            }

            $value = $row[count($row) - 1] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $values[] = (float) $value;
        }

        return $values;
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

    public function AddNewDimension()
    {
        $this->rows[] = [
            'item' => '',
            'editable' => true,
            'forXBar' => false,
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
            'specLower' => '',
            'device' => ''
        ];
    }

    public function removeDimension(int $index): void
    {
        if ($this->readonly) {
            return;
        }

        if (!array_key_exists($index, $this->rows)) {
            return;
        }

        if (empty($this->rows[$index]['editable'])) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);

        $this->syncToParent();
    }
}
?>

<div
    x-on:focus.capture="$event.target.matches('input[type=text], input[type=number]') && $event.target.select()">
    <div class="bg-gray-700 w-full">
        <p class="text-4xl font-extrabold text-center text-white p-4 mt-4">Dimensions</p>
    </div>

    <div class="mt-3">
        <button
            type="button"
            wire:click="AddNewDimension"
            @if($readonly) disabled @endif
            class="p-3 rounded-xl bg-green-700 text-md text-white
               hover:bg-green-600
               transition-colors duration-200
               flex items-center gap-1">
            Add New Dimension
        </button>
    </div>


    <div class="w-full mx-auto mt-3 @if($readonly) opacity-50 cursor-not-allowed @endif">
        @foreach ($rows as $i => $row)
        @php $isXBar = (bool) ($row['forXBar'] ?? false); @endphp
        <div wire:key="dim-row-{{ $i }}" data-card-index="{{ $i }}" class="w-full mb-4">
            @if (!$row['revealed'])

            <button
                type="button"
                wire:click="revealRow({{ $i }})"
                @if($readonly) disabled @endif
                class="w-full flex items-center justify-between bg-white border border-gray-200 rounded-2xl p-5 hover:border-blue-300 hover:bg-blue-50/40 transition-all text-left">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center">
                        <i class="ti ti-ruler-2 text-xl text-green-700"></i>
                    </div>
                    <div>
                        <p class="font-medium text-base">Dimension entry</p>
                        @if($isXBar)
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">X-Bar Table</span>
                        @endif
                        <p class="text-sm text-gray-500">{{ $row['item'] ?: 'Enter item' }}</p>
                    </div>
                </div>
                <span class="text-sm text-blue-600 font-medium flex items-center gap-1">
                    Set up <i class="ti ti-chevron-right"></i>
                </span>
            </button>

            @else

            <div class="bg-white border border-gray-200 rounded-2xl p-6 w-full">
                <div class="flex items-start justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-green-100 flex items-center justify-center">
                            <i class="ti ti-ruler-2 text-xl text-green-700"></i>
                        </div>
                        <div>
                            <p class="font-medium text-base">Dimension entry</p>
                            <p class="text-sm text-gray-500 flex flex-wrap items-center gap-1">
                                <span>{{ $row['item'] ?: 'Enter item' }}</span>
                                @if($isXBar)
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">X-Bar Table</span>
                                @endif
                                @if(!empty($row['mode']))
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $row['mode'] === 'tightened' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">
                                    {{ $row['mode'] === 'tightened' ? 'Tightened · ' . $row['sets'] . ' set' . ($row['sets'] > 1 ? 's' : '') : 'Normal' }}
                                </span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        @if($row['editable'])
                        <button
                            type="button"
                            @if($readonly) disabled @endif
                            @click.prevent="if (confirm('Delete this dimension row?')) $wire.removeDimension({{ $i }})"
                            class="text-sm text-red-500 hover:text-red-700 flex items-center gap-1">
                            <i class="ti ti-trash text-base"></i> Delete
                        </button>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="text-sm font-medium block mb-1.5">Dimension Item</label>
                        @if ($row['editable'])
                        <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.item"
                            wire:blur="initItem({{ $i }})"
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
                        <label class="text-sm font-medium block mb-1.5">Measuring Device</label>
                        <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.device"
                            class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2 text-center"
                            placeholder="Enter the measuring device use" @if($readonly) disabled @endif>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium block mb-1.5">Specification</label>
                        <div class="flex items-center gap-2">
                            <select wire:model.live.debounce.400ms="rows.{{ $i }}.specType"
                                class="bg-gray-50 border-0 rounded-lg px-2 py-2 text-sm"
                                disabled>

                                <option value="">Select</option>
                                <option value="max">MAX</option>
                                <option value="min">MIN</option>
                                <option value="tolerance">±</option>
                                <option value="tolerance_diff">TOLERANCE DIFF</option>
                            </select>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
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
                        </div>
                    </div>
                </div>

                @if($isXBar)
                <div class="mb-4 rounded-xl border border-purple-200 bg-purple-50/40 p-4">
                    <p class="text-sm font-medium text-purple-700 mb-2">X-Bar Control Limits</p>
                    <livewire:inspection::partials.specs-control-limit
                        :dimItem="$row['item']"
                        :partNo="$partNo"
                        :rowIndex="$i"
                        :key="'specs-control-limit-'.$i" />
                </div>
                @endif

                <div class="mb-4">
                    <label class="text-sm font-medium block mb-1.5">Control Limit</label>
                    <input type="text" wire:model.live.debounce.400ms="rows.{{ $i }}.CL"
                        class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2 text-gray-500"
                        placeholder="Refer to parts WI" @if($readonly) disabled @endif>
                </div>

                <hr class="border-gray-200 my-4">

                {{-- Measurements --}}
                @php
                $filled = array_filter($row['measurements'] ?? [], fn ($m) => $m !== '' && $m !== null);
                @endphp
                <div class="mb-1">
                    <label class="text-sm font-medium block mb-1.5">
                        Measurements
                        <span class="text-gray-400 font-normal">({{ count($filled) }} total)</span>
                    </label>
                </div>
                {{-- Drop zone: Excel / CSV --}}
                @unless($readonly)
                <div
                    x-data="{ uploading: false, parsing: false, progress: 0, dragging: false }"
                    x-on:livewire-upload-start="uploading = true; progress = 0"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                    x-on:livewire-upload-finish="uploading = false; parsing = true; $wire.importMeasurements({{ $i }}).finally(() => { parsing = false; $refs.fileInput.value = '' })"
                    x-on:livewire-upload-error="uploading = false"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; if (!uploading && !parsing) { $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change')) }"
                    :class="(uploading || parsing) ? 'border-emerald-400 bg-emerald-50 cursor-wait' : (dragging ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300 cursor-pointer')"
                    class="border-2 border-dashed rounded-xl p-6 text-center transition mb-4"
                    @click="if (!uploading && !parsing) $refs.fileInput.click()">

                    <input type="file" x-ref="fileInput" wire:model="excelFile" accept=".xlsx,.xls,.csv" class="hidden"
                        :disabled="uploading || parsing" @click.stop>

                    <template x-if="!uploading && !parsing">
                        <p class="text-sm text-gray-500">
                            Drop the Excel/CSV file for <strong>{{ $row['item'] ?: 'this dimension' }}</strong>, or click to browse
                        </p>
                    </template>

                    <template x-if="uploading">
                        <div class="flex flex-col items-center gap-2">
                            <p class="text-sm text-emerald-700 font-medium">Uploading… <span x-text="progress"></span>%</p>
                            <div class="w-full bg-emerald-100 rounded-full h-1.5 max-w-xs">
                                <div class="bg-emerald-600 h-1.5 rounded-full transition-all" :style="`width: ${progress}%`"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="parsing">
                        <p class="text-sm text-emerald-700 font-medium">Parsing file...</p>
                    </template>
                </div>
                @error('rows.' . $i . '.upload')
                <p class="-mt-2 mb-4 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @endunless

                @if(count($filled) > 0)
                <div class="flex flex-wrap gap-2 text-sm text-gray-600 mb-4">
                    @foreach($row['measurements'] as $j => $m)
                    @if($m !== '' && $m !== null)
                    <span wire:key="dim-{{ $i }}-m-{{ $j }}" class="border rounded-lg px-2 py-1">{{ $j + 1 }}: {{ $m }}</span>
                    @endif
                    @endforeach
                </div>
                @else
                <p class="text-sm text-gray-400 mb-4">No file uploaded yet.</p>
                @endif

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
</div>