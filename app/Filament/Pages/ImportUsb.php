<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportUsb extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Absensi';
    protected static ?int $navigationSort = 2;

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
                FileUpload::make('attlog')
                    ->label('Attendance Log (attlog.dat)')
                    ->disk('local')
                    ->directory('imports')
                    ->preserveFilenames(),
                FileUpload::make('user')
                    ->label('User Data (user.dat)')
                    ->disk('local')
                    ->directory('imports')
                    ->preserveFilenames(),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();

        $service = new \App\Services\UsbParserService();
        $count = 0;

        if (!empty($data['attlog'])) {
            // In local disk, path is relative to storage/app
            $path = storage_path('app/' . $data['attlog']);
            $logs = $service->parseAttLog($path);

            // logic to save logs (simplified)
            foreach ($logs as $log) {
                // Find or create
                \App\Models\AttendanceLog::firstOrCreate(
                    ['badge_number' => $log['badge_number'], 'timestamp' => $log['timestamp']],
                    ['status' => $log['status'], 'verification_mode' => $log['verification']]
                );
                $count++;
            }
        }

        Notification::make()
            ->title('Imported ' . $count . ' logs successfully')
            ->success()
            ->send();
    }
}
