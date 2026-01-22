<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                PROSES IMPORT

                <x-filament::loading-indicator class="h-5 w-5 ml-2" wire:loading />
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            📖 Panduan Import USB
        </x-slot>

        <div class="prose dark:prose-invert max-w-none">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Langkah-Langkah:</h4>
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-2">
                <li>Colokkan <strong>Flashdisk</strong> ke Mesin Fingerprint (Solution X-100C).</li>
                <li>Masuk Menu <strong>Data Mgt</strong> → <strong>Download Data</strong>.</li>
                <li>Pilih <strong>AttLog</strong> untuk absensi, atau <strong>User Data</strong> untuk pegawai.</li>
                <li>Tunggu proses download selesai (biasanya 10-30 detik).</li>
                <li>Cabut Flashdisk dan pindahkan file ke Komputer.</li>
                <li>Upload file tersebut di form di atas.</li>
            </ol>

            <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-xs text-blue-700 dark:text-blue-300">
                    💡 <strong>Tips:</strong> File biasanya bernama <code
                        class="px-1 py-0.5 bg-blue-100 dark:bg-blue-800 rounded">attlog.dat</code> atau <code
                        class="px-1 py-0.5 bg-blue-100 dark:bg-blue-800 rounded">user.dat</code>.
                    Jika file berformat .txt, tetap bisa diupload.
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>