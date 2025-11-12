<?php
namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class HardwareController extends BaseController
{
    use ResponseTrait;
    
    private $logger;

    public function __construct()
    {
        $this->logger = new \App\Libraries\HardwareLogger();
    }

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

        // Log heartbeat activity
        $this->logger->logHeartbeat($hardwareId, 'online');

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

        // Get previous state for logging
        $lockModel = new \App\Models\LockModel();
        $currentLock = $lockModel->where('hardware_id', $hardwareId)->first();
        $previousState = null;
        
        if ($currentLock) {
            $currentStatus = json_decode($currentLock['status_data'], true);
            $previousState = $currentStatus['is_locked'] ?? null;
        }

        // Log status update
        $this->logger->logStatusUpdate($hardwareId, $isLocked, $previousState);

        // Update lock status
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
        $commandModel = new \App\Models\CommandQueueModel();
        $pendingCommand = $commandModel->where('hardware_id', $hardwareId)
                                      ->where('status', 'pending')
                                      ->orderBy('created_at', 'ASC')
                                      ->first();

        if ($pendingCommand) {
            // Mark command as sent and log it
            $commandModel->update($pendingCommand['id'], ['status' => 'sent']);
            
            $this->logger->logCommand(
                $hardwareId, 
                $pendingCommand['command'], 
                $pendingCommand['id'], 
                'sent'
            );
            
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
        $hardwareId = $input['hardware_id'] ?? '';
        
        if (!$commandId) {
            return $this->fail('Command ID required');
        }

        // Get command details for logging
        $commandModel = new \App\Models\CommandQueueModel();
        $command = $commandModel->find($commandId);
        
        if ($command) {
            // Log command completion
            $this->logger->logCommand(
                $command['hardware_id'], 
                $command['command'], 
                $commandId, 
                $status
            );
        }

        // Update command status
        $commandModel->update($commandId, [
            'status' => $status,
            'executed_at' => date('Y-m-d H:i:s'),
            'response' => json_encode($input)
        ]);

        return $this->respond(['status' => 'confirmed']);
    }

    public function getLogs()
    {
        $hardwareId = $this->request->getGet('hardware_id');
        $limit = (int)($this->request->getGet('limit') ?? 100);
        
        $logs = $this->logger->getRecentActivity($hardwareId, $limit);
        
        return $this->respond([
            'status' => 'success',
            'data' => $logs,
            'count' => count($logs)
        ]);
    }
}
}
