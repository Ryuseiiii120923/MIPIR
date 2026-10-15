<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes the Tightened Flash Thickness Excel workbook from
 * tblDimensionMeasure / tblInspectionRecord. Always rebuilds from the DB.
 */
class TightenedFlashRefresher
{
    private const DIM_ITEM = 'Flash Thickness';

    public function refresh(int $ppf, ?string $confirmedBy = null): void
    {
        try {
            $source = app(MipirSourceData::class);
            $header = $source->header($ppf);

            if ($header === null) {
                return;
            }

            $flash = $source->dimensions($ppf, self::DIM_ITEM);

            if ($flash->isEmpty()) {
                return;
            }

            $allRows = $flash
                ->filter(fn ($r) => $source->isTightened($r))
                ->map(fn ($r) => [
                    'checkTime' => (string) $r->Checktime,
                    'measurements' => $source->measurements($r),
                    'specification' => (string) ($r->Specs ?? ''),
                    'controlLimit' => (string) ($r->CL ?? ''),
                    'remarks' => $header['remarks'][(string) $r->Checktime] ?? '',
                ])
                ->values()
                ->all();

            $context = [
                'ppf' => $ppf,
                'partNo' => $header['partNo'],
                'moldNo' => $header['moldNo'],
                'machineNo' => $header['machineNo'],
                'lotNo' => $header['lotNo'],
                'inspector' => $header['inspector'],
                'confirmedBy' => $confirmedBy
            ];

            app(InsertTightenedFlash::class)->insertFlashToExcel($context, $allRows);
        } catch (Throwable $e) {
            Log::error('Tightened Flash Thickness Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }
}