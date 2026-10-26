<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\Defect;
use App\Inspection\Models\GroupLeader\LotConfirmation;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Repositories\Excel\ExcelDataRepository;
use App\Inspection\Support\PartNumber;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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

    public function generate(array $lots, array $dimItems, ?string $path = null, int $variant = 3, ?string $confirmedBy = null, ?string $partNoDisplay = null): string
    {
        try {
            $variant = max($variant ?? 3, count($dimItems) > 3 ? 4 : 3);

            if (! isset(self::BLOCK_LAYOUTS[$variant])) {
                throw new \RuntimeException("Unsupported column variant: {$variant}");
            }

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

            $identity = $this->groupIdentity($plan);
            $this->warnIfMixedGroup($identity);

            $this->insertHeader($firstPpf, $sheet, $identity['partNos'], $identity['mdNos']);
            $this->insertDimensionHeaders($dimItems, $blocks, $sheet, $identity['partNos']);
            $this->insertRows($plan, $dimItems, $blocks, $trailing, $sheet, $confirmedBy);

            $output = $outputPath ?? $this->legacyOutputPath($plan);

            File::ensureDirectoryExists(dirname($output));
            $tmp = $output . '.tmp';

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tmp);
            $spreadsheet->disconnectWorksheets();

            File::move($tmp, $output);

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
    private function legacyOutputPath(array $plan): string
    {
        $allPpfNos = array_unique(array_filter(array_column(array_merge([], ...$plan), 'ppf')));

        return storage_path('app/excel/MIPIR_' . implode('-', $allPpfNos) . '.xlsx');
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

    public function insertHeader(string $ppf, $sheet, ?array $partNos = null, ?array $mdNos = null)
    {
        $repository = app(ExcelDataRepository::class);
        $header = $repository->getHeaderforRec($ppf)->first();

        $partDisplay = $partNos ? implode(' / ', $partNos) : $header->PartNo;
        $mdDisplay   = $mdNos ? implode(' / ', $mdNos) : $header->MDNo;

        $sheet->setCellValue('C3', $partDisplay);
        $sheet->setCellValue('M3', $mdDisplay);

        if (count($partNos ?? []) > 1) {
            $sheet->getStyle('C3')->getAlignment()->setWrapText(true);
        }

        $sheet->setCellValue('R3', $header->NoofCavity . "\nNOTE: Refer to PPF");
        $sheet->setCellValue('X3', $header->NQR . "\nNOTE: Refer to PPF");
        $sheet->setCellValue('AC3', $header->Year);
    }


    private function specText(string $label, string $partNo): ?string
    {
        $repository = app(ExcelDataRepository::class);

        if ($x = $repository->getSpecs($label, $partNo)) {
            if ((float) $x->UpperLimit === 0.0) {
                return 'Min ' . $this->formatSpecValue($x->LowerLimit);
            }
            if ((float) $x->LowerLimit === 0.0) {
                return 'Max ' . $this->formatSpecValue($x->UpperLimit);
            }

            return $this->formatSpecValue($x->Specification - $x->LowerLimit)
                . '-' . $this->formatSpecValue($x->Specification + $x->UpperLimit);
        }

        if ($m = $repository->getMasterSpecs($label, $partNo)) {
            $upper = is_numeric($m->UpperLimit) ? (float) $m->UpperLimit : null;
            $lower = is_numeric($m->LowerLimit) ? (float) $m->LowerLimit : null;

            return match (true) {
                $upper !== null && $upper != 0.0 && ($lower === null || $lower == 0.0) => 'Max ' . $this->formatSpecValue($m->UpperLimit),
                $lower !== null && $lower != 0.0 && ($upper === null || $upper == 0.0) => 'Min ' . $this->formatSpecValue($m->LowerLimit),
                $upper !== null && $lower !== null => $this->formatSpecValue($m->LowerLimit) . '-' . $this->formatSpecValue($m->UpperLimit),
                default => trim((string) $m->Specification) ?: null,
            };
        }
        return null;
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

    public function insertRows(array $plan, array $dimItems, array $blocks, array $trailing, $sheet, ?string $confirmedBy = null)
    {
        $startRow  = 9;
        $slotIndex = 0;

        $statuses = $this->lotStatuses($plan);

        foreach ($plan as $lotEntries) {
            $groupStartRow = $startRow + ($slotIndex * 2);
            $groupEndRow   = $startRow + (($slotIndex + count($lotEntries)) * 2) - 1;

            $this->clearOverlappingMerges($sheet, $trailing['stamp'], $groupStartRow, $groupEndRow);
            $this->clearOverlappingMerges($sheet, $trailing['confirmedBy'], $groupStartRow, $groupEndRow);

            $sheet->mergeCells("{$trailing['stamp']}{$groupStartRow}:{$this->offsetColumn($trailing['stamp'], 2)}{$groupEndRow}");
            $sheet->mergeCells("{$trailing['confirmedBy']}{$groupStartRow}:{$trailing['confirmedBy']}{$groupEndRow}");

            $state            = $this->lotState($lotEntries, $statuses);
            $stampWritten     = false;
            $confirmedWritten = false;
            $lotInfoWritten   = false;

            foreach ($lotEntries as $entry) {
                $row = $startRow + ($slotIndex * 2);

                if ($entry['synthetic']) {
                    $sheet->setCellValue("C{$row}", 'CS');
                    foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
                        $sheet->setCellValue("{$col}{$row}", 0);
                    }
                    $sheet->setCellValue("L{$row}", 'O');
                } else {
                    $ppf    = $entry['ppf'];
                    $record = MIPIRInspectionRecord::where('PPFNo', $ppf)
                        ->where('Checktime', $entry['checktime'])
                        ->first();

                    $inspectedBy = MIPIRDimensionMeasure::where('PPFNo', $ppf)
                        ->where('Checktime', $entry['checktime'])
                        ->value('InspectedBy');

                    $inspectedBy = str_replace('Q', '', (string) $inspectedBy);

                    if (! $record) {
                        $slotIndex++;
                        continue;
                    }

                    if (! $lotInfoWritten) {
                        $sheet->setCellValue("A{$row}", $record->ProdLotNo);
                        $sheet->setCellValue("B{$row}", $record->MachineNo);
                        $lotInfoWritten = true;
                    }

                    $sheet->setCellValue("C{$row}", $record->Checktime);
                    $sheet->setCellValue("{$trailing['remarks']}{$row}", $record->Remarks);

                    $this->insertDimensionValues($record, $dimItems, $blocks, $sheet, $row);
                    $this->insertAppearanceInspection($ppf, $entry['checktime'], $sheet, $row);

                    if ($state['submitted'] && ! $stampWritten) {
                        $stampCell = "{$trailing['stamp']}{$groupStartRow}";

                        $sheet->setCellValue(
                            $stampCell,
                            $this->qcPassedStamp((string) $inspectedBy, $record->DateJudge)
                        );

                        $this->applyQcStampStyle($sheet, $stampCell);
                        $stampWritten = true;
                    }

                    if ($state['confirmed'] && ! $confirmedWritten) {
                        $sheet->setCellValue(
                            "{$trailing['confirmedBy']}{$groupStartRow}",
                            $state['confirmedBy'] ?? $confirmedBy
                        );
                        $confirmedWritten = true;
                    }
                }

                $slotIndex++;
            }
        }
    }

    private function qcPassedStamp(string $inspectedBy, $dateJudge): string
    {
        return implode("\n", [
            'QC',
            $inspectedBy . ' • ' . Carbon::parse($dateJudge)->format('m.d'),
            'PASSED',
        ]);
    }

    private function applyQcStampStyle(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 9,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                ],
            ],
        ]);
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

    private function insertDimensionValues(MIPIRInspectionRecord $record, array $dimItems, array $blocks, $sheet, int $row)
    {
        $targets = [];

        foreach (array_values($dimItems) as $index => $raw) {
            $entry = $this->normalizeEntry($raw);

            foreach ($entry['items'] as $offset => $name) {
                $targets["{$name}|{$entry['nth']}"] = [
                    'slot'      => $index + 1,
                    'rowOffset' => $offset,
                    'pair'      => count($entry['items']) > 1,
                ];
            }
        }

        $measures = $record->dimensionMeasures()
            ->where('Checktime', $record->Checktime)
            ->orderBy('RowNo')
            ->orderBy('Set')
            ->orderBy('RECNO')
            ->get();

        $seen        = [];
        $judgeBySlot = [];

        foreach ($measures as $measure) {

            $rowNo = $measure->RowNo ?? 0;
            $seen[$measure->DimItem] ??= [];
            $seen[$measure->DimItem][$rowNo] ??= count($seen[$measure->DimItem]);
            $nth = $seen[$measure->DimItem][$rowNo];

            $target = $targets["{$measure->DimItem}|{$nth}"] ?? null;

            if ($target === null || ! isset($blocks[$target['slot']])) {
                continue;
            }

            $targetRow = $row + $target['rowOffset'];
            $values    = [$measure->Value1, $measure->Value2, $measure->Value3, $measure->Value4, $measure->Value5];

            foreach ($blocks[$target['slot']]['values'] as $i => $col) {
                $sheet->setCellValue("{$col}{$targetRow}", $values[$i]);
            }

            if (! $target['pair'] || $target['rowOffset'] === 0) {
                $judgeBySlot[$target['slot']] = max($judgeBySlot[$target['slot']] ?? 0, (int) $measure->Judge);
            }
        }

        foreach ($judgeBySlot as $slot => $judge) {
            $sheet->setCellValue("{$blocks[$slot]['judge']}{$row}", $judge);
        }
    }

    private function normalizeEntry(mixed $entry): array
    {
        if (is_string($entry)) {
            return ['items' => [$entry], 'nth' => 0];
        }

        if (isset($entry['item'])) {
            return ['items' => [$entry['item']], 'nth' => (int) ($entry['nth'] ?? 0)];
        }

        return ['items' => array_values($entry), 'nth' => 0];
    }

    public function insertDimensionHeaders(array $dimItems, array $blocks, $sheet, array|string $partNos = '')
    {
        $partNos = array_values(array_filter((array) $partNos, fn($p) => trim((string) $p) !== ''));
        $entries = array_map(fn($e) => $this->normalizeEntry($e), array_values($dimItems));

        foreach ($blocks as $slot => $block) {
            $col   = $block['values'][2];
            $entry = $entries[$slot - 1] ?? null;

            if ($entry === null) {
                $sheet->setCellValue("{$col}5", null);
                $sheet->setCellValue("{$col}6", null);
                continue;
            }

            $label = $entry['items'][0];
            $sheet->setCellValue("{$col}5", $label);

            $specText = $this->specTextFor($label, $partNos);

            if ($specText === null) {
                Log::error('InspectionRecordService: no specs found for dimension label', [
                    'label'   => $label,
                    'partNos' => $partNos,
                ]);
                continue;
            }

            $sheet->setCellValue("{$col}6", $specText);
        }
    }
    private function buildRowPlan(array $lots): array
    {
        $plan = [];

        foreach ($lots as $lot) {
            $entries = [];

            foreach ($lot as $item) {
                $entries[] = ['ppf' => $item['ppf'], 'checktime' => $item['checktime'], 'synthetic' => false];
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

    private function lotKey($partNo, $prodLotNo): string
    {
        return trim((string) $partNo) . '|' . trim((string) $prodLotNo);
    }

    private function lotStatuses(array $plan): array
    {
        $ppfs = collect($plan)
            ->collapse()
            ->reject(fn($e) => $e['synthetic'])
            ->pluck('ppf')
            ->unique()
            ->values()
            ->all();

        if ($ppfs === []) {
            return [];
        }

        $records = MIPIRInspectionRecord::whereIn('PPFNo', $ppfs)
            ->get(['PPFNo', 'PartNo', 'ProdLotNo'])
            ->unique('PPFNo');

        $confirmations = LotConfirmation::whereIn('PartNo', $records->pluck('PartNo')->unique()->all())
            ->whereIn('ProdLotNo', $records->pluck('ProdLotNo')->unique()->all())
            ->orderBy('Id')   
            ->get(['PartNo', 'ProdLotNo', 'Status', 'ConfirmedBy'])
            ->mapWithKeys(fn($c) => [
                $this->lotKey($c->PartNo, $c->ProdLotNo) => [
                    'status'      => strtolower(trim((string) $c->Status)),
                    'confirmedBy' => $c->ConfirmedBy,
                ],
            ])
            ->all();

        return $records
            ->mapWithKeys(fn($r) => [
                (string) $r->PPFNo => $confirmations[$this->lotKey($r->PartNo, $r->ProdLotNo)]
                    ?? ['status' => '', 'confirmedBy' => null],
            ])
            ->all();
    }

    /** @return array{submitted: bool, confirmed: bool, confirmedBy: ?string} */
    private function lotState(array $lotEntries, array $statuses): array
    {
        $ppfs = collect($lotEntries)
            ->reject(fn($e) => $e['synthetic'])
            ->pluck('ppf')
            ->unique();

        if ($ppfs->isEmpty()) {
            return ['submitted' => false, 'confirmed' => false, 'confirmedBy' => null];
        }

        $rows = $ppfs->map(fn($p) => $statuses[(string) $p] ?? ['status' => '', 'confirmedBy' => null]);

        return [
            'submitted'   => $rows->every(fn($r) => in_array($r['status'], ['pending', 'confirmed'], true)),
            'confirmed'   => $rows->every(fn($r) => $r['status'] === 'confirmed'),
            'confirmedBy' => $rows->first()['confirmedBy'] ?? null,
        ];
    }

    private function groupIdentity(array $plan): array
    {
        $ppfs = collect($plan)
            ->collapse()
            ->reject(fn($e) => $e['synthetic'])
            ->pluck('ppf')
            ->unique()
            ->values()
            ->all();

        $rows = MIPIRInspectionRecord::whereIn('PPFNo', $ppfs)->get(['PartNo', 'MDNo']);

        $partNos = $rows
            ->map(fn($r) => trim((string) $r->PartNo))
            ->filter()
            ->unique()
            ->sort(fn($a, $b) => [strlen($a), $a] <=> [strlen($b), $b])
            ->values()
            ->all();

        $mdNos = $rows
            ->map(fn($r) => trim((string) $r->MDNo))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return ['partNos' => $partNos, 'mdNos' => $mdNos];
    }

    private function warnIfMixedGroup(array $identity): void
    {
        $bases = array_unique(array_map(fn($p) => PartNumber::base($p), $identity['partNos']));

        if (count($bases) > 1 || count($identity['mdNos']) > 1) {
            Log::warning('InspectionRecordService: lots mix different base part numbers or molding dies', [
                'partNos' => $identity['partNos'],
                'mdNos'   => $identity['mdNos'],
            ]);
        }
    }

    /** Tries each part number in the group, then the computed base, and returns the first spec found. */
    private function specTextFor(string $label, array $partNos): ?string
    {
        $candidates = $partNos;

        if ($partNos !== []) {
            $candidates[] = PartNumber::base($partNos[0]);
        }

        foreach (array_unique($candidates) as $partNo) {
            $text = $this->specText($label, $partNo);

            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }
}
