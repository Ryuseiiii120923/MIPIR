<?php

namespace App\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;

class DimensionMaster extends Model
{
    protected $table = 'DimensionMaster';
    protected $primaryKey = 'RecNo';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'PartNo',
        'DimensionNo',
        'Symbol',
        'DimensionName',
        'Specification',
        'UpperLimit',
        'LowerLimit',
        'Device',
        'Unit',
        'Enc',
        'JudgementClsIP',
        'JudgementClsMP',
        'SamplingQtyIP',
        'SamplingQtyMP',
        'XBar',
        'DEnc',
    ];

    protected $casts = [
        'DimensionNo'   => 'integer',
        'SamplingQtyIP' => 'integer',
        'SamplingQtyMP' => 'integer',
        'XBar'          => 'boolean',
        'DEnc'          => 'datetime',
    ];
}
