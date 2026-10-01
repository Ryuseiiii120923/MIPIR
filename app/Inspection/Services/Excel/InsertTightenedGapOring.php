<?php

namespace App\Inspection\Services\Excel;

use App\Traits\ExpandsSheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InsertTightenedGapOring
{
    use ExpandsSheet;

    private const TEMPLATE = 'app/excel-template/tightened-monitoring/Offset-checking (o-Ring).xlsx';

    private const OUTPUT_ROOT = 'app/exports/tightened-monitoring/Offset-Checking(Oring)';

    private const FIRST_READING_COLUMN = 5;   // E
    private const READINGS_PER_BLOCK = 5;
    private const BLOCKS = 5;

    /** Row order inside one record: 0° (Y), 90° (X), 45° (Y), 135° (X). */
    private const LOCATIONS = ['0', '90', '45', '135'];

    private const LAYOUT = [
        [
            'part'          => 'A2',
            'mold'          => 'A3',
            'firstRow'      => 9,
            'lastRow'       => 788,
            'rowsPerRecord' => 4,
            'staticCols'    => ['D', 'AD', 'AE'],   // location labels, Ave/Min/Max labels and their formulas
        ],
    ];

    public function insertGapOringToExcel(array $context, array $rows): ?string
    {
        $ppf = (int) $context['ppf'];
        $records = $this->recordsFromPayload($context, $rows);

        if ($records->isEmpty()) {
            $this->deleteStaleFiles($ppf);

            return null;
        }

        $layout = self::LAYOUT;

        $spreadsheet = IOFactory::load(storage_path(self::TEMPLATE));
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle((string) $context['partNo']));

        // header + merges first, while the template's original cell addresses are still valid
        $this->fillHeader($sheet, $layout[0], $context);
        $this->splitTallMerges($sheet, $layout[0]);

        $extra = max(0, $records->count() - $this->pageCapacity($layout));

        if ($extra > 0) {
            $this->addRows($sheet, $layout, $extra);
            $layout = $this->withExtra($layout, $extra);
        }

        foreach ($records as $index => $record) {
            $this->fillRecord($sheet, $layout[0], $index, $record);
        }

        return $this->save($spreadsheet, $ppf, (string) $context['partNo']);
    }

    public function appendGapOringToExcel(array $context, array $rows, ?array $allRows = null): ?string
    {
        $allRows ??= $rows;

        $ppf = (int) $context['ppf'];
        $path = $this->partDirectory((string) $context['partNo']) . DIRECTORY_SEPARATOR . $this->fileName($ppf);

        if (! File::exists($path)) {
            return $this->insertGapOringToExcel($context, $allRows);
        }

        $records = $this->recordsFromPayload($context, $rows);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        $extra = $this->currentExtra($sheet);   // records already added in earlier saves
        $layout = $this->withExtra(self::LAYOUT, $extra);

        foreach ($records as $record) {
            $index = $this->findRecordIndex($sheet, $layout[0], $record)
                ?? $this->firstEmptyIndex($sheet, $layout[0]);

            if ($index === null) {
                // table is full: add one more record to the end
                $index = $this->pageCapacity($layout);
                $this->addRows($sheet, $layout, 1);
                $extra++;
                $layout = $this->withExtra(self::LAYOUT, $extra);
            }

            $this->fillRecord($sheet, $layout[0], $index, $record);
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * The ONE place that knows the shape of the incoming payload. Adapt it to your data.
     *
     * Expected row:
     *   checkTime, remarks (optional), judgement (optional), disposition (optional),
     *   measurements => ['0' => [25 values], '90' => [...], '45' => [...], '135' => [...]]
     *   (25 values = 5 blocks x 5 readings, in column order)
     */
    private function recordsFromPayload(array $context, array $rows): Collection
    {
        return collect($rows)->map(function (array $row) use ($context) {
            $locations = [];

            foreach (self::LOCATIONS as $location) {
                $locations[$location] = array_map(
                    fn($v) => is_numeric($v) ? (float) $v : null,   // blank stays blank, a typed 0 stays 0
                    array_values((array) ($row['measurements'][$location] ?? []))
                );
            }

            return [
                'lotNo'       => $context['lotNo'],
                'machineNo'   => $context['machineNo'],
                'checkTime'   => $row['checkTime'],
                'inspector'   => $context['inspector'],
                'judgement'   => $row['judgement'] ?? null,
                'disposition' => $row['disposition'] ?? null,
                'remarks'     => $row['remarks'] ?? '',
                'locations'   => $locations,
            ];
        })->values();
    }

    private function fillHeader(Worksheet $sheet, array $section, array $context): void
    {
        $sheet->setCellValue(
            $section['part'],
            rtrim((string) $sheet->getCell($section['part'])->getValue()) . ' ' . $context['partNo']
        );

        $sheet->setCellValue(
            $section['mold'],
            rtrim((string) $sheet->getCell($section['mold'])->getValue()) . ' ' . ($context['moldNo'] ?? '')
        );
    }

    /**
     * The template merges Lot No. / Machine No. of the first two records into one tall cell (A9:A16, B9:B16).
     * Split every merge taller than one record so each record owns its cells.
     */
    private function splitTallMerges(Worksheet $sheet, array $section): void
    {
        $h = $section['rowsPerRecord'];

        foreach (array_values($sheet->getMergeCells()) as $range) {
            [[$c1, $r1], [$c2, $r2]] = Coordinate::rangeBoundaries($range);

            if ((int) $c1 !== (int) $c2 || $r1 < $section['firstRow'] || ($r2 - $r1 + 1) <= $h) {
                continue;
            }

            $col = Coordinate::stringFromColumnIndex($c1);

            $sheet->unmergeCells($range);

            for ($r = $r1; $r <= $r2; $r++) {
                // borders of a normal record (the one right below) go on the split cells
                $sheet->getCell("{$col}{$r}")->setXfIndex(
                    $sheet->getCell($col . ($r2 + 1 + (($r - $r1) % $h)))->getXfIndex()
                );
            }

            for ($r = $r1; $r <= $r2; $r += $h) {
                $sheet->mergeCells("{$col}{$r}:{$col}" . min($r + $h - 1, $r2));
            }
        }
    }

    private function fillRecord(Worksheet $sheet, array $section, int $index, array $record): void
    {
        $top = $section['firstRow'] + $index * $section['rowsPerRecord'];

        $sheet->setCellValue("A{$top}", $record['lotNo']);
        $sheet->setCellValue("B{$top}", $record['machineNo']);
        $sheet->setCellValue("C{$top}", $record['checkTime']);

        foreach (self::LOCATIONS as $k => $location) {
            $row = $top + $k;
            $readings = $record['locations'][$location] ?? [];

            for ($i = 0; $i < self::BLOCKS * self::READINGS_PER_BLOCK; $i++) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex(self::FIRST_READING_COLUMN + $i) . $row,
                    $readings[$i] ?? null   // null clears any old value when a record is replaced
                );
            }
        }

        $sheet->setCellValue("AF{$top}", $record['judgement']);
        $sheet->setCellValue("AG{$top}", $record['disposition']);
        $sheet->setCellValue("AH{$top}", $record['inspector']);
        $sheet->setCellValue("AI{$top}", $record['remarks']);
    }

    /** Record index of an existing (Lot No, Check Time) entry, or null. */
    private function findRecordIndex(Worksheet $sheet, array $section, array $record): ?int
    {
        $h = $section['rowsPerRecord'];

        for ($row = $section['firstRow']; $row <= $section['lastRow']; $row += $h) {
            if (
                $this->cellText($sheet, "A{$row}") === trim((string) $record['lotNo'])
                && $this->cellText($sheet, "C{$row}") === trim((string) $record['checkTime'])
            ) {
                return intdiv($row - $section['firstRow'], $h);
            }
        }

        return null;
    }

    /** Record index of the first unused record, or null when the table is full. */
    private function firstEmptyIndex(Worksheet $sheet, array $section): ?int
    {
        $h = $section['rowsPerRecord'];

        for ($row = $section['firstRow']; $row <= $section['lastRow']; $row += $h) {
            if ($this->cellText($sheet, "A{$row}") === '' && $this->cellText($sheet, "C{$row}") === '') {
                return intdiv($row - $section['firstRow'], $h);
            }
        }

        return null;
    }

    private function cellText(Worksheet $sheet, string $address): string
    {
        return $sheet->cellExists($address)
            ? trim((string) $sheet->getCell($address)->getValue())
            : '';
    }

    /** Excel sheet names: max 31 chars, none of \ / ? * [ ] : */
    private function sheetTitle(string $partNo): string
    {
        $title = trim(mb_substr((string) preg_replace('/[\\\\\/?*\[\]:]+/', '-', $partNo), 0, 31));

        return $title !== '' ? $title : 'Offset Checking';
    }

    private function fileName(int $ppf): string
    {
        return "Offset Checking (O-Ring) - PPF{$ppf}.xlsx";
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
        File::delete(File::glob(storage_path(self::OUTPUT_ROOT) . '/*/' . $this->fileName($ppf)) ?: []);
    }

    private function save(Spreadsheet $spreadsheet, int $ppf, string $partNo): string
    {
        $path = $this->partDirectory($partNo) . DIRECTORY_SEPARATOR . $this->fileName($ppf);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}