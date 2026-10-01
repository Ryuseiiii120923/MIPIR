<?php

namespace App\Inspection\Repositories;

use App\Inspection\Models\XBar\ControlSpecsLimit;

class SpecsControlRepository{
    public function fetchLimit(string $partNo, string $dimItem){
       return ControlSpecsLimit::where('PartNo', $partNo)->where('DimItem', $dimItem)->first();
    }
}