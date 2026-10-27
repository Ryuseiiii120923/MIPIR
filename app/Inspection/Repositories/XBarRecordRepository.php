<?php

namespace App\Inspection\Repositories;

use App\Dashboard\Models\XBar\ControlSpecsLimit;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Services\Excel\MipirSourceData;
use Illuminate\Support\Collection;

class XBarRecordRepository
{
    private const MAX_VALUES_BY_PART = [
        '7W0706-0500A' => 2,
    ];
    private const CHECKTIME_ORDER = ['F' => 1, 'MI' => 2, 'E' => 3];

    public function __construct(
        private MipirSourceData $source
    ) {}


    public function getSubgroups(string $partNumber, ?string $dimension, int|string $moldDie, int $limit): Collection
    {
        $parts = $this->familyPartNumbers($partNumber, $moldDie);
        $md    = trim((string) $moldDie);

        $base = fn() => MIPIRDimensionMeasure::query()
            ->whereIn('PartNo', $parts)
            ->when(
                $md === '',
                fn($q) => $q->where(fn($w) => $w->whereNull('MDNo')->orWhere('MDNo', '')),
                fn($q) => $q->where('MDNo', $md)
            )
            ->when($dimension, fn($q) => $q->where('DimItem', $dimension))
            ->where('ForXBar', 1);

        // Lahat ng (PPFNo + Checktime) at ang unang RECNO ng bawat isa
        $keys = $base()
            ->select('PPFNo', 'Checktime')
            ->selectRaw('MIN(RECNO) as first_recno')
            ->groupBy('PPFNo', 'Checktime')
            ->get();

        if ($keys->isEmpty()) {
            return collect();
        }

        // Unang RECNO ng bawat PPF, para magkakasama ang check times ng iisang PPF
        $ppfFirst = $keys
            ->groupBy(fn($k) => trim((string) $k->PPFNo))
            ->map(fn($group) => (int) $group->min('first_recno'));

        // PPF muna, tapos check time, tapos pinakabagong N
        $selected = $keys
            ->sortBy(fn($k) => [
                $ppfFirst->get(trim((string) $k->PPFNo)),
                self::CHECKTIME_ORDER[strtoupper(trim((string) $k->Checktime))] ?? 99,
                (int) $k->first_recno,
            ])
            ->take(-$limit)
            ->values();

        $order = $selected->mapWithKeys(fn($k, $i) => [
            $this->key($k->PPFNo, $k->Checktime) => $i,
        ]);

        $rows = $base()
            ->whereIn('PPFNo', $selected->pluck('PPFNo')->unique()->all())
            ->orderBy('Checktime')
            ->orderBy('Set')
            ->orderBy('RECNO')
            ->get()
            ->filter(fn($r) => $order->has($this->key($r->PPFNo, $r->Checktime)));

        $multiPart = $rows->pluck('PartNo')->map(fn($p) => trim((string) $p))->unique()->count() > 1;

        return $rows
            ->groupBy(fn($r) => $this->key($r->PPFNo, $r->Checktime))
            ->map(function ($group, $key) use ($order, $multiPart) {
                $first = $group->first();

                $values = $group->flatMap(function ($row) {
                    $max = self::MAX_VALUES_BY_PART[trim((string) $row->PartNo)] ?? 5;

                    return collect($this->source->measurements($row))
                        ->filter(fn($v) => is_numeric($v))
                        ->map(fn($v) => (float) $v)
                        ->take($max)
                        ->values();
                })->values()->all();

                $label = trim(($first->PPFNo ? $first->PPFNo . ' · ' : '') . $first->Checktime);

                return (object) [
                    'measurements'  => $values,
                    'subgroup_size' => count($values),
                    'label'         => $multiPart ? trim((string) $first->PartNo) . ' | ' . $label : $label,
                    'order'         => $order->get($key),
                ];
            })
            ->filter(fn($g) => $g->subgroup_size > 0)
            ->sortBy('order')
            ->values();
    }

    public function getLimits(string $partNumber, ?string $dimension, int|string $moldDie = 0): object
    {
        $parts = $this->familyPartNumbers($partNumber, $moldDie);

        $columns = ['UCLx', 'CCLx', 'LCLx', 'USLx', 'LSLx', 'CSLx', 'UCLr', 'CCLr', 'LCLr', 'USLr', 'LSLr', 'CSLr'];

        $spec = ControlSpecsLimit::query()
            ->whereIn('PartNo', $parts)
            ->when($dimension, fn($q) => $q->where('DimItem', $dimension))
            ->get()
            ->sortBy(fn($s) => trim((string) $s->PartNo) === trim($partNumber) ? 0 : 1)
            ->first(fn($s) => collect($columns)->contains(fn($c) => (float) $s->{$c} !== 0.0));

        if (! $spec) {
            return (object) ['xControl' => null, 'xSpec' => null, 'rControl' => null, 'rSpec' => null];
        }

        return (object) [
            'xControl' => $this->pick($spec, ['ucl' => 'UCLx', 'cl' => 'CCLx', 'lcl' => 'LCLx']),
            'xSpec'    => $this->pick($spec, ['usl' => 'USLx', 'lsl' => 'LSLx', 'csl' => 'CSLx']),
            'rControl' => $this->pick($spec, ['ucl' => 'UCLr', 'cl' => 'CCLr', 'lcl' => 'LCLr']),
            'rSpec'    => $this->pick($spec, ['usl' => 'USLr', 'lsl' => 'LSLr', 'csl' => 'CSLr']),
        ];
    }

    private function pick($spec, array $map): ?array
    {
        $out = [];

        foreach ($map as $key => $column) {
            $out[$key] = (float) $spec->{$column};
        }

        return array_filter($out) === [] ? null : $out;
    }

    private function key(mixed $ppf, mixed $checktime): string
    {
        return trim((string) $ppf) . '|' . trim((string) $checktime);
    }

    private function familyPartNumbers(string $partNumber, int|string $moldDie): array
    {
        $partNumber = trim($partNumber);

        $family = $this->source->sameNotationPartNos($partNumber, trim((string) $moldDie));

        return array_values(array_unique(array_merge([$partNumber], $family)));
    }
}
