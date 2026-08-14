<?php

namespace App\Traits;

trait WithLoading
{
    public bool $loading = false;

    public function startLoading(string $message = 'Fetching data...', string $submessage = 'Please wait while we load your records'): void
    {
        $this->loading = true;

        $this->js("window.dispatchEvent(new CustomEvent('show-loading', { detail: " . json_encode([
            'message' => $message,
            'submessage' => $submessage,
        ]) . " }))");
    }

    public function stopLoading(): void
    {
        $this->loading = false;
        $this->js("window.dispatchEvent(new Event('hide-loading'))");
    }
}