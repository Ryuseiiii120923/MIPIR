<?php

namespace App\Inspection\Actions;

use App\Domain\XBar\Repositories\XBarRepository;
use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\EncodingDuration;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Repositories\MIPIRInspectionReporsitory;
use App\Inspection\Services\Excel\InsertTightenedDefects;
use App\Inspection\Services\Excel\InsertTightenedFlash;
use App\Inspection\Services\Excel\InsertTightenedGapF00VE;
use App\Inspection\Services\Excel\InsertTightenedGapOring;
use App\Inspection\Services\Excel\InspectionXBarService;
use App\Inspection\Services\Excel\MipirRecordRefresher;
use App\Inspection\Services\Excel\MIPIRRecordCycleTracker;
use App\Inspection\Services\LotConfirmationService;
use App\Inspection\Services\Saving\CreateInspectionService;
use App\Inspection\Services\XBarCycleTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $inspectorNo = Auth::user()->InspectorNo ?? null;
        $xbarJobs = [];

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

        DB::transaction(function () use ($composedRemarks, $dateEncodeCheck, $defectBatches, $defectJudge, $inspectorNo, $ngpercent, $ppf, $ppfLookUp, $touchedCheckTimes, $dimensions, $dateJudge, $remarks, &$xbarJobs) {
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

                // Ang Gap-Offset ay sa ibang page ine-encode. Kapag wala ito sa draft na ito,
                // huwag burahin ang Gap-Offset rows na nai-save na ng gap-offset page.
                $draftItems = collect($dimensions[$checkTime] ?? [])->pluck('item')->filter()->all();
                $keepGapOffset = ! in_array('Gap-Offset', $draftItems, true);

                MIPIRDimensionMeasure::where('PPFNo', $ppf)
                    ->where('Checktime', $checkTime)
                    ->when($keepGapOffset, fn($q) => $q->where('DimItem', 'not like', 'Gap-Offset%'))
                    ->delete();
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

                        if ($isXBarTracked && $itemName && $forXBar && $transactionId) {
                            if ($xbarResult !== null && $xbarResult['shouldGenerate']) {
                                app(XBarCycleTracker::class)->closeCycle(
                                    Auth::user()->Plant,
                                    $itemName,
                                    $xbarResult['cycleStart'],
                                    $xbarResult['cycleEnd'],
                                    $transactionId
                                );
                            }

                            // Isang Excel bawat cycle, kahit ilang set o check time ang nasa loob
                            $xbarJobs["{$itemName}|{$transactionId}"] = [
                                'dimension'     => $itemName,
                                'transactionId' => $transactionId,
                            ];
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
        $this->refreshTightenedDefectsExcel($ppf, $ppfLookUp, $inspectorNo, $checkTimes, $modeByTime, $shotsByTime);
        app(MipirRecordRefresher::class)->refresh($ppf);
        $this->refreshXBarExcel($xbarJobs, Auth::user()->Plant ?? null);

        if ($recordDuration) {
            foreach ($touchedCheckTimes as $checkTime) {
                $existing = EncodingDuration::where('PPFNo', $ppf)
                    ->where('Checktime', $checkTime)
                    ->first();

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

    private function refreshTightenedDefectsExcel(
        int $ppf,
        array $ppfLookUp,
        $inspectorNo,
        array $checkTimes,
        array $modeByTime,
        array $shotsByTime
    ): void {
        $cavity = (float) ($ppfLookUp['noOfCavity'] ?? 0);
        $nqr    = (float) ($ppfLookUp['nqr'] ?? 0);

        // Lahat ng natitirang check time ng PPF, para laging buo ang column ng lot
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

                $ng  = collect($shot['defects'] ?? [])->sum(fn($d) => (float) ($d['qty'] ?? 0));
                $pct = $cavity > 0 ? ($ng / $cavity) * 100 : 0;

                // Kung pareho ang shot sa dalawang check time, X ang nananaig
                $judge = $pct > $nqr ? 'X' : 'O';
                $judgements[$label] = ($judgements[$label] ?? 'O') === 'X' ? 'X' : $judge;
            }
        }

        ksort($judgements);

        // May check time ba sa PPF na ito na naka-tightened? Kung oo, siguraduhing may file na.
        $hasTightened = collect($checkTimes)
            ->contains(fn($t) => ($modeByTime[$t] ?? 'normal') === 'tightened');

        try {
            app(InsertTightenedDefects::class)->sync([
                'partNo'    => $ppfLookUp['partNo'] ?? '',
                'partName'  => $ppfLookUp['partName'] ?? '',
                'machineNo' => $ppfLookUp['machineNo'] ?? '',
                'moldNo'    => $ppfLookUp['moldNo'] ?? '',
                'lotNo'     => $ppfLookUp['productionLotNo'] ?? '',
                'inspector' => $inspectorNo,
            ], $judgements, ensureFile: $hasTightened);
        } catch (\Throwable $e) {
            Log::error('Tightened defects SAIR Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function refreshXBarExcel(array $jobs, ?string $plant): void
    {
        Log::info('XBar refresh', ['plant' => $plant, 'jobs' => array_values($jobs)]);

        if ($plant === null) {
            Log::warning('XBar Excel skipped: user has no Plant');
            return;
        }

        $safe = fn(string $v) => preg_replace('/[^A-Za-z0-9_-]/', '_', $v);

        foreach ($jobs as $job) {
            try {
                $window = app(XBarCycleTracker::class)
                    ->cycleWindow($plant, $job['dimension'], $job['transactionId']);

                if ($window === null) {
                    Log::warning('XBar Excel skipped: cycle window not found', $job);
                    continue;
                }

                $partNos = collect(app(XBarRepository::class)->getPartNumbersForTransaction($job['transactionId']))
                    ->values()
                    ->all();

                if ($partNos === []) {
                    Log::warning('XBar Excel skipped: no part numbers for transaction', $job);
                    continue;
                }

                $name = sprintf(
                    'Xbar_%s_%s_%s',
                    $safe($plant),
                    $safe($job['dimension']),
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

                XBarCycleLog::where('TransactionId', $job['transactionId'])->update(['FilePath' => $path]);
            } catch (\Throwable $e) {
                Log::error('XBar Excel failed', [
                    'dimension'     => $job['dimension'],
                    'transactionId' => $job['transactionId'],
                    'error'         => $e->getMessage(),
                    'at'            => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }
    }
}
