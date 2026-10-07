<?php

use App\Dashboard\Services\DimensionEncodingService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;

new class extends Component
{
    use HasNotifications, WithPagination;

    public string $search = '';

    public bool $showModal = false;
    public ?int $editingId = null; // RecNo

    public string $partNo = '';
    public string $dimensionNo = '';
    public string $symbol = '';
    public string $dimensionName = '';
    public string $specification = '';
    public string $upperLimit = '';
    public string $lowerLimit = '';
    public string $device = '';
    public string $unit = '';
    public string $judgementClsIP = '';
    public string $judgementClsMP = '';
    public string $samplingQtyIP = '';
    public string $samplingQtyMP = '';
    public bool $xBar = false;
    public bool $showLimitsModal = false;
    public string $limitsPartNo = '';
    public string $limitsDimItem = '';
    public ?float $limitsUpper = null;
    public ?float $limitsLower = null;

    public function openLimits(int $recNo, DimensionEncodingService $service): void
    {
        $dim = $service->find($recNo);

        if ($dim === null) {
            unset($this->dimensions);
            $this->notifyFail('Not Found', 'Dimension no longer exists.');
            return;
        }

        if (!$dim['xBar']) {
            $this->notifyFail('Not X-Bar', 'Only X-Bar dimensions have specification and control limits.');
            return;
        }

        $this->limitsPartNo    = $dim['partNo'];
        $this->limitsDimItem   = $dim['dimensionName'];
        $this->limitsUpper     = is_numeric($dim['upperLimit']) ? (float) $dim['upperLimit'] : null;
        $this->limitsLower     = is_numeric($dim['lowerLimit']) ? (float) $dim['lowerLimit'] : null;
        $this->showLimitsModal = true;
    }

    #[On('limits-saved')]
    #[On('limits-closed')]
    public function closeLimits(): void
    {
        $this->showLimitsModal = false;
        $this->reset(['limitsPartNo', 'limitsDimItem', 'limitsUpper', 'limitsLower']);
    }

    #[Computed]
    public function dimensions()
    {
        return app(DimensionEncodingService::class)->list($this->search, 10);
    }

    #[Computed]
    public function symbolPreviewUrl(): ?string
    {
        return app(DimensionEncodingService::class)->symbolUrl($this->symbol);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        $upperRules = ['nullable', 'numeric'];

        // Only compare when both limits are filled in
        if (trim($this->upperLimit) !== '' && trim($this->lowerLimit) !== '') {
            $upperRules[] = 'gte:lowerLimit';
        }

        return [
            'partNo'         => ['required', 'string', 'max:50'],
            'dimensionNo'    => ['required', 'integer', 'min:1'],
            'symbol'         => ['nullable', 'integer'], // negative = no symbol
            'dimensionName'  => ['required', 'string', 'max:100'],
            'specification'  => ['required', 'string', 'max:100'],
            'upperLimit'     => $upperRules,
            'lowerLimit'     => ['nullable', 'numeric'],
            'device'         => ['nullable', 'string', 'max:100'],
            'unit'           => ['nullable', 'string', 'max:20'],
            'judgementClsIP' => ['nullable', 'string', 'max:10'],
            'judgementClsMP' => ['nullable', 'string', 'max:10'],
            'samplingQtyIP'  => ['nullable', 'integer', 'min:0'],
            'samplingQtyMP'  => ['nullable', 'integer', 'min:0'],
            'xBar'           => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'partNo.required'        => 'Part no is required.',
            'dimensionNo.required'   => 'Dimension no is required.',
            'dimensionNo.integer'    => 'Dimension no must be a whole number.',
            'symbol.integer'         => 'Symbol must be a whole number (negative = no symbol).',
            'dimensionName.required' => 'Dimension name is required.',
            'specification.required' => 'Specification is required.',
            'upperLimit.numeric'     => 'Upper limit must be a number.',
            'upperLimit.gte'         => 'Upper limit must be greater than or equal to the lower limit.',
            'lowerLimit.numeric'     => 'Lower limit must be a number.',
            'samplingQtyIP.integer'  => 'Sampling qty (IP) must be a whole number.',
            'samplingQtyMP.integer'  => 'Sampling qty (MP) must be a whole number.',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $recNo, DimensionEncodingService $service): void
    {
        $form = $service->find($recNo);

        if ($form === null) {
            unset($this->dimensions);
            $this->notifyFail('Not Found', 'Dimension no longer exists.');
            return;
        }

        $this->resetForm();
        $this->fill($form);
        $this->editingId = $recNo;
        $this->showModal = true;
    }

    public function save(DimensionEncodingService $service): void
    {
        try {
            $data = $this->validate();
        } catch (ValidationException $e) {
            $this->resetValidation(); // toast only, no inline @error
            $this->notifyFail('Validation Error', implode(', ', $e->validator->errors()->all()));
            return;
        }

        try {
            $isEdit = $this->editingId !== null;

            $service->save($data, $this->editingId, Auth::user()->社員CD);

            $this->closeModal();
            unset($this->dimensions);

            $this->notifySuccess('Saved', $isEdit ? 'Dimension updated' : 'Dimension created');
        } catch (\DomainException $e) {
            $this->notifyFail('Duplicate', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Dimension encoding save failed', [
                'rec_no' => $this->editingId,
                'error'  => $e->getMessage(),
            ]);

            $this->notifyFail('Failed', 'Failed to save dimension. Please try again.');
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'partNo',
            'dimensionNo',
            'symbol',
            'dimensionName',
            'specification',
            'upperLimit',
            'lowerLimit',
            'device',
            'unit',
            'judgementClsIP',
            'judgementClsMP',
            'samplingQtyIP',
            'samplingQtyMP',
            'xBar',
        ]);
        $this->resetValidation();
    }
};
?>

