<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\EncodingDuration;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use App\Inspection\Models\XBar\ControlSpecsLimit;
use App\Inspection\Repositories\MIPIRInspectionReporsitory;
use App\Inspection\Services\Excel\InsertTightenedFlash;
use App\Inspection\Services\Excel\InsertTightenedGapF00VE;
use App\Inspection\Services\Excel\InsertTightenedGapOring;
use App\Inspection\Services\Excel\InsertTightenedRecord;
use App\Inspection\Services\Excel\MIPIRRecordCycleTracker;
use App\Inspection\Services\XBarCycleTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Inspection\Services\Saving\CreateInspectionService;
use Illuminate\Support\Facades\Log;

class CreateInspection
{
    private const SMALL_DEFECT_MARKER = '[Small Defects]';
    public function execute(int $ppf, array $draft, bool $recordDuration = true): void
    {
        $ppfLookUp = $draft['ppfLookup'] ?? null;
        $checkTimes = $draft['check-time']['check-time'] ?? [];
        $dateEncodeCheck = $draft['check-time']['date-encode'] ?? [];
        $startTimeByTime = $draft['check-time']['start-time'] ?? [];
        $endTimeByTime = $draft['check-time']['end-time'] ?? [];
        $touchedCheckTimes = $draft['check-time']['touched'] ?? $checkTimes; // fallback treats all as touched
        $defects = $draft['defects']['defects'] ?? [];
        $smallDefects = $draft['defects']['smallDefects'] ?? [];
        $modeByTime = $draft['defects']['mode'] ?? [];
        $shotsByTime = $draft['defects']['shots'] ?? [];
        $ngpercent = $draft['defects']['ngPercent'] ?? null;
        $defectJudge = $draft['defects']['judgement'] ?? null;
        $dimensions = $draft['dimensions'] ?? [];
        $remarks = $draft['remarks'] ?? [];
        $deletedCheckTimes = $draft['check-time']['deleted'] ?? [];
        $dateJudge = $draft['judgement']['dateOfJudge'] ?? null;
        $inspectorNo = Auth::user()->InspectorNo ?? Null;

        $defectBatches = [];
        $composedRemarks = [];
        foreach ($checkTimes as $time) {
            $defectBatches[$time] = $this->defectBatchesFor($time, $defects, $smallDefects, $modeByTime, $shotsByTime);
            $composedRemarks[$time] = $this->composeRemarks($remarks[$time] ?? '', $defectBatches[$time]);
        }

        if (empty($ppfLookUp['productionLotNo']) || empty($ppfLookUp['machineNo'])) {
            throw new \InvalidArgumentException('Process details are required to create an inspection.');
        }

        if (empty($checkTimes)) {
            throw new \InvalidArgumentException('At least one check time is required to create an inspection.');
        }

        DB::transaction(function () use ($composedRemarks, $dateEncodeCheck, $defectBatches, $defectJudge, $inspectorNo, $ngpercent, $ppf, $ppfLookUp, $touchedCheckTimes, $dimensions, $dateJudge, $remarks) {
            foreach ($touchedCheckTimes as $checkTime) {
                $isExistingCheckTime = MIPIRInspectionRecord::where('PPFNo', $ppf)
                    ->where('Checktime', $checkTime)
                    ->exists();
                $existingTransactionByKey = MIPIRDimensionMeasure::where('PPFNo', $ppf)
                    ->where('Checktime', $checkTime)
                    ->get(['DimItem', 'Set', 'xbarTransaction'])
                    ->mapWithKeys(fn($m) => ["{$m->DimItem}|{$m->Set}" => $m->xbarTransaction])
                    ->all();
                $existingDefect = Defect::where('PPFNo', $ppf)
                    ->where('Checktime', $checkTime)
                    ->first(['Judgement', 'NGPercent']);

                $judgementForTime = $defectJudge[$checkTime]
                    ?? ($existingDefect ? ($existingDefect->Judgement ? 'X' : 'O') : null);

                $ngPercentForTime = $ngpercent[$checkTime]
                    ?? $existingDefect?->NGPercent
                    ?? 0;

                MIPIRDimensionMeasure::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                Defect::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                ChckTRemarks::where('PPFNo', $ppf)->where('CheckTime', $checkTime)->delete();
                SmallDefect::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                CheckTime::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                MIPIRInspectionRecord::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();

                app(CreateInspectionService::class)->createInspectionRecord([
                    'PPFNo' => $ppf,
                    'PartNo' => $ppfLookUp['partNo'] ?? null,
                    'MDNo' => $ppfLookUp['moldNo'] ?? null,
                    'NoofCavity' => $ppfLookUp['noOfCavity'] ?? null,
                    'NQR' => $ppfLookUp['nqr'] ?? null,
                    'ProdLotNo' => $ppfLookUp['productionLotNo'],
                    'MachineNo' => $ppfLookUp['machineNo'],
                    'Checktime' => $checkTime ?? null,
                    'DateJudge' => $dateJudge ?? now(),
                    'InspectBy' => Auth::user()->EmployeeID ?? null,
                    'Year' => now()->year,
                    'MoldingOperator' => $ppfLookUp['moldOperator'] ?? null
                ]);

                if ($checkTime === 'E') {
                    app(MIPIRRecordCycleTracker::class)->checkAndRecordLotComplete($ppf, $ppfLookUp['partNo'] ?? '');
                }

                app(CreateInspectionService::class)->saveCheckTime([
                    'PPFNo' => $ppf,
                    'PartNo' => $ppfLookUp['partNo'] ?? null,
                    'Checktime' => $checkTime,
                    'DateEncode' => $dateEncodeCheck[$checkTime] ?? now(),
                    'MachineNo' => $ppfLookUp['machineNo'] ?? null
                ]);

                app(CreateInspectionService::class)->saveRemarksByTime([
                    'PPFNo' => $ppf,
                    'PartNo' => $ppfLookUp['partNo'] ?? null,
                    'MachineNo' => $ppfLookUp['machineNo'],
                    'CheckTime' => $checkTime ?? null,
                    'ProdLotNo' => $ppfLookUp['productionLotNo'],
                    'Remarks' => $composedRemarks[$checkTime] ?? '',
                ]);

                // Normal: one batch (shot = null). Tightened: one batch per shot.
                foreach ($defectBatches[$checkTime] ?? [] as $batch) {
                    $defectRows = $batch['defects'];

                    // Tightened shot na walang defect: mag-iwan ng placeholder row para hindi mawala ang shot
                    if ($batch['shot'] !== null && $defectRows === []) {
                        $defectRows = [['type' => null, 'qty' => 0]];
                    }

                    foreach ($defectRows as $defect) {
                        app(CreateInspectionService::class)->createDefect([
                            'PPFNo' => $ppf,
                            'PartNo' => $ppfLookUp['partNo'] ?? null,
                            'MDNo' => $ppfLookUp['moldNo'] ?? null,
                            'ProdLotNo' => $ppfLookUp['productionLotNo'],
                            'MachineNo' => $ppfLookUp['machineNo'],
                            'Checktime' => $checkTime,
                            'Shot' => $batch['shot'],
                            'Defect' => $defect['type'] ?? null,
                            'Qty' => $defect['qty'] ?? 0,
                            'Judgement' => $judgementForTime === 'X' ? 1 : 0,
                            'NGPercent' => $ngPercentForTime,
                        ]);

                        $smallRows = $defect['type'] !== null
                            ? ($batch['smallDefects'][$defect['type']] ?? [])
                            : [];

                        foreach ($smallRows as $small) {
                            app(MIPIRInspectionReporsitory::class)->createSmall([
                                'PPFNo' => $ppf,
                                'Checktime' => $checkTime,
                                'shot' => $batch['shot'],
                                'largeDefect' => $defect['type'],
                                'smallDefect' => $small['type'],
                                'qty' => $small['qty']
                            ]);
                        }
                    }
                }

                $rowsForThisTime = $dimensions[$checkTime] ?? [];
                foreach ($rowsForThisTime as $row) {

                    $measurements = $row['measurements'] ?? [];
                    $mode = $row['mode'] ?? null;
                    $judge = ($row['judge'] ?? null) === 'O' ? 0 : 1;
                    $itemName = $row['item'] ?? null;
                    $forXBar = $row['forXBar'] ?? false;
                    $controlLimit = $row['CL'] ?? "";
                    $isXBarTracked = ! in_array($itemName, ['Flash Thickness', 'Gap-Offset'], true);

                    $setsCount = (int) ceil(count($measurements) / 5);

                    for ($s = 0; $s < $setsCount; $s++) {
                        $chunk = array_slice($measurements, $s * 5, 5);

                        $transactionId = null;
                        $xbarResult = null;

                        if ($isExistingCheckTime) {
                            $transactionId = $existingTransactionByKey["{$itemName}|" . ($s + 1)] ?? null;
                        } elseif ($isXBarTracked && $itemName && $forXBar) {
                            $xbarResult = app(XBarCycleTracker::class)->recordSubgroup(
                                Auth::user()->Plant,
                                $itemName,
                                $dateEncodeCheck[$checkTime] ?? now()->toDateTimeString()
                            );
                            $transactionId = $xbarResult['transactionId'];
                        }

                        app(CreateInspectionService::class)->createDimensionMeasure([
                            'PPFNo'      => $ppf,
                            'MDNo'       => $ppfLookUp['moldNo'] ?? null,
                            'PartNo'     => $ppfLookUp['partNo'] ?? null,
                            'ProdLotNo'  => $ppfLookUp['productionLotNo'],
                            'MachineNo'  => $ppfLookUp['machineNo'],
                            'Checktime'  => $checkTime,
                            'Mode'       => $mode,
                            'Set'        => $s + 1,
                            'Specs'      => $row['specification'] ?? null,
                            'DimItem'    => $itemName,
                            'Judge'      => $judge,
                            'CL' => $controlLimit,
                            'forXBar'    => $forXBar,
                            'xbarTransaction' => $transactionId,
                            '1' => number_format((float) ($chunk[0] ?? 0), 4, '.', ''),
                            '2' => number_format((float) ($chunk[1] ?? 0), 4, '.', ''),
                            '3' => number_format((float) ($chunk[2] ?? 0), 4, '.', ''),
                            '4' => number_format((float) ($chunk[3] ?? 0), 4, '.', ''),
                            '5' => number_format((float) ($chunk[4] ?? 0), 4, '.', ''),
                            'InspectedBy' => $inspectorNo
                        ]);

                        if ($xbarResult !== null && $xbarResult['shouldGenerate']) {
                            app(XBarCycleTracker::class)->closeCycleAndGenerate(
                                Auth::user()->Plant,
                                $itemName,
                                $xbarResult['cycleStart'],
                                $xbarResult['cycleEnd'],
                                $transactionId
                            );
                        }
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
                                'ProdLotNo'  => $ppfLookUp['productionLotNo'],
                                'MachineNo'  => $ppfLookUp['machineNo'],
                                'Checktime'  => $checkTime,
                                'Mode'       => $mode,
                                'Set'        => $s + 1,
                                'Specs'      => $row['specification'] ?? null,
                                'DimItem'    => ($row['item'] ?? '') . ' (Y)',
                                'Judge'      => $judge,
                                'CL' => $controlLimit,
                                '1' => number_format((float) ($yChunk[0] ?? 0), 4, '.', ''),
                                '2' => number_format((float) ($yChunk[1] ?? 0), 4, '.', ''),
                                '3' => number_format((float) ($yChunk[2] ?? 0), 4, '.', ''),
                                '4' => number_format((float) ($yChunk[3] ?? 0), 4, '.', ''),
                                '5' => number_format((float) ($yChunk[4] ?? 0), 4, '.', ''),
                                'InspectedBy' => $inspectorNo
                            ]);
                        }
                    }
                }
            }
        });

        DB::transaction(function () use ($ppf, $deletedCheckTimes) {
            foreach ($deletedCheckTimes as $deleted) {
                $checkTime = $deleted['time'];
                $rows = $deleted['dimensions'] ?? [];

                MIPIRDimensionMeasure::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                Defect::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                ChckTRemarks::where('PPFNo', $ppf)->where('CheckTime', $checkTime)->delete();
                SmallDefect::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                CheckTime::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                MIPIRInspectionRecord::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();
                EncodingDuration::where('PPFNo', $ppf)->where('Checktime', $checkTime)->delete();

                $plant = Auth::user()->Plant ?? null;

                if ($plant !== null) {
                    foreach ($rows as $row) {
                        $itemName = $row['item'] ?? null;
                        $forXBar = $row['forXBar'] ?? false;
                        $isXBarTracked = ! in_array($itemName, ['Flash Thickness', 'Gap-Offset'], true);

                        if (! $isXBarTracked || ! $forXBar || ! $itemName) {
                            continue;
                        }

                        $setsCount = (int) ceil(count($row['measurements'] ?? []) / 5);

                        for ($s = 0; $s < $setsCount; $s++) {
                            app(XBarCycleTracker::class)->reverseSubgroup($plant, $itemName);
                        }
                    }
                }
            }
        });

        $this->refreshTightenedFlashExcel($ppf, $ppfLookUp, $inspectorNo, $checkTimes, $touchedCheckTimes, $dimensions, $composedRemarks, $deletedCheckTimes);
        $this->refreshTightenedGapOffsetExcel($ppf, $ppfLookUp, $inspectorNo, $checkTimes, $touchedCheckTimes, $dimensions, $composedRemarks, $deletedCheckTimes);

        if ($recordDuration) {
            Log::info('EncodingDuration: recordDuration block entered', [
                'PPFNo' => $ppf,
                'touchedCheckTimes' => $touchedCheckTimes,
            ]);

            foreach ($touchedCheckTimes as $checkTime) {
                $existing = EncodingDuration::where('PPFNo', $ppf)
                    ->where('Checktime', $checkTime)
                    ->first();

                Log::info('EncodingDuration: processing check time', [
                    'PPFNo' => $ppf,
                    'Checktime' => $checkTime,
                    'existingFound' => $existing !== null,
                    'startTimeByTime' => $startTimeByTime[$checkTime] ?? null,
                    'endTimeByTime' => $endTimeByTime[$checkTime] ?? null,
                ]);

                $record = EncodingDuration::updateOrCreate(
                    [
                        'PPFNo'     => $ppf,
                        'Checktime' => $checkTime,
                    ],
                    [
                        'PartNo'        => $ppfLookUp['partNo'] ?? null,
                        'MachineNo'     => $ppfLookUp['machineNo'] ?? null,
                        'Encoder'       => Auth::user()->EmployeeID ?? null,
                        'StartDatetime' => $existing->StartDatetime ?? ($startTimeByTime[$checkTime] ?? now()),
                        'EndDatetime'   => $endTimeByTime[$checkTime] ?? now(),
                    ]
                );

                Log::info('EncodingDuration: saved', [
                    'PPFNo' => $ppf,
                    'Checktime' => $checkTime,
                    'RECNO' => $record->getKey() ?? null,
                    'wasRecentlyCreated' => $record->wasRecentlyCreated,
                ]);
            }
        } else {
            Log::info('EncodingDuration: skipped, recordDuration is false', ['PPFNo' => $ppf]);
        }
    }

    private function refreshTightenedFlashExcel(
        int $ppf,
        array $ppfLookUp,
        $inspectorNo,
        array $checkTimes,
        array $touchedCheckTimes,
        array $dimensions,
        array $remarks,
        array $deletedCheckTimes
    ): void {
        $isFlash = fn($row) => ($row['item'] ?? null) === 'Flash Thickness';
        $isTightenedFlash = fn($row) => $isFlash($row)
            && strcasecmp((string) ($row['mode'] ?? ''), 'tightened') === 0;

        // Builds the tightened Flash Thickness rows straight from the submitted measurements
        $flashRowsFor = function (array $times) use ($dimensions, $remarks, $isTightenedFlash): array {
            $rows = [];

            foreach ($times as $checkTime) {
                foreach ($dimensions[$checkTime] ?? [] as $row) {
                    if ($isTightenedFlash($row)) {
                        $rows[] = [
                            'checkTime'     => $checkTime,
                            'measurements'  => $row['measurements'] ?? [],
                            'specification' => $row['specification'] ?? '',
                            'controlLimit'  => $row['CL'] ?? '',
                            'remarks'       => $remarks[$checkTime] ?? '',
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
                    // Flash row is now Normal: an old tightened row may have to leave the file
                    $needsRebuild = true;
                }
            }
        }

        foreach ($deletedCheckTimes as $deleted) {
            if (collect($deleted['dimensions'] ?? [])->contains($isFlash)) {
                $needsRebuild = true;
            }
        }

        $upserts = $flashRowsFor($touchedCheckTimes);

        if (! $needsRebuild && $upserts === []) {
            return;
        }

        // Every remaining check time, used for a full rebuild of the sheet
        $deletedTimes = array_column($deletedCheckTimes, 'time');
        $allRows = $flashRowsFor(array_values(array_diff($checkTimes, $deletedTimes)));

        $context = [
            'ppf'       => $ppf,
            'partNo'    => $ppfLookUp['partNo'] ?? '',
            'moldNo'    => $ppfLookUp['moldNo'] ?? '',
            'machineNo' => $ppfLookUp['machineNo'],
            'lotNo'     => $ppfLookUp['productionLotNo'],
            'inspector' => $inspectorNo,
        ];

        try {
            $service = app(InsertTightenedFlash::class);

            if ($needsRebuild) {
                $service->insertFlashToExcel($context, $allRows);
            } else {
                $service->appendFlashToExcel($context, $upserts, $allRows);
            }
        } catch (\Throwable $e) {
            Log::error('Tightened Flash Thickness Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function refreshTightenedGapOffsetExcel(
        int $ppf,
        array $ppfLookUp,
        $inspectorNo,
        array $checkTimes,
        array $touchedCheckTimes,
        array $dimensions,
        array $remarks,
        array $deletedCheckTimes
    ): void {
        $isGap = fn($row) => ($row['item'] ?? null) === 'Gap-Offset';
        $isTightenedGap = fn($row) => $isGap($row)
            && strcasecmp((string) ($row['mode'] ?? ''), 'tightened') === 0;

        $partNo = (string) ($ppfLookUp['partNo'] ?? '');
        $isF00VE = str_starts_with(strtoupper(trim($partNo)), 'F00VE');

        $gapRowsFor = function (array $times) use ($dimensions, $remarks, $isTightenedGap): array {
            $rows = [];

            foreach ($times as $checkTime) {
                foreach ($dimensions[$checkTime] ?? [] as $row) {
                    if ($isTightenedGap($row)) {
                        $rows[] = [
                            'checkTime'     => $checkTime,
                            'remarks'       => $remarks[$checkTime] ?? '',
                            'judgement'     => $row['judge'] ?? null,
                            'specification' => $row['specification'] ?? '',
                            'controlLimit'  => $row['CL'] ?? '',
                            'measurements'  => [
                                '0'  => $row['measurements_y'] ?? [],
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
                }
            }
        }

        foreach ($deletedCheckTimes as $deleted) {
            if (collect($deleted['dimensions'] ?? [])->contains($isGap)) {
                $needsRebuild = true;
            }
        }

        $upserts = $gapRowsFor($touchedCheckTimes);

        if (! $needsRebuild && $upserts === []) {
            return;
        }

        $deletedTimes = array_column($deletedCheckTimes, 'time');
        $allRows = $gapRowsFor(array_values(array_diff($checkTimes, $deletedTimes)));

        $context = [
            'ppf'       => $ppf,
            'partNo'    => $partNo,
            'moldNo'    => $ppfLookUp['moldNo'] ?? '',
            'machineNo' => $ppfLookUp['machineNo'],
            'lotNo'     => $ppfLookUp['productionLotNo'],
            'inspector' => $inspectorNo,
            'spec'      => $allRows[0]['specification'] ?? '',
            'limit'     => $allRows[0]['controlLimit'] ?? '',
        ];

        try {
            if ($isF00VE) {
                $service = app(InsertTightenedGapF00VE::class);

                $needsRebuild
                    ? $service->insertGapF00VEToExcel($context, $allRows)
                    : $service->appendGapF00VEToExcel($context, $upserts, $allRows);
            } else {
                $service = app(InsertTightenedGapOring::class);

                $needsRebuild
                    ? $service->insertGapOringToExcel($context, $allRows)
                    : $service->appendGapOringToExcel($context, $upserts, $allRows);
            }
        } catch (\Throwable $e) {
            Log::error('Tightened Gap-Offset Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);
        }
    }


    private function composeRemarks(string $remarks, array $batches): string
    {
        $base = trim(preg_replace(
            '/\s*' . preg_quote(self::SMALL_DEFECT_MARKER, '/') . '.*$/s',
            '',
            $remarks
        ));

        $parts = [];

        foreach ($batches as $batch) {
            $prefix = $batch['shot'] !== null ? "Shot {$batch['shot']} " : '';

            foreach ($batch['defects'] as $defect) {
                $large = $defect['type'] ?? null;

                if ($large === null) {
                    continue;
                }

                $smalls = collect($batch['smallDefects'][$large] ?? [])
                    ->filter(fn($s) => (float) ($s['qty'] ?? 0) > 0)
                    ->map(fn($s) => "{$s['type']} ({$s['qty']})")
                    ->values();

                if ($smalls->isNotEmpty()) {
                    $parts[] = "{$prefix}{$large}: " . $smalls->implode(', ');
                }
            }
        }

        if ($parts === []) {
            return $base;
        }

        return trim($base . ' ' . self::SMALL_DEFECT_MARKER . ' ' . implode('; ', $parts));
    }

    /**
     * Normal    => one batch with shot = null (the old behaviour).
     * Tightened => one batch per shot, labelled 1, 5, 10, 15 ...
     *
     * @return array<int, array{shot: ?int, defects: array, smallDefects: array}>
     */
    private function defectBatchesFor(
        string $checkTime,
        array $defects,
        array $smallDefects,
        array $modeByTime,
        array $shotsByTime
    ): array {
        if (($modeByTime[$checkTime] ?? 'normal') !== 'tightened') {
            return [[
                'shot'         => null,
                'defects'      => $defects[$checkTime] ?? [],
                'smallDefects' => $smallDefects[$checkTime] ?? [],
            ]];
        }

        return collect($shotsByTime[$checkTime] ?? [])
            ->map(fn(array $shot) => [
                'shot'         => isset($shot['shot']) ? (int) $shot['shot'] : null,
                'defects'      => $shot['defects'] ?? [],
                'smallDefects' => $shot['smallDefects'] ?? [],
            ])
            ->values()
            ->all();
    }
}
