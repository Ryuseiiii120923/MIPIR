<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Repositories\Excel\ExcelDataRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use ZipArchive;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;     
use PhpOffice\PhpSpreadsheet\Style\Alignment;  

class InspectionXBarService
{

    private const CHECKTIME_RANK  = ['F' => 0, 'MI' => 1, 'E' => 2];
    private const CHECKTIME_SLOTS = 3;
    private const POINTS_PER_SUBGROUP = 5;
    private const FIRST_DATA_COLUMN = 3;  

    private const POINT_LIMITS = [
        '7W0706-0500A' => 2,
    ];

    public function generate(array $partNos, string $dimension, string $cycleStart, string $cycleEnd, string $transactionId, ?string $outputName = null, ?string $confirmedBy = null): string
    {
        $template = storage_path('app/excel-template/Xbar.xlsx');

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $spreadsheet = $reader->load($template);
        $sheet = $spreadsheet->getSheet(0);

        $this->insertHeader($partNos, $dimension, $sheet, $cycleStart, $cycleEnd);
        $this->insertMeasurement($partNos, $dimension, $sheet, $transactionId, $confirmedBy);
        $this->insertLimit($dimension, $sheet);



        $symbol = storage_path('app/Symbol/58.png');
        $drawing = new Drawing();
        $drawing->setName('Signature');
        $drawing->setDescription('Inspector Signature');
        $drawing->setPath($symbol);
        $drawing->setWidth(206);
        $drawing->setHeight(146);
        $drawing->setCoordinates('G6');
        $drawing->setOffsetX(35);
        $drawing->setOffsetY(20);
        $sheet->getDrawingCollection()->append($drawing);

        File::ensureDirectoryExists(storage_path('app/excel-xbar'));


        $fileTag = implode('-', $partNos);
        $output  = storage_path('app/excel-xbar/' . ($outputName ?? 'Xbar_' . $fileTag) . '.xlsx');
        $tmp     = $output . '.tmp';

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setIncludeCharts(true);
        $writer->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $this->restoreConnectorLines($template, $tmp);

        File::move($tmp, $output);

        return $output;
    }

    private function pointsFor(string $partNo): int
    {
        $key = strtoupper((string) preg_replace('/\s+/', '', $partNo));

        return self::POINT_LIMITS[$key] ?? self::POINTS_PER_SUBGROUP;
    }

    /**
     * NEW (optional): rank ng check time sa pamamagitan ng prefix, para mabilang
     * ang mga alias (hal. FMDW = F). Ibalik sa dating
     * "self::CHECKTIME_RANK[$ct] ?? 99" kung ayaw mo ng ganito.
     */
    private function rank(string $checkTime): int
    {
        foreach (self::CHECKTIME_RANK as $prefix => $rank) {
            if (str_starts_with($checkTime, $prefix)) {
                return $rank;
            }
        }

        return 99;
    }

    private function assignColumns($groups, int $startColumn): array
    {
        $blocks = [];

        foreach ($groups as $key => $group) {
            $first = $group->first();
            $lot   = trim((string) $first->ProdLotNo);
            $ppf   = (string) $first->PPFNo;
            $blockKey = $lot . '|' . $ppf;

            $at = (string) $group->min('created_at');

            $blocks[$blockKey]['lot']   = $lot;
            $blocks[$blockKey]['ppf']   = $ppf;
            $blocks[$blockKey]['first'] = isset($blocks[$blockKey]['first'])
                ? min($blocks[$blockKey]['first'], $at)
                : $at;

            $blocks[$blockKey]['items'][] = [
                'key' => $key,
                'ct'  => strtoupper(trim((string) $first->Checktime)),
                'at'  => $at,
            ];
        }

        uasort($blocks, fn($a, $b) => [$a['first'], $a['lot'], $a['ppf']] <=> [$b['first'], $b['lot'], $b['ppf']]);

        $map  = [];
        $next = $startColumn;

        foreach ($blocks as $block) {
            $items = $block['items'];

            usort($items, fn($a, $b) => [
                $this->rank($a['ct']),               // CHANGED
                $a['at'],
            ] <=> [
                $this->rank($b['ct']),               // CHANGED
                $b['at'],
            ]);

            foreach ($items as $item) {
                $map[$item['key']] = Coordinate::stringFromColumnIndex($next++);
            }
        }

        return $map;
    }

