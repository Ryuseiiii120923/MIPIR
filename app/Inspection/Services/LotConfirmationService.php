<?php

namespace App\Inspection\Services;

use App\Inspection\Models\GroupLeader\LotConfirmation;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Repositories\LotConfirmationRepository;
use App\Inspection\Services\Excel\MipirRecordRefresher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LotConfirmationService
{
    public function __construct(private LotConfirmationRepository $repo) {}

    /** Tawagin pagkatapos ng bawat save ng inspector. Hindi dapat mabigo ang pag-save dahil dito. */
    public function requestIfReady(int $ppf): void
    {
        try {
            $record = MIPIRInspectionRecord::where('PPFNo', $ppf)->first(['PartNo', 'ProdLotNo', 'MachineNo']);

            if ($record === null || ! $this->isReady((string) $record->PartNo, (string) $record->ProdLotNo)) {
                return;
            }

            $lot = LotConfirmation::firstOrNew([
                'PartNo'    => (string) $record->PartNo,
                'ProdLotNo' => (string) $record->ProdLotNo,
            ]);

            // Naghihintay na ng GL: huwag nang gawin ulit
            if ($lot->exists && $lot->Status === 'pending') {
                return;
            }

            // Bago, rejected, o confirmed na may bagong save: balik sa pending
            $lot->fill([
                'Plant'        => Auth::user()->Plant ?? $lot->Plant,
                'MachineNo'    => (string) $record->MachineNo,
                'Status'       => 'pending',
                'RequestedAt'  => now(),
                'ConfirmedBy'  => null,
                'ConfirmedAt'  => null,
                'RejectedBy'   => null,
                'RejectReason' => null,
            ])->save();
        } catch (\Throwable $e) {
            Log::error('Lot confirmation request failed', ['PPFNo' => $ppf, 'error' => $e->getMessage()]);
        }
    }

    public function confirm(int $id, string $employeeId): void
    {
        $lot = DB::connection('mipirDB')->transaction(function () use ($id, $employeeId) {
            $lot = LotConfirmation::lockForUpdate()->findOrFail($id);

            if ($lot->Status !== 'pending') {
                throw new \DomainException('This lot is no longer pending.');
            }

            if (! $this->repo->canConfirm($lot, $employeeId)) {
                throw new \DomainException('You are not the GL of this lot.');
            }

            $lot->update([
                'Status'      => 'confirmed',
                'ConfirmedBy' => $employeeId,
                'ConfirmedAt' => now(),
            ]);

            return $lot;
        });

        $this->rebuildExcel($lot);
    }

    public function reject(int $id, string $employeeId, string $reason): void
    {
        DB::connection('mipirDB')->transaction(function () use ($id, $employeeId, $reason) {
            $lot = LotConfirmation::lockForUpdate()->findOrFail($id);

            if ($lot->Status !== 'pending') {
                throw new \DomainException('This lot is no longer pending.');
            }

            if (! $this->repo->canConfirm($lot, $employeeId)) {
                throw new \DomainException('You are not the GL of this lot.');
            }

            if (trim($reason) === '') {
                throw new \DomainException('Please enter a reason.');
            }

            $lot->update([
                'Status'       => 'rejected',
                'RejectedBy'   => $employeeId,
                'RejectReason' => trim($reason),
            ]);
        });
    }

    // Tapos na ba ang lot: may check time F at may Flash Thickness at Gap-Offset
    private function isReady(string $partNo, string $lotNo): bool
    {
        $ppfs = MIPIRInspectionRecord::where('PartNo', $partNo)
            ->where('ProdLotNo', $lotNo)
            ->pluck('PPFNo');

        $hasF = MIPIRInspectionRecord::whereIn('PPFNo', $ppfs)->where('Checktime', 'F')->exists();

        $hasItem = fn (string $item) => MIPIRDimensionMeasure::whereIn('PPFNo', $ppfs)
            ->where('DimItem', $item)
            ->exists();

        return $hasF && $hasItem('Flash Thickness') && $hasItem('Gap-Offset');
    }

    private function rebuildExcel(LotConfirmation $lot): void
    {
        // Nasa iisang sheet ang buong lot, kaya isang refresh ng kahit anong PPF nito ay sapat
        $ppf = MIPIRInspectionRecord::where('PartNo', $lot->PartNo)
            ->where('ProdLotNo', $lot->ProdLotNo)
            ->value('PPFNo');

        if ($ppf !== null) {
            app(MipirRecordRefresher::class)->refresh((int) $ppf);
        }
        // XBar, SAIR at Flash: parehong pattern, kapag handa na ang mga cell nila
    }
}