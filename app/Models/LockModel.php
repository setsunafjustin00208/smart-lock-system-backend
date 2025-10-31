<?php

namespace App\Models;

use CodeIgniter\Model;

class LockModel extends Model
{
    protected $table = 'locks';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['uuid', 'name', 'hardware_id', 'config_data', 'status_data', 'is_online'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[100]',
        'hardware_id' => 'required|is_unique[locks.hardware_id,id,{id}]'
    ];

    public function createLock($lockData)
    {
        $configData = [
            'auto_lock_delay' => $lockData['auto_lock_delay'] ?? 300,
            'notifications_enabled' => true,
            'access_schedule' => []
        ];

        $statusData = [
            'is_locked' => true,
            'battery_level' => 100,
            'last_activity' => null
        ];

        return $this->insert([
            'uuid' => $this->generateUuid(),
            'name' => $lockData['name'],
            'hardware_id' => $lockData['hardware_id'],
            'config_data' => json_encode($configData),
            'status_data' => json_encode($statusData),
            'is_online' => false
        ]);
    }

    public function getLocksForUser($userId)
    {
        return $this->select('locks.*, ulp.permissions')
                   ->join('user_lock_permissions ulp', 'locks.id = ulp.lock_id', 'left')
                   ->where('ulp.user_id', $userId)
                   ->findAll();
    }

    public function updateStatus($hardwareId, $statusData)
    {
        $lock = $this->where('hardware_id', $hardwareId)->first();
        if ($lock) {
            $currentStatus = json_decode($lock['status_data'], true);
            $newStatus = array_merge($currentStatus, $statusData);
            
            return $this->update($lock['id'], [
                'status_data' => json_encode($newStatus),
                'is_online' => true
            ]);
        }
        return false;
    }

    private function generateUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
