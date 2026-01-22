<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Device;
use App\Models\Employee;
use App\Models\BiometricTemplate;
use App\Models\DeviceEmployeeSync;
use App\Services\ZKTecoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Data Pegawai';
    protected static ?string $modelLabel = 'Pegawai';
    protected static ?string $navigationGroup = 'Personalia';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Pegawai')
                    ->schema([
                        Forms\Components\TextInput::make('badge_number')
                            ->label('Nomor Badge / NIK')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->helperText('ID ini harus sama dengan User ID di mesin fingerprint.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('department')
                            ->label('Departemen / Divisi')
                            ->placeholder('Contoh: IT, HRD, Gudang'),

                        Forms\Components\TextInput::make('position')
                            ->label('Jabatan')
                            ->placeholder('Contoh: Staff, Manager'),

                        Forms\Components\TextInput::make('card_number')
                            ->label('Nomor Kartu (RFID)')
                            ->numeric(),

                        Forms\Components\TextInput::make('password')
                            ->label('Password Mesin')
                            ->password()
                            ->revealable()
                            ->helperText('Password untuk login di mesin (opsional).'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('badge_number')
                    ->label('Badge ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department')
                    ->label('Departemen')
                    ->sortable()
                    ->toggleable(),

                // Kolom Canggih: Menghitung jumlah jari yang terdaftar
                Tables\Columns\TextColumn::make('biometric_templates_count')
                    ->counts('biometricTemplates')
                    ->label('Jari')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => $state . ' Jari'),

                // Kolom Canggih: Menampilkan di berapa mesin dia terdaftar
                Tables\Columns\TextColumn::make('devices_count')
                    ->counts('devices')
                    ->label('Sync')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => $state . ' Mesin')
                    ->icon('heroicon-m-arrow-path'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->label('Filter Departemen'),
            ])
            ->headerActions([
                // ACTION SPESIAL: TARIK DATA DARI MESIN (PULL)
                Tables\Actions\Action::make('pull_from_device')
                    ->label('Tarik Data Mesin')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->form([
                        Select::make('device_id')
                            ->label('Pilih Mesin Sumber')
                            ->options(Device::where('is_active', true)->where('protocol', '!=', 'offline')->pluck('name', 'id'))
                            ->required()
                            ->helperText('Pilih mesin yang Online/Direct IP untuk ditarik datanya.'),
                    ])
                    ->action(function (array $data) {
                        self::pullDataProcess($data['device_id']);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    // FITUR BONUS: Push ke Mesin (Bulk Action)
                    Tables\Actions\BulkAction::make('push_to_device')
                        ->label('Kirim ke Mesin')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->form([
                            Select::make('device_id')
                                ->label('Pilih Mesin Tujuan')
                                ->options(Device::where('is_active', true)->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (array $data, \Illuminate\Database\Eloquent\Collection $records) {
                            // Logic Push akan kita bahas detail nanti kalau Pull sudah sukses
                            Notification::make()->title('Fitur Push akan segera hadir!')->info()->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Bisa tambahkan RelationManager untuk lihat detail jari
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

    /**
     * Logic Utama: Menarik Data (Pull) dari Mesin
     * Ditaruh di static function agar bersih
     */
    protected static function pullDataProcess($deviceId)
    {
        $device = Device::find($deviceId);

        if (!$device) {
            Notification::make()->title('Mesin tidak ditemukan')->danger()->send();
            return;
        }

        // 1. Konek ke Mesin
        $zk = new ZKTecoService($device->ip_address, $device->port);

        if (!$zk->connect()) {
            Notification::make()->title('Gagal Konek ke Mesin')->body('Cek IP atau Kabel LAN.')->danger()->send();
            return;
        }

        try {
            // 2. Ambil List User
            $users = $zk->getUsers();
            $countNew = 0;
            $countUpdated = 0;

            DB::beginTransaction(); // Pakai Transaction biar aman

            foreach ($users as $userData) {
                // Update atau Buat Pegawai Baru berdasarkan Badge Number (NIK)
                $employee = Employee::updateOrCreate(
                    ['badge_number' => $userData['userid']], // Kunci pencarian
                    [
                        'name' => $userData['name'],
                        'password' => $userData['password'],
                        'card_number' => $userData['cardno'],
                        // 'privilege' => $userData['role'],
                    ]
                );

                if ($employee->wasRecentlyCreated) {
                    $countNew++;
                } else {
                    $countUpdated++;
                }

                // 3. Ambil Template Jari (Looping 0-9)
                // Hati-hati: Ini bisa lama jika user banyak. 
                // Idealnya kita cek dulu apakah jumlah jari di DB < jumlah di mesin.
                $templates = $zk->getFingerprints($userData['uid']);

                foreach ($templates as $tpl) {
                    BiometricTemplate::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'finger_id' => $tpl['finger_index']
                        ],
                        [
                            'template' => $tpl['template_data'],
                        ]
                    );
                }

                // 4. Catat bahwa user ini SUDAH ada di mesin ini
                DeviceEmployeeSync::updateOrCreate(
                    [
                        'device_id' => $device->id,
                        'employee_id' => $employee->id
                    ],
                    [
                        'is_synced_to_device' => true,
                        'synced_at' => now()
                    ]
                );
            }

            DB::commit();

            Notification::make()
                ->title('Sinkronisasi Sukses! 🚀')
                ->body("Ditarik: {$countNew} Pegawai Baru, {$countUpdated} Terupdate.")
                ->success()
                ->persistent() // Notifikasi tidak hilang otomatis biar admin baca
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error saat Sync')->body($e->getMessage())->danger()->send();
        }
    }
}
