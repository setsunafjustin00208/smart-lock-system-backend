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

        if ($user === 'ACCOUNT_LOCKED') {
            return $this->respond([
                'status' => 'error',
                'message' => 'Account is locked due to too many failed login attempts. Please try again in 30 minutes.',
                'error_code' => 'ACCOUNT_LOCKED'
            ], 423);
        }

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
        $authLib = new \App\Libraries\AuthenticationLib();
        $refreshToken = $this->request->getHeaderLine('Authorization');
        
        if (!$refreshToken) {
            return $this->failUnauthorized('Refresh token required');
        }

        $refreshToken = str_replace('Bearer ', '', $refreshToken);
        $user = $authLib->validateRefreshToken($refreshToken);

        if (!$user) {
            return $this->failUnauthorized('Invalid refresh token');
        }

        $newToken = $authLib->generateToken($user);
        $newRefreshToken = $authLib->generateRefreshToken($user);

        return $this->respond([
            'status' => 'success',
            'data' => [
                'token' => $newToken,
                'refresh_token' => $newRefreshToken
            ]
        ]);
    }

    public function profile()
    {
        $user = $this->request->user;
        
        return $this->respond([
            'status' => 'success',
            'data' => [
                'id' => $user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'] ?? '',
                'roles' => $user['roles']
            ]
        ]);
    }

    public function updateProfile()
    {
        $user = $this->request->user;
        $input = $this->request->getJSON(true);

        $userModel = new \App\Models\UserModel();
        $userData = $userModel->find($user['user_id']);

        if (!$userData) {
            return $this->failNotFound('User not found');
        }

        $updateData = [];
        if (isset($input['email'])) {
            $updateData['email'] = $input['email'];
        }

        if (!empty($updateData)) {
            $userModel->update($user['user_id'], $updateData);
        }

        return $this->respond([
            'status' => 'success',
            'message' => 'Profile updated successfully'
        ]);
    }

    public function changePassword()
    {
        $user = $this->request->user;
        $input = $this->request->getJSON(true);

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $userModel = new \App\Models\UserModel();
        $userData = $userModel->find($user['user_id']);

        if (!$userData) {
            return $this->failNotFound('User not found');
        }

        $authData = json_decode($userData['auth_data'], true);

        if (!password_verify($input['current_password'], $authData['password_hash'])) {
            return $this->fail('Current password is incorrect', 400);
        }

        $authData['password_hash'] = password_hash($input['new_password'], PASSWORD_ARGON2ID);

        $userModel->update($user['user_id'], [
            'auth_data' => json_encode($authData)
        ]);

        return $this->respond([
            'status' => 'success',
            'message' => 'Password changed successfully'
        ]);
    }

    public function getNotificationSettings()
    {
        $user = $this->request->user;
        $userModel = new \App\Models\UserModel();
        $userData = $userModel->find($user['user_id']);

        if (!$userData) {
            return $this->failNotFound('User not found');
        }

        $profileData = json_decode($userData['profile_data'], true) ?? [];
        $notifications = $profileData['notifications'] ?? [
            'email_enabled' => true,
            'push_enabled' => true,
            'lock_status' => true,
            'status_alerts' => true,
            'system_alerts' => true
        ];

        return $this->respond([
            'status' => 'success',
            'data' => $notifications
        ]);
    }

    public function updateNotificationSettings()
    {
        $user = $this->request->user;
        $input = $this->request->getJSON(true);

        $userModel = new \App\Models\UserModel();
        $userData = $userModel->find($user['user_id']);

        if (!$userData) {
            return $this->failNotFound('User not found');
        }

        $profileData = json_decode($userData['profile_data'], true) ?? [];
        $profileData['notifications'] = $input;

        $userModel->update($user['user_id'], [
            'profile_data' => json_encode($profileData)
        ]);

        return $this->respond([
            'status' => 'success',
            'message' => 'Notification settings updated successfully'
        ]);
    }
}
