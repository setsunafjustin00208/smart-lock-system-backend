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
        
        if (in_array('admin', $user['roles'])) {
            $locks = $lockModel->findAll();
        } else {
            $locks = $lockModel->getLocksForUser($user['user_id']);
        }

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

    public function control($id)
    {
        $command = $this->request->getPost('command');
        
        if (!in_array($command, ['lock', 'unlock', 'status'])) {
            return $this->failValidationError('Invalid command');
        }

        $user = $this->request->user;
        $lockControlLib = new \App\Libraries\LockControlLib();
        
        $result = $lockControlLib->sendCommand($id, $command, [
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
}
