<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use Illuminate\Support\Facades\DB;

class UpdateInspection
{
    public function execute(int $ppfno)
    {
        DB::transaction(function () use ($ppfno) {
            $draft = app(DraftAction::class)->get($ppfno);

            Defect::where('PPFNo', $ppfno)->delete();

            MIPIRDimensionMeasure::where('PPFNo', $ppfno)->delete();

            MIPIRInspectionRecord::where('PPFNo', $ppfno)->delete();

            app(CreateInspection::class)->execute($ppfno, $draft);
        });
    }
}
