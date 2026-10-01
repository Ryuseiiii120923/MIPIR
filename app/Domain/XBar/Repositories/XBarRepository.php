<?php

namespace App\Domain\XBar\Repositories;

use App\Inspection\Models\Dimensions\DimensionMasterForXBar;
use App\Inspection\Models\MIPIRDimensionMeasure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class XBarRepository
{
    public function searchPartNumbers(string $search = ''): Collection
    {
        $query = DimensionMasterForXBar::query()
            ->select('PartNo')
            ->distinct();

        if ($search !== '') {
            $query->where('PartNo', 'like', "%{$search}%");
        }

        return $query->orderBy('PartNo')->limit(50)->get();
    }

    public function searchDimensionsForParts(array $partNos, string $search = ''): Collection
    {
        $query = DimensionMasterForXBar::query()
            ->whereIn('PartNo', $partNos);

        if ($search !== '') {
            $query->where('DimensionName', 'like', "%{$search}%");
        }

        $query->select([
                'DimensionName',
                DB::raw('MIN(Device) as Device'),
                DB::raw('MIN(Specification) as Specification'),
                DB::raw('MIN(LowerLimit) as LowerLimit'),
                DB::raw('MIN(UpperLimit) as UpperLimit'),
            ])
            ->groupBy('DimensionName');

        if (count($partNos) > 1) {
            $query->havingRaw('COUNT(DISTINCT PartNo) = ?', [count($partNos)]);
        }

        return $query->orderBy('DimensionName')->get();
    }

    public function getPartNumbersSharingDimension(string $dimensionName): array
    {
        return DimensionMasterForXBar::where('DimensionName', $dimensionName)
            ->distinct()
            ->pluck('PartNo')
            ->all();
    }

    public function getPartNumbersForTransaction(string $transactionId): array
{
    return MIPIRDimensionMeasure::where('xbarTransaction', $transactionId)
        ->distinct()
        ->pluck('PartNo')
        ->all();
}
}