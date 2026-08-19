<?php

namespace App\Inspection\Repositories\Dimensions;

use App\Inspection\Models\Dimensions\DimensionMaster;
use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;
use Illuminate\Support\Collection;

class DimensionMasterRepository implements DimensionMasterRepositoryInterface
{
    public function getDimensionName(string $partNo): Collection
    {
        return DimensionMaster::where('PartNo', $partNo)->distinct()->pluck('DimensionName');
    }

    public function getMasterSpecification(string $partNo, string $item): ?array
    {
        $row = DimensionMaster::query()
            ->where('PartNo', $partNo)
            ->where('DimensionName', $item)
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'Specification' => $row->Specification,
            'UpperLimit'    => $row->UpperLimit,
            'LowerLimit'    => $row->LowerLimit,
        ];
    }


    public function search(string $term, string $partNo): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        return DimensionMaster::where('PartNo', $partNo)
            ->where('DimensionName', 'like', "%{$term}%")
            ->limit(8)
            ->orderBy('DimensionName')
            ->distinct()
            ->pluck('DimensionName')
            ->all();
    }

    public function updateOrCreateSpecification(
        string $partNo,
        string $item,
        string $specification,
        string $upperLimit,
        string $lowerLimit
    ): void {
        $row = [
            'PartNo' => $partNo,
            'DimensionName' => $item,
            'Specification' => $specification,
            'UpperLimit' => $upperLimit,
            'LowerLimit' => $lowerLimit
        ];

        DimensionMaster::upsert(
            $row,
            ['PartNo', 'DimensionName'],
            [
                'Specification',
                'UpperLimit',
                'LowerLimit',
            ]
        );
    }
}
