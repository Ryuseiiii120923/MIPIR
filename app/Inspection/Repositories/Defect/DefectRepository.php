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
        ->whereNotIn('LargeDefect', [
            'Coating Defect',
            'Excessive flash',
            'Off set',
        ])
        ->orderBy('LargeDefect', 'asc')
        ->get();
}

     public function getSmallDefectsFor(string $largeDefect): Collection
    {
        return DefectMaster::select('SmallDefect')
            ->distinct()
            ->where('LargeDefect', $largeDefect)
            ->whereNotNull('SmallDefect')
            ->orderBy('SmallDefect', 'asc')
            ->get();
    }

}
