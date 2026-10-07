<?php

namespace App\Dashboard\Models\XBar;

use Illuminate\Database\Eloquent\Model;

class ControlSpecsLimit extends Model
{
    protected $connection = 'mipirDB';
    protected $table = 'control_specs_limit';
    public $incrementing = true;

    protected $fillable = [
        'PartNo',
        'DimItem',
        'CSLx',
        'USLx',
        'LSLx',
        'CCLx',
        'UCLx',
        'LCLx',
        'CSLr',
        'USLr',
        'LSLr',
        'CCLr',
        'UCLr',
        'LCLr',
    ];
}