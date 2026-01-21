<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Employee;
use App\Models\Device;
use App\Models\AttendanceLog;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalEmployees = Employee::count();
        $presentToday = AttendanceLog::whereDate('timestamp', today())->distinct('employee_id')->count();

        $totalDevices = Device::count();
        $activeDevices = Device::where('is_active', true)->count();

        return [
            Stat::make('Total Karyawan', $totalEmployees)
                ->description('Total terdaftar')
                ->color('success')
                ->icon('heroicon-m-users'),
            Stat::make('Kehadiran Hari Ini', $presentToday . ' / ' . $totalEmployees)
                ->description('Karyawan hadir')
                ->color('primary')
                ->icon('heroicon-m-clock'),
            Stat::make('Mesin Online', $activeDevices . ' / ' . $totalDevices)
                ->description('Status koneksi')
                ->color($activeDevices === $totalDevices ? 'success' : 'danger')
                ->icon('heroicon-m-server'),
        ];
    }
}
