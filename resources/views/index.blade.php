<x-layouts.app>
    <div class="p-2">
        <x-gl-switcher current="inspection" />
        <livewire:inspection::page-host />
    </div>

    @if (session('gl_choice'))
    <div x-data="{ open: true }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-800">Where do you want to go?</h2>
            <p class="mt-1 text-sm text-gray-500">
                You're logged in as GL. Pick what you want to do.
            </p>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <a href="{{ route('lot-confirmation') }}"
                    class="rounded-lg bg-blue-600 px-4 py-3 text-center font-medium text-white hover:bg-blue-700">
                    Lot Confirmation
                </a>

                <button type="button"
                    @click="open = false"
                    class="rounded-lg border border-gray-300 px-4 py-3 font-medium text-gray-700 hover:bg-gray-50">
                    Inspection
                </button>
            </div>
        </div>
    </div>
    @endif
</x-layouts.app>