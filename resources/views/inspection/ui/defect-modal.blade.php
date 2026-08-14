<div
    x-show="openAddDefect"
    x-cloak
    x-trap.noscroll="openAddDefect"
    x-transition
    class="fixed inset-0 flex items-center justify-center z-50 p-4 ">

    <!-- Overlay -->
    <div x-show="openAddDefect" x-transition.opacity class="fixed inset-0 bg-white/10 backdrop-blur-sm z-40"></div>

    <!-- Modal Panel -->
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-5xl z-50 flex flex-col max-h-[90vh] overflow-y-auto">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
            <div>
                <h3 class="text-xl font-bold text-gray-900">Add Defects</h3>
                <p class="text-sm text-gray-500 mt-0.5">
                    @if(count($staged) > 0)
                    <span class="text-blue-600 font-medium">{{ count($staged) }} defect{{ count($staged) > 1 ? 's' : '' }} staged</span> — add more or confirm
                    @elseif($modalSelectedType)
                    <span class="text-blue-600 font-medium">{{ $modalSelectedType }}</span> selected — fill in qty, then stage or confirm
                    @else
                    Select one or more defect types to get started
                    @endif
                </p>
            </div>
            <button
                type="button"
                wire:click="cancelDefectModal"
                @click="openAddDefect = false"
                class="text-gray-400 hover:text-gray-700 rounded-lg w-8 h-8 flex justify-center items-center hover:bg-gray-100 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                </svg>
            </button>
        </div>

        <!-- Current defects table -->
         @unless($readonly)
        <div class="px-6 pt-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Current Defects</p>
            @include('inspection.ui.defect-table')
        </div>
        @endunless
        
        <!-- Body: Three-column layout -->
        <div class="flex flex-1 mt-4 border-t border-gray-100">
            @include('inspection.ui.defect-modal-staged-column')
            @include('inspection.ui.defect-modal-type-column')
            @include('inspection.ui.defect-modal-config')
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white z-10">
            <button
                type="button"
                wire:click="cancelDefectModal"
                @click="openAddDefect = false"
                class="px-5 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
                Cancel
            </button>
            <button
                type="button"
                wire:click="confirmDefects"
                x-on:defect-confirmed.window="openAddDefect = false"
                class="px-5 py-2 rounded-lg bg-[#0F3C89] text-white text-sm font-medium hover:bg-blue-800 transition disabled:opacity-50
                    {{ (count($staged) === 0 && !$modalSelectedType) ? 'opacity-50 cursor-not-allowed' : '' }}">
                Confirm &amp; Add{{ count($staged) > 0 ? ' (' . count($staged) . ')' : '' }}
            </button>
        </div>

    </div>
</div>