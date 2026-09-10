<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'student_ids' => 'array',
        'appoint_ids' => 'array',
        'package_ids' => 'array',
        'booking_payload' => 'array',
        'raw_response' => 'array',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
