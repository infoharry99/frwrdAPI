<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'client';
    protected $primaryKey = 'clientid';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'studentdetails' => 'array',
        'students' => 'array',
        'is_first_login' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
