<?php

namespace App\Dashboard\Services;

use App\Dashboard\Models\InspectorDb;
use App\Dashboard\Repositories\InspectorRepository;
use App\Domain\Worker\InspectorID;
use Illuminate\Support\Facades\DB;

class InspectorService
{

    public function registerInspector(int $employeeId, string $name, string $plant, int $encoder, string $inspectorId): void
    {

        $isExistInWorkerMaster = InspectorID::where('社員CD', $employeeId)->where('区分', 3)->exists();

        if (!$isExistInWorkerMaster) {
            app(InspectorRepository::class)->saveInWorkerMaster($inspectorId, $employeeId, $encoder);
        } else {
            $inspectorId = InspectorID::where('社員CD', $employeeId)->value('作業員CD');
        }
        app(InspectorRepository::class)->saveInspector($employeeId, $name, $plant, $inspectorId);
    }

    public function removeInspector(string $inspectorId, string $employeeId): void
    {
        $dbConn  = (new InspectorDb)->getConnectionName();
        $idConn  = (new InspectorID)->getConnectionName();

        DB::connection($dbConn)->transaction(function () use ($inspectorId, $employeeId, $idConn) {
            DB::connection($idConn)->transaction(function () use ($inspectorId, $employeeId) {
                $deletedDb = InspectorDb::where('EmployeeID', $employeeId)
                    ->where('InspectorNo', $inspectorId)
                    ->delete();

                $deletedId = InspectorID::where('社員CD', $employeeId)
                    ->where('作業員CD', $inspectorId)
                    ->where('区分', 3)
                    ->delete();

                if ($deletedDb === 0 && $deletedId === 0) {
                    throw new \RuntimeException("Inspector {$inspectorId} / employee {$employeeId} not found.");
                }
            });
        });
    }

    public function updateInspector(string $inspectorId, string $plant, int $encoder, string $employeeId): bool
    {
        $inspectorDB = InspectorDb::where('EmployeeID', $employeeId)->update([
            'InspectorNo' => $inspectorId,
            'Plant' => $plant
        ]);

        $workerMaster = InspectorID::where('社員CD', $employeeId)->where('区分', 3)->update([
            '作業員CD' => $inspectorId,
            '登録者' => $encoder,
            '更新日' => now()->format('Y/m/d'),
        ]);

        return $inspectorDB || $workerMaster;
    }
}
