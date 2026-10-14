<?php

namespace App\Dashboard\Repositories;

use App\Auth\Models\PASWORD;
use App\Domain\Worker\WorkerName;
use App\Inspection\Models\GroupLeader\GroupLeader;
use Illuminate\Support\Collection;

class GroupLeaderRepository
{
    public function all(): Collection
    {
        return GroupLeader::orderBy('Plant')->orderBy('EmployeeID')->get();
    }

    public function employeeHasLogin(string $employeeId): bool
    {
        return PASWORD::where('社員CD', $employeeId)->exists();
    }

    public function nameOf(string $employeeId): string
    {
        return (string) (WorkerName::where('社員CD', $employeeId)->value('名前') ?? '');
    }

    public function register(string $employeeId, string $name, string $plant, ?string $machineNo): GroupLeader
    {
        return GroupLeader::updateOrCreate(
            ['EmployeeID' => $employeeId, 'Plant' => $plant, 'MachineNo' => $machineNo],
            ['Name' => $name, 'IsActive' => true]
        );
    }

    public function remove(int $id): void
    {
        GroupLeader::whereKey($id)->delete();
    }
}