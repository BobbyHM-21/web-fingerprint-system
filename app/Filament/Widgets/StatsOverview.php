<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    // Agar widget ini refresh otomatis setiap 15 detik (Realtime feel)
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        // Hitung Hadir Hari Ini (Unik berdasarkan Badge Number)
        $presentToday = AttendanceLog::whereDate('timestamp', today())
            ->distinct('badge_number')
            ->count('badge_number');

        // Total Pegawai Aktif
        $totalEmp = Employee::where('is_active', true)->count();

        // Mesin Online (is_online = true)
        $onlineDevices = Device::where('is_online', true)->count();
        $totalDevices = Device::count();

        return [
            Stat::make('Kehadiran Hari Ini', "$presentToday / $totalEmp")
                ->description('Pegawai sudah scan jari')
                ->descriptionIcon('heroicon-m-finger-print')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, $presentToday]), // Dummy chart visual

            Stat::make('Mesin Online', "$onlineDevices / $totalDevices Unit")
                ->description('Status koneksi perangkat')
                ->descriptionIcon('heroicon-m-signal')
                ->color($onlineDevices < $totalDevices ? 'warning' : 'success'),

            Stat::make('Log Baru', AttendanceLog::whereDate('created_at', today())->count())
                ->description('Total scan masuk hari ini')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
