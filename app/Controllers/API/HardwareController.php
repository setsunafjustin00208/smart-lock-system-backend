<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use App\Models\LockModel;
use CodeIgniter\HTTP\ResponseInterface;

class HardwareController extends BaseController
{
    protected $lockModel;

    public function __construct()
    {
        $this->lockModel = new LockModel();
    }

    public function heartbeat()
    {
        $data = $this->request->getJSON(true);
        
        if (!isset($data['hardware_id'])) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'hardware_id is required'
            ])->setStatusCode(400);
        }

        $hardwareId = $data['hardware_id'];
        
        // Check if device exists, if not auto-register
        $lock = $this->lockModel->where('hardware_id', $hardwareId)->first();
        
        if (!$lock) {
            // Auto-register new device
            $lockData = [
                'uuid' => service('uuid')->uuid4()->toString(),
                'name' => 'Auto-detected ' . $hardwareId,
                'hardware_id' => $hardwareId,
                'config_data' => json_encode(['auto_registered' => true]),
                'status_data' => json_encode(['status' => 'online']),
                'is_online' => true
            ];
            
            $this->lockModel->insert($lockData);
            $lock = $this->lockModel->where('hardware_id', $hardwareId)->first();
        } else {
            // Update existing device status
            $this->lockModel->update($lock['id'], [
                'is_online' => true,
                'status_data' => json_encode(['status' => 'online', 'last_heartbeat' => date('Y-m-d H:i:s')])
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Heartbeat received',
            'device_registered' => !isset($lock['id'])
        ]);
    }

    public function status()
    {
        $data = $this->request->getJSON(true);
        
        if (!isset($data['hardware_id']) || !isset($data['status'])) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'hardware_id and status are required'
            ])->setStatusCode(400);
        }

        $lock = $this->lockModel->where('hardware_id', $data['hardware_id'])->first();
        
        if (!$lock) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Device not found'
            ])->setStatusCode(404);
        }

        $this->lockModel->update($lock['id'], [
            'status_data' => json_encode(['status' => $data['status'], 'updated_at' => date('Y-m-d H:i:s')])
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Status updated'
        ]);
    }
}
