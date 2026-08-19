<?php

namespace App\Inspection\Repositories\Excel;

use App\Domain\Master\SEIHIN;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\MIPIRInspectionRecord;

class ExcelDataRepository
{
    public function getHeaderforXbar(int $ppf)
    {
        $mainRec = $this->getMainData($ppf);
        $partNo = $mainRec['partNo'];
        $seihin = SEIHIN::select('材料名', '品名')->where('品番', $partNo)->first();
        $moldNo = $mainRec['mdNo'];
        $matNo = $seihin->材料名;
        $process = 'IN PROCESS';
    }

    public function getMainData(int $ppf)
    {
        $records = MIPIRInspectionRecord::where('PPFNo', $ppf)->get();
        $checkTime = CheckTime::where('PPFNo', $ppf)->where->get();
        $ppfLookUp = $records->first();

        return [
            'partNo' => $ppfLookUp['PartNo'],
            'mdNo' => $ppfLookUp['MDNo'],
            'nqr' => $ppfLookUp['NQR'],
            'noOfCav' => $ppfLookUp['NoofCavity'],
            'lotNo' => $ppfLookUp['ProdLotNo'],
            'checkTime' => $checkTime->pluck('check-time')->all(),
            'dateJudge' => $ppfLookUp['DateJudge'],
        ];
    }

    public function getHeaderforRec($ppf) {}
}
