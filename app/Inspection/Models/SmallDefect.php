<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class SmallDefect extends Model
{
    protected $table = 'tblSmallDefect';
    protected $connection = 'mipirDB';
    protected $primaryKey = 'RECNO';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'PPFNo',
        'largeDefect',
        'smallDefect',
        'qty',
        'Checktime'
    ];
}
