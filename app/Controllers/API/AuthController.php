<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    public function login()
    {
        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Handle JSON input properly
        $input = $this->request->getJSON(true);
        $username = $input['username'] ?? $this->request->getPost('username');
        $password = $input['password'] ?? $this->request->getPost('password');

        $userModel = new \App\Models\UserModel();
        $user = $userModel->authenticateUser($username, $password);

        if (!$user) {
            return $this->failUnauthorized('Invalid credentials');
        }

        $authLib = new \App\Libraries\AuthenticationLib();
        $token = $authLib->generateToken($user);
        $refreshToken = $authLib->generateRefreshToken($user);

        return $this->respond([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'roles' => $user['auth_data']['roles']
                ],
                'token' => $token,
                'refresh_token' => $refreshToken
            ]
        ]);
    }

    public function logout()
    {
        return $this->respond([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    public function refresh()
    {
        $refreshToken = $this->request->getPost('refresh_token');
        
        if (!$refreshToken) {
            return $this->failValidationError('Refresh token required');
        }

        $authLib = new \App\Libraries\AuthenticationLib();
        $newToken = $authLib->refreshToken($refreshToken);
        
        if (!$newToken) {
            return $this->failUnauthorized('Invalid refresh token');
        }

        return $this->respond([
            'status' => 'success',
            'data' => ['token' => $newToken]
        ]);
    }
}
