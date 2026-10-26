<?php

namespace App\Inspection\Services\Excel;

use App\Traits\ExpandsSheet;
use App\Traits\GroupsExcelFiles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Offset checking sheet for F00VE.
 *
 * Template shape (Sheet1, data rows 8..133):
 *   - one BLOCK = 6 rows = one lot: Machine No. (A), Lot Number (B) and Inspectors (S) are merged over the 6 rows
 *   - a block holds up to 3 CHECKS of 2 rows each: Check time (C, merged over 2 rows),
 *     row 1 = 0° (Y-axis), row 2 = 90° (X-axis), 12 readings in E..P (cavities in row 7)
 *   - Q/R = Ave / max labels and their formulas, Remarks (T) is one cell per row
 *
 * For ExpandsSheet a block is one "record" (rowsPerRecord = 6), so rows are always added a whole
 * block at a time and the A/B/S merges of the last block are copied as they are.
 */
class InsertTightenedGapF00VE
{
    use ExpandsSheet, GroupsExcelFiles;

    private const TEMPLATE = 'app/excel-template/tightened-monitoring/F00VE24009 & F00VE2406S-01.xlsx';

    private const OUTPUT_ROOT = 'app/exports/tightened-monitoring/Offset-Checking(F00VE)';

    private const FIRST_READING_COLUMN = 5;
    private const READINGS_PER_ROW = 12;
    private const CHECKS_PER_BLOCK = 3;
    private const ROWS_PER_CHECK = 2;

    private const LOCATIONS = ['0', '90'];


    private const NORMALIZE_LOCATION_LABELS = true;
    private const LOCATION_LABELS = ['0 º(Y-axis)', '90 º(X-axis)'];

    private const LAYOUT = [
        [
            'part'          => 'A1',
            'mold'          => 'N1',
            'spec'          => 'E4',
            'limit'         => 'E5',
            'firstRow'      => 8,
            'lastRow'       => 133,
            'rowsPerRecord' => 6,
            'staticCols'    => ['D', 'Q', 'R'],
        ],
    ];

    public function insertGapF00VEToExcel(array $context, array $rows): ?string
    {
        $checks = $this->checksFromPayload($context, $rows)
            ->groupBy(fn($c) => trim((string) $c['lotNo']))
            ->flatten(1)
            ->values();

        if ($checks->isEmpty()) {
            $this->deleteStaleFiles($context);

            return null;
        }

        $layout = self::LAYOUT;

        $spreadsheet = IOFactory::load(storage_path(self::TEMPLATE));
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle((string) $context['basePartNo']));

        $this->fillHeader($sheet, $layout[0], $context);

        $positions    = $this->positionsFor($checks);
        $blocksNeeded = max(array_column($positions, 0)) + 1;
        $extra        = max(0, $blocksNeeded - $this->pageCapacity($layout));

        if ($extra > 0) {
            $this->addRows($sheet, $layout, $extra);
            $layout = $this->withExtra($layout, $extra);
        }

        foreach ($checks as $index => $check) {
            [$block, $slot] = $positions[$index];
            $this->fillCheck($sheet, $layout[0], $block, $slot, $check);
        }

        $this->normalizeLabels($sheet, $layout[0]);

