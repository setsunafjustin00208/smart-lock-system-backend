<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class UsersController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $user = $this->request->user;
        
        if (!in_array('admin', $user['roles'])) {
            return $this->failForbidden('Admin access required');
        }

        $userModel = new \App\Models\UserModel();
        $users = $userModel->findAll();

        return $this->respond([
            'status' => 'success',
            'data' => $users
        ]);
    }

    public function create()
    {
        $user = $this->request->user;
        
        if (!in_array('admin', $user['roles'])) {
            return $this->failForbidden('Admin access required');
        }

        $rules = [
            'username' => 'required|min_length[3]|max_length[50]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Handle JSON input properly
        $input = $this->request->getJSON(true);
        $username = $input['username'] ?? $this->request->getPost('username');
        $email = $input['email'] ?? $this->request->getPost('email');
        $password = $input['password'] ?? $this->request->getPost('password');
        $roles = $input['roles'] ?? $this->request->getPost('roles') ?? ['user'];

        $userModel = new \App\Models\UserModel();
        $userId = $userModel->createUser([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'roles' => $roles
        ]);

        if ($userId) {
            return $this->respondCreated([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => ['id' => $userId]
            ]);
        }

        return $this->failServerError('Failed to create user');
    }

    public function update($id)
    {
        $user = $this->request->user;
        
        if (!in_array('admin', $user['roles'])) {
            return $this->failForbidden('Admin access required');
        }

        $rules = [
            'email' => 'valid_email',
            'roles' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Handle JSON input
        $input = $this->request->getJSON(true);
        $email = $input['email'] ?? $this->request->getPost('email');
        $roles = $input['roles'] ?? $this->request->getPost('roles') ?? [];

        $userModel = new \App\Models\UserModel();
        $targetUser = $userModel->find($id);
        
        if (!$targetUser) {
            return $this->failNotFound('User not found');
        }

        $updateData = [];
        if ($email) {
            $updateData['email'] = $email;
        }
        
        if (!empty($roles)) {
            $authData = json_decode($targetUser['auth_data'], true);
            $authData['roles'] = $roles;
            $updateData['auth_data'] = json_encode($authData);
        }

        if (empty($updateData)) {
            return $this->failValidationErrors(['error' => 'No valid fields to update']);
        }

        if ($userModel->update($id, $updateData)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'User updated successfully'
            ]);
        }

        return $this->failServerError('Failed to update user');
    }

    public function delete($id)
    {
        $user = $this->request->user;
        
        if (!in_array('admin', $user['roles'])) {
            return $this->failForbidden('Admin access required');
        }

        // Prevent self-deletion
        if ($user['user_id'] == $id) {
            return $this->failValidationErrors(['error' => 'Cannot delete your own account']);
        }

        $userModel = new \App\Models\UserModel();
        $targetUser = $userModel->find($id);
        
        if (!$targetUser) {
            return $this->failNotFound('User not found');
        }

        if ($userModel->delete($id)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        }

        return $this->failServerError('Failed to delete user');
    }
}
