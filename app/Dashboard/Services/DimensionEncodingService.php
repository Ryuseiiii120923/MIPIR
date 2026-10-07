<?php

namespace App\Dashboard\Services;

use App\Dashboard\Repositories\DimensionEncodingRepository;
use App\Dashboard\Repositories\SpecsControlRepository;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DimensionEncodingService
{
    private const SYMBOL_EXTENSIONS = ['png', 'bmp']; // checked in this order

    private const LIMIT_FIELDS = ['CCLx', 'UCLx', 'LCLx', 'CSLr', 'USLr', 'LSLr', 'CCLr', 'UCLr', 'LCLr'];

    public function __construct(
        private readonly DimensionEncodingRepository $repo,
        private readonly SpecsControlRepository $specsRepo,
    ) {}

    public function list(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repo->paginate($search, $perPage)->through(function ($row) {
            $row->symbol_url = $this->symbolUrl($row->Symbol);
            return $row;
        });
    }

    /**
     * Returns the record shaped for the Livewire form, or null if it no longer exists.
     */
    public function find(int $recNo): ?array
    {
        $row = $this->repo->find($recNo);

        if ($row === null) {
            return null;
        }

        return [
            'partNo'         => (string) $row->PartNo,
            'dimensionNo'    => (string) $row->DimensionNo,
            'symbol'         => $row->Symbol !== null ? (string) $row->Symbol : '',
            'dimensionName'  => (string) $row->DimensionName,
            'specification'  => (string) ($row->Specification ?? ''),
            'upperLimit'     => $row->UpperLimit !== null ? (string) $row->UpperLimit : '',
            'lowerLimit'     => $row->LowerLimit !== null ? (string) $row->LowerLimit : '',
            'device'         => (string) ($row->Device ?? ''),
            'unit'           => (string) ($row->Unit ?? ''),
            'judgementClsIP' => (string) ($row->JudgementClsIP ?? ''),
            'judgementClsMP' => (string) ($row->JudgementClsMP ?? ''),
            'samplingQtyIP'  => $row->SamplingQtyIP !== null ? (string) $row->SamplingQtyIP : '',
            'samplingQtyMP'  => $row->SamplingQtyMP !== null ? (string) $row->SamplingQtyMP : '',
            'xBar'           => (bool) $row->XBar,
        ];
    }

    /**
     * @throws DomainException when DimensionNo already exists for the part no.
     */
    public function save(array $data, ?int $recNo, string $encoder): void
    {
        // Create is currently disabled (repository create() is commented out)
        if ($recNo === null) {
            throw new DomainException('Creating new dimensions is disabled.');
        }

        $payload = $this->toPayload($data);

        if ($this->repo->existsByPartAndDimensionNo($payload['PartNo'], $payload['DimensionNo'], $recNo)) {
            throw new DomainException('Dimension No already exists for this part no.');
        }

        $payload['Enc']  = $encoder;
        $payload['DEnc'] = now();

        $this->repo->update($recNo, $payload);
    }

    // ------------------------------------------------------------------
    // Specification and control limits (X-Bar dimensions)
    // ------------------------------------------------------------------

    public function saveControlLimits(string $partNo, string $dimItem, array $limits, string $encoder): void
    {
        $payload = [];

        foreach (self::LIMIT_FIELDS as $field) {
            $value = $limits[$field] ?? null;
            $payload[$field] = ($value === null || $value === '') ? null : (float) $value;
        }

        $this->specsRepo->saveLimit(trim($partNo), trim($dimItem), $payload, $encoder);
    }

    // ------------------------------------------------------------------
    // Symbol images (storage/app/Symbol/{n}.png|bmp)
    // ------------------------------------------------------------------

    /**
     * Absolute path of the symbol image, or null when there is none.
     * Negative, blank, or non-numeric symbol values mean "no symbol".
     */
    public function resolveSymbolFile(mixed $symbol): ?string
    {
        $value = trim((string) $symbol);

        // digits only: rejects negatives, decimals, and path tricks like ../
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        $number = (int) $value;

        foreach (self::SYMBOL_EXTENSIONS as $ext) {
            $path = storage_path("app/Symbol/{$number}.{$ext}");

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function symbolUrl(mixed $symbol): ?string
    {
        if ($this->resolveSymbolFile($symbol) === null) {
            return null;
        }

        return route('symbols.show', ['symbol' => (int) trim((string) $symbol)]);
    }

    // ------------------------------------------------------------------

    private function toPayload(array $data): array
    {
        return [
            'PartNo'         => trim($data['partNo']),
            'DimensionNo'    => (int) $data['dimensionNo'],
            'Symbol'         => $this->intOrNull($data['symbol'] ?? null),
            'DimensionName'  => trim($data['dimensionName']),
            'Specification'  => trim($data['specification']),
            'UpperLimit'     => $this->nullIfBlank($data['upperLimit'] ?? null),
            'LowerLimit'     => $this->nullIfBlank($data['lowerLimit'] ?? null),
            'Device'         => $this->nullIfBlank($data['device'] ?? null),
            'Unit'           => $this->nullIfBlank($data['unit'] ?? null),
            'JudgementClsIP' => $this->nullIfBlank($data['judgementClsIP'] ?? null),
            'JudgementClsMP' => $this->nullIfBlank($data['judgementClsMP'] ?? null),
            'SamplingQtyIP'  => $this->intOrNull($data['samplingQtyIP'] ?? null),
            'SamplingQtyMP'  => $this->intOrNull($data['samplingQtyMP'] ?? null),
            'XBar'           => (bool) ($data['xBar'] ?? false),
        ];
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function intOrNull(?string $value): ?int
    {
        $value = $value !== null ? trim($value) : '';

        return $value === '' ? null : (int) $value;
    }
}