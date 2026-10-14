<?php

use App\Dashboard\Repositories\GroupLeaderRepository;
use App\Dashboard\Services\GroupLeaderService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasNotifications;

    public string $employeeID = '';
    public string $name = '';
    public string $plant = '';
    public string $machineNo = '';

    protected function rules(): array
    {
        return [
            'employeeID' => ['required', 'digits_between:3,5'],
            'plant'      => ['required', Rule::in(['P1A', 'P1B', 'P2'])],
            'machineNo'  => ['nullable', 'string', 'max:20'],
        ];
    }

    #[Computed]
    public function groupLeaders()
    {
        return app(GroupLeaderRepository::class)->all();
    }

    public function checkName(GroupLeaderRepository $repository): void
    {
        $this->name = $repository->nameOf($this->employeeID);
    }

    public function save(GroupLeaderService $service): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->resetValidation();
            $this->notifyFail('Validation Error', implode(', ', $e->validator->errors()->all()));
            return;
        }

        try {
            $service->register($this->employeeID, $this->plant, $this->machineNo);

            $this->reset(['employeeID', 'name', 'plant', 'machineNo']);
            unset($this->groupLeaders);

            $this->notifySuccess('Saved', 'GL can now log in and confirm lots');
        } catch (\DomainException $e) {
            $this->notifyFail('Not allowed', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('GL registration failed', [
                'employee_id' => $this->employeeID,
                'error'       => $e->getMessage(),
            ]);

            $this->notifyFail('Failed', 'Failed to register GL. Please try again.');
        }
    }

    public function remove(int $id, GroupLeaderService $service): void
    {
        try {
            $service->remove($id);
            unset($this->groupLeaders);

            $this->notifySuccess('Removed', 'GL access removed');
        } catch (\Throwable $e) {
            Log::error('GL removal failed', ['id' => $id, 'error' => $e->getMessage()]);

            $this->notifyFail('Failed', 'Failed to remove GL. Please try again.');
        }
    }
};
?>

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Authorized GL</h2>
            <p class="text-sm text-gray-500">Allow a GL to log in and confirm lots for a plant or machine.</p>
        </div>

        <form wire:submit="save" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="gl_employee_id" class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                    <input type="text" id="gl_employee_id" wire:model="employeeID" wire:blur="checkName" placeholder="xxxx"
                        class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="gl_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" id="gl_name" readonly wire:model="name"
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-600">
                </div>

                <div>
                    <label for="gl_plant" class="block text-sm font-medium text-gray-700 mb-1">Plant</label>
                    <select id="gl_plant" wire:model="plant"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select plant</option>
                        @foreach (['P1A', 'P1B', 'P2'] as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="gl_machine" class="block text-sm font-medium text-gray-700 mb-1">Machine (optional)</label>
                    <input type="text" id="gl_machine" wire:model="machineNo" placeholder="All machines"
                        class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">Authorize</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-800">Authorized GLs</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plant</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Machine</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($this->groupLeaders as $gl)
                    <tr wire:key="gl-{{ $gl->Id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $gl->EmployeeID }}</td>
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $gl->Name }}</td>
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $gl->Plant }}</td>
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $gl->MachineNo ?? 'All machines' }}</td>
                        <td class="px-6 py-3 text-right">
                            <button type="button"
                                wire:click="remove({{ $gl->Id }})"
                                wire:confirm="Remove access of {{ $gl->Name ?: $gl->EmployeeID }}?"
                                class="px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50">
                                Remove
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No GL authorized yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>