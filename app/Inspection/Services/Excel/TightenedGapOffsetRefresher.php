<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\Log;
use Throwable;

class TightenedGapOffsetRefresher
{
    private const DIM_ITEM   = 'Gap-Offset';  
    private const DIM_ITEM_Y = 'Gap-Offset (Y)';

    public function refresh(int $ppf): void
    {
        try {
            $source = app(MipirSourceData::class);
            $group  = $source->groupContext($ppf);

            if ($group === null) {
                return;
            }

            $isF00VE = str_starts_with(strtoupper($group['basePartNo']), 'F00VE');
            $allRows = [];

            foreach ($group['headers'] as $groupPpf => $header) {
                $x = $source->dimensions($groupPpf, self::DIM_ITEM)
                    ->filter(fn($r) => $source->isTightened($r))
                    ->groupBy(fn($r) => (string) $r->Checktime);

                $y = $source->dimensions($groupPpf, self::DIM_ITEM_Y)
                    ->filter(fn($r) => $source->isTightened($r))
                    ->groupBy(fn($r) => (string) $r->Checktime);

                foreach ($x->keys()->merge($y->keys())->unique() as $checkTime) {
                    $xRows = $x->get($checkTime, collect());
                    $yRows = $y->get($checkTime, collect());
                    $first = $xRows->first() ?? $yRows->first();

                    $allRows[] = [
                        'lotNo'         => $header['lotNo'],
                        'machineNo'     => $header['machineNo'],
                        'inspector'     => $header['inspector'],
                        'checkTime'     => (string) $checkTime,
                        'remarks'       => $header['remarks'][(string) $checkTime] ?? '',
                        'judgement'     => $this->judgementOf($xRows->concat($yRows)),
                        'specification' => (string) ($first->Specs ?? ''),
                        'controlLimit'  => (string) ($first->CL ?? ''),
                        'measurements'  => [
                            '0'  => $yRows->flatMap(fn($r) => $source->measurements($r))->values()->all(),
                            '90' => $xRows->flatMap(fn($r) => $source->measurements($r))->values()->all(),
                        ],
                    ];
                }
            }

            $context = [
                'partNo'     => $group['partNo'],
                'basePartNo' => $group['basePartNo'],
                'moldNo'     => $group['moldNo'],
                'spec'       => $allRows[0]['specification'] ?? '',
                'limit'      => $allRows[0]['controlLimit'] ?? '',
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
                'at'    => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    private function judgementOf($rows): ?string
    {
        $judges = $rows
            ->map(fn($r) => strtoupper(trim((string) $r->Judge)))
            ->filter(fn($j) => $j !== '')
            ->values();

        if ($judges->isEmpty()) {
            return null;
        }

        return $judges->contains(fn($j) => in_array($j, ['X', 'NG', '1'], true)) ? 'X' : 'O';
    }
}