        return $this->save($spreadsheet, $context);
    }

    private function positionsFor(Collection $checks): array
    {
        $positions = [];
        $block = -1;
        $slot  = self::CHECKS_PER_BLOCK;
        $lot   = null;

        foreach ($checks as $index => $check) {
            $lotNo = trim((string) $check['lotNo']);

            if ($lotNo !== $lot || $slot >= self::CHECKS_PER_BLOCK) {
                $block++;
                $slot = 0;
                $lot  = $lotNo;
            }

            $positions[$index] = [$block, $slot++];
        }

        return $positions;
    }


    public function appendGapF00VEToExcel(array $context, array $rows, ?array $allRows = null): ?string
    {
        $allRows ??= $rows;

        $ppf = (int) $context['ppf'];
        $path = $this->partDirectory((string) $context['basePartNo']) . DIRECTORY_SEPARATOR . $this->fileName($context);

        if (! File::exists($path)) {
            return $this->insertGapF00VEToExcel($context, $allRows);
        }

        $checks = $this->checksFromPayload($context, $rows);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        $extra = $this->currentExtra($sheet);
        $layout = $this->withExtra(self::LAYOUT, $extra);

        foreach ($checks as $check) {
            $position = $this->findCheck($sheet, $layout[0], $check)
                ?? $this->findFreeSlot($sheet, $layout[0], $check);

            if ($position === null) {
                $position = [$this->pageCapacity($layout), 0];
                $this->addRows($sheet, $layout, 1);
                $extra++;
                $layout = $this->withExtra(self::LAYOUT, $extra);
            }

            $this->fillCheck($sheet, $layout[0], $position[0], $position[1], $check);
        }

        $this->normalizeLabels($sheet, $layout[0]);

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }


    private function checksFromPayload(array $context, array $rows): Collection
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
                'lotNo'     => $row['lotNo'] ?? $context['lotNo'] ?? '',
                'machineNo' => $row['machineNo'] ?? $context['machineNo'] ?? '',
                'inspector' => $row['inspector'] ?? $context['inspector'] ?? '',
                'checkTime' => $row['checkTime'],
                'remarks'   => $row['remarks'] ?? '',
                'locations' => $locations,
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

        foreach (['spec', 'limit'] as $key) {
            if (isset($context[$key]) && trim((string) $context[$key]) !== '') {
                $sheet->setCellValue(
                    $section[$key],
                    rtrim((string) $sheet->getCell($section[$key])->getValue()) . ' ' . $context[$key]
                );
            }
        }
    }

    private function fillCheck(Worksheet $sheet, array $section, int $block, int $slot, array $check): void
    {
        $blockTop = $this->blockTop($section, $block);
        $top = $blockTop + $slot * self::ROWS_PER_CHECK;
        $sheet->setCellValueExplicit("A{$blockTop}", (string) $check['machineNo'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$blockTop}", (string) $check['lotNo'], DataType::TYPE_STRING);
        $sheet->setCellValue("S{$blockTop}", $check['inspector']);

        $sheet->setCellValue("C{$top}", $check['checkTime']);

        foreach (self::LOCATIONS as $k => $location) {
            $row = $top + $k;
            $readings = $check['locations'][$location] ?? [];

            for ($i = 0; $i < self::READINGS_PER_ROW; $i++) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex(self::FIRST_READING_COLUMN + $i) . $row,
                    $readings[$i] ?? null
                );
            }
        }

        $sheet->setCellValue("T{$top}", $check['remarks']);
    }


    private function findCheck(Worksheet $sheet, array $section, array $check): ?array
    {
        $blocks = $this->blockCount($section);

        for ($b = 0; $b < $blocks; $b++) {
            $blockTop = $this->blockTop($section, $b);

            if ($this->cellText($sheet, "B{$blockTop}") !== trim((string) $check['lotNo'])) {
                continue;
            }

            for ($s = 0; $s < self::CHECKS_PER_BLOCK; $s++) {
                $top = $blockTop + $s * self::ROWS_PER_CHECK;

                if ($this->cellText($sheet, "C{$top}") === trim((string) $check['checkTime'])) {
                    return [$b, $s];
                }
            }
        }

        return null;
    }

    private function findFreeSlot(Worksheet $sheet, array $section, array $check): ?array
    {
        $blocks = $this->blockCount($section);
        $lot = trim((string) $check['lotNo']);
        $firstEmptyBlock = null;

        for ($b = 0; $b < $blocks; $b++) {
            $blockTop = $this->blockTop($section, $b);
            $blockLot = $this->cellText($sheet, "B{$blockTop}");
            $freeSlot = null;
            $used = 0;

            for ($s = 0; $s < self::CHECKS_PER_BLOCK; $s++) {
                if ($this->cellText($sheet, 'C' . ($blockTop + $s * self::ROWS_PER_CHECK)) === '') {
                    $freeSlot ??= $s;
                } else {
                    $used++;
                }
            }

            if ($blockLot === $lot && $freeSlot !== null) {
                return [$b, $freeSlot];
            }

            if ($firstEmptyBlock === null && $used === 0 && $blockLot === '' && $this->cellText($sheet, "A{$blockTop}") === '') {
                $firstEmptyBlock = $b;
            }
        }

        return $firstEmptyBlock !== null ? [$firstEmptyBlock, 0] : null;
    }

    private function blockTop(array $section, int $block): int
    {
        return $section['firstRow'] + $block * $this->rowsPerRecord($section);
    }

    private function blockCount(array $section): int
    {
        return intdiv($section['lastRow'] - $section['firstRow'] + 1, $this->rowsPerRecord($section));
    }

    private function normalizeLabels(Worksheet $sheet, array $section): void
    {
        if (! self::NORMALIZE_LOCATION_LABELS) {
            return;
        }

        for ($row = $section['firstRow']; $row <= $section['lastRow']; $row++) {
            $sheet->setCellValue("D{$row}", self::LOCATION_LABELS[($row - $section['firstRow']) % self::ROWS_PER_CHECK]);
        }
    }

    private function cellText(Worksheet $sheet, string $address): string
    {
        return $sheet->cellExists($address)
            ? trim((string) $sheet->getCell($address)->getValue())
            : '';
    }

    private function sheetTitle(string $partNo): string
    {
        $title = trim(mb_substr((string) preg_replace('/[\\\\\/?*\[\]:]+/', '-', $partNo), 0, 31));

        return $title !== '' ? $title : 'Offset Checking';
    }

    private function fileName(array $context): string
    {
        return "Offset Checking (F00VE) - {$this->groupStem($context)}.xlsx";
    }

    private function partDirectory(string $partNo): string
    {
        $folder = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '_', $partNo), ' ._');
        $folder = $folder !== '' ? $folder : 'UNKNOWN';

        $directory = storage_path(self::OUTPUT_ROOT) . DIRECTORY_SEPARATOR . $folder;
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function deleteStaleFiles(array $context): void
{
    File::delete(File::glob(storage_path(self::OUTPUT_ROOT) . '/*/' . $this->fileName($context)) ?: []);
}

private function save(Spreadsheet $spreadsheet, array $context): string
{
    $path = $this->partDirectory((string) $context['basePartNo']) . DIRECTORY_SEPARATOR . $this->fileName($context);

    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
    $spreadsheet->disconnectWorksheets();

    return $path;
}
}
