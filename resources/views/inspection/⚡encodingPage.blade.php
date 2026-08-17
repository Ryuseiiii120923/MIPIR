<?php

use App\Inspection\Actions\CreateInspection;
use App\Inspection\Actions\DeleteInspection;
use App\Inspection\Actions\DraftAction;
use App\Inspection\Actions\UpdateInspection;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use App\Traits\HasNotifications;
use App\Traits\WithLoading;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    // use WithLoading;
    use HasNotifications;

    public string $action = '';
    public int $selectedPpf = 0;
    public string $selectedMachine;

    public function mount(string $selectedMachine){
        $this->selectedMachine = $selectedMachine;
    }
    
    public function setAction(string $action): void
    {
        $this->action = $action;
        $this->selectedPpf = 0;
        $this->dispatch('action-changed', action: $action);
            $this->dispatch('machine-selected', machine: $this->selectedMachine);
        // $this->dispatch('read-only', false);
    }

    #[On('ppf-checked')]
    public function onPpfChecked(int $ppf): void
    {
        $this->selectedPpf = $ppf;
    }

   public function submit(): void
{
    if ($this->action === 'view') {
        Log::debug('DashboardSave: view action, no-op');
        return;
    }

    if ($this->action === 'delete') {
        if (app(DeleteInspection::class)->execute($this->selectedPpf, $this->selectedMachine)) {
           PpfLookUpRepository::forgetMainData($this->selectedPpf, $this->selectedMachine);

            $this->notifyReload('success', 'Deleted Successfully');
        }

        return;
    }

    if ($this->action === 'edit') {
        app(UpdateInspection::class)->execute($this->selectedPpf,$this->selectedMachine);

       PpfLookUpRepository::forgetMainData($this->selectedPpf, $this->selectedMachine);
        $this->notifyReload('success', 'Updated Successfully');

        return;
    }

    $draft = app(DraftAction::class)->get($this->selectedPpf);

    foreach (['ppfLookup', 'process-details', 'check-time', 'judgement'] as $component) {
        if (!isset($draft[$component])) {
            $this->addError('Incomplete', "Missing {$component} data.");
            return;
        }
    }

    try {
        app(CreateInspection::class)->execute(
            $this->selectedPpf,
            $draft
        );

        app(DraftAction::class)->clear($this->selectedPpf);

       PpfLookUpRepository::forgetMainData($this->selectedPpf, $this->selectedMachine);
        $this->notifyReload('success', 'Inspection record saved.');

    } catch (\InvalidArgumentException $e) {

        Log::warning('DashboardSave: validation error', [
            'ppf' => $this->selectedPpf,
            'message' => $e->getMessage(),
        ]);

        $this->notifyFail(
            'Validation error',
            $e->getMessage()
        );

    } catch (\Throwable $e) {

        Log::error('DashboardSave: unexpected error', [
            'ppf' => $this->selectedPpf,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->notifyFail(
            'Save failed',
            'Something went wrong while saving. Please try again.'
        );
    }
}
};
?>

<div>
    <div class="flex justify-center gap-3 p-6">
        @foreach([
        'add' => ['ti-plus', 'Add', 'blue'],
        'edit' => ['ti-edit', 'Update', 'green'],
        'view' => ['ti-eye', 'View', 'yellow'],
        'delete' => ['ti-trash', 'Delete', 'red'],
        ] as $key => [$icon, $label, $color])
        <button
            wire:click="setAction('{{ $key }}')"
            @class([ 'flex flex-col items-center gap-1.5 py-3 flex-1 rounded-xl border-2 text-sm font-medium transition-all' , 'border-gray-200 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400'=> $action !== $key,
            'border-blue-600 bg-blue-50 text-blue-700' => $action === $key && $key === 'add',
            'border-green-700 bg-green-50 text-green-700' => $action === $key && $key === 'edit',
            'border-yellow-500 bg-yellow-50 text-yellow-700' => $action === $key && $key === 'view',
            'border-red-700 bg-red-50 text-red-700' => $action === $key && $key === 'delete',
            ])>
            <i class="ti {{ $icon }} text-xl"></i>
            <span>{{ $label }}</span>
        </button>
        @endforeach
    </div>
    <div class="w-full justify-center">
        <livewire:inspection::partials.table-data :selectedMachineNo="$selectedMachine"/>
    </div>
    <div class="flex flex-col md:flex-row gap-5">
        <livewire:inspection::partials.ppflookup :selectedMachineNo="$selectedMachine"/>
        <livewire:inspection::partials.process-details :selectedMachineNo="$selectedMachine"/>
    </div>
    <div class="mt-4">
        <livewire:inspection::partials.check-time :selectedMachineNo="$selectedMachine"/>
    </div>
    <div class="mt-4">
        <livewire:inspection::partials.judgement :selectedMachineNo="$selectedMachine"/>
    </div>

    <div class="flex items-center justify-center mt-4 @if($this->selectedPpf === 0) opacity-50 cursor-not-allowed @endif">
        @if($action !== '' && $action !== 'view')
        <div class="flex justify-center p-6">
            <button
                wire:click="submit"
                @if($action=='delete' ) @click.prevent="if (confirm('Are you sure you want to delete this ppf?')) $wire.submit()" @endif
                @class([ 'px-12 py-2.5 rounded-lg text-white text-sm font-medium transition' , 'bg-blue-700 hover:bg-blue-800'=> $action === 'add',
                'bg-green-700 hover:bg-green-800' => $action === 'edit',
                'bg-red-700 hover:bg-red-800' => $action === 'delete',
                ])>
                {{ match($action) {
            'add'    => 'Submit',
            'edit'   => 'Update',
            'delete' => 'Confirm Delete',
            default  => 'Submit'
        } }}
            </button>
        </div>
        @endif
    </div>

</div>