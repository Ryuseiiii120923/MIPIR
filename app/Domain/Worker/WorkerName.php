<?php

namespace App\Domain\Worker;

use Illuminate\Database\Eloquent\Model;

class WorkerName extends Model
{
     protected $table = "社員";
    protected $primaryKey = '社員CD';
     public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        '名前',
        '社員CD',
    ];
}
