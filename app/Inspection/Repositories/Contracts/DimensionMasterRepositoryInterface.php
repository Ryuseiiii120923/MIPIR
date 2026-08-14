<?php

namespace App\Inspection\Repositories\Contracts;
use Illuminate\Support\Collection;

interface DimensionMasterRepositoryInterface
{
    /**
     * Get the dimension names for a given part number.
     *
     * @param string $partNo
     * @return Collection
     */
    public function getDimensionName(string $partNo): Collection;

    /**
     * Get the specification for a given part number and dimension name.
     *
     * @param string $partNo
     * @param string $dimensionName
     * @return string|null
     */

    public function getSpecification(string $partNo, string $dimensionName): ?string;

    /**
     * Search for dimension names matching a term for a given part number.
     *
     * @param string $term
     * @param string $partNo
     * @return array
     */

    public function search(string $term, string $partNo): array;
}