<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestAttendance extends BaseWidget
{
    protected static ?string $heading = 'Aktivitas Absensi Terkini';
    protected int|string|array $columnSpan = 'full'; // Lebar penuh biar lega

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Ambil 10 log terakhir
                AttendanceLog::query()->latest('scan_time')->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('scan_time')
                    ->label('Waktu')
                    ->dateTime('H:i:s') // Jam saja cukup
                    ->description(fn($record) => $record->scan_time->format('d M Y')),

                Tables\Columns\TextColumn::make('badge_number')
                    ->label('ID')
                    ->weight('bold'),

                // Lookup nama pegawai
                Tables\Columns\TextColumn::make('employee_name')
                    ->label('Nama Pegawai')
                    ->state(function ($record) {
                        return \App\Models\Employee::where('badge_number', $record->badge_number)->value('name') ?? 'Unknown';
                    }),

                Tables\Columns\TextColumn::make('device.name')
                    ->label('Mesin')
                    ->default('-'),

                Tables\Columns\IconColumn::make('verification_mode')
                    ->label('Mode')
                    ->icon(fn($state) => match ($state) {
                        1 => 'heroicon-o-finger-print',
                        15 => 'heroicon-o-face-smile',
                        default => 'heroicon-o-identification',
                    })
                    ->tooltip(fn($state) => match ($state) {
                        1 => 'Fingerprint',
                        15 => 'Face Recognition',
                        4 => 'Card',
                        default => 'Unknown',
                    }),
            ])
            ->paginated(false); // Matikan pagination biar ringkas
    }
}
