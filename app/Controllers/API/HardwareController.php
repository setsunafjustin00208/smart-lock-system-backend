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

        // Update device online status
        $lockModel = new \App\Models\LockModel();
        $lockModel->where('hardware_id', $hardwareId)->set([
            'is_online' => true,
            'updated_at' => date('Y-m-d H:i:s')
        ])->update();

        return $this->respond(['status' => 'online']);
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
}
