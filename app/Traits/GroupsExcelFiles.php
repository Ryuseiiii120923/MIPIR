<?php

namespace App\Traits;
use Illuminate\Support\Facades\File;

trait GroupsExcelFiles
{
    protected function groupStem(array $context): string
    {
        $clean = fn(string $v) => trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $v), '-. ');

        $base = $clean((string) ($context['basePartNo'] ?? $context['partNo'] ?? '')) ?: 'UNKNOWN';
        $md   = $clean((string) ($context['moldNo'] ?? '')) ?: 'NOMD';

        return "{$base}_{$md}";
    }

    protected function partsLabel(array $context): string
    {
        $list = collect(explode('/', (string) ($context['partNo'] ?? '')))
            ->map(fn($p) => trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', '-', trim($p)), ' .'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($list === []) {
            return 'UNKNOWN';
        }

        $label = implode(' + ', $list);

        return mb_strlen($label) > 100 ? $list[0] . ' +' . (count($list) - 1) . ' more' : $label;
    }

    protected function moldSuffix(array $context): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($context['moldNo'] ?? '')), '-. ') ?: 'NOMD';
    }

    protected function groupFileName(string $prefix, array $context, string $ext = 'xlsx'): string
    {
        return $prefix . $this->partsLabel($context) . ' - ' . $this->moldSuffix($context) . '.' . $ext;
    }

    protected function familyDirectory(string $outputRoot, string $partNo): string
    {
        $folder = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '_', $partNo), ' ._');

        return storage_path($outputRoot) . DIRECTORY_SEPARATOR . ($folder !== '' ? $folder : 'UNKNOWN');
    }

    /** Mga file ng family at mold na ito: bagong pangalan at ang lumang `{base}_{mold}`. */
    protected function familyFiles(string $dir, string $prefix, array $context, string $ext = 'xlsx'): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $suffix = ' - ' . $this->moldSuffix($context) . '.' . $ext;
        $legacy = $prefix . $this->groupStem($context) . '.' . $ext;

        return collect(File::files($dir))
            ->filter(fn($f) => $f->getFilename() === $legacy
                || (str_starts_with($f->getFilename(), $prefix) && str_ends_with($f->getFilename(), $suffix)))
            ->map(fn($f) => $f->getPathname())
            ->values()
            ->all();
    }
}
