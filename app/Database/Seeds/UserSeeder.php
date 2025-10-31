<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $userModel = new \App\Models\UserModel();

        // Admin user
        $userModel->createUser([
            'username' => 'admin',
            'email' => 'admin@smartlock.com',
            'password' => 'admin123',
            'roles' => ['admin']
        ]);

        // Manager user
        $userModel->createUser([
            'username' => 'manager',
            'email' => 'manager@smartlock.com',
            'password' => 'manager123',
            'roles' => ['manager']
        ]);

        // Regular user
        $userModel->createUser([
            'username' => 'user',
            'email' => 'user@smartlock.com',
            'password' => 'user123',
            'roles' => ['user']
        ]);

        // Guest user
        $userModel->createUser([
            'username' => 'guest',
            'email' => 'guest@smartlock.com',
            'password' => 'guest123',
            'roles' => ['guest']
        ]);
    }
}
