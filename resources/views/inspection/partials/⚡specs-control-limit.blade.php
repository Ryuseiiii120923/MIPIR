<?php

use App\Inspection\Repositories\SpecsControlRepository;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?float $xSpecs = null;
    public ?float $xControl = null;
    public ?float $ySpecs = null;
    public ?float $yControl = null;
    public string $action = '';
    public int $ppf = 0;
    public string $partNo = '';

    public function syncDraft() {}

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
    public function fetchPartNo(string $partNo){
        $this->partNo = $partNo;
        $this->resolveLimit();
    }
    
    #[On('ppf-checked')]
    public function fetchPPF(int $ppf){
        $this->ppf = $ppf;
        $this->resolveLimit();
    }

    public function resolveLimit(){
        $record = app(SpecsControlRepository::class)->fetchLimit($this->partNo);

        $this->xSpecs = $record->xSpecs ?? null;
        $this->xControl = $record->xControl ?? null;
        $this->ySpecs = $record->ySpecs ?? null;
        $this->yControl = $record->yControl  ?? null;
    }
};
?>

<div class="w-full mx-auto bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 sm:p-8 @if($this->ppf === 0 || $this->action === 'view' || $this->action === 'delete') opacity-50 cursor-not-allowed @endif">

    {{-- Header --}}
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

    <div class="flex flex-col items-center justify-center gap-2 mb-3">
        <div class="flex flex-row gap-3 justify-center">
            <div>
                <x-ui.input-field
                    id="xSpecs"
                    label="X-Specification Limit"
                    type="text"
                    wire:model="xSpecs"
                    wire:blur="resolveLimit"
                    placeholder="Enter X Specification Limit."
                    :disabled="$this->action === 'view'"
                    :class="$errors->has('x-specs') ? 'border-red-400 ring-1 ring-red-300' : ($this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : '')" />
                @error('xSpecs')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

             <div>
                <x-ui.input-field
                    id="xControl"
                    label="X-Control Limit"
                    type="text"
                    wire:model="xControl"
                    wire:blur="syncDraft"
                    placeholder="Enter X Control Limit."
                    :disabled="$this->action === 'view'"
                    :class="$errors->has('x-specs') ? 'border-red-400 ring-1 ring-red-300' : ($this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : '')" />
                @error('xControl')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

        </div>

         <div class="flex flex-row gap-3 justify-center">
            <div>
                <x-ui.input-field
                    id="ySpecs"
                    label="Y-Specification Limit"
                    type="text"
                    wire:model="ySpecs"
                    wire:blur="syncDraft"
                    placeholder="Enter Y Specification Limit."
                    :disabled="$this->action === 'view'"
                    :class="$errors->has('x-specs') ? 'border-red-400 ring-1 ring-red-300' : ($this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : '')" />
                @error('ySpecs')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

             <div>
                <x-ui.input-field
                    id="yControl"
                    label="Y-Control Limit"
                    type="text"
                    wire:model="yControl"
                    wire:blur="syncDraft"
                    placeholder="Enter Y Control Limit."
                    :disabled="$this->action === 'view'"
                    :class="$errors->has('x-specs') ? 'border-red-400 ring-1 ring-red-300' : ($this->action === 'view' ? 'cursor-not-allowed bg-gray-50' : '')" />
                @error('yControl')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

        </div>
    </div>
</div>