@php
$isStaffMenu = auth('web')->check() && session('access') !== 'gl';
@endphp
<nav class="flex-1 py-4 space-y-1 px-2 overflow-y-auto overflow-x-hidden">

    <x-ui.sidebar-button
        page="dashboard"
        title="Process Inspection Dashboard"
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

    <x-ui.sidebar-button
        page="cycle-time-report"
        title="Cycle Time / Man-hr"
        @click.stop="desktopExpanded = false; mobileOpen = false">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">

                <!-- Clock face (cycle time) -->
                <circle cx="9.5" cy="11" r="7" />

                <!-- Clock hands -->
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9.5 7v4l2.5 1.5" />

                <!-- Person (man-hr): head -->
                <circle cx="19" cy="14" r="1.8" />

                <!-- Person: shoulders -->
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16 21c0-2 1.3-3.5 3-3.5s3 1.5 3 3.5" />
            </svg>
        </x-slot:icon>
    </x-ui.sidebar-button>

    @if ($isStaffMenu)
    <x-ui.sidebar-button
        page="dimension-encoding"
        title="Dimension Encoding"
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
    <x-ui.sidebar-button
        page="x-bar-generation"
        title="Report Generation"
        @click.stop="desktopExpanded = false; mobileOpen = false">

        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">

                <!-- X-Bar symbol -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 18l4-4 4 3 5-7 5 3" />

                <!-- Chart points -->
                <circle cx="3" cy="18" r="1" fill="currentColor" stroke="none" />
                <circle cx="7" cy="14" r="1" fill="currentColor" stroke="none" />
                <circle cx="11" cy="17" r="1" fill="currentColor" stroke="none" />
                <circle cx="16" cy="10" r="1" fill="currentColor" stroke="none" />
                <circle cx="21" cy="13" r="1" fill="currentColor" stroke="none" />

                <!-- X-bar / center line -->
                <path stroke-linecap="round"
                    d="M3 6h18" />

                <!-- X-bar symbol -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8 7l2 3 2-3" />

            </svg>
        </x-slot:icon>

    </x-ui.sidebar-button>

    <x-ui.sidebar-button
        page="inspector-registration"
        title="Inspector Registration"
        @click.stop="desktopExpanded = false; mobileOpen = false">

        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">

                <!-- Inspector (head) -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M13 7a4 4 0 11-8 0 4 4 0 018 0z" />

                <!-- Inspector (shoulders) -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 20a6 6 0 0112 0v1H3v-1z" />

                <!-- Register / add (+) -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M18 8v6m-3-3h6" />

            </svg>
        </x-slot:icon>

    </x-ui.sidebar-button>

    <x-ui.sidebar-button
        page="gl-registration"
        title="GL Registration"
        @click.stop="desktopExpanded = false; mobileOpen = false">

        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8">

                <!-- Group Leader -->
                <circle cx="9" cy="7" r="3"
                    stroke-linecap="round"
                    stroke-linejoin="round" />

                <!-- Group Leader shoulders -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.5 19v-1.5a5.5 5.5 0 0111 0V19" />

                <!-- Registration form -->
                <rect x="14" y="3" width="7" height="10"
                    rx="1.2"
                    stroke-linecap="round"
                    stroke-linejoin="round" />

                <!-- Form lines -->
                <path stroke-linecap="round"
                    d="M16 6h3M16 8.5h3" />

                <!-- Checkmark -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.5 11l1.2 1.2 2.3-2.5" />

                <!-- Registration record -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 17h5M15 20h5" />

            </svg>
        </x-slot:icon>

    </x-ui.sidebar-button>

    @else
    <x-ui.sidebar-button
        page="gap-offset"
        title="Gap & Offset Encoding"
        @click.stop="desktopExpanded = false; mobileOpen = false">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">

                <!-- Top line -->
                <path stroke-linecap="round" d="M4 7h16" />

                <!-- Bottom line -->
                <path stroke-linecap="round" d="M4 17h16" />

                <!-- Offset measurement -->
                <path stroke-linecap="round" d="M12 9v6" />

                <!-- Up arrow -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9.5 11.5L12 9l2.5 2.5" />

                <!-- Down arrow -->
                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9.5 14.5L12 17l2.5-2.5" />

                <path stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6" />
            </svg>
        </x-slot:icon>
    </x-ui.sidebar-button>

    <x-ui.sidebar-button
        page="mka-measuring-encoding"
        title="MKA Measuring Encoding"
        @click.stop="desktopExpanded = false; mobileOpen = false">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2">

                <!-- Machine frame -->
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4 19h16" />

                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 19V5h4v14" />

                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6 5h12" />

                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M18 5v6" />

                <!-- Measuring arm -->
                <path stroke-linecap="round"
                    d="M10 9h8" />

                <path stroke-linecap="round"
                    d="M14 9v4" />

                <!-- Probe -->
                <path stroke-linecap="round"
                    d="M14 13v2" />

                <circle cx="14" cy="16" r="1" />

                <!-- Workpiece -->
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 17h10v2H9z" />

                <!-- Measurement indication -->
                <path stroke-linecap="round"
                    d="M17 12h2" />

                <path stroke-linecap="round"
                    d="M17 10.5h2" />

            </svg>

            <path stroke-linecap="round"
                stroke-linejoin="round"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6" />
            </svg>
        </x-slot:icon>
    </x-ui.sidebar-button>


    @endif
</nav>