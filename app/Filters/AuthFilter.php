<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return service('response')->setJSON([
                'status' => 'error',
                'message' => 'Authorization token required'
            ])->setStatusCode(401);
        }

        $token = substr($authHeader, 7);
        $authLib = new \App\Libraries\AuthenticationLib();
        $tokenData = $authLib->validateToken($token);

        if (!$tokenData) {
            return service('response')->setJSON([
                'status' => 'error',
                'message' => 'Invalid token'
            ])->setStatusCode(401);
        }

        $request->user = $tokenData;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do here
    }
}
