<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DowntimeCategory extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'is_downtime'];

    protected $casts = ['is_downtime' => 'boolean'];
}