<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\API'], function($routes) {
    // Auth routes (no auth required)
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/refresh', 'AuthController::refresh');
    
    // Protected routes
    $routes->group('', ['filter' => 'auth'], function($routes) {
        $routes->post('auth/logout', 'AuthController::logout');
        
        // Users
        $routes->get('users', 'UsersController::index');
        $routes->post('users', 'UsersController::create');
        
        // Locks
        $routes->get('locks', 'LocksController::index');
        $routes->get('locks/(:num)', 'LocksController::show/$1');
        $routes->post('locks/(:num)/control', 'LocksController::control/$1');
    });
});
