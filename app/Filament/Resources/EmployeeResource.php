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
use Illuminate\Support\Facades\Log;

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
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->description(fn(Employee $record) => $record->position ?? '-'),

                // --- FITUR MATRIX DISTRIBUSI (VISUALISASI) ---
                Tables\Columns\TextColumn::make('distribution_matrix')
                    ->label('Sebaran Data')
                    ->html() // Mengizinkan HTML custom
                    ->state(function (Employee $record) {
                        // Ambil semua mesin aktif (Cache query ini biar ringan)
                        static $allDevices = null;
                        if (!$allDevices) {
                            $allDevices = Device::where('is_active', true)->orderBy('name')->get();
                        }

                        // Ambil ID mesin di mana user ini sudah ada
                        $syncedDeviceIds = $record->devices->pluck('id')->toArray();

                        $html = '<div class="flex gap-1 flex-wrap">';

                        foreach ($allDevices as $device) {
                            // Cek apakah user ada di mesin ini?
                            $isSynced = in_array($device->id, $syncedDeviceIds);

                            // Style Visual
                            $color = $isSynced ? 'bg-success-500 text-white' : 'bg-gray-200 text-gray-400';
                            $tooltip = $device->name . ($isSynced ? ' (Tersedia)' : ' (Belum Ada)');

                            // Kode Lokasi (3 Huruf Pertama, misal: JKT, SBY)
                            $code = strtoupper(substr($device->location ?? $device->name, 0, 3));

                            $html .= "
                                <div class='px-1.5 py-0.5 rounded text-[10px] font-bold {$color}' title='{$tooltip}' style='cursor:help'>
                                    {$code}
                                </div>
                            ";
                        }

                        $html .= '</div>';
                        return $html;
                    }),

                Tables\Columns\TextColumn::make('biometric_templates_count')
                    ->counts('biometricTemplates')
                    ->label('Jari')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger'),
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

                    // --- FITUR EKSEKUSI: PUSH (KIRIM) DATA ---
                    Tables\Actions\BulkAction::make('push_to_device')
                        ->label('Kirim ke Mesin (Push)')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->form([
                            Select::make('device_id')
                                ->label('Pilih Mesin Tujuan')
                                ->options(Device::where('is_active', true)->where('protocol', '!=', 'offline')->pluck('name', 'id'))
                                ->required()
                                ->helperText('Hanya mesin Online (Direct IP) yang bisa menerima Push instan.'),
                        ])
                        ->action(function (array $data, \Illuminate\Database\Eloquent\Collection $records) {
                            self::pushDataProcess($data['device_id'], $records);
                        })
                        ->deselectRecordsAfterCompletion(),
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
                ->persistent()
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->title('Error saat Sync')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * LOGIC PUSH: Mengirim data pegawai terpilih ke mesin tertentu
     */
    protected static function pushDataProcess($deviceId, $employees)
    {
        $device = Device::find($deviceId);

        // Validasi Dasar
        if (!$device || $device->protocol === 'offline') {
            Notification::make()->title('Gagal: Mesin Offline/USB tidak bisa menerima Push.')->danger()->send();
            return;
        }

        // Koneksi ke Mesin
        $zk = new ZKTecoService($device->ip_address, $device->port);
        if (!$zk->connect()) {
            Notification::make()->title("Gagal Connect ke {$device->name}")->danger()->send();
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($employees as $emp) {
            try {
                // 1. Upload User Info
                // Convert privilege web ke privilege mesin (jika perlu)
                $role = ($emp->position === 'Manager') ? 14 : 0;

                // Perintah PUSH User
                $zk->setUser(
                    uid: $emp->id, // Gunakan ID database sebagai UID mesin
                    badgeNumber: $emp->badge_number,
                    name: $emp->name,
                    password: $emp->password ?? '',
                    role: $role,
                    cardNumber: (int) ($emp->card_number ?? 0)
                );

                // 2. Upload Template Jari (Jika ada)
                foreach ($emp->biometricTemplates as $finger) {
                    $zk->setFingerprint(
                        uid: $emp->id,
                        fingerIndex: $finger->finger_id,
                        templateData: $finger->template
                    );
                }

                // 3. Update Matrix (Tandai sudah sync)
                DeviceEmployeeSync::updateOrCreate(
                    ['device_id' => $device->id, 'employee_id' => $emp->id],
                    ['is_synced_to_device' => true, 'synced_at' => now()]
                );

                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                Log::error("Push failed for {$emp->badge_number}: " . $e->getMessage());
            }
        }

        Notification::make()
            ->title("Push Selesai")
            ->body("Berhasil: {$successCount}, Gagal: {$failCount}")
            ->success()
            ->send();
    }
}
