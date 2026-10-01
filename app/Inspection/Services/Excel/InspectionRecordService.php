<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\Defect;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Repositories\Excel\ExcelDataRepository;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InspectionRecordService
{
    private const BLOCK_LAYOUTS = [
        3 => [
            1 => ['values' => ['M', 'N', 'O', 'P', 'Q'], 'judge' => 'R'],
            2 => ['values' => ['S', 'T', 'U', 'V', 'W'], 'judge' => 'X'],
            3 => ['values' => ['Y', 'Z', 'AA', 'AB', 'AC'], 'judge' => 'AD'],
        ],
        4 => [
            1 => ['values' => ['M', 'N', 'O', 'P', 'Q'], 'judge' => 'R'],
            2 => ['values' => ['S', 'T', 'U', 'V', 'W'], 'judge' => 'X'],
            3 => ['values' => ['Y', 'Z', 'AA', 'AB', 'AC'], 'judge' => 'AD'],
            4 => ['values' => ['AE', 'AF', 'AG', 'AH', 'AI'], 'judge' => 'AJ'],
        ],
    ];

    private const TRAILING_COLUMNS = [
        3 => ['remarks' => 'AE', 'stamp' => 'AG', 'confirmedBy' => 'AJ'],
        4 => ['remarks' => 'AK', 'stamp' => 'AM', 'confirmedBy' => 'AP'],
    ];

    public function generate(array $lots, array $dimItems): string
    {
        try {
            $variant = count($dimItems);
            $sheetName = $variant === 4 ? '4 columns' : '3 Columns';

            $template = storage_path('app/excel-template/FQCJ33-D2-14_Molding In-process Inspection Record.xlsx');
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($template);
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if ($sheet === null) {
                throw new \RuntimeException("Sheet not found: {$sheetName}");
            }

            $blocks = self::BLOCK_LAYOUTS[$variant];
            $trailing = self::TRAILING_COLUMNS[$variant];

            $plan = $this->buildRowPlan($lots);

            $firstPpf = $this->firstPpfInPlan($plan);
            if ($firstPpf === null) {
                throw new \RuntimeException('No valid PPF found to build the report header.');
            }

            $this->insertHeader($firstPpf, $sheet);
            $this->insertDimensionHeaders($dimItems, $blocks, $sheet);
            $this->insertRows($plan, $dimItems, $blocks, $trailing, $sheet);

            $allPpfNos = array_unique(array_filter(array_column(array_merge([], ...$plan), 'ppf')));
            $fileTag = implode('-', $allPpfNos);
            $output = storage_path("app/excel/MIPIR_{$fileTag}.xlsx");

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($output);

            return $output;
        } catch (\Throwable $e) {
            Log::error('InspectionRecordService: failed to generate MIPIR record', [
                'lots'      => $lots,
                'dimItems'  => $dimItems,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            throw $e;
        }
    }

    private function firstPpfInPlan(array $plan): ?string
    {
        foreach ($plan as $lotEntries) {
            foreach ($lotEntries as $entry) {
                if (! $entry['synthetic']) {
                    return $entry['ppf'];
                }
            }
        }

        return null;
    }

    public function insertHeader(string $ppf, $sheet)
    {
        $repository = app(ExcelDataRepository::class);
        $header = $repository->getHeaderforRec($ppf)->first();

        $sheet->setCellValue('C3', $header->PartNo);
        $sheet->setCellValue('M3', $header->MDNo);
        $sheet->setCellValue('R3', $header->NoofCavity . "\nNOTE: Refer to PPF");
        $sheet->setCellValue('X3', $header->NQR . "\nNOTE: Refer to PPF");
        $sheet->setCellValue('AC3', $header->Year);
    }



    /**
     * $dimItems: ordered array of DimItem identifiers, one entry per column-block.
     * An entry can be a plain string ('Flash Thickness') for a single-row item, or an
     * array (['Gap-Offset', 'Gap-Offset (Y)']) for an item that needs its own two rows —
     * index 0 lands on the block's top row, index 1 on the row beneath it.
     */
    public function insertDimensionHeaders(array $dimItems, array $blocks, $sheet)
    {
        $repository = app(ExcelDataRepository::class);

        foreach ($dimItems as $index => $entry) {
            $slot = $index + 1;
            if (! isset($blocks[$slot])) {
                continue;
            }

            $label = is_array($entry) ? $entry[0] : $entry;
            $specs = $repository->getSpecs($label);

            if ($specs === null) {
                Log::error('InspectionRecordService: no specs found for dimension label', [
                    'label' => $label,
                    'entry' => $entry,
                ]);
                continue;
            }

            if ((float) $specs->UpperLimit === 0.0) {
                $specText = 'Min' . $this->formatSpecValue($specs->LowerLimit);
            } elseif ((float) $specs->LowerLimit === 0.0) {
                $specText = 'Max ' . $this->formatSpecValue($specs->UpperLimit);
            } else {
                $lower = $specs->Specification - $specs->LowerLimit;
                $higher = $specs->Specification + $specs->UpperLimit;
                $specText = $this->formatSpecValue($lower) . '-' . $this->formatSpecValue($higher);
            }

            $col = $blocks[$slot]['values'][2];

            $sheet->setCellValue("{$col}5", $label);
            $sheet->setCellValue("{$col}6", $specText);
        }
    }

    private function formatSpecValue($value): string
    {
        $str = (string) $value;

        if (str_starts_with($str, '.')) {
            return '0' . $str;
        }

        if (str_starts_with($str, '-.')) {
            return '-0' . substr($str, 1);
        }

        return $str;
    }

    public function insertRows(array $plan, array $dimItems, array $blocks, array $trailing, $sheet)
    {
        $startRow = 9;
        $slotIndex = 0;

        foreach ($plan as $lotEntries) {
            $groupStartRow = $startRow + ($slotIndex * 2);
            $groupEndRow = $startRow + (($slotIndex + count($lotEntries)) * 2) - 1;

            $this->clearOverlappingMerges($sheet, $trailing['stamp'], $groupStartRow, $groupEndRow);
            $this->clearOverlappingMerges($sheet, $trailing['confirmedBy'], $groupStartRow, $groupEndRow);

            $sheet->mergeCells("{$trailing['stamp']}{$groupStartRow}:{$this->offsetColumn($trailing['stamp'], 2)}{$groupEndRow}");
            $sheet->mergeCells("{$trailing['confirmedBy']}{$groupStartRow}:{$trailing['confirmedBy']}{$groupEndRow}");

            $stampWritten = false;
            $lotInfoWritten = false;

            foreach ($lotEntries as $entry) {
                $row = $startRow + ($slotIndex * 2);

                if ($entry['synthetic']) {
                    $sheet->setCellValue("C{$row}", 'CS');
                    foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
                        $sheet->setCellValue("{$col}{$row}", 0);
                    }
                    $sheet->setCellValue("L{$row}", 'O');
                } else {
                    $ppf = $entry['ppf'];
                    $record = MIPIRInspectionRecord::where('PPFNo', $ppf)
                        ->where('Checktime', $entry['checktime'])
                        ->first();

                    if (! $record) {
                        $slotIndex++;
                        continue;
                    }

                    // ProdLotNo at MachineNo — isang beses lang, sa unang entry ng lot
                    if (! $lotInfoWritten) {
                        $sheet->setCellValue("A{$row}", $record->ProdLotNo);
                        $sheet->setCellValue("B{$row}", $record->MachineNo);
                        $lotInfoWritten = true;
                    }

                    $sheet->setCellValue("C{$row}", $record->Checktime);
                    $sheet->setCellValue("{$trailing['remarks']}{$row}", $record->Remarks);

                    $this->insertDimensionValues($record, $dimItems, $blocks, $sheet, $row);
                    $this->insertAppearanceInspection($ppf, $entry['checktime'], $sheet, $row);

                    if (! $stampWritten) {
                        $sheet->setCellValue(
                            "{$trailing['stamp']}{$groupStartRow}",
                            $record->InspectBy . "\n" . Carbon::parse($record->DateJudge)->format('y.m.d') . "\n" . $record->Judgement
                        );
                        $sheet->setCellValue("{$trailing['confirmedBy']}{$groupStartRow}", $record->ConfirmedBy);
                        $stampWritten = true;
                    }
                }

                $slotIndex++;
            }
        }
    }

    private function offsetColumn(string $col, int $offset): string
    {
        $index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col);
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + $offset);
    }

    private function clearOverlappingMerges($sheet, string $col, int $fromRow, int $toRow): void
    {
        foreach ($sheet->getMergeCells() as $mergeRange) {
            $range = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($mergeRange);
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($range[0][0]);

            if ($startCol === $col && $range[0][1] <= $toRow && $range[1][1] >= $fromRow) {
                $sheet->unmergeCells($mergeRange);
            }
        }
    }
    private function insertAppearanceInspection(string $ppf, string $checktime, $sheet, int $row)
    {
        $defectColumns = [
            'MIXED FLASH'      => 'D',
            'CUT'              => 'E',
            'PL DAMAGE'        => 'F',
            'DEFORMATION'      => 'G',
            'SURFACE DAMAGE'   => 'H',
            'FOREIGN MATERIAL' => 'I',
        ];

        $defects = Defect::where('PPFNo', $ppf)->where('Checktime', $checktime)->get();

        $qtyByColumn = array_fill_keys($defectColumns, 0);
        $totalNg = 0;
        $unmatched = [];

        foreach ($defects as $defect) {
            $key = strtoupper(trim($defect->Defect ?? ''));
            $col = $defectColumns[$key] ?? null;

            if ($col === null) {
                $unmatched[] = $defect->Defect;
                continue;
            }

            $qty = (int) ($defect->Qty ?? 0);
            $qtyByColumn[$col] += $qty;
            $totalNg += $qty;
        }

        if ($unmatched) {
            Log::warning('InspectionRecordService: unmatched defect type(s)', [
                'ppf' => $ppf,
                'checktime' => $checktime,
                'defects' => $unmatched,
            ]);
        }

        foreach ($defectColumns as $col) {
            $sheet->setCellValue("{$col}{$row}", $qtyByColumn[$col]);
        }

        $sheet->setCellValue("J{$row}", $totalNg);

        $first = $defects->first();
        $sheet->setCellValue("K{$row}", $first->NGPercent ?? 0);
        $sheet->setCellValue("L{$row}", $first->Judgement ?? 'O');
    }
    private function resolveDimItemTarget(array $dimItems, string $dimItemName): ?array
    {
        foreach ($dimItems as $index => $entry) {
            $slot = $index + 1;

            if (is_array($entry)) {
                $offset = array_search($dimItemName, $entry, true);
                if ($offset !== false) {
                    return ['slot' => $slot, 'rowOffset' => $offset];
                }
                continue;
            }

            if ($entry === $dimItemName) {
                return ['slot' => $slot, 'rowOffset' => 0];
            }
        }

        return null;
    }

    private function insertDimensionValues(MIPIRInspectionRecord $record, array $dimItems, array $blocks, $sheet, int $row)
    {
        $measures = $record->dimensionMeasures()->where('Checktime', $record->Checktime)->get();
        foreach ($measures as $measure) {
            $target = $this->resolveDimItemTarget($dimItems, $measure->DimItem);
            if (! $target) {
                continue;
            }

            $columns = $blocks[$target['slot']]['values'];
            $targetRow = $row + $target['rowOffset']; // e.g. Gap-Offset X -> row, Y -> row+1

            $values = [$measure->Value1, $measure->Value2, $measure->Value3, $measure->Value4, $measure->Value5];

            foreach ($columns as $index => $col) {
                $sheet->setCellValue("{$col}{$targetRow}", $values[$index]);
            }

            // Judge is merged across both sub-rows when a slot has them — write once, from the top row
            if ($target['rowOffset'] === 0) {
                $sheet->setCellValue("{$blocks[$target['slot']]['judge']}{$row}", $measure->Judge);
            }
        }
    }

    private function buildRowPlan(array $lots): array
    {
        $plan = [];

        foreach ($lots as $lot) {
            $entries = [];

            foreach ($lot as $item) {
                $entries[] = ['ppf' => $item['ppf'], 'checktime' => $item['checktime'], 'synthetic' => false];

                if ($this->appearanceJudgeIsNg($item['ppf'], $item['checktime'])) {
                    $entries[] = ['ppf' => null, 'checktime' => null, 'synthetic' => true];
                }
            }

            $plan[] = $entries;
        }

        return $plan;
    }

    private function appearanceJudgeIsNg(string $ppf, string $checktime): bool
    {
        $defect = Defect::where('PPFNo', $ppf)->where('Checktime', $checktime)->first();

        return $defect && (int) $defect->Judgement === 1;
    }
}
