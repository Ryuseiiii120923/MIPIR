<?php

namespace App\Inspection\Models\GroupLeader;

use Illuminate\Database\Eloquent\Model;

class LotConfirmation extends Model
{
    protected $connection = 'mipirDB';
    protected $table = 'LotConfirmation';
    protected $primaryKey = 'Id';
    protected $guarded = [];
}