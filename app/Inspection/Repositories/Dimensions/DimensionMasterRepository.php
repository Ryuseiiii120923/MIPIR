<?php

namespace App\Inspection\Repositories\Dimensions;

use App\Inspection\Models\Dimensions\DimensionMaster;
use App\Inspection\Models\Dimensions\DimensionMasterForXBar;
use App\Inspection\Models\Dimensions\TempDimension;
use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DimensionMasterRepository implements DimensionMasterRepositoryInterface
{
    public function getDimensionName(string $partNo): Collection
    {
        return DimensionMasterForXBar::where('PartNo', $partNo)->distinct()->pluck('DimensionName');
    }

    

    public function getMasterSpecification(string $partNo, string $item): ?array
    {
        $row = DimensionMasterForXBar::query()
            ->where('PartNo', $partNo)
            ->where('DimensionName', $item)
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'Device' => $row->Device,
            'Specification' => $row->Specification,
            'UpperLimit'    => $row->UpperLimit,
            'LowerLimit'    => $row->LowerLimit,
        ];
    }

     public function getTempMaster(string $partNo, string $item): ?array
    {
        $row = TempDimension::query()
            ->where('PartNo', $partNo)
            ->where('DimensionName', $item)
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'Device' => $row->Device,
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

        return DimensionMasterForXBar::where('PartNo', $partNo)
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
        string $device,
        string $specification,
        string $upperLimit,
        string $lowerLimit,
        
    ): void {
        $row = [
            'PartNo' => $partNo,
            'DimensionName' => $item,
            'Device' => $device,
            'Specification' => $specification,
            'UpperLimit' => $upperLimit,
            'LowerLimit' => $lowerLimit,
            'created_at' => Carbon::now()
        ];

        TempDimension::upsert(
            $row,
            ['PartNo', 'DimensionName'],
            [
                'Specification',
                'UpperLimit',
                'LowerLimit',
                'Device'
            ]
        );
    }
}
