<?php

namespace App\Inspection\Services\Excel;

/**
 * Formats normal/tightened defect batches and adds the small-defect detail
 * marker to remarks.
 *
 * Supply the existing SMALL_DEFECT_MARKER value from the parent component
 * when constructing this formatter; its value was not present in the source
 * excerpt used to create this class.
 */
class DefectBatchFormatter
{
    public function __construct(
        private readonly string $smallDefectMarker
    ) {
    }

    public function composeRemarks(string $remarks, array $batches): string
    {
        $pattern = '/\\s\\*' . preg_quote($this->smallDefectMarker, '/') . '\\.\\*$/s';
        $base = trim((string) preg_replace($pattern, '', $remarks));
        $parts = [];

        foreach ($batches as $batch) {
            $shot = $batch['shot'] ?? null;
            $prefix = $shot !== null ? "Shot {$shot} " : '';

            foreach ($batch['defects'] ?? [] as $defect) {
                $large = $defect['type'] ?? null;

                if ($large === null) {
                    continue;
                }

                $smalls = collect($batch['smallDefects'][$large] ?? [])
                    ->filter(static fn ($small) => (float) ($small['qty'] ?? 0) > 0)
                    ->map(static fn ($small) => ($small['type'] ?? '') . ' (' . ($small['qty'] ?? 0) . ')')
                    ->values();

                if ($smalls->isNotEmpty()) {
                    $parts[] = "{$prefix}{$large}: " . $smalls->implode(', ');
                }
            }
        }

        if ($parts === []) {
            return $base;
        }

        return trim($base . ' ' . $this->smallDefectMarker . ' ' . implode('; ', $parts));
    }

    /**
     * Normal mode returns one unlabelled batch.
     * Tightened mode returns one batch per shot.
     *
     * @return array<int, array{shot: ?int, defects: array, smallDefects: array}>
     */
    public function batchesFor(
        string $checkTime,
        array $defects,
        array $smallDefects,
        array $modeByTime,
        array $shotsByTime
    ): array {
        if (($modeByTime[$checkTime] ?? 'normal') !== 'tightened') {
            return [[
                'shot' => null,
                'defects' => $defects[$checkTime] ?? [],
                'smallDefects' => $smallDefects[$checkTime] ?? [],
            ]];
        }

        return collect($shotsByTime[$checkTime] ?? [])
            ->map(static fn (array $shot) => [
                'shot' => isset($shot['shot']) ? (int) $shot['shot'] : null,
                'defects' => $shot['defects'] ?? [],
                'smallDefects' => $shot['smallDefects'] ?? [],
            ])
            ->values()
            ->all();
    }
}
