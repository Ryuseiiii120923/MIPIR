<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Models\XBarCycleLog;
use App\Inspection\Repositories\Excel\ExcelDataRepository;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use ZipArchive;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class InspectionXBarService
{
    public function generate(array $partNos, string $dimension, string $cycleStart, string $cycleEnd, string $transactionId): string
    {
        $template = storage_path('app/excel-template/Xbar.xlsx');

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $spreadsheet = $reader->load($template);
        $sheet = $spreadsheet->getSheet(0);

        $this->insertHeader($partNos, $dimension, $sheet, $cycleStart, $cycleEnd);
        $this->insertMeasurement($partNos, $dimension, $sheet, $transactionId);
        $this->insertLimit($dimension, $sheet);



        // To place the symbol in excel
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

        // Save output
        $fileTag = implode('-', $partNos);
        $output = storage_path('app/excel/Xbar_' . $fileTag . '.xlsx');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setIncludeCharts(true);
        $writer->save($output);

        $this->restoreConnectorLines($template, $output);

        return $output;
    }

    public function insertHeader(array $partNos, string $dimension, $sheet, string $cycleStart, string $cycleEnd)
    {
        $header = app(ExcelDataRepository::class)->getHeaderforXbar($partNos[0], $dimension);
        $formattedStart = \Carbon\Carbon::parse($cycleStart)->format('Y/m/d');
        $formattedEnd = \Carbon\Carbon::parse($cycleEnd)->format('Y/m/d');

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
        $sheet->setCellValue('N3', '5PCS.');
        $sheet->setCellValue('S3', 'F.MI.E');
        $sheet->setCellValue('V3', $formattedStart);
        $sheet->setCellValue('Y3', $formattedEnd);
    }

    public function insertMeasurement(array $partNos, string $dimension, $sheet, $transactionId)
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

        $startColumn = 3;
        $columnIndex = 0;

        foreach ($groups as $group) {

            $column = Coordinate::stringFromColumnIndex(
                $startColumn + $columnIndex
            );

            $columnIndex++;
            $firstMeasurement = $group->first();

            $sheet->setCellValue("{$column}57", $firstMeasurement->ProdLotNo);
            $sheet->setCellValue("{$column}58", $firstMeasurement->PPFNo);
            $sheet->setCellValue("{$column}59", $firstMeasurement->Checktime);
            $sheet->setCellValue("{$column}93", Carbon::parse($firstMeasurement->created_at)->format('y.m.d'));
            $sheet->setCellValue("{$column}94", $firstMeasurement->InspectedBy);

            $trendChartResult = 'Ok';

            foreach ($group as $measurement) {
                $values = [
                    $measurement->Value1,
                    $measurement->Value2,
                    $measurement->Value3,
                    $measurement->Value4,
                    $measurement->Value5,
                ];

                foreach ($values as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $numericValue = (float) $value;

                    if ($numericValue > $ucl || $numericValue < $lcl) {
                        $trendChartResult = 'NG';
                        break; // sapat na ang isang violation para markahan NG ang buong column
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
        $sheet->setCellValue('Z8', $result->LSLr);

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
