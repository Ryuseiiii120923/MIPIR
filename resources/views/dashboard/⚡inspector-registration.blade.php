<?php

use App\Dashboard\Repositories\InspectorRepository;
use App\Dashboard\Services\InspectorService;
use App\Domain\Worker\InspectorID;
use App\Domain\Worker\WorkerName;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    use HasNotifications;
    public string $name = '';
    public string $employeeID = '';
    public string $plant = '';
    public array $removed = [];
    public ?string $pendingId = null;

    public function save(InspectorService $service): void
    {
        try {
            $data = $this->validate();
        } catch (ValidationException $e) {
            $this->resetValidation(); // toast only, no inline @error
            $this->notifyFail('Validation Error', implode(', ', $e->validator->errors()->all()));
            return;
        }

        try {
            $service->registerInspector($this->employeeID, $this->name, $this->plant);

            $this->resetForm();
            unset($this->allInspectors, $this->inspectors);

            $this->notifySuccess('Saved', 'Inspector successfully saved');
        } catch (\Throwable $e) {
            Log::error('Inspector registration failed', [
                'inspector_id' => $this->employeeID,
                'error'        => $e->getMessage(),
            ]);

            $this->notifyFail('Failed', 'Failed to register inspector. Please try again.');
        }
    }

    protected function rules(): array
    {
        return [
            'employeeID' => ['required', 'digits_between:3,5'],
            'plant'      => ['required', Rule::in(['P1A', 'P1B', 'P2'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'employeeID.required' => 'Employee ID is required.',
            'employeeID.digits'   => 'Employee ID must be exactly 5 digits or 4 digits.',
            'plant.required'      => 'Please select a plant.',
            'plant.in'            => 'Selected plant is invalid.',
        ];
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'employeeID', 'plant']);
        $this->resetValidation();
    }

    #[Computed]
    public function allInspectors()
    {
        return app(InspectorRepository::class)->fetchInspectors();
    }

    #[Computed]
    public function inspectors()
    {
        return $this->allInspectors
            ->reject(fn($row) => (string) $row->inspector_id === $this->pendingId)
            ->values();
    }

    #[Computed]
    public function pendingInspector()
    {
        return $this->pendingId === null
            ? null
            : $this->allInspectors->first(fn($row) => (string) $row->inspector_id === $this->pendingId);
    }

    public function remove(string $inspectorId, InspectorService $service): void
    {
        // If another removal is still waiting, finalize it first (one pending at a time)
        if ($this->pendingId !== null) {
            $this->commitPending($this->pendingId, $service);
        }

        $this->pendingId = $inspectorId;
    }

    public function undo(): void
    {
        $this->pendingId = null;
    }

    public function commitPending(string $inspectorId, InspectorService $service): void
    {
        // Ignore stale timers: only commit if it's still the pending one
        if ($this->pendingId !== $inspectorId) {
            return;
        }

        $this->pendingId = null;

        try {
            $service->removeInspector($inspectorId);
            unset($this->allInspectors, $this->inspectors);

            $this->notifySuccess('Removed', 'Inspector permanently removed');
        } catch (\Throwable $e) {
            Log::error('Inspector removal failed', [
                'inspector_id' => $inspectorId,
                'error'        => $e->getMessage(),
            ]);

            $this->notifyFail('Failed', 'Failed to remove inspector. Please try again.');
        }
    }

    public function checkName()
    {
        $inspectorExist = InspectorID::where('社員CD', $this->employeeID)->where('区分', 3)->exists();
        if (!$inspectorExist) {
            $this->notifyFail('Not Exist', 'This QC Inspector is not exist');
            $this->employeeID = '';
            $this->name = '';
            return;
        }
        $this->name = WorkerName::where('社員CD', $this->employeeID)->value('名前') ?? '';
    }
};
?>

<div class="space-y-6">

    {{-- Registration Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Inspector Registration</h2>
            <p class="text-sm text-gray-500">Register a new inspector and assign a plant.</p>
        </div>

        <form wire:submit="save" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text"
                        id="name"
                        wire:model="name"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Inspector ID --}}
                <div>
                    <label for="inspector_id" class="block text-sm font-medium text-gray-700 mb-1">Employee ID (4 numbers)</label>
                    <input type="text"
                        id="employee_id"
                        wire:model="employeeID"
                        placeholder="xxxx"
                        wire:blur="checkName()"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('employeeID')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Designated Plant --}}
                <div>
                    <label for="plant" class="block text-sm font-medium text-gray-700 mb-1">Designated Plant</label>
                    <select id="plant"
                        wire:model="plant"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select plant</option>
                        @foreach (['P1A', 'P1B', 'P2'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('plant')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button"
                    wire:click="resetForm"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Clear
                </button>
                <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">Register</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>

    @if ($this->pendingInspector)
    <div wire:key="undo-{{ $pendingId }}"
        x-data="{
        duration: 8,
        remaining: 8,
        interval: null,
        init() {
            this.interval = setInterval(() => {
                this.remaining--;
                if (this.remaining <= 0) {
                    this.commit();
                }
            }, 1000);
        },
        stop() {
            clearInterval(this.interval);
        },
        commit() {
            this.stop();
            $wire.commitPending('{{ $pendingId }}');
        }
    }"
        class="overflow-hidden bg-amber-50 border border-amber-200 rounded-lg">

        <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm text-gray-700">
            <span>
                Removing <span class="font-medium">{{ $this->pendingInspector->name }}</span>
                ({{ $this->pendingInspector->inspector_id }}) in
                <span class="font-semibold tabular-nums" x-text="remaining + 's'"></span>
            </span>

            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                    x-on:click="stop(); $wire.undo()"
                    wire:loading.attr="disabled"
                    wire:target="undo,commitPending"
                    class="px-3 py-1 text-xs font-medium text-amber-800 bg-white border border-amber-300 rounded-lg hover:bg-amber-100 disabled:opacity-50">
                    Undo
                </button>

                <button type="button"
                    x-on:click="commit()"
                    wire:loading.attr="disabled"
                    wire:target="undo,commitPending"
                    class="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
                    Remove now
                </button>
            </div>
        </div>

        {{-- Progress bar --}}
        <div class="h-1 bg-amber-100">
            <div class="h-full bg-amber-500 transition-all duration-1000 ease-linear"
                :style="`width: ${(remaining / duration) * 100}%`"></div>
        </div>
    </div>
    @endif

    {{-- Registered Inspectors Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-800">Registered Inspectors</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Inspector ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plant</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($this->inspectors as $inspector)
                    <tr wire:key="inspector-{{ $inspector->inspector_id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $inspector->inspector_id }}</td>
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $inspector->name }}</td>
                        <td class="px-6 py-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                {{ $inspector->plant }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <button type="button"
                                wire:click="remove('{{ $inspector->inspector_id }}')"
                                wire:confirm="Remove {{ $inspector->name }} ({{ $inspector->inspector_id }})?"
                                wire:loading.attr="disabled"
                                wire:target="remove('{{ $inspector->inspector_id }}')"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 disabled:opacity-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                </svg>
                                Remove
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">
                            No inspectors registered yet.
                        </td>

                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>