<?php

namespace App\Inspection\Repositories\Excel;

use App\Domain\Master\SEIHIN;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Dimensions\DimensionMaster;
use App\Inspection\Models\Dimensions\DimensionMasterForXBar;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;

class ExcelDataRepository
{

    public function getMainData(int $ppf)
    {
        $records = MIPIRInspectionRecord::where('PPFNo', $ppf)->get();
        $ppfLookUp = $records->first();
        $dimMaster = DimensionMasterForXBar::where('PartNo', $ppfLookUp['PartNo'])->first();

        $checkTimes = CheckTime::where('PPFNo', $ppf)
            ->where('machine-no', $ppfLookUp['MachineNo'])
            ->get()
            ->pluck('check-time')
            ->all();

        return [
            'partNo' => $ppfLookUp['PartNo'],
            'mdNo' => $ppfLookUp['MDNo'],
            'nqr' => $ppfLookUp['NQR'],
            'noOfCav' => $ppfLookUp['NoofCavity'],
            'lotNo' => $ppfLookUp['ProdLotNo'],
            'checkTime' => $checkTimes,
            'dateJudge' => $ppfLookUp['DateJudge'],
            'machineNo' => $ppfLookUp['MachineNo'],
            'specification' => $dimMaster['Specification'],
            'upper' => $dimMaster['UpperLimit'],
            'lower' => $dimMaster['LowerLimit'],
            'device' => $dimMaster['Device'],
            'prodLotNo' => $dimMaster['ProdLotNo']
        ];
    }
    public function getHeaderforXbar(int $ppf)
    {
        $mainRec = $this->getMainData($ppf);
        $partNo = $mainRec['partNo'];
        $seihin = SEIHIN::select('材料名', '品名')->where('品番', $partNo)->first();
        $moldNo = $mainRec['mdNo'];
        $matNo = $seihin->材料名;
        $partName = $seihin->品名;
        $process = 'IN PROCESS';
        $dimItem = MIPIRDimensionMeasure::where('PPFNo', $ppf)
            ->where('MachineNo', $mainRec['machineNo'])
            ->whereNotIn('DimItem', ['Flash Thickness', 'Gap-Offset'])
            ->pluck('DimItem')
            ->first();

        if ($mainRec['upper'] === $mainRec['lower']) {
            $specs = $mainRec['specification'] . ' ± ' . $mainRec['upper'];
        } elseif ($mainRec['upper'] === 0.000) {
            $specs = $mainRec['specification'] . 'Min' . $mainRec['lower'];
        } else {
            $specs = $mainRec['specification'] . 'Max' . $mainRec['upper'];
        }

        return [
            'partName' => $partName,
            'partNo' => $partNo,
            'moldNo' => $moldNo,
            'matNo' => $matNo,
            'process' => $process,
            'dimItem' => $dimItem,
            'specs' => $specs,
            'device' => $mainRec['device'],
            'prodLotNo' => $mainRec['prodLotNo'],
            'machineNo' => $mainRec['machineNo'],
        ];
    }

public function getMeasurement(int $ppf)
{
    return MIPIRDimensionMeasure::where('PPFNo', $ppf)
        ->select([
            'ProdLotNo',
            'MachineNo',
            'Checktime',
            'Set',
            'Value1',
            'Value2',
            'Value3',
            'Value4',
            'Value5',
        ])
        ->orderBy('MachineNo')
        ->orderBy('ProdLotNo')
        ->orderBy('Checktime')
        ->orderBy('Set')
        ->get();
}    
public function getHeaderforRec($ppf) {}
}
