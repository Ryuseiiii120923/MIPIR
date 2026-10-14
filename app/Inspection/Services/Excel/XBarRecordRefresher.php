<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Repositories\XBarRepository;
use App\Inspection\Services\InspectionXBarService;
use App\Inspection\Services\XBarCycleTracker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes XBar Excel workbooks for the supplied transaction jobs.
 */
class XBarRecordRefresher
{
    public function refresh(array $jobs, ?string $plant): void
    {
        Log::info('XBar refresh', [
            'plant' => $plant,
            'jobs' => array_values($jobs),
        ]);

        if ($plant === null) {
            Log::warning('XBar Excel skipped: user has no Plant');
            return;
        }

        $safe = static fn (string $value): string =>
            (string) preg_replace('/[^A-Za-z0-9_-]/', '_', $value);

        foreach ($jobs as $job) {
            try {
                $window = app(XBarCycleTracker::class)->cycleWindow(
                    $plant,
                    $job['dimension'],
                    $job['transactionId']
                );

                if ($window === null) {
                    Log::warning('XBar Excel skipped: cycle window not found', $job);
                    continue;
                }

                $partNos = collect(
                    app(XBarRepository::class)->getPartNumbersForTransaction($job['transactionId'])
                )->values()->all();

                if ($partNos === []) {
                    Log::warning('XBar Excel skipped: no part numbers for transaction', $job);
                    continue;
                }

                $name = sprintf(
                    'Xbar_%s_%s_%s',
                    $safe($plant),
                    $safe((string) $job['dimension']),
                    Carbon::parse($window['start'])->format('Ymd_His')
                );

                $path = app(InspectionXBarService::class)->generate(
                    $partNos,
                    $job['dimension'],
                    $window['start'],
                    $window['end'],
                    $job['transactionId'],
                    $name
                );

                XBarCycleLog::where('TransactionId', $job['transactionId'])
                    ->update(['FilePath' => $path]);
            } catch (Throwable $e) {
                Log::error('XBar Excel failed', [
                    'dimension' => $job['dimension'] ?? null,
                    'transactionId' => $job['transactionId'] ?? null,
                    'error' => $e->getMessage(),
                    'at' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }
    }
}
