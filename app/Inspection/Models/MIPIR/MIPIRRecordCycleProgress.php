<?php

namespace App\Inspection\Models\MIPIR;

use Illuminate\Database\Eloquent\Model;

class MIPIRRecordCycleProgress extends Model
{
    protected $table = 'mipir_record_cycle_progress';
    protected $connection = 'mipirDB';

    protected $fillable = [
        'PartNo',
        'Count',
    ];
}