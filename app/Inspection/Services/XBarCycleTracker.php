<?php

namespace App\Inspection\Services;

use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Models\XBarCycleProgress;
use App\Inspection\Support\PartNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class XBarCycleTracker
{
    private const CYCLE_SIZE = 25;

    public static function groupKey(string $partNo, ?string $mdNo): string
    {
        return PartNumber::base($partNo) . '|' . trim((string) $mdNo);
    }

    public function recordSubgroup(string $plant, string $dimensionName, string $groupKey, ?string $encodedAt = null): array
    {
        $encodedAt = $encodedAt ?? now()->toDateTimeString();

        $shouldGenerate = false;
        $cycleStart = '';
        $cycleEnd = '';
        $transactionId = '';

        DB::connection('mipirDB')->transaction(function () use ($plant, $dimensionName, $groupKey, $encodedAt, &$shouldGenerate, &$cycleStart, &$cycleEnd, &$transactionId) {
            /** @var XBarCycleProgress $progress */
            $progress = XBarCycleProgress::query()
                ->where('Plant', $plant)
                ->where('DimensionName', $dimensionName)
                ->where('GroupKey', $groupKey)
                ->lockForUpdate()
                ->firstOrNew([
                    'Plant' => $plant,
                    'DimensionName' => $dimensionName,
                    'GroupKey' => $groupKey,
                ], [
                    'Count'      => 0,
                    'CycleStart' => null,
                    'TransactionId' => null,
                ]);

            if ((int) $progress->Count === 0) {
                $progress->CycleStart = $encodedAt;
                $progress->TransactionId = (string) Str::uuid();
            }

            $transactionId = $progress->TransactionId;

            $progress->Count = (int) $progress->Count + 1;

            if ($progress->Count >= self::CYCLE_SIZE) {
                $cycleStart = (string) $progress->CycleStart;
                $cycleEnd = $encodedAt;
                $shouldGenerate = true;

                $progress->Count = 0;
                $progress->CycleStart = null;
                $progress->TransactionId = null;
            }

            $progress->save();
        });

        return [
            'transactionId'  => $transactionId,
            'shouldGenerate' => $shouldGenerate,
            'cycleStart'     => $cycleStart,
            'cycleEnd'       => $cycleEnd,
        ];
    }

    public function closeCycle(string $plant, string $dimensionName, string $groupKey, string $cycleStart, string $cycleEnd, string $transactionId): void
    {
        XBarCycleLog::create([
            'Plant'         => $plant,
            'DimensionName' => $dimensionName,
            'GroupKey'      => $groupKey,
            'StartDatetime' => $cycleStart,
            'EndDatetime'   => $cycleEnd,
            'FilePath'      => null,
            'TransactionId' => $transactionId,
        ]);

        XBarCycleProgress::where('Plant', $plant)
            ->where('DimensionName', $dimensionName)
            ->where('GroupKey', $groupKey)
            ->delete();
    }

    public function reverseSubgroup(string $plant, string $dimensionName, string $groupKey): void
    {
        DB::connection('mipirDB')->transaction(function () use ($plant, $dimensionName, $groupKey) {
            /** @var XBarCycleProgress|null $progress */
            $progress = XBarCycleProgress::query()
                ->where('Plant', $plant)
                ->where('DimensionName', $dimensionName)
                ->where('GroupKey', $groupKey)
                ->lockForUpdate()
                ->first();

            if ($progress === null) {
                return;
            }

            $newCount = (int) $progress->Count - 1;

            if ($newCount <= 0) {
                $progress->delete();
                return;
            }

            $progress->Count = $newCount;
            $progress->save();
        });
    }

    public function cycleWindow(string $plant, string $dimensionName, string $transactionId): ?array
    {
        $progress = XBarCycleProgress::where('Plant', $plant)
            ->where('DimensionName', $dimensionName)
            ->where('TransactionId', $transactionId)
            ->first();

        if ($progress !== null && $progress->CycleStart !== null) {
            return ['start' => (string) $progress->CycleStart, 'end' => now()->toDateTimeString()];
        }

        $log = XBarCycleLog::where('TransactionId', $transactionId)->first();

        return $log
            ? ['start' => (string) $log->StartDatetime, 'end' => (string) $log->EndDatetime]
            : null;
    }

    public function resolveGroupKey(string $plant, string $dimensionName, string $partNo, ?string $mdNo): string
    {
        $default = self::groupKey($partNo, $mdNo);

        $open = XBarCycleProgress::query()
            ->where('Plant', $plant)
            ->where('DimensionName', $dimensionName)
            ->get(['GroupKey', 'TransactionId']);

        if ($open->contains('GroupKey', $default)) {
            return $default;
        }

        $mdSuffix = '|' . trim((string) $mdNo);
        $family   = app(PartFamily::class);

        foreach ($open as $row) {
            if (! str_ends_with((string) $row->GroupKey, $mdSuffix) || ! $row->TransactionId) {
                continue;
            }

            $same = MIPIRDimensionMeasure::where('xbarTransaction', $row->TransactionId)
                ->distinct()
                ->pluck('PartNo')
                ->contains(fn($p) => $family->sameNotation((string) $p, $partNo));

            if ($same) {
                return $row->GroupKey;
            }
        }

        return $default;
    }

    public function reverseSubgroupByTransaction(string $transactionId): void
    {
        DB::connection('mipirDB')->transaction(function () use ($transactionId) {
            $progress = XBarCycleProgress::where('TransactionId', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($progress === null) {
                return;
            }

            $newCount = (int) $progress->Count - 1;

            if ($newCount <= 0) {
                $progress->delete();
                return;
            }

            $progress->Count = $newCount;
            $progress->save();
        });
    }
}
