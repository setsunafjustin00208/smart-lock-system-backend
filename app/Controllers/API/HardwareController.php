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
}
