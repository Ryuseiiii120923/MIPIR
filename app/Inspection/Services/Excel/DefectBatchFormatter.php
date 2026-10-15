<?php

namespace App\Inspection\Services\Excel;

/**
 * Formats normal/tightened defect batches and adds the small-defect detail
 * marker to remarks. Batches are read from tblDefect
 * and tblSmallDefect.
 *
 * Supply the existing SMALL_DEFECT_MARKER value from the parent component
 * when constructing this formatter.
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
     * Rows without a shot number return one unlabelled batch.
     * Rows with a shot number return one batch per shot.
     *
     * @return array<int, array{shot: ?int, defects: array, smallDefects: array}>
     */
    public function batchesFor(int $ppf, string $checkTime): array
    {
        $source = app(MipirSourceData::class);

        $rows = $source->defects($ppf)
            ->filter(fn ($d) => (string) $d->Checktime === $checkTime)
            ->filter(fn ($d) => trim((string) $d->Defect) !== '');

        $smalls = $source->smallDefects($ppf)
            ->filter(fn ($s) => (string) $s->Checktime === $checkTime);

        $hasShot = static fn ($row): bool => $row->Shot !== null && (int) $row->Shot >= 1;

        $toDefect = static fn ($d) => [
            'type' => (string) $d->Defect,
            'qty' => (float) ($d->Qty ?? 0),
        ];

        // large defect => [['type' => small, 'qty' => n], ...]
        $smallMap = static fn ($group) => $group
            ->groupBy(fn ($s) => (string) $s->largeDefect)
            ->map(fn ($g) => $g->map(fn ($s) => [
                'type' => (string) $s->smallDefect,
                'qty' => (float) ($s->qty ?? 0),
            ])->values()->all())
            ->all();

        $tightened = $rows->filter($hasShot);

        if ($tightened->isEmpty()) {
            return [[
                'shot' => null,
                'defects' => $rows->map($toDefect)->values()->all(),
                'smallDefects' => $smallMap($smalls->reject($hasShot)),
            ]];
        }

        $smallByShot = $smalls->filter($hasShot)->groupBy(fn ($s) => (int) $s->Shot);

        return $tightened
            ->groupBy(fn ($d) => (int) $d->Shot)
            ->sortKeys()
            ->map(fn ($group, $shot) => [
                'shot' => (int) $shot,
                'defects' => $group->map($toDefect)->values()->all(),
                'smallDefects' => $smallMap($smallByShot->get((int) $shot, collect())),
            ])
            ->values()
            ->all();
    }
}