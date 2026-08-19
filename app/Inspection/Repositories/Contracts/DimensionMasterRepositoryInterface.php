<?php

namespace App\Inspection\Repositories\Contracts;

use Illuminate\Support\Collection;

interface DimensionMasterRepositoryInterface
{
    /**
     * Get the distinct dimension names for a given part number.
     *
     * @param string $partNo
     * @return Collection
     */
    public function getDimensionName(string $partNo): Collection;

    /**
     * Get the specification, upper limit, and lower limit for a given
     * part number and dimension name.
     *
     * @param string $partNo
     * @param string $item
     * @return array{Specification: string, UpperLimit: float, LowerLimit: float}|null
     */
    public function getMasterSpecification(string $partNo, string $item): ?array;

    /**
     * Search for dimension names matching a term for a given part number.
     *
     * @param string $term
     * @param string $partNo
     * @return array
     */
    public function search(string $term, string $partNo): array;

     public function updateOrCreateSpecification(
        string $partNo,
        string $item,
        string $specification,
        string $upperLimit,
        string $lowerLimit
    ): void;
}