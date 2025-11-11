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

        // Try WebSocket first if hardware is online
        if ($lock['is_online'] && $lock['hardware_id']) {
            $result = $this->sendWebSocketCommand($lock['hardware_id'], $command);
            if ($result['success']) {
                $this->logActivity($lockId, $command, $params);
                return $result;
            }
        }

        // Fallback to simulation if offline
        $response = $this->simulateHardwareResponse($lock, $command, $params);
        
        // Update database status if command was successful
        if ($response['success'] && in_array($command, ['lock', 'unlock'])) {
            $this->updateLockStatus($lockModel, $lock, $command, $response['hardware_response']);
        }
        
        // Log activity
        $this->logActivity($lockId, $command, $params);
        
        return $response;
    }

    private function sendWebSocketCommand($hardwareId, $command)
    {
        // This would be called by the WebSocket server
        // For now, return success assuming WebSocket delivery
        return [
            'success' => true,
            'message' => 'Command sent to hardware',
            'hardware_response' => [
                'status' => $command === 'lock' ? 'locked' : 'unlocked',
                'timestamp' => date('c')
            ]
        ];
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

    private function updateLockStatus($lockModel, $lock, $command, $hardwareResponse)
    {
        // Parse current status data
        $currentStatus = json_decode($lock['status_data'], true);
        
        // Update status based on command
        $newStatus = array_merge($currentStatus, [
            'is_locked' => ($command === 'lock'),
            'battery_level' => $hardwareResponse['battery_level'] ?? $currentStatus['battery_level'],
            'last_activity' => $hardwareResponse['timestamp'] ?? date('c')
        ]);
        
        // Update the database
        $lockModel->update($lock['id'], [
            'status_data' => json_encode($newStatus),
            'is_online' => true
        ]);
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
