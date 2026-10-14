<?php

namespace App\Dashboard\Services;

use App\Dashboard\Repositories\GroupLeaderRepository;

class GroupLeaderService
{
    public function __construct(private GroupLeaderRepository $repository) {}

    public function register(string $employeeId, string $plant, ?string $machineNo): void
    {
        // Kailangan may account sa PASWORD, kung hindi ay hindi makakapag-login
        if (! $this->repository->employeeHasLogin($employeeId)) {
            throw new \DomainException("Employee {$employeeId} has no login account.");
        }

        $machineNo = ($machineNo !== null && trim($machineNo) !== '') ? trim($machineNo) : null;

        $this->repository->register(
            $employeeId,
            $this->repository->nameOf($employeeId),
            $plant,
            $machineNo
        );
    }

    public function remove(int $id): void
    {
        $this->repository->remove($id);
    }
}