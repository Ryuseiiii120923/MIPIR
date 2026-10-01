<?php

namespace App\Dashboard\Services;

use App\Dashboard\Models\InspectorDb;
use App\Dashboard\Repositories\InspectorRepository;
use App\Domain\Worker\InspectorID;

class InspectorService{

    public function registerInspector(int $employeeId, string $name, string $plant){
        $inspectorId = InspectorID::where('社員CD', $employeeId)->value('作業員CD');

        app(InspectorRepository::class)->saveInspector($employeeId, $name, $plant, $inspectorId);
        
    }

    public function removeInspector(string $inspectorId){
        return InspectorDb::where('InspectorNo', $inspectorId)->delete();
    }

}