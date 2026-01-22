<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="downloadReport" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end gap-3">
                <x-filament::button type="submit" icon="heroicon-m-arrow-down-tray" wire:loading.attr="disabled">
                    DOWNLOAD DATA
                    <x-filament::loading-indicator class="h-5 w-5 ml-2" wire:loading />
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            💡 Tips Penggunaan
        </x-slot>

        <div class="prose dark:prose-invert max-w-none">
            <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-2">
                <li>Pilih rentang tanggal <strong>maksimal 1 bulan</strong> agar proses download cepat.</li>
                <li>File CSV bisa langsung dibuka di <strong>Microsoft Excel</strong> atau <strong>Google
                        Sheets</strong>.</li>
                <li>Format "Raw Data" cocok untuk analisis detail per scan.</li>
                <li>Data akan otomatis ter-download ke folder <strong>Downloads</strong> Anda.</li>
            </ul>

            <div
                class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                <p class="text-xs text-amber-700 dark:text-amber-300">
                    ⚠️ <strong>Perhatian:</strong> Jika data sangat banyak (>10,000 baris), proses download mungkin
                    memakan waktu beberapa detik.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>