<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use Illuminate\Support\Facades\DB;

class UpdateInspection
{
    public function execute(int $ppfno): void
    {
        $draft = app(DraftAction::class)->get($ppfno);
        if (empty($draft['ppfLookup']['productionLotNo']) || empty($draft['ppfLookup']['machineNo'])) {
            throw new \InvalidArgumentException('Process details are required to update this inspection.');
        }

        if (empty($draft['check-time']['check-time'])) {
            throw new \InvalidArgumentException('At least one check time is required to update this inspection.');
        }

        DB::transaction(function () use ($ppfno, $draft) {
            app(CreateInspection::class)->execute($ppfno, $draft);
        });
    }
}