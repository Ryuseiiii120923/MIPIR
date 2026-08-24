<nav class="flex-1 py-4 space-y-1 px-2 overflow-y-auto overflow-x-hidden">

    <x-ui.sidebar-button
        page="dashboard"
        title="Hand Finishing Dashboard"
        @click.stop="desktopExpanded = false; mobileOpen = false">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">

                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6" />
            </svg>
        </x-slot:icon>
    </x-ui.sidebar-button>

    @if (auth('web')->check())
    <x-ui.sidebar-button
        page="dimension-approval"
        title="Dimension Approval"
        @click.stop="desktopExpanded = false; mobileOpen = false">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">

                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-5 9l2 2 4-4" />
            </svg>
        </x-slot:icon>
    </x-ui.sidebar-button>
    @endif
</nav>