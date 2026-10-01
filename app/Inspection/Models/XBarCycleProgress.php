<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class XBarCycleProgress extends Model
{
    protected $table = 'XBarCycleProgress';
    protected $connection = 'mipirDB';
    public $incrementing = true;
    public $timestamps = false;
    protected $primaryKey = 'RecNo';
    protected $guarded = [];
}