<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricTemplate extends Model
{
    protected $fillable = [
        'employee_id',
        'finger_id',
        'template',
        'size',
        'valid',
    ];

    protected $casts = [
        'valid' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
