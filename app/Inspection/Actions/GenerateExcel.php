<?php

namespace App\Inspection\Actions;

use App\Inspection\Services\Excel\ExportService;
use Illuminate\Http\Request;

class GenerateExcel
{
    public function __invoke(Request $request)
    {
        $partNos = (array) $request->query('partNo', []);
        $dimension = $request->query('dimension');

        if (empty($partNos) || ! $dimension) {
            abort(400, 'Missing part number(s) or dimension.');
        }

        $pdfPath = app(ExportService::class)->generatePdfXBar($partNos, $dimension);

        return response()->download($pdfPath)
            ->deleteFileAfterSend(true);
    }
}