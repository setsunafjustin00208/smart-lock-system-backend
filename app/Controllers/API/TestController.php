<?php
namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class TestController extends BaseController
{
    use ResponseTrait;

    public function sendTestEmail()
    {
        $emailService = new \App\Libraries\EmailService();
        $userModel = new \App\Models\UserModel();
        $user = $userModel->find($this->request->user['user_id']);
        
        // Test lock alert
        $result = $emailService->sendLockAlert(
            $user['email'],
            'Test Lock',
            'unlocked',
            'Admin User'
        );
        
        if ($result['success']) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Test email sent successfully',
                'messageId' => $result['messageId']
            ]);
        } else {
            return $this->fail('Email send failed: ' . $result['error']);
        }
    }

    public function sendStatusAlert()
    {
        $emailService = new \App\Libraries\EmailService();
        
        $result = $emailService->sendStatusAlert(
            'setsunafjustin002@gmail.com',
            'Main Entrance',
            'offline'
        );
        
        return $this->respond($result);
    }

    public function sendSecurityAlert()
    {
        $emailService = new \App\Libraries\EmailService();
        
        $result = $emailService->sendSecurityAlert(
            'setsunafjustin002@gmail.com',
            'Multiple failed unlock attempts detected',
            'Server Room'
        );
        
        return $this->respond($result);
    }

    public function sendAccountCreationEmail()
    {
        $emailService = new \App\Libraries\EmailService();
        
        $result = $emailService->sendAccountCreated(
            'setsunafjustin002@gmail.com',
            'test_user',
            'temp123',
            'Admin User'
        );
        
        return $this->respond($result);
    }

    public function testNotificationEmail()
    {
        // Test creating a notification which should trigger email
        $notificationModel = new \App\Models\NotificationModel();
        
        $result = $notificationModel->createNotification(
            1, // Admin user ID
            'lock_status',
            'Lock Alert',
            'Test Lock was unlocked by Admin User',
            1,
            'Test Lock'
        );
        
        return $this->respond([
            'status' => 'success',
            'message' => 'Notification created and email sent',
            'notification_id' => $result
        ]);
    }
}
