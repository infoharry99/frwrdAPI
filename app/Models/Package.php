<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'packega';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'numberofclass' => 'integer',
        'duration_days' => 'integer',
        'created_at' => 'datetime',
    ];
}
