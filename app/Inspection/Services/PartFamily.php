<?php

namespace App\Inspection\Services;

use App\Domain\Master\SEIHIN;
use App\Inspection\Support\PartNumber;
use Illuminate\Support\Facades\Log;
use Throwable;

class PartFamily
{
    /** @var string[]|null */
    private ?array $master = null;
    private ?array $materials = null;

    public function base(string $partNo): string
    {
        $partNo = $this->normalize($partNo);

        if ($partNo === '') {
            return '';
        }

        $roots = array_filter(
            $this->masterPartNos(),
            fn(string $root) => $root !== ''
                && $this->isSameOrChild($partNo, $root)
                && $this->sameMaterial($partNo, $root)
        );

        $candidates = $roots !== []
            ? array_values($roots)
            : [PartNumber::guessBase($partNo)];

        usort($candidates, fn($a, $b) => [strlen($a), $a] <=> [strlen($b), $b]);

        return $candidates[0];
    }

    public function members(string $partNo): array
    {
        $base = $this->base($partNo);

        $members = array_values(array_filter(
            $this->masterPartNos(),
            fn(string $p) => $this->isSameOrChild($p, $base) && $this->sameMaterial($p, $partNo)
        ));

        usort($members, fn($a, $b) => [strlen($a), $a] <=> [strlen($b), $b]);

        return $members;
    }

    public function foreign(string $partNo): array
    {
        $base = $this->base($partNo);

        return array_values(array_filter(
            $this->masterPartNos(),
            fn(string $p) => $this->isSameOrChild($p, $base) && ! $this->sameMaterial($p, $partNo)
        ));
    }

    public function sameNotation(string $a, string $b): bool
    {
        return $this->normalize($a) !== '' && $this->normalize($b) !== ''
            && ($this->isSameOrChild($a, $b) || $this->isSameOrChild($b, $a))
            && $this->sameMaterial($a, $b);
    }

    public function flush(): void
    {
        $this->master = null;
        $this->materials = null;
    }


    private function masterPartNos(): array
    {
        if ($this->master !== null) {
            return $this->master;
        }

        try {
            return $this->master = SEIHIN::query()
                ->distinct()
                ->pluck('品番')
                ->map(fn($p) => $this->normalize((string) $p))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('PartFamily master lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** @return array<string,string> */
    private function masterMaterials(): array
    {
        if ($this->materials !== null) {
            return $this->materials;
        }

        try {
            return $this->materials = SEIHIN::query()
                ->get(['品番', '材料名'])
                ->mapWithKeys(fn($r) => [
                    strtoupper($this->normalize((string) data_get($r, '品番')))
                    => $this->materialFamily((string) data_get($r, '材料名')),
                ])
                ->all();
        } catch (Throwable $e) {
            Log::warning('PartFamily material lookup failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** 99407-X5 / 99407-X => 99407 */
    private function materialFamily(string $material): string
    {
        return strtoupper(trim(explode('-', $this->normalize($material))[0]));
    }

    private function materialOf(string $partNo): string
    {
        return $this->masterMaterials()[strtoupper($this->normalize($partNo))] ?? '';
    }

    /** Kapag walang materyal na alam ang isa sa dalawa, huwag harangan. */
    private function sameMaterial(string $a, string $b): bool
    {
        $ma = $this->materialOf($a);
        $mb = $this->materialOf($b);

        return $ma === '' || $mb === '' || $ma === $mb;
    }

    private function isSameOrChild(string $partNo, string $root): bool
    {
        $rest = $this->remainder($partNo, $root);

        if ($rest === null) {
            return false;
        }

        if ($rest === '') {
            return true;
        }

        $next = $rest[0];

        if ($next === ' ' || $next === '-') {
            return true;
        }

        if (! ctype_alpha($next)) {
            return false;
        }

        if (preg_match('/^[A-Za-z0-9]+\.[A-Za-z0-9]+\.\d+$/', $root) === 1) {
            return true;
        }

        $material = $this->materialOf($partNo);

        return ctype_digit(substr(rtrim($root), -1))
            && $material !== ''
            && $material === $this->materialOf($root);
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function remainder(string $partNo, string $root): ?string
    {
        $i = 0;
        $n = strlen($partNo);

        foreach (str_split($root) as $char) {
            if (ctype_space($char)) {
                continue;
            }

            while ($i < $n && ctype_space($partNo[$i])) {
                $i++;
            }

            if ($i >= $n || strcasecmp($partNo[$i], $char) !== 0) {
                return null;
            }

            $i++;
        }

        return substr($partNo, $i);
    }
}
