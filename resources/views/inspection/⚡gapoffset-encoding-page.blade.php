<?php

use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use App\Traits\HasNotifications;
use App\Traits\WithLoading;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    use WithLoading;
    use HasNotifications;
    public $isHideTable = true;
    public string $search = '';
    public int $encoder = 0;

    public string $action = '';
    public int $selectedPpf = 0;

    public function setAction(string $action): void
    {
        $this->action = $action;
        $this->selectedPpf = 0;
        if ($this->action != 'add') {
            $this->isHideTable = false;
        } else {
            $this->isHideTable = false;
        }
        $this->dispatch('action-changed-gapoffset', action: $action);
        // $this->dispatch('read-only', false);
    }

    public function submit(): void
    {
        if ($this->action === 'add') {
            $this->dispatch('submit-gapoffset');
        } else {
            $this->dispatch('delete-gapoffset');
        }
    }

    public function mount()
    {
        $this->encoder = Auth::user()->EmployeeID;
    }

    #[Computed]
    public function data()
    {
        if (empty($this->action)) {
            return new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 5);
        }

        return app(PpfLookUpRepository::class)->getDataforSearchGapOffset(
            $this->search,
            $this->encoder,
            excludeGenerated: $this->action === 'add'
        );
    }

    public function confirm_ppf(int $ppf)
    {
        $this->startLoading('Loading PPF...', 'Please wait while we load the record');
        $this->selectedPpf = $ppf;

        $this->dispatch('lookup_ppf', $ppf);
    }

    #[On('stopLoading')]
    public function stopLoading()
    {
        $this->stopLoading();
    }

    #[On('ppf-checked')]
    public function onPpfChecked(int $ppf): void
    {
        $this->selectedPpf = $ppf;
    }
};
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="flex justify-center gap-3 p-6">
        @foreach([
        'add' => ['ti-plus', 'Add/Update', 'blue'],
        'delete' => ['ti-trash', 'Delete', 'red'],
        ] as $key => [$icon, $label, $color])
        <button
            wire:click="setAction('{{ $key }}')"
            @class([ 'flex flex-col items-center gap-1.5 py-3 flex-1 rounded-xl border-2 text-sm font-medium transition-all' , 'border-gray-200 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400'=> $action !== $key,
            'border-blue-600 bg-blue-50 text-blue-700' => $action === $key && $key === 'add',
            'border-red-700 bg-red-50 text-red-700' => $action === $key && $key === 'delete',
            ])>
            <i class="ti {{ $icon }} text-xl"></i>
            <span>{{ $label }}</span>
        </button>
        @endforeach
    </div>
    <div class="w-full justify-center">
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
                            <th class="px-4 spy-2">PartNo</th>
                            <th class="px-4 py-2">Molding Die</th>
                            <th class="px-4 py-2">Date Measure</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-700">
                        @forelse($this->data as $d)
                        <tr wire:key="gapoffset-row-{{ $d->PPFNo }}">
                            <td class="px-4 py-2 text-center">{{ $d->PPFNo }}</td>
                            <td class="px-4 py-2 text-center">{{ $d->PartNo }}</td>
                            <td class="px-4 py-2 text-center">{{ $d->MDNo }}</td>
                            <td class="px-4 py-2 text-center">{{ $d->DateJudge }}</td>
                            <td class="px-4 py-2 flex justify-center gap-2">
                                <button
                                    class="text-white px-4 py-2 rounded {{ $action === 'delete' ? 'bg-red-600' : 'bg-blue-600' }}"
                                    wire:loading.attr="disabled"
                                    wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo }})">
                                    {{ $action === 'delete' ? 'Delete' : 'Add' }}
                                </button>
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
        <livewire:inspection::partials.ppflookup wire:key="ppf-lookup-panel" />
        <livewire:inspection::partials.gap-offset-encoding wire:key="gap-offset-encoding-panel" />
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
            'delete' => 'Confirm Delete',
            default  => 'Submit'
        } }}
            </button>
        </div>
        @endif
    </div>
</div>