<?php

namespace App\Dashboard\Services;

use App\Dashboard\Repositories\GroupLeaderRepository;

class GroupLeaderService
{
    public function __construct(private GroupLeaderRepository $repository) {}

    public function register(string $employeeId, string $plant,string $inspectorNo): void
    {
        if (! $this->repository->employeeHasLogin($employeeId)) {
            throw new \DomainException("Employee {$employeeId} has no login account.");
        }

        $this->repository->register(
            $employeeId,
            $this->repository->nameOf($employeeId),
            $plant,
            $inspectorNo
        );
    }

    public function remove(int $id): void
    {
        $this->repository->remove($id);
    }
}