<?php

namespace App\Inspection\Services;

use App\Inspection\Models\EncodingDuration;
use Illuminate\Support\Facades\Auth;

class EncodingDurationRecorder
{
    /**
     * One row per PPF + Checktime + RowNo.
     * RowNo 0 is reserved for the Gap-Offset page; dimension rows start at 1.
     */
    public function record(
        int $ppf,
        string $checkTime,
        int $rowNo,
        string $dimItem,
        array $meta,
        ?string $startedAt,
        ?string $endedAt,
    ): EncodingDuration {
        $existing = EncodingDuration::where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->where('RowNo', $rowNo)
            ->first();

        return EncodingDuration::updateOrCreate(
            [
                'PPFNo'     => $ppf,
                'Checktime' => $checkTime,
                'RowNo'     => $rowNo,
            ],
            [
                'DimItem'       => $dimItem,
                'PartNo'        => $meta['partNo'] ?? null,
                'MachineNo'     => $meta['machineNo'] ?? null,
                'Encoder'       => Auth::user()->EmployeeID ?? null,
                'StartDatetime' => $existing->StartDatetime ?? ($startedAt ?? now()),
                'EndDatetime'   => $endedAt ?? ($existing->EndDatetime ?? now()),
            ]
        );
    }
}