<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class Defect extends Model
{
    protected $table = 'tblDefect';
    protected $connection = 'mipirDB';
    protected $primaryKey = 'RECNO';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'PPFNo',
        'PartNo',
        'MDNo',
        'ProdLotNo',
        'MachineNo',
        'Checktime',
        'Defect',
        'Qty',
    ];
}