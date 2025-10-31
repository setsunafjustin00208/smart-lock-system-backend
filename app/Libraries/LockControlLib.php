<?php

namespace App\Libraries;

class LockControlLib
{
    private $securityLib;

    public function __construct()
    {
        $this->securityLib = new HardwareSecurityLib();
    }

    public function sendCommand($lockId, $command, $params = [])
    {
        $lockModel = new \App\Models\LockModel();
        $lock = $lockModel->find($lockId);
        
        if (!$lock) {
            return [
                'success' => false,
                'message' => 'Lock not found'
            ];
        }

        // Simulate hardware command without WebSocket
        $response = $this->simulateHardwareResponse($lock, $command, $params);
        
        // Log activity
        $this->logActivity($lockId, $command, $params);
        
        return $response;
    }

    private function simulateHardwareResponse($lock, $command, $params)
    {
        // Simulate different responses based on command
        switch ($command) {
            case 'lock':
                return [
                    'success' => true,
                    'message' => 'Lock command sent successfully',
                    'hardware_response' => [
                        'status' => 'locked',
                        'timestamp' => date('c'),
                        'battery_level' => 85
                    ]
                ];
                
            case 'unlock':
                return [
                    'success' => true,
                    'message' => 'Unlock command sent successfully',
                    'hardware_response' => [
                        'status' => 'unlocked',
                        'timestamp' => date('c'),
                        'battery_level' => 85
                    ]
                ];
                
            case 'status':
                return [
                    'success' => true,
                    'message' => 'Status retrieved successfully',
                    'hardware_response' => [
                        'status' => 'locked',
                        'timestamp' => date('c'),
                        'battery_level' => 85,
                        'is_online' => true
                    ]
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Invalid command'
                ];
        }
    }

    private function logActivity($lockId, $command, $params)
    {
        // Log the activity to database
        $activityModel = new \App\Models\ActivityLogModel();
        $activityModel->insert([
            'user_id' => $params['user_id'] ?? null,
            'lock_id' => $lockId,
            'action' => $command,
            'details' => json_encode([
                'command' => $command,
                'timestamp' => date('c'),
                'success' => true
            ])
        ]);
    }
}
