<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ADMS Controller - Penerima Request dari Mesin Solution X-100C
 * 
 * Mesin ADMS bersifat "Push": Mesin yang aktif menghubungi server,
 * bukan server yang polling ke mesin.
 * 
 * Protokol Standar ZKTeco ADMS:
 * 1. Heartbeat: Mesin lapor diri setiap X detik
 * 2. Upload Data: Mesin kirim log absen baru
 * 3. Command: Mesin tanya "ada perintah untuk saya?"
 */
class AdmsController extends Controller
{
    /**
     * Endpoint 1: Heartbeat (Lapor Diri)
     * 
     * Mesin bertanya: "Halo Server, saya SN:XXX, ada perintah buat saya?"
     * URL: GET /iclock/getrequest?SN=123456
     * 
     * Response: "OK" (atau perintah khusus jika ada)
     */
    public function getrequest(Request $request)
    {
        $sn = $request->query('SN');

        if (!$sn) {
            Log::warning('ADMS: Heartbeat tanpa Serial Number');
            return response('ERROR: INVALID SN', 400);
        }

        // 1. Cari Mesin di Database berdasarkan Serial Number
        $device = Device::where('serial_number', $sn)->first();

        // Jika mesin belum terdaftar di web, tolak!
        if (!$device) {
            Log::warning("ADMS: Unknown device SN={$sn}");
            return response('ERROR: UNKNOWN DEVICE', 401);
        }

        // 2. Update Status "Online" & Waktu Terakhir Terlihat
        $device->update([
            'last_activity' => now(),
            'is_online' => true,
            'ip_address' => $request->ip(), // Catat IP Publik cabang (berguna buat debug)
            'protocol' => 'push', // Pastikan tercatat sebagai ADMS
        ]);

        Log::info("ADMS: Heartbeat dari {$device->name} (SN: {$sn}, IP: {$request->ip()})");

        // 3. Cek Antrian Perintah (Fitur Push Data akan masuk sini nanti)
        // Untuk sekarang, kita jawab "OK" saja biar mesin tenang.
        return response('OK', 200);
    }

    /**
     * Endpoint 2: Upload Data (Cdata)
     * 
     * Mesin melapor: "Ini ada log absen baru, atau user baru."
     * URL: GET/POST /iclock/cdata?SN=123456&table=ATTLOG&...
     * 
     * Response: "OK" (konfirmasi data diterima)
     */
    public function cdata(Request $request)
    {
        $sn = $request->query('SN');
        $table = $request->query('table'); // Apa isi paketnya? (ATTLOG / OPERLOG / USER)

        // Validasi Mesin
        $device = Device::where('serial_number', $sn)->first();

        if (!$device) {
            Log::warning("ADMS: Upload dari unknown device SN={$sn}");
            return response('ERROR: UNKNOWN DEVICE', 401);
        }

        // Update status online juga saat upload
        $device->update([
            'last_activity' => now(),
            'is_online' => true,
        ]);

        // KASUS A: Upload Log Absensi
        if ($table == 'ATTLOG') {
            return $this->processAttendanceLog($request, $device);
        }

        // KASUS B: Mesin lapor info teknis (Sisa memori, versi firmware)
        if ($table == 'OPERLOG') {
            Log::info("ADMS: OPERLOG dari {$device->name}");
            // Bisa disimpan kalau mau, tapi return OK aja cukup
            return response('OK', 200);
        }

        // KASUS C: Upload User Baru (jika mesin support)
        if ($table == 'USER') {
            Log::info("ADMS: USER upload dari {$device->name}");
            // TODO: Process user data jika diperlukan
            return response('OK', 200);
        }

        // Default: Terima kasih
        Log::info("ADMS: Unknown table '{$table}' dari {$device->name}");
        return response('OK', 200);
    }

    /**
     * Logic Memproses Data Absensi Mentah
     * 
     * Format data dari mesin biasanya text panjang dipisah tab/newline
     * Contoh:
     * 101<TAB>2024-05-20 08:00:00<TAB>0<TAB>1
     * 102<TAB>2024-05-20 08:05:00<TAB>0<TAB>1
     */
    private function processAttendanceLog(Request $request, Device $device)
    {
        // Ambil body content (Raw Data)
        $content = $request->getContent();

        if (empty($content)) {
            // Kadang dikirim via POST field, bukan body raw
            Log::info("ADMS: Empty ATTLOG dari {$device->name}");
            return response('OK', 200);
        }

        // Pecah per baris
        $rows = explode("\n", $content);
        $count = 0;
        $errors = 0;

        foreach ($rows as $row) {
            if (empty(trim($row)))
                continue;

            // Pecah per kolom (Biasanya dipisah Tab \t)
            $cols = explode("\t", $row);

            // Format standar ADMS:
            // [0] ID/PIN (Badge Number)
            // [1] Waktu (YYYY-MM-DD HH:MM:SS)
            // [2] Status (0=Check In, 1=Check Out, dll)
            // [3] Verify Type (1=Finger, 4=Card, 15=Face)

            if (count($cols) >= 2) {
                $badgeNumber = trim($cols[0]);
                $scanTime = trim($cols[1]);
                $status = isset($cols[2]) ? intval($cols[2]) : 0;
                $verifyMode = isset($cols[3]) ? intval($cols[3]) : 1; // Default: Finger

                // Simpan ke Database (Gunakan try-catch biar 1 error gak bikin batal semua)
                try {
                    AttendanceLog::firstOrCreate(
                        [
                            'badge_number' => $badgeNumber,
                            'scan_time' => $scanTime,
                            'device_id' => $device->id,
                        ],
                        [
                            'status' => $status,
                            'verification_mode' => $verifyMode,
                            'created_at' => now(),
                        ]
                    );
                    $count++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("ADMS: Gagal simpan log {$badgeNumber} @ {$scanTime}: " . $e->getMessage());
                }
            }
        }

        Log::info("ADMS: Menerima {$count} log dari {$device->name}" . ($errors > 0 ? " ({$errors} errors)" : ""));
        return response('OK', 200);
    }

    /**
     * Endpoint 3: Device Command
     * 
     * Mesin mengambil detail perintah yang sudah diantrekan
     * URL: POST /iclock/devicecmd
     * 
     * Response: Perintah dalam format khusus (atau "OK" jika tidak ada)
     */
    public function devicecmd(Request $request)
    {
        $sn = $request->query('SN');

        $device = Device::where('serial_number', $sn)->first();

        if (!$device) {
            return response('ERROR: UNKNOWN DEVICE', 401);
        }

        // TODO: Implementasi antrian perintah
        // Contoh perintah yang bisa dikirim:
        // - DATA UPDATE user: Kirim user baru ke mesin
        // - DATA DELETE user: Hapus user dari mesin
        // - DATA CLEAR data: Hapus semua log

        Log::info("ADMS: devicecmd request dari {$device->name}");

        // Return OK dulu untuk sekarang (tidak ada perintah)
        return response('OK', 200);
    }
}
