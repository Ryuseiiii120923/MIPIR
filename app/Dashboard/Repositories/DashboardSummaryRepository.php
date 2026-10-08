<?php

namespace App\Dashboard\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardSummaryRepository
{
    private const CONNECTION = 'mipirDB';
    private const TABLE      = 'tblDimensionMeasure';

    // ── EncodingDuration: palitan ayon sa totoong columns ─────────────
    // Kung nasa ibang database ito, ilagay ang buong pangalan: 'DB_NAME.dbo.EncodingDuration'
    private const DURATION_TABLE     = 'EncodingDuration';
    private const DURATION_PPF       = 'PPFNo';
    private const DURATION_CHECKTIME = 'Checktime'; // gawing null kung per PPF lang ang table
    private const DURATION_START     = 'StartTime';
    private const DURATION_END       = 'EndTime';

    /** Saved judge values per filter (the app uses both O/X and OK/NG). */
    private const JUDGE_VALUES = [
        'OK' => ['O', 'OK'],
        'NG' => ['X', 'NG'],
    ];

    private function table(?string $alias = null): Builder
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE . ($alias ? " as {$alias}" : ''));
    }

    /** One row per PPF, newest first. Start/End come from EncodingDuration. */
    public function paginatePpfs(string $search, string $mode, string $judge, int $perPage): LengthAwarePaginator
    {
        $d = self::DURATION_TABLE;
        $p = self::DURATION_PPF;
        $s = self::DURATION_START;
        $e = self::DURATION_END;

        return $this->table('m')
            ->selectRaw("
                m.PPFNo,
                MAX(m.PartNo)    AS PartNo,
                MAX(m.MachineNo) AS MachineNo,
                MAX(m.ProdLotNo) AS ProdLotNo,
                COALESCE(
                    (SELECT MIN(d.{$s}) FROM {$d} AS d WHERE d.{$p} = m.PPFNo),
                    MIN(m.created_at)
                ) AS DateStart,
                COALESCE(
                    (SELECT MAX(d.{$e}) FROM {$d} AS d WHERE d.{$p} = m.PPFNo),
                    MAX(COALESCE(m.updated_at, m.created_at))
                ) AS DateEnd
            ")
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('m.PPFNo', 'like', "%{$search}%")
                    ->orWhere('m.PartNo', 'like', "%{$search}%")
            ))
            ->when($mode === 'tightened', fn ($q) => $q->whereExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from(self::TABLE . ' as x')
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->where('x.Mode', 'like', 'tight%')
            ))
            ->when($mode === 'normal', fn ($q) => $q->whereExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from(self::TABLE . ' as x')
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->where(fn ($w) => $w->whereNull('x.Mode')->orWhere('x.Mode', 'not like', 'tight%'))
            ))
            ->when(isset(self::JUDGE_VALUES[$judge]), fn ($q) => $q->whereExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from(self::TABLE . ' as x')
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->whereIn('x.Judge', self::JUDGE_VALUES[$judge])
            ))
            ->groupBy('m.PPFNo')
            ->orderByRaw('MIN(m.created_at) DESC')
            ->paginate($perPage);
    }

    /** One grouped query for every PPF on the current page. */
    public function summaryForPpfs(array $ppfs): Collection
    {
        if ($ppfs === []) {
            return collect();
        }

        return $this->table()
            ->whereIn('PPFNo', $ppfs)
            ->groupBy('PPFNo')
            ->selectRaw("
                PPFNo,
                COUNT(DISTINCT Checktime) AS check_times,
                COUNT(*) AS total,
                SUM(CASE WHEN Mode LIKE 'tight%' THEN 1 ELSE 0 END) AS tightened,
                SUM(CASE WHEN Judge IN ('O', 'OK') THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN Judge IN ('X', 'NG') THEN 1 ELSE 0 END) AS ng
            ")
            ->get()
            ->keyBy('PPFNo');
    }

    /** Light rows, enough to group into check times. */
    public function rowsForPpf(int $ppf): Collection
    {
        return $this->table()
            ->where('PPFNo', $ppf)
            ->get(['Checktime', 'Mode', 'Judge', 'InspectedBy', 'created_at', 'updated_at']);
    }

    /** Every dimension / set of one check time, in encoding order. */
    public function rowsForCheckTime(int $ppf, string $checkTime): Collection
    {
        return $this->table()
            ->where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->orderBy('RECNO')
            ->get([
                'RECNO', 'PartNo', 'MDNo', 'ProdLotNo', 'MachineNo', 'Checktime',
                'Mode', 'Set', 'DimItem', 'Specs', 'CL', 'Judge',
                'Value1', 'Value2', 'Value3', 'Value4', 'Value5',
                'ForXBar', 'InspectedBy', 'created_at', 'updated_at',
            ]);
    }

    /** Start / end rows of the PPF from EncodingDuration (aliased StartAt, EndAt, Checktime). */
    public function durationsForPpf(int $ppf): Collection
    {
        $columns = [
            self::DURATION_START . ' as StartAt',
            self::DURATION_END . ' as EndAt',
        ];

        if (self::DURATION_CHECKTIME !== null) {
            $columns[] = self::DURATION_CHECKTIME . ' as Checktime';
        }

        return DB::connection(self::CONNECTION)
            ->table(self::DURATION_TABLE)
            ->where(self::DURATION_PPF, $ppf)
            ->get($columns);
    }
}