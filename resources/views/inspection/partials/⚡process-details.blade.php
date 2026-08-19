<?php



use App\Inspection\Actions\DraftAction;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;

new class extends \Livewire\Component
{
    #[Validate('required|string|max:50')]
    public string $productionLotNo = '';

    public string $device = '';
    public int $machineNo = 0;
    public int $ppf = 0;
    public bool $saved = false;
    public string $action = '';
    public int $selectedMachineNo;

    public function mount(int $selectedMachineNo)
    {
        $this->selectedMachineNo = $selectedMachineNo;
    }

    public function syncDraft()
    {
        app(DraftAction::class)->put($this->ppf, 'process-details', [
            'productionLotNo' => $this->productionLotNo,
            'machineNo' => $this->machineNo,
            'device' => $this->device
        ]);
    }

    public function clear(): void
    {
        $this->reset('productionLotNo');
        $this->saved = false;
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
        $this->ppf = $ppf;
        if ($this->action != 'add') {
            $result = app(PpfLookUpRepository::class)->getMainData($ppf, $this->selectedMachineNo);

            $this->productionLotNo = $result['productionLotNo'];
            $this->machineNo = $result['machineNo'];
        }
        $this->syncDraft();
    }

    #[On('machine-selected')]
    public function machineSelected(int $machine)
    {
        $this->machineNo = $machine;
    }

    #[On('field-error')]
    public function onFieldError(string $field, string $message): void
    {
        $this->addError($field, $message);
        $this->dispatch('focus-field', id: $field);
    }
} ?>

<div
    x-on:focus-field.window="document.getElementById($event.detail.id)?.focus()"
    class="w-full mx-auto bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 sm:p-8 @if($this->ppf === 0) opacity-50 cursor-not-allowed @endif">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Production Encoding</h2>
            <p class="text-sm text-gray-500">Enter the production lot and machine number</p>
        </div>
    </div>

    {{-- Success banner --}}
    @if ($saved)
    <div class="mb-4 flex items-center gap-2 rounded-lg bg-emerald-50 text-emerald-700 text-sm px-3.5 py-2.5">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        Record saved successfully.
    </div>
    @endif

    {{-- Form fields --}}
    <div class="space-y-4 @if($action === 'view' || $action === 'delete') opacity-50 cursor-not-allowed @endif">
        <div>
            <x-ui.input-field
                id="productionLotNo"
                label="Production Lot No."
                type="text"
                wire:model="productionLotNo"
                wire:blur="syncDraft"
                placeholder="Enter production lot no."
                :disabled="$this->action === 'view'"
                :class="$errors->has('productionLotNo') ? 'border-red-400 ring-1 ring-red-300' : ($this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : '')" />
            @error('productionLotNo')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-ui.input-field
            id="machineNo"
            label="Machine No."
            type="text"
            wire:model="machineNo"
            wire:blur="syncDraft"
            placeholder="Enter machine no."
            :disabled="$this->action === 'view'"
            :class="$this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : ''"
            readonly />
    </div>
<div>
    <hr class="my-2 border-gray-300">
</div>
    {{-- Header --}}
    <div class="flex items-center gap-3 mt-6 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 7h18M3 7v10h18V7M7 7v3m4-3v3m4-3v3m4-3v3M6 17v-4h12v4" />
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Measuring Device</h2>
            <p class="text-sm text-gray-500">Enter the measuring device</p>
        </div>
    </div>

    <div class="space-y-4 @if($action === 'view' || $action === 'delete') opacity-50 cursor-not-allowed @endif">
        <div>
            <x-ui.input-field
                id="device"
                label="Measuring Device"
                type="text"
                wire:model="device"
                wire:blur="syncDraft"
                placeholder="Enter production measuring device."
                :disabled="$this->action === 'view'"
                :class="$errors->has('device') ? 'border-red-400 ring-1 ring-red-300' : ($this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : '')" />
            @error('device')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

    </div>
</div>