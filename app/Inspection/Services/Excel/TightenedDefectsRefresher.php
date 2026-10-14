<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Synchronizes tightened shot judgements to the SAIR Excel workbook.
 */
class TightenedDefectsRefresher
{
    public function refresh(
        int $ppf,
        array $ppfLookUp,
        $inspectorNo,
        array $checkTimes,
        array $modeByTime,
        array $shotsByTime
    ): void {
        $cavity = (float) ($ppfLookUp['noOfCavity'] ?? 0);
        $nqr = (float) ($ppfLookUp['nqr'] ?? 0);
        $judgements = [];

        foreach ($checkTimes as $checkTime) {
            if (($modeByTime[$checkTime] ?? 'normal') !== 'tightened') {
                continue;
            }

            foreach ($shotsByTime[$checkTime] ?? [] as $shot) {
                $label = (int) ($shot['shot'] ?? 0);

                if ($label < 1) {
                    continue;
                }

                $ng = collect($shot['defects'] ?? [])
                    ->sum(static fn ($defect) => (float) ($defect['qty'] ?? 0));

                $percentage = $cavity > 0 ? ($ng / $cavity) * 100 : 0;
                $judge = $percentage > $nqr ? 'X' : 'O';

                // If a shot label appears more than once, X takes precedence.
                $judgements[$label] = ($judgements[$label] ?? 'O') === 'X'
                    ? 'X'
                    : $judge;
            }
        }

        ksort($judgements);

        $hasTightened = collect($checkTimes)
            ->contains(static fn ($time) => ($modeByTime[$time] ?? 'normal') === 'tightened');

        try {
            app(InsertTightenedDefects::class)->sync([
                'partNo' => $ppfLookUp['partNo'] ?? '',
                'partName' => $ppfLookUp['partName'] ?? '',
                'machineNo' => $ppfLookUp['machineNo'] ?? '',
                'moldNo' => $ppfLookUp['moldNo'] ?? '',
                'lotNo' => $ppfLookUp['productionLotNo'] ?? '',
                'inspector' => $inspectorNo,
            ], $judgements, ensureFile: $hasTightened);
        } catch (Throwable $e) {
            Log::error('Tightened defects SAIR Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
