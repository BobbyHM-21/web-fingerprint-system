<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceEmployeeSyncResource\Pages;
use App\Filament\Resources\DeviceEmployeeSyncResource\RelationManagers;
use App\Models\DeviceEmployeeSync;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeviceEmployeeSyncResource extends Resource
{
    protected static ?string $model = DeviceEmployeeSync::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $navigationLabel = 'Status Sinkronisasi';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Log Sinkronisasi';
    protected static ?string $pluralModelLabel = 'Status Sinkronisasi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('device_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('employee_id')
                    ->required()
                    ->numeric(),
                Forms\Components\Toggle::make('is_synced_to_device')
                    ->required(),
                Forms\Components\DateTimePicker::make('synced_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_synced_to_device')
                    ->boolean(),
                Tables\Columns\TextColumn::make('synced_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDeviceEmployeeSyncs::route('/'),
        ];
    }
}
