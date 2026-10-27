<?php

namespace App\Inspection\Services;

use App\Inspection\Repositories\XBarRecordRepository;

class XBarChartService
{
    private const FACTORS = [
        2  => [1.128, 1.880, 0.000, 3.267],
        3  => [1.693, 1.023, 0.000, 2.574],
        4  => [2.059, 0.729, 0.000, 2.282],
        5  => [2.326, 0.577, 0.000, 2.114],
        6  => [2.534, 0.483, 0.000, 2.004],
        7  => [2.704, 0.419, 0.076, 1.924],
        8  => [2.847, 0.373, 0.136, 1.864],
        9  => [2.970, 0.337, 0.184, 1.816],
        10 => [3.078, 0.308, 0.223, 1.777],
        11 => [3.173, 0.285, 0.256, 1.744],
        12 => [3.258, 0.266, 0.283, 1.717],
        13 => [3.336, 0.249, 0.307, 1.693],
        14 => [3.407, 0.235, 0.328, 1.672],
        15 => [3.472, 0.223, 0.347, 1.653],
        16 => [3.532, 0.212, 0.363, 1.637],
        17 => [3.588, 0.203, 0.378, 1.622],
        18 => [3.640, 0.194, 0.391, 1.608],
        19 => [3.689, 0.187, 0.403, 1.597],
        20 => [3.735, 0.180, 0.415, 1.585],
        21 => [3.778, 0.173, 0.425, 1.575],
        22 => [3.819, 0.167, 0.434, 1.566],
        23 => [3.858, 0.162, 0.443, 1.557],
        24 => [3.895, 0.157, 0.451, 1.548],
        25 => [3.931, 0.153, 0.459, 1.541],
    ];

    private const CPK_REQUIRED = 1.67;

    public function __construct(
        private XBarRecordRepository $repository
    ) {}

    public function build(string $partNumber, ?string $dimension, int|string $moldDie = 0, int $limit = 25): array
    {
        $subgroups = $this->repository->getSubgroups($partNumber, $dimension, $moldDie, $limit);

        if ($subgroups->isEmpty()) {
            return $this->empty();
        }

        $labels = $means = $ranges = [];

        foreach ($subgroups as $group) {
            $values   = collect($group->measurements);
            $labels[] = $group->label;
            $means[]  = round($values->avg(), 4);         
            $ranges[] = round($values->max() - $values->min(), 4); 
        }

        $count = count($means);

        $n       = (int) $subgroups->first()->subgroup_size;
        $factors = $n >= 2 ? self::FACTORS[min($n, 25)] : null;

        $nonZeroMeans  = array_filter($means, fn ($v) => $v != 0);
        $nonZeroRanges = array_filter($ranges, fn ($v) => $v != 0);

        $grandMean = $nonZeroMeans ? array_sum($nonZeroMeans) / count($nonZeroMeans) : 0.0;
        $rbar      = $nonZeroRanges ? array_sum($nonZeroRanges) / count($nonZeroRanges) : 0.0;

        $computedX = $factors ? [
            'ucl' => $grandMean + $factors[1] * $rbar,
            'cl'  => $grandMean,
            'lcl' => $grandMean - $factors[1] * $rbar,
        ] : null;

        $computedR = $factors ? [
            'ucl' => $factors[3] * $rbar,
            'cl'  => $rbar,
            'lcl' => $factors[2] * $rbar,
        ] : null;

        $limits = $this->repository->getLimits($partNumber, $dimension, $moldDie);

        $xControl = $limits->xControl ?? $computedX;
        $rControl = $limits->rControl ?? $computedR;

        $usl   = $limits->xSpec['usl'] ?? null;
        $lsl   = $limits->xSpec['lsl'] ?? null;
        $sigma = ($factors && $rbar > 0) ? $rbar / $factors[0] : null;

        $cp  = ($sigma && $usl !== null && $lsl !== null) ? ($usl - $lsl) / (6 * $sigma) : null;
        $cpu = ($sigma && $usl !== null) ? ($usl - $grandMean) / (3 * $sigma) : null;
        $cpl = ($sigma && $lsl !== null) ? ($grandMean - $lsl) / (3 * $sigma) : null;
        $cpk = collect([$cpu, $cpl])->filter(fn ($v) => $v !== null)->min();

        return [
            'labels' => $labels,
            'means'  => $means,
            'ranges' => $ranges,
            'x' => [
                'ucl' => $this->line($xControl['ucl'] ?? null, $count),
                'cl'  => $this->line($xControl['cl'] ?? null, $count),
                'lcl' => $this->line($xControl['lcl'] ?? null, $count),
                'usl' => $this->line($limits->xSpec['usl'] ?? null, $count),
                'lsl' => $this->line($limits->xSpec['lsl'] ?? null, $count),
                'csl' => $this->line($limits->xSpec['csl'] ?? null, $count),
            ],
            'r' => [
                'ucl' => $this->line($rControl['ucl'] ?? null, $count),
                'cl'  => $this->line($rControl['cl'] ?? null, $count),
                'lcl' => $this->line($rControl['lcl'] ?? null, $count),
                'usl' => $this->line($limits->rSpec['usl'] ?? null, $count),
                'lsl' => $this->line($limits->rSpec['lsl'] ?? null, $count),
                'csl' => $this->line($limits->rSpec['csl'] ?? null, $count),
            ],
            'outOfControl'  => $this->outside($means, $xControl['ucl'] ?? null, $xControl['lcl'] ?? null),
            'outOfControlR' => $this->outside($ranges, $rControl['ucl'] ?? null, null),
            'stats' => [
                'n'           => $n,
                'subgroups'   => $count,
                'grandMean'   => round($grandMean, 4),
                'rbar'        => round($rbar, 4),
                'cp'          => $cp !== null ? round($cp, 2) : null,
                'cpk'         => $cpk !== null ? round($cpk, 2) : null,
                'judge'       => $cpk === null ? null : ($cpk < self::CPK_REQUIRED ? 'NG' : 'OK'),
                'limitSource' => $limits->xControl ? 'stored' : 'computed',
            ],
        ];
    }

    private function line(?float $value, int $count): array
    {
        return array_fill(0, $count, $value === null ? null : round($value, 4));
    }

    private function outside(array $values, ?float $ucl, ?float $lcl): array
    {
        return array_keys(array_filter(
            $values,
            fn ($v) => ($ucl !== null && $v > $ucl) || ($lcl !== null && $v < $lcl)
        ));
    }

    private function empty(): array
    {
        return [
            'labels' => [], 'means' => [], 'ranges' => [],
            'x' => [], 'r' => [],
            'outOfControl' => [], 'outOfControlR' => [],
            'stats' => [],
        ];
    }
}