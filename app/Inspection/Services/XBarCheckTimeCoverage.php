<?php

namespace App\Inspection\Services;

use App\Inspection\Repositories\LotConfirmationRepository;

class XBarCheckTimeCoverage
{
    private const REQUIRED = ['F', 'MI', 'E'];

    public function __construct(private LotConfirmationRepository $repo) {}

    public function missing(int $ppf): array
    {
        $present = collect($this->repo->xbarCheckTimes($ppf));

        if ($present->isEmpty()) {
            return [];
        }

        $covered = $present
            ->map(fn (string $c) => $this->group($c))
            ->filter()
            ->unique();

        return array_values(array_diff(self::REQUIRED, $covered->all()));
    }

    /** Aling required check time ang kinabibilangan nito (F, MI, E), o null kung wala. */
    private function group(string $checkTime): ?string
    {
        foreach (self::REQUIRED as $required) {
            if (str_starts_with($checkTime, $required)) {
                return $required;
            }
        }

        return null;
    }
}