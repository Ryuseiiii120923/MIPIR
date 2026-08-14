<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class ChckTRemarks extends Model
{
    protected $table = 'tblChkTRemarks';
    protected $connection = 'mipirDB';
    protected $primaryKey = 'RECNO';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'PPFNo',
        'PartNo',
        'ProdLotNo',
        'Checktime',
        'Remarks'
    ];
}
