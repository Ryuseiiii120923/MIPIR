<?php

use App\Dashboard\Repositories\SpecsControlRepository;
use App\Dashboard\Services\DimensionEncodingService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Inspection\Services\Dimensions\DimensionsService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;

new class extends Component
{
    use HasNotifications;

    public ?float $CSLx = null;
    public ?float $USLx = null;
    public ?float $LSLx = null;

    public ?float $CCLx = null;
    public ?float $UCLx = null;
    public ?float $LCLx = null;

    public ?float $CSLr = null;
    public ?float $USLr = null;
    public ?float $LSLr = null;

    public ?float $CCLr = null;
    public ?float $UCLr = null;
    public ?float $LCLr = null;

    public string $action = '';
    public int $ppf = 0;
    #[Locked]
    public string $specification = '';

    #[Locked]
    public string $partNo = '';

    #[Locked]
    public string $dimItem = '';

    #[Locked]
    public bool $standalone = false;
    public int $rowIndex = -1;

    #[Locked]
    public array $moldingDieNo = [];

    public string $selectedDie = '';
    #[Reactive]
    public array $measurements = [];

    public function syncDraft()
    {
        $this->dispatch('control-limit-synced', draft: [
            'dimItem' => $this->dimItem,
            'partNo' => $this->partNo,
            'CSLx' => $this->CSLx,
            'USLx' => $this->USLx,
            'LSLx' => $this->LSLx,
            'CCLx' => $this->CCLx,
            'UCLx' => $this->UCLx,
            'LCLx' => $this->LCLx,
            'CSLr' => $this->CSLr,
            'USLr' => $this->USLr,
            'LSLr' => $this->LSLr,
            'CCLr' => $this->CCLr,
            'UCLr' => $this->UCLr,
            'LCLr' => $this->LCLr,
            'moldingDieNo' => $this->selectedDie,
        ]);
    }

    public function mount(
        array $moldingDieNo = [],
        string $dimItem = '',
        string $partNo = '',
        int $rowIndex = -1,
        bool $standalone = false,
        ?float $USLx = null,
        ?float $LSLx = null,
        ?float $specs = null,
        ?string $moldDie = '',
        array $measurements = []
    ) {
        $this->measurements = $measurements;
        $this->moldingDieNo = array_values(array_unique(array_map('strval', $moldingDieNo)));
        $this->selectedDie = $this->moldingDieNo[0] ?? ($moldDie ? (string) $moldDie : '');
        $this->partNo = $partNo;
        $this->dimItem = $dimItem;
        $this->rowIndex = $rowIndex;
        $this->standalone = $standalone;
        $this->specification = (string) $specs;

        if ($standalone) {
            $this->USLx = $this->specification + $USLx;
            $this->LSLx = $this->specification - $LSLx;
            $this->CSLx = (float) $this->specification;
        }

        $this->resolveLimit();
    }

    #[Computed]
    public function judgements(): array
    {
        if ($this->standalone || $this->measurements === []) {
            return [];
        }

        $j = app(DimensionsService::class)->judgeXBar($this->measurements, [
            'LCLx' => $this->LCLx,
            'UCLx' => $this->UCLx,
            'LSLr' => $this->LSLr,
            'USLr' => $this->USLr,
            'LCLr' => $this->LCLr,
            'UCLr' => $this->UCLr,
        ]);

        return [
            'X - CONTROL LIMIT'       => $j['judgeXCL'],
            'R - SPECIFICATION LIMIT' => $j['judgeRSL'],
            'R - CONTROL LIMIT'       => $j['judgeRCL'],
        ];
    }
    public function updatedSelectedDie(string $value): void
    {
        if ($value !== '' && ! in_array($value, $this->moldingDieNo, true)) {
            $this->selectedDie = $this->moldingDieNo[0] ?? '';
        }

        $this->resetErrorBag();
        $this->resolveLimit();
    }

    protected function rules(): array
    {
        $rules = [];

        foreach (['CSLx', 'USLx', 'LSLx', 'CCLx', 'UCLx', 'LCLx', 'CSLr', 'USLr', 'LSLr', 'CCLr', 'UCLr', 'LCLr'] as $field) {
            $rules[$field] = ['nullable', 'numeric'];
        }

        foreach ([['UCLx', 'LCLx'], ['USLr', 'LSLr'], ['UCLr', 'LCLr']] as [$upper, $lower]) {
            if ($this->{$upper} !== null && $this->{$lower} !== null) {
                $rules[$upper][] = "gte:{$lower}";
            }
        }

        return $rules;
    }
    public function save(DimensionEncodingService $service): void
    {
        $this->validate();

        if ($this->selectedDie === '') {
            $this->addError('selectedDie', 'Select a molding die no.');
            return;
        }

        try {
            $service->saveControlLimits($this->partNo, $this->dimItem, (int) $this->selectedDie, [
                'CSLx' => $this->CSLx,
                'USLx' => $this->USLx,
                'LSLx' => $this->LSLx,
                'CCLx' => $this->CCLx,
                'UCLx' => $this->UCLx,
                'LCLx' => $this->LCLx,
                'CSLr' => $this->CSLr,
                'USLr' => $this->USLr,
                'LSLr' => $this->LSLr,
                'CCLr' => $this->CCLr,
                'UCLr' => $this->UCLr,
                'LCLr' => $this->LCLr,
            ]);

            $this->notifySuccess('Saved', "Limits saved for die {$this->selectedDie}");
            $this->dispatch('limits-saved');
        } catch (\Throwable $e) {
            Log::error('Control limit save failed', [
                'part_no'  => $this->partNo,
                'dim_item' => $this->dimItem,
                'die_no'   => $this->selectedDie,
                'error'    => $e->getMessage(),
            ]);

            $this->notifyFail('Failed', 'Failed to save limits. Please try again.');
        }
    }
    #[On('dimension-spec-limits-changed')]
    public function onSpecLimitsChanged(int $rowIndex, float $CSLx, float $USLx, float $LSLx): void
    {
        if ($rowIndex !== $this->rowIndex) {
            return;
        }

        $this->CSLx = $CSLx;
        $this->USLx = $USLx;
        $this->LSLx = $LSLx;
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

    public function clear(): void
    {
        $this->reset(['ppf', 'partNo']);
        $this->resetErrorBag();
    }

    #[On('fetchPartNo')]
    public function fetchPartNo(string $partNo)
    {
        $this->partNo = $partNo;
        $this->resolveLimit();
    }

    #[On('ppf-checked')]
    public function fetchPPF(int $ppf)
    {
        $this->ppf = $ppf;
        $this->resolveLimit();
    }

    #[On('dimension-item-changed')]
    public function onDimItemChanged(string $dimItem): void
    {
        if ($dimItem !== $this->dimItem) {
            $this->dimItem = $dimItem;
            $this->resolveLimit();
        }
    }

    public function resolveLimit()
    {
        $record = $this->selectedDie !== ''
            ? app(SpecsControlRepository::class)->fetchLimit(
                $this->partNo,
                $this->dimItem,
                (int) $this->selectedDie
            )
            : null;

        $this->CCLx = $record->CCLx ?? null;
        $this->UCLx = $record->UCLx ?? null;
        $this->LCLx = $record->LCLx ?? null;
        $this->CSLr = $record->CSLr ?? null;
        $this->USLr = $record->USLr ?? null;
        $this->LSLr = $record->LSLr ?? null;
        $this->CCLr = $record->CCLr ?? null;
        $this->UCLr = $record->UCLr ?? null;
        $this->LCLr = $record->LCLr ?? null;
    }
};
?>

<div class="w-full mx-auto bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 sm:p-8">

    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 3v18m0-18l-6 4m6-4l6 4M4 7l-2 6a3 3 0 006 0l-2-6m14 0l-2 6a3 3 0 006 0l-2-6M4 7h4m8 0h4" />
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Specification and Control Limit</h2>
            <p class="text-sm text-gray-500">
                @if ($standalone)
                {{ $dimItem }} &middot; Part No: {{ $partNo }}
                @if ($specification !== '') &middot; Spec: {{ $specification }} @endif
                @else
                Enter the Specification and Control Limit
                @endif
            </p>
        </div>
    </div>

    @if (! empty($moldingDieNo))
    <div class="mb-5"
        wire:key="die-select-{{ $partNo }}-{{ $dimItem }}"
        x-data="{
        open: false,
        q: '',
        dies: @js($moldingDieNo),
        get filtered() {
            return this.q === ''
                ? this.dies
                : this.dies.filter(d => d.toLowerCase().includes(this.q.toLowerCase()));
        },
        pick(d) {
            $wire.$set('selectedDie', d);
            this.open = false;
            this.q = '';
        }
    }"
        @click.outside="open = false"
        @keydown.escape.stop="open = false">

        <label class="block text-sm font-medium text-gray-700 mb-1">Molding Die No</label>

        <div class="relative">
            <button type="button"
                @click="open = !open; $nextTick(() => open && $refs.search.focus())"
                @disabled($action==='view' )
                class="w-full flex items-center justify-between rounded-lg border bg-white px-3 py-2 text-sm text-left focus:border-emerald-500 focus:ring-emerald-500 {{ $errors->has('selectedDie') ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-300' }}">
                <span class="{{ $selectedDie === '' ? 'text-gray-400' : 'text-gray-900' }}">
                    {{ $selectedDie !== '' ? $selectedDie : 'Select molding die no' }}
                </span>
                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition.opacity
                class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg">
                <div class="p-2 border-b border-gray-100">
                    <input type="text" x-ref="search" x-model="q" placeholder="Search die no"
                        @keydown.enter.prevent="filtered.length && pick(filtered[0])"
                        class="w-full rounded-md border border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <ul class="max-h-52 overflow-y-auto py-1">
                    <template x-for="d in filtered" :key="d">
                        <li>
                            <button type="button" @click="pick(d)"
                                class="w-full px-3 py-2 text-sm text-left hover:bg-emerald-50"
                                :class="d === $wire.selectedDie ? 'bg-emerald-50 font-medium text-emerald-700' : 'text-gray-700'"
                                x-text="d"></button>
                        </li>
                    </template>
                    <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400">No die found.</li>
                </ul>
            </div>
        </div>

        @error('selectedDie')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    @endif

    @php
    $fieldGroups = [
    'X - SPECIFICATION LIMIT' => ['CSLx', 'USLx', 'LSLx'],
    'X - CONTROL LIMIT' => ['CCLx', 'UCLx', 'LCLx'],
    'R - SPECIFICATION LIMIT' => ['CSLr', 'USLr', 'LSLr'],
    'R - CONTROL LIMIT' => ['CCLr', 'UCLr', 'LCLr'],
    ];
    $xSpecFields = ['CSLx', 'USLx', 'LSLx'];
    @endphp

    <div class="flex flex-col gap-3">
        @foreach ($fieldGroups as $groupLabel => $fields)
        <div class="flex items-center gap-3">
            <span class="w-40 text-sm font-medium text-gray-700 shrink-0">{{ $groupLabel }}</span>
            @foreach ($fields as $field)
            @php
            $locked = $this->action === 'view' || ($standalone && in_array($field, $xSpecFields, true));
            @endphp
            <div class="flex-1">
                <x-ui.input-field
                    id="{{ $field }}"
                    label="{{ $field }}"
                    type="text"
                    wire:model="{{ $field }}"
                    wire:blur="syncDraft"
                    placeholder="Enter {{ $field }}"
                    :disabled="$locked"
                    :class="$errors->has($field) ? 'border-red-400 ring-1 ring-red-300' : ($locked ? 'cursor-not-allowed bg-gray-50' : '')" />
                @error($field)
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            @endforeach
            @if (! empty($this->judgements))
            @php $verdict = $this->judgements[$groupLabel] ?? null; @endphp
            <div class="w-24 shrink-0" wire:key="verdict-{{ $groupLabel }}">
                @if (array_key_exists($groupLabel, $this->judgements))
                <span class="block text-xs font-medium text-gray-600 mb-1">Judgement</span>
                <div class="rounded-lg px-3 py-2 text-center text-sm font-semibold
        {{ $verdict === 'X' ? 'bg-red-100 text-red-700' : ($verdict === 'O' ? 'bg-green-100 text-green-700' : 'bg-gray-50 text-gray-400') }}">
                    {{ $verdict ?? '—' }}
                </div>
                @endif
            </div>
            @endif
        </div>
        @endforeach
    </div>

    @if ($standalone)
    <div class="mt-6 flex justify-end gap-2">
        <button type="button"
            wire:click="$dispatch('limits-closed')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            Cancel
        </button>
        <button type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="save">Save</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>
    </div>
    @endif
</div>