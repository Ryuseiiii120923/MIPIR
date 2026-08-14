<?php

namespace App\Inspection\Repositories\Dimensions;

use App\Inspection\Models\Dimensions\DimensionMaster;
use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;
use Illuminate\Support\Collection;

class DimensionMasterRepository implements DimensionMasterRepositoryInterface
{
    public function getDimensionName(string $partNo) : Collection
    {
        return DimensionMaster::where('PartNo', $partNo)->distinct()->pluck('DimensionName');
    }

    public function getSpecification(string $partNo, string $dimensionName): ?string
    {
        $result = DimensionMaster::select(['Specification', 'UpperLimit', 'LowerLimit'])
            ->where('PartNo', $partNo)
            ->where('DimensionName', $dimensionName)
            ->first();

        if (!$result) {
            return null;
        }

        if ($result->UpperLimit === '0.000') {
            return 'Max ' . $result->Specification;
        }

        if ($result->LowerLimit === '0.000') {
            return ($result->Specification - $result->LowerLimit) . '-' . $result->Specification;
        }

        $lowerLimit = $result->Specification - $result->LowerLimit;
        $upperLimit = $result->Specification + $result->UpperLimit;

        return $lowerLimit . '-' . $upperLimit;
    }

    public function search(string $term, string $partNo): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        return DimensionMaster::where('PartNo', $partNo)
        ->where('DimensionName','like', "%{$term}%")
        ->limit(8)
        ->orderBy('DimensionName')
        ->distinct()
        ->pluck('DimensionName')
        ->all();
    }
}
