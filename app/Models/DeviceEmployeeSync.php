<?php

namespace App\Models;

use App\Models\Device;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class DeviceEmployeeSync extends Model
{
    protected $table = 'device_employee_sync';

    protected $fillable = [
        'device_id',
        'employee_id',
        'is_synced_to_device',
        'synced_at',
    ];

    protected $casts = [
        'is_synced_to_device' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
