<?php

namespace App\Inspection\Repositories;

use App\Inspection\Models\GroupLeader\GroupLeader;
use App\Inspection\Models\GroupLeader\LotConfirmation;
use Illuminate\Support\Collection;

class LotConfirmationRepository
{
    public function isGl(string $employeeId): bool
    {
        return GroupLeader::where('EmployeeID', $employeeId)->where('IsActive', true)->exists();
    }

    /** Mga pending na lot na sakop ng GL na ito (ayon sa plant at machine sa roster) */
    public function pendingFor(string $employeeId): Collection
    {
        $scopes = GroupLeader::where('EmployeeID', $employeeId)
            ->where('IsActive', true)
            ->get(['Plant', 'MachineNo']);

        if ($scopes->isEmpty()) {
            return collect();
        }

        return LotConfirmation::where('Status', 'pending')
            ->where(function ($q) use ($scopes) {
                foreach ($scopes as $scope) {
                    $q->orWhere(function ($w) use ($scope) {
                        $w->where('Plant', $scope->Plant)
                            ->when($scope->MachineNo !== null, fn ($m) => $m->where('MachineNo', $scope->MachineNo));
                    });
                }
            })
            ->orderBy('RequestedAt')
            ->get();
    }

    public function canConfirm(LotConfirmation $lot, string $employeeId): bool
    {
        return GroupLeader::where('EmployeeID', $employeeId)
            ->where('IsActive', true)
            ->where('Plant', $lot->Plant)
            ->where(fn ($q) => $q->whereNull('MachineNo')->orWhere('MachineNo', $lot->MachineNo))
            ->exists();
    }
}