<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['user_id', 'type', 'title', 'message', 'lock_id', 'lock_name', 'is_read'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function createNotification($userId, $type, $title, $message, $lockId = null, $lockName = null)
    {
        return $this->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'lock_id' => $lockId,
            'lock_name' => $lockName,
            'is_read' => false
        ]);
    }

    public function getUnreadCount($userId)
    {
        return $this->where('user_id', $userId)->where('is_read', false)->countAllResults();
    }

    public function getRecentNotifications($userId, $limit = 10)
    {
        return $this->where('user_id', $userId)
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->findAll();
    }
}
