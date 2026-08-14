<?php

namespace App\Inspection\Models;

use Illuminate\Database\Eloquent\Model;

class MIPIRInspectionRecord extends Model
{
    protected $table = 'tblInspectionRecord';
    protected $connection = 'mipirDB';
    protected $primaryKey = 'RECNO';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'PPFNo',
        'PartNo',
        'MDNo',
        'NoofCavity',
        'NQR',
        'ProdLotNo',
        'MachineNo',
        'Checktime',
        'Remarks',
        'DateJudge',
        'ConfirmedBy',
        'InspectBy',
        'Year',
        'Judgement',
    ];
}
