<?php

namespace App\Inspection\Models\GroupLeader;

use Illuminate\Database\Eloquent\Model;

class GroupLeader extends Model{
     protected $connection = 'mipirDB';
    protected $table = 'GroupLeader';
    protected $primaryKey = 'Id';
    protected $guarded = [];
}