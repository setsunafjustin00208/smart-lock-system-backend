<?php
require_once 'vendor/autoload.php';

// Load CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

// Create user using UserModel
$userModel = new \App\Models\UserModel();

$userData = [
    'username' => 'august_graves',
    'email' => 'setsunafjustin002@gmail.com',
    'password' => 'gundam',
    'roles' => ['admin'],
    'first_name' => 'August',
    'last_name' => 'Graves',
    'phone' => '',
    'preferences' => []
];

try {
    $userId = $userModel->createUser($userData);
    
    if ($userId) {
        echo "✅ Admin user created successfully!\n";
        echo "Username: august_graves\n";
        echo "Email: setsunafjustin002@gmail.com\n";
        echo "Password: gundam\n";
        echo "Role: admin\n";
        echo "User ID: {$userId}\n";
    } else {
        echo "❌ Failed to create user\n";
        print_r($userModel->errors());
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
