<?php

namespace App\Inspection\Services;

use App\Domain\XBar\Repositories\XBarRepository;
use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Models\XBarCycleProgress;
use App\Inspection\Services\Excel\ExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XBarCycleTracker
{
    private const CYCLE_SIZE = 25;

    public function recordSubgroup(string $plant, string $dimensionName, ?string $encodedAt = null): array
    {
        $encodedAt = $encodedAt ?? now()->toDateTimeString();

        $shouldGenerate = false;
        $cycleStart = '';
        $cycleEnd = '';
        $transactionId = '';

        DB::connection('mipirDB')->transaction(function () use ($plant, $dimensionName, $encodedAt, &$shouldGenerate, &$cycleStart, &$cycleEnd, &$transactionId) {
            /** @var XBarCycleProgress $progress */
            $progress = XBarCycleProgress::query()
                ->where('Plant', $plant)
                ->where('DimensionName', $dimensionName)
                ->lockForUpdate()
                ->firstOrNew([
                    'Plant' => $plant,
                    'DimensionName' => $dimensionName,
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

    public function closeCycle(string $plant, string $dimensionName, string $cycleStart, string $cycleEnd, string $transactionId): void
    {
        XBarCycleLog::create([
            'Plant'         => $plant,
            'DimensionName' => $dimensionName,
            'StartDatetime' => $cycleStart,
            'EndDatetime'   => $cycleEnd,
            'FilePath'      => null,
            'TransactionId' => $transactionId,
        ]);

        XBarCycleProgress::where('Plant', $plant)->where('DimensionName', $dimensionName)->delete();
    }

    public function reverseSubgroup(string $plant, string $dimensionName): void
    {
        DB::connection('mipirDB')->transaction(function () use ($plant, $dimensionName) {
            /** @var XBarCycleProgress|null $progress */
            $progress = XBarCycleProgress::query()
                ->where('Plant', $plant)
                ->where('DimensionName', $dimensionName)
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
}
