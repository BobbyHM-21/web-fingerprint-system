<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\Device;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id',
        'badge_number',
        'device_id',
        'timestamp',
        'status',
        'verification_mode',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
