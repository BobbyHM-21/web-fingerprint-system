<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        {{-- Column 1: DB Only --}}
        <x-filament::section>
            <x-slot name="heading">
                Missing in Device ({{ count($diffSummary['only_in_db']) }})
            </x-slot>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($diffSummary['only_in_db'] as $emp)
                    <div class="p-2 bg-red-50 rounded border border-red-100 flex justify-between items-center group">
                        <div>
                            <div class="font-bold">{{ $emp['name'] }}</div>
                            <div class="text-xs text-gray-500">ID: {{ $emp['badge_number'] }}</div>
                        </div>
                        <x-filament::button size="xs" color="success"
                            wire:click="syncSingleToDevice('{{ $emp['badge_number'] }}')">
                            <span wire:loading.remove
                                wire:target="syncSingleToDevice('{{ $emp['badge_number'] }}')">Push</span>
                            <span wire:loading wire:target="syncSingleToDevice('{{ $emp['badge_number'] }}')">...</span>
                        </x-filament::button>
                    </div>
                @endforeach
            </div>

            <x-slot name="footer">
                <x-filament::button wire:click="syncToDevice" color="success" class="w-full"
                    :disabled="empty($diffSummary['only_in_db'])">
                    <span wire:loading.remove wire:target="syncToDevice">Push All
                        ({{ count($diffSummary['only_in_db']) }})</span>
                    <span wire:loading wire:target="syncToDevice">Pushing...</span>
                </x-filament::button>
            </x-slot>
        </x-filament::section>

        {{-- Column 2: Synced Stats --}}
        <x-filament::section class="text-center">
            <x-slot name="heading">
                Synchronized
            </x-slot>
            <div class="py-10">
                <div class="text-5xl font-bold text-success-600">{{ $diffSummary['synced_count'] }}</div>
                <div class="text-gray-500">Users Match</div>
                <br>
                <div class="text-sm text-gray-400">
                    Device: {{ \App\Models\Device::find($selectedDeviceId)?->name ?? 'None Selected' }}
                </div>

                <div class="mt-4">
                    <select wire:model="selectedDeviceId"
                        class="border rounded p-2 w-full dark:bg-gray-800 dark:border-gray-700">
                        @foreach(\App\Models\Device::all() as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-4">
                    <x-filament::button wire:click="scanDifferences" size="lg" icon="heroicon-o-arrow-path">
                        <span wire:loading.remove wire:target="scanDifferences">Start Scan</span>
                        <span wire:loading wire:target="scanDifferences">Scanning...</span>
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Column 3: Device Only --}}
        <x-filament::section>
            <x-slot name="heading">
                Missing in Database ({{ count($diffSummary['only_in_device']) }})
            </x-slot>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($diffSummary['only_in_device'] as $u)
                    <div class="p-2 bg-blue-50 rounded border border-blue-100 flex justify-between items-center">
                        <div>
                            <div class="font-bold">{{ $u['name'] }}</div>
                            <div class="text-xs text-gray-500">ID: {{ $u['userid'] }}</div>
                        </div>
                        <x-filament::button size="xs" color="primary"
                            wire:click="syncSingleFromDevice('{{ $u['userid'] }}')">
                            <span wire:loading.remove wire:target="syncSingleFromDevice('{{ $u['userid'] }}')">Import</span>
                            <span wire:loading wire:target="syncSingleFromDevice('{{ $u['userid'] }}')">...</span>
                        </x-filament::button>
                    </div>
                @endforeach
            </div>

            <x-slot name="footer">
                <x-filament::button wire:click="syncFromDevice" color="primary" class="w-full"
                    :disabled="empty($diffSummary['only_in_device'])">
                    <span wire:loading.remove wire:target="syncFromDevice">Import All
                        ({{ count($diffSummary['only_in_device']) }})</span>
                    <span wire:loading wire:target="syncFromDevice">Importing...</span>
                </x-filament::button>
            </x-slot>
        </x-filament::section>

    </div>

    {{-- Full Device Content Section --}}
    @if(!empty($deviceContents))
        <div class="mt-8">
            <x-filament::section>
                <x-slot name="heading">
                    Full Device Content: {{ \App\Models\Device::find($selectedDeviceId)?->name }}
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-6 py-3">User ID</th>
                                <th scope="col" class="px-6 py-3">Name</th>
                                <th scope="col" class="px-6 py-3">Role</th>
                                <th scope="col" class="px-6 py-3 text-center">Fingerprints</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deviceContents as $u)
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                    <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {{ $u['userid'] }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $u['name'] }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $u['role'] == 14 ? 'Admin' : 'User' }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                            {{ $u['finger_count'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    @endif

</x-filament-panels::page>