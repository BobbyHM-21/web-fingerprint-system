<?php

namespace App\Filament\Resources\DeviceEmployeeSyncResource\Pages;

use App\Filament\Resources\DeviceEmployeeSyncResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeviceEmployeeSyncs extends ManageRecords
{
    protected static string $resource = DeviceEmployeeSyncResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
