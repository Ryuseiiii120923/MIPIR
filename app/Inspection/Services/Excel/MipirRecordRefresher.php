<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\CheckTime;
use App\Inspection\Models\MIPIRInspectionRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MipirRecordRefresher
{
    private const MAX_SLOTS_PER_SHEET = 21; // row capacity ng template

    /**
     * I-rebuild ang MIPIR_{PartNumber}_{petsa}.xlsx ng part number ng PPF na ito.
     * Ang mga sheet na nauuna sa sheet ng PPF ay hindi ginagalaw.
     */
    public function refresh(int $ppf): void
    {
        try {
            $partNo = trim((string) MIPIRInspectionRecord::where('PPFNo', $ppf)->value('PartNo'));

            if ($partNo === '') {
                return;
            }

            $sheets = $this->buildSheets($partNo);

            // Unang sheet na may PPF na ito
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

            $safePart = $this->safeName($partNo);
            $files    = $this->existingFiles($dir, $safePart);

            foreach ($sheets as $i => $sheet) {
                if ($i < $from) {
                    continue;
                }

                $tracker  = app(MIPIRRecordCycleTracker::class);
                $variant  = $tracker->variantFor($partNo);
                $dimItems = $tracker->resolveDimItems($partNo, $sheet['ppfs']);

                if ($dimItems === []) {
                    Log::warning('MIPIR record skipped: no dimension items', [
                        'partNo' => $partNo,
                        'sheet'  => $i + 1,
                        'ppfs'   => $sheet['ppfs'],
                    ]);

                    continue;
                }

                $path = $files[$i] ?? $this->newFilePath($dir, $safePart);

                app(InspectionRecordService::class)->generate($sheet['lots'], $dimItems, $path, $variant);

                $files[$i] = $path;
            }
        } catch (\Throwable $e) {
            // Hindi dapat mabigo ang pag-save dahil sa Excel
            Log::error('MIPIR record refresh failed', [
                'PPFNo' => $ppf,
                'error' => $e->getMessage(),
                'at'    => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
    }

    /**
     * Lahat ng lot ng part number, pinakaluma muna, hinati sa mga sheet na 21 rows bawat isa.
     *
     * @return array<int, array{lots: array, ppfs: array<int,string>, slots: int}>
     */
    private function buildSheets(string $partNo): array
    {
        $encoded = CheckTime::where('PartNo', $partNo)
            ->get(['PPFNo', 'Checktime', 'DateEncode'])
            ->mapWithKeys(fn($c) => ["{$c->PPFNo}|{$c->Checktime}" => (string) $c->DateEncode])
            ->all();

        $lots = MIPIRInspectionRecord::where('PartNo', $partNo)
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

            // Hindi na kasya sa sheet: magsimula ng bago
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

    /** MIPIR_{PartNo}_*.xlsx ng part number, pinakaluma muna */
    private function existingFiles(string $dir, string $safePart): array
    {
        $prefix = "MIPIR_{$safePart}_";

        $files = collect(File::files($dir))
            ->filter(fn($f) => str_starts_with($f->getFilename(), $prefix) && $f->getExtension() === 'xlsx')
            ->map(fn($f) => $f->getPathname())
            ->all();

        usort($files, 'strnatcasecmp');

        return array_values($files);
    }

    /** MIPIR_{PartNo}_{petsa}.xlsx, may suffix kung may kapareho na ngayong araw */
    private function newFilePath(string $dir, string $safePart): string
    {
        $base = sprintf('MIPIR_%s_%s', $safePart, now()->format('Y-m-d'));
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
