<?php

namespace App\Models;

use App\Models\AttendanceLog;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceEmployeeSync;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'badge_number',
        'name',
        'password',
        'card_number',
        'privilege',
    ];

    public function biometricTemplates()
    {
        return $this->hasMany(BiometricTemplate::class);
    }

    public function devices()
    {
        return $this->belongsToMany(Device::class, 'device_employee_sync')
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
