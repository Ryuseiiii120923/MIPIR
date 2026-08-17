<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class MIPIRDimensionMeasure extends Model
{
    protected $table = 'tblDimensionMeasure';
    protected $connection = 'mipirDB';
    protected $primaryKey = 'RECNO';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'PPFNo',
        'ProdLotNo',
        'PartNo',
        'MachineNo',
        'MDNo',
        'Checktime',
        'DimItem',
        'Specs',
        'Note',
        'Judge',
        'Value1',
        'Value2',
        'Value3',
        'Value4',
        'Value5',
        'Mode',
        'Set'
    ];
}
