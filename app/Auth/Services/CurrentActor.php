<?php

namespace App\Auth\Services;

use App\Inspection\Models\GroupLeader\GroupLeader;
use Illuminate\Support\Facades\Auth;

class CurrentActor
{
    private const DUMMY_PLANT = 'P1A';

    public function id(): int
    {
        $worker = Auth::guard('worker')->user();

        if ($worker) {
            return (int) $worker->EmployeeID;
        }

        return (int) $this->webEmployeeCode();
    }

    public function isWorker(): bool
    {
        return Auth::guard('worker')->check();
    }

    public function isDummy(): bool
    {
        if ($this->isWorker() || ! config('dummy-login.enabled')) {
            return false;
        }

        $code = $this->webEmployeeCode();

        return $code !== null
            && (int) $code === (int) config('dummy-login.userid');
    }

    public function plant(): ?string
    {
        $worker = Auth::guard('worker')->user();

        if ($worker) {
            return $worker->Plant;
        }

        if ($this->isDummy()) {
            return self::DUMMY_PLANT;
        }

        return GroupLeader::where('EmployeeId', $this->webEmployeeCode())->value('Plant');
    }

    public function inspectorNo(): ?string
    {
        $worker = Auth::guard('worker')->user();

        if ($worker) {
            return $worker->InspectorNo;
        }

        return GroupLeader::where('EmployeeId', $this->webEmployeeCode())->value('InspectorNo');
    }

    private function webEmployeeCode(): int|string|null
    {
        return Auth::guard('web')->user()?->{'社員CD'};
    }
}
