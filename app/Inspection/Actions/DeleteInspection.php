<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use Illuminate\Support\Facades\DB;

class DeleteInspection
{
    public function execute(int $ppfno): bool
    {
        return DB::transaction(function () use ($ppfno) {
            Defect::where('PPFNo', $ppfno)->delete();

            MIPIRDimensionMeasure::where('PPFNo', $ppfno)->delete();

            MIPIRInspectionRecord::where('PPFNo', $ppfno)->delete();

            return true;
        });
    }
}