<?php

namespace App\Inspection\Services\Excel;

use App\Domain\XBar\Repositories\XBarRepository as RepositoriesXBarRepository;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Services\Excel\InspectionXBarService as ExcelInspectionXBarService;
use App\Inspection\Services\XBarCycleTracker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes XBar Excel workbooks for the (dimension, transaction) pairs
 * stored against a PPF in tblDimensionMeasure.
 */
class XBarRecordRefresher
{
    public function refresh(int $ppf, ?string $plant, ?string $confirmedBy): void
    {
        if ($plant === null) {
            Log::warning('XBar Excel skipped: user has no Plant');
            return;
        }

        $jobs = $this->jobsFor($ppf);

        Log::info('XBar refresh', [
            'PPFNo' => $ppf,
            'plant' => $plant,
            'jobs' => $jobs,
        ]);

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
                    app(RepositoriesXBarRepository::class)->getPartNumbersForTransaction($job['transactionId'])
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

                $path = app(ExcelInspectionXBarService::class)->generate(
                    $partNos,
                    $job['dimension'],
                    $window['start'],
                    $window['end'],
                    $job['transactionId'],
                    $name,
                    $confirmedBy
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

    /**
     * @return array<int, array{dimension: string, transactionId: mixed}>
     */
    private function jobsFor(int $ppf): array
    {
        try {
            return MIPIRDimensionMeasure::where('PPFNo', $ppf)
                ->where('ForXBar', 1)
                ->whereNotNull('xbarTransaction')
                ->where('xbarTransaction', '!=', '')
                ->get(['DimItem', 'xbarTransaction'])
                ->unique(fn ($r) => "{$r->DimItem}|{$r->xbarTransaction}")
                ->map(fn ($r) => [
                    'dimension' => (string) $r->DimItem,
                    'transactionId' => $r->xbarTransaction,
                ])
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::error('XBar jobs lookup failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}