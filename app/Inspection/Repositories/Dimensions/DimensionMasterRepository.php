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
        return DimensionMaster::where('PartNo', $partNo)->distinct()->pluck('DimensionName');
    }



    public function getMasterSpecification(string $partNo, string $item): ?array
    {
        $query = DimensionMaster::query()->where('PartNo', $partNo);
        $row = (clone $query)->where('DimensionName', $item)->first();

        // dd($row, $partNo, $item);
        if ($row === null) {
            $wanted = $this->normalizeDimensionName($item);

            $row = $query->get()->first(
                fn($r) => $this->normalizeDimensionName((string) $r->DimensionName) === $wanted
            );
        }
        // dd($row);

        if ($row === null) {
            return null;
        }

        return [
            'Device'        => $row->Device,
            'Specification' => $row->Specification,
            'UpperLimit'    => $row->UpperLimit,
            'LowerLimit'    => $row->LowerLimit,
        ];
    }

    private function normalizeDimensionName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $name));
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
