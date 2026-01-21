<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ScriptWidget;
use Filament\Widgets\Widget;
use App\Models\Device;

class SystemAlerts extends Widget
{
    protected static string $view = 'filament.widgets.system-alerts';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function getViewData(): array
    {
        // Logic to find offline devices or those that haven't synced in 3 days
        $offlineDevices = Device::where('last_activity', '<', now()->subMinutes(10))
            ->orWhereNull('last_activity')
            ->get();

        // Example check: Devices with no logs for > 3 days (mock logic for now if no log relationship time check appropriate)
        // In real app, check latest AttendanceLog per device.

        return [
            'offlineDevices' => $offlineDevices,
        ];
    }
}
