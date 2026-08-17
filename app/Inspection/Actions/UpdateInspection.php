<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use Illuminate\Support\Facades\DB;

class UpdateInspection
{
    public function execute(int $ppfno,$machineNo )
    {
        DB::transaction(function () use ($ppfno,$machineNo ) {
            $draft = app(DraftAction::class)->get($ppfno);

            Defect::where('PPFNo', $ppfno)->where('MachineNo',$machineNo)->delete();

            MIPIRDimensionMeasure::where('PPFNo', $ppfno)->where('MachineNo',$machineNo)->delete();

            MIPIRInspectionRecord::where('PPFNo', $ppfno)->where('MachineNo',$machineNo)->delete();
            CheckTime::where('PPFNo',$ppfno)->where('machine-no', $machineNo)->delete();
            ChckTRemarks::where('PPFNo', $ppfno)->where('MachineNo', $machineNo)->delete();

            app(CreateInspection::class)->execute($ppfno, $draft);
        });
    }
}
