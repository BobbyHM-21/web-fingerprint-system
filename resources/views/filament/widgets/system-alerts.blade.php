<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Peringatan Sistem
        </x-slot>

        <div class="space-y-2">
            @if($offlineDevices->isEmpty())
                <div class="flex items-center text-success-600">
                    <x-heroicon-o-check-circle class="w-5 h-5 mr-2" />
                    <span>Semua sistem berjalan normal.</span>
                </div>
            @else
                @foreach($offlineDevices as $device)
                    <div class="flex items-center p-2 mb-2 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400"
                        role="alert">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 mr-2" />
                        <span class="font-medium">Perhatian!</span>&nbsp;Mesin <strong>{{ $device->name }}</strong>
                        ({{ $device->ip_address }}) sedang Offline atau belum sync lebih dari 10 menit.
                    </div>
                @endforeach
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>