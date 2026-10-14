<?php

use App\Inspection\Repositories\LotConfirmationRepository;
use App\Inspection\Services\LotConfirmationService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasNotifications;

    public ?int $rejectingId = null;
    public string $reason = '';

    private function employeeId(): string
    {
        return (string) Auth::guard('web')->user()->社員CD;
    }

    #[Computed]
    public function lots()
    {
        return app(LotConfirmationRepository::class)->pendingFor($this->employeeId());
    }

    public function confirm(int $id, LotConfirmationService $service): void
    {
        try {
            $service->confirm($id, $this->employeeId());
            unset($this->lots);
            $this->notifySuccess('Confirmed', 'Lot confirmed. The Excel files were updated.');
        } catch (\DomainException $e) {
            unset($this->lots);
            $this->notifyFail('Not allowed', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Lot confirm failed', ['id' => $id, 'error' => $e->getMessage()]);
            $this->notifyFail('Failed', 'Failed to confirm the lot. Please try again.');
        }
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->reason = '';
    }

    public function reject(LotConfirmationService $service): void
    {
        try {
            $service->reject((int) $this->rejectingId, $this->employeeId(), $this->reason);
            $this->reset(['rejectingId', 'reason']);
            unset($this->lots);
            $this->notifySuccess('Rejected', 'The lot was returned to the inspector.');
        } catch (\DomainException $e) {
            $this->notifyFail('Not allowed', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Lot reject failed', ['id' => $this->rejectingId, 'error' => $e->getMessage()]);
            $this->notifyFail('Failed', 'Failed to reject the lot. Please try again.');
        }
    }
};
?>

<div wire:poll.15s class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">For confirmation</h2>
        <span class="text-sm text-gray-500">{{ $this->lots->count() }} pending</span>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @forelse ($this->lots as $lot)
        <div wire:key="lot-{{ $lot->Id }}" class="flex items-center justify-between gap-4 px-6 py-3">
            <div>
                <p class="text-sm font-medium text-gray-800">{{ $lot->PartNo }} · Lot {{ $lot->ProdLotNo }}</p>
                <p class="text-xs text-gray-400">
                    {{ $lot->Plant }} · Machine {{ $lot->MachineNo }} · {{ \Illuminate\Support\Carbon::parse($lot->RequestedAt)->diffForHumans() }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="startReject({{ $lot->Id }})"
                    class="px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50">
                    Reject
                </button>
                <button type="button" wire:click="confirm({{ $lot->Id }})"
                    wire:confirm="Confirm lot {{ $lot->ProdLotNo }}?"
                    wire:loading.attr="disabled" wire:target="confirm"
                    class="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                    Confirm
                </button>
            </div>
        </div>
        @empty
        <p class="px-6 py-8 text-center text-sm text-gray-400">Nothing waiting for your confirmation.</p>
        @endforelse
    </div>

    @if ($rejectingId)
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-2">
        <label class="block text-sm font-medium text-gray-700">Reason for rejecting</label>
        <textarea wire:model="reason" rows="2" class="w-full rounded-lg border border-gray-300 text-sm"></textarea>
        <div class="flex justify-end gap-2">
            <button type="button" wire:click="$set('rejectingId', null)" class="px-3 py-1.5 text-sm text-gray-600">Cancel</button>
            <button type="button" wire:click="reject" class="px-3 py-1.5 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700">Reject lot</button>
        </div>
    </div>
    @endif
</div>