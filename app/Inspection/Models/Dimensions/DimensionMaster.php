<?php

namespace App\Inspection\Models\Dimensions;

use Illuminate\Database\Eloquent\Model;

class DimensionMaster extends Model
{
    protected $connection = 'mipirDB';
    protected $table = 'DimensionMaster';
    public $incrementing = true;
    public $timestamps = false;
    protected $primaryKey = 'RecNo';

    protected $fillable = [
        'PartNo',
        'DimensionName',
        'Specification',
        'UpperLimit',
        'LowerLimit',
        'Device'
    ];
}