    public function insertHeader(array $partNos, string $dimension, $sheet, string $cycleStart, string $cycleEnd)
    {
        $header = app(ExcelDataRepository::class)->getHeaderforXbar($partNos[0], $dimension);
        $formattedStart = \Carbon\Carbon::parse($cycleStart)->format('Y/m/d');
        $formattedEnd = \Carbon\Carbon::parse($cycleEnd)->format('Y/m/d');
        $points = collect($partNos)->map(fn($p) => $this->pointsFor((string) $p))->max();

        $partNoDisplay = $header['partNo'];

        if (count($partNos) > 1) {
            $partNoDisplay = implode('/', array_map(function ($partNo) use ($dimension) {
                $partHeader = app(ExcelDataRepository::class)->getHeaderforXbar($partNo, $dimension);
                return $partHeader['partNo'];
            }, $partNos));
        }

        $sheet->setCellValue('A3', $header['partName']);
        $sheet->setCellValue('A6', $partNoDisplay);
        $sheet->getStyle('A6')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(6)->setRowHeight(30);
        $sheet->setCellValue('D3', $header['moldNo']);
        $sheet->setCellValue('D6', $header['matNo']);
        $sheet->setCellValue('G3', $header['process']);
        $sheet->setCellValue('G6', '     ' . str($header['dimItem'])->upper());
        $sheet->setCellValue('J3', $header['specs']);
        $sheet->setCellValue('J6', $header['device']);
        $sheet->setCellValue('N3', $points . 'PCS.');
        $sheet->setCellValue('S3', 'F.MI.E');
        $sheet->setCellValue('V3', $formattedStart);
        $sheet->setCellValue('Y3', $formattedEnd);
    }

    public function insertMeasurement(array $partNos, string $dimension, $sheet, $transactionId, ?string $confirmedBy)
    {
        $repository = app(ExcelDataRepository::class);
        $measurements = $repository
            ->getMeasurement($partNos, $dimension, $transactionId)
            ->where('Set', 1);

        $limit = $repository->getLimit($dimension);
        $ucl = (float) $limit->UCLx;
        $lcl = (float) $limit->LCLx;

        $groups = $measurements->groupBy(function ($measurement) {
            return $measurement->PPFNo . '|' . $measurement->Checktime;
        });

        $columns = $this->assignColumns($groups, self::FIRST_DATA_COLUMN);   // CHANGED

        foreach ($groups as $key => $group) {
            $column = $columns[$key];
            $firstMeasurement = $group->first();

            $sheet->setCellValue("{$column}57", $firstMeasurement->ProdLotNo);
            $sheet->setCellValue("{$column}58", $firstMeasurement->PPFNo);
            $sheet->setCellValue("{$column}59", $firstMeasurement->Checktime);
            $sheet->setCellValue("{$column}93", Carbon::parse($firstMeasurement->created_at)->format('y.m.d'));
            $sheet->setCellValue("{$column}94", $firstMeasurement->InspectedBy);
            $sheet->setCellValue("{$column}95", $confirmedBy);

            $trendChartResult = 'Ok';

            foreach ($group as $measurement) {
                $values = array_slice([
                    $measurement->Value1,
                    $measurement->Value2,
                    $measurement->Value3,
                    $measurement->Value4,
                    $measurement->Value5,
                ], 0, $this->pointsFor((string) $measurement->PartNo));

                foreach ($values as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $numericValue = (float) $value;

                    if ($numericValue > $ucl || $numericValue < $lcl) {
                        $trendChartResult = 'NG';
                        break;
                    }
                }

                $set = (int) $measurement->Set;
                $startRow = 60 + (($set - 1) * 5);

                foreach ($values as $index => $value) {
                    $row = $startRow + $index;
                    $sheet->setCellValue("{$column}{$row}", $value);
                }
            }

            $sheet->setCellValue("{$column}92", $trendChartResult);
        }

        $this->insertRemarks($sheet, $groups, $columns);                     // NEW
    }

