<?php

namespace App\Inspection\Services\Excel;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class InsertTightenedDefects
{
    private const TEMPLATE = 'app/excel-template/FQCJ33-D1-6_Surface Appearance Inspection Record.xls';
    private const BASE_DIR = 'app/defects-tightened';

    private const LOT_ROW   = 8;
    private const FIRST_ROW = 10;
    private const LAST_ROW  = 47;
    private const STAMP_ROW = 48;

    private const SLOTS = [
        ['B', 'B'],
        ['C', 'D'],
        ['E', 'F'],
        ['G', 'H'],
        ['I', 'J'],
        ['K', 'L'],
        ['M', 'N'],
        ['O', 'P'],
        ['Q', 'R'],
    ];


    public function sync(array $context, array $shots, bool $ensureFile = false): ?string
    {
        $partNo = $this->safeName((string) ($context['partNo'] ?? ''));
        $lotNo  = trim((string) ($context['lotNo'] ?? ''));

        if ($partNo === '' || $lotNo === '') {
            throw new RuntimeException('Part number and lot number are required.');
        }

        $create = $ensureFile || $shots !== [];
        $dir    = storage_path(self::BASE_DIR . DIRECTORY_SEPARATOR . $partNo);

        if (! $create && ! is_dir($dir)) {
            return null;
        }

        File::ensureDirectoryExists($dir);

        $lock = fopen($dir . DIRECTORY_SEPARATOR . '.lock', 'c');
        flock($lock, LOCK_EX);

        try {
            $files = $this->listFiles($dir, $partNo); 

            $spreadsheet = null;
            $path = null;
            $slot = null;

            foreach (array_reverse($files) as $file) {
                $book  = IOFactory::load($file);
                $found = $this->findLotSlot($book->getActiveSheet(), $lotNo);

                if ($found !== null) {
                    [$spreadsheet, $path, $slot] = [$book, $file, $found];
                    break;
                }

                $book->disconnectWorksheets();
            }

            if ($slot === null) {
                if (! $create) {
                    return null;
                }

                if ($files !== []) {
                    $latest = $files[array_key_last($files)];
                    $book   = IOFactory::load($latest);
                    $free   = $this->firstEmptySlot($book->getActiveSheet());

                    if ($free !== null) {
                        [$spreadsheet, $path, $slot] = [$book, $latest, $free];
                    } else {
                        $book->disconnectWorksheets();
                    }
                }

                if ($slot === null) {
                    $path        = $this->newFilePath($dir, $partNo);
                    $spreadsheet = IOFactory::load(storage_path(self::TEMPLATE));
                    $this->fillHeader($spreadsheet->getActiveSheet(), $context);
                    $slot        = $this->firstEmptySlot($spreadsheet->getActiveSheet());

                    Log::info('SAIR: creating new file', ['path' => $path]);
                }
            }

            $sheet = $spreadsheet->getActiveSheet();
            [$labelCol, $judgeCol] = $slot;

            $sheet->setCellValue($labelCol . self::LOT_ROW, 'Lot No.: ' . $lotNo);

            $confirmedBy = trim((string) ($context['confirmedBy'] ?? ''));

            if ($confirmedBy !== '' && ($slot === self::SLOTS[0] || trim((string) $sheet->getCell('R1')->getValue()) === '')) {
                $sheet->setCellValue('R1', $confirmedBy);
            }

            $inspector = trim((string) ($context['inspector'] ?? ''));

            if ($inspector !== '') {
                $stamp = $labelCol . self::STAMP_ROW;

                $sheet->setCellValueExplicit(
                    $stamp,
                    $inspector . "\n" . now()->format('Y-m-d'),
                    DataType::TYPE_STRING
                );

                $sheet->getStyle($stamp)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
            }

            for ($row = self::FIRST_ROW; $row <= self::LAST_ROW; $row++) {
                $sheet->setCellValue($judgeCol . $row, null);
                $sheet->getStyle($judgeCol . $row)->getFont()->getColor()->setARGB('FF000000');
            }

            foreach ($shots as $shot => $judge) {
                $row = $this->shotRow((int) $shot);

                if ($row === null) {
                    Log::warning('SAIR: shot is outside the template range', ['shot' => $shot, 'part' => $partNo]);
                    continue;
                }

                $cell = $judgeCol . $row;
                $sheet->setCellValue($cell, $judge);

                if ($judge === 'X') {
                    $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB('FFFF0000');
                }
            }

            $tmp = $path . '.tmp';
            IOFactory::createWriter($spreadsheet, 'Xls')->save($tmp);
            $spreadsheet->disconnectWorksheets();

            File::move($tmp, $path);

            return $path;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function findLotSlot(Worksheet $sheet, string $lotNo): ?array
    {
        foreach (self::SLOTS as $slot) {
            if ($this->slotLot($sheet, $slot) === $lotNo) {
                return $slot;
            }
        }

        return null;
    }

    private function firstEmptySlot(Worksheet $sheet): ?array
    {
        foreach (self::SLOTS as $slot) {
            if ($this->slotLot($sheet, $slot) === '') {
                return $slot;
            }
        }

        return null;
    }

    private function slotLot(Worksheet $sheet, array $slot): string
    {
        $text = trim((string) $sheet->getCell($slot[0] . self::LOT_ROW)->getValue());

        return trim((string) preg_replace('/^Lot No\.?:?/i', '', $text));
    }

    private function listFiles(string $dir, string $partNo): array
    {
        $prefix = "SAIR_{$partNo}_";

        $files = collect(File::files($dir))
            ->filter(fn($f) => str_starts_with($f->getFilename(), $prefix) && $f->getExtension() === 'xls')
            ->map(fn($f) => $f->getPathname())
            ->all();

        usort($files, 'strnatcasecmp');

        return $files;
    }

    private function newFilePath(string $dir, string $partNo): string
    {
        $base = sprintf('SAIR_%s_%s', $partNo, now()->format('Y-m-d'));
        $path = $dir . DIRECTORY_SEPARATOR . $base . '.xls';

        for ($n = 2; file_exists($path); $n++) {
            $path = $dir . DIRECTORY_SEPARATOR . "{$base}_{$n}.xls";
        }

        return $path;
    }

    private function shotRow(int $shot): ?int
    {
        if ($shot === 1) {
            return self::FIRST_ROW;
        }

        if ($shot >= 5 && $shot <= 185 && $shot % 5 === 0) {
            return self::FIRST_ROW + intdiv($shot, 5);
        }

        return null;
    }

    private function resolveSlot(Worksheet $sheet, string $lotNo, bool $createIfMissing): ?array
    {
        $firstEmpty = null;

        foreach (self::SLOTS as $slot) {
            $text = trim((string) $sheet->getCell($slot[0] . self::LOT_ROW)->getValue());
            $existing = trim((string) preg_replace('/^Lot No\.?:?/i', '', $text));

            if ($existing === $lotNo) {
                return $slot;
            }

            if ($existing === '' && $firstEmpty === null) {
                $firstEmpty = $slot;
            }
        }

        if (! $createIfMissing) {
            return null;
        }

        if ($firstEmpty === null) {
            throw new RuntimeException('All 9 lot columns of this SAIR file are already used.');
        }

        return $firstEmpty;
    }

    private function fillHeader(Worksheet $sheet, array $context): void
    {
        $sheet->setCellValue('I3', 'Part Name: ' . ($context['partName'] ?? ''));
        $sheet->setCellValue('I4', 'Part Number: ' . ($context['partNo'] ?? ''));
        $sheet->setCellValue('I5', 'Machine No. ' . ($context['machineNo'] ?? ''));
        $sheet->setCellValue('I6', 'Molding Die Number: ' . ($context['moldNo'] ?? ''));
        $sheet->setCellValue('R1', $context['confirmedBy'] ?? '');
    }


    private function findExisting(string $dir, string $partNo): ?string
    {
        $prefix = "SAIR_{$partNo}_";

        $files = collect(File::files($dir))
            ->filter(fn($f) => str_starts_with($f->getFilename(), $prefix) && $f->getExtension() === 'xls')
            ->map(fn($f) => $f->getPathname())
            ->sort()
            ->values();

        return $files->first();
    }

    private function safeName(string $value): string
    {
        return trim((string) preg_replace('/[\\\\\/:*?"<>|\s]+/', '-', $value), '-. ');
    }
}