<?php

namespace App\Dashboard\Services;

use App\Dashboard\Repositories\DashboardSummaryRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardSummaryService
{
    public function __construct(private DashboardSummaryRepository $repo) {}

    public function ppfs(string $search, string $mode, string $judge, int $perPage = 10): LengthAwarePaginator
    {
        $page      = $this->repo->paginatePpfs(trim($search), $mode, $judge, $perPage);
        $summaries = $this->repo->summaryForPpfs($page->getCollection()->pluck('PPFNo')->all());

        return $page->through(function ($row) use ($summaries) {
            $s     = $summaries->get($row->PPFNo);
            $total = (int) ($s->total ?? 0);

            $row->dateStart  = $this->date($row->DateStart, 'Y/m/d');
            $row->dateEnd    = $this->date($row->DateEnd, 'Y/m/d');
            $row->checkTimes = (int) ($s->check_times ?? 0);
            $row->mode       = $total === 0 ? null : ((int) $s->tightened > 0 ? 'tightened' : 'normal');
            $row->judgement  = match (true) {
                $total === 0            => null,
                (int) $s->ng > 0        => 'NG',
                (int) $s->ok === $total => 'OK',
                default                 => null, // some rows not judged yet
            };

            return $row;
        });
    }

    /** Check times done for the PPF, oldest first. */
    public function checkTimes(int $ppf): array
    {
        $durations = $this->repo->durationsForPpf($ppf);

        return $this->repo->rowsForPpf($ppf)
            ->groupBy('Checktime')
            ->map(fn(Collection $rows, $checkTime) => $this->checkTimeSummary(
                (string) $checkTime,
                $rows,
                ...$this->durationFor($durations, (string) $checkTime)
            ))
            ->sortBy('sortKey')
            ->values()
            ->map(fn(array $c) => Arr::except($c, 'sortKey'))
            ->all();
    }

    public function detail(int $ppf, string $checkTime): ?array
    {
        $rows = $this->repo->rowsForCheckTime($ppf, $checkTime);

        if ($rows->isEmpty()) {
            return null;
        }

        $first     = $rows->first();
        $durations = $this->repo->durationsForPpf($ppf);

        return [
            ...Arr::except(
                $this->checkTimeSummary($checkTime, $rows, ...$this->durationFor($durations, $checkTime)),
                'sortKey'
            ),
            'partNo'     => $first->PartNo ?: '-',
            'mdNo'       => $first->MDNo ?: '-',
            'lotNo'      => $first->ProdLotNo ?: '-',
            'machineNo'  => $first->MachineNo ?: '-',
            'dimensions' => $rows->groupBy('DimItem')
                ->map(fn(Collection $dimRows, $item) => $this->dimension((string) $item, $dimRows))
                ->values()
                ->all(),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * Start / end of one check time from EncodingDuration.
     * Returns [null, null] when the table has no match (or no check time column),
     * so the summary falls back to created_at / updated_at.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function durationFor(Collection $durations, string $checkTime): array
    {
        $matches = $durations->filter(
            fn($d) => property_exists($d, 'Checktime') && trim((string) $d->Checktime) === trim($checkTime)
        );

        return [
            $matches->pluck('StartAt')->filter()->min(),
            $matches->pluck('EndAt')->filter()->max(),
        ];
    }

    private function checkTimeSummary(string $checkTime, Collection $rows, ?string $start = null, ?string $end = null): array
    {
        $created = $rows->pluck('created_at')->filter()->min();

        $start ??= $created;
        $end   ??= $rows->map(fn($r) => $r->updated_at ?: $r->created_at)->filter()->max();

        return [
            'checkTime' => $checkTime,
            'sortKey'   => (string) $created,
            'dateEnc'   => $this->date($created, 'Y-m-d H:i'),
            'timeStart' => $this->date($start, 'Y-m-d H:i'),
            'timeEnd'   => $this->date($end, 'Y-m-d H:i'),
            'inspector' => $rows->pluck('InspectedBy')->filter()->unique()->implode(', ') ?: '-',
            'judgement' => $this->overallJudge($rows->pluck('Judge')),
            'mode'      => $this->overallMode($rows->pluck('Mode')),
        ];
    }

    private function dimension(string $item, Collection $rows): array
    {
        $first = $rows->first();

        return [
            'item'          => $item,
            'specification' => $first->Specs ?: '-',
            'cl'            => $first->CL ?: '-',
            'forXBar'       => (bool) $first->ForXBar,
            'mode'          => $this->overallMode($rows->pluck('Mode')),
            'judgement'     => $this->overallJudge($rows->pluck('Judge')),
            'sets'          => $rows->sortBy('Set')->map(fn($r) => [
                'set'       => (int) $r->Set,
                'judgement' => $this->judge($r->Judge),
                'values'    => $this->values($r),
            ])->values()->all(),
        ];
    }

    /** [position => value] for the filled Value1..Value5 of a set. */
    private function values(object $row): array
    {
        $values = [];

        for ($i = 1; $i <= 5; $i++) {
            $v = $row->{"Value{$i}"} ?? null;

            if (is_numeric($v)) {
                $values[$i] = $this->number($v);
            }
        }

        return $values;
    }

    private function number(mixed $value): string
    {
        $s = (string) $value;

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    private function overallJudge(Collection $judges): ?string
    {
        $normalized = $judges->map(fn($j) => $this->judge($j));

        return match (true) {
            $normalized->contains('NG')                                => 'NG',
            $normalized->isNotEmpty() && ! $normalized->contains(null) => 'OK',
            default                                                    => null,
        };
    }

    private function overallMode(Collection $modes): string
    {
        return $modes->contains(fn($m) => $this->isTightened($m)) ? 'tightened' : 'normal';
    }

    private function isTightened(mixed $mode): bool
    {
        $value = strtolower(trim((string) $mode));

        return str_starts_with($value, 'tight')
            || in_array($value, array_map('strtolower', DashboardSummaryRepository::MODE_TIGHTENED), true);
    }

    private function judge(mixed $raw): ?string
    {
        $value = strtoupper(trim((string) $raw));

        return match (true) {
            in_array($value, DashboardSummaryRepository::JUDGE_OK, true) => 'OK',
            in_array($value, DashboardSummaryRepository::JUDGE_NG, true) => 'NG',
            default                                                      => null,
        };
    }

    private function date(mixed $value, string $format): string
    {
        if (! filled($value)) {
            return '-';
        }

        // A time-only column (e.g. "08:15:00") has no date, so show just the time
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?(\.\d+)?$/', trim((string) $value))) {
            return Carbon::parse($value)->format('H:i');
        }

        return Carbon::parse($value)->format($format);
    }
}
