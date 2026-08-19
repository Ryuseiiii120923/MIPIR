<?php

use App\Inspection\Actions\CreateMachine;
use App\Inspection\Models\Machine;
use App\Traits\HasNotifications;
use Livewire\Component;

new class extends Component
{
    use HasNotifications;
    public bool $showModal = false;
    public string $machineNumber = '';
    public array $machines = [];
    public ?string $selectedMachine = null;

    public function mount(): void
    {
        $this->machines = Machine::orderBy('created_at')->get()->toArray();
    }

    public function openAddMachineModal(): void
    {
        $this->machineNumber = '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $this->validate([
            'machineNumber' => 'required|string|max:50|unique:mipirDB.machine_inprocess,machine_number',
        ], [
            'machineNumber.unique' => 'This machine number already exists.',
        ]);

        app(CreateMachine::class)->execute($this->machineNumber);

        $this->machines = Machine::orderBy('created_at')->get()->toArray();

        $this->showModal = false;
        $this->machineNumber = '';
    }

    public function selectMachine(string $machineNumber): void
    {
        $this->selectedMachine = $machineNumber;
    }
};
?>

<div class="w-full mx-auto bg-white rounded-2xl shadow-sm border border-emerald-100 p-6 sm:p-8">
    <div class="w-full mx-auto mb-4 pb-4 border-b border-gray-100">
        <p class="text-xs uppercase tracking-wide text-gray-400 font-medium">Currently Encoding</p>
        <h1 class="text-2xl font-bold text-gray-800">Machine {{ $selectedMachine }}</h1>
    </div>
    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
        <button
            wire:click="openAddMachineModal"
            type="button"
            class="text-sm text-white bg-green-500 hover:bg-green-700 font-medium px-4 py-2 rounded-lg">
            Add Machine
        </button>
    </div>

    {{-- Cards --}}
    <div class="flex flex-row flex-wrap gap-3 mt-4">
        @foreach($machines as $machine)
        <button
            wire:click="selectMachine('{{ $machine['machine_number'] }}')"
            wire:key="machine-{{ $machine['machine_number'] }}"
            type="button"
            @class([ 'px-5 py-3 rounded-xl border-2 text-sm font-medium transition-all' , 'border-blue-600 bg-blue-50 text-blue-700'=> $selectedMachine === $machine['machine_number'],
            'border-gray-200 text-gray-600 hover:bg-gray-50' => $selectedMachine !== $machine['machine_number'],
            ])>
            {{ $machine['machine_number'] }}
        </button>
        @endforeach

        @if(empty($machines))
        <p class="text-sm text-gray-400">No machines encoded yet.</p>
        @endif
    </div>

    {{-- Active tab content --}}
    @if($selectedMachine)
    <div class="mt-6 pt-4 border-t border-gray-100">
        <livewire:inspection::encodingPage
            :key="'encoding-' . $selectedMachine"
            :selectedMachine="$selectedMachine" />
    </div>
    @endif

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-sm shadow-lg">
            <h3 class="text-lg font-semibold mb-4">Encode Machine Number</h3>

            <input
                type="text"
                wire:model="machineNumber"
                placeholder="Machine Number"
                class="w-full border rounded-lg px-3 py-2 text-sm" />
            @error('machineNumber')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror

            <div class="flex justify-end gap-2 mt-5">
                <button wire:click="closeModal" type="button" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
                <button wire:click="save" type="button" class="px-4 py-2 text-sm text-white bg-green-600 hover:bg-green-700 rounded-lg">Save</button>
            </div>
        </div>
    </div>
    @endif
</div>