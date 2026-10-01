<?php

namespace App\Dashboard\Repositories;

use App\Dashboard\Models\InspectorDb;

class InspectorRepository
{

    public function fetchInspectors()
    {
        return InspectorDb::query()
            ->select('InspectorNo as inspector_id', 'Name as name', 'Plant as plant')
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
}
