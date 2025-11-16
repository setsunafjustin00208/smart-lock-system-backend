<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class LocksController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $user = $this->request->user;
        $lockModel = new \App\Models\LockModel();
        
        // Show all locks to all authenticated users
        $locks = $lockModel->findAll();

        return $this->respond([
            'status' => 'success',
            'data' => $locks
        ]);
    }

    public function show($id)
    {
        $lockModel = new \App\Models\LockModel();
        $lock = $lockModel->find($id);

        if (!$lock) {
            return $this->failNotFound('Lock not found');
        }

        return $this->respond([
            'status' => 'success',
            'data' => $lock
        ]);
    }

    public function update($id)
    {
        $json = $this->request->getJSON(true);
        
        if (!isset($json['name']) || empty(trim($json['name']))) {
            return $this->fail('Lock name is required', 400);
        }
        
        $lockModel = new \App\Models\LockModel();
        $lock = $lockModel->find($id);
        
        if (!$lock) {
            return $this->failNotFound('Lock not found');
        }
        
        $updateData = [
            'name' => trim($json['name']),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($lockModel->update($id, $updateData)) {
            $updatedLock = $lockModel->find($id);
            return $this->respond([
                'status' => 'success',
                'message' => 'Lock name updated successfully',
                'data' => $updatedLock
            ]);
        }
        
        return $this->failServerError('Failed to update lock name');
    }

    public function control($id)
    {
        // Handle JSON input properly
        $input = $this->request->getJSON(true);
        $action = $input['action'] ?? $this->request->getPost('action');
        
        if (!in_array($action, ['lock', 'unlock', 'status'])) {
            return $this->fail('Invalid action', 400);
        }

        $user = $this->request->user;
        $lockControlLib = new \App\Libraries\LockControlLib();
        
        $result = $lockControlLib->sendCommand($id, $action, [
            'user_id' => $user['user_id']
        ]);

        if ($result['success']) {
            return $this->respond([
                'status' => 'success',
                'data' => $result
            ]);
        }

        return $this->failServerError($result['message']);
    }

    public function batteryStatus()
    {
        $user = $this->request->user;
        
        $lockModel = new \App\Models\LockModel();
        $locks = $lockModel->findAll();

        $statusData = [];
        foreach ($locks as $lock) {
            $lockStatus = json_decode($lock['status_data'], true) ?? [];
            $isOnline = $lock['is_online'] === 't' || $lock['is_online'] === true;
            
            $statusData[] = [
                'lock_id' => $lock['id'],
                'lock_name' => $lock['name'],
                'status' => $isOnline ? 'online' : 'offline',
                'last_updated' => $lock['updated_at']
            ];
        }

        return $this->respond([
            'status' => 'success',
            'data' => $statusData
        ]);
    }
}
