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
        $commandModel = new \App\Models\CommandQueueModel();
        $pendingCommand = $commandModel->where('hardware_id', $hardwareId)
                                      ->where('status', 'pending')
                                      ->orderBy('created_at', 'ASC')
                                      ->first();

        if ($pendingCommand) {
            // Mark command as sent
            $commandModel->update($pendingCommand['id'], ['status' => 'sent']);
            
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
        $commandModel = new \App\Models\CommandQueueModel();
        $commandModel->update($commandId, [
            'status' => $status,
            'executed_at' => date('Y-m-d H:i:s'),
            'response' => json_encode($input)
        ]);

        return $this->respond(['status' => 'confirmed']);
    }
}
