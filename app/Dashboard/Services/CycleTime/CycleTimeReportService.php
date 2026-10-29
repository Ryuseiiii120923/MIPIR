<?php

namespace App\Dashboard\Services\CycleTime;

use App\Dashboard\Repositories\CycleTime\CycleTimeReportRepository;
use Illuminate\Support\Collection;

class CycleTimeReportService
{
    public function __construct(private CycleTimeReportRepository $repository) {}

    /** One row per PPF */
    public function ppfRows(array $filters): Collection
    {
        return $this->repository->finishedPpfRows($filters)->map(function ($r) {
            $cycle = max(0, (int) $r->cycle_seconds);
            $man   = max(0, (int) $r->man_seconds);

            return [
                'ppf_no'       => $r->PPFNo,
                'part_no'      => $r->PartNo,
                'lot_no'       => $r->ProdLotNo,
                'plant'        => $r->Plant,
                'machine_no'   => $r->MachineNo,
                'status'       => $r->Status,
                'started_at'   => $r->started_at,
                'ended_at'     => $r->RequestedAt,
                'checks_done'  => (int) $r->checks_done,
                'inspectors'   => (int) $r->inspectors,
                'cycle_seconds'=> $cycle,
                'man_seconds'  => $man,
                'cycle_hr'     => round($cycle / 3600, 2),
                'man_hr'       => round($man / 3600, 2),
                'cycle_hms'    => $this->hms($cycle),
                'man_hms'      => $this->hms($man),
            ];
        });
    }

    /** One row per part number (totals and averages across its finished PPFs) */
    public function partRows(array $filters): Collection
    {
        return $this->ppfRows($filters)
            ->groupBy('part_no')
            ->map(function (Collection $rows, $partNo) {
                $count = $rows->count();
                $cycle = $rows->sum('cycle_seconds');
                $man   = $rows->sum('man_seconds');

                return [
                    'part_no'       => $partNo,
                    'ppf_count'     => $count,
                    'checks_done'   => $rows->sum('checks_done'),
                    'total_cycle_hr'=> round($cycle / 3600, 2),
                    'avg_cycle_hr'  => round($cycle / 3600 / $count, 2),
                    'total_man_hr'  => round($man / 3600, 2),
                    'avg_man_hr'    => round($man / 3600 / $count, 2),
                    'last_ended_at' => $rows->max('ended_at'),
                ];
            })
            ->sortBy('part_no')
            ->values();
    }

    public function plants(): Collection
    {
        return $this->repository->plants();
    }

    private function hms(int $seconds): string
    {
        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}