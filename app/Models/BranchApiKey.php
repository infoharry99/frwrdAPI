<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchApiKey extends Model
{
    protected $table = 'branch_api_key';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'branch_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
