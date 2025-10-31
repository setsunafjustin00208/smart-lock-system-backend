<?php

namespace App\Libraries;

class LockControlLib
{
    private $webSocketLib;
    private $securityLib;

    public function __construct()
    {
        $this->webSocketLib = new WebSocketLib();
        $this->securityLib = new HardwareSecurityLib();
    }

    public function sendCommand($lockId, $command, $params = [])
    {
        $lockModel = new \App\Models\LockModel();
        $lock = $lockModel->find($lockId);
        
        if (!$lock) {
            return ['success' => false, 'message' => 'Lock not found'];
        }

        $payload = [
            'command' => $command,
            'lock_id' => $lockId,
            'hardware_id' => $lock['hardware_id'],
            'timestamp' => time(),
            'params' => $params
        ];

        $payload['signature'] = $this->securityLib->signPayload($payload);

        $result = $this->webSocketLib->sendToHardware($lock['hardware_id'], $payload);

        if ($result) {
            $this->logActivity($params['user_id'] ?? null, $lockId, $command);
            return ['success' => true, 'message' => 'Command sent'];
        }

        return ['success' => false, 'message' => 'Hardware offline'];
    }

    private function logActivity($userId, $lockId, $action)
    {
        $activityModel = new \App\Models\ActivityLogModel();
        $activityModel->insert([
            'user_id' => $userId,
            'lock_id' => $lockId,
            'action' => $action,
            'details' => json_encode(['timestamp' => time()])
        ]);
    }
}
