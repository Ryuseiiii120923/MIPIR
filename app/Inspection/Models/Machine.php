<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $table = 'machine_inprocess';
    protected $connection = 'mipirDB';
    protected $fillable = ['machine_number'];
    
}
