<?php
namespace App\Libraries;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class SimpleWebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $hardwareDevices;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->hardwareDevices = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'hardware_register':
                $this->registerHardware($from, $data);
                break;
            case 'hardware_heartbeat':
                $this->updateHeartbeat($from, $data);
                break;
            case 'lock_command':
                $this->sendLockCommand($data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        // Mark hardware as offline if it was a hardware connection
        if (isset($conn->hardwareId)) {
            $this->markHardwareOffline($conn->hardwareId);
            unset($this->hardwareDevices[$conn->hardwareId]);
        }
        
        echo "Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function registerHardware($conn, $data)
    {
        $hardwareId = $data['hardware_id'] ?? '';
        
        if ($hardwareId) {
            $conn->hardwareId = $hardwareId;
            $this->hardwareDevices[$hardwareId] = $conn;
            
            // Update database - mark as online
            $lockModel = new \App\Models\LockModel();
            $lockModel->where('hardware_id', $hardwareId)->set([
                'is_online' => true,
                'updated_at' => date('Y-m-d H:i:s')
            ])->update();
            
            $conn->send(json_encode(['type' => 'registered', 'status' => 'success']));
        }
    }

    private function updateHeartbeat($from, $data)
    {
        $hardwareId = $data['hardware_id'] ?? '';
        
        if ($hardwareId) {
            // Update last seen timestamp
            $lockModel = new \App\Models\LockModel();
            $lockModel->where('hardware_id', $hardwareId)->set([
                'is_online' => true,
                'updated_at' => date('Y-m-d H:i:s')
            ])->update();
        }
    }

    private function sendLockCommand($data)
    {
        $hardwareId = $data['hardware_id'] ?? '';
        $command = $data['command'] ?? '';
        
        if (isset($this->hardwareDevices[$hardwareId])) {
            $this->hardwareDevices[$hardwareId]->send(json_encode([
                'type' => 'command',
                'action' => $command,
                'timestamp' => time()
            ]));
        }
    }

    private function markHardwareOffline($hardwareId)
    {
        $lockModel = new \App\Models\LockModel();
        $lockModel->where('hardware_id', $hardwareId)->set([
            'is_online' => false,
            'updated_at' => date('Y-m-d H:i:s')
        ])->update();
    }
}
