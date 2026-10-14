<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes the Tightened Flash Thickness Excel workbook.
 *
 * Data is supplied by the calling inspection component so this class does not
 * need to know how the form state is assembled.
 */
class TightenedFlashRefresher
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
        $isFlash = static fn (array $row): bool =>
            ($row['item'] ?? null) === 'Flash Thickness';

        $isTightenedFlash = static fn (array $row): bool =>
            $isFlash($row)
            && strcasecmp((string) ($row['mode'] ?? ''), 'tightened') === 0;

        $flashRowsFor = static function (array $times) use ($dimensions, $remarks, $isTightenedFlash): array {
            $rows = [];

            foreach ($times as $checkTime) {
                foreach ($dimensions[$checkTime] ?? [] as $row) {
                    if ($isTightenedFlash($row)) {
                        $rows[] = [
                            'checkTime' => $checkTime,
                            'measurements' => $row['measurements'] ?? [],
                            'specification' => $row['specification'] ?? '',
                            'controlLimit' => $row['CL'] ?? '',
                            'remarks' => $remarks[$checkTime] ?? '',
                        ];
                    }
                }
            }

            return $rows;
        };

        $needsRebuild = false;

        foreach ($touchedCheckTimes as $checkTime) {
            foreach ($dimensions[$checkTime] ?? [] as $row) {
                if ($isFlash($row) && ! $isTightenedFlash($row)) {
                    $needsRebuild = true;
                    break 2;
                }
            }
        }

        if (! $needsRebuild) {
            foreach ($deletedCheckTimes as $deleted) {
                foreach ($deleted['dimensions'] ?? [] as $row) {
                    if ($isFlash($row)) {
                        $needsRebuild = true;
                        break 2;
                    }
                }
            }
        }

        $upserts = $flashRowsFor($touchedCheckTimes);

        if (! $needsRebuild && $upserts === []) {
            return;
        }

        $deletedTimes = array_column($deletedCheckTimes, 'time');
        $allRows = $flashRowsFor(array_values(array_diff($checkTimes, $deletedTimes)));

        $context = [
            'ppf' => $ppf,
            'partNo' => $ppfLookUp['partNo'] ?? '',
            'moldNo' => $ppfLookUp['moldNo'] ?? '',
            'machineNo' => $ppfLookUp['machineNo'] ?? '',
            'lotNo' => $ppfLookUp['productionLotNo'] ?? '',
            'inspector' => $inspectorNo,
        ];

        try {
            $service = app(InsertTightenedFlash::class);

            if ($needsRebuild) {
                $service->insertFlashToExcel($context, $allRows);
            } else {
                $service->appendFlashToExcel($context, $upserts, $allRows);
            }
        } catch (Throwable $e) {
            Log::error('Tightened Flash Thickness Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
