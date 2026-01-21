<?php

namespace App\Services;

use App\Models\Device;
use Rats\Zkteco\Lib\ZKTeco;
use Illuminate\Support\Facades\Log;

class ZKTecoService
{
    /**
     * Connect to the ZKTeco device.
     * 
     * @param Device $device
     * @return ZKTeco|false
     */
    public function connect(Device $device)
    {
        try {
            $zk = new ZKTeco($device->ip_address, $device->port);
            if ($zk->connect()) {
                return $zk;
            }
        } catch (\Exception $e) {
            Log::error("ZKTeco Connection Error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Test connection to the device.
     * 
     * @param Device $device
     * @return bool
     */
    public function testConnection(Device $device): bool
    {
        $zk = $this->connect($device);
        if ($zk) {
            $zk->disconnect();
            return true;
        }
        return false;
    }

    /**
     * Get all users from the device.
     * 
     * @param Device $device
     * @return array
     */
    public function getEmployees(Device $device)
    {
        $zk = $this->connect($device);
        if (!$zk)
            return [];

        try {
            $users = $zk->getUser();
            return $users;
        } catch (\Exception $e) {
            Log::error("ZKTeco Get Users Error: " . $e->getMessage());
            return [];
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * Get attendance logs from the device.
     * 
     * @param Device $device
     * @return array
     */
    public function getAttendance(Device $device)
    {
        $zk = $this->connect($device);
        if (!$zk)
            return [];

        try {
            $attendance = $zk->getAttendance();
            return $attendance;
        } catch (\Exception $e) {
            Log::error("ZKTeco Get Attendance Error: " . $e->getMessage());
            return [];
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * Get fingerprint templates for a user.
     * 
     * @param Device $device
     * @return array
     */
    public function getFingerprint(Device $device)
    {
        $zk = $this->connect($device);
        if (!$zk)
            return [];

        try {
            // rats/zkteco usually provides getFingerprint() returning all templates
            return $zk->getFingerprint();
        } catch (\Exception $e) {
            Log::error("ZKTeco Get Fingerprint Error: " . $e->getMessage());
            return [];
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * Set fingerprint template for a user.
     * 
     * @param Device $device
     * @param int $uid
     * @param array $templateData
     * @return bool
     */
    public function setFingerprint(Device $device, int $uid, array $templateData)
    {
        $zk = $this->connect($device);
        if (!$zk)
            return false;

        try {
            // $templateData should contain: 'uid', 'id' (finger index), 'template' (data)
            return $zk->setFingerprint($uid, $templateData['id'], $templateData['template']);
        } catch (\Exception $e) {
            Log::error("ZKTeco Set Fingerprint Error: " . $e->getMessage());
            return false;
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * Clear attendance logs from the device.
     * 
     * @param Device $device
     * @return bool
     */
    public function clearAttendance(Device $device)
    {
        $zk = $this->connect($device);
        if (!$zk)
            return false;

        try {
            return $zk->clearAttendance();
        } catch (\Exception $e) {
            Log::error("ZKTeco Clear Attendance Error: " . $e->getMessage());
            return false;
        } finally {
            $zk->disconnect();
        }
    }

    /**
     * Ping the device IP address.
     * 
     * @param Device $device
     * @return bool
     */
    public function ping(Device $device): bool
    {
        $ip = escapeshellarg($device->ip_address);
        // Linux ping: -c count, -W timeout (seconds)
        $cmd = "ping -c 3 -W 1 {$ip}";
        exec($cmd, $output, $status);
        return $status === 0;
    }
}
