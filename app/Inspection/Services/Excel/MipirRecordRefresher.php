<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\CheckTime;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Support\PartNumber;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MipirRecordRefresher
{
    private const MAX_SLOTS_PER_SHEET = 21;
    private const PART_SEPARATOR      = '/ ';

    public function refresh(int $ppf, ?string $confirmedBy = null): void
    {
        try {
            $group = app(MipirSourceData::class)->groupContext($ppf);

            if ($group === null) {
                return;
            }

            $groupPpfs = array_map('strval', array_keys($group['headers']));
            $sheets    = $this->buildSheets($groupPpfs);

            $from = null;
            foreach ($sheets as $i => $sheet) {
                if (in_array((string) $ppf, $sheet['ppfs'], true)) {
                    $from = $i;
                    break;
                }
            }

            if ($from === null) {
                return;
            }

            $dir = storage_path('app/excel');
            File::ensureDirectoryExists($dir);

            $groupKey = $this->groupKey($group);
            $files    = $this->existingFiles($dir, $groupKey);
            $tracker  = app(MIPIRRecordCycleTracker::class);

            foreach ($sheets as $i => $sheet) {
                if ($i < $from) {
                    continue;
                }

                // part numbers na talagang nasa sheet na ito (hindi buong group)
                $partNos  = $this->partNosOf($group['headers'], $sheet['ppfs']);
                $variant  = $tracker->variantFor($partNos);
                $dimItems = $tracker->resolveDimItems($partNos, $sheet['ppfs']);

                if ($dimItems === []) {
                    Log::warning('MIPIR record skipped: no dimension items', [
                        'partNos' => $partNos,
                        'sheet'   => $i + 1,
                        'ppfs'    => $sheet['ppfs'],
                    ]);

                    continue;
                }

                $path = $files[$i] ?? $this->newFilePath($dir, $groupKey);

                app(InspectionRecordService::class)->generate(
                    $sheet['lots'],
                    $dimItems,
                    $path,
                    $variant,
                    $confirmedBy,
                    implode(self::PART_SEPARATOR, $partNos) // F00VC38005-5/ F 00V C38 005
                );

                $files[$i] = $path;
            }
        } catch (\Throwable $e) {
            Log::error('MIPIR record refresh failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
                'at'    => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    /** @param string[] $ppfs lahat ng PPF ng group (base part + die) */
    private function buildSheets(array $ppfs): array
    {
        $encoded = CheckTime::whereIn('PPFNo', $ppfs)
            ->get(['PPFNo', 'Checktime', 'DateEncode'])
            ->mapWithKeys(fn($c) => ["{$c->PPFNo}|{$c->Checktime}" => (string) $c->DateEncode])
            ->all();

        $lots = MIPIRInspectionRecord::whereIn('PPFNo', $ppfs)
            ->get(['PPFNo', 'ProdLotNo', 'Checktime', 'DateJudge'])
            ->map(fn($r) => [
                'ppf'       => (string) $r->PPFNo,
                'checktime' => (string) $r->Checktime,
                'lot'       => (string) $r->ProdLotNo,
                'at'        => $encoded["{$r->PPFNo}|{$r->Checktime}"] ?? (string) $r->DateJudge,
            ])
            ->sortBy('at')
            ->values()
            ->groupBy('lot')
            ->map(fn($group) => $group
                ->map(fn($r) => ['ppf' => $r['ppf'], 'checktime' => $r['checktime']])
                ->values()
                ->all())
            ->values()
            ->all();

        $sheets  = [];
        $current = ['lots' => [], 'ppfs' => [], 'slots' => 0];

        foreach ($lots as $entries) {
            $count = count($entries);

            if ($current['lots'] !== [] && $current['slots'] + $count > self::MAX_SLOTS_PER_SHEET) {
                $sheets[] = $current;
                $current  = ['lots' => [], 'ppfs' => [], 'slots' => 0];
            }

            $current['lots'][] = $entries;
            $current['slots'] += $count;
            $current['ppfs']   = array_values(array_unique(
                array_merge($current['ppfs'], array_column($entries, 'ppf'))
            ));
        }

        if ($current['lots'] !== []) {
            $sheets[] = $current;
        }

        return $sheets;
    }

    /** Unique part numbers ng mga PPF sa sheet; same sort ng groupContext (haba muna, tapos alpha). */
    private function partNosOf(array $headers, array $sheetPpfs): array
    {
        return collect($sheetPpfs)
            ->map(fn($p) => trim((string) ($headers[(int) $p]['partNo'] ?? '')))
            ->filter()
            ->unique()
            ->sort(fn($a, $b) => [strlen($a), $a] <=> [strlen($b), $b])
            ->values()
            ->all();
    }

    private function groupKey(array $group): string
    {
        $mold = trim((string) $group['moldNo']);

        return $this->safeName($group['basePartNo']) . '_' . $this->safeName($mold !== '' ? $mold : 'NOMD');
    }

    private function existingFiles(string $dir, string $groupKey): array
    {
        $prefix = "MIPIR_{$groupKey}_";

        $files = collect(File::files($dir))
            ->filter(fn($f) => str_starts_with($f->getFilename(), $prefix) && $f->getExtension() === 'xlsx')
            ->map(fn($f) => $f->getPathname())
            ->all();

        usort($files, 'strnatcasecmp');

        return array_values($files);
    }

    private function newFilePath(string $dir, string $groupKey): string
    {
        $base = sprintf('MIPIR_%s_%s', $groupKey, now()->format('Y-m-d'));
        $path = $dir . DIRECTORY_SEPARATOR . $base . '.xlsx';

        for ($n = 2; file_exists($path); $n++) {
            $path = $dir . DIRECTORY_SEPARATOR . "{$base}_{$n}.xlsx";
        }

        return $path;
    }

    private function safeName(string $value): string
    {
        return trim((string) preg_replace('/[\\\\\/:*?"<>|\s]+/', '-', $value), '-. ');
    }
}