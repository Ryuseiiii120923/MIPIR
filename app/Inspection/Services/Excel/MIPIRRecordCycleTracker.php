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
    private const MAX_SLOTS_PER_SHEET = 21;
    private const FOUR_COLUMN_PARTS = ['135659-0010'];

    public function variantFor(string|array $partNo): int
    {
        foreach ((array) $partNo as $p) {
            if (in_array(strtoupper(trim($p)), self::FOUR_COLUMN_PARTS, true)) {
                return 4;
            }
        }

        return 3;
    }

    public function checkAndRecordLotComplete(int $ppf, string $partNo): void
    {
        if (trim($partNo) === '') {
            return;
        }

        DB::connection('mipirDB')->transaction(function () use ($ppf, $partNo) {
            $hasE = MIPIRInspectionRecord::where('PPFNo', $ppf)
                ->where('Checktime', 'E')
                ->exists();
            if (! $hasE) {
                return;
            }

            $hasGapOffset = MIPIRDimensionMeasure::where('PPFNo', $ppf)
                ->where('DimItem', 'Gap-Offset')
                ->exists();

            if (! $hasGapOffset) {
                return;
            }

            $completion = MIPIRLotCompletion::query()
                ->where('PPFNo', $ppf)
                ->lockForUpdate()
                ->first();
            if ($completion !== null && $completion->Counted) {
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

        if ($dimItems === []) {
            Log::error('MIPIRRecordCycleTracker: no dimItems, skipping report generation', ['partNo' => $partNo]);
            return;
        }

        app(InspectionRecordService::class)->generate($lots, $dimItems, null, $this->variantFor($partNo));

        MIPIRDimensionMeasure::whereIn('PPFNo', $allPpfNos)->update(['isRecord' => true]);
    }

    public function resolveDimItems(string|array $partNo, array|int|null $ppfs = null): array
    {
        $rows = $this->dimQuery($partNo, $ppfs)
            ->where('DimItem', 'not like', '% (Y)')
            ->orderBy('RECNO')
            ->get(['PPFNo', 'Checktime', 'DimItem', 'RowNo', 'ForXBar']);

        if ($rows->isEmpty()) {
            return [];
        }

        $fixed    = ['Flash Thickness', 'Gap-Offset'];
        $hasFlash = $rows->contains('DimItem', 'Flash Thickness');
        $hasGap   = $rows->contains('DimItem', 'Gap-Offset');
        $custom   = $rows->reject(fn($r) => in_array($r->DimItem, $fixed, true));

        $names = $custom->pluck('DimItem')->unique()->values();
        $xbar  = $custom->first(fn($r) => (bool) $r->ForXBar)?->DimItem;

        if ($xbar !== null) {
            $names = $names->reject(fn($n) => $n === $xbar)->prepend($xbar)->values();
        }

        $nameLimit = $this->variantFor($partNo) === 4 ? 2 : 1;

        $entries = [];

        foreach ($names->take($nameLimit) as $name) {
            $copies = $custom->where('DimItem', $name)
                ->groupBy(fn($r) => $r->PPFNo . '|' . $r->Checktime)
                ->map(fn($g) => $g->map(fn($r) => $r->RowNo ?? 0)->unique()->count())
                ->max() ?? 1;

            for ($n = 0; $n < $copies; $n++) {
                $entries[] = ['item' => $name, 'nth' => $n];
            }
        }

        $capacity = 4 - (int) $hasFlash - (int) $hasGap;

        if (count($entries) > $capacity) {
            Log::warning('MIPIRRecordCycleTracker: too many dimension columns, extras dropped', [
                'partNo' => $partNo,
                'needed' => count($entries),
                'capacity' => $capacity,
            ]);
            $entries = array_slice($entries, 0, $capacity);
        }

        if ($hasFlash) {
            $entries[] = 'Flash Thickness';
        }

        if ($hasGap) {
            $entries[] = ['Gap-Offset', 'Gap-Offset (Y)'];
        }

        return $entries;
    }

    private function dimQuery(string|array $partNo, array|int|null $ppfs)
    {
        return MIPIRDimensionMeasure::query()
            ->whereIn('PartNo', (array) $partNo)
            ->when($ppfs !== null, fn($q) => $q->whereIn('PPFNo', (array) $ppfs));
    }
}
