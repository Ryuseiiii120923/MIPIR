<?php

namespace App\Domain\Worker;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model{
       protected $connection = 'EML';
    protected $table = 'tblEmployeeRecord';
     public $incrementing = false;

}