<div class="w-full mx-auto p-6">
    <div>
        <h2 class="text-lg font-semibold">Dimension Encoding</h2>
        <p class="text-sm text-gray-400">Encode and maintain the dimension master per part no.</p>
    </div>

    <div class="shadow-sm bg-white rounded-2xl overflow-hidden mb-6 p-3 mt-3">
        <div class="relative overflow-x-auto bg-white shadow-xs rounded-md border border-gray-200 mt-3">
            <div class="p-4 flex items-center justify-between space-x-4 rounded-lg">
                <label for="dimension-search" class="sr-only">Search</label>
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="m21 21-3.5-3.5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                        </svg>
                    </div>
                    <input type="text"
                        id="dimension-search"
                        wire:model.live.debounce.400ms="search"
                        class="block w-full max-w-96 ps-9 pe-3 py-2 border border-blue-500 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Search dimension or part no">
                </div>
            </div>

            <table class="w-full text-sm text-left text-gray-700">
                <thead class="text-sm bg-gray-50 border-b border-t border-gray-200">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-medium">Part No</th>
                        <th scope="col" class="px-6 py-3 font-medium">Dim No</th>
                        <th scope="col" class="px-6 py-3 font-medium">Symbol</th>
                        <th scope="col" class="px-6 py-3 font-medium">Dimension Name</th>
                        <th scope="col" class="px-6 py-3 font-medium">Specification</th>
                        <th scope="col" class="px-6 py-3 font-medium">Upper</th>
                        <th scope="col" class="px-6 py-3 font-medium">Lower</th>
                        <th scope="col" class="px-6 py-3 font-medium">Device</th>
                        <th scope="col" class="px-6 py-3 font-medium">X-Bar</th>
                        <th scope="col" class="px-6 py-3 font-medium">Update Date</th>
                        <th scope="col" class="px-6 py-3 font-medium">Updated By</th>
                        <th scope="col" class="px-6 py-3 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($this->dimensions as $dim)
                    <tr wire:key="dim-{{ $dim->RecNo }}" class="hover:bg-gray-50">
                        <td class="px-6 py-3">{{ $dim->PartNo }}</td>
                        <td class="px-6 py-3">{{ $dim->DimensionNo }}</td>
                        <td class="px-6 py-3">
                            @if ($dim->symbol_url)
                            <img src="{{ $dim->symbol_url }}"
                                alt="Symbol {{ $dim->Symbol }}"
                                class="h-6 w-auto object-contain">
                            @endif
                        </td>
                        <td class="px-6 py-3 font-medium text-gray-900">{{ $dim->DimensionName }}</td>
                        <td class="px-6 py-3">{{ $dim->Specification ?: '-' }}</td>
                        <td class="px-6 py-3">{{ $dim->UpperLimit ?? '-' }}</td>
                        <td class="px-6 py-3">{{ $dim->LowerLimit ?? '-' }}</td>
                        <td class="px-6 py-3">{{ $dim->Device ?: '-' }}</td>
                        <td class="px-6 py-3">
                            @if ($dim->XBar)
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">X-Bar</span>
                            @else
                            -
                            @endif
                        </td>
                        <td class="px-6 py-3">{{ $dim->DEnc?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td class="px-6 py-3">{{ $dim->Enc ?: '-' }}</td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2">
                                <button type="button"
                                    wire:click="edit({{ $dim->RecNo }})"
                                    wire:loading.attr="disabled"
                                    wire:target="edit({{ $dim->RecNo }})"
                                    class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-white border border-blue-200 rounded-lg hover:bg-blue-50 disabled:opacity-50">
                                    Edit
                                </button>

                                @if ($dim->XBar)
                                <button type="button"
                                    wire:click="openLimits({{ $dim->RecNo }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openLimits({{ $dim->RecNo }})"
                                    class="px-3 py-1.5 text-xs font-medium text-purple-700 bg-white border border-purple-200 rounded-lg hover:bg-purple-50 disabled:opacity-50">
                                    Limits
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="px-6 py-8 text-center text-sm text-gray-500">
                            No dimensions found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3">
                {{ $this->dimensions->links() }}
            </div>
        </div>
    </div>

    {{-- Encoding Modal --}}
    @if ($showModal)
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
        wire:keydown.escape.window="closeModal">
        <div class="bg-white rounded-xl w-full max-w-2xl shadow-lg overflow-hidden max-h-full flex flex-col">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ $editingId ? 'Edit Dimension' : 'New Dimension' }}
                </h3>
                <p class="text-sm text-gray-500">Encode the dimension master details.</p>
            </div>

            <form wire:submit="save" class="p-6 space-y-4 overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="partNo" class="block text-sm font-medium text-gray-700 mb-1">Part No</label>
                        <input type="text" id="partNo" wire:model="partNo"
                            @if($editingId) readonly @endif
                            class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $editingId ? 'bg-gray-100 text-gray-500' : '' }}"
                            placeholder="Part no">
                    </div>

                    <div>
                        <label for="dimensionNo" class="block text-sm font-medium text-gray-700 mb-1">Dimension No</label>
                        <input type="text" id="dimensionNo" wire:model="dimensionNo" inputmode="numeric"
                            @if($editingId) readonly @endif
                            class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $editingId ? 'bg-gray-100 text-gray-500' : '' }}"
                            placeholder="1">
                    </div>

                    {{-- Symbol with live preview --}}
                    <div>
                        <label for="symbol" class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
                        <div class="flex items-center gap-2">
                            <input type="text" id="symbol"
                                wire:model.live.debounce.300ms="symbol"
                                class="w-20 rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0">

                            <div class="flex-1 h-10 flex items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-2">
                                @if ($this->symbolPreviewUrl)
                                <img src="{{ $this->symbolPreviewUrl }}"
                                    alt="Symbol {{ $symbol }}"
                                    class="max-h-8 w-auto object-contain">
                                @else
                                <span class="text-xs text-gray-400">No symbol</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <label for="dimensionName" class="block text-sm font-medium text-gray-700 mb-1">Dimension Name</label>
                        <input type="text" id="dimensionName" wire:model="dimensionName"
                            class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Enter dimension name">
                    </div>

                    <div class="md:col-span-3">
                        <label for="specification" class="block text-sm font-medium text-gray-700 mb-1">Specification</label>
                        <input type="text" id="specification" wire:model="specification"
                            class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. 1.20 ± 0.10">
                    </div>

                    <div>
                        <label for="upperLimit" class="block text-sm font-medium text-gray-700 mb-1">Upper Limit</label>
                        <input type="text" id="upperLimit" wire:model="upperLimit"
                            class="w-full rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="1.30">
                    </div>

                    <div>
                        <label for="lowerLimit" class="block text-sm font-medium text-gray-700 mb-1">Lower Limit</label>
                        <input type="text" id="lowerLimit" wire:model="lowerLimit"
                            class="w-full rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="1.10">
                    </div>

                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" id="unit" wire:model="unit"
                            class="w-full rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="mm">
                    </div>

                    <div class="md:col-span-3">
                        <label for="device" class="block text-sm font-medium text-gray-700 mb-1">Measuring Device</label>
                        <input type="text" id="device" wire:model="device"
                            class="w-full rounded-lg border border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Enter the measuring device used">
                    </div>

                    <div>
                        <label for="judgementClsIP" class="block text-sm font-medium text-gray-700 mb-1">Judgement Cls (IP)</label>
                        <input type="text" id="judgementClsIP" wire:model="judgementClsIP"
                            class="w-full rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="judgementClsMP" class="block text-sm font-medium text-gray-700 mb-1">Judgement Cls (MP)</label>
                        <input type="text" id="judgementClsMP" wire:model="judgementClsMP"
                            class="w-full rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="hidden md:block"></div>

                    <div>
                        <label for="samplingQtyIP" class="block text-sm font-medium text-gray-700 mb-1">Sampling Qty (IP)</label>
                        <input type="text" id="samplingQtyIP" wire:model="samplingQtyIP" inputmode="numeric"
                            class="w-full rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="5">
                    </div>

                    <div>
                        <label for="samplingQtyMP" class="block text-sm font-medium text-gray-700 mb-1">Sampling Qty (MP)</label>
                        <input type="text" id="samplingQtyMP" wire:model="samplingQtyMP" inputmode="numeric"
                            class="w-full rounded-lg border border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="5">
                    </div>

                    <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" wire:model="xBar"
                                class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            For X-Bar table
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Save' }}</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if ($showLimitsModal)
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 overflow-y-auto"
        wire:keydown.escape.window="closeLimits">
        <div class="w-full max-w-3xl my-auto">
            <livewire:inspection::partials.specs-control-limit
                :partNo="$limitsPartNo"
                :dimItem="$limitsDimItem"
                :standalone="true"
                :USLx="$limitsUpper"
                :LSLx="$limitsLower"
                :key="'limits-' . $limitsPartNo . '-' . $limitsDimItem" />
        </div>
    </div>
    @endif
</div>