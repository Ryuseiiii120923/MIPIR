<?php

namespace App\Inspection\Services;

use App\Dashboard\Models\InspectorDb;
use App\Inspection\Models\GroupLeader\GroupLeader;
use App\Inspection\Models\GroupLeader\LotConfirmation;
use App\Inspection\Models\MIPIRDimensionMeasure;
use App\Inspection\Models\MIPIRInspectionRecord;
use App\Inspection\Repositories\LotConfirmationRepository;
use App\Inspection\Services\Excel\DefectBatchFormatter;
use App\Inspection\Services\Excel\MipirRecordRefresher;
use App\Inspection\Services\Excel\TightenedDefectsRefresher;
use App\Inspection\Services\Excel\TightenedFlashRefresher;
use App\Inspection\Services\Excel\TightenedGapOffsetRefresher;
use App\Inspection\Services\Excel\XBarRecordRefresher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LotConfirmationService
{
    public function __construct(private LotConfirmationRepository $repo) {}


    public function confirm(int $id, string $inspectorId, string $employeeId): void
    {
        $lot = DB::connection('mipirDB')->transaction(function () use ($id, $employeeId, $inspectorId) {
            $lot = LotConfirmation::lockForUpdate()->findOrFail($id);

            if ($lot->Status !== 'pending') {
                throw new \DomainException('This lot is no longer pending.');
            }

            if (! $this->repo->canConfirm($lot, $employeeId)) {
                throw new \DomainException('You are not the GL of this lot.');
            }

            $lot->update([
                'Status'      => 'confirmed',
                'ConfirmedBy' => $inspectorId,
                'ConfirmedAt' => now(),
            ]);

            return $lot;
        });

        $this->rebuildExcel($lot,$employeeId);
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


    private function rebuildExcel(LotConfirmation $lot, int $employeeId): void
    {
        $ppf = MIPIRInspectionRecord::where('PartNo', $lot->PartNo)
            ->where('ProdLotNo', $lot->ProdLotNo)
            ->value('PPFNo');
        $plant = GroupLeader::where('EmployeeID', $employeeId)->value('Plant');
        $confimedBy = $lot->ConfirmedBy !== null ? (string) $lot->ConfirmedBy : null;

        if ($ppf !== null) {
            app(MipirRecordRefresher::class)->refresh((int) $ppf, $confimedBy);
            app(TightenedDefectsRefresher::class)->refresh((int)$ppf, $confimedBy);
            app(TightenedFlashRefresher::class)->refresh((int)$ppf, $confimedBy);
            app(TightenedGapOffsetRefresher::class)->refresh((int)$ppf);
            app(XBarRecordRefresher::class)->refresh((int)$ppf, $plant, $confimedBy);
        }
    }

    public function submitForConfirmation(string $partNo, string $lotNo, string $employeeId): void
    {
        $record = MIPIRInspectionRecord::where('PartNo', $partNo)
            ->where('ProdLotNo', $lotNo)
            ->where('InspectBy', $employeeId)
            ->first(['MachineNo']);

        if ($record === null) {
            throw new \DomainException('You have no inspection record for this lot.');
        }

        $plant = trim((string) (Auth::user()->Plant ?? ''));

        if ($plant === '') {
            throw new \DomainException('Your account has no plant. Please inform the staff.');
        }

        DB::connection('mipirDB')->transaction(function () use ($partNo, $lotNo, $record, $plant) {
            $lot = LotConfirmation::where('PartNo', $partNo)
                ->where('ProdLotNo', $lotNo)
                ->lockForUpdate()
                ->first() ?? new LotConfirmation(['PartNo' => $partNo, 'ProdLotNo' => $lotNo]);

            if ($lot->Status === 'pending') {
                throw new \DomainException('This lot is already waiting for the GL.');
            }

            if ($lot->Status === 'confirmed') {
                throw new \DomainException('This lot is already confirmed.');
            }

            $lot->fill([
                'Plant'        => $plant,
                'MachineNo'    => (string) $record->MachineNo,
                'Status'       => 'pending',
                'RequestedAt'  => now(),
                'ConfirmedBy'  => null,
                'ConfirmedAt'  => null,
                'RejectedBy'   => null,
                'RejectReason' => null,
            ])->save();
        });
    }

    public function invalidateOnEdit(int $ppf): void
    {
        try {
            $record = MIPIRInspectionRecord::where('PPFNo', $ppf)->first(['PartNo', 'ProdLotNo']);

            if ($record === null) {
                return;
            }

            LotConfirmation::where('PartNo', $record->PartNo)
                ->where('ProdLotNo', $record->ProdLotNo)
                ->whereIn('Status', ['pending', 'confirmed'])
                ->update([
                    'Status'      => 'draft',
                    'ConfirmedBy' => null,
                    'ConfirmedAt' => null,
                ]);
        } catch (\Throwable $e) {
            Log::error('Lot confirmation invalidate failed', ['PPFNo' => $ppf, 'error' => $e->getMessage()]);
        }
    }
}