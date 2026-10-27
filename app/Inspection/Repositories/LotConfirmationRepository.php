<?php

namespace App\Inspection\Repositories;

use App\Auth\Services\CurrentActor;
use App\Inspection\Models\GroupLeader\GroupLeader;
use App\Inspection\Models\GroupLeader\LotConfirmation;
use App\Inspection\Models\MIPIRInspectionRecord;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Inspection\Models\CheckTime;
use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\SmallDefect;

class LotConfirmationRepository
{
    /* ---------------------------------------------------------------
     |  GL side
     * ------------------------------------------------------------- */

    public function isGl(string $employeeId): bool
    {
        return GroupLeader::where('EmployeeID', $employeeId)->where('IsActive', true)->exists();
    }

    public function canConfirm(LotConfirmation $lot, string $employeeId): bool
    {
        return GroupLeader::where('EmployeeID', $employeeId)
            ->where('IsActive', true)
            ->where('Plant', $lot->Plant)
            ->exists();
    }

    private function pendingQuery(string $employeeId): Builder
    {
        $scopes = GroupLeader::query()
            ->where('EmployeeID', $employeeId)
            ->where('IsActive', true)
            ->get(['Plant']);

        $query = LotConfirmation::query()
            ->where('Status', LotConfirmation::PENDING);

        if ($scopes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($scopes) {
            foreach ($scopes as $scope) {
                $q->orWhere(function ($w) use ($scope) {
                    $w->where('Plant', $scope->Plant);
                });
            }
        });
    }

    public function pendingFor(string $employeeId): Collection
    {
        return $this->pendingQuery($employeeId)->orderBy('RequestedAt')->get();
    }

