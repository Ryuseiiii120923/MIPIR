<?php

namespace App\Inspection\Services\Dimensions;

use App\Inspection\Repositories\Contracts\DimensionMasterRepositoryInterface;

class DimensionsService
{
    public function __construct(
        private DimensionMasterRepositoryInterface $repo
    ) {}

    public function displaySpecification(string $partNo, string $dimensionName): array
    {
        return [
            'specification' => $this->repo->getMasterSpecification($partNo, $dimensionName),
        ];
    }

    /**
     * Derive a row's specType/specNominal/specTolerance/specUpper/specLower
     * fields from a DimensionMaster record. Returns null if no master match.
     */
    public function resolveSpecFromMaster(?array $master): ?array
    {
        if ($master === null) {
            return null;
        }

        $nominal = (float) $master['Specification'];
        $upper   = (float) $master['UpperLimit'];
        $lower   = (float) $master['LowerLimit'];
        $device = $master['Device'];

        if ($lower === 0.00 && $upper === $nominal) {
            return [
                'specType'      => 'max',
                'specNominal'   => (string) $nominal,
                'specTolerance' => '',
                'specUpper'     => '',
                'specLower'     => '',
                'device'         => $device,
            ];
        }

        if ($upper === 0.00 && $lower === $nominal) {
            return [
                'specType'      => 'min',
                'specNominal'   => (string) $nominal,
                'specTolerance' => '',
                'specUpper'     => '',
                'specLower'     => '',
                'device'         => $device,
            ];
        }

        if ($upper != $lower) {
            return [
                'specType'      => 'tolerance_diff',
                'specNominal'   => (string) $nominal,
                'specTolerance' => '',
                'specUpper'     => (string) $upper,
                'specLower'     => (string) $lower,
                'device'         => $device,
            ];
        }

        return [
            'specType'      => 'tolerance',
            'specNominal'   => (string) $nominal,
            'specTolerance' => (string) round($upper, 4),
            'specUpper'     => '',
            'specLower'     => '',
            'device'         => $device,
        ];
    }

    public function computeLimits(array $row): ?array
    {
        $type = $row['specType'] ?? null;
        $nominal = is_numeric($row['specNominal'] ?? null) ? (float) $row['specNominal'] : null;

        if ($type === null || $type === '' || $nominal === null) {
            return null;
        }

        return match ($type) {
            'max' => [
                'upperLimit'        => $nominal,
                'lowerLimit'        => 0.000,
                'judgingUpperLimit' => $nominal,
                'judgingLowerLimit' => 0.000,
            ],
            'min' => [
                'upperLimit'        => 0.000,
                'lowerLimit'        => $nominal,
                'judgingUpperLimit' => 0.000,
                'judgingLowerLimit' => $nominal,
            ],
            'tolerance' => $this->toleranceLimits($row, $nominal),
            'tolerance_diff' => $this->toleranceDiffLimits($row),
            default => null,
        };
    }

    private function toleranceLimits(array $row, float $nominal): ?array
    {
        $tolerance = is_numeric($row['specTolerance'] ?? null) ? (float) $row['specTolerance'] : null;

        if ($tolerance === null) {
            return null;
        }

        return [
            'upperLimit'        => $tolerance,
            'lowerLimit'        => $tolerance,
            'judgingUpperLimit' => $nominal + $tolerance,
            'judgingLowerLimit' => $nominal - $tolerance,
        ];
    }

    private function toleranceDiffLimits(array $row): ?array
    {
        $upper = is_numeric($row['specUpper'] ?? null) ? (float) $row['specUpper'] : null;
        $lower = is_numeric($row['specLower'] ?? null) ? (float) $row['specLower'] : null;

        if ($upper === null || $lower === null) {
            return null;
        }

        return [
            'upperLimit'        => $upper,
            'lowerLimit'        => $lower,
            'judgingUpperLimit' => $upper,
            'judgingLowerLimit' => $lower,
        ];
    }
    public function judgeRow(array $row, ?array $limits): string
    {
        if ($limits === null) {
            return '-';
        }

        $measurements = $row['measurements'] ?? [];
        $filled = array_filter($measurements, fn($v) => $v !== null && trim((string) $v) !== '');

        if (count($filled) === 0) {
            return '-';
        }

        foreach ($filled as $val) {
            if (!is_numeric($val)) {
                continue;
            }

            $ok = match ($row['specType']) {
                'max'            => (float) $val <= (float) $row['specNominal'],
                'min'            => (float) $val >= (float) $row['specNominal'],
                'tolerance'      => (float) $val >= $limits['judgingLowerLimit'] && (float) $val <= $limits['judgingUpperLimit'],
                'tolerance_diff' => (float) $val >= $limits['judgingLowerLimit'] && (float) $val <= $limits['judgingUpperLimit'],
                default          => true,
            };

             $okCL = match ($row['specType']) {
                'max'            => (float) $val <= (float) $row['CL'],
                'min'            => (float) $val >= (float) $row['CL'],
                default          => true,
            };

            if (!$ok || !$okCL) {
                return 'X';
            }
        }

        return 'O';
    }

    public function persistSpecification(string $partNo, string $item, array $row): bool
    {
        $item = trim($item);
        $device = $row['device'];
        if ($item === '' || trim($partNo) === '') {
            return false;
        }

        $limits = $this->computeLimits($row);

        if ($limits === null) {
            return false;
        }

        $this->repo->updateOrCreateSpecification(
            $partNo,
            $item,
            $device,
            number_format((float) $row['specNominal'], 3, '.', ''),
            number_format((float) $limits['upperLimit'], 3, '.', ''),
            number_format((float) $limits['lowerLimit'], 3, '.', '')
        );

        return true;
    }

public function formatSpecification(array $row): string
{
    $limits = $this->computeLimits($row);

    if ($limits === null) {
        return '';
    }

    $nominal = $this->trimNumber((float) $row['specNominal']);

    return match ($row['specType']) {
        'max'            => "MAX {$nominal}",
        'min'            => "MIN {$nominal}",
        'tolerance'      => "{$nominal} ± " . $this->trimNumber((float) $row['specTolerance']),
        'tolerance_diff' => "{$nominal} ("
            . $this->trimNumber($limits['judgingLowerLimit'])
            . ' ~ '
            . $this->trimNumber($limits['judgingUpperLimit'])
            . ')',
        default          => '',
    };
}

private function trimNumber(float $value): string
{
    return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
}
}
