<?php
namespace App\Models;

use CodeIgniter\Model;

class CommandQueueModel extends Model
{
    protected $table = 'command_queue';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'hardware_id', 'command', 'payload', 'user_id', 'priority', 
        'retry_count', 'max_retries', 'status', 'executed_at', 'response'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function queueCommand($hardwareId, $command, $userId = null, $payload = [])
    {
        return $this->insert([
            'hardware_id' => $hardwareId,
            'command' => $command,
            'payload' => json_encode($payload),
            'user_id' => $userId,
            'status' => 'pending',
            'priority' => 1,
            'retry_count' => 0,
            'max_retries' => 3
        ]);
    }

    public function getPendingCommands($hardwareId)
    {
        return $this->where('hardware_id', $hardwareId)
                   ->where('status', 'pending')
                   ->orderBy('priority', 'DESC')
                   ->orderBy('created_at', 'ASC')
                   ->findAll();
    }
}
