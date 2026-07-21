<?php

namespace App\Models;

use Database\Factories\AccessControllerEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccessControllerEvent extends Model
{
    /** @use HasFactory<AccessControllerEventFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_no',
        'card_no',
        'full_name',
        'major_event_type',
        'sub_event_type',
        'verify_mode',
        'user_type',
        'attendance_status',
        'event_time',
        'device_ip',
        'picture',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'event_time' => 'datetime',
    ];

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }
}
