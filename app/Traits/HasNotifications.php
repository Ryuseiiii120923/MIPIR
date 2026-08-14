<?php

namespace App\Traits;

trait HasNotifications
{
    protected function notify(
        string $type,
        string $title,
        string $message = '',
        int $duration = 3500
    ): void {
        $this->dispatch(
            'notify',
            type: $type,
            title: $title,
            message: $message,
            duration: $duration,
        );
    }

    protected function notifySuccess(
        string $title,
        string $message = '',
        int $duration = 3500
    ): void {
        $this->notify('success', $title, $message, $duration);
    }

    protected function notifyFail(
        string $title,
        string $message = '',
        int $duration = 3500
    ): void {
        $this->notify('fail', $title, $message, $duration);
    }

    protected function notifyReload(string $type, string $message): void
    {
        $this->dispatch('sessionNotify',
        type: $type,
        message : $message
        );
    }
}
