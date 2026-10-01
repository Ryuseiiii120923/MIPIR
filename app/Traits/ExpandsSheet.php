<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Grows the data area of a template sheet by adding records at the end of every section.
 *
 * A section is one entry of a layout array:
 *   firstRow, lastRow   data rows of the section (as they currently sit in the sheet)
 *   rowsPerRecord       rows one record occupies (default 1)
 *   staticCols          column letters whose text / formulas are copied into the new rows
 *                       (row labels, summary formulas); everything else is left empty
 */
trait ExpandsSheet
{
    private function rowsPerRecord(array $section): int
    {
        return max(1, (int) ($section['rowsPerRecord'] ?? 1));
    }

    /** Records the first section holds. */
    private function pageCapacity(array $layout): int
    {
        $section = $layout[0];

        return intdiv($section['lastRow'] - $section['firstRow'] + 1, $this->rowsPerRecord($section));
    }

    /**
     * Layout after $extra records were added to every section.
     * A section grows at its end, so every section below it moves down.
     */
    private function withExtra(array $layout, int $extra): array
    {
        $shift = 0;

        foreach ($layout as $i => $section) {
            $added = $extra * $this->rowsPerRecord($section);

            $layout[$i]['firstRow'] += $shift;
            $layout[$i]['lastRow']  += $shift + $added;

            $shift += $added;
        }

        return $layout;
    }

    /** Records already added per section; stored inside the file, so it survives every save. */
    private function currentExtra(Worksheet $sheet, ?array $layout = null): int
    {
        $properties = $sheet->getParent()->getProperties();

        return $properties->isCustomPropertySet('extraRecords')
            ? (int) $properties->getCustomPropertyValue('extraRecords')
            : 0;
    }

    /**
     * Add $count records at the end of every section's data area, styled like that section's last record.
     * $layout must be the layout as it currently sits in the sheet.
     */
    private function addRows(Worksheet $sheet, array $layout, int $count): void
    {
        if ($count < 1) {
            return;   // insertNewRowBefore() with a negative count DELETES rows
        }

        $lastCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // bottom section first, so the rows above it don't move yet
        foreach (array_reverse($layout) as $section) {
            $h = $this->rowsPerRecord($section);
            $static = $section['staticCols'] ?? [];
            $last = $section['lastRow'];
            $srcTop = $last - $h + 1;
            $at = $last + 1;

            // snapshots before inserting; PhpSpreadsheet re-keys these ranges on insert
            $merges = array_values($sheet->getMergeCells());
            $conditionals = $sheet->getConditionalStylesCollection();

            $sheet->insertNewRowBefore($at, $count * $h);

            for ($n = 0; $n < $count; $n++) {
                for ($i = 0; $i < $h; $i++) {
                    $src = $srcTop + $i;
                    $row = $at + $n * $h + $i;

                    $sheet->getRowDimension($row)->setRowHeight($sheet->getRowDimension($src)->getRowHeight());

                    for ($col = 1; $col <= $lastCol; $col++) {
                        if (! $sheet->cellExists([$col, $src])) {
                            continue;
                        }

                        $source = $sheet->getCell([$col, $src]);
                        $target = $sheet->getCell([$col, $row]);

                        if (in_array(Coordinate::stringFromColumnIndex($col), $static, true) && $source->getValue() !== null) {
                            if ($source->isFormula()) {
                                $target->setValueExplicit(
                                    ReferenceHelper::getInstance()->updateFormulaReferences(
                                        (string) $source->getValue(),
                                        'A1',
                                        0,
                                        $row - $src,
                                        $sheet->getTitle()
                                    ),
                                    DataType::TYPE_FORMULA
                                );
                            } else {
                                $target->setValueExplicit($source->getValue(), $source->getDataType());
                            }
                        }

                        $target->setXfIndex($source->getXfIndex());
                    }
                }
            }

            // merged cells inside the last record (remarks, check time, ...) -> same merges on every new record
            foreach ($merges as $range) {
                [[$c1, $r1], [$c2, $r2]] = Coordinate::rangeBoundaries($range);

                if ((int) $r1 >= $srcTop && (int) $r2 <= $last) {
                    for ($n = 0; $n < $count; $n++) {
                        $offset = ($n + 1) * $h;

                        $sheet->mergeCells(
                            Coordinate::stringFromColumnIndex($c1) . ($r1 + $offset) . ':' .
                                Coordinate::stringFromColumnIndex($c2) . ($r2 + $offset)
                        );
                    }
                }
            }

            // conditional formatting that reaches the last row: cover the new rows too
            foreach ($conditionals as $sqref => $rules) {
                $extended = [];

                foreach (preg_split('/[\s,]+/', trim((string) $sqref)) as $range) {   // PhpSpreadsheet 2.x joins multi-area ranges with a comma
                    [[$c1, $r1], [$c2, $r2]] = Coordinate::rangeBoundaries($range);

                    if ((int) $r2 === $last) {
                        $extended[] = Coordinate::stringFromColumnIndex($c1) . $at . ':' .
                            Coordinate::stringFromColumnIndex($c2) . ($at + $count * $h - 1);
                    }
                }

                if ($extended !== []) {
                    $sheet->setConditionalStyles(
                        implode(' ', $extended),
                        array_map(fn($rule) => clone $rule, $rules)
                    );
                }
            }
        }

        $this->rememberExtra($sheet, $this->currentExtra($sheet) + $count);

        // a print area doesn't grow by itself for rows added after its last row (only touch one that exists)
        $printArea = (string) $sheet->getPageSetup()->getPrintArea();

        if (preg_match('/^\$?([A-Z]+)\$?\d+:\$?([A-Z]+)\$?\d+$/', $printArea, $m)) {
            $total = 0;

            foreach ($layout as $section) {
                $total += $count * $this->rowsPerRecord($section);
            }

            $sheet->getPageSetup()->setPrintArea($m[1] . '1:' . $m[2] . (end($layout)['lastRow'] + $total));
        }
    }

    private function rememberExtra(Worksheet $sheet, int $extra): void
    {
        $sheet->getParent()->getProperties()->setCustomProperty('extraRecords', $extra, Properties::PROPERTY_TYPE_INTEGER);
    }
}