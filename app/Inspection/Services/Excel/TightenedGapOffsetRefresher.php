<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes Tightened Gap-Offset Excel workbooks from the database.
 *
 * F00VE parts use InsertTightenedGapF00VE; other parts use
 * InsertTightenedGapOring.
 */
class TightenedGapOffsetRefresher
{
    private const DIM_ITEM = 'Gap-Offset';

    public function refresh(int $ppf): void
    {
        try {
            $source = app(MipirSourceData::class);
            $header = $source->header($ppf);

            if ($header === null) {
                return;
            }

            $gap = $source->dimensions($ppf, self::DIM_ITEM);

            if ($gap->isEmpty()) {
                return;
            }

            $partNo = $header['partNo'];
            $isF00VE = str_starts_with(strtoupper($partNo), 'F00VE');

            $allRows = [];

            foreach ($gap->filter(fn ($r) => $source->isTightened($r))->groupBy(fn ($r) => (string) $r->Checktime) as $checkTime => $rows) {
                $measurements = ['0' => [], '90' => []];

                foreach ($rows as $row) {
                    $angle = $this->angleOf($row);
                    $measurements[$angle] = array_merge($measurements[$angle], $source->measurements($row));
                }

                $first = $rows->first();

                $allRows[] = [
                    'checkTime' => (string) $checkTime,
                    'remarks' => $header['remarks'][(string) $checkTime] ?? '',
                    'judgement' => $this->judgementOf($rows),
                    'specification' => (string) ($first->Specs ?? ''),
                    'controlLimit' => (string) ($first->CL ?? ''),
                    'measurements' => $measurements,
                ];
            }

            $context = [
                'ppf' => $ppf,
                'partNo' => $partNo,
                'moldNo' => $header['moldNo'],
                'machineNo' => $header['machineNo'],
                'lotNo' => $header['lotNo'],
                'inspector' => $header['inspector'],
                'spec' => $allRows[0]['specification'] ?? '',
                'limit' => $allRows[0]['controlLimit'] ?? '',
            ];

            if ($isF00VE) {
                app(InsertTightenedGapF00VE::class)->insertGapF00VEToExcel($context, $allRows);
            } else {
                app(InsertTightenedGapOring::class)->insertGapOringToExcel($context, $allRows);
            }
        } catch (Throwable $e) {
            Log::error('Tightened Gap-Offset Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    /**
     * ASSUMPTION: tblDimensionMeasure.Set tells the 0 deg (measurements_y)
     * rows from the 90 deg (measurements) rows. Adjust here if it differs.
     */
    private function angleOf($row): string
    {
        return in_array(strtoupper(trim((string) $row->Set)), ['0', 'Y'], true) ? '0' : '90';
    }

    /** Worst judgement wins; otherwise the first non-empty value. */
    private function judgementOf($rows): ?string
    {
        $judges = $rows->map(fn ($r) => trim((string) $r->Judge))->filter(fn ($j) => $j !== '')->values();

        $bad = $judges->first(fn ($j) => in_array(strtoupper($j), ['X', 'NG'], true));

        return $bad ?? $judges->first();
    }
}