<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use Illuminate\Support\Collection;

class MipirSourceData
{
    public function header(int $ppf): ?array
    {
        $rows = MIPIRInspectionRecord::where('PPFNo', $ppf)->orderBy('RECNO')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $latest = $rows->last();
        $remarks = [];

        foreach ($rows as $row) {
            $remarks[(string) $row->Checktime] = (string) ($row->Remarks ?? '');
        }

        return [
            'partNo' => trim((string) $latest->PartNo),
            'partName' => '',
            'moldNo' => (string) $latest->MDNo,
            'machineNo' => (string) $latest->MachineNo,
            'lotNo' => (string) $latest->ProdLotNo,
            'inspector' => (string) $latest->InspectBy,
            'cavity' => (float) $latest->NoofCavity,
            'nqr' => (float) $latest->NQR,
            'checkTimes' => array_map('strval', array_keys($remarks)),
            'remarks' => $remarks,
        ];
    }

    public function defects(int $ppf): Collection
    {
        return Defect::where('PPFNo', $ppf)
            ->orderBy('Checktime')
            ->orderBy('Shot')
            ->orderBy('RECNO')
            ->get();
    }

    public function smallDefects(int $ppf): Collection
    {
        return SmallDefect::where('PPFNo', $ppf)
            ->orderBy('Checktime')
            ->orderBy('Shot')
            ->orderBy('RECNO')
            ->get();
    }

    public function dimensions(int $ppf, string $dimItem): Collection
    {
        return MIPIRDimensionMeasure::where('PPFNo', $ppf)
            ->where('DimItem', $dimItem)
            ->orderBy('Checktime')
            ->orderBy('Set')
            ->orderBy('RECNO')
            ->get();
    }

    public function measurements($row): array
    {
        $values = [];

        for ($i = 1; $i <= 5; $i++) {
            $value = $row->{"Value{$i}"};

            if ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    public function isTightened($row): bool
    {
        return strcasecmp(trim((string) $row->Mode), 'tightened') === 0;
    }
}