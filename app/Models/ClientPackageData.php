<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPackageData extends Model
{
    protected $table = 'client_package_data';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'packageForm' => 'array',
        'bookingdata' => 'array',
        'booked_classess' => 'integer',
        'total_classess' => 'integer',
        'remaining_classess' => 'integer',
        'completed_classess' => 'integer',
        'created_at' => 'datetime',
    ];
}
