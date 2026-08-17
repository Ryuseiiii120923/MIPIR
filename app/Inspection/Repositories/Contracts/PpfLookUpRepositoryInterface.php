<?php

namespace App\Inspection\Repositories\Contracts;

use App\Domain\Master\Molding;
use App\Domain\Master\NQR;

interface PpfLookUpRepositoryInterface
{
    public function getPartNoMoldNo(int $ppf) : ?Molding;
    public function getCavity(string $partNo): ?int;
    public function getNQR(string $partNo, string $moldNo) : ?NQR;
    public function isExist(int $ppf): bool;

    public function getMainData(int $ppf, int $machineNo): ?array;
}