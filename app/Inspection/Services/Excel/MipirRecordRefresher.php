<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\CheckTime;
use App\Inspection\Models\MIPIRInspectionRecord;
use Illuminate\Support\Facades\Log;

class MipirRecordRefresher
{
    public function refresh(int $ppf): ?string
    {
        try {
            $recorded = MIPIRInspectionRecord::where('PPFNo', $ppf)->get(['PartNo', 'Checktime']);

            if ($recorded->isEmpty()) {
                return null;
            }

            $partNo = trim((string) $recorded->first()->PartNo);
            $times  = $recorded->pluck('Checktime')->unique()->values()->all();

            $order = CheckTime::where('PPFNo', $ppf)->orderBy('DateEncode')->pluck('Checktime')->all();
            $times = array_values(array_merge(
                array_intersect($order, $times),
                array_diff($times, $order)
            ));

            $dimItems = app(MIPIRRecordCycleTracker::class)->resolveDimItems($partNo);

            if (count($dimItems) < 3 || count($dimItems) > 4) {
                Log::warning('MIPIR record skipped: unsupported number of dimension items', [
                    'PPFNo'    => $ppf,
                    'partNo'   => $partNo,
                    'dimItems' => $dimItems,
                ]);

                return null;
            }

            $lots = [
                array_map(fn (string $t) => ['ppf' => (string) $ppf, 'checktime' => $t], $times),
            ];

            return app(InspectionRecordService::class)->generate($lots, $dimItems);
        } catch (\Throwable $e) {
            // Hindi dapat mabigo ang pag-save dahil sa Excel
            Log::error('MIPIR record refresh failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}