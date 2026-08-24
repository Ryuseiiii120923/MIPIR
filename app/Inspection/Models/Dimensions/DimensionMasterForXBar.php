<?php

namespace App\Inspection\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimensionMasterForXBar extends Model
{

    protected $table = 'DimensionMaster';
    protected $connection = 'mipirDB';
    
    public $incrementing = true;
    public $timestamps = false;
    protected $primaryKey = 'RecNo';
}
