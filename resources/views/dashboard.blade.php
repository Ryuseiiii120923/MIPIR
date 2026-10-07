<x-layouts.app>
    <div
        class="p-2"
        x-data="{ currentPage: 'dashboard' }"
        @navigate-to.window="currentPage = $event.detail.page">

        <div
            x-show="currentPage === 'dashboard'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0">
            <livewire:dashboard::index />
        </div>
        <div
            x-show="currentPage === 'dimension-encoding'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0">
            <livewire:dashboard::dimensions.dimension-encoding />
        </div>
        <div
            x-show="currentPage === 'x-bar-generation'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0">
            <livewire:dashboard::reports-browser />
        </div>

         <div
            x-show="currentPage === 'inspector-registration'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0">
            <livewire:dashboard::inspector-registration />
        </div>
    </div>
</x-layouts.app>