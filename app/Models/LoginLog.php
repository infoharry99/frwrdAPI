<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'login_logs';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'login_time' => 'datetime',
    ];
}
