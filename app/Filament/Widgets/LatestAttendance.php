<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\AttendanceLog;

class LatestAttendance extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Aktivitas Terkini';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AttendanceLog::query()->latest('timestamp')->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('timestamp')
                    ->dateTime()
                    ->label('Waktu Scan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->default('Unknown'),
                Tables\Columns\TextColumn::make('badge_number')
                    ->label('Badge ID'),
                Tables\Columns\TextColumn::make('device.name')
                    ->label('Mesin (Lokasi)'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(int $state): string => match ($state) {
                        0 => 'success', // Check In
                        1 => 'danger',  // Check Out
                        default => 'warning',
                    })
                    ->formatStateUsing(fn(int $state): string => match ($state) {
                        0 => 'Check In',
                        1 => 'Check Out',
                        2 => 'Break Out',
                        3 => 'Break In',
                        4 => 'OT In',
                        5 => 'OT Out',
                        default => (string) $state,
                    }),
            ])
            ->paginated(false);
    }
}
