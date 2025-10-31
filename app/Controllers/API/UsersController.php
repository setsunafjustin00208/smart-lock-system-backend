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

        $userModel = new \App\Models\UserModel();
        $userId = $userModel->createUser([
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'roles' => $this->request->getPost('roles') ?? ['user']
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
}
