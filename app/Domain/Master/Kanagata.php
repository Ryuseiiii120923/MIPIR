<?php

namespace App\Domain\Master;

use Illuminate\Database\Eloquent\Model;

class Kanagata extends Model
{
    protected $table = 'KANAGATA';
      public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = 'RECNO';
}
