<?php

namespace App\Domain\Master;

use Illuminate\Database\Eloquent\Model;

class MoldedProduct extends Model
{
    protected $table = '成形製品';
      public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = 'RECNO';
}
