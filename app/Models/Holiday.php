<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $table = 'holidays';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'holiday_date' => 'date',
        'year' => 'integer',
        'created_at' => 'datetime',
    ];
}
