<?php

namespace App\Filament\Pages;

use App\Models\AttendanceLog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Response;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Laporan';
    protected static ?string $title = 'Export Laporan Absensi';
    protected static ?string $navigationGroup = 'Absensi';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth(),
            'end_date' => now(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Laporan')
                    ->description('Pilih rentang tanggal untuk export data absensi')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Dari Tanggal')
                            ->required()
                            ->maxDate(now()),

                        DatePicker::make('end_date')
                            ->label('Sampai Tanggal')
                            ->required()
                            ->maxDate(now())
                            ->afterOrEqual('start_date'),

                        Select::make('type')
                            ->label('Format Laporan')
                            ->options([
                                'csv_raw' => 'Raw Data (CSV) - Untuk Olah Excel',
                                'csv_summary' => 'Summary Per Pegawai (CSV)',
                            ])
                            ->default('csv_raw')
                            ->required(),
                    ])->columns(3),
            ])->statePath('data');
    }

    // ACTION DOWNLOAD
    public function downloadReport()
    {
        $data = $this->form->getState();

        $query = AttendanceLog::whereBetween('scan_time', [$data['start_date'], $data['end_date']])
            ->orderBy('scan_time');

        $logs = $query->get();

        if ($logs->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('Tidak Ada Data')
                ->body('Tidak ada log absensi pada rentang tanggal yang dipilih.')
                ->warning()
                ->send();
            return;
        }

        // Logic Export Sederhana ke CSV (Tanpa Library Berat)
        $csvData = "Badge ID,Nama,Waktu Scan,Tanggal,Jam,Mesin,Verifikasi\n";

        foreach ($logs as $log) {
            $name = \App\Models\Employee::where('badge_number', $log->badge_number)->value('name') ?? 'Unknown';
            $device = $log->device ? $log->device->name : ($log->device_serial ?? 'Unknown');
            $verifyMode = match ($log->verification_mode) {
                1 => 'Fingerprint',
                15 => 'Face',
                4 => 'Card',
                default => 'Unknown'
            };

            $csvData .= sprintf(
                "%s,\"%s\",%s,%s,%s,\"%s\",%s\n",
                $log->badge_number,
                $name,
                $log->scan_time,
                $log->scan_time->format('Y-m-d'),
                $log->scan_time->format('H:i:s'),
                $device,
                $verifyMode
            );
        }

        $filename = "laporan_absen_" . date('Ymd_Hi') . ".csv";

        return response()->streamDownload(function () use ($csvData) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}
