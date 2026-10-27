<?php

use App\Auth\Services\CurrentActor;
use App\Inspection\Repositories\PPFLookUp\PpfLookUpRepository;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Traits\HasNotifications;
use App\Traits\WithLoading;
use App\Inspection\Repositories\LotConfirmationRepository;
use App\Inspection\Services\Excel\MipirRecordRefresher;
use App\Inspection\Services\Excel\XBarRecordRefresher;
use App\Inspection\Services\XBarCheckTimeCoverage;
use Livewire\Attributes\Locked;

new class extends Component
{
    use WithPagination;
    use WithLoading;
    use HasNotifications;
    public $isHideTable = true;
    public string $search = '';
    public string $action = '';
    public int $encoder = 0;

    public bool $isGl = false;
    public string $confirmSearch = '';
    public ?int $rejectingId = null;
    public string $rejectReason = '';

    public bool $showRemarksModal = false;
    #[Locked]
    public ?int $remarksPpf = null;
    public array $missingCheckTimes = [];
    public string $remarks = '';



    public function mount()
    {
        $worker = Auth::guard('worker')->user();

        $this->encoder = (int) ($worker
            ? $worker->EmployeeID
            : Auth::guard('web')->user()?->{'社員CD'});
    }

    #[On('action-changed')]
    public function onActionChanged($action)
    {
        $this->action = $action;
        $this->isHideTable = in_array($action, ['add', 'confirm'], true);
        $this->reset('confirmSearch');
        $this->resetPage();
        $this->resetPage('confirmationPage');
        $this->search = '';
    }

    public function updatedConfirmSearch(): void
    {
        $this->resetPage('confirmationPage');
    }

    #[Computed]
    public function data()
    {
        if (empty($this->action)) {
            return;
        }

        return app(PpfLookUpRepository::class)->getDataforSearch($this->search, app(CurrentActor::class)->plant());
    }

    public function confirm_ppf(int $ppf)
    {
        $this->startLoading('Loading PPF...', 'Please wait while we load the record');

        $this->dispatch('lookup_ppf', $ppf);
    }

    #[On('stopLoading')]
    public function stopLoading()
    {
        $this->stopLoading();
    }



    #[Computed]
    public function forConfirmation()
    {
        return app(LotConfirmationRepository::class)
            ->getInspectorLots((string) $this->encoder, $this->confirmSearch);
    }

    public function requestConfirmation(int $ppf): void
    {
        $missing = app(XBarCheckTimeCoverage::class)->missing($ppf);

        if ($missing !== []) {
            $this->remarksPpf        = $ppf;
            $this->missingCheckTimes = $missing;
            $this->remarks           = '';
            $this->resetErrorBag();
            $this->showRemarksModal  = true;

            return;
        }

        $this->sendConfirmation($ppf);
    }

    public function submitRemarks(): void
    {
        $this->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ], [
            'remarks.required' => 'Please enter a remark.',
        ]);

        $this->sendConfirmation($this->remarksPpf, trim($this->remarks));
        $this->closeRemarks();
    }

    public function closeRemarks(): void
    {
        $this->reset('showRemarksModal', 'remarksPpf', 'missingCheckTimes', 'remarks');
        $this->resetErrorBag();
    }

    private function sendConfirmation(int $ppf, ?string $remarks = null): void
    {
        $error = app(LotConfirmationRepository::class)
            ->requestForPpf($ppf, (string) $this->encoder, $remarks);

        

        if ($error !== null) {
            $this->notifyFail('Cannot confirm', $error);

            return;
        }

        app(MipirRecordRefresher::class)->refresh($ppf);
        app(XBarRecordRefresher::class)->refresh($ppf, app(CurrentActor::class)->plant(), null);

        unset($this->forConfirmation);
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
            <tbody class="bg-gray-700">
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
                            wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo}})">
                            Edit
                        </button>

                        @elseif($action === 'view')
                        <button class="text-white bg-gray-500 px-4 py-2 rounded"
                            wire:loading.attr="disabled"
                            wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo}})">
                            View
                        </button>

                        @elseif($action === 'delete')
                        <button class="text-white bg-red-600 px-4 py-2 rounded"
                            wire:loading.attr="disabled"
                            wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo}})">
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

    @if($action === 'confirm')
    <div class="w-full flex flex-col gap-3">
        <div class="w-full flex justify-between items-center">
            <h2 class="text-lg font-semibold">Confirm Done</h2>
            <input type="text" wire:model.live.debounce.400ms="confirmSearch"
                placeholder="Search PPF..."
                class="px-4 py-2 border rounded-md focus:outline-none focus:ring focus:border-blue-300">
        </div>

        <div class="w-full overflow-x-auto">
            <table class="table-auto w-full text-sm text-white bg-gray-800 rounded-lg overflow-hidden">
                <thead>
                    <tr class="bg-gray-900 text-center">
                        <th class="px-4 py-2">PPFNo</th>
                        <th class="px-4 py-2">PartNo</th>
                        <th class="px-4 py-2">Molding Die</th>
                        <th class="px-4 py-2">Prod Lot No</th>
                        <th class="px-4 py-2">Machine No</th>
                        <th class="px-4 py-2">Date Judge</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-gray-700">
                    @forelse($this->forConfirmation as $d)
                    @php $st = strtolower((string) $d->Status); @endphp
                    <tr wire:key="fc-{{ $d->PPFNo }}">
                        <td class="px-4 py-2 text-center">{{ $d->PPFNo }}</td>
                        <td class="px-4 py-2 text-center">{{ $d->PartNo }}</td>
                        <td class="px-4 py-2 text-center">{{ $d->MDNo }}</td>
                        <td class="px-4 py-2 text-center">{{ $d->ProdLotNo }}</td>
                        <td class="px-4 py-2 text-center">{{ $d->MachineNo }}</td>
                        <td class="px-4 py-2 text-center">{{ $d->DateJudge }}</td>
                        <td class="px-4 py-2 text-center">
                            @if($st === 'pending')
                            <span class="px-2 py-1 rounded bg-yellow-600">Waiting for GL</span>
                            @elseif($st === 'confirmed')
                            <span class="px-2 py-1 rounded bg-green-700">Confirmed</span>
                            @elseif($st === 'rejected')
                            <span class="px-2 py-1 rounded bg-red-700">Rejected</span>
                            <p class="text-xs text-red-300 mt-1">{{ $d->RejectReason }}</p>
                            @else
                            <span class="px-2 py-1 rounded bg-gray-500">Not yet confirmed</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 flex justify-center gap-2">
                            <button class="bg-gray-500 px-3 py-2 rounded"
                                wire:loading.attr="disabled"
                                wire:click.throttle.10000ms="confirm_ppf({{ $d->PPFNo }})">View</button>

                            @if($st === '' || $st === 'rejected')
                            <button class="bg-green-600 px-3 py-2 rounded"
                                wire:loading.attr="disabled"
                                wire:click="requestConfirmation({{ $d->PPFNo }})"
                                wire:confirm="Are you finished with PPF {{ $d->PPFNo }}? It will be sent to your GL for confirmation."> Done
                            </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center">No record found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="w-full">
            {{ $this->forConfirmation->links() }}
        </div>
    </div>
    @endif

    @if($showRemarksModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        x-data @keydown.escape.window="$wire.closeRemarks()">
        <div class="w-full max-w-md rounded-xl bg-gray-800 p-6 text-white shadow-xl">
            <h3 class="text-lg font-semibold">Missing check time</h3>
            <p class="mt-2 text-sm text-gray-300">
                PPF {{ $remarksPpf }} has no X-Bar record for
                <span class="font-medium text-amber-400">{{ implode(', ', $missingCheckTimes) }}</span>.
                Add a remark before sending it to your GL.
            </p>

            <textarea wire:model="remarks" rows="4" placeholder="Reason for the missing check time..."
                class="mt-3 w-full rounded-md border border-gray-600 bg-gray-700 px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"></textarea>
            @error('remarks')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="closeRemarks" class="rounded bg-gray-500 px-3 py-2 text-sm">Cancel</button>
                <button type="button" wire:click="submitRemarks" wire:loading.attr="disabled"
                    class="rounded bg-green-600 px-3 py-2 text-sm">Send to GL</button>
            </div>
        </div>
    </div>
    @endif
</div>