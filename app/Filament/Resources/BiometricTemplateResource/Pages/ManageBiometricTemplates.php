<?php

namespace App\Filament\Resources\BiometricTemplateResource\Pages;

use App\Filament\Resources\BiometricTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBiometricTemplates extends ManageRecords
{
    protected static string $resource = BiometricTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
