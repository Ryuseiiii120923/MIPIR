<?php

use App\Inspection\Repositories\SpecsControlRepository;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    // X - Specification Limit
    public ?float $CSLx = null;
    public ?float $USLx = null;
    public ?float $LSLx = null;

    // X - Control Limit
    public ?float $CCLx = null;
    public ?float $UCLx = null;
    public ?float $LCLx = null;

    // R - Specification Limit
    public ?float $CSLr = null;
    public ?float $USLr = null;
    public ?float $LSLr = null;

    // R - Control Limit
    public ?float $CCLr = null;
    public ?float $UCLr = null;
    public ?float $LCLr = null;

    public string $action = '';
    public int $ppf = 0;
    public string $partNo = '';
    public string $dimItem = '';

    public int $rowIndex = -1;

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
        ]);
    }

    public function mount(string $dimItem = '', string $partNo = '', int $rowIndex = -1)
    {
        $this->partNo = $partNo;
        $this->dimItem = $dimItem;
        $this->rowIndex = $rowIndex;
        $this->resolveLimit();
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
        $record = app(SpecsControlRepository::class)->fetchLimit($this->partNo, $this->dimItem);

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
            <p class="text-sm text-gray-500">Enter the Specification and Control Limit</p>
        </div>
    </div>

    @php
    $fieldGroups = [
    'X - SPECIFICATION LIMIT' => ['CSLx', 'USLx', 'LSLx'],
    'X - CONTROL LIMIT' => ['CCLx', 'UCLx', 'LCLx'],
    'R - SPECIFICATION LIMIT' => ['CSLr', 'USLr', 'LSLr'],
    'R - CONTROL LIMIT' => ['CCLr', 'UCLr', 'LCLr'],
    ];
    @endphp

    <div class="flex flex-col gap-3">
        @foreach ($fieldGroups as $groupLabel => $fields)
        <div class="flex items-center gap-3">
            <span class="w-40 text-sm font-medium text-gray-700 shrink-0">{{ $groupLabel }}</span>
            @foreach ($fields as $field)
            <div class="flex-1">
                <x-ui.input-field
                    id="{{ $field }}"
                    label="{{ $field }}"
                    type="text"
                    wire:model="{{ $field }}"
                    wire:blur="syncDraft"
                    placeholder="Enter {{ $field }}"
                    :disabled="$this->action === 'view'"
                    :class="$errors->has('{{ $field }}') ? 'border-red-400 ring-1 ring-red-300' : ($this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : '')" />
                @error($field)
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
</div>