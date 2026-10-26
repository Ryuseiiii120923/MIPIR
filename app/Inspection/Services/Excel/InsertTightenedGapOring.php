<?php

namespace App\Inspection\Services\Excel;

use App\Traits\ExpandsSheet;
use App\Traits\GroupsExcelFiles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InsertTightenedGapOring
{
    use ExpandsSheet, GroupsExcelFiles;

    private const TEMPLATE = 'app/excel-template/tightened-monitoring/Offset-checking (o-Ring).xlsx';

    private const OUTPUT_ROOT = 'app/exports/tightened-monitoring/Offset-Checking(Oring)';

    private const FIRST_READING_COLUMN = 5;
    private const READINGS_PER_BLOCK = 5;
    private const BLOCKS = 5;
    private const NAME_PREFIX = 'Offset Checking (O-Ring) - ';
    private const LOCATIONS = ['0', '90', '45', '135'];

    private const LAYOUT = [
        [
            'part'          => 'A2',
            'mold'          => 'A3',
            'firstRow'      => 9,
            'lastRow'       => 788,
            'rowsPerRecord' => 4,
            'staticCols'    => ['D', 'AD', 'AE'],
        ],
    ];

    public function insertGapOringToExcel(array $context, array $rows): ?string
    {
        $records = $this->recordsFromPayload($context, $rows);

        if ($records->isEmpty()) {
            $this->deleteStaleFiles($context);

            return null;
        }

        $layout = self::LAYOUT;

        $spreadsheet = IOFactory::load(storage_path(self::TEMPLATE));
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle((string) $context['basePartNo']));

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

        return $this->save($spreadsheet, $context);
    }


    public function appendGapOringToExcel(array $context, array $rows, ?array $allRows = null): ?string
    {
        $allRows ??= $rows;

        $ppf = (int) $context['ppf'];
        $path = $this->partDirectory((string) $context['basePartNo']) . DIRECTORY_SEPARATOR . $this->fileName($context);

        if (! File::exists($path)) {
            return $this->insertGapOringToExcel($context, $allRows);
        }

        $records = $this->recordsFromPayload($context, $rows);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        $extra = $this->currentExtra($sheet);
        $layout = $this->withExtra(self::LAYOUT, $extra);

        foreach ($records as $record) {
            $index = $this->findRecordIndex($sheet, $layout[0], $record)
                ?? $this->firstEmptyIndex($sheet, $layout[0]);

            if ($index === null) {

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
                    fn($v) => is_numeric($v) ? (float) $v : null,
                    array_values((array) ($row['measurements'][$location] ?? []))
                );
            }

            return [
                'lotNo'       => $row['lotNo'] ?? $context['lotNo'] ?? '',
                'machineNo'   => $row['machineNo'] ?? $context['machineNo'] ?? '',
                'checkTime'   => $row['checkTime'],
                'inspector'   => $row['inspector'] ?? $context['inspector'] ?? '',
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
                    $readings[$i] ?? null
                );
            }
        }

        $sheet->setCellValue("AF{$top}", $record['judgement']);
        $sheet->setCellValue("AG{$top}", $record['disposition']);
        $sheet->setCellValue("AH{$top}", $record['inspector']);
        $sheet->setCellValue("AI{$top}", $record['remarks']);
    }

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

    private function fileName(array $context): string
    {
        return $this->groupFileName(self::NAME_PREFIX, $context);
    }

    private function partDirectory(string $partNo): string
    {
        $directory = $this->familyDirectory(self::OUTPUT_ROOT, $partNo);
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function deleteStaleFiles(array $context): void
    {
        File::delete($this->familyFiles(
            $this->familyDirectory(self::OUTPUT_ROOT, (string) $context['basePartNo']),
            self::NAME_PREFIX,
            $context
        ));
    }

    private function save(Spreadsheet $spreadsheet, array $context): string
    {
        $dir  = $this->partDirectory((string) $context['basePartNo']);
        $path = $dir . DIRECTORY_SEPARATOR . $this->fileName($context);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        foreach ($this->familyFiles($dir, self::NAME_PREFIX, $context) as $old) {
            if ($old !== $path) {
                File::delete($old);
            }
        }

        return $path;
    }
}
