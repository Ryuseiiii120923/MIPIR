<?php

namespace App\Inspection\Models;

use App\Domain\Worker\InspectorID;
use Illuminate\Foundation\Auth\User as Authenticable;

class WorkerLogin extends Authenticable
{
    protected $table = 'tblUser';
    protected $primaryKey = 'RECNO';
    public $timestamps = false;
    protected $connection = 'mipirDB';

    public function inspector()
    {
        return $this->hasOne(InspectorID::class, '作業員CD', 'EmployeeID');
    }
}
