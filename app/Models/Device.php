<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\DeviceEmployeeSync;
use App\Models\AttendanceLog;

class Device extends Model
{
    protected $fillable = [
        'name',
        'serial_number',  // ADMS identifier
        'ip_address',
        'port',
        'protocol',
        'is_active',
        'is_online',
        'last_activity',
        'location',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'last_activity' => 'datetime',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'device_employee_sync')
            ->withPivot('is_synced_to_device', 'synced_at')
            ->withTimestamps();
    }

    public function syncRecords()
    {
        return $this->hasMany(DeviceEmployeeSync::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
