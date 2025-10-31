<?php

namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthenticationLib
{
    private $secretKey;
    private $algorithm = 'HS256';

    public function __construct()
    {
        $this->secretKey = env('JWT_SECRET_KEY');
    }

    public function generateToken($user, $expiry = 3600)
    {
        $payload = [
            'iss' => base_url(),
            'iat' => time(),
            'exp' => time() + $expiry,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'roles' => $user['auth_data']['roles'] ?? ['user']
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    public function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function generateRefreshToken($user)
    {
        $payload = [
            'iss' => base_url(),
            'iat' => time(),
            'exp' => time() + (7 * 24 * 3600),
            'user_id' => $user['id'],
            'type' => 'refresh'
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    public function refreshToken($refreshToken)
    {
        $decoded = $this->validateToken($refreshToken);
        
        if (!$decoded || ($decoded['type'] ?? '') !== 'refresh') {
            return false;
        }

        $userModel = new \App\Models\UserModel();
        $user = $userModel->find($decoded['user_id']);
        
        return $user ? $this->generateToken($user) : false;
    }
}
