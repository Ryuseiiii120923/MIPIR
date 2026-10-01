<?php

namespace App\Inspection\Repositories\Excel;

use App\Domain\Master\SEIHIN;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Dimensions\DimensionMaster;
use App\Inspection\Models\Dimensions\DimensionMasterForXBar;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\XBar\ControlSpecsLimit;
use App\Inspection\Repositories\SpecsControlRepository;

class ExcelDataRepository
{
    public function getMainData(string $partNo, string $dimension)
    {
        $records = MIPIRInspectionRecord::where('PartNo', $partNo)->get();
        $ppfLookUp = $records->first();

        $dimMaster = DimensionMasterForXBar::where('PartNo', $ppfLookUp['PartNo'])
            ->where('DimensionName', $dimension)
            ->first();

        $checkTimes = CheckTime::where('PartNo', $ppfLookUp['PartNo'])
            ->where('MachineNo', $ppfLookUp['MachineNo'])
            ->get()
            ->pluck('Checktime')
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

    public function getHeaderforXbar(string $partNo, string $dimension)
    {
        $mainRec = $this->getMainData($partNo, $dimension);
        $partNo = $mainRec['partNo'];
        $seihin = SEIHIN::select('材料名', '品名')->where('品番', $partNo)->first();
        $moldNo = $mainRec['mdNo'];
        $matNo = $seihin->材料名;
        $partName = $seihin->品名;
        $process = 'IN PROCESS';

        $dimItem = MIPIRDimensionMeasure::where('PartNo', $partNo)
            ->where('MachineNo', $mainRec['machineNo'])
            ->where('DimItem', $dimension)
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

    public function getMeasurement(array $partNo, string $dimension, string $transactionId)
    {
        return MIPIRDimensionMeasure::whereIn('PartNo', $partNo)
            ->where('DimItem', $dimension)
            ->where('xbarTransaction', $transactionId)
            ->select([
                'RecNo',       // <-- adjust to your actual PK column name if different from RecNo
                'PPFNo',
                'ProdLotNo',
                'MachineNo',
                'Checktime',
                'Set',
                'Value1',
                'Value2',
                'Value3',
                'Value4',
                'Value5',
                'InspectedBy'
            ])
            ->orderBy('RecNo')   // earliest-encoded first
            ->orderBy('Set')
            ->get();
    }

    public function getLimit(string $dimension){
        return ControlSpecsLimit::where('DimItem', $dimension)->first();
    }

    public function getSpecs(string $dimension){
        return DimensionMasterForXBar::where('DimensionName', $dimension)->first();
    }

    public function getHeaderforRec(string $ppf) {
        return MIPIRInspectionRecord::where('PPFNo', $ppf);
    }
}
