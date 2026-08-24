<?php

namespace App\Dashboard\Action;
use App\Inspection\Models\DimensionMaster;
use App\Inspection\Models\Dimensions\DimensionMasterForXBar;
use App\Inspection\Models\Dimensions\TempDimension;
use App\Inspection\Models\TempDimStorage;
use Illuminate\Http\UploadedFile;

class DimensionApprovalAction{
    public function execute(TempDimension $temp, array $encoding, ?UploadedFile $photo, string $encoder): void
    {
        $photoPath = $photo?->store('dimension-photos', 'public');

        DimensionMasterForXBar::upsert(
            [[
                'PartNo'        => $temp->PartNo,
                'Symbol'        => $encoding['symbol'],
                'DimensionName' => $temp->DimensionName,
                'Specification' => $temp->Specification,
                'UpperLimit'    => $temp->UpperLimit,
                'LowerLimit'    => $temp->LowerLimit,
                'Device'        => $temp->Device,
                'SamplingQTY'   => $encoding['sampling_qty'],
                'Encoder'       => $encoder,
                'EncodedDate'   => now(),
                'PhotoPath'     => $photoPath,
            ]],
            ['PartNo', 'DimensionName'],
            ['Symbol', 'Specification', 'UpperLimit', 'LowerLimit', 'Device', 'SamplingQTY', 'Encoder', 'EncodedDate', 'PhotoPath']
        );

        $temp->delete();
    }
}