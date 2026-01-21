<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AppSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'App Settings';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.app-settings';
}
