<?php

namespace App\Inspection\Actions;

use App\Inspection\Services\Excel\InspectionXBarService;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class GenerateExcel
{
    public function __invoke(int $ppf)
    {
        $xlsxPath = app(InspectionXBarService::class)
            ->generate($ppf);

        $pdfPath = $this->convertToPdf($xlsxPath);

        return response()->download($pdfPath)
            ->deleteFileAfterSend(true);
    }

    private function convertToPdf(string $xlsxPath): string
    {
        $outputDir = dirname($xlsxPath);

        $process = new Process([
            'C:\Program Files\LibreOffice\program\soffice.exe',
            '--headless',
            '--convert-to', 'pdf',
            '--outdir', $outputDir,
            $xlsxPath,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $pdfPath = preg_replace('/\.xlsx$/i', '.pdf', $xlsxPath);

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF conversion failed, expected output not found: {$pdfPath}");
        }

        @unlink($xlsxPath);

        return $pdfPath;
    }
}