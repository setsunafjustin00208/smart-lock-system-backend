<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MonitorDevices extends BaseCommand
{
    protected $group = 'Hardware';
    protected $name = 'hardware:monitor';
    protected $description = 'Monitor hardware device status';

    public function run(array $params)
    {
        CLI::write('Starting device monitor...', 'green');
        
        while (true) {
            $this->checkDeviceStatus();
            sleep(60); // Check every minute
        }
    }

    private function checkDeviceStatus()
    {
        $lockModel = new \App\Models\LockModel();
        $locks = $lockModel->where('hardware_id IS NOT NULL')->findAll();
        
        foreach ($locks as $lock) {
            $lastUpdate = strtotime($lock['updated_at']);
            $timeout = 300; // 5 minutes timeout
            
            if (time() - $lastUpdate > $timeout && $lock['is_online']) {
                // Mark as offline
                $lockModel->update($lock['id'], ['is_online' => false]);
                CLI::write("Lock {$lock['hardware_id']} marked offline", 'yellow');
                
                // Create notification
                $notificationModel = new \App\Models\NotificationModel();
                $notificationModel->createNotification(
                    1, // Admin user
                    'status_alert',
                    'Device Offline',
                    "Lock {$lock['name']} has gone offline",
                    $lock['id'],
                    $lock['name']
                );
            }
        }
    }
}
