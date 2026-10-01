<?php

namespace App\Inspection\Models;
use Illuminate\Database\Eloquent\Model;

class CheckTime extends Model{
    protected $table = 'checktime';
    protected $connection = 'mipirDB';
    protected $fillable = ['Checktime','DateEncode','PPFNo','MachineNo'];
       public $incrementing = true;
    public $timestamps = false;

}