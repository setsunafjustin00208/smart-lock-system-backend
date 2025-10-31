<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class LockSeeder extends Seeder
{
    public function run()
    {
        $lockModel = new \App\Models\LockModel();

        // Office locks
        $lockModel->createLock([
            'name' => 'Main Entrance',
            'hardware_id' => 'ESP32_MAIN_001'
        ]);

        $lockModel->createLock([
            'name' => 'Conference Room A',
            'hardware_id' => 'ESP32_CONF_A01'
        ]);

        $lockModel->createLock([
            'name' => 'Server Room',
            'hardware_id' => 'ESP32_SERVER_001'
        ]);

        // Warehouse locks
        $lockModel->createLock([
            'name' => 'Warehouse Gate',
            'hardware_id' => 'ESP32_WH_GATE_001'
        ]);

        $lockModel->createLock([
            'name' => 'Storage Room',
            'hardware_id' => 'ESP32_STORAGE_001'
        ]);

        // Test lock
        $lockModel->createLock([
            'name' => 'Test Lock',
            'hardware_id' => 'ESP32_TEST_001'
        ]);
    }
}
