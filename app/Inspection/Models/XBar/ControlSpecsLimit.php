<?php

namespace App\Inspection\Models\XBar;

use Illuminate\Database\Eloquent\Model;

class ControlSpecsLimit extends Model
{
    protected $connection = 'mipirDB';
    protected $table = 'control_specs_limit';
    public $incrementing = true;
}
