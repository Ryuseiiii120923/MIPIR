<?php

namespace App\Inspection\Support;

use App\Inspection\Services\PartFamily;

class PartNumber
{
    public static function base(string $partNo): string
    {
        return app(PartFamily::class)->base($partNo);
    }

    public static function guessBase(string $partNo): string
    {
        $partNo = trim((string) preg_replace('/\s+/', ' ', $partNo));

        if ($partNo === '') {
            return '';
        }

        $tokens = explode(' ', $partNo);

        if (preg_match('/^[A-Za-z0-9]+\.[A-Za-z0-9]+\.\d+/', $tokens[0], $m)) {
            return $m[0];
        }

        if (count($tokens) >= 2 && preg_match('/\d/', $tokens[1])) {
            return $tokens[0] . ' ' . explode('-', $tokens[1])[0];
        }

        return $tokens[0];
    }

    public static function sameFamily(string $a, string $b): bool
    {
        return strcasecmp(self::base($a), self::base($b)) === 0;
    }

    public function partNumbers(array $context, array $rows): array
{
    return collect($rows)
        ->map(fn(array $row) => trim((string) ($row['partNo'] ?? $context['partNo'] ?? '')))
        ->prepend(trim((string) ($context['partNo'] ?? '')))
        ->filter(fn($p) => $p !== '')
        ->unique()
        ->values()
        ->all();
}
}