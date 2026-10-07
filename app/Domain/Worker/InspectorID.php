<?php

namespace App\Domain\Worker;

use App\Domain\Worker\WorkerName;
use Illuminate\Database\Eloquent\Model;

class InspectorID extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = "作業員";
    protected $primaryKey = '社員CD';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        '区分',
        '社員CD',
        '作業員CD',
        '更新日',
        '登録者',
    ];

    public function getAuthIdentifier()
    {
        return (int) $this->getKey();
    }

    public function employeeName()
    {
        return $this->hasOne(WorkerName::class, '社員CD', '社員CD');
    }
}
