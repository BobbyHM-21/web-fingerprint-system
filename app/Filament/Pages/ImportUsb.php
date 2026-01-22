<?php

namespace App\Filament\Pages;

use App\Models\Device;
use App\Services\UsbParserService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ImportUsb extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Import USB';
    protected static ?string $title = 'Import Data Manual (USB)';
    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.import-usb';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Upload File dari Mesin')
                    ->description('Upload file .dat yang diambil dari Flashdisk mesin fingerprint.')
                    ->schema([
                        Select::make('device_id')
                            ->label('Pilih Mesin Asal')
                            ->options(Device::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->helperText('Penting: Pastikan memilih mesin yang benar agar data log tidak tertukar.'),

                        Select::make('import_type')
                            ->label('Jenis Data')
                            ->options([
                                'attlog' => 'Log Absensi (attlog.dat)',
                                'user' => 'Data Pegawai (user.dat)',
                            ])
                            ->required()
                            ->reactive(),

                        FileUpload::make('attachment')
                            ->label('File Data (.dat / .txt)')
                            ->disk('local') // Simpan sementara di local storage
                            ->directory('usb-imports')
                            ->required()
                            ->acceptedFileTypes(['text/plain', 'application/octet-stream', 'application/x-msdownload'])
                            ->maxSize(5120) // Max 5MB
                            ->helperText('Upload file attlog.dat atau user.dat dari mesin fingerprint.'),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();
        $device = Device::find($data['device_id']);

        if (!$device) {
            Notification::make()->title('Mesin tidak valid')->danger()->send();
            return;
        }

        // Ambil path file fisik
        $filePath = Storage::disk('local')->path($data['attachment']);

        // Baca isi file
        $content = file_get_contents($filePath);

        $parser = new UsbParserService();

        try {
            if ($data['import_type'] === 'attlog') {
                // PROSES LOG ABSENSI
                $count = $parser->parseAttLog($content, $device->serial_number);

                Notification::make()
                    ->title('Import Log Berhasil! 🎉')
                    ->body("Berhasil menyimpan {$count} data log absensi baru.")
                    ->success()
                    ->persistent()
                    ->send();

            } elseif ($data['import_type'] === 'user') {
                // PROSES USER DATA
                $result = $parser->parseUserDat($content, $device->id);

                Notification::make()
                    ->title('Import Pegawai Berhasil! 🎉')
                    ->body("Pegawai Baru: {$result['new']}, Terupdate: {$result['updated']}")
                    ->success()
                    ->persistent()
                    ->send();
            }

            // Hapus file setelah diproses biar bersih
            Storage::disk('local')->delete($data['attachment']);

            // Reset form
            $this->form->fill();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal Memproses File')
                ->body('Format file tidak sesuai atau rusak. Error: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
