<?php

namespace App\Dashboard\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CycleTimeReportExport
{
    public function write(Collection $rows, string $mode): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($mode === 'part' ? 'By Part No' : 'By PPF');

        if ($mode === 'part') {
            $headers = ['Part No', 'No. of PPF', 'Checks Done', 'Total Cycle (hr)', 'Avg Cycle (hr)',
                        'Total Man-hr', 'Avg Man-hr', 'Last PPF End'];
            $data = $rows->map(fn ($r) => [
                $r['part_no'], $r['ppf_count'], $r['checks_done'], $r['total_cycle_hr'],
                $r['avg_cycle_hr'], $r['total_man_hr'], $r['avg_man_hr'], $r['last_ended_at'],
            ])->all();
        } else {
            $headers = ['PPF No', 'Part No', 'Lot No', 'Plant', 'Machine', 'Start', 'PPF End',
                        'Checks Done', 'Inspectors', 'Cycle Time (hr)', 'Cycle Time (h:mm:ss)',
                        'Man-hr', 'Man-hr (h:mm:ss)'];
            $data = $rows->map(fn ($r) => [
                $r['ppf_no'], $r['part_no'], $r['lot_no'], $r['plant'], $r['machine_no'],
                $r['started_at'], $r['ended_at'], $r['checks_done'], $r['inspectors'],
                $r['cycle_hr'], $r['cycle_hms'], $r['man_hr'], $r['man_hms'],
            ])->all();
        }

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($data, null, 'A2');

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save('php://output');
    }
}