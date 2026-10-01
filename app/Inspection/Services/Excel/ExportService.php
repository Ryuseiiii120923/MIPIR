<?php

namespace App\Inspection\Services\Excel;

use Exception;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExportService
{
    public function generatePdfXBar(array $partNos, string $dimension, string $cycleStart, string $cycleEnd, string $transactionId): string
    {
        try {
            $xlsxPath = app(InspectionXBarService::class)->generate($partNos, $dimension, $cycleStart, $cycleEnd, $transactionId);

            return $this->convertToPdf($xlsxPath);
        } catch (Exception $e) {
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    public function generatePdfInspectionRecord(array $ppfnos, array $dimItems)
    {
        try {
            $xlsxPath = app(InspectionRecordService::class)->generate($ppfnos, $dimItems);

            return $this->convertToPdf($xlsxPath);
        } catch (Exception $e) {
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    private function convertToPdf(string $xlsxPath): string
    {
        $outputDir = dirname($xlsxPath);

        $process = new Process([
            'C:\Program Files\LibreOffice\program\soffice.exe',
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDir,
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
