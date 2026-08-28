<?php

namespace App\Inspection\Repositories;

use App\Inspection\Models\XBar\ControlSpecsLimit;

class SpecsControlRepository{
    public function fetchLimit(string $partNo){
       return ControlSpecsLimit::where('PartNo', $partNo)->first();
    }
}