<?php

namespace App\Dashboard\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DashboardSummaryExportService
{
    public function __construct(private DashboardSummaryService $summary) {}

    public function fileName(?string $from, ?string $to): string
    {
        $range = ($from || $to)
            ? ($from ?: 'start') . '_to_' . ($to ?: 'latest')
            : now()->format('Y-m-d');

        return "dashboard-summary_{$range}.xlsx";
    }

    public function write(string $search, string $mode, string $judge, ?string $plant, ?string $from, ?string $to): void
    {
        $ppfs = $this->summary->allPpfs($search, $mode, $judge, $plant, $from, $to);

        $spreadsheet = new Spreadsheet();

        $this->buildSummarySheet($spreadsheet, $ppfs);
        $this->buildMeasurementSheet($spreadsheet, $ppfs);

        $spreadsheet->setActiveSheetIndex(0);

        (new Xlsx($spreadsheet))->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function buildSummarySheet(Spreadsheet $spreadsheet, $ppfs): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PPF Summary');

        $headers = ['PPF', 'Part No', 'Plant', 'Date Start', 'Date Inspector Confirm',
                    'Confirmed', 'Confirmed By', 'Check Times'];

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($ppfs as $ppf) {
            $sheet->fromArray([
                $ppf->PPFNo,
                $ppf->PartNo,
                $ppf->Plant ?: '-',
                $ppf->dateStart,
                $ppf->requestedAt,
                $ppf->confirmedAt,
                $ppf->confirmedBy,
                $ppf->checkTimes ?: '-',
            ], null, "A{$row}");
            $row++;
        }

        $this->styleHeader($sheet, count($headers));
    }

    private function buildMeasurementSheet(Spreadsheet $spreadsheet, $ppfs): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Measurements');

        $fixed = ['PPF', 'Part No', 'Plant', 'Lot No', 'Machine No', 'Check Time', 'Check Mode',
                  'Time Start', 'Time End', 'Inspector', 'Item', 'X-Bar', 'Specification',
                  'Control Limit', 'Set', 'Set Judgement'];

        $rows = [];
        $maxValues = 0;

        foreach ($ppfs as $ppf) {
            foreach ($this->summary->checkTimes((int) $ppf->PPFNo) as $ct) {
                $detail = $this->summary->detail((int) $ppf->PPFNo, $ct['checkTime']);

                if (! $detail) {
                    continue;
                }

                foreach ($detail['dimensions'] as $dim) {
                    foreach ($dim['sets'] as $set) {
                        $values = array_values($set['values']);
                        $maxValues = max($maxValues, count($values));

                        $rows[] = [
                            array_merge([
                                $ppf->PPFNo,
                                $detail['partNo'],
                                $ppf->Plant ?: '-',
                                $detail['lotNo'],
                                $detail['machineNo'],
                                $detail['checkTime'],
                                ucfirst($dim['mode']),
                                $dim['timeStart'],
                                $dim['timeEnd'],
                                $dim['inspector'],
                                $dim['item'],
                                $dim['forXBar'] ? 'Yes' : 'No',
                                $dim['specification'],
                                $dim['cl'],
                                $set['set'],
                                $set['judgement'] ?: '',
                            ]),
                            $values,
                        ];
                    }
                }
            }
        }

        $headers = $fixed;
        for ($i = 1; $i <= $maxValues; $i++) {
            $headers[] = "Value {$i}";
        }

        $sheet->fromArray($headers, null, 'A1');

        $rowNo = 2;
        foreach ($rows as [$fixedCols, $values]) {
            $sheet->fromArray(array_merge($fixedCols, $values), null, "A{$rowNo}");
            $rowNo++;
        }

        $this->styleHeader($sheet, count($headers));
    }

    private function styleHeader($sheet, int $columnCount): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ]);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastCol}1");

        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }
}