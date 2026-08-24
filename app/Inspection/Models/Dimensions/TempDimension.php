<?php

namespace App\Inspection\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class TempDimension extends Model
{
    protected $table = 'tempDim_storage';
    protected $connection = 'mipirDB';

    public $incrementing = true;
    public $timestamps = false;
}
