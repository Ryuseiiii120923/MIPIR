<?php

use App\Dashboard\Repositories\InspectorRepository;
use App\Dashboard\Services\InspectorService;
use App\Domain\Worker\InspectorID;
use App\Domain\Worker\WorkerName;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
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
    public string $inspectorId = '';
    public bool $inspectorReadOnly = false;
    public bool $editInspector = false;
    public string $editInspectorId = '';
    public string $editName = '';
    public string $editPlant = '';
    public string $editEmployeeID = '';

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
            $encoder = Auth::user()->社員CD;

            $inspectorIdTaken = InspectorID::where('作業員CD', $this->inspectorId)->where('区分', 3)->value('作業員CD');

            if ($inspectorIdTaken === $this->inspectorId) {
                $this->notifyFail('Taken', 'This Inspector ID ' . $this->inspectorId . ' is already taken. Please choose another one.');
                return;
            }

            $service->registerInspector($this->employeeID, $this->name, $this->plant, $encoder, strtoupper($this->inspectorId));

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

    public function updatedInspectorId(): void
    {
        try {
            $this->validateOnly('inspectorId');
        } catch (ValidationException $e) {
            $this->notifyFail('Validation Error', implode(', ', $e->validator->errors()->all()));
        }
    }

    protected function rules(): array
    {
        return [
            'employeeID' => ['required', 'digits_between:3,5'],
            'inspectorId' => ['required', 'alpha_num', 'between:2,5'],
            'plant'      => ['required', Rule::in(['P1A', 'P1B', 'P2'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'employeeID.required' => 'Employee ID is required.',
            'employeeID.digits'   => 'Employee ID must be exactly 5 digits or 4 digits.',
            'inspectorId.required' => 'Inspector ID is required.',
            'inspectorId.digits'   => 'Inspector ID must be exactly 5 digits or 4 digits.',
            'plant.required'      => 'Please select a plant.',
            'plant.in'            => 'Selected plant is invalid.',
        ];
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'employeeID', 'plant', 'inspectorId']);
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
            ->reject(fn($row) => (string) $row->employee_id === $this->pendingId)
            ->values();
    }

    #[Computed]
    public function pendingInspector()
    {
        return $this->pendingId === null
            ? null
            : $this->allInspectors->first(fn($row) => (string) $row->employee_id === $this->pendingId);
    }

    public function remove(string $employeeId, InspectorService $service): void
    {
        if ($this->pendingId !== null) {
            $this->commitPending($this->pendingId, $service);
        }

        $this->pendingId = $employeeId;
    }

    public function commitPending(string $employeeId, InspectorService $service): void
    {
        if ($this->pendingId !== $employeeId) {
            return;
        }

        $inspector = $this->pendingInspector;
        $this->pendingId = null;

        if (! $inspector) {
            $this->notifyFail('Not Found', 'Inspector no longer exists.');
            return;
        }

        try {
            $service->removeInspector(
                (string) $inspector->inspector_id,
                (string) $inspector->employee_id
            );

            unset($this->allInspectors, $this->inspectors, $this->pendingInspector);

            $this->notifySuccess('Removed', 'Inspector permanently removed');
        } catch (\Throwable $e) {
            Log::error('Inspector removal failed', [
                'employee_id'  => $employeeId,
                'inspector_id' => $inspector->inspector_id,
                'error'        => $e->getMessage(),
            ]);

            $this->notifyFail('Failed', 'Failed to remove inspector. Please try again.');
        }
    }

    public function undo(): void
    {
        $this->pendingId = null;
    }

    public function checkName()
    {
        $this->name = WorkerName::where('社員CD', $this->employeeID)->value('名前') ?? '';
        $this->inspectorId = InspectorID::where('社員CD', $this->employeeID)->where('区分', 3)->value('作業員CD') ?? '';

        if (!empty($this->inspectorId)) {
            $this->inspectorReadOnly = true;
        }
    }

    public function checkIfTaken(string $inspectorId)
    {
        $taken = InspectorID::where('作業員CD', strtoupper($inspectorId))->where('区分', 3)->exists();
        if ($taken) {
            $this->notifyFail('Taken', 'This Inspector ID.' . strtoupper($inspectorId) . ' is already taken. Please choose another one.');
            return;
        }
    }

    public function edit(string $employeeId): void
    {
        $inspector = $this->allInspectors
            ->first(fn($row) => (string) $row->employee_id === $employeeId);

        if (! $inspector) {
            $this->notifyFail('Not Found', 'Inspector no longer exists.');
            return;
        }

        $this->resetValidation();
        $this->editEmployeeID  = (string) $inspector->employee_id;
        $this->editInspectorId = (string) $inspector->inspector_id;
        $this->editName        = (string) $inspector->name;
        $this->editPlant       = (string) $inspector->plant;
        $this->editInspector   = true;
    }

    public function updateInspector(InspectorService $service): void
    {
        try {
            $this->validate([
                'editInspectorId' => [
                    'required',
                    'alpha_num',
                    'between:2,5',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        $taken = InspectorID::where('作業員CD', $value)
                            ->where('区分', 3)
                            ->where('社員CD', '!=', $this->editEmployeeID)
                            ->exists();

                        if ($taken) {
                            $fail("Inspector ID {$value} is already taken. Please choose another one.");
                        }
                    },
                ],
                'editPlant' => ['required', Rule::in(['P1A', 'P1B', 'P2'])],
            ], [
                'editInspectorId.required' => 'Inspector ID is required.',
                'editInspectorId.between'  => 'Inspector ID must be 2 to 5 characters.',
                'editPlant.required'       => 'Please select a plant.',
                'editPlant.in'             => 'Selected plant is invalid.',
            ]);
        } catch (ValidationException $e) {
            $this->resetValidation();
            $this->notifyFail('Validation Error', implode(', ', $e->validator->errors()->all()));
            return;
        }

        try {
            $encoder = Auth::user()->社員CD;

            $service->updateInspector(
                strtoupper($this->editInspectorId),
                $this->editPlant,
                $encoder,
                $this->editEmployeeID,
            );

            unset($this->allInspectors, $this->inspectors);
            $this->closeEdit();

            $this->notifySuccess('Updated', 'Inspector successfully updated');
        } catch (\Throwable $e) {
            Log::error('Inspector update failed', [
                'employee_id'  => $this->editEmployeeID,
                'inspector_id' => $this->editInspectorId,
                'error'        => $e->getMessage(),
            ]);

            $this->notifyFail('Failed', 'Failed to update inspector. Please try again.');
        }
    }

    public function closeEdit(): void
    {
        $this->reset(['editInspector', 'editEmployeeID', 'editInspectorId', 'editName', 'editPlant']);
        $this->resetValidation();
    }
}
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


                {{-- Inspector ID --}}
                <div>
                    <label for="employeeId" class="block text-sm font-medium text-gray-700 mb-1">Employee ID (4 numbers)</label>
                    <input type="text"
                        id="employeeId"
                        wire:model="employeeID"
                        placeholder="xxxx"
                        wire:blur="checkName()"
                        class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('employeeID')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="inspector_id" class="block text-sm font-medium text-gray-700 mb-1">Inspector ID</label>
                    <input type="text"
                        @if($inspectorReadOnly) readonly @endif
                        id="inspector_id"
                        wire:model.live.debounce.8000ms="inspectorId"
                        placeholder="xxxx"
                        class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('inspectorId')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text"
                        readonly
                        id="name"
                        wire:model="name"
                        class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name')
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
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Inspector ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plant</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($this->inspectors as $inspector)
                    <tr wire:key="inspector-{{ $inspector->employee_id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $inspector->employee_id }}</td>
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $inspector->inspector_id }}</td>
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $inspector->name }}</td>
                        <td class="px-6 py-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                {{ $inspector->plant }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button type="button"
                                    wire:click="edit('{{ $inspector->employee_id }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="edit"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-600 bg-white border border-blue-200 rounded-lg hover:bg-blue-50 disabled:opacity-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>

                                <button type="button"
                                    wire:click="remove('{{ $inspector->employee_id }}')"
                                    wire:confirm="Remove {{ $inspector->name }} ({{ $inspector->inspector_id }})?"
                                    wire:loading.attr="disabled"
                                    wire:target="remove"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 disabled:opacity-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                    </svg>
                                    Remove
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                            No inspectors registered yet.
                        </td>

                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($editInspector)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
        wire:key="edit-inspector-modal"
        x-data
        x-on:keydown.escape.window="$wire.closeEdit()">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-gray-900/50" wire:click="closeEdit"></div>

        {{-- Dialog --}}
        <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden"
            role="dialog" aria-modal="true" aria-labelledby="edit-inspector-title">

            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h3 id="edit-inspector-title" class="text-lg font-semibold text-gray-800">Edit Inspector</h3>
                    <p class="text-sm text-gray-500">Update the designated plant.</p>
                </div>
                <button type="button" wire:click="closeEdit"
                    class="text-gray-400 hover:text-gray-600" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="updateInspector" class="p-6 space-y-4">
                <div>
                    <label for="edit_employee_id" class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                    <input type="text" id="edit_employee_id" readonly
                        wire:model="editEmployeeID"
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-600">
                </div>

                <div>
                    <label for="edit_inspector_id" class="block text-sm font-medium text-gray-700 mb-1">Inspector ID</label>
                    <input type="text" id="edit_inspector_id"
                        wire:model="editInspectorId"
                        class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('editInspectorId')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" id="edit_name" readonly
                        wire:model="editName"
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-600">
                </div>

                <div>
                    <label for="edit_plant" class="block text-sm font-medium text-gray-700 mb-1">Designated Plant</label>
                    <select id="edit_plant"
                        wire:model="editPlant"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select plant</option>
                        @foreach (['P1A', 'P1B', 'P2'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('editPlant')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeEdit"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="updateInspector"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="updateInspector">Save Changes</span>
                        <span wire:loading wire:target="updateInspector">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>