<div
    x-data="{ open: false }"
    @open-scanner-modal.window="open = true"
    @close-scanner-modal.window="open = false">

    <div
        x-show="open"
        x-transition.opacity
        class="overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 flex justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full"
        style="display: none;">

        {{-- Backdrop --}}
        <div
            class="fixed inset-0 bg-white/10 backdrop-blur-sm"
            @click="$dispatch('close-scanner-modal')">
        </div>

        {{-- Panel --}}
        <div class="relative p-4 w-full max-w-2xl max-h-full" @click.stop>
            <div class="relative bg-white rounded-lg shadow-sm">

                {{-- Header --}}
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-900">
                        Scan the PPF No
                    </h3>
                    <button
                        type="button"
                        id="scanner-id-close"
                        @click="$dispatch('close-scanner-modal')"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>

                {{-- Body --}}
                <div class="max-w-lg mx-auto">
                    <video class="w-full rounded-lg border border-gray-300" id="video"></video>
                </div>

            </div>
        </div>
    </div>
</div>