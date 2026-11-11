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
        $config   = config('services.teams_notify');
        $channels = $this->resolveChannels($event->plant, $config);

        if (empty($channels)) {
            Log::warning('X-Bar full: no Teams channel mapped for plant, notification skipped', [
                'plant'       => $event->plant,
                'dimension'   => $event->dimension,
                'transaction' => $event->transactionId,
            ]);
            return;
        }

        $filePath = XBarCycleLog::where('TransactionId', $event->transactionId)->value('FilePath');
        $hasFile  = $filePath && is_file($filePath);

        $url = $hasFile
            ? URL::temporarySignedRoute('xbar.download', now()->addDays(7), ['transaction' => $event->transactionId])
            : rtrim($config['dashboard_url'], '/') . '#x-bar-generation';

        $partNumbers = collect($event->partNumbers)->filter()->unique()->values();

        $partLabel = $partNumbers->count() > 3
            ? $partNumbers->take(3)->implode(', ') . ' +' . ($partNumbers->count() - 3) . ' more'
            : $partNumbers->implode(', ');

        try {
            $response = Http::timeout(5)->post($config['url'], [
                'title'       => '📊 X-Bar Full',
                'message'     => "X-Bar for {$event->dimension} is full (25 subgroups). Plant {$event->plant}, Part Number(s): {$partLabel}.",
                'url'         => $url,
                'button_text' => $hasFile ? 'Download X-Bar' : 'Open X-Bar Generation',
                'channels'    => $channels,
            ]);

            if (! $response->successful()) {
                Log::warning('X-Bar full Teams notification was rejected', [
                    'status'      => $response->status(),
                    'url'         => $config['url'],
                    'body'        => mb_substr($response->body(), 0, 500),
                    'dimension'   => $event->dimension,
                    'transaction' => $event->transactionId,
                    'channels'    => $channels,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('X-Bar full Teams notification failed', [
                'dimension' => $event->dimension,
                'channels'  => $channels,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function resolveChannels(?string $plant, array $config): array
    {
        $key      = strtoupper(trim((string) $plant));
        $plantMap = array_change_key_case($config['plant_channels'] ?? [], CASE_UPPER);

        return isset($plantMap[$key]) ? [$plantMap[$key]] : [];
    }
}
