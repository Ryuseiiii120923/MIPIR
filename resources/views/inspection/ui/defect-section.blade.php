<div
    @if($readonly)
    x-data="{ openAddDefect: true }"
    x-init="openAddDefect = false"
    @else
    x-data="{ openAddDefect: false }"
    x-init="openAddDefect = true"
    @endif

    wire:init="reviewCommittedDefects"
    class="bg-white rounded-lg w-full max-w-1xl mx-auto py-4  @if($readonly) opacity-50 cursor-not-allowed @endif">

    <div class="bg-gray-700 w-full">
        <p class="text-4xl font-extrabold text-center text-white p-4">Defect</p>
    </div>

    <!-- ADD DEFECT BUTTON (reopen modal if closed) -->
    @unless($readonly)
    <div class="w-full flex justify-center mb-3 px-3 mt-5">
        <button
            @click="$wire.reviewCommittedDefects().then(() => { openAddDefect = true })"
            class="text-white w-11/12 sm:w-2/3 bg-[#0F3C89] hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center"
            id="add-defect">
            Add Defect / Edit Staged Defects
        </button>
    </div>
    @endunless

    <div class="px-6 pt-4">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Current Defects</p>
        @include('inspection.ui.defect-table')
    </div>


    @if(count($defects) > 0)
    <p class="text-center text-sm text-gray-500 mt-2">
        {{ count($defects) }} defect{{ count($defects) > 1 ? 's' : '' }} added — Total NG: <span class="font-semibold text-gray-700">{{ $totalNg }}</span>
    </p>
    @endif

    <!-- ERROR MESSAGES -->
    @error('newDefect') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
    @error('newQuan') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror

    @include('inspection.ui.defect-modal')

</div>