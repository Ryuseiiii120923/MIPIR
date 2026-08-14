<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    protected function notifySuccess(string $title, string $message = '', int $duration = 3500): void
    {
        $this->dispatch('notify', [
            'type'     => 'success',
            'title'    => $title,
            'message'  => $message,
            'duration' => $duration,
        ]);
    }
    protected function notifyFail(string $title, string $message = '', int $duration = 3500): void
    {
        $this->dispatch('notify', [
            'type'     => 'fail',
            'title'    => $title,
            'message'  => $message,
            'duration' => $duration,
        ]);
    }
};
?>

<div
    x-data="{
        notifications: [],
        counter: 0,
        add(detail) {
            const notif = {
                id:       ++this.counter,
                type:     detail.type    ?? 'success',
                title:    detail.title   ?? (detail.type === 'success' ? 'Success' : 'Failed'),
                message:  detail.message ?? '',
                duration: detail.duration ?? 3500,
                visible:  true,
                timer:    null,
            };
            this.notifications.unshift(notif);
            notif.timer = setTimeout(() => this.dismiss(notif), notif.duration);
        },
        dismiss(notif) {
            clearTimeout(notif.timer);
            notif.visible = false;
            setTimeout(() => {
                this.notifications = this.notifications.filter(n => n.id !== notif.id);
            }, 250);
        }
    }"
    x-on:notify.window="add($event.detail)"
    aria-live="polite"
    class="fixed top-4 right-4 z-[9999] flex flex-col gap-2.5 w-[300px]"
>
    <template x-for="notif in notifications" :key="notif.id">
        <div
            x-show="notif.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-8"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-8"
            :class="notif.type === 'success'
                ? 'bg-blue-50 border border-blue-300'
                : 'bg-blue-950 border border-blue-700'"
            class="relative flex items-start gap-3 rounded-xl px-4 py-3.5 overflow-hidden"
            role="alert"
        >
            {{-- Icon --}}
            <div
                :class="notif.type === 'success'
                    ? 'bg-blue-200 text-blue-800'
                    : 'bg-red-700 text-red-200'"
                class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
            >
                <template x-if="notif.type === 'success'">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </template>
                <template x-if="notif.type !== 'success'">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </template>
            </div>

            {{-- Body --}}
            <div class="flex-1 min-w-0">
                <p
                    :class="notif.type === 'success' ? 'text-blue-950' : 'text-red-50'"
                    class="text-sm font-semibold leading-snug mb-0.5"
                    x-text="notif.title"
                ></p>
                <p
                    :class="notif.type === 'success' ? 'text-blue-600' : 'text-red-300'"
                    class="text-xs leading-snug"
                    x-text="notif.message"
                ></p>
            </div>

            {{-- Close --}}
            <button
                @click="dismiss(notif)"
                :class="notif.type === 'success' ? 'text-blue-500 hover:text-blue-700' : 'text-blue-400 hover:text-blue-200'"
                class="flex-shrink-0 mt-0.5 p-0.5 opacity-70 hover:opacity-100 transition-opacity"
                aria-label="Dismiss"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            {{-- Progress bar --}}
            <div
                :class="notif.type === 'success' ? 'bg-blue-400' : 'bg-blue-600'"
                :style="`animation-duration: ${notif.duration}ms`"
                class="absolute bottom-0 left-0 h-[3px] w-full [animation-name:notif-shrink] [animation-timing-function:linear] [animation-fill-mode:forwards]"
            ></div>
        </div>
    </template>
</div>

@once
@push('styles')
<style>
@keyframes notif-shrink {
    from { width: 100%; }
    to   { width: 0%; }
}
</style>
@endpush
@endonce