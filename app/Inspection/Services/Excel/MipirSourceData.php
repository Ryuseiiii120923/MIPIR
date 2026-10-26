<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Models\SmallDefect;
use App\Inspection\Services\PartFamily;
use App\Inspection\Support\PartNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MipirSourceData
{
    public function header(int $ppf): ?array
    {
        $rows = MIPIRInspectionRecord::where('PPFNo', $ppf)->orderBy('RECNO')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $latest = $rows->last();
        $remarks = [];

        foreach ($rows as $row) {
            $remarks[(string) $row->Checktime] = (string) ($row->Remarks ?? '');
        }

        return [
            'partNo' => trim((string) $latest->PartNo),
            'partName' => '',
            'moldNo' => (string) $latest->MDNo,
            'machineNo' => (string) $latest->MachineNo,
            'lotNo' => (string) $latest->ProdLotNo,
            'inspector' => (string) $latest->InspectBy,
            'cavity' => (float) $latest->NoofCavity,
            'nqr' => (float) $latest->NQR,
            'checkTimes' => array_map('strval', array_keys($remarks)),
            'remarks' => $remarks,
        ];
    }

    public function defects(int $ppf): Collection
    {
        return Defect::where('PPFNo', $ppf)
            ->orderBy('Checktime')
            ->orderBy('Shot')
            ->orderBy('RECNO')
            ->get();
    }

    public function smallDefects(int $ppf): Collection
    {
        return SmallDefect::where('PPFNo', $ppf)
            ->orderBy('Checktime')
            ->orderBy('Shot')
            ->orderBy('RECNO')
            ->get();
    }

    public function dimensions(int $ppf, string $dimItem): Collection
    {
        return MIPIRDimensionMeasure::where('PPFNo', $ppf)
            ->where('DimItem', $dimItem)
            ->orderBy('Checktime')
            ->orderBy('Set')
            ->orderBy('RECNO')
            ->get();
    }

    public function measurements($row): array
    {
        $values = [];

        for ($i = 1; $i <= 5; $i++) {
            $value = $row->{"Value{$i}"};

            if ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    public function isTightened($row): bool
    {
        return strcasecmp(trim((string) $row->Mode), 'tightened') === 0;
    }

    public function groupPpfs(int $ppf): array
    {
        $record = MIPIRInspectionRecord::where('PPFNo', $ppf)
            ->orderByDesc('RECNO')
            ->first(['PartNo', 'MDNo']);

        if ($record === null) {
            return [];
        }

        $partNo = (string) $record->PartNo;
        $mdNo   = (string) $record->MDNo;

        return MIPIRInspectionRecord::inGroup($partNo, $mdNo, $this->sameNotationPartNos($partNo, $mdNo))
            ->select('PPFNo')
            ->selectRaw('MIN(DateJudge) as first_at')
            ->groupBy('PPFNo')
            ->orderBy('first_at')
            ->pluck('PPFNo')
            ->map(fn($p) => (int) $p)
            ->all();
    }

    private function sameNotationPartNos(string $partNo, string $mdNo): array
    {
        $family = app(PartFamily::class);
        $md     = trim($mdNo);

        return MIPIRInspectionRecord::query()
            ->when(
                $md === '',
                fn($q) => $q->where(fn($w) => $w->whereNull('MDNo')->orWhere('MDNo', '')),
                fn($q) => $q->where('MDNo', $md)
            )
            ->distinct()
            ->pluck('PartNo')
            ->map(fn($p) => trim((string) $p))
            ->filter(fn($p) => $p !== '' && $family->sameNotation($p, $partNo))
            ->values()
            ->all();
    }

    public function groupContext(int $ppf): ?array
    {
        $headers = [];

        foreach ($this->groupPpfs($ppf) as $groupPpf) {
            $header = $this->header($groupPpf);

            if ($header !== null) {
                $headers[$groupPpf] = $header;
            }
        }

        if ($headers === []) {
            return null;
        }

        $partNos = collect($headers)
            ->pluck('partNo')
            ->map(fn($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->sort(fn($a, $b) => [strlen($a), $a] <=> [strlen($b), $b])
            ->values()
            ->all();

        $moldNos = collect($headers)
            ->pluck('moldNo')
            ->map(fn($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
        return [
            'headers'    => $headers,
            'partNos'    => $partNos,
            'partNo'     => implode(' / ', $partNos),
            'basePartNo' => PartNumber::base($partNos[0] ?? ''),
            'moldNo'     => $moldNos[0] ?? '',
        ];
    }
}
