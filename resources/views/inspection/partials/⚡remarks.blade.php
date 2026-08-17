<?php

use Livewire\Component;

new class extends Component
{
    public string $remarks = '';
    public int $ppf = 0;
    public string $action = '';
    public string $selectedCheckTime = '';
    public bool $readonly = false;


    public function mount(string $action, ?string $selectedCheckTime, string $loadedRemarks): void
    {
        $this->action = $action;
        $this->remarks = $loadedRemarks ?? '';
        $this->selectedCheckTime = $selectedCheckTime;
        if ($this->action === 'view' || $this->action === 'delete') {
            $this->readonly = true;
        }
    }
    public function updated(string $property): void
    {
        if ($property === 'remarks') {
            $this->syncToParent();
        }
    }

    private function syncToParent(): void
    {
        if ($this->selectedCheckTime !== '') {
            $this->dispatch('remarks-synced', selectedCheckTime: $this->selectedCheckTime, remarks: $this->remarks);
        }
    }
};
?>

<div>
    <div class="mb-4">
        <label class="text-sm font-medium block mb-1.5">Remarks</label>
        <input type="text" wire:model.live.debounce.400ms="remarks"
            class="w-full bg-gray-50 border-0 rounded-lg px-3 py-2"
            placeholder="Enter Remarks(Corrective Action Done)" @if($readonly) disabled @endif>
    </div>
</div>