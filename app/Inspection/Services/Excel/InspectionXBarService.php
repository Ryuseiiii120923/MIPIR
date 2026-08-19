<?php

namespace App\Inspection\Services\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class InspectionXBarService
{
    public function generate($ppf)
    {
        $template = storage_path('app/excel-template/FQCI04-D1-9_Xbar R Chart.xlsx');


        $spreadsheet = IOFactory::load($template);
        $sheet = $spreadsheet->getActiveSheet();
        $header =

        $symbol = storage_path('app/Symbol/');
        $drawing = new Drawing();

        $drawing->setName('Signature');
        $drawing->setDescription('Inspector Signature');
        $drawing->setPath($symbol);

        $drawing->setWidth(206);
        $drawing->setHeight(146);

        $drawing->setCoordinates('B20');

        $sheet->getDrawingCollection()->append($drawing);
    }
}
