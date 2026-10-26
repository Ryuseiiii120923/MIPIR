<?php

namespace App\Inspection\Services\Excel;

use App\Domain\XBar\Repositories\XBarRepository as RepositoriesXBarRepository;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Services\Excel\InspectionXBarService as ExcelInspectionXBarService;
use App\Inspection\Services\XBarCycleTracker;
use App\Inspection\Support\PartNumber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class XBarRecordRefresher
{
    public function refresh(int $ppf, ?string $plant, ?string $confirmedBy, ?array $jobs = null): void
    {
        if ($plant === null) {
            Log::warning('XBar Excel skipped: user has no Plant');
            return;
        }

        $jobs ??= $this->jobsFor($ppf);

        Log::info('XBar refresh', [
            'PPFNo' => $ppf,
            'plant' => $plant,
            'jobs' => $jobs,
        ]);

        $mdNo = trim((string) MIPIRInspectionRecord::where('PPFNo', $ppf)->orderByDesc('RECNO')->value('MDNo'));

        $safe = static fn(string $value): string =>
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
                )
                    ->map(fn($p) => trim((string) $p))
                    ->filter()
                    ->unique()
                    ->sort(fn($a, $b) => [strlen($a), $a] <=> [strlen($b), $b])
                    ->values()
                    ->all();

                if ($partNos === []) {
                    Log::warning('XBar Excel skipped: no part numbers for transaction', $job);
                    continue;
                }

                $name = sprintf(
                    'Xbar_%s_%s_%s_%s_%s',
                    $safe($plant),
                    $safe(PartNumber::base($partNos[0])),
                    $safe($mdNo !== '' ? $mdNo : 'NOMD'),
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
                ->unique(fn($r) => "{$r->DimItem}|{$r->xbarTransaction}")
                ->map(fn($r) => [
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
