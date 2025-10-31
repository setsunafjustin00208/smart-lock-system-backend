<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['user_id', 'lock_id', 'action', 'details'];
    protected $useTimestamps = false;

    public function logActivity($userId, $lockId, $action, $details = [])
    {
        return $this->insert([
            'user_id' => $userId,
            'lock_id' => $lockId,
            'action' => $action,
            'details' => json_encode($details)
        ]);
    }

    public function getRecentActivity($limit = 50)
    {
        return $this->select('activity_logs.*, users.username, locks.name as lock_name')
                   ->join('users', 'activity_logs.user_id = users.id', 'left')
                   ->join('locks', 'activity_logs.lock_id = locks.id', 'left')
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->findAll();
    }
}
