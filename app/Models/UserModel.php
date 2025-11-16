<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['uuid', 'username', 'email', 'auth_data', 'profile_data'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]'
    ];

    public function createUser($userData)
    {
        $authData = [
            'password_hash' => password_hash($userData['password'], PASSWORD_ARGON2ID),
            'roles' => $userData['roles'] ?? ['user'],
            'failed_attempts' => 0,
            'locked_until' => null
        ];

        return $this->insert([
            'uuid' => $this->generateUuid(),
            'username' => $userData['username'],
            'email' => $userData['email'],
            'auth_data' => json_encode($authData),
            'profile_data' => json_encode($userData['profile'] ?? [])
        ]);
    }

    public function authenticateUser($username, $password)
    {
        $user = $this->where('username', $username)->first();
        
        if (!$user) {
            log_message('debug', 'User not found: ' . $username);
            return false;
        }

        $authData = json_decode($user['auth_data'], true);
        
        if (!$authData) {
            log_message('debug', 'Failed to decode auth_data for user: ' . $username);
            return false;
        }
        
        if ($authData['locked_until'] && strtotime($authData['locked_until']) > time()) {
            log_message('debug', 'Account locked for user: ' . $username);
            return 'ACCOUNT_LOCKED';
        }

        if (password_verify($password, $authData['password_hash'])) {
            $authData['failed_attempts'] = 0;
            $authData['locked_until'] = null;
            
            $this->update($user['id'], [
                'auth_data' => json_encode($authData)
            ]);
            
            $user['auth_data'] = $authData;
            return $user;
        }

        log_message('debug', 'Password verification failed for user: ' . $username);
        
        $authData['failed_attempts']++;
        if ($authData['failed_attempts'] >= 5) {
            $authData['locked_until'] = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        }
        
        $this->update($user['id'], [
            'auth_data' => json_encode($authData)
        ]);
        
        return false;
    }

    private function generateUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
