<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes Tightened Gap-Offset Excel workbooks.
 *
 * F00VE parts use InsertTightenedGapF00VE; other parts use
 * InsertTightenedGapOring.
 */
class TightenedGapOffsetRefresher
{
    public function refresh(
        int $ppf,
        array $ppfLookUp,
        $inspectorNo,
        array $checkTimes,
        array $touchedCheckTimes,
        array $dimensions,
        array $remarks,
        array $deletedCheckTimes
    ): void {
        $isGap = static fn (array $row): bool =>
            ($row['item'] ?? null) === 'Gap-Offset';

        $isTightenedGap = static fn (array $row): bool =>
            $isGap($row)
            && strcasecmp((string) ($row['mode'] ?? ''), 'tightened') === 0;

        $partNo = (string) ($ppfLookUp['partNo'] ?? '');
        $isF00VE = str_starts_with(strtoupper(trim($partNo)), 'F00VE');

        $gapRowsFor = static function (array $times) use ($dimensions, $remarks, $isTightenedGap): array {
            $rows = [];

            foreach ($times as $checkTime) {
                foreach ($dimensions[$checkTime] ?? [] as $row) {
                    if ($isTightenedGap($row)) {
                        $rows[] = [
                            'checkTime' => $checkTime,
                            'remarks' => $remarks[$checkTime] ?? '',
                            'judgement' => $row['judge'] ?? null,
                            'specification' => $row['specification'] ?? '',
                            'controlLimit' => $row['CL'] ?? '',
                            'measurements' => [
                                '0' => $row['measurements_y'] ?? [],
                                '90' => $row['measurements'] ?? [],
                            ],
                        ];
                    }
                }
            }

            return $rows;
        };

        $needsRebuild = false;

        foreach ($touchedCheckTimes as $checkTime) {
            foreach ($dimensions[$checkTime] ?? [] as $row) {
                if ($isGap($row) && ! $isTightenedGap($row)) {
                    $needsRebuild = true;
                    break 2;
                }
            }
        }

        if (! $needsRebuild) {
            foreach ($deletedCheckTimes as $deleted) {
                foreach ($deleted['dimensions'] ?? [] as $row) {
                    if ($isGap($row)) {
                        $needsRebuild = true;
                        break 2;
                    }
                }
            }
        }

        $upserts = $gapRowsFor($touchedCheckTimes);

        if (! $needsRebuild && $upserts === []) {
            return;
        }

        $deletedTimes = array_column($deletedCheckTimes, 'time');
        $allRows = $gapRowsFor(array_values(array_diff($checkTimes, $deletedTimes)));

        $context = [
            'ppf' => $ppf,
            'partNo' => $partNo,
            'moldNo' => $ppfLookUp['moldNo'] ?? '',
            'machineNo' => $ppfLookUp['machineNo'] ?? '',
            'lotNo' => $ppfLookUp['productionLotNo'] ?? '',
            'inspector' => $inspectorNo,
            'spec' => $allRows[0]['specification'] ?? '',
            'limit' => $allRows[0]['controlLimit'] ?? '',
        ];

        try {
            if ($isF00VE) {
                $service = app(InsertTightenedGapF00VE::class);

                if ($needsRebuild) {
                    $service->insertGapF00VEToExcel($context, $allRows);
                } else {
                    $service->appendGapF00VEToExcel($context, $upserts, $allRows);
                }
            } else {
                $service = app(InsertTightenedGapOring::class);

                if ($needsRebuild) {
                    $service->insertGapOringToExcel($context, $allRows);
                } else {
                    $service->appendGapOringToExcel($context, $upserts, $allRows);
                }
            }
        } catch (Throwable $e) {
            Log::error('Tightened Gap-Offset Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
