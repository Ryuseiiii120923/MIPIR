<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\Log;
use App\Inspection\Models\GroupLeader\LotConfirmation;
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
            $group  = $source->groupContext($ppf);

            if ($group === null) {
                return;
            }

            $allRows = [];

            foreach ($group['headers'] as $groupPpf => $header) {
                $tightened = $source->dimensions($groupPpf, self::DIM_ITEM)
                    ->filter(fn($r) => $source->isTightened($r))
                    ->groupBy(fn($r) => (string) $r->Checktime);

                foreach ($tightened as $checkTime => $rows) {
                    $first = $rows->first();

                    $allRows[] = [
                        'lotNo'         => $header['lotNo'],
                        'machineNo'     => $header['machineNo'],
                        'inspector'     => $header['inspector'],
                        'checkTime'     => (string) $checkTime,
                        'measurements'  => $rows->flatMap(fn($r) => $source->measurements($r))->values()->all(),
                        'specification' => (string) ($first->Specs ?? ''),
                        'controlLimit'  => (string) ($first->CL ?? ''),
                        'remarks'       => $header['remarks'][(string) $checkTime] ?? '',
                    ];
                }
            }

            $context = [
                'partNo'      => $group['partNo'],
                'basePartNo'  => $group['basePartNo'],
                'moldNo'      => $group['moldNo'],
                'confirmedBy' => $confirmedBy ?? $this->confirmedByFromDb($group),
            ];

            // walang row = buburahin ng service ang lumang file ng group
            app(InsertTightenedFlash::class)->insertFlashToExcel($context, $allRows);
        } catch (Throwable $e) {
            Log::error('Tightened Flash Thickness Excel failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
                'at'    => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    /** Dahil rebuild ang file at iisa ang "checked by" cell, kunin ang pinakabagong GL na nag-confirm sa group. */
    private function confirmedByFromDb(array $group): ?string
    {
        $lots = collect($group['headers'])->pluck('lotNo')->unique()->all();

        $value = LotConfirmation::whereIn('PartNo', $group['partNos'])
            ->whereIn('ProdLotNo', $lots)
            ->where('Status', LotConfirmation::CONFIRMED)
            ->orderByDesc('Id')
            ->value('ConfirmedBy');

        return $value !== null ? (string) $value : null;
    }
}
