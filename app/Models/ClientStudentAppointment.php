<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientStudentAppointment extends Model
{
    protected $table = 'client_student_appointments';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'reminder_7_sent' => 'boolean',
        'reminder_4_sent' => 'boolean',
        'reminder_1_sent' => 'boolean',
        'last_appointment_date' => 'datetime',
    ];
}
