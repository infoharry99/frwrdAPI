<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewDoctor extends Model
{
    protected $table = 'reviewdoctor';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'rating' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
