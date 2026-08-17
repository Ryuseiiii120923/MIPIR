<?php

namespace App\Inspection\Models;
use Illuminate\Database\Eloquent\Model;

class CheckTime extends Model{
    protected $table = 'checktime';
    protected $connection = 'mipirDB';
    protected $fillable = ['check-time','date-encode','PPFNo','machine-no'];
       public $incrementing = true;
    public $timestamps = false;

}