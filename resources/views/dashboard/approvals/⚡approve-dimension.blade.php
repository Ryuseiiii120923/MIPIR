<?php

use App\Dashboard\Action\DimensionApprovalAction;
use App\Inspection\Models\Dimensions\TempDimension;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $pending = [];
    public ?int $activeId = null;

    public string $search = '';
    public $symbol = '';
    public $samplingQty = '';
    public $dimensionName = '';
    public $device = '';
    public $specification = '';
    public $photo = null;

    protected function rules(): array
    {
        return [
            'symbol'         => 'required|numeric',
            'samplingQty'    => 'required|integer|min:1',
            'dimensionName'  => 'required|string|max:255',
            'device'         => 'required|string|max:255',
            'specification'  => 'required|string|max:255',
            'photo'          => 'nullable|image|max:5120',
        ];
    }

    public function mount(): void
    {
        $this->pending = TempDimension::orderBy('created_at')->get();
    }

    #[Computed]
    public function filteredPending()
    {
        if ($this->search === '') {
            return $this->pending;
        }

        $needle = mb_strtolower($this->search);

        return collect($this->pending)->filter(function ($temp) use ($needle) {
            return str_contains(mb_strtolower($temp->PartNo), $needle)
                || str_contains(mb_strtolower($temp->DimensionName), $needle)
                || str_contains(mb_strtolower($temp->Device), $needle);
        });
    }

    public function review(int $id): void
    {
        $this->activeId = $id;
        $this->reset(['symbol', 'samplingQty', 'photo']);

        $temp = collect($this->pending)->firstWhere('id', $id);

        $this->dimensionName = $temp->DimensionName ?? '';
        $this->device = $temp->Device ?? '';
        $this->specification = $temp->Specification ?? '';
    }

    public function removePhoto(): void
    {
        $this->photo = null;
    }

    public function approve(DimensionApprovalAction $action): void
    {
        $this->validate();

        $temp = TempDimension::find($this->activeId);

        if (! $temp) {
            $this->cancel();
            $this->pending = TempDimension::orderBy('created_at')->get();
            return;
        }

        $action->execute(
            $temp,
            [
                'symbol'         => $this->symbol,
                'sampling_qty'   => $this->samplingQty,
                'dimension_name' => $this->dimensionName,
                'device'         => $this->device,
                'specification'  => $this->specification,
            ],
            $this->photo,
            Auth::user()->社員CD
        );

        $this->cancel();
        $this->pending = TempDimension::orderBy('created_at')->get();
        $this->dispatch('dimension-approved');
    }

    public function cancel(): void
    {
        $this->reset(['activeId', 'symbol', 'samplingQty', 'dimensionName', 'device', 'specification', 'photo']);
    }
}; ?>

<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-800">Dimension Updates Awaiting Approval</h2>
                <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                    {{ count($this->filteredPending) }}
                </span>
            </div>

            <div class="relative w-full sm:w-64">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search part no, dimension, device..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                        <th class="p-3 font-medium">Part No</th>
                        <th class="p-3 font-medium">Dimension</th>
                        <th class="p-3 font-medium">Device</th>
                        <th class="p-3 font-medium">Spec</th>
                        <th class="p-3 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->filteredPending as $temp)
                        <tr wire:key="temp-{{ $temp->id }}" class="hover:bg-emerald-50/60 transition-colors duration-150">
                            <td class="p-3 font-medium text-gray-800">{{ $temp->PartNo }}</td>
                            <td class="p-3 text-gray-600">{{ $temp->DimensionName }}</td>
                            <td class="p-3 text-gray-600">{{ $temp->Device }}</td>
                            <td class="p-3 text-gray-600">
                                {{ $temp->Specification }}
                                <span class="text-gray-400">({{ $temp->LowerLimit }} ~ {{ $temp->UpperLimit }})</span>
                            </td>
                            <td class="p-3 text-right">
                                <button
                                    type="button"
                                    wire:click="review({{ $temp->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors duration-150">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Review
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm">
                                        {{ $search ? 'No dimensions match your search.' : 'No pending dimensions to approve.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($activeId)
        <div
            x-data
            x-init="document.body.style.overflow = 'hidden'"
            x-on:keydown.escape.window="$wire.cancel()"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="opacity-0">

            <div
                class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                wire:click="cancel"></div>

            <div
                class="relative bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden max-h-[90vh] overflow-y-auto"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Encode &amp; Approve</h3>
                    <button type="button" wire:click="cancel" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Dimension Name</label>
                        <input
                            type="text"
                            wire:model="dimensionName"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('dimensionName') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Device</label>
                        <input
                            type="text"
                            wire:model="device"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('device') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Specification</label>
                        <input
                            type="text"
                            wire:model="specification"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('specification') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Symbol</label>
                        <input
                            type="text"
                            wire:model="symbol"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('symbol') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sampling QTY</label>
                        <input
                            type="number"
                            wire:model="samplingQty"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('samplingQty') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div x-data="{ dragging: false }">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Photo</label>

                        @if (! $photo)
                            <label
                                x-on:dragover.prevent="dragging = true"
                                x-on:dragleave.prevent="dragging = false"
                                x-on:drop.prevent="
                                    dragging = false;
                                    if ($event.dataTransfer.files.length > 0) {
                                        $wire.upload('photo', $event.dataTransfer.files[0]);
                                    }
                                "
                                :class="dragging ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300 bg-gray-50'"
                                class="flex flex-col items-center justify-center gap-1.5 border-2 border-dashed rounded-lg py-6 cursor-pointer transition-colors duration-150">
                                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9m0 0l-3 3m3-3l3 3M6 20h12a2 2 0 002-2V8a2 2 0 00-2-2h-3.5l-1-1.5h-3L9 6H5.5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <span class="text-xs text-gray-500">
                                    <span class="text-emerald-600 font-medium">Click to upload</span> or drag and drop
                                </span>
                                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                            </label>
                        @else
                            <div class="relative rounded-lg overflow-hidden border border-gray-200">
                                <img src="{{ $photo->temporaryUrl() }}" class="w-full h-40 object-cover">
                                <button
                                    type="button"
                                    wire:click="removePhoto"
                                    class="absolute top-2 right-2 bg-black/60 hover:bg-black/80 text-white rounded-full p-1.5 transition-colors duration-150">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        @endif

                        <div wire:loading wire:target="photo" class="flex items-center gap-1.5 text-xs text-gray-500 mt-1.5">
                            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Uploading...
                        </div>
                        @error('photo') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-5 py-4 bg-gray-50 border-t border-gray-100">
                    <button
                        type="button"
                        wire:click="cancel"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-150">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="approve"
                        wire:loading.attr="disabled"
                        wire:target="approve"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors duration-150">
                        <svg wire:loading wire:target="approve" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Approve
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>