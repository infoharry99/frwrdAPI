<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPackageDataTwo extends Model
{
    protected $table = 'client_package_datatwo';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'packageForm' => 'array',
        'bookingdata' => 'array',
        'created_at' => 'datetime',
    ];
}
