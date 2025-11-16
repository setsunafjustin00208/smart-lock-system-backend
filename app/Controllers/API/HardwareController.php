<?php
namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class HardwareController extends BaseController
{
    use ResponseTrait;

    public function heartbeat()
    {
        // Accept both POST form data and JSON
        $hardwareId = $this->request->getPost('hardware_id');
        if (!$hardwareId) {
            $input = $this->request->getJSON(true);
            $hardwareId = $input['hardware_id'] ?? '';
        }
        
        if (!$hardwareId) {
            return $this->fail('Hardware ID required');
        }

        // Check if lock exists, if not auto-register it
        $lockModel = new \App\Models\LockModel();
        $existingLock = $lockModel->where('hardware_id', $hardwareId)->first();
        
        if (!$existingLock) {
            // Auto-register new ESP32 device
            $lockModel->insert([
                'name' => $this->generateFriendlyName($hardwareId),
                'hardware_id' => $hardwareId,
                'config_data' => json_encode([
                    'auto_lock_delay' => 300,
                    'notifications_enabled' => true,
                    'access_schedule' => ['enabled' => false]
                ]),
                'status_data' => json_encode([
                    'is_locked' => true,
                    'last_activity' => date('c')
                ]),
                'is_online' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            log_message('info', "Auto-registered new ESP32 device: {$hardwareId} as '{$this->generateFriendlyName($hardwareId)}'");
            
            return $this->respond([
                'status' => 'registered',
                'message' => 'Device auto-registered successfully'
            ]);
        }

        // Update existing device online status
        $lockModel->where('hardware_id', $hardwareId)->set([
            'is_online' => true,
            'updated_at' => date('Y-m-d H:i:s')
        ])->update();

        return $this->respond($this->checkSyncCommand($hardwareId));
    }

    public function statusUpdate()
    {
        $input = $this->request->getJSON(true);
        $hardwareId = $input['hardware_id'] ?? '';
        $isLocked = $input['is_locked'] ?? true;

        if (!$hardwareId) {
            return $this->fail('Hardware ID required');
        }

        // Update lock status
        $lockModel = new \App\Models\LockModel();
        $statusData = json_encode(['is_locked' => $isLocked]);
        
        $lockModel->where('hardware_id', $hardwareId)->set([
            'status_data' => $statusData,
            'is_online' => true,
            'updated_at' => date('Y-m-d H:i:s')
        ])->update();

        return $this->respond(['status' => 'updated']);
    }

    public function getCommand()
    {
        // Accept both POST form data and JSON
        $hardwareId = $this->request->getPost('hardware_id');
        if (!$hardwareId) {
            $input = $this->request->getJSON(true);
            $hardwareId = $input['hardware_id'] ?? '';
        }
        
        if (!$hardwareId) {
            return $this->fail('Hardware ID required');
        }

        // Check for pending commands in database
        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM command_queue WHERE hardware_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1", [$hardwareId]);
        $pendingCommand = $query->getRowArray();

        if ($pendingCommand) {
            // Mark command as sent
            $db->query("UPDATE command_queue SET status = 'sent', updated_at = NOW() WHERE id = ?", [$pendingCommand['id']]);
            
            return $this->respond([
                'command' => $pendingCommand['command'],
                'command_id' => $pendingCommand['id'],
                'timestamp' => time()
            ]);
        }

        return $this->respond(['command' => 'none']);
    }

    public function confirmCommand()
    {
        $input = $this->request->getJSON(true);
        $commandId = $input['command_id'] ?? '';
        $status = $input['status'] ?? 'completed';
        
        if (!$commandId) {
            return $this->fail('Command ID required');
        }

        // Update command status
        $db = \Config\Database::connect();
        $db->query("UPDATE command_queue SET status = ?, executed_at = NOW(), response = ? WHERE id = ?", [
            $status,
            json_encode($input),
            $commandId
        ]);

        return $this->respond(['status' => 'confirmed']);
    }

    public function getLogs()
    {
        $hardwareId = $this->request->getGet('hardware_id');
        $limit = (int)($this->request->getGet('limit') ?? 100);
        
        if (!$hardwareId) {
            return $this->fail('Hardware ID required');
        }
        
        // Get recent activity logs for the hardware
        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM activity_logs WHERE lock_id IN (SELECT id FROM locks WHERE hardware_id = ?) ORDER BY created_at DESC LIMIT ?", [$hardwareId, $limit]);
        $logs = $query->getResultArray();
        
        return $this->respond([
            'status' => 'success',
            'data' => $logs,
            'count' => count($logs)
        ]);
    }

    public function log()
    {
        $input = $this->request->getJSON(true);
        $hardwareId = $input['hardware_id'] ?? '';
        $type = $input['type'] ?? 'INFO';
        $message = $input['message'] ?? '';
        $level = $input['level'] ?? 'info';
        $timestamp = $input['timestamp'] ?? time();
        $stateChanged = $input['state_changed'] ?? false;
        $currentState = $input['current_state'] ?? '';
        
        if (!$hardwareId || !$message) {
            return $this->fail('Hardware ID and message required');
        }

        // Write to hardware activity log file
        $logFile = WRITEPATH . 'logs/hardware_activity.log';
        $logDir = dirname($logFile);
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Format log entry
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'hardware_id' => $hardwareId,
            'type' => $type,
            'level' => strtoupper($level),
            'message' => $message,
            'state_changed' => $stateChanged,
            'current_state' => $currentState,
            'device_timestamp' => $timestamp
        ];
        
        $logLine = json_encode($logEntry) . "\n";
        
        // Append to log file
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        return $this->respond(['status' => 'logged']);
    }

    public function forceSync()
    {
        $input = $this->request->getJSON(true);
        $hardwareId = $input['hardware_id'] ?? '';
        
        if (!$hardwareId) {
            return $this->fail('Hardware ID required');
        }

        // Get current lock state from database
        $lockModel = new \App\Models\LockModel();
        $lock = $lockModel->where('hardware_id', $hardwareId)->first();
        
        if (!$lock) {
            return $this->fail('Lock not found');
        }

        // Parse current database state
        $statusData = json_decode($lock['status_data'], true);
        $isLocked = $statusData['is_locked'] ?? true;
        
        // Queue sync command for hardware
        $commandModel = new \App\Models\CommandQueueModel();
        $commandModel->queueCommand(
            $hardwareId,
            'sync',
            null,
            [
                'target_state' => $isLocked ? 'LOCKED' : 'UNLOCKED',
                'force_sync' => true,
                'timestamp' => time()
            ]
        );

        return $this->respond([
            'status' => 'sync_queued',
            'target_state' => $isLocked ? 'LOCKED' : 'UNLOCKED',
            'message' => 'Forced sync command queued for hardware'
        ]);
    }

    private function generateFriendlyName($hardwareId)
    {
        // Extract meaningful parts from hardware ID
        $patterns = [
            'ESP32_MAIN_' => 'Main Entrance Lock',
            'ESP32_CONF_' => 'Conference Room Lock', 
            'ESP32_SERVER_' => 'Server Room Lock',
            'ESP32_WH_GATE_' => 'Warehouse Gate Lock',
            'ESP32_STORAGE_' => 'Storage Room Lock',
            'ESP32_TEST_' => 'Test Lock',
            'ESP32_OFFICE_' => 'Office Lock',
            'ESP32_FRONT_' => 'Front Door Lock',
            'ESP32_BACK_' => 'Back Door Lock',
            'ESP32_SIDE_' => 'Side Door Lock',
            'ESP32_GARAGE_' => 'Garage Lock',
            'ESP32_GATE_' => 'Gate Lock',
            'ESP32_LAB_' => 'Laboratory Lock',
            'ESP32_ROOM_' => 'Room Lock',
            'NODEMCU_' => 'NodeMCU Lock',
        ];
        
        // Check for known patterns
        foreach ($patterns as $pattern => $name) {
            if (strpos($hardwareId, $pattern) === 0) {
                // Extract number/suffix if present
                $suffix = str_replace($pattern, '', $hardwareId);
                if (!empty($suffix)) {
                    return $name . ' ' . $suffix;
                }
                return $name;
            }
        }
        
        // Fallback: Clean up generic hardware IDs
        $cleanName = str_replace(['ESP32_', 'NODEMCU_', '_'], [' ', ' ', ' '], $hardwareId);
        $cleanName = ucwords(strtolower($cleanName));
        $cleanName = preg_replace('/\s+/', ' ', trim($cleanName));
        
        return $cleanName . ' Lock';
    }

    private function checkSyncCommand($hardwareId)
    {
        // Check if there's a pending sync command
        $db = \Config\Database::connect();
        $syncCommand = $db->query("SELECT id FROM command_queue WHERE hardware_id = ? AND command = 'sync' AND status = 'pending' LIMIT 1", [$hardwareId])->getRowArray();
        
        $response = ['status' => 'online'];
        
        if ($syncCommand) {
            $response['force_sync'] = true;
            $response['sync_command_id'] = $syncCommand['id'];
            // Mark sync command as sent
            $db->query("UPDATE command_queue SET status = 'sent' WHERE id = ?", [$syncCommand['id']]);
        }
        
        return $response;
    }
}