    public function pendingPaginated(string $employeeId, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        return $this->pendingQuery($employeeId)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('PartNo', 'like', "%{$search}%")
                        ->orWhere('ProdLotNo', 'like', "%{$search}%");
                });
            })
            ->orderBy('RequestedAt')
            ->paginate($perPage, ['*'], 'confirmationPage');
    }

    public function confirm(int $id, string $employeeId): bool
    {
        $lot = LotConfirmation::find($id);

        if (!$lot || !$this->canConfirm($lot, $employeeId)) {
            return false;
        }

        return LotConfirmation::where('Id', $id)
            ->where('Status', LotConfirmation::PENDING)
            ->update([
                'Status'      => LotConfirmation::CONFIRMED,
                'ConfirmedBy' => $employeeId,
                'ConfirmedAt' => now(),
            ]) === 1;
    }

    public function reject(int $id, string $employeeId, string $reason): bool
    {
        $lot = LotConfirmation::find($id);

        if (!$lot || !$this->canConfirm($lot, $employeeId)) {
            return false;
        }

        return LotConfirmation::where('Id', $id)
            ->where('Status', LotConfirmation::PENDING)
            ->update([
                'Status'       => LotConfirmation::REJECTED,
                'RejectedBy'   => $employeeId,
                'RejectReason' => trim($reason),
            ]) === 1;
    }

    /** Para sa View button ng GL: PPF(s) ng lot */
    public function getPpfNosForLot(int $id): array
    {
        $lot = LotConfirmation::findOrFail($id);

        return MIPIRInspectionRecord::where('PartNo', $lot->PartNo)
            ->where('ProdLotNo', $lot->ProdLotNo)
            ->where('MachineNo', $lot->MachineNo)
            ->distinct()
            ->pluck('PPFNo')
            ->all();
    }

    /** Lahat ng datos na kailangang suriin ng GL bago mag-confirm */
    public function getLotReview(int $id): ?array
    {
        $lot = LotConfirmation::find($id);

        if (! $lot) {
            return null;
        }

        $records = MIPIRInspectionRecord::where('PartNo', $lot->PartNo)
            ->where('ProdLotNo', $lot->ProdLotNo)
            ->where('MachineNo', $lot->MachineNo)
            ->get();

        $ppfs = $records->pluck('PPFNo')->unique()->values()->all();

        $encoded = CheckTime::whereIn('PPFNo', $ppfs)->get()
            ->mapWithKeys(fn($c) => ["{$c->PPFNo}|{$c->Checktime}" => (string) $c->DateEncode]);

        $defects = Defect::whereIn('PPFNo', $ppfs)->get()
            ->groupBy(fn($d) => "{$d->PPFNo}|{$d->Checktime}");

        $smalls = SmallDefect::whereIn('PPFNo', $ppfs)->get()
            ->groupBy(fn($s) => "{$s->PPFNo}|{$s->Checktime}");

        $dims = MIPIRDimensionMeasure::whereIn('PPFNo', $ppfs)
            ->orderBy('DimItem')
            ->orderBy('Set')
            ->get()
            ->groupBy(fn($m) => "{$m->PPFNo}|{$m->Checktime}");

        $checkTimes = $records
            ->sortBy(fn($r) => $encoded["{$r->PPFNo}|{$r->Checktime}"] ?? (string) $r->DateJudge)
            ->map(function ($r) use ($defects, $smalls, $dims) {
                $key = "{$r->PPFNo}|{$r->Checktime}";

                $defectRows = $defects->get($key, collect());
                $smallRows  = $smalls->get($key, collect());

                $defectList = $defectRows
                    ->map(function ($d) use ($smallRows) {
                        $detail = $smallRows
                            ->filter(fn($s) => $s->largeDefect === $d->Defect && (int) $s->Shot === (int) $d->Shot)
                            ->map(fn($s) => "{$s->smallDefect} ({$s->qty})")
                            ->implode(', ');

                        return [
                            'shot'   => $d->Shot !== null ? (int) $d->Shot : null,
                            'defect' => filled($d->Defect) ? $d->Defect : null, // null = shot na walang defect
                            'qty'    => (int) $d->Qty,
                            'small'  => $detail,
                        ];
                    })
                    ->sortBy(fn($row) => $row['shot'] ?? 0)
                    ->values()
                    ->all();

                $dimList = $dims->get($key, collect())
                    ->map(fn($m) => [
                        'item'   => $m->DimItem,
                        'set'    => (int) $m->Set,
                        'mode'   => $m->Mode,
                        'specs'  => $m->Specs,
                        'cl'     => $m->CL,
                        'values' => [$m->Value1, $m->Value2, $m->Value3, $m->Value4, $m->Value5],
                        'judge'  => (int) $m->Judge === 0 ? 'O' : 'X',
                    ])
                    ->values()
                    ->all();

                $first = $defectRows->first();

                return [
                    'key'       => $r->PPFNo . '_' . str_replace('.', '-', (string) $r->Checktime),
                    'ppf'       => $r->PPFNo,
                    'checktime' => $r->Checktime,
                    'inspector' => $r->InspectBy,
                    'judgedAt'  => $r->DateJudge,
                    'remarks'   => $r->Remarks,
                    'ngPercent' => $first?->NGPercent,
                    'defects'   => $defectList,
                    'dims'      => $dimList,
                    'failed'    => (int) ($first?->Judgement ?? 0) === 1
                        || collect($dimList)->contains(fn($row) => $row['judge'] === 'X'),
                ];
            })
            ->values()
            ->all();

        return [
            'lot'        => $lot,
            'checkTimes' => $checkTimes,
        ];
    }

    /* ---------------------------------------------------------------
     |  Inspector side
     * ------------------------------------------------------------- */

    public function getInspectorLots(string $employeeId, string $search = '', int $perPage = 5): LengthAwarePaginator
    {
        $latest = MIPIRInspectionRecord::query()
            ->selectRaw('MAX(RECNO)')
            ->where('InspectBy', $employeeId)
            ->groupBy('PPFNo');

        return MIPIRInspectionRecord::query()
            ->select([
                'tblInspectionRecord.PPFNo',
                'tblInspectionRecord.PartNo',
                'tblInspectionRecord.MDNo',
                'tblInspectionRecord.ProdLotNo',
                'tblInspectionRecord.MachineNo',
                'tblInspectionRecord.DateJudge',
                'lc.Status',
                'lc.RequestedAt',
                'lc.RejectReason',
            ])
            ->leftJoin('DB_MIPIR.dbo.LotConfirmation as lc', function ($join) {
                $join->on('lc.PartNo', '=', 'tblInspectionRecord.PartNo')
                    ->on('lc.ProdLotNo', '=', 'tblInspectionRecord.ProdLotNo')
                    ->on('lc.MachineNo', '=', 'tblInspectionRecord.MachineNo');
            })
            ->where('tblInspectionRecord.InspectBy', $employeeId)
            ->whereIn('tblInspectionRecord.RECNO', $latest)
            ->when($search !== '', fn($q) => $q->where('tblInspectionRecord.PPFNo', 'like', "%{$search}%"))
            ->orderByDesc('tblInspectionRecord.DateJudge')
            ->orderByDesc('tblInspectionRecord.PPFNo')
            ->paginate($perPage, ['*'], 'confirmationPage');
    }

    public function requestForPpf(int $ppf, string $employeeId): ?string
    {
        $record = MIPIRInspectionRecord::where('PPFNo', $ppf)
            ->where('InspectBy', $employeeId)
            ->orderByDesc('RECNO')
            ->first(['PartNo', 'ProdLotNo', 'MachineNo']);

        if (!$record || blank($record->ProdLotNo) || blank($record->MachineNo)) {
            return 'This PPF has no production lot or machine number.';
        }

        $keys = [
            'PartNo'    => $record->PartNo,
            'ProdLotNo' => $record->ProdLotNo,
            'MachineNo' => $record->MachineNo,
        ];

        $lot = LotConfirmation::where($keys)->first();

        if ($lot && in_array(strtolower((string) $lot->Status), [LotConfirmation::PENDING, LotConfirmation::CONFIRMED], true)) {
            return 'This lot is already ' . strtolower($lot->Status) . '.';
        }

        $plant = app(CurrentActor::class)->plant();

        LotConfirmation::updateOrCreate($keys, [
            'Plant'        => $plant,
            'Status'       => LotConfirmation::PENDING,
            'RequestedAt'  => now(),
            'PPFNo'        => $ppf,
            'ConfirmedBy'  => null,
            'ConfirmedAt'  => null,
            'RejectedBy'   => null,   // resubmit pagkatapos ma-reject
            'RejectReason' => null,
        ]);

        return null;
    }
}
