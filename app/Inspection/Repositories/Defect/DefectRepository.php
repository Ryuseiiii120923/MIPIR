<?php

namespace App\Inspection\Repositories\Defect;

use App\Inspection\Models\Defect\DefectMaster;
use Illuminate\Support\Collection;

class DefectRepository
{
    public function getLargeDefects(): Collection
    {
        return DefectMaster::select('LargeDefect')
            ->distinct()
            ->whereNotNull('LargeDefect')
            ->orderBy('LargeDefect', 'asc')
            ->get();
    }

}
