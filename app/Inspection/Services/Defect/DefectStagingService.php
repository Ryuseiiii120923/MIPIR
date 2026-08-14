<?php

namespace App\Inspection\Services\Defect;

use Illuminate\Support\Collection;

class DefectStagingService
{
    /**
     * Build a staged-defect entry from raw modal input, validating qty constraints.
     *
     * @return array{ok: bool, entry: array|null, error: string|null}
     */
    public function buildStagedEntry(string $largeDefect, int $largeQty, array $smallDefects): array
    {
        $smallsWithQty = $this->normalizeSmallDefects($smallDefects);
        $smallTotal    = collect($smallsWithQty)->sum('qty');

        if ($largeQty < 1) {
            return ['ok' => false, 'entry' => null, 'error' => 'Quantity must be at least 1.'];
        }

        if ($smallTotal > $largeQty) {
            return [
                'ok'    => false,
                'entry' => null,
                'error' => "Total small qty ({$smallTotal}) cannot exceed large qty ({$largeQty}).",
            ];
        }

        return [
            'ok'    => true,
            'entry' => [
                'type'         => $largeDefect,
                'qty'          => $largeQty,
                'smallDefects' => $smallsWithQty,
            ],
            'error' => null,
        ];
    }

    /**
     * Strips zero/empty rows and casts qty to int.
     */
    public function normalizeSmallDefects(array $smallDefects): array
    {
        return collect($smallDefects)
            ->filter(fn($s) => isset($s['qty']) && (int) $s['qty'] > 0)
            ->map(fn($s) => ['type' => $s['type'], 'qty' => (int) $s['qty']])
            ->values()
            ->toArray();
    }

    /**
     * Insert or replace a staged entry by type.
     */
    public function upsertStagedDefect(array $stagedDefects, array $entry): array
    {
        $idx = collect($stagedDefects)->search(fn($e) => $e['type'] === $entry['type']);

        if ($idx !== false) {
            $stagedDefects[$idx] = $entry;
        } else {
            $stagedDefects[] = $entry;
        }

        return $stagedDefects;
    }

    public function removeStagedDefect(array $stagedDefects, string $type): array
    {
        return collect($stagedDefects)
            ->reject(fn($e) => $e['type'] === $type)
            ->values()
            ->toArray();
    }

    public function largeDefectExists(string $type, Collection $largeDefects): bool
    {
        return $largeDefects
            ->pluck('LargeDefect')
            ->map(fn($d) => strtolower(trim($d)))
            ->contains(strtolower(trim($type)));
    }

    /**
     * Merge all staged entries into the persisted $defects / $smallDefects arrays.
     * Skips any staged entry whose type isn't in the master list.
     */
    public function mergeStagedIntoDefects(array $stagedDefects, array $defects, array $smallDefects, Collection $largeDefects): array
    {
        foreach ($stagedDefects as $entry) {
            $largeType = $entry['type'];
            $largeQty  = (int) $entry['qty'];

            if (!$this->largeDefectExists($largeType, $largeDefects)) {
                continue;
            }

            $normalized    = strtolower(trim($largeType));
            $existingIndex = collect($defects)->search(fn($d) => strtolower(trim($d['type'])) === $normalized);

            if ($existingIndex !== false) {
                $defects[$existingIndex]['qty'] = $largeQty;
            } else {
                $defects[] = ['type' => trim($largeType), 'category' => 'large', 'qty' => $largeQty];
            }

            if (!isset($smallDefects[$largeType])) {
                $smallDefects[$largeType] = [];
            }

            foreach ($entry['smallDefects'] as $small) {
                $smallType = trim($small['type']);
                $smallQty  = (int) $small['qty'];
                $idx = collect($smallDefects[$largeType])
                    ->search(fn($s) => strtolower(trim($s['type'])) === strtolower($smallType));

                if ($idx !== false) {
                    $smallDefects[$largeType][$idx]['qty'] = $smallQty;
                } else {
                    $smallDefects[$largeType][] = ['type' => $smallType, 'qty' => $smallQty];
                }
            }
        }

        return ['defects' => $defects, 'smallDefects' => $smallDefects];
    }

    public function removeDefect(array $defects, array $smallDefects, string $type): array
    {
        $defects = collect($defects)->reject(fn($d) => $d['type'] === $type)->values()->toArray();
        unset($smallDefects[$type]);

        return ['defects' => $defects, 'smallDefects' => $smallDefects];
    }

    public function removeSmallDefect(array $smallDefects, string $largeDefect, string $type): array
    {
        if (!isset($smallDefects[$largeDefect])) {
            return $smallDefects;
        }

        $smallDefects[$largeDefect] = collect($smallDefects[$largeDefect])
            ->reject(fn($d) => trim($d['type'] ?? '') === trim($type))
            ->values()
            ->toArray();

        return $smallDefects;
    }

    /**
     * Update a large defect's qty, and proportionally trim its small defects
     * if their total now exceeds the new large qty.
     */
    public function updateLargeDefectQty(array $defects, array $smallDefects, string $type, float $newQty): array
    {
        foreach ($defects as &$defect) {
            if ($defect['type'] === $type) {
                $defect['qty'] = $newQty;
                break;
            }
        }
        unset($defect);

        if (isset($smallDefects[$type])) {
            $smallTotal = collect($smallDefects[$type])->sum(fn($s) => (float) $s['qty']);

            if ($smallTotal > $newQty) {
                $remaining = $newQty;
                foreach ($smallDefects[$type] as &$small) {
                    if ($remaining <= 0) {
                        $small['qty'] = 0;
                        continue;
                    }
                    if ($small['qty'] > $remaining) {
                        $small['qty'] = $remaining;
                    }
                    $remaining -= $small['qty'];
                }
                unset($small);
            }
        }

        return ['defects' => $defects, 'smallDefects' => $smallDefects];
    }

    /**
     * Update one small defect's qty, capped so the group total never exceeds
     * its parent large defect's qty.
     */
    public function updateSmallDefectQty(array $smallDefects, array $defects, string $largeType, string $smallType, float $newQty): array
    {
        if (!isset($smallDefects[$largeType])) {
            return $smallDefects;
        }

        $largeQty = collect($defects)->firstWhere('type', $largeType)['qty'] ?? 0;

        $otherTotal = collect($smallDefects[$largeType])
            ->reject(fn($d) => $d['type'] === $smallType)
            ->sum(fn($d) => (float) $d['qty']);

        $remaining = $largeQty - $otherTotal;

        foreach ($smallDefects[$largeType] as &$defect) {
            if ($defect['type'] === $smallType) {
                $defect['qty'] = min($newQty, $remaining);
                break;
            }
        }
        unset($defect);

        return $smallDefects;
    }

    public function calculateTotalNg(array $defects): float
    {
        return collect($defects)->sum('qty');
    }

    public function calculateTotalSmallQty(array $smallDefects): float
    {
        return collect($smallDefects)->flatten(1)->sum(fn($d) => (float) ($d['qty'] ?? 0));
    }
}