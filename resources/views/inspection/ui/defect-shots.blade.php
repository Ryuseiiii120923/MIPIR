@if ($mode === 'tightened')
<div class="space-y-2">
    <div class="flex flex-wrap items-center justify-center gap-2">
        @foreach ($shots as $i => $shot)
        @php
        $summary = $shotSummaries[$i] ?? null;
        $active = $selectedShot === $i;
        @endphp
        <div wire:key="shot-{{ $i }}"
            class="flex items-center rounded-lg border text-sm font-medium transition
                {{ $active ? 'border-blue-600 bg-blue-50 text-blue-700 ring-2 ring-blue-300' : 'border-gray-300 bg-white text-gray-700 hover:border-blue-400' }}">
            <button type="button" wire:click="selectShot({{ $i }})" class="flex items-center gap-2 px-3 py-2">
                <span>Shot {{ $shot['shot'] }}</span>
                @if ($summary)
                <span class="text-xs text-gray-500">NG {{ $summary['ng'] }}</span>
                <span class="text-xs font-bold px-1.5 py-0.5 rounded-full {{ $summary['judgement'] === 'X' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                    {{ $summary['judgement'] }}
                </span>
                @endif
            </button>

            @if (! $readonly && count($shots) > 1)
            <button type="button"
                wire:click="removeShot({{ $i }})"
                wire:confirm="Remove Shot {{ $shot['shot'] }} and its defects?"
                class="px-2 py-2 text-gray-400 hover:text-red-600">✕</button>
            @endif
        </div>
        @endforeach

        @unless ($readonly)
        <button type="button" wire:click="addShot"
            class="px-3 py-2 rounded-lg border-2 border-dashed border-blue-400 text-blue-600 text-sm font-semibold hover:bg-blue-50">
            + Add Shot
        </button>
        @endunless
    </div>

    @if ($selectedShot !== null)
    <p class="text-center text-sm text-gray-500">
        Defects below belong to <span class="font-semibold text-gray-700">Shot {{ $shots[$selectedShot]['shot'] }}</span>
    </p>
    @endif
</div>
@endif