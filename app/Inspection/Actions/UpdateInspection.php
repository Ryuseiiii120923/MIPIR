<?php

namespace App\Inspection\Actions;

use App\Inspection\Services\Saving\ConfirmationCheckTimeService;
use Illuminate\Support\Facades\DB;

class UpdateInspection
{
    public function execute(int $ppfno): array
    {
        $drafts = app(DraftAction::class);

        $draft = $drafts->get($ppfno);
        if (empty($draft['ppfLookup']['productionLotNo']) || empty($draft['ppfLookup']['machineNo'])) {
            throw new \InvalidArgumentException('Process details are required to update this inspection.');
        }

        if (empty($draft['check-time']['check-time'])) {
            throw new \InvalidArgumentException('At least one check time is required to update this inspection.');
        }
        if (app(ConfirmationCheckTimeService::class)->appendIfRejected($ppfno)) {
            $draft = $drafts->get($ppfno);
        }

        return DB::transaction(function () use ($ppfno, $draft) {
            return app(CreateInspection::class)->execute($ppfno, $draft);
        });
    }
}
