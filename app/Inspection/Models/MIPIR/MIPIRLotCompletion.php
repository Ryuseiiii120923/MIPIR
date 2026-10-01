<?php

namespace App\Inspection\Models\MIPIR;

use Illuminate\Database\Eloquent\Model;

class MIPIRLotCompletion extends Model
{
    protected $table = 'mipir_lot_completion';
    protected $connection = 'mipirDB';

    protected $fillable = ['PPFNo', 'Counted'];
}