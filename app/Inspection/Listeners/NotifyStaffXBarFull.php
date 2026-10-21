<?php

namespace App\Inspection\Listeners;

use App\Inspection\Events\XBarCycleFull;
use App\Inspection\Models\XBarCycleLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class NotifyStaffXBarFull
{
    public function handle(XBarCycleFull $event): void
    {
        $config = config('services.teams_notify');

        $filePath = XBarCycleLog::where('TransactionId', $event->transactionId)->value('FilePath');
        $hasFile  = $filePath && is_file($filePath);

        $url = $hasFile
            ? URL::temporarySignedRoute('xbar.download', now()->addDays(7), ['transaction' => $event->transactionId])
            : rtrim($config['dashboard_url'], '/') . '#x-bar-generation';

        try {
            $response = Http::timeout(5)->post($config['url'], [
                'title'       => '📊 X-Bar Full',
                'message'     => "X-Bar for {$event->dimension} is full (25 subgroups). Plant {$event->plant}, PPF {$event->ppf}.",
                'url'         => $url,
                'button_text' => $hasFile ? 'Download X-Bar' : 'Open X-Bar Generation',
                'channels'    => $config['channels'],
            ]);

            if (! $response->successful()) {
                Log::warning('X-Bar full Teams notification was rejected', [
                    'status'      => $response->status(),
                    'url'         => $config['url'],
                    'body'        => mb_substr($response->body(), 0, 500),
                    'dimension'   => $event->dimension,
                    'transaction' => $event->transactionId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('X-Bar full Teams notification failed', [
                'dimension' => $event->dimension,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
