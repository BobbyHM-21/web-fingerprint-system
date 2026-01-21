<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Personalia';
    protected static ?string $navigationLabel = 'Data Karyawan';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Karyawan';
    protected static ?string $pluralModelLabel = 'Data Karyawan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('badge_number')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->maxLength(255),
                Forms\Components\TextInput::make('card_number')
                    ->maxLength(255),
                Forms\Components\Select::make('privilege')
                    ->options([
                        0 => 'User',
                        14 => 'Admin',
                    ])
                    ->required()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('badge_number')
                    ->label('Badge ID (NIK)')
                    ->weight('bold')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Identitas')
                    ->description(fn(Employee $record) => $record->job_title ?? 'Karyawan')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('biometric_templates_count')
                    ->label('Jml Jari')
                    ->counts('biometricTemplates')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('distribution_matrix')
                    ->label('Posisi Data')
                    ->html()
                    ->getStateUsing(function (Employee $record) {
                        $allDevices = \App\Models\Device::all();
                        $syncedDeviceIds = $record->devices->pluck('id')->toArray();

                        $html = '<div class="flex gap-1 flex-wrap">';
                        foreach ($allDevices as $device) {
                            $isSynced = in_array($device->id, $syncedDeviceIds);
                            $icon = $isSynced ? '✅' : '❌';
                            $color = $isSynced ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            $shortName = strtoupper(substr($device->name, 0, 3));

                            $html .= "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {$color}'>[{$shortName}: {$icon}]</span>";
                        }
                        $html .= '</div>';
                        return $html;
                    }),
                Tables\Columns\TextColumn::make('card_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('privilege')
                    ->formatStateUsing(fn(int $state): string => match ($state) {
                        0 => 'User',
                        14 => 'Admin',
                        default => 'Unknown',
                    })
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('push_to_device')
                        ->label('Push to Device')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('success')
                        ->form([
                            Forms\Components\CheckboxList::make('devices')
                                ->label('Select Target Devices')
                                ->options(\App\Models\Device::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $devices = \App\Models\Device::whereIn('id', $data['devices'])->get();
                            $service = new \App\Services\ZKTecoService();
                            $successCount = 0;
                            $failCount = 0;

                            foreach ($devices as $device) {
                                $zk = $service->connect($device);
                                if (!$zk) {
                                    $failCount++;
                                    continue;
                                }

                                foreach ($records as $employee) {
                                    try {
                                        $zk->setUser(
                                            (int) $employee->badge_number,
                                            (int) $employee->badge_number,
                                            $employee->name,
                                            $employee->password ?? '',
                                            (int) $employee->privilege,
                                            $employee->card_number ?? ''
                                        );

                                        \App\Models\DeviceEmployeeSync::firstOrCreate(
                                            ['device_id' => $device->id, 'employee_id' => $employee->id],
                                            ['is_synced_to_device' => true, 'synced_at' => now()]
                                        );

                                        $successCount++;
                                    } catch (\Exception $e) {
                                        // Log error
                                    }
                                }
                                $zk->disconnect();
                            }

                            \Filament\Notifications\Notification::make()
                                ->title("Push process completed")
                                ->body("Success: {$successCount}, Failed Devices: {$failCount}")
                                ->success()
                                ->send();
                        }),
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
