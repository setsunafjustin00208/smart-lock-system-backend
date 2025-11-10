<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run()
    {
        $notificationModel = new \App\Models\NotificationModel();

        $notifications = [
            [
                'user_id' => 1, // admin
                'type' => 'lock_status',
                'title' => 'Lock Status Changed',
                'message' => 'Main Entrance was unlocked',
                'lock_id' => 1,
                'lock_name' => 'Main Entrance',
                'is_read' => false
            ],
            [
                'user_id' => 1, // admin
                'type' => 'battery_alert',
                'title' => 'Low Battery Alert',
                'message' => 'Conference Room A battery is at 15%',
                'lock_id' => 2,
                'lock_name' => 'Conference Room A',
                'is_read' => false
            ],
            [
                'user_id' => 2, // manager
                'type' => 'lock_status',
                'title' => 'Lock Status Changed',
                'message' => 'Server Room was locked',
                'lock_id' => 3,
                'lock_name' => 'Server Room',
                'is_read' => true
            ],
            [
                'user_id' => 1, // admin
                'type' => 'system_alert',
                'title' => 'System Alert',
                'message' => 'New user account created: testuser4',
                'lock_id' => null,
                'lock_name' => null,
                'is_read' => false
            ],
            [
                'user_id' => 3, // user
                'type' => 'lock_status',
                'title' => 'Lock Status Changed',
                'message' => 'Warehouse Gate was unlocked',
                'lock_id' => 4,
                'lock_name' => 'Warehouse Gate',
                'is_read' => false
            ]
        ];

        foreach ($notifications as $notification) {
            $notificationModel->insert($notification);
        }
    }
}
