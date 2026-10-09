<?php

namespace App\Dashboard\Services;

use App\Dashboard\Repositories\DimensionEncodingRepository;
use App\Dashboard\Repositories\SpecsControlRepository;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

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
     * Creates the dimension when $recNo is null, otherwise updates it.
     *
     * @throws DomainException when DimensionNo already exists for the part no.
     */
    public function save(array $data, ?int $recNo, string $encoder): void
    {
        $payload = $this->toPayload($data);

        if ($this->repo->existsByPartAndDimensionNo($payload['PartNo'], $payload['DimensionNo'], $recNo)) {
            throw new DomainException('Dimension No already exists for this part no.');
        }

        $payload['Enc']  = $encoder;
        $payload['DEnc'] = now();

        if ($recNo === null) {
            $this->repo->create($payload);
            return;
        }

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

    public function symbolExists(string|int|null $symbol): bool
    {
        if (! is_numeric($symbol) || (int) $symbol < 0) {
            return false;
        }

        return $this->findSymbolFile((int) $symbol) !== null;
    }

    private function findSymbolFile(int $symbol): ?string
    {
        $matches = glob(storage_path("app/Symbol/{$symbol}.*")) ?: [];

        return $matches[0] ?? null;
    }

    public function storeSymbol(int $symbol, UploadedFile $file): void
    {
        if ($symbol < 0) {
            throw new \DomainException('A negative symbol number has no image.');
        }

        // Never overwrite a symbol that already exists
        if ($this->findSymbolFile($symbol) !== null) {
            throw new \DomainException("Symbol {$symbol} already exists.");
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        File::ensureDirectoryExists(storage_path('app/Symbol'));
        File::put(storage_path("app/Symbol/{$symbol}.{$extension}"), $file->get());
    }

    /**
     * Every symbol image in storage/app/Symbol, sorted by symbol number.
     * Files not named by a number are ignored.
     *
     * @return array<int, array{symbol: int, url: string}>
     */
    public function listSymbols(): array
    {
        return collect(glob(storage_path('app/Symbol/*.*')) ?: [])
            ->map(fn(string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->filter(fn(string $name) => ctype_digit($name))
            ->map(fn(string $name) => (int) $name)
            ->unique()
            ->sort()
            ->map(fn(int $symbol) => [
                'symbol' => $symbol,
                'url'    => $this->symbolUrl((string) $symbol),
            ])
            ->filter(fn(array $item) => filled($item['url']))
            ->values()
            ->all();
    }

    public function nextDimensionNo(string $partNo):int
    {
        return $this->repo->nextDimensionNo($partNo);
    }
}
