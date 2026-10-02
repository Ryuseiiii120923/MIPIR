<?php

namespace App\Inspection\Repositories;

use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\Defect;
use App\Inspection\Models\SmallDefect;
use Illuminate\Log\Logger;

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
            'Year' => $data['Year'],
            'MoldingOperator' => $data['MoldingOperator'] ?? null
        ]);
    }

    public function createDimensionMeasure(array $data): MIPIRDimensionMeasure
    {
        
        return MIPIRDimensionMeasure::create([
            'PPFNo' => $data['PPFNo'],
            'MDNo' => $data['MDNo'],
            'PartNo' => $data['PartNo'],
            'ProdLotNo' => $data['ProdLotNo'],
            'MachineNo' => $data['MachineNo'],
            'Checktime' => $data['Checktime'],
            'Mode' => $data['Mode'],
            'CL' => $data['CL'],
            'Set' => $data['Set'],
            'ForXBar' => $data['forXBar'] ?? false,
            'Specs' => $data['Specs'],
            'DimItem' => $data['DimItem'],
            'Judge' => $data['Judge'] ?? null,
            'xbarTransaction' => $data['xbarTransaction'] ?? null,
            'Value1' => $data['1'] ?? null,
            'Value2' => $data['2'] ?? null,
            'Value3' => $data['3'] ?? null,
            'Value4' => $data['4'] ?? null,
            'Value5' => $data['5'] ?? null,
            'InspectedBy' => $data['InspectedBy']
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
            'Qty' => $data['Qty'],
            'NGPercent' => $data['NGPercent'],
            'Judgement' => $data['Judgement']
        ]);
    }

    public function createSmall(array $data)
    {
        return SmallDefect::create([
            'PPFNo' => $data['PPFNo'],
            'Checktime' => $data['Checktime'],
            'largeDefect' => $data['largeDefect'],
            'smallDefect' => $data['smallDefect'],
            'qty' => $data['qty'],
        ]);
    }

    public function saveCheckTime(array $data): CheckTime
    {
        return CheckTime::create([
            'PPFNo' => $data['PPFNo'],
            'PartNo' => $data['PartNo'],
            'Checktime' => $data['Checktime'],
            'DateEncode' => $data['DateEncode'],
            'MachineNo' => $data['MachineNo']
        ]);
    }

    public function saveRemarksTime(array $data): ChckTRemarks
    {
        return ChckTRemarks::create([
            'PPFNo' => $data['PPFNo'],
            'PartNo' => $data['PartNo'],
            'MachineNo' => $data['MachineNo'],
            'CheckTime' => $data['CheckTime'],
            'ProdLotNo' => $data['ProdLotNo'],
            'Remarks' => $data['Remarks']
        ]);
    }
}