    /**
     * NEW: isulat ang remarks (LotConfirmation.RequestRemarks) ng bawat PPF sa
     * Comments cell ng subgroup kung saan nakalagay ang UNANG check time niya
     * sa workbook na ito (ang pinakakaliwang column ng PPF).
     */
    private function insertRemarks($sheet, $groups, array $columns): void
    {
        $firstColumn = [];

        foreach ($groups as $key => $group) {
            $ppf   = (string) $group->first()->PPFNo;
            $index = Coordinate::columnIndexFromString($columns[$key]);

            $firstColumn[$ppf] = min($firstColumn[$ppf] ?? PHP_INT_MAX, $index);
        }

        if ($firstColumn === []) {
            return;
        }

        $remarks = app(ExcelDataRepository::class)->getRequestRemarks(array_keys($firstColumn));

        foreach ($firstColumn as $ppf => $index) {
            $text = trim((string) ($remarks[$ppf] ?? ''));

            if ($text === '') {
                continue;
            }

            $cell = $this->commentCell($index - self::FIRST_DATA_COLUMN + 1);

            if ($cell === null) {
                continue;
            }

            // Explicit string: para hindi ma-treat na formula ang text na nagsisimula sa "="
            $sheet->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);

            $range = $sheet->getCell($cell)->getMergeRange() ?: $cell;
            $sheet->getStyle($range)->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
        }
    }

    /**
     * NEW: Comment cell ng subgroup sa template.
     * Subgroup 1-24 ay nasa row 9 + 2n; ang subgroup 25 ay nasa row 58.
     */
    private function commentCell(int $subgroup): ?string
    {
        return match (true) {
            $subgroup >= 1 && $subgroup <= 24 => 'AC' . (9 + 2 * $subgroup),
            $subgroup === 25                  => 'AC58',
            default                           => null,
        };
    }

    public function insertLimit(string $dimension, $sheet)
    {
        $result = app(ExcelDataRepository::class)->getLimit($dimension);

        $sheet->setCellValue('T5', $result->CSLx);
        $sheet->setCellValue('W5', $result->USLx);
        $sheet->setCellValue('Z5', $result->LSLx);
        $sheet->setCellValue('T6',  $result->CCLx);
        $sheet->setCellValue('W6', $result->UCLx);
        $sheet->setCellValue('Z6', $result->LCLx);
        $sheet->setCellValue('T7', $result->CSLr);
        $sheet->setCellValue('W7',  $result->USLr);
        $sheet->setCellValue('Z7',  $result->LSLr);
        $sheet->setCellValue('T8', $result->CCLr);
        $sheet->setCellValue('W8', $result->UCLr);
        $sheet->setCellValue('Z8', $result->LCLr);

        $maximum = number_format((float)$result->UCLx + 0.015, 3, '.', '');
        $minimum = number_format((float)$result->LCLx - 0.015, 3, '.', '');

        foreach ($sheet->getChartCollection() as $chart) {
            $axisY = $chart->getChartAxisY();
            $gridlines = $axisY->getMajorGridlines();
            $gridlines->setLineColorProperties('808080');
            if ($chart->getName() === 'chart1') {
                $axisY->setAxisOption('minimum', $minimum);
                $axisY->setAxisOption('maximum', $maximum);
            }
        }
    }


    private function restoreConnectorLines(string $templatePath, string $outputPath): void
    {
        $templateZip = new ZipArchive();
        if ($templateZip->open($templatePath) !== true) {
            return;
        }
        $templateDrawingXml = $templateZip->getFromName('xl/drawings/drawing1.xml');
        $templateZip->close();

        if ($templateDrawingXml === false) {
            return;
        }

        $parts = preg_split(
            '/(?=<xdr:(?:oneCellAnchor|twoCellAnchor))/',
            $templateDrawingXml
        );

        $connectorBlocks = [];
        foreach ($parts as $part) {
            if (str_contains($part, 'cxnSp')) {
                $part = str_replace('</xdr:wsDr>', '', $part);
                $connectorBlocks[] = $part;
            }
        }

        if (empty($connectorBlocks)) {
            return;
        }

        $connectorXml = implode('', $connectorBlocks);

        $outputZip = new ZipArchive();
        if ($outputZip->open($outputPath) !== true) {
            return;
        }

        $generatedDrawingXml = $outputZip->getFromName('xl/drawings/drawing1.xml');
        if ($generatedDrawingXml === false) {
            $outputZip->close();
            return;
        }

        $splicedXml = str_replace(
            '</xdr:wsDr>',
            $connectorXml . '</xdr:wsDr>',
            $generatedDrawingXml
        );

        $outputZip->addFromString('xl/drawings/drawing1.xml', $splicedXml);
        $outputZip->close();
    }
}