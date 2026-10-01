<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class XBarCycleLog extends Model
{
    protected $table = 'XBarCycleLog';
    protected $connection = 'mipirDB';
    public $incrementing = true;
    public $timestamps = false;
    protected $primaryKey = 'RecNo';
    protected $guarded = [];
}