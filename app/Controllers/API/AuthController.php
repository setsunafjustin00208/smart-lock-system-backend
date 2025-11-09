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
            return $this->failValidationErrors(['refresh_token' => 'Refresh token required']);
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

    public function updateProfile()
    {
        $user = $this->request->user;
        
        $rules = [
            'username' => 'min_length[3]|max_length[50]',
            'email' => 'valid_email'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Handle JSON input
        $input = $this->request->getJSON(true);
        $username = $input['username'] ?? $this->request->getPost('username');
        $email = $input['email'] ?? $this->request->getPost('email');

        $userModel = new \App\Models\UserModel();
        $updateData = [];

        if ($username && $username !== $user['username']) {
            // Check if username is already taken
            $existingUser = $userModel->where('username', $username)->where('id !=', $user['user_id'])->first();
            if ($existingUser) {
                return $this->failValidationErrors(['username' => 'Username already taken']);
            }
            $updateData['username'] = $username;
        }

        if ($email) {
            // Check if email is already taken
            $existingUser = $userModel->where('email', $email)->where('id !=', $user['user_id'])->first();
            if ($existingUser) {
                return $this->failValidationErrors(['email' => 'Email already taken']);
            }
            $updateData['email'] = $email;
        }

        if (empty($updateData)) {
            return $this->failValidationErrors(['error' => 'No valid fields to update']);
        }

        if ($userModel->update($user['user_id'], $updateData)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Profile updated successfully'
            ]);
        }

        return $this->failServerError('Failed to update profile');
    }

    public function changePassword()
    {
        $user = $this->request->user;
        
        $rules = [
            'currentPassword' => 'required',
            'newPassword' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Handle JSON input
        $input = $this->request->getJSON(true);
        $currentPassword = $input['currentPassword'] ?? $this->request->getPost('currentPassword');
        $newPassword = $input['newPassword'] ?? $this->request->getPost('newPassword');

        $userModel = new \App\Models\UserModel();
        $currentUser = $userModel->find($user['user_id']);
        
        if (!$currentUser) {
            return $this->failNotFound('User not found');
        }

        $authData = json_decode($currentUser['auth_data'], true);
        
        // Verify current password
        if (!password_verify($currentPassword, $authData['password_hash'])) {
            return $this->failValidationErrors(['current_password' => 'Current password is incorrect']);
        }

        // Update password
        $authData['password_hash'] = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        if ($userModel->update($user['user_id'], ['auth_data' => json_encode($authData)])) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Password changed successfully'
            ]);
        }

        return $this->failServerError('Failed to change password');
    }

    public function getNotificationSettings()
    {
        $user = $this->request->user;
        
        $userModel = new \App\Models\UserModel();
        $currentUser = $userModel->find($user['user_id']);
        
        if (!$currentUser) {
            return $this->failNotFound('User not found');
        }

        $profileData = json_decode($currentUser['profile_data'], true) ?? [];
        $notificationSettings = $profileData['notification_settings'] ?? [
            'email' => true,
            'push' => true,
            'sms' => false,
            'lockAlerts' => true,
            'batteryAlerts' => true
        ];

        return $this->respond([
            'status' => 'success',
            'data' => $notificationSettings
        ]);
    }

    public function updateNotificationSettings()
    {
        $user = $this->request->user;
        
        // Handle JSON input
        $input = $this->request->getJSON(true);
        $settings = $input ?? $this->request->getPost();

        $userModel = new \App\Models\UserModel();
        $currentUser = $userModel->find($user['user_id']);
        
        if (!$currentUser) {
            return $this->failNotFound('User not found');
        }

        $profileData = json_decode($currentUser['profile_data'], true) ?? [];
        $profileData['notification_settings'] = [
            'email' => $settings['email'] ?? false,
            'push' => $settings['push'] ?? false,
            'sms' => $settings['sms'] ?? false,
            'lockAlerts' => $settings['lockAlerts'] ?? false,
            'batteryAlerts' => $settings['batteryAlerts'] ?? false
        ];

        if ($userModel->update($user['user_id'], ['profile_data' => json_encode($profileData)])) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Notification settings updated'
            ]);
        }

        return $this->failServerError('Failed to update notification settings');
    }
}
