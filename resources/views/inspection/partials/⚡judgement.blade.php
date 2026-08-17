<?php

use App\Inspection\Actions\DraftAction;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $judgement = '';
    public int $ppf = 0;
    public string $action = '';
    public string $dateOfJudgement = '';
    public int $selectedMachineNo;

    public function mount(int $selectedMachineNo){
        $this->selectedMachineNo = $selectedMachineNo;
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
            $this->judgement = $result['judgement'];
            $this->dateOfJudgement = $result['dateJudge'];
            $this->syncDraft();
        }
    }
    public function clear(): void
    {
        $this->reset(['judgement', 'ppf']);
        $this->resetErrorBag();
    }

    public function syncDraft()
    {
        app(DraftAction::class)->put($this->ppf, 'judgement', [
            'judgement' => $this->judgement,
            'dateOfJudge' => $this->dateOfJudgement ?? null
        ]);
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
            <h2 class="text-lg font-semibold text-gray-900">Judgement</h2>
            <p class="text-sm text-gray-500">Enter Judgement</p>
        </div>
    </div>

    <div class="flex flex-col items-center justify-center gap-2">
        <div>
            @if($action != 'add' && $action != '')
            <label for="dateOfJudgement" class="block text-sm font-medium text-gray-700 mb-1.5">Date of Judgement</label>
            <div class="flex gap-2">
                <input readonly @if($this->ppf === 0 || $this->action === 'view' || $this->action === 'delete') disabled @endif name="dateOfJudgement" wire:model="dateOfJudgement" class="border rounded p-2" />
            </div>
            @endif

            <label for="judgement" class="block text-sm font-medium text-gray-700 mb-1.5 mt-4">Judgement</label>
            <div class="flex gap-2">
                <select name="judgement" wire:model="judgement" @if($this->ppf === 0 || $this->action === 'view' || $this->action === 'delete') disabled @endif class="border rounded p-2" wire:change="syncDraft">
                    <option value="">-- Choose judgement--</option>
                    <option value="Passed">Passed</option>
                    <option value="Failed">Failed</option>
                </select>
            </div>
            @error('judgement')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>