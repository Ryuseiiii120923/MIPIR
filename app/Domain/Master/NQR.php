<?php

namespace App\Domain\Master;

use Illuminate\Database\Eloquent\Model;

class NQR extends Model
{
    protected $table = 'NCPHISTORY';
    public $incrementing = false;
    public $timestamps = false;
}
