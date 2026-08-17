<?php

use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Traits\HasNotifications;
use App\Traits\WithLoading;

new class extends Component
{
    use WithPagination;
    use WithLoading;
    use HasNotifications;
    public $isHideTable = true;
    public string $search = '';
    public string $action = '';
    public int $encoder = 0;
    public int $selectedMachineNo= 0;

    public function mount(int $selectedMachineNo)
    {
        $this->encoder = Auth::user()->EmployeeID;
        $this->selectedMachineNo = $selectedMachineNo;
    }


    #[On('action-changed')]
    public function onActionChanged($action)
    {
        $this->action = $action;
        if ($this->action != 'add') {
            $this->isHideTable = false;
        } else {
            $this->isHideTable = true;
        }
        $this->resetPage();
        $this->search = '';
    }

    #[Computed]
    public function data()
    {
        if (empty($this->action)) {
            return;
        }

        return app(PpfLookUpRepository::class)->getDataforSearch($this->search, $this->encoder, $this->selectedMachineNo);
    }

    public function confirm_ppf(int $ppf){
     $this->startLoading('Loading PPF...', 'Please wait while we load the record');
    
     $this->dispatch('lookup_ppf', $ppf);
    }

    #[On('stopLoading')]
    public function stopLoading(){
        $this->stopLoading();
    }
};
?>

<div class="w-full flex flex-col gap-4 mt-3">
    <x-ui.round-notification />
    @unless($isHideTable)
    <div class="w-full flex justify-end">
        <input type="text" wire:model.live.debounce.400ms="search" ...
            placeholder="Search..."
            class="px-4 py-2 border rounded-md focus:outline-none focus:ring focus:border-blue-300">
    </div>

    <div class="w-full overflow-x-auto">
        <table class="table-auto w-full text-sm text-white bg-gray-800 rounded-lg overflow-hidden">
            <thead>
                <tr class="bg-gray-900 text-white text-center">
                    <th class="px-4 py-2">PPFNo</th>
                    <th class="px-4 py-2">PartNo</th>
                    <th class="px-4 py-2">Molding Die</th>
                    <th class="px-4 py-2">Date Judge</th>
                    <th class="px-4 py-2">Action</th>
                </tr>
            </thead>
            <tbody class = "bg-gray-700">
                @forelse($this->data as $d)
                <tr>
                    <td class="px-4 py-2 text-center">{{ $d->PPFNo }}</td>
                    <td class="px-4 py-2 text-center">{{ $d->PartNo }}</td>
                    <td class="px-4 py-2 text-center">{{ $d->MDNo }}</td>
                    <td class="px-4 py-2 text-center">{{ $d->DateJudge }}</td>
                    <td class="px-4 py-2 flex justify-center gap-2">
                        @if($action === 'edit')
                        <button class="text-white bg-blue-600 px-4 py-2 rounded"
                            wire:loading.attr="disabled"
                            wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo}})"
                            @click="window.dispatchEvent(new CustomEvent('show-loading', { detail: { message: 'Loading PPF...' } }))">
                            Edit
                        </button>

                        @elseif($action === 'view')
                        <button class="text-white bg-gray-500 px-4 py-2 rounded"
                            wire:loading.attr="disabled"
                            wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo}})"
                            @click="window.dispatchEvent(new CustomEvent('show-loading', { detail: { message: 'Loading PPF...' } }))">
                            View
                        </button>

                        @elseif($action === 'delete')
                        <button class="text-white bg-red-600 px-4 py-2 rounded"
                            wire:loading.attr="disabled"
                            wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo}})"
                            @click="window.dispatchEvent(new CustomEvent('show-loading', { detail: { message: 'Deleting PPF...' } }))">
                            Delete
                        </button>
                        @endif
                    </td>
                </tr>
             
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center">No record added yet.</td>
                </tr>
                   @endforelse

            </tbody>
        </table>
    </div>
    <div class="w-full">
        {{ $this->data->links() }}
    </div>
    @endunless
</div>