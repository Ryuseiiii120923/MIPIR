<?php

namespace App\Inspection\Services\Dimensions;

use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;



class DimensionsService
{
    public function __construct(
        private DimensionMasterRepositoryInterface $repo
    ) {}

    public function displaySpecification(string $partNo, string $dimensionName): array
    {
        return [
            'specification' => $this->repo->getSpecification($partNo, $dimensionName),
        ];
    }
}