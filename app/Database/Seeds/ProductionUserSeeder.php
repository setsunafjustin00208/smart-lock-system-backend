<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductionUserSeeder extends Seeder
{
    public function run()
    {
        // Clean up existing development/test data
        $this->cleanupDevelopmentData();

        // Production users with secure defaults
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@lockey.system',
                'auth_data' => json_encode([
                    'password_hash' => password_hash('LockeyAdmin2024!', PASSWORD_ARGON2ID),
                    'roles' => ['admin'],
                    'permissions' => ['*'],
                    'last_login' => null,
                    'failed_attempts' => 0,
                    'locked_until' => null,
                    'must_change_password' => true,
                    'created_by' => 'system'
                ]),
                'profile_data' => json_encode([
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'phone' => '',
                    'department' => 'IT',
                    'preferences' => [
                        'notifications' => true,
                        'email_alerts' => true,
                        'theme' => 'light'
                    ]
                ])
            ],
            [
                'username' => 'security',
                'email' => 'security@lockey.system',
                'auth_data' => json_encode([
                    'password_hash' => password_hash('LockeySecure2024!', PASSWORD_ARGON2ID),
                    'roles' => ['manager'],
                    'permissions' => ['locks.view', 'locks.control', 'users.view', 'activity.view'],
                    'last_login' => null,
                    'failed_attempts' => 0,
                    'locked_until' => null,
                    'must_change_password' => true,
                    'created_by' => 'system'
                ]),
                'profile_data' => json_encode([
                    'first_name' => 'Security',
                    'last_name' => 'Manager',
                    'phone' => '',
                    'department' => 'Security',
                    'preferences' => [
                        'notifications' => true,
                        'email_alerts' => true,
                        'theme' => 'dark'
                    ]
                ])
            ],
            [
                'username' => 'operator',
                'email' => 'operator@lockey.system',
                'auth_data' => json_encode([
                    'password_hash' => password_hash('LockeyOp2024!', PASSWORD_ARGON2ID),
                    'roles' => ['user'],
                    'permissions' => ['locks.view', 'locks.control'],
                    'last_login' => null,
                    'failed_attempts' => 0,
                    'locked_until' => null,
                    'must_change_password' => true,
                    'created_by' => 'system'
                ]),
                'profile_data' => json_encode([
                    'first_name' => 'System',
                    'last_name' => 'Operator',
                    'phone' => '',
                    'department' => 'Operations',
                    'preferences' => [
                        'notifications' => true,
                        'email_alerts' => false,
                        'theme' => 'light'
                    ]
                ])
            ]
        ];

        // Insert users
        foreach ($users as $user) {
            // Check if user already exists
            $existing = $this->db->table('users')
                ->where('username', $user['username'])
                ->orWhere('email', $user['email'])
                ->get()
                ->getRowArray();

            if (!$existing) {
                $this->db->table('users')->insert($user);
                echo "Created user: {$user['username']}\n";
            } else {
                echo "User already exists: {$user['username']}\n";
            }
        }

        // Create default lock permissions for users
        $this->createDefaultLockPermissions();

        echo "\n=== Production Users Created ===\n";
        echo "Admin:    admin / LockeyAdmin2024!\n";
        echo "Security: security / LockeySecure2024!\n";
        echo "Operator: operator / LockeyOp2024!\n";
        echo "\n⚠️  IMPORTANT: Change these passwords after first login!\n";
        echo "All users are set to must_change_password = true\n";
    }

    private function createDefaultLockPermissions()
    {
        // Get all users and locks
        $users = $this->db->table('users')->get()->getResultArray();
        $locks = $this->db->table('locks')->get()->getResultArray();

        foreach ($users as $user) {
            $authData = json_decode($user['auth_data'], true);
            $roles = $authData['roles'] ?? [];

            foreach ($locks as $lock) {
                // Check if permission already exists
                $existing = $this->db->table('user_lock_permissions')
                    ->where('user_id', $user['id'])
                    ->where('lock_id', $lock['id'])
                    ->get()
                    ->getRowArray();

                if (!$existing) {
                    // Set permissions based on role
                    $permissions = [];
                    
                    if (in_array('admin', $roles)) {
                        $permissions = ['view', 'lock', 'unlock', 'configure', 'delete'];
                    } elseif (in_array('manager', $roles)) {
                        $permissions = ['view', 'lock', 'unlock', 'configure'];
                    } elseif (in_array('user', $roles)) {
                        $permissions = ['view', 'lock', 'unlock'];
                    } else {
                        $permissions = ['view'];
                    }

                    $this->db->table('user_lock_permissions')->insert([
                        'user_id' => $user['id'],
                        'lock_id' => $lock['id'],
                        'permissions' => json_encode($permissions)
                    ]);
                }
            }
        }

        echo "Default lock permissions created\n";
    }

    private function cleanupDevelopmentData()
    {
        echo "🧹 Cleaning up development/test data...\n";

        // Delete test locks and hardware
        $testLocks = $this->db->table('locks')
            ->where('name LIKE', '%Test%')
            ->orWhere('name LIKE', '%test%')
            ->orWhere('name LIKE', '%ESP32%')
            ->orWhere('name LIKE', '%Auto-registered%')
            ->orWhere('hardware_id LIKE', '%TEST%')
            ->get()
            ->getResultArray();

        foreach ($testLocks as $lock) {
            // Delete related permissions
            $this->db->table('user_lock_permissions')
                ->where('lock_id', $lock['id'])
                ->delete();
            
            // Delete related activity logs
            $this->db->table('activity_logs')
                ->where('lock_id', $lock['id'])
                ->delete();
            
            // Delete related notifications
            $this->db->table('notifications')
                ->where('lock_id', $lock['id'])
                ->delete();
            
            echo "Deleted test lock: {$lock['name']}\n";
        }

        // Delete the test locks
        $this->db->table('locks')
            ->where('name LIKE', '%Test%')
            ->orWhere('name LIKE', '%test%')
            ->orWhere('name LIKE', '%ESP32%')
            ->orWhere('name LIKE', '%Auto-registered%')
            ->orWhere('hardware_id LIKE', '%TEST%')
            ->delete();

        // Delete test users (keep only production users)
        $testUsers = $this->db->table('users')
            ->where('username !=', 'admin')
            ->where('username !=', 'security')
            ->where('username !=', 'operator')
            ->get()
            ->getResultArray();

        foreach ($testUsers as $user) {
            // Delete related permissions
            $this->db->table('user_lock_permissions')
                ->where('user_id', $user['id'])
                ->delete();
            
            // Delete related activity logs
            $this->db->table('activity_logs')
                ->where('user_id', $user['id'])
                ->delete();
            
            // Delete related notifications
            $this->db->table('notifications')
                ->where('user_id', $user['id'])
                ->delete();
            
            echo "Deleted test user: {$user['username']}\n";
        }

        // Delete the test users
        $this->db->table('users')
            ->where('username !=', 'admin')
            ->where('username !=', 'security')
            ->where('username !=', 'operator')
            ->delete();

        // Clear command queue
        $this->db->table('command_queue')->truncate();
        echo "Cleared command queue\n";

        // Clear old activity logs (keep last 100)
        $this->db->query("DELETE FROM activity_logs WHERE id NOT IN (SELECT id FROM activity_logs ORDER BY created_at DESC LIMIT 100)");
        echo "Cleaned old activity logs\n";

        // Clear old notifications (keep last 50)
        $this->db->query("DELETE FROM notifications WHERE id NOT IN (SELECT id FROM notifications ORDER BY created_at DESC LIMIT 50)");
        echo "Cleaned old notifications\n";

        echo "✅ Development data cleanup complete\n\n";
    }
}
