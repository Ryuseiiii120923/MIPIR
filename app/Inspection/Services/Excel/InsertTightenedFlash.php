<?php

namespace App\Inspection\Services\Excel;

use App\Traits\ExpandsSheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InsertTightenedFlash
{
    use ExpandsSheet;
    private const TEMPLATE = 'app/excel-template/tightened-monitoring/Flash Thickness Template.xlsx';
    private const OUTPUT_ROOT = 'app/exports/tightened-monitoring/Flash Thickness';
    private const FIRST_BLOCK_COLUMN = 4;
    private const READINGS_PER_BLOCK = 5;
    private const SHEET_SIZES = [2, 4, 5, 6, 10, 12];

    private const LAYOUTS = [
        2 => [
            ['part' => 'A2', 'mold' => 'A3', 'limit' => 'D5', 'firstRow' => 9, 'lastRow' => 58, 'firstBlock' => 1, 'blockCount' => 2, 'inspectorCol' => 'N', 'remarksCol' => 'O'],
        ],
        4 => [
            ['part' => 'A2', 'mold' => 'A3', 'limit' => 'D5', 'firstRow' => 9, 'lastRow' => 35, 'firstBlock' => 1, 'blockCount' => 4, 'inspectorCol' => 'X', 'remarksCol' => 'Y'],
        ],
        5 => [
            ['part' => 'A2', 'mold' => 'A3', 'limit' => 'D5', 'firstRow' => 8, 'lastRow' => 41, 'firstBlock' => 1, 'blockCount' => 5, 'inspectorCol' => 'AC', 'remarksCol' => 'AD'],
        ],
        6 => [
            ['part' => 'A2', 'mold' => 'A3', 'limit' => 'A5', 'firstRow' => 9, 'lastRow' => 38, 'firstBlock' => 1, 'blockCount' => 6, 'inspectorCol' => 'AH', 'remarksCol' => 'AI'],
        ],
        10 => [
            ['part' => 'A2',  'mold' => 'A3',  'limit' => 'D5',  'firstRow' => 8,  'lastRow' => 39, 'firstBlock' => 1, 'blockCount' => 5, 'inspectorCol' => 'AC', 'remarksCol' => 'AD'],
            ['part' => 'A41', 'mold' => 'A42', 'limit' => 'D44', 'firstRow' => 47, 'lastRow' => 78, 'firstBlock' => 6, 'blockCount' => 5, 'inspectorCol' => 'AC', 'remarksCol' => 'AD'],
        ],
        12 => [
            ['part' => 'A2',  'mold' => 'A3',  'limit' => 'A5',  'firstRow' => 9,  'lastRow' => 38, 'firstBlock' => 1, 'blockCount' => 6, 'inspectorCol' => 'AH', 'remarksCol' => 'AI'],
            ['part' => 'A40', 'mold' => 'A41', 'limit' => 'A43', 'firstRow' => 47, 'lastRow' => 76, 'firstBlock' => 7, 'blockCount' => 6, 'inspectorCol' => 'AH', 'remarksCol' => 'AI'],
        ],
    ];

    public function insertFlashToExcel(array $context, array $rows): ?string
    {
        $ppf = (int) $context['ppf'];
        $records = $this->recordsFromPayload($context, $rows);

        if ($records->isEmpty()) {
            $this->deleteStaleFiles($ppf);

            return null;
        }

        $header = $this->buildHeader($context, (array) collect($rows)->first());

        $sheetSize = $this->resolveSheetSize($this->blocksNeeded($records));
        $layout = self::LAYOUTS[$sheetSize];

        $reader = IOFactory::createReader('Xlsx');
        $reader->setLoadSheetsOnly("BLOCK {$sheetSize}");
        $spreadsheet = $reader->load(storage_path(self::TEMPLATE));
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();

        // header first, while the template's original cell addresses are still valid
        foreach ($layout as $section) {
            $this->fillHeader($sheet, $section, $header);
        }

        $extra = max(0, $records->count() - $this->pageCapacity($layout));

        if ($extra > 0) {
            $this->addRows($sheet, $layout, $extra);
            $layout = $this->withExtra($layout, $extra);
        }

        foreach ($layout as $section) {
            $this->fillRows($sheet, $section, $records);
        }

        return $this->save($spreadsheet, $ppf, $header['partNo']);
    }

    public function appendFlashToExcel(array $context, array $rows, ?array $allRows = null): ?string
    {
        $allRows ??= $rows;
        logger('insertToFlash', ['draft' => $rows]);

        $ppf = (int) $context['ppf'];
        $path = $this->partDirectory((string) $context['partNo']) . DIRECTORY_SEPARATOR . $this->fileName($ppf);

        if (! File::exists($path)) {
            return $this->insertFlashToExcel($context, $allRows);
        }

        $records = $this->recordsFromPayload($context, $rows);
        $neededBlocks = $this->blocksNeeded($records);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $sheetSize = (int) preg_replace('/\D+/', '', $sheet->getTitle());   // "BLOCK 4" -> 4

        if (! isset(self::LAYOUTS[$sheetSize]) || $neededBlocks > $sheetSize) {
            $spreadsheet->disconnectWorksheets();

            return $this->insertFlashToExcel($context, $allRows);   // needs a bigger template
        }

        $base = self::LAYOUTS[$sheetSize];
        $extra = $this->currentExtra($sheet, $base);   // rows already added in earlier saves
        $layout = $this->withExtra($base, $extra);

        foreach ($records as $record) {
            $index = $this->findRowIndex($sheet, $layout[0], $record)
                ?? $this->firstEmptyIndex($sheet, $layout[0]);

            if ($index === null) {
                // table is full: add one more row to every section
                $index = $this->pageCapacity($layout);
                $this->addRows($sheet, $layout, 1);
                $extra++;
                $layout = $this->withExtra($base, $extra);
            }

            foreach ($layout as $section) {
                $this->fillRecordRow($sheet, $section, $index, $record);
            }
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function recordsFromPayload(array $context, array $rows): Collection
    {
        return collect($rows)->map(function (array $row) use ($context) {
            $blocks = [];

            foreach (array_chunk(array_values($row['measurements'] ?? []), self::READINGS_PER_BLOCK) as $s => $chunk) {
                // blank input stays blank (null), a typed 0 stays 0
                $blocks[$s + 1] = array_map(fn($v) => is_numeric($v) ? (float) $v : null, $chunk);
            }

            return [
                'machineNo' => $context['machineNo'],
                'lotNo'     => $context['lotNo'],
                'checkTime' => $row['checkTime'],
                'inspector' => $context['inspector'],
                'remarks'   => $row['remarks'] ?? '',
                'blocks'    => $blocks,
            ];
        })->values();
    }

    private function blocksNeeded(Collection $records): int
    {
        return (int) $records
            ->map(fn($r) => $r['blocks'] === [] ? 0 : max(array_keys($r['blocks'])))
            ->max();
    }

    private function buildHeader(array $context, array $firstRow): array
    {
        return [
            'partNo'       => (string) $context['partNo'],
            'moldNo'       => (string) ($context['moldNo'] ?? ''),
            // template already prints "max.", so drop it if it was entered with the spec
            'spec'         => trim((string) preg_replace('/^\s*max\.?\s*/i', '', (string) ($firstRow['specification'] ?? ''))),
            'controlLimit' => trim((string) ($firstRow['controlLimit'] ?? '')),
        ];
    }

    private function resolveSheetSize(int $blockCount): int
    {
        foreach (self::SHEET_SIZES as $size) {
            if ($blockCount <= $size) {
                return $size;
            }
        }

        throw new \RuntimeException("No Flash Thickness template supports {$blockCount} blocks (max 12).");
    }

    private function fillHeader(Worksheet $sheet, array $section, array $header): void
    {
        $sheet->setCellValue(
            $section['part'],
            rtrim((string) $sheet->getCell($section['part'])->getValue()) . ' ' . $header['partNo']
        );

        $sheet->setCellValue(
            $section['mold'],
            rtrim((string) $sheet->getCell($section['mold'])->getValue()) . ' ' . $header['moldNo']
        );

        $values = [$header['spec'], $header['controlLimit']];
        $text = (string) $sheet->getCell($section['limit'])->getValue();

        $text = preg_replace_callback('/_+/', function (array $match) use (&$values) {
            $value = array_shift($values);

            return ($value !== null && $value !== '') ? $value : $match[0];
        }, $text);

        $sheet->setCellValue($section['limit'], $text);
    }

    private function fillRows(Worksheet $sheet, array $section, Collection $records): void
    {
        $lastReadingCol = Coordinate::stringFromColumnIndex(
            self::FIRST_BLOCK_COLUMN + ($section['blockCount'] * self::READINGS_PER_BLOCK) - 1
        );

        $sheet->getStyle("D{$section['firstRow']}:{$lastReadingCol}{$section['lastRow']}")
            ->getNumberFormat()
            ->setFormatCode('0.00##');

        foreach ($records as $index => $record) {
            $this->fillRecordRow($sheet, $section, $index, $record);
        }
    }

    private function fileName(int $ppf): string
    {
        return "Flash Thickness - PPF{$ppf}.xlsx";
    }

    private function partDirectory(string $partNo): string
    {
        $folder = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '_', $partNo), ' ._');
        $folder = $folder !== '' ? $folder : 'UNKNOWN';

        $directory = storage_path(self::OUTPUT_ROOT) . DIRECTORY_SEPARATOR . $folder;
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function deleteStaleFiles(int $ppf): void
    {
        $pattern = storage_path(self::OUTPUT_ROOT) . '/*/' . $this->fileName($ppf);

        File::delete(File::glob($pattern) ?: []);
    }

    private function save(Spreadsheet $spreadsheet, int $ppf, string $partNo): string
    {
        $path = $this->partDirectory($partNo) . DIRECTORY_SEPARATOR . $this->fileName($ppf);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function cellText(Worksheet $sheet, string $address): string
    {
        return $sheet->cellExists($address)
            ? trim((string) $sheet->getCell($address)->getValue())
            : '';
    }

    /** Row offset of an existing (Lot No, Check Time) entry, or null. */
    private function findRowIndex(Worksheet $sheet, array $section, array $record): ?int
    {
        for ($row = $section['firstRow']; $row <= $section['lastRow']; $row++) {
            if (
                $this->cellText($sheet, "B{$row}") === trim((string) $record['lotNo'])
                && $this->cellText($sheet, "C{$row}") === trim((string) $record['checkTime'])
            ) {
                return $row - $section['firstRow'];
            }
        }

        return null;
    }

    /** Row offset of the first unused row, or null when the sheet is full. */
    private function firstEmptyIndex(Worksheet $sheet, array $section): ?int
    {
        for ($row = $section['firstRow']; $row <= $section['lastRow']; $row++) {
            if ($this->cellText($sheet, "B{$row}") === '' && $this->cellText($sheet, "C{$row}") === '') {
                return $row - $section['firstRow'];
            }
        }

        return null;
    }

    private function fillRecordRow(Worksheet $sheet, array $section, int $index, array $record): void
    {
        $row = $section['firstRow'] + $index;

        $sheet->setCellValue("A{$row}", $record['machineNo']);
        $sheet->setCellValue("B{$row}", $record['lotNo']);
        $sheet->setCellValue("C{$row}", $record['checkTime']);

        for ($b = 0; $b < $section['blockCount']; $b++) {
            $readings = $record['blocks'][$section['firstBlock'] + $b] ?? [];

            for ($i = 0; $i < self::READINGS_PER_BLOCK; $i++) {
                $col = Coordinate::stringFromColumnIndex(
                    self::FIRST_BLOCK_COLUMN + ($b * self::READINGS_PER_BLOCK) + $i
                );
                // null clears any old value when a row is replaced
                $sheet->setCellValue("{$col}{$row}", $readings[$i] ?? null);
            }
        }

        $sheet->setCellValue("{$section['inspectorCol']}{$row}", $record['inspector']);
        $sheet->setCellValue("{$section['remarksCol']}{$row}", $record['remarks']);
    }
}
