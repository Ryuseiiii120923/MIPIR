<?php

namespace App\Inspection\Services\Excel;

use App\Inspection\Repositories\Excel\ExcelDataRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use ZipArchive;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


class InspectionXBarService
{
    public function generate(int $ppf)
    {
        $template = storage_path('app/excel-template/Xbar.xlsx');

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $spreadsheet = $reader->load($template);
        $sheet = $spreadsheet->getSheet(0);

        $this->insertHeader($ppf, $sheet);
        $partNos = ['Y578D01802B', 'Y578D01802C'];
        $this->insertMeasurement($partNos, $sheet);

        foreach ($sheet->getChartCollection() as $chart) {
            $axisY = $chart->getChartAxisY();
            $gridlines = $axisY->getMajorGridlines();
            $gridlines->setLineColorProperties('808080');
            if ($chart->getName() === 'chart1') {
                $axisY->setAxisOption('minimum', '0.40');
                $axisY->setAxisOption('maximum', '0.60');
            }
        }

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
        $output = storage_path('app/excel/Xbar_' . $ppf . '.xlsx');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setIncludeCharts(true);
        $writer->save($output);

        $this->restoreConnectorLines($template, $output);

        return $output;
    }

    public function insertHeader(int $ppf, $sheet)
    {
        $header = app(ExcelDataRepository::class)->getHeaderforXbar($ppf);

        $sheet->setCellValue('A3', $header['partName']);
        $sheet->setCellValue('A6', $header['partNo']);
        $sheet->setCellValue('D3', $header['moldNo']);
        $sheet->setCellValue('D6', $header['matNo']);
        $sheet->setCellValue('G3', $header['process']);
        $sheet->setCellValue('G6', '     ' . str($header['dimItem'])->upper());
        $sheet->setCellValue('J3', $header['specs']);
        $sheet->setCellValue('J6', $header['device']);
        $sheet->setCellValue('N3', '5');
        $sheet->setCellValue('S3', 'F.MI.E');
    }

    public function insertMeasurement(array $partNos, $sheet)
    {
        
        $repository = app(ExcelDataRepository::class);
        $measurements = $repository
            ->getMeasurement($partNos)
            ->where('Set', 1);

        $groups = $measurements->groupBy(function ($measurement) {
            return $measurement->ProdLotNo . '|' .
                $measurement->Checktime;
        });

        $startColumn = 3;
        $columnIndex = 0;

        foreach ($groups as $group) {

            $column = Coordinate::stringFromColumnIndex(
                $startColumn + $columnIndex
            );

            $columnIndex++;
            $firstMeasurement = $group->first();

            $sheet->setCellValue(
                "{$column}57",
                $firstMeasurement->ProdLotNo
            );

            $sheet->setCellValue(
                "{$column}58",
                $firstMeasurement->PPFNo
            );

            $sheet->setCellValue(
                "{$column}59",
                $firstMeasurement->Checktime
            );
            foreach ($group as $measurement) {

                $values = [
                    $measurement->Value1,
                    $measurement->Value2,
                    $measurement->Value3,
                    $measurement->Value4,
                    $measurement->Value5,
                ];

                $set = (int) $measurement->Set;

                // Set 1 = rows 60-64
                $startRow = 60 + (($set - 1) * 5);

                foreach ($values as $index => $value) {

                    $row = $startRow + $index;

                    $sheet->setCellValue(
                        "{$column}{$row}",
                        $value
                    );
                }
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

    // public function insertFooter(int $ppf, $sheet)
    // {
    //     $footer = app(ExcelDataRepository::class)->getFooterforXbar($ppf);

    //     $sheet->setCellValue('A70', $footer['inspectedBy']);
    //     $sheet->setCellValue('D70', $footer['checkedBy']);
    //     $sheet->setCellValue('G70', $footer['approvedBy']);
    // }
}
