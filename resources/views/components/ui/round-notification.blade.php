<div
    x-data="{ loading: false, message: 'Fetching data...', submessage: 'Please wait while we load your records' }"
    x-on:show-loading.window="
        loading = true;
        message = $event.detail?.message ?? 'Fetching data...';
        submessage = $event.detail?.submessage ?? 'Please wait while we load your records';
    "
    x-on:hide-loading.window="loading = false"
    x-show="loading"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm"
    style="display:none;"
>
    <div class="bg-white rounded-2xl px-10 py-8 flex flex-col items-center gap-5">

        <div class="relative w-20 h-20">
            <svg viewBox="0 0 80 80" width="80" height="80"
                class="absolute animate-spin"
                style="animation-duration:1.1s;">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#e5e7eb" stroke-width="6"/>
                <path d="M40 6 A34 34 0 0 1 74 40" fill="none" stroke="#1d4ed8"
                    stroke-width="6" stroke-linecap="round"/>
            </svg>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 7c0-1.657 3.582-3 8-3s8 1.343 8 3M4 7v5c0 1.657 3.582 3 8 3s8-1.343 8-3V7M4 7c0 1.657 3.582 3 8 3s8-1.343 8-3"/>
                </svg>
            </div>
        </div>

        <div class="text-center">
            <p class="text-base font-medium text-gray-800 animate-pulse" x-text="message"></p>
            <p class="text-sm text-gray-500 mt-1" x-text="submessage"></p>
        </div>

    </div>
</div>