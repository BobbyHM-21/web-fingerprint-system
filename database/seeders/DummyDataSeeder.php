<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Devices
        $devices = [
            [
                'name' => 'Main Gate Entrance',
                'ip_address' => '192.168.1.201',
                'port' => 4370,
                'is_active' => false, // Simulate offline
                'last_activity' => now()->subMinutes(60),
            ],
            [
                'name' => 'Office Lobby',
                'ip_address' => '192.168.1.202',
                'port' => 4370,
                'is_active' => true,
                'last_activity' => now(),
            ],
            [
                'name' => 'Warehouse Backdoor',
                'ip_address' => '10.5.50.2',
                'port' => 4370,
                'is_active' => false,
                'last_activity' => null,
            ],
        ];

        foreach ($devices as $d) {
            \App\Models\Device::firstOrCreate(['ip_address' => $d['ip_address']], $d);
        }

        // 2. Employees (50 Users)
        for ($i = 1001; $i <= 1050; $i++) {
            \App\Models\Employee::firstOrCreate(
                ['badge_number' => (string) $i],
                [
                    'name' => fake()->name(),
                    'password' => '',
                    'card_number' => (string) rand(10000000, 99999999),
                    'privilege' => 0,
                ]
            );
        }

        // 3. Attendance Logs (Simulate recent activity)
        $employees = \App\Models\Employee::all();
        $deviceIds = \App\Models\Device::pluck('id');

        foreach ($employees as $emp) {
            // Random check-in today
            if (rand(0, 1)) {
                \App\Models\AttendanceLog::create([
                    'employee_id' => $emp->id,
                    'badge_number' => $emp->badge_number,
                    'device_id' => $deviceIds->random(),
                    'timestamp' => Carbon::now()->subHours(rand(0, 8))->subMinutes(rand(0, 59)),
                    'status' => rand(0, 1), // 0 or 1
                    'verification_mode' => 1,
                ]);
            }
        }
    }
}
