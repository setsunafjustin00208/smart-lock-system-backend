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
        $notificationId = $this->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'lock_id' => $lockId,
            'lock_name' => $lockName,
            'is_read' => false
        ]);

        // Send email notification
        if ($notificationId && $userId) {
            $this->sendEmailNotification($userId, $type, $title, $message, $lockName);
        }

        return $notificationId;
    }

    private function sendEmailNotification($userId, $type, $title, $message, $lockName = null)
    {
        try {
            // Get user email
            $userModel = new \App\Models\UserModel();
            $user = $userModel->find($userId);
            
            if (!$user || !$user['email']) {
                return false;
            }

            $emailService = new \App\Libraries\EmailService();
            
            // Send appropriate email based on notification type
            switch ($type) {
                case 'lock_status':
                    // Determine action from message
                    $action = (strpos(strtolower($message), 'unlock') !== false) ? 'unlocked' : 'locked';
                    $emailService->sendLockAlert($user['email'], $lockName ?: 'Lock', $action, $user['username']);
                    break;
                    
                case 'status_alert':
                    // Determine status from message
                    $status = (strpos(strtolower($message), 'offline') !== false) ? 'offline' : 'online';
                    $emailService->sendStatusAlert($user['email'], $lockName ?: 'Device', $status);
                    break;
                    
                case 'system_alert':
                case 'user_action':
                default:
                    $emailService->sendSecurityAlert($user['email'], $message, $lockName);
                    break;
            }
            
            log_message('info', "Email notification sent to {$user['email']} for notification type: {$type}");
            
        } catch (\Exception $e) {
            log_message('error', "Failed to send email notification: " . $e->getMessage());
        }
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
