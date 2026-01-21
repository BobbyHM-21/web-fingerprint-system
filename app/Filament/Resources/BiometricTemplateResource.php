<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BiometricTemplateResource\Pages;
use App\Filament\Resources\BiometricTemplateResource\RelationManagers;
use App\Models\BiometricTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BiometricTemplateResource extends Resource
{
    protected static ?string $model = BiometricTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationGroup = 'Personalia';
    protected static ?string $navigationLabel = 'Data Jari & Wajah';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Biometrik';
    protected static ?string $pluralModelLabel = 'Data Biometrik';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('employee_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('finger_id')
                    ->required()
                    ->numeric(),
                Forms\Components\Textarea::make('template')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('size')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('valid')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('finger_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('size')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('valid')
                    ->boolean(),
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
            'index' => Pages\ManageBiometricTemplates::route('/'),
        ];
    }
}
