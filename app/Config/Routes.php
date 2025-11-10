<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\API'], function($routes) {
    // OPTIONS routes for CORS preflight
    $routes->options('auth/login', function() { return ''; });
    $routes->options('auth/logout', function() { return ''; });
    $routes->options('auth/refresh', function() { return ''; });
    $routes->options('auth/profile', function() { return ''; });
    $routes->options('auth/password', function() { return ''; });
    $routes->options('auth/notifications', function() { return ''; });
    $routes->options('users', function() { return ''; });
    $routes->options('users/(:any)', function() { return ''; });
    $routes->options('locks', function() { return ''; });
    $routes->options('locks/(:any)', function() { return ''; });
    $routes->options('notifications', function() { return ''; });
    $routes->options('notifications/(:any)', function() { return ''; });
    $routes->options('hardware/(:any)', function() { return ''; });
    
    // Auth routes (no auth required)
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/refresh', 'AuthController::refresh');
    
    // Hardware routes (no auth required for device communication)
    $routes->post('hardware/heartbeat', 'HardwareController::heartbeat');
    $routes->post('hardware/status', 'HardwareController::status');
    
    // Protected routes
    $routes->group('', ['filter' => 'auth'], function($routes) {
        // Auth endpoints
        $routes->post('auth/logout', 'AuthController::logout');
        $routes->put('auth/profile', 'AuthController::updateProfile');
        $routes->put('auth/password', 'AuthController::changePassword');
        $routes->get('auth/notifications', 'AuthController::getNotificationSettings');
        $routes->put('auth/notifications', 'AuthController::updateNotificationSettings');
        
        // Users
        $routes->get('users', 'UsersController::index');
        $routes->post('users', 'UsersController::create');
        $routes->put('users/(:num)', 'UsersController::update/$1');
        $routes->delete('users/(:num)', 'UsersController::delete/$1');
        
        // Locks
        $routes->get('locks', 'LocksController::index');
        $routes->get('locks/(:num)', 'LocksController::show/$1');
        $routes->post('locks/(:num)/control', 'LocksController::control/$1');
        $routes->get('locks/status', 'LocksController::batteryStatus');
        
        // Notifications
        $routes->get('notifications', 'NotificationsController::index');
        $routes->put('notifications/(:num)/read', 'NotificationsController::markAsRead/$1');
        $routes->put('notifications/read-all', 'NotificationsController::markAllAsRead');
        $routes->delete('notifications/(:num)', 'NotificationsController::delete/$1');
    });
});
