<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use Illuminate\Support\Facades\DB;

class DeleteInspection
{
    public function execute(int $ppfno): bool
    {
        return DB::transaction(function () use ($ppfno) {
            Defect::where('PPFNo', $ppfno)->delete();
            SmallDefect::where('PPFNo', $ppfno)->delete();
            MIPIRDimensionMeasure::where('PPFNo', $ppfno)->delete();

            MIPIRInspectionRecord::where('PPFNo', $ppfno)->delete();
            CheckTime::where('PPFNo', $ppfno)->delete();
            ChckTRemarks::where('PPFNo', $ppfno)->delete();

            return true;
        });
    }
}
