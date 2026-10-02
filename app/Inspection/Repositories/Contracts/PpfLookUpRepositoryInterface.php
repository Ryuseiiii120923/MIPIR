<?php

namespace App\Inspection\Repositories\Contracts;
use App\Domain\Master\MoldingPlan;
use App\Domain\Master\NQR;
use App\Domain\Master\SEIHIN;

interface PpfLookUpRepositoryInterface
{
    public function getPartNoMoldNo(int $ppf) : ?MoldingPlan;
    public function getCavity(string $partNo): ?int;
    public function getNQR(string $partNo, string $moldNo);
    public function getNQRSeihin(string $partNo, string $moldNo);
    public function isExist(int $ppf): bool;

    public function getMainData(int $ppf): ?array;
}