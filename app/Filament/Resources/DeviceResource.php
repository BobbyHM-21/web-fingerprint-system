<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Models\Device;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $navigationLabel = 'Data Mesin';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Mesin';
    protected static ?string $pluralModelLabel = 'Data Mesin';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ip_address')
                    ->label('IP Address')
                    ->required()
                    ->ip()
                    ->maxLength(45),
                Forms\Components\TextInput::make('port')
                    ->required()
                    ->numeric()
                    ->default(4370),
                Forms\Components\Select::make('protocol')
                    ->options([
                        'udp' => 'UDP',
                        'tcp' => 'TCP',
                    ])
                    ->required()
                    ->default('udp'),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('port')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('port')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn($record) => $record->is_online ? 'success' : 'danger')
                    ->getStateUsing(fn($record) => $record->is_online ? 'Online' : 'Offline'),
                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Jml User')
                    ->counts('employees'),
                Tables\Columns\TextColumn::make('last_activity')
                    ->label('Terakhir Aktif')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test_connection')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->action(function (Device $record) {
                        $service = new \App\Services\ZKTecoService();
                        if ($service->testConnection($record)) {
                            $record->update(['last_activity' => now(), 'is_online' => true]);
                            \Filament\Notifications\Notification::make()
                                ->title('Connection Successful')
                                ->success()
                                ->send();
                        } else {
                            $record->update(['is_online' => false]);
                            \Filament\Notifications\Notification::make()
                                ->title('Connection Failed')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('ping')
                    ->label('Ping')
                    ->icon('heroicon-o-command-line')
                    ->color('gray')
                    ->action(function (Device $record) {
                        $service = new \App\Services\ZKTecoService();
                        if ($service->ping($record)) {
                            $record->update(['last_activity' => now(), 'is_online' => true]);
                            \Filament\Notifications\Notification::make()
                                ->title('Ping Successful')
                                ->success()
                                ->send();
                        } else {
                            $record->update(['is_online' => false]);
                            \Filament\Notifications\Notification::make()
                                ->title('Ping Failed')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('info')
                    ->label('Info')
                    ->icon('heroicon-o-information-circle')
                    ->color('secondary')
                    ->modalHeading('Device Info')
                    ->action(function (Device $record) {
                        // Simulated info fetch or simple detailed view
                        // In real scenario, $zk->getVersion(), $zk->getSerialNumber()
                        \Filament\Notifications\Notification::make()
                            ->title('Device Information')
                            ->body("IP: {$record->ip_address}\nPort: {$record->port}\nProtocol: {$record->protocol}")
                            ->info()
                            ->send();
                    }),
                Tables\Actions\Action::make('pull_employees')
                    ->label('Pull Employees')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->action(function (Device $record) {
                        $service = new \App\Services\ZKTecoService();
                        $users = $service->getEmployees($record);

                        if (empty($users)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No employees found or connection failed')
                                ->warning()
                                ->send();
                            return;
                        }

                        $record->update(['last_activity' => now(), 'is_online' => true]);

                        $count = 0;
                        foreach ($users as $user) {
                            // Determine privilege: 14=Admin, 0=User (Adjust based on ZK SDK actual return)
                            // Often ZK returns 'role' or 'privilege'
                            $privilege = isset($user['role']) ? $user['role'] : 0;

                            $employee = \App\Models\Employee::updateOrCreate(
                                ['badge_number' => $user['uid']], // Using UID/Badge as unique identifier
                                [
                                    'name' => $user['name'],
                                    'card_number' => $user['cardno'] ?? null,
                                    'privilege' => $privilege,
                                    'password' => $user['password'] ?? null,
                                ]
                            );

                            // Sync with pivot table
                            \App\Models\DeviceEmployeeSync::firstOrCreate(
                                ['device_id' => $record->id, 'employee_id' => $employee->id],
                                ['is_synced_to_device' => true, 'synced_at' => now()]
                            );

                            $count++;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title("Synced {$count} employees successfully")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
