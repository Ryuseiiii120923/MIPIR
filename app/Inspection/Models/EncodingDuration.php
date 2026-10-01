<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class EncodingDuration extends Model
{
    protected $table = 'EncodingDuration';
    protected $connection = 'mipirDB';
    public $incrementing = true;
    public $timestamps = false;
    protected $primaryKey = 'RecNo';
    protected $guarded = [];
}