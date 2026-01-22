<?php

namespace App\Services;

use Rats\Zkteco\Lib\ZKTeco;
use Illuminate\Support\Collection;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * ZKTecoService - Antigravity Pattern
 * 
 * Service ini adalah "penerjemah" antara Laravel dan Mesin Fingerprint.
 * Menggunakan Wrapper Pattern agar mudah diganti library-nya nanti.
 * 
 * Fitur Utama:
 * - Auto Connect/Disconnect
 * - Return Laravel Collection (bukan Array mentah)
 * - Error Handling yang kuat
 * - Destructor untuk auto-cleanup
 */
class ZKTecoService
{
    protected ?ZKTeco $zk = null;
    protected string $ip;
    protected int $port;
    protected bool $isConnected = false;

    /**
     * Inisialisasi Service dengan IP & Port Target
     */
    public function __construct(string $ip, int $port = 4370)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->zk = new ZKTeco($this->ip, $this->port);
    }

    /**
     * Membuka koneksi ke mesin
     */
    public function connect(): bool
    {
        if ($this->isConnected) {
            return true;
        }

        try {
            if ($this->zk->connect()) {
                $this->isConnected = true;
                return true;
            }
            Log::warning("ZKTeco: Gagal connect ke {$this->ip}:{$this->port}");
            return false;
        } catch (Exception $e) {
            Log::error("ZKTeco Connection Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Menutup koneksi (Penting biar slot user di mesin gak penuh)
     */
    public function disconnect(): void
    {
        if ($this->isConnected && $this->zk) {
            $this->zk->disconnect();
            $this->isConnected = false;
        }
    }

    /**
     * Ambil Serial Number Mesin (Untuk Validasi ADMS)
     */
    public function getSerialNumber(): ?string
    {
        if (!$this->connect())
            return null;

        try {
            // Library kadang return string dengan null byte, kita bersihkan
            $sn = $this->zk->serialNumber();
            return $this->cleanString($sn);
        } catch (Exception $e) {
            Log::error("ZKTeco Get Serial Number Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ambil Semua User dari Mesin (Tanpa Template Jari)
     */
    public function getUsers(): Collection
    {
        if (!$this->connect())
            return collect([]);

        try {
            $users = $this->zk->getUser(); // Return array

            // Kita ubah array mentah jadi Collection rapi
            return collect($users)->map(function ($user) {
                return [
                    'uid' => $user['uid'], // ID Internal Mesin (1, 2, 3...)
                    'userid' => $this->cleanString($user['userid'] ?? ''), // Badge Number (NIK/String)
                    'name' => $this->cleanString($user['name'] ?? ''),
                    'role' => $user['role'] ?? 0,
                    'password' => $user['password'] ?? '',
                    'cardno' => $user['cardno'] ?? 0,
                ];
            });
        } catch (Exception $e) {
            Log::error("ZKTeco Get Users Error: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Ambil Template Jari User Tertentu
     */
    public function getFingerprints(int $uid): Collection
    {
        if (!$this->connect())
            return collect([]);

        try {
            // Solution X-100C biasanya support index 0-9 per user
            $templates = [];

            // Loop cek semua jari
            // Note: Ini agak lambat, gunakan dengan bijak
            for ($i = 0; $i <= 9; $i++) {
                $template = $this->zk->getUserTemplate($uid, $i);
                if ($template && !empty($template[2])) {
                    $templates[] = [
                        'finger_index' => $i,
                        'template_data' => $template[2], // Raw data template
                        'size' => $template[1] ?? 0
                    ];
                }
            }

            return collect($templates);
        } catch (Exception $e) {
            Log::error("ZKTeco Get Fingerprints Error: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Ambil SEMUA Template Jari dari Mesin (Untuk Sync)
     */
    public function getAllFingerprints(): Collection
    {
        if (!$this->connect())
            return collect([]);

        try {
            $fingerprints = $this->zk->getFingerprint();

            return collect($fingerprints)->map(function ($fp) {
                return [
                    'uid' => $fp['uid'],
                    'finger_id' => $fp['id'] ?? $fp['fid'] ?? 0,
                    'template' => $fp['template'] ?? $fp['tmp'] ?? '',
                    'size' => $fp['size'] ?? 0,
                ];
            });
        } catch (Exception $e) {
            Log::error("ZKTeco Get All Fingerprints Error: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Ambil Log Absensi (Attendance)
     */
    public function getAttendance(): Collection
    {
        if (!$this->connect())
            return collect([]);

        try {
            $logs = $this->zk->getAttendance();

            return collect($logs)->map(function ($log) {
                return [
                    'uid' => $log['uid'] ?? 0,
                    'badge_number' => $this->cleanString($log['id'] ?? $log['userid'] ?? ''),
                    'state' => $log['state'] ?? 0, // Masuk/Pulang/Lembur
                    'timestamp' => $log['timestamp'] ?? now(),
                    'type' => $log['type'] ?? 1, // 1=Finger, 4=Card, 15=Face
                ];
            });
        } catch (Exception $e) {
            Log::error("ZKTeco Get Attendance Error: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Upload User Baru ke Mesin (PUSH)
     */
    public function setUser(int $uid, string $badgeNumber, string $name, string $password = '', int $role = 0, int $cardNumber = 0): bool
    {
        if (!$this->connect())
            return false;

        try {
            // Set User Info
            return $this->zk->setUser($uid, $badgeNumber, $name, $password, $role, $cardNumber);
        } catch (Exception $e) {
            Log::error("ZKTeco Set User Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload Template Jari ke Mesin (PUSH Finger)
     */
    public function setFingerprint(int $uid, int $fingerIndex, string $templateData): bool
    {
        if (!$this->connect())
            return false;

        try {
            // Set Template
            return $this->zk->setUserTemplate($uid, $fingerIndex, $templateData);
        } catch (Exception $e) {
            Log::error("ZKTeco Set Fingerprint Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hapus User dari Mesin
     */
    public function deleteUser(int $uid): bool
    {
        if (!$this->connect())
            return false;

        try {
            return $this->zk->deleteUser($uid);
        } catch (Exception $e) {
            Log::error("ZKTeco Delete User Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear Attendance Logs (Hapus semua log absensi)
     */
    public function clearAttendance(): bool
    {
        if (!$this->connect())
            return false;

        try {
            return $this->zk->clearAttendance();
        } catch (Exception $e) {
            Log::error("ZKTeco Clear Attendance Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restart Mesin (Remote Reboot)
     */
    public function restart(): bool
    {
        if (!$this->connect())
            return false;

        try {
            return $this->zk->restart();
        } catch (Exception $e) {
            Log::error("ZKTeco Restart Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear Admin (Jaga-jaga kalau admin terkunci/lupa password)
     */
    public function clearAdmin(): bool
    {
        if (!$this->connect())
            return false;

        try {
            return $this->zk->clearAdmin();
        } catch (Exception $e) {
            Log::error("ZKTeco Clear Admin Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ping Device (Test Network Reachability)
     */
    public function ping(): bool
    {
        $ip = escapeshellarg($this->ip);

        // Windows menggunakan -n, Linux menggunakan -c
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $cmd = $isWindows
            ? "ping -n 3 -w 1000 {$ip}"
            : "ping -c 3 -W 1 {$ip}";

        exec($cmd, $output, $status);
        return $status === 0;
    }

    /**
     * Helper: Bersihkan string dari karakter aneh (Null bytes)
     */
    private function cleanString($value): string
    {
        if (is_string($value)) {
            // Hapus null byte dan trim spasi
            return trim(str_replace(chr(0), '', $value));
        }
        return (string) $value;
    }

    /**
     * Destructor: Pastikan koneksi putus saat class selesai dipakai
     * Ini penting agar slot koneksi di mesin tidak penuh
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
