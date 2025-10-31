<?php

namespace App\Libraries;

class HardwareSecurityLib
{
    private $secretKey;

    public function __construct()
    {
        $this->secretKey = env('COMMAND_SECRET_KEY');
    }

    public function signPayload($payload)
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $data, $this->secretKey);
    }

    public function verifySignature($payload, $signature)
    {
        return hash_equals($this->signPayload($payload), $signature);
    }

    public function encryptPayload($payload, $key = null)
    {
        $key = $key ?: $this->secretKey;
        $iv = random_bytes(16);
        $data = json_encode($payload);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv)
        ];
    }

    public function decryptPayload($encryptedData, $key = null)
    {
        $key = $key ?: $this->secretKey;
        $data = base64_decode($encryptedData['data']);
        $iv = base64_decode($encryptedData['iv']);
        
        $decrypted = openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return json_decode($decrypted, true);
    }
}
