<?php

namespace App\Dashboard\Repositories;

use App\Inspection\Models\GroupLeader\LotConfirmation;
use App\Inspection\Models\MIPIRDimensionMeasure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DashboardSummaryRepository
{
    public const JUDGE_OK = ['0', 'O', 'OK'];
    public const JUDGE_NG = ['1', 'X', 'NG'];

    public const MODE_TIGHTENED = [];

    private const DURATION_TABLE     = 'EncodingDuration';
    private const DURATION_PPF       = 'PPFNo';
    private const DURATION_CHECKTIME = 'Checktime';
    private const DURATION_START     = 'StartDateTime';
    private const DURATION_END       = 'EndDateTime';
    private const INSPECTOR_TABLE = 'tblUser';
    private const INSPECTOR_KEY   = 'InspectedBy';
    private const INSPECTOR_CODE  = 'InspectorNo';
    private const INSPECTOR_PLANT = 'Plant';

    private function tableName(): string
    {
        return (new MIPIRDimensionMeasure)->getTable();
    }

    private function connection(): Connection
    {
        return (new MIPIRDimensionMeasure)->getConnection();
    }

    private function query(?string $alias = null): Builder
    {
        $query = MIPIRDimensionMeasure::query();

        return $alias
            ? $query->from($this->tableName() . " as {$alias}")
            : $query;
    }


    private function sqlList(array $values): string
    {
        return implode(', ', array_map(
            fn($v) => "'" . str_replace("'", "''", (string) $v) . "'",
            $values
        ));
    }

    private function judgeSql(string $column, array $values): string
    {
        return "CAST({$column} AS VARCHAR(10)) IN (" . $this->sqlList($values) . ')';
    }

    private function tightenedSql(string $column = 'Mode'): string
    {
        $cast = "CAST({$column} AS VARCHAR(20))";
        $sql  = "{$cast} LIKE 'tight%'";

        if (self::MODE_TIGHTENED !== []) {
            $sql .= " OR {$cast} IN (" . $this->sqlList(self::MODE_TIGHTENED) . ')';
        }

        return "({$sql})";
    }

    // ------------------------------------------------------------------

    /**
     * One row per PPF, newest first. Start/End come from EncodingDuration.
     * Grouped aggregates aren't model records, so the query goes through toBase().
     */
    public function paginatePpfs(string $search, string $mode, string $judge, int $perPage, ?string $plant = null): LengthAwarePaginator
    {
        $table = $this->tableName();
        $d     = self::DURATION_TABLE;
        $p     = self::DURATION_PPF;
        $s     = self::DURATION_START;
        $e     = self::DURATION_END;
        $it    = self::INSPECTOR_TABLE;
        $ik    = self::INSPECTOR_KEY;
        $ic    = self::INSPECTOR_CODE;
        $ip    = self::INSPECTOR_PLANT;

        $judgeValues = ['OK' => self::JUDGE_OK, 'NG' => self::JUDGE_NG][$judge] ?? null;

        return $this->query('m')
            ->toBase()
            ->leftJoin("{$it} as i", "i.{$ic}", '=', "m.{$ik}")
            ->selectRaw("
            m.PPFNo,
            MAX(m.PartNo)    AS PartNo,
            MAX(m.MachineNo) AS MachineNo,
            MAX(m.ProdLotNo) AS ProdLotNo,
            MAX(i.{$ip})     AS Plant,
            COALESCE(
                (SELECT MIN(d.{$s}) FROM {$d} AS d WHERE d.{$p} = m.PPFNo),
                MIN(m.created_at)
            ) AS DateStart,
            COALESCE(
                (SELECT MAX(d.{$e}) FROM {$d} AS d WHERE d.{$p} = m.PPFNo),
                MAX(COALESCE(m.updated_at, m.created_at))
            ) AS DateEnd
        ")
            ->when($search !== '', fn($q) => $q->where(
                fn($w) => $w->where('m.PPFNo', 'like', "%{$search}%")
                    ->orWhere('m.PartNo', 'like', "%{$search}%")
            ))
            ->when($mode === 'tightened', fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->whereRaw($this->tightenedSql('x.Mode'))
            ))
            ->when($mode === 'normal', fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->whereRaw('(x.Mode IS NULL OR NOT ' . $this->tightenedSql('x.Mode') . ')')
            ))
            ->when($judgeValues !== null, fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->whereRaw($this->judgeSql('x.Judge', $judgeValues))
            ))
            ->when($plant, fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->join("{$it} as xi", "xi.{$ic}", '=', "x.{$ik}")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->where("xi.{$ip}", $plant)
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

        $tightened = $this->tightenedSql();
        $ok        = $this->judgeSql('Judge', self::JUDGE_OK);
        $ng        = $this->judgeSql('Judge', self::JUDGE_NG);

        return $this->query()
            ->toBase()
            ->whereIn('PPFNo', $ppfs)
            ->groupBy('PPFNo')
            ->selectRaw("
                PPFNo,
                COUNT(DISTINCT Checktime) AS check_times,
                COUNT(*) AS total,
                SUM(CASE WHEN {$tightened} THEN 1 ELSE 0 END) AS tightened,
                SUM(CASE WHEN {$ok} THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN {$ng} THEN 1 ELSE 0 END) AS ng
            ")
            ->get()
            ->keyBy('PPFNo');
    }

    /** Light rows, enough to group into check times. */
    public function rowsForPpf(int $ppf): Collection
    {
        return $this->query()
            ->where('PPFNo', $ppf)
            ->get(['Checktime', 'Mode', 'Judge', 'InspectedBy', 'created_at', 'updated_at'])
            ->toBase();
    }

    /** Every dimension / set of one check time, in encoding order. */
    public function rowsForCheckTime(int $ppf, string $checkTime): Collection
    {
        return $this->query()
            ->where('PPFNo', $ppf)
            ->where('Checktime', $checkTime)
            ->orderBy('RECNO')
            ->get([
                'RECNO',
                'PartNo',
                'MDNo',
                'ProdLotNo',
                'MachineNo',
                'Checktime',
                'Mode',
                'Set',
                'DimItem',
                'Specs',
                'CL',
                'Judge',
                'Value1',
                'Value2',
                'Value3',
                'Value4',
                'Value5',
                'ForXBar',
                'InspectedBy',
                'created_at',
                'updated_at',
            ])
            ->toBase();
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

        return $this->connection()
            ->table(self::DURATION_TABLE)
            ->where(self::DURATION_PPF, $ppf)
            ->get($columns);
    }

    public function confirmationsForPpfs(array $ppfs): Collection
    {
        if ($ppfs === []) {
            return collect();
        }

        return LotConfirmation::query()
            ->toBase()
            ->whereIn('PPFNo', $ppfs)
            ->orderByDesc('RequestedAt')
            ->get(['PPFNo', 'RequestedAt', 'ConfirmedAt', 'ConfirmedBy'])
            ->unique('PPFNo')
            ->keyBy('PPFNo');
    }

    public function allPpfs(
        string $search,
        string $mode,
        string $judge,
        ?string $plant = null,
        ?string $from = null,
        ?string $to = null
    ): Collection {
        return $this->ppfQuery($search, $mode, $judge, $plant, $from, $to)->get();
    }

    private function ppfQuery(
        string $search,
        string $mode,
        string $judge,
        ?string $plant,
        ?string $from,
        ?string $to
    ): \Illuminate\Database\Query\Builder {
        $table = $this->tableName();
        $d     = self::DURATION_TABLE;
        $p     = self::DURATION_PPF;
        $s     = self::DURATION_START;
        $e     = self::DURATION_END;
        $it    = self::INSPECTOR_TABLE;
        $ik    = self::INSPECTOR_KEY;
        $ic    = self::INSPECTOR_CODE;
        $ip    = self::INSPECTOR_PLANT;

        $judgeValues = ['OK' => self::JUDGE_OK, 'NG' => self::JUDGE_NG][$judge] ?? null;

        // Same expression as the DateStart column; SQL Server can't use the alias in HAVING
        $dateStartSql = "COALESCE((SELECT MIN(d.{$s}) FROM {$d} AS d WHERE d.{$p} = m.PPFNo), MIN(m.created_at))";

        return $this->query('m')
            ->toBase()
            ->leftJoin("{$it} as i", "i.{$ic}", '=', "m.{$ik}")
            ->selectRaw("
            m.PPFNo,
            MAX(m.PartNo)    AS PartNo,
            MAX(m.MachineNo) AS MachineNo,
            MAX(m.ProdLotNo) AS ProdLotNo,
            MAX(i.{$ip})     AS Plant,
            {$dateStartSql} AS DateStart,
            COALESCE(
                (SELECT MAX(d.{$e}) FROM {$d} AS d WHERE d.{$p} = m.PPFNo),
                MAX(COALESCE(m.updated_at, m.created_at))
            ) AS DateEnd
        ")
            ->when($search !== '', fn($q) => $q->where(
                fn($w) => $w->where('m.PPFNo', 'like', "%{$search}%")
                    ->orWhere('m.PartNo', 'like', "%{$search}%")
            ))
            ->when($mode === 'tightened', fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->whereRaw($this->tightenedSql('x.Mode'))
            ))
            ->when($mode === 'normal', fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->whereRaw('(x.Mode IS NULL OR NOT ' . $this->tightenedSql('x.Mode') . ')')
            ))
            ->when($judgeValues !== null, fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->whereRaw($this->judgeSql('x.Judge', $judgeValues))
            ))
            ->when($plant, fn($q) => $q->whereExists(
                fn($sub) => $sub->selectRaw('1')
                    ->from("{$table} as x")
                    ->join("{$it} as xi", "xi.{$ic}", '=', "x.{$ik}")
                    ->whereColumn('x.PPFNo', 'm.PPFNo')
                    ->where("xi.{$ip}", $plant)
            ))
            ->groupBy('m.PPFNo')
            ->when($from, fn($q) => $q->havingRaw(
                "{$dateStartSql} >= ?",
                [\Illuminate\Support\Carbon::parse($from)->startOfDay()->toDateTimeString()]
            ))
            ->when($to, fn($q) => $q->havingRaw(
                "{$dateStartSql} <= ?",
                [\Illuminate\Support\Carbon::parse($to)->endOfDay()->toDateTimeString()]
            ))
            ->orderByRaw('MIN(m.created_at) DESC');
    }
}
