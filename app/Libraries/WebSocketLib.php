<?php

namespace App\Libraries;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketLib implements MessageComponentInterface
{
    protected $clients;
    protected $hardwareConnections;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->hardwareConnections = [];
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
            case 'auth':
                $this->handleAuth($from, $data);
                break;
            case 'hardware_register':
                $this->handleHardwareRegister($from, $data);
                break;
            case 'lock_status_update':
                $this->handleStatusUpdate($from, $data);
                break;
            case 'client_command':
                $this->handleClientCommand($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        if (isset($conn->hardwareId)) {
            unset($this->hardwareConnections[$conn->hardwareId]);
        }
        
        echo "Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleAuth($conn, $data)
    {
        $authLib = new AuthenticationLib();
        $tokenData = $authLib->validateToken($data['token']);
        
        if ($tokenData) {
            $conn->user = $tokenData;
            $conn->send(json_encode(['type' => 'auth_success']));
        } else {
            $conn->close();
        }
    }

    private function handleHardwareRegister($conn, $data)
    {
        $conn->hardwareId = $data['hardware_id'];
        $this->hardwareConnections[$data['hardware_id']] = $conn;
        
        $conn->send(json_encode(['type' => 'registration_success']));
    }

    private function handleStatusUpdate($conn, $data)
    {
        $this->broadcastToClients([
            'type' => 'lock_update',
            'data' => $data
        ]);
    }

    private function handleClientCommand($conn, $data)
    {
        if (isset($this->hardwareConnections[$data['hardware_id']])) {
            $this->hardwareConnections[$data['hardware_id']]->send(json_encode($data));
        }
    }

    public function sendToHardware($hardwareId, $payload)
    {
        if (isset($this->hardwareConnections[$hardwareId])) {
            $this->hardwareConnections[$hardwareId]->send(json_encode($payload));
            return true;
        }
        return false;
    }

    private function broadcastToClients($message)
    {
        foreach ($this->clients as $client) {
            if (isset($client->user)) {
                $client->send(json_encode($message));
            }
        }
    }
}
