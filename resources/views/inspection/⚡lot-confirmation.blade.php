<?php

use App\Inspection\Models\GroupLeader\GroupLeader;
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

    public ?int $reviewId = null;
    public array $reviewed = [];

    private function employeeId(): string
    {
        return (string) Auth::guard('web')->user()->社員CD;
    }

    private function InspectorId(): string
    {
        return (string) GroupLeader::where('EmployeeID', $this->employeeId())->value('InspectorNo');
    }

    #[Computed]
    public function lots()
    {
        return app(LotConfirmationRepository::class)->pendingFor($this->employeeId());
    }

    #[Computed]
    public function review(): ?array
    {
        return $this->reviewId === null
            ? null
            : app(LotConfirmationRepository::class)->getLotReview($this->reviewId);
    }

    public function openReview(int $id): void
    {
        if (! $this->lots->contains('Id', $id)) {
            unset($this->lots);
            $this->notifyFail('Not allowed', 'This lot is no longer in your list.');
            return;
        }

        $this->reviewId = $id;
        $this->reviewed = [];
    }

    public function closeReview(): void
    {
        $this->reset(['reviewId', 'reviewed']);
        unset($this->review);
    }

    public function confirmReviewed(LotConfirmationService $service): void
    {
        $review = $this->review;

        if ($review === null || $review['checkTimes'] === []) {
            $this->notifyFail('Nothing to confirm', 'No check time was found for this lot.');
            return;
        }

        $required = collect($review['checkTimes'])->pluck('key')->all();
        $missing  = array_diff($required, array_keys(array_filter($this->reviewed)));

        if ($missing !== []) {
            $this->notifyFail('Review incomplete', 'Please check every check time before confirming.');
            return;
        }

        if ($this->confirm((int) $this->reviewId, $service)) {
            $this->closeReview();
        }
    }

    public function confirm(int $id, LotConfirmationService $service): bool
    {

        try {
            $service->confirm($id, $this->InspectorId(), $this->employeeId());
            unset($this->lots);
            $this->notifySuccess('Confirmed', 'Lot confirmed. The Excel files were updated.');

            return true;
        } catch (\DomainException $e) {
            unset($this->lots);
            $this->notifyFail('Not allowed', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Lot confirm failed', ['id' => $id, 'error' => $e->getMessage()]);
            $this->notifyFail('Failed', 'Failed to confirm the lot. Please try again.');
        }

        return false;
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->reason = '';
    }

    public function rejectFromReview(): void
    {
        $id = $this->reviewId;

        $this->closeReview();

        if ($id !== null) {
            $this->startReject($id);
        }
    }

    #[Computed]
    public function ownLot(): bool
    {
        $review = $this->review;

        return $review !== null
            && app(LotConfirmationService::class)
            ->inspectedByApprover($review, $this->InspectorId(), $this->employeeId());
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

{{-- Naka-pause ang poll habang bukas ang review modal --}}
<div @unless($reviewId) wire:poll.15s @endunless class="space-y-4">
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
                <button type="button" wire:click="openReview({{ $lot->Id }})"
                    wire:loading.attr="disabled" wire:target="openReview"
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

    {{-- Review modal --}}
    @if ($reviewId && $this->review)
    @php
    $review = $this->review;
    $keys = collect($review['checkTimes'])->pluck('key');
    $checkedCount = $keys->filter(fn($k) => ! empty($reviewed[$k] ?? null))->count();
    $allChecked = $keys->isNotEmpty() && $checkedCount === $keys->count();
    $fmt = fn($v) => number_format((float) $v, 4, '.', '');
    @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
        wire:key="review-modal"
        x-data
        x-on:keydown.escape.window="$wire.closeReview()">

        <div class="absolute inset-0 bg-gray-900/50" wire:click="closeReview"></div>

        <div class="relative w-full max-w-5xl max-h-[90vh] flex flex-col bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden"
            role="dialog" aria-modal="true">

            {{-- Header --}}
            <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">
                        Review before confirming
                    </h3>
                    <p class="text-sm text-gray-500">
                        {{ $review['lot']->PartNo }} · Lot {{ $review['lot']->ProdLotNo }} ·
                        Machine {{ $review['lot']->MachineNo }} · {{ $review['lot']->Plant }}
                    </p>
                </div>
                <button type="button" wire:click="closeReview" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                @if ($this->ownLot)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    You inspected this lot, so you cannot confirm it. Another GL must approve it.
                </div>
                @endif
                @forelse ($review['checkTimes'] as $ct)
                <div wire:key="review-{{ $ct['key'] }}"
                    class="rounded-xl border {{ $ct['failed'] ? 'border-red-300' : 'border-gray-200' }}">

                    <div class="flex items-center justify-between gap-3 px-4 py-3 bg-gray-50 rounded-t-xl">
                        <div class="flex items-center gap-3">
                            <span class="text-base font-semibold text-gray-900">{{ $ct['checktime'] }}</span>
                            <span class="text-xs text-gray-500">
                                PPF {{ $ct['ppf'] }} · Inspector {{ $ct['inspector'] }} ·
                                {{ \Illuminate\Support\Carbon::parse($ct['judgedAt'])->format('Y-m-d H:i') }}
                            </span>
                            @if ($ct['failed'])
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Has X</span>
                            @endif
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" wire:model.live="reviewed.{{ $ct['key'] }}"
                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            Checked
                        </label>
                    </div>

                    <div class="p-4 space-y-4">
                        {{-- Defects --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">
                                Defects
                                @if ($ct['ngPercent'] !== null)
                                <span class="normal-case font-normal text-gray-500">· NG {{ $ct['ngPercent'] }}%</span>
                                @endif
                            </p>

                            @if (count($ct['defects']) > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-500">
                                            <th class="py-1 pr-4 font-medium">Shot</th>
                                            <th class="py-1 pr-4 font-medium">Defect</th>
                                            <th class="py-1 pr-4 font-medium">Qty</th>
                                            <th class="py-1 font-medium">Small defects</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($ct['defects'] as $row)
                                        <tr>
                                            <td class="py-1 pr-4 text-gray-700">{{ $row['shot'] ?? '—' }}</td>
                                            <td class="py-1 pr-4 {{ $row['defect'] ? 'text-gray-900' : 'text-gray-400' }}">{{ $row['defect'] ?? 'No defect' }}</td>
                                            <td class="py-1 pr-4 text-gray-700">{{ $row['qty'] }}</td>
                                            <td class="py-1 text-gray-500">{{ $row['small'] ?: '—' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-sm text-gray-400">No defects recorded.</p>
                            @endif
                        </div>

                        {{-- Dimensions --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Measurements</p>

                            @if (count($ct['dims']) > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-500">
                                            <th class="py-1 pr-3 font-medium">Item</th>
                                            <th class="py-1 pr-3 font-medium">Set</th>
                                            <th class="py-1 pr-3 font-medium">Mode</th>
                                            <th class="py-1 pr-3 font-medium">Specs</th>
                                            <th class="py-1 pr-3 font-medium">CL</th>
                                            @foreach ([1, 2, 3, 4, 5] as $n)
                                            <th class="py-1 pr-3 font-medium text-right">{{ $n }}</th>
                                            @endforeach
                                            <th class="py-1 font-medium text-center">Judge</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($ct['dims'] as $row)
                                        <tr class="{{ $row['judge'] === 'X' ? 'bg-red-50' : '' }}">
                                            <td class="py-1 pr-3 text-gray-900">{{ $row['item'] }}</td>
                                            <td class="py-1 pr-3 text-gray-700">{{ $row['set'] }}</td>
                                            <td class="py-1 pr-3 text-gray-500">{{ $row['mode'] }}</td>
                                            <td class="py-1 pr-3 text-gray-700">{{ $row['specs'] }}</td>
                                            <td class="py-1 pr-3 text-gray-700">{{ $row['cl'] ?: '—' }}</td>
                                            @foreach ($row['values'] as $v)
                                            <td class="py-1 pr-3 text-right tabular-nums text-gray-800">{{ $fmt($v) }}</td>
                                            @endforeach
                                            <td class="py-1 text-center font-semibold {{ $row['judge'] === 'X' ? 'text-red-600' : 'text-emerald-600' }}">
                                                {{ $row['judge'] }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-sm text-gray-400">No measurements recorded.</p>
                            @endif
                        </div>

                        @if (filled($ct['remarks']))
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Remarks</p>
                            <p class="text-sm text-gray-600">{{ $ct['remarks'] }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <p class="py-8 text-center text-sm text-gray-400">No check time was found for this lot.</p>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200">
                <span class="text-sm {{ $allChecked ? 'text-emerald-600' : 'text-gray-500' }}">
                    {{ $checkedCount }} of {{ $keys->count() }} check times checked
                </span>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="closeReview"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="rejectFromReview"
                        class="px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50">
                        Reject
                    </button>
                    <button type="button" wire:click="confirmReviewed"
                        wire:confirm="Confirm lot {{ $review['lot']->ProdLotNo }}?"
                        wire:loading.attr="disabled" wire:target="confirmReviewed"
                        @disabled(! $allChecked || $this->ownLot)
                        class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>