<?php

namespace App\Inspection\Actions;

use App\Inspection\Models\ChckTRemarks;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\EncodingDuration;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use App\Inspection\Services\XBarCycleTracker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteInspection
{
    public function execute(int $ppfno): bool
    {
        return DB::transaction(function () use ($ppfno) {
            $plant = Auth::user()->Plant ?? null; // <-- confirm this is correct source
            $groupKey = XBarCycleTracker::groupKey((string) ($ppfLookUp['partNo'] ?? ''), $ppfLookUp['moldNo'] ?? null);

            $measuresToReverse = MIPIRDimensionMeasure::where('PPFNo', $ppfno)
                ->where('forXBar', true)
                ->where('DimItem', 'not like', '% (Y)')
                ->whereNotNull('xbarTransaction')
                ->get(['xbarTransaction']);

            Defect::where('PPFNo', $ppfno)->delete();
            SmallDefect::where('PPFNo', $ppfno)->delete();
            MIPIRDimensionMeasure::where('PPFNo', $ppfno)->delete();

            MIPIRInspectionRecord::where('PPFNo', $ppfno)->delete();
            CheckTime::where('PPFNo', $ppfno)->delete();
            ChckTRemarks::where('PPFNo', $ppfno)->delete();
            EncodingDuration::where('PPFNo', $ppfno)->delete();

            if ($plant !== null) {
                $countsByDimItem = $measuresToReverse->countBy('DimItem');

                foreach ($measuresToReverse->countBy('xbarTransaction') as $transactionId => $count) {
                    for ($i = 0; $i < $count; $i++) {
                        app(XBarCycleTracker::class)->reverseSubgroupByTransaction((string) $transactionId);
                    }
                }
            }

            return true;
        });
    }

    public function executeGapOffset(int $ppfno): bool
    {
        return DB::transaction(function () use ($ppfno) {
            MIPIRDimensionMeasure::where('PPFNo', $ppfno)
                ->where(function ($query) {
                    $query->where('DimItem', 'Gap-Offset')
                        ->orWhere('DimItem', 'Gap-Offset (Y)');
                })
                ->delete();

            EncodingDuration::where('PPFNo', $ppfno)->where('RowNo', 0)->delete();

            return true;
        });
    }
}
