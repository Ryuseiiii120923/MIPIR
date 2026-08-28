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
    public function execute(int $ppfno): void
    {
        $draft = app(DraftAction::class)->get($ppfno);
        if (empty($draft['process-details']['productionLotNo']) || empty($draft['process-details']['machineNo'])) {
            throw new \InvalidArgumentException('Process details are required to update this inspection.');
        }

        if (empty($draft['check-time']['check-time'])) {
            throw new \InvalidArgumentException('At least one check time is required to update this inspection.');
        }

        DB::transaction(function () use ($ppfno, $draft) {
            Defect::where('PPFNo', $ppfno)->delete();

            MIPIRDimensionMeasure::where('PPFNo', $ppfno)->delete();

            MIPIRInspectionRecord::where('PPFNo', $ppfno)->delete();

            CheckTime::where('PPFNo', $ppfno)->delete();

            ChckTRemarks::where('PPFNo', $ppfno)->delete();

            app(CreateInspection::class)->execute($ppfno, $draft);
        });
    }
}