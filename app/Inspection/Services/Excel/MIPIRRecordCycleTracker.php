<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIR\MIPIRLotCompletion;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MIPIRRecordCycleTracker
{
    private const MAX_SLOTS_PER_SHEET = 21; // 7 lots x 3 checktimes — row capacity ng template

    public function checkAndRecordLotComplete(int $ppf, string $partNo): void
    {
        if (trim($partNo) === '') {
            Log::debug('first');
            return;
        }

        DB::connection('mipirDB')->transaction(function () use ($ppf, $partNo) {
            $hasE = MIPIRInspectionRecord::where('PPFNo', $ppf)
                ->where('Checktime', 'E')
                ->exists();
            if (! $hasE) {
                Log::debug('second');
                return;
            }

            $hasGapOffset = MIPIRDimensionMeasure::where('PPFNo', $ppf)
                ->where('DimItem', 'Gap-Offset')
                ->exists();

            if (! $hasGapOffset) {
                Log::debug('third');
                return;
            }

            $completion = MIPIRLotCompletion::query()
                ->where('PPFNo', $ppf)
                ->lockForUpdate()
                ->first();
            if ($completion !== null && $completion->Counted) {
                Log::debug('fourth');
                return;
            }

            MIPIRLotCompletion::updateOrCreate(['PPFNo' => $ppf], ['Counted' => true]);

            $this->tryGenerateIfFull($partNo);
        });
    }

    private function tryGenerateIfFull(string $partNo): void
    {
        $lotNos = MIPIRInspectionRecord::where('PartNo', $partNo)
            ->where('Checktime', 'E')
            ->whereHas('dimensionMeasures', fn($q) => $q->whereNull('isRecord'))
            ->select('ProdLotNo')
            ->selectRaw('MIN(DateJudge) as first_date')
            ->groupBy('ProdLotNo')
            ->orderBy('first_date')
            ->pluck('ProdLotNo');

        if ($lotNos->isEmpty()) {
            return;
        }

        $fittingLots = [];
        $cumulativeSlots = 0;
        $overflowed = false;

        foreach ($lotNos as $lotNo) {
            $entries = MIPIRInspectionRecord::where('PartNo', $partNo)
                ->where('ProdLotNo', $lotNo)
                ->orderBy('DateJudge')
                ->get(['PPFNo', 'Checktime'])
                ->map(fn($r) => ['ppf' => $r->PPFNo, 'checktime' => $r->Checktime])
                ->all();

            $lotSlotCount = $this->estimateLotSlots($entries);

            if ($cumulativeSlots + $lotSlotCount > self::MAX_SLOTS_PER_SHEET) {
                $overflowed = true;
                break;
            }

            $fittingLots[] = $entries;
            $cumulativeSlots += $lotSlotCount;
        }

        if (! $overflowed && $cumulativeSlots < self::MAX_SLOTS_PER_SHEET) {
            Log::debug('MIPIRRecordCycleTracker: sheet not full yet, waiting for more lots', [
                'partNo' => $partNo,
                'cumulativeSlots' => $cumulativeSlots,
                'lotsAccumulated' => count($fittingLots),
            ]);
            return;
        }

        $this->generateReport($partNo, $fittingLots);
    }

    private function estimateLotSlots(array $entries): int
    {
        $slots = count($entries);

        foreach ($entries as $entry) {
            $defect = Defect::where('PPFNo', $entry['ppf'])
                ->where('Checktime', $entry['checktime'])
                ->first();

            if ($defect && (int) $defect->Judgement === 1) {
                $slots++;
            }
        }

        return $slots;
    }

    private function generateReport(string $partNo, array $lots): void
    {
        $allPpfNos = array_filter(array_column(array_merge([], ...$lots), 'ppf'));

        $dimItems = $this->resolveDimItems($partNo);

        if (count($dimItems) < 3 || count($dimItems) > 4) {
            Log::error('MIPIRRecordCycleTracker: unexpected dimItems count, skipping report generation', [
                'partNo' => $partNo,
                'dimItemsCount' => count($dimItems),
                'dimItems' => $dimItems,
            ]);
            return;
        }

        app(InspectionRecordService::class)->generate($lots, $dimItems);

        MIPIRDimensionMeasure::whereIn('PPFNo', $allPpfNos)->update(['isRecord' => true]);
    }

    private function resolveDimItems(string $partNo): array
    {
        $forXBarDim = MIPIRDimensionMeasure::where('PartNo', $partNo)
            ->where('ForXBar', true)
            ->whereNotIn('DimItem', ['Flash Thickness', 'Gap-Offset'])
            ->where('DimItem', 'not like', '% (Y)')
            ->distinct()
            ->value('DimItem');

        if ($forXBarDim === null) {
            return [];
        }

        $otherCustomDims = MIPIRDimensionMeasure::where('PartNo', $partNo)
            ->whereNotIn('DimItem', ['Flash Thickness', 'Gap-Offset', $forXBarDim])
            ->where('DimItem', 'not like', '% (Y)')
            ->distinct()
            ->pluck('DimItem')
            ->all();

        return array_merge(
            [$forXBarDim],
            $otherCustomDims,
            ['Flash Thickness', ['Gap-Offset', 'Gap-Offset (Y)']]
        );
    }
}