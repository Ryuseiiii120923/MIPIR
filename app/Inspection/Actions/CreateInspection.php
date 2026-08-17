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
        $dateEncodeCheck = $draft['check-time']['date-encode'] ?? [];
        $defects = $draft['defects']['defects'] ?? [];
        $ngpercent = $draft['defects']['ngPercent'];
        $defectJudge = $draft['defects']['judgement'];
        $dimensions = $draft['dimensions'] ?? [];
        $judgment = $draft['judgement']['judgement'] ?? null;
        $processDetails = $draft['process-details'] ?? null;
        $remarks = $draft['remarks'] ?? [];
        $dateJudge = $draft['judgement']['dateOfJudge'] ?? null;
    

        if (empty($processDetails['productionLotNo']) || empty($processDetails['machineNo'])) {
            throw new \Exception('Process details are required to create an inspection.');
        }

        DB::transaction(function () use ($dateEncodeCheck, $defectJudge, $ngpercent, $ppf, $ppfLookUp, $checkTimes, $defects, $dimensions, $judgment, $processDetails, $remarks) {
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

                app(CreateInspectionService::class)->saveCheckTime([
                    'PPFNo' => $ppf,
                    'check-time' => $checkTime,
                    'date-encode' => $dateEncodeCheck[$checkTime] ?? now(),
                    'machine-no' => $processDetails['machineNo'] ?? null
                ]);

                app(CreateInspectionService::class)->saveRemarksByTime([
                    'PPFNo' => $ppf,
                    'PartNo' => $ppfLookUp['partNo'] ?? null,
                    'MachineNo' => $processDetails['machineNo'],
                    'CheckTime' => $checkTime ?? null,
                    'ProdLotNo' => $processDetails['productionLotNo'],
                    'Remarks' => $remarks[$checkTime] ?? ''
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
                    $mode = $row['mode'] ?? null;
                    $judge = ($row['judge'] ?? null) === 'O' ? 0 : 1;

                    $setsCount = (int) ceil(count($measurements) / 5);

                    for ($s = 0; $s < $setsCount; $s++) {
                        $chunk = array_slice($measurements, $s * 5, 5);

                        app(CreateInspectionService::class)->createDimensionMeasure([
                            'PPFNo'      => $ppf,
                            'MDNo'       => $ppfLookUp['moldNo'] ?? null,
                            'PartNo'     => $ppfLookUp['partNo'] ?? null,
                            'ProdLotNo'  => $processDetails['productionLotNo'],
                            'MachineNo'  => $processDetails['machineNo'],
                            'Checktime'  => $checkTime,
                            'Mode'       => $mode,
                            'Set'        => $s + 1,
                            'Specs'      => $row['specification'] ?? null,
                            'DimItem'    => $row['item'] ?? null,
                            'Judge'      => $judge,
                            '1' => number_format((float) ($chunk[0] ?? 0), 4, '.', ''),
                            '2' => number_format((float) ($chunk[1] ?? 0), 4, '.', ''),
                            '3' => number_format((float) ($chunk[2] ?? 0), 4, '.', ''),
                            '4' => number_format((float) ($chunk[3] ?? 0), 4, '.', ''),
                            '5' => number_format((float) ($chunk[4] ?? 0), 4, '.', ''),
                        ]);
                    }

                    if (isset($row['measurements_y'])) {
                        $yMeasurements = $row['measurements_y'];
                        $ySetsCount = (int) ceil(count($yMeasurements) / 5);

                        for ($s = 0; $s < $ySetsCount; $s++) {
                            $yChunk = array_slice($yMeasurements, $s * 5, 5);

                            app(CreateInspectionService::class)->createDimensionMeasure([
                                'PPFNo'      => $ppf,
                                'MDNo'       => $ppfLookUp['moldNo'] ?? null,
                                'PartNo'     => $ppfLookUp['partNo'] ?? null,
                                'ProdLotNo'  => $processDetails['productionLotNo'],
                                'MachineNo'  => $processDetails['machineNo'],
                                'Checktime'  => $checkTime,
                                'Mode'       => $mode,
                                'Set'        => $s + 1,
                                'Specs'      => $row['specification'] ?? null,
                                'DimItem'    => ($row['item'] ?? '') . ' (Y)',
                                'Judge'      => $judge,
                                '1' => number_format((float) ($yChunk[0] ?? 0), 4, '.', ''),
                                '2' => number_format((float) ($yChunk[1] ?? 0), 4, '.', ''),
                                '3' => number_format((float) ($yChunk[2] ?? 0), 4, '.', ''),
                                '4' => number_format((float) ($yChunk[3] ?? 0), 4, '.', ''),
                                '5' => number_format((float) ($yChunk[4] ?? 0), 4, '.', ''),
                            ]);
                        }
                    }
                }
            }
        });
    }
}
