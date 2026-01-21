<?php

namespace App\Filament\Pages;

use App\Models\Device;
use App\Models\Employee;
use App\Services\ZKTecoService;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Illuminate\Support\Collection;
use Filament\Notifications\Notification;

class SmartSync extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $navigationLabel = 'Smart Sync';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.smart-sync';

    public ?string $selectedDeviceId = null;

    public array $diffSummary = [
        'only_in_db' => [],
        'only_in_device' => [],
        'synced_count' => 0,
    ];

    public array $deviceContents = []; // New property for detailed list

    public function mount()
    {
        $this->selectedDeviceId = Device::first()?->id;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Scan Differences')
                ->icon('heroicon-o-magnifying-glass')
                ->action('scanDifferences'),
        ];
    }

    public function scanDifferences()
    {
        if (!$this->selectedDeviceId)
            return;

        $device = Device::find($this->selectedDeviceId);
        $service = new ZKTecoService();

        // 1. Fetch Device Users
        $deviceUsers = $service->getEmployees($device); // Returns array of user data

        if (empty($deviceUsers) && !$service->connect($device)) {
            Notification::make()->title('Connection Failed')->danger()->send();
            return;
        }

        // Fetch Fingerprints to count
        $fingerprints = $service->getFingerprint($device);
        $fpCountMap = collect($fingerprints)->groupBy('uid')->map(fn($g) => $g->count());

        $deviceUserMap = collect($deviceUsers)->mapWithKeys(fn($u) => [$u['userid'] => $u]);

        // 2. Fetch DB Users
        $dbUsers = Employee::all()->keyBy('badge_number');

        // 3. Compare
        $onlyInDb = $dbUsers->diffKeys($deviceUserMap);
        $onlyInDevice = $deviceUserMap->diffKeys($dbUsers);
        $synced = $dbUsers->intersectByKeys($deviceUserMap);

        $this->diffSummary = [
            'only_in_db' => $onlyInDb->values()->toArray(),
            'only_in_device' => $onlyInDevice->values()->toArray(),
            'synced_count' => $synced->count(),
        ];

        // 4. Populate Device Contents View
        $this->deviceContents = collect($deviceUsers)->map(function ($u) use ($fpCountMap) {
            return [
                'userid' => $u['userid'],
                'name' => $u['name'],
                'role' => $u['role'],
                'finger_count' => $fpCountMap->get($u['uid'], 0), // Use uid (internal ID) or userid depending on lib
            ];
        })->values()->toArray();

        Notification::make()->title('Scan Completed')->success()->send();
    }

    public function syncToDevice()
    {
        $this->bulkSyncToDevice($this->diffSummary['only_in_db']);
    }

    public function syncSingleToDevice($badgeNumber)
    {
        $empData = collect($this->diffSummary['only_in_db'])->firstWhere('badge_number', $badgeNumber);
        if ($empData) {
            $this->bulkSyncToDevice([$empData], true);
        }
    }

    protected function bulkSyncToDevice(array $employees, bool $single = false)
    {
        $device = Device::find($this->selectedDeviceId);
        $service = new ZKTecoService();
        $zk = $service->connect($device);

        if (!$zk) {
            Notification::make()->title('Connection Failed')->danger()->send();
            return;
        }

        $count = 0;
        foreach ($employees as $empData) {
            $emp = (object) $empData;
            try {
                $zk->setUser(
                    (int) $emp->badge_number,
                    (int) $emp->badge_number,
                    $emp->name,
                    '',
                    0,
                    (int) ($emp->card_number ?? 0) // Fix: Cast to int
                );
                $count++;
            } catch (\Exception $e) {
            }
        }
        $zk->disconnect();

        $msg = $single ? "User synced to Device" : "Synced {$count} users to Device";
        Notification::make()->title($msg)->success()->send();
        $this->scanDifferences();
    }

    public function syncFromDevice()
    {
        $this->bulkSyncFromDevice($this->diffSummary['only_in_device']);
    }

    public function syncSingleFromDevice($userid)
    {
        $zkUser = collect($this->diffSummary['only_in_device'])->firstWhere('userid', $userid);
        if ($zkUser) {
            $this->bulkSyncFromDevice([$zkUser], true);
        }
    }

    protected function bulkSyncFromDevice(array $users, bool $single = false)
    {
        $count = 0;
        foreach ($users as $zkUser) {
            Employee::updateOrCreate(
                ['badge_number' => $zkUser['userid']],
                ['name' => $zkUser['name'], 'privilege' => $zkUser['role']]
            );
            $count++;
        }

        $msg = $single ? "User imported to DB" : "Imported {$count} users from Device";
        Notification::make()->title($msg)->success()->send();
        $this->scanDifferences();
    }
}
