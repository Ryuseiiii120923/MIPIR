<?php

namespace App\Dashboard\Repositories\CycleTime;

use App\Inspection\Models\EncodingDuration;
use App\Inspection\Models\GroupLeader\LotConfirmation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CycleTimeReportRepository
{
    public function finishedPpfRows(array $filters): Collection
    {
        $durations = EncodingDuration::query()
            ->selectRaw('PPFNo')
            ->selectRaw('MIN(StartDatetime) AS started_at')
            ->selectRaw('MAX(EndDatetime) AS last_end_at')
            ->selectRaw('COUNT(DISTINCT Checktime) AS checks_done')
            ->selectRaw('COUNT(DISTINCT Encoder) AS inspectors')
            ->selectRaw('SUM(CASE WHEN EndDatetime IS NOT NULL
                                  THEN DATEDIFF(SECOND, StartDatetime, EndDatetime)
                                  ELSE 0 END) AS man_seconds')
            ->groupBy('PPFNo');

        $from = Carbon::parse($filters['dateFrom'])->startOfDay();
        $to   = Carbon::parse($filters['dateTo'])->endOfDay();

        $query = LotConfirmation::query()
            ->from('LotConfirmation as lc')
            ->joinSub($durations, 'ed', 'ed.PPFNo', '=', 'lc.PPFNo')
            ->whereNotNull('lc.RequestedAt')
            ->whereBetween('lc.RequestedAt', [$from, $to])
            ->select([
                'lc.PPFNo',
                'lc.PartNo',
                'lc.ProdLotNo',
                'lc.Plant',
                'lc.MachineNo',
                'lc.Status',
                'lc.RequestedAt',
                'ed.started_at',
                'ed.last_end_at',
                'ed.checks_done',
                'ed.inspectors',
                'ed.man_seconds',
            ])
            ->selectRaw('DATEDIFF(SECOND, ed.started_at, lc.RequestedAt) AS cycle_seconds')
            ->orderByDesc('lc.RequestedAt');

        if (!empty($filters['plant'])) {
            $query->where('lc.Plant', $filters['plant']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q
                ->where('lc.PPFNo', 'like', $term)
                ->orWhere('lc.PartNo', 'like', $term));
        }

        return $query->get();
    }

    public function plants(): Collection
    {
        return LotConfirmation::query()
            ->whereNotNull('Plant')
            ->distinct()
            ->orderBy('Plant')
            ->pluck('Plant');
    }
}