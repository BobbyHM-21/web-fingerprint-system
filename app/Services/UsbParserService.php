<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceEmployeeSync;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * USB Parser Service
 * 
 * Service untuk membedah file mentah dari mesin fingerprint
 * Format: SSR (Self-Service Recorder) - Text based dengan Tab separator
 */
class UsbParserService
{
    /**
     * Parsing File Log Absensi (attlog.dat)
     * 
     * Format Umum: BadgeNumber <tab> DateTime <tab> Status <tab> VerifyMode
     * Contoh: 101    2024-05-20 08:00:00    0    1
     */
    public function parseAttLog($fileContent, $deviceSerialNumber)
    {
        $lines = explode("\n", $fileContent);
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            // Format Umum: ID <tab> Waktu <tab> Status <tab> VerifyMode
            // Bisa juga dipisah spasi/tab multiple
            $parts = preg_split('/\s+/', $line);

            if (count($parts) >= 2) {
                try {
                    $badgeNumber = $parts[0];

                    // Gabung Tanggal & Jam jika terpisah
                    // Format bisa: "2024-05-20 08:00:00" atau "2024-05-20" "08:00:00"
                    $scanTime = $parts[1];
                    if (isset($parts[2]) && strpos($parts[2], ':') !== false) {
                        $scanTime .= ' ' . $parts[2];
                    }

                    // Cek format tanggal/jam valid
                    if (strtotime($scanTime) === false) {
                        Log::warning("Invalid datetime format: $scanTime");
                        continue;
                    }

                    // Status & Verify Mode bisa di posisi berbeda tergantung format
                    $status = 0; // Default: Check In
                    $verifyMode = 1; // Default: Finger

                    // Coba deteksi dari kolom selanjutnya
                    for ($i = 2; $i < count($parts); $i++) {
                        if (is_numeric($parts[$i])) {
                            if (!isset($statusSet)) {
                                $status = (int) $parts[$i];
                                $statusSet = true;
                            } else {
                                $verifyMode = (int) $parts[$i];
                                break;
                            }
                        }
                    }

                    AttendanceLog::firstOrCreate(
                        [
                            'badge_number' => $badgeNumber,
                            'scan_time' => $scanTime,
                            'device_id' => null, // USB import tidak punya device_id spesifik
                        ],
                        [
                            'status' => $status,
                            'verification_mode' => $verifyMode,
                            'created_at' => now(),
                        ]
                    );
                    $count++;
                } catch (\Exception $e) {
                    // Skip baris error
                    Log::warning("Gagal parse baris log: $line - " . $e->getMessage());
                }
            }
        }

        return $count;
    }

    /**
     * Parsing File User & Template (user.dat)
     * 
     * Format SSR (Text Based):
     * UID <tab> BadgeNumber <tab> Name <tab> Password <tab> Role <tab> CardNo
     */
    public function parseUserDat($fileContent, $deviceId)
    {
        $device = Device::find($deviceId);
        if (!$device)
            throw new \Exception("Mesin tidak ditemukan");

        $lines = explode("\n", $fileContent);
        $newUsers = 0;
        $updatedUsers = 0;

        DB::beginTransaction();
        try {
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                // Format SSR User biasanya:
                // UID <tab> BadgeNumber <tab> Name <tab> Password <tab> Role <tab> CardNo
                $parts = explode("\t", $line);

                if (count($parts) >= 3) {
                    // Kolom bisa berbeda tergantung firmware, tapi umumnya:
                    // [0]=UID, [1]=Badge, [2]=Name, [3]=Password, [4]=Role, [5]=Card
                    $badgeNumber = trim($parts[1]); // Kolom ke-2 biasanya NIK/Badge
                    $name = trim($parts[2]);

                    // Skip jika badge kosong
                    if (empty($badgeNumber))
                        continue;

                    // Logic UpdateOrCreate Pegawai
                    $employee = Employee::updateOrCreate(
                        ['badge_number' => $badgeNumber],
                        [
                            'name' => $name,
                            'password' => isset($parts[3]) && !empty($parts[3]) ? $parts[3] : null,
                            'card_number' => isset($parts[5]) && !empty($parts[5]) ? $parts[5] : null,
                            'privilege' => isset($parts[4]) ? (int) $parts[4] : 0,
                        ]
                    );

                    if ($employee->wasRecentlyCreated) {
                        $newUsers++;
                    } else {
                        $updatedUsers++;
                    }

                    // Tandai sudah sync di mesin ini
                    DeviceEmployeeSync::updateOrCreate(
                        ['device_id' => $device->id, 'employee_id' => $employee->id],
                        ['is_synced_to_device' => true, 'synced_at' => now()]
                    );
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return ['new' => $newUsers, 'updated' => $updatedUsers];
    }
}
