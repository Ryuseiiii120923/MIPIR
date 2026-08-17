<?php


use App\Inspection\Actions\DraftAction;
use App\Inspection\Services\PPFLookUp\PpfLookUpService;
use App\Traits\WithLoading;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    use WithLoading;
    #[Validate('required|string|max:50')]
    public string $ppfno = '';

    public string $partNumber = '';
    public string $moldingDieNo = '';
    public ?int $noOfCavity = null;
    public string $nqrIssuanceCriteria = '';
    public int $selectedMachineNo;
    public bool $found = false;
    public bool $searching = false;
    public string $action = '';

    public function mount(int $selectedMachineNo){
        $this->selectedMachineNo = $selectedMachineNo;
    }

    #[On('lookup_ppf')]
    public function lookup(string $ppf = ''): void
    {
        if ($this->ppfno === '') {
            $this->ppfno = $ppf;
        }

        if ($this->action === '') {
            $this->addError(
                'ppfno',
                'Please select an action before searching.'
            );

            $this->stopLoading();

            return;
        }

        if (blank($this->ppfno)) {
            $this->addError(
                'ppfno',
                'Please enter a PPF No.'
            );

            $this->stopLoading();

            return;
        }

        $this->resetErrorBag();

        $this->reset([
            'partNumber',
            'moldingDieNo',
            'noOfCavity',
            'nqrIssuanceCriteria',
            'found',
        ]);

        $this->searching = true;

        $result = app(PpfLookUpService::class)
            ->findByPpfNo($this->ppfno);

        if (is_null($result)) {
            $this->addError(
                'ppfno',
                'No record found for this PPF No.'
            );

            $this->searching = false;

            $this->stopLoading();

            return;
        }

        $this->partNumber = $result['partNo'];
        $this->moldingDieNo = $result['moldNo'];
        $this->noOfCavity = $result['noOfCavity'];
        $this->nqrIssuanceCriteria = $result['nqr'];
        $this->found = true;

        // Notify other components only after PPF was found.
        $this->dispatch('ppf-checked', ppf: (int) $this->ppfno);

        $this->dispatch(
            'fetchPartNo',
            partNo: $this->partNumber
        );

        $this->dispatch('fromMaster', [
            'noOfCavity' => $this->noOfCavity,
            'nqr' => $this->nqrIssuanceCriteria
        ]);

        $this->syncDraft();

        $this->searching = false;

        $this->stopLoading();
    }

    public function syncDraft()
    {
        app(DraftAction::class)->put($this->ppfno, 'ppfLookup', [
            'ppf' => $this->ppfno,
            'partNo' => $this->partNumber,
            'moldNo' => $this->moldingDieNo,
            'noOfCavity' => $this->noOfCavity,
            'nqr' => $this->nqrIssuanceCriteria
        ]);
    }

    public function clear(): void
    {
        $this->reset(['ppfno', 'partNumber', 'moldingDieNo', 'noOfCavity', 'nqrIssuanceCriteria', 'found']);
        $this->resetErrorBag();
    }

    #[On('action-changed')]
    public function onActionChanged(string $action): void
    {
        $this->action = $action;
        if ($action) {
            $this->clear();
        }
    }
};
?>

<div class="w-full mx-auto bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 sm:p-8">

    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-900">PPF Lookup</h2>
            <p class="text-sm text-gray-500">Enter the PPF</p>
        </div>
    </div>

    <div class="mb-6">
        <label for="ppfno" class="block text-sm font-medium text-gray-700 mb-1.5">PPF No.</label>
        <div class="flex gap-2">
            <div class="relative flex-1">
                <input
                    @if($action==='view' || $action==='delete' ) disabled @endif
                    wire:model="ppfno"
                    wire:blur="lookup"
                    id="ppfno"
                    type="number"
                    placeholder="Enter PPF No."
                    class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 focus:ring-2 focus:ring-offset-0 text-sm py-2.5 px-3.5 transition
                        @error('ppfno') border-red-400 @enderror">
                <div wire:loading wire:target="lookup" class="absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                </div>
            </div>
        </div>
        @error('ppfno')
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Divider --}}
    <div class="border-t border-gray-100 mb-6"></div>

    {{-- Result fields --}}
    <div class="space-y-4">
        <x-ui.input-field id="partNumber"
            label="Part Number"
            type="text"
            wire:model="partNumber"
            readonly />
        <div class="grid grid-cols-2 gap-4">
            <x-ui.input-field id="moldingDieNo"
                label="Molding Die No."
                type="text"
                wire:model="moldingDieNo"
                readonly />
            <x-ui.input-field id="noOfCavity"
                label="No. of Cavity"
                type="text"
                wire:model="noOfCavity"
                readonly />
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">NQR Issuance Criteria</label>
        <textarea
            wire:model="nqrIssuanceCriteria"
            readonly
            rows="3"
            placeholder="—"
            class="w-full rounded-lg border-gray-200 bg-gray-50 text-gray-800 text-sm py-2.5 px-3.5 cursor-not-allowed resize-none"></textarea>
    </div>
    {{-- Status + actions --}}
    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
        <div>
            @if ($found)
            <span class="inline-flex items-center gap-1.5 text-sm text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Record found
            </span>
            @endif
        </div>
        <button
            @if($action==='view' || $action==='delete' ) disabled @endif
            wire:click="clear"
            type="button"
            class="text-sm text-gray-500 hover:text-gray-700 font-medium">
            Clear
        </button>
    </div>
</div>