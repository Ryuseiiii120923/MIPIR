<button
    type="button"
    @click="
    mobileOpen = false;
    desktopExpanded = false;
    currentPage = @js($page);
    $dispatch('navigate-to', { page: @js($page) })
"
    :class="currentPage === @js($page)
        ? 'bg-white/20 text-white shadow-lg scale-[1.02]'
        : 'text-white/70 hover:bg-white/10 hover:text-white'"
    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg
           text-sm font-medium transition-all duration-200"
    title="{{ $title }}">
    <span class="flex-shrink-0">
        {{ $icon }}
    </span>

    <span
        :class="{ 'opacity-100 max-w-full ml-1': isExpanded }"
        class="opacity-0 max-w-0 whitespace-nowrap overflow-hidden transition-all duration-200 text-left">
        {{ $title }}
    </span>
</button>