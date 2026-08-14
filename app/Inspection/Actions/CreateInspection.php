<?php

namespace App\Inspection\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Inspection\Services\Saving\CreateInspectionService;

class CreateInspection
{
    public function execute(int $ppf, array $draft): void
    {
        $ppfLookUp = $draft['ppfLookup'] ?? null;
        $checkTimes = $draft['check-time']['check-time'] ?? [];
        $defects = $draft['defects']['defects'] ?? [];
        $ngpercent = $draft['defects']['ngPercent'];
        $defectJudge = $draft['defects']['judgement'];
        $dimensions = $draft['dimensions'] ?? [];
        $judgment = $draft['judgement']['judgement'] ?? null;
        $processDetails = $draft['process-details'] ?? null;
        $dateJudge = $draft['judgement']['dateOfJudge']??null;

        Log::debug('CreateInspection: draft shapes', [
            'ppf' => $ppf,
            'checkTimes' => $checkTimes,
            'judgment_value' => $judgment,
        ]);

        if (empty($processDetails['productionLotNo']) || empty($processDetails['machineNo'])) {
            throw new \Exception('Process details are required to create an inspection.');
        }

        DB::transaction(function () use ($defectJudge, $ngpercent, $ppf, $ppfLookUp, $checkTimes, $defects, $dimensions, $judgment, $processDetails) {
            foreach ($checkTimes as $checkTime) {
                app(CreateInspectionService::class)->createInspectionRecord([
                    'PPFNo' => $ppf,
                    'PartNo' => $ppfLookUp['partNo'] ?? null,
                    'MDNo' => $ppfLookUp['moldNo'] ?? null,
                    'NoofCavity' => $ppfLookUp['noOfCavity'] ?? null,
                    'NQR' => $ppfLookUp['nqr'] ?? null,
                    'ProdLotNo' => $processDetails['productionLotNo'],
                    'MachineNo' => $processDetails['machineNo'],
                    'Checktime' => $checkTime ?? null,
                    'DateJudge' => $dateJudge ?? now(),
                    'InspectBy' => Auth::user()->EmployeeID ?? null,
                    'Judgement' => $judgment === 'Passed' ? 1 : 0,
                    'Year' => now()->year,
                ]);

                $defectsForThisTime = $defects[$checkTime] ?? [];

                foreach ($defectsForThisTime as $defect) {
                    app(CreateInspectionService::class)->createDefect([
                        'PPFNo' => $ppf,
                        'PartNo' => $ppfLookUp['partNo'] ?? null,
                        'MDNo' => $ppfLookUp['moldNo'] ?? null,
                        'ProdLotNo' => $processDetails['productionLotNo'],
                        'MachineNo' => $processDetails['machineNo'],
                        'Checktime' => $checkTime,
                        'Defect' => $defect['type'] ?? null,
                        'Qty' => $defect['qty'] ?? null,
                        'Judgement' => $defectJudge === 'X' ? 1 : 0,
                        'NGPercent' => $ngpercent
                    ]);
                }

                $rowsForThisTime = $dimensions[$checkTime] ?? [];

                foreach ($rowsForThisTime as $row) {
                    $measurements = $row['measurements'] ?? [];

                    app(CreateInspectionService::class)->createDimensionMeasure([
                        'PPFNo'      => $ppf,
                        'MDNo'       => $ppfLookUp['moldNo'] ?? null,
                        'PartNo'     => $ppfLookUp['partNo'] ?? null,
                        'ProdLotNo'  => $processDetails['productionLotNo'],
                        'MachineNo'  => $processDetails['machineNo'],
                        'Checktime'  => $checkTime,
                        'Specs'      => $row['specification'] ?? null,
                        'DimItem'    => $row['item'] ?? null,
                        'Judge'      => $row['judge'] === 'O' ? 0 : 1,
                        '1'          => (float) ($measurements[0] ?? 0),
                        '2'          => (float) ($measurements[1] ?? 0),
                        '3'          => (float) ($measurements[2] ?? 0),
                        '4'          => (float) ($measurements[3] ?? 0),
                        '5'          => (float) ($measurements[4] ?? 0),
                    ]);

                    if (isset($row['measurements_y'])) {
                        $yMeasurements = $row['measurements_y'];

                        app(CreateInspectionService::class)->createDimensionMeasure([
                            'PPFNo'      => $ppf,
                            'MDNo'       => $ppfLookUp['moldNo'] ?? null,
                             'PartNo'     => $ppfLookUp['partNo'] ?? null,
                            'ProdLotNo'  => $processDetails['productionLotNo'],
                            'MachineNo'  => $processDetails['machineNo'],
                            'Checktime'  => $checkTime,
                            'Specs'      => $row['specification'] ?? null,
                            'DimItem'    => ($row['item'] ?? '') . ' (Y)',
                            'Judge'      => $row['judge'] === 'O' ? 0 : 1,
                            '1' => (float) ($yMeasurements[0] ?? 0),
                            '2' => (float) ($yMeasurements[1] ?? 0),
                            '3' => (float) ($yMeasurements[2] ?? 0),
                            '4' => (float) ($yMeasurements[3] ?? 0),
                            '5' => (float) ($yMeasurements[4] ?? 0),
                        ]);
                    }
                }
            }
        });
    }
}