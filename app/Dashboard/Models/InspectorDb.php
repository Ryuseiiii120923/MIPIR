<?php

namespace App\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;

class InspectorDb extends Model{
    protected $connection = 'mipirDB';
    protected $table = 'tblUser';
    public $incrementing = true;
    protected $primaryKey = 'RECNO';
    public $timestamps = false;

    protected $fillable = [
        'EmployeeID',
        'Name',
        'Password',
        'Plant',
        'InspectorNo'
    ];
}