<?php
namespace App\Domain\Master;

use Illuminate\Database\Eloquent\Model;

class Molding extends Model

{
    protected $table = '成形日報';
     public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = 'RECNO';
}
