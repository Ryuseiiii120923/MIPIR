<?php

namespace App\Inspection\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimensionMaster extends Model
{
    protected $table = 'DimensionMaster';
    public $incrementing = true;
    public $timestamps = false;
    protected $primaryKey = 'RecNo';
}
