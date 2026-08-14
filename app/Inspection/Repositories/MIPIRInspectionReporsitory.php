<?php

namespace App\Inspection\Repositories;

use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\Defect;


class MIPIRInspectionReporsitory
{
    public function createInspectionRecord(array $data): MIPIRInspectionRecord
    {
        return MIPIRInspectionRecord::create([
            'PPFNo' => $data['PPFNo'],
            'PartNo' => $data['PartNo'],
            'MDNo' => $data['MDNo'],
            'NoofCavity' => $data['NoofCavity'],
            'NQR' => $data['NQR'],
            'ProdLotNo' => $data['ProdLotNo'],
            'MachineNo' => $data['MachineNo'],
            'Checktime' => $data['Checktime'],
            'DateJudge' => $data['DateJudge'],
            'InspectBy' => $data['InspectBy'],
            'Judgement' => $data['Judgement'],
            'Year' => $data['Year'],
        ]);
    }

    public function createDimensionMeasure(array $data): MIPIRDimensionMeasure
    {
        logger('Creating Dimension Measure', ['data' => $data]);
        return MIPIRDimensionMeasure::create([
            'PPFNo' => $data['PPFNo'],
            'MDNo' => $data['MDNo'],
            'PartNo' => $data['PartNo'],
            'ProdLotNo' => $data['ProdLotNo'],
            'MachineNo' => $data['MachineNo'],
            'Checktime' => $data['Checktime'],
            'Specs' => $data['Specs'],
            'DimItem' => $data['DimItem'],
            'Judge' => $data['Judge'] ?? null,
            'Value1' => $data['1'] ?? null,
            'Value2' => $data['2'] ?? null,
            'Value3' => $data['3'] ?? null,
            'Value4' => $data['4'] ?? null,
            'Value5' => $data['5'] ?? null,
        ]);
    }

    public function createDefect(array $data): Defect
    {
        return Defect::create([
            'PPFNo' => $data['PPFNo'],
            'PartNo' => $data['PartNo'],
            'MDNo' => $data['MDNo'],
            'ProdLotNo' => $data['ProdLotNo'],
            'MachineNo' => $data['MachineNo'],
            'Checktime' => $data['Checktime'],
            'Defect' => $data['Defect'],
            'Qty' => $data['Qty']
        ]);
    }
}
