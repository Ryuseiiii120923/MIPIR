<?php

namespace App\Inspection\Models\GroupLeader;

use Illuminate\Database\Eloquent\Model;

class LotConfirmation extends Model
{
  public const PENDING   = 'pending';
    public const CONFIRMED = 'confirmed'; 
    public const REJECTED  = 'rejected';    

    protected $connection = 'mipirDB';
    protected $table = 'LotConfirmation';
    protected $primaryKey = 'Id';
    protected $guarded = [];
}