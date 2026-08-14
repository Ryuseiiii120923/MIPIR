<?php

namespace App\Inspection\Models\Defect;

use Illuminate\Database\Eloquent\Model;

class DefectMaster extends Model
{
     protected $table = "DefectMatrix2";
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = 'LargeDefect';
    protected $keyType = 'string';
    protected $fillable = [
        'LargeDefect',
    ];
}
