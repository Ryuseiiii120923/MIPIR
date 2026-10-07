<?php

namespace App\Dashboard\Repositories;

use App\Dashboard\Models\InspectorDb;
use App\Domain\Worker\InspectorID;

class InspectorRepository
{

    public function fetchInspectors()
    {
        return InspectorDb::query()
            ->select('InspectorNo as inspector_id', 'Name as name', 'Plant as plant', 'EmployeeID as employee_id')
            ->orderBy('InspectorNo')
            ->get();
    }

    public function saveInspector(int $employeeId, string $name, string $plant, string $inspectorId){
        return InspectorDb::create([
        'EmployeeID' => $employeeId,
        'Plant' => $plant,
        'Name' => $name,
        'InspectorNo' => $inspectorId
        ]);
    }

    public function saveInWorkerMaster(string $inspectorId,int $employeeId, int $encoder){
        return InspectorID::create([
            '区分' => 3,
            '作業員CD' => $inspectorId,
            '社員CD' => $employeeId,
            '登録者'=> $encoder,
            '更新日'=> now()->format('Y/m/d'),
        ]);
    }
}
