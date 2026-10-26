<?php

namespace App\Traits;

trait GroupsExcelFiles
{
    protected function groupStem(array $context): string
    {
        $clean = fn (string $v) => trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $v), '-. ');

        $base = $clean((string) ($context['basePartNo'] ?? $context['partNo'] ?? '')) ?: 'UNKNOWN';
        $md   = $clean((string) ($context['moldNo'] ?? '')) ?: 'NOMD';

        return "{$base}_{$md}";
    }
}