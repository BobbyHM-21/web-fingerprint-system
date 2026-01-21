<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Absensi';
    protected static ?string $navigationLabel = 'Laporan';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reports';
}
