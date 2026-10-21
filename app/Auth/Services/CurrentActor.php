<?php

namespace App\Auth\Services;

use App\Inspection\Models\GroupLeader\GroupLeader;
use Illuminate\Support\Facades\Auth;

class CurrentActor
{
    public function id(): int
    {
        $worker = Auth::guard('worker')->user();

        if ($worker) {
            return (int) $worker->EmployeeID;
        }

        return (int) Auth::guard('web')->user()?->{'社員CD'};
    }

    public function isWorker(): bool
    {
        return Auth::guard('worker')->check();
    }

    public function plant(): ?string
    {
        $worker = Auth::guard('worker')->user();

        if ($worker) {
            return $worker->Plant;
        }

        $glNo = Auth::guard('web')->user()?->{'社員CD'};

        return GroupLeader::where('EmployeeId', $glNo)->value('Plant');
    }

    public function inspectorNo(): ?string
    {
         $worker = Auth::guard('worker')->user();

        if ($worker) {
            return $worker->InspectorNo;
        }

         $glNo = Auth::guard('web')->user()?->{'社員CD'};
        return GroupLeader::where('EmployeeId', $glNo)->value('InspectorNo');
    }
}
