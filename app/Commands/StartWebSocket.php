<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class StartWebSocket extends BaseCommand
{
    protected $group = 'Hardware';
    protected $name = 'websocket:start';
    protected $description = 'Start WebSocket server for ESP32 communication';

    public function run(array $params)
    {
        $port = $params['port'] ?? 3000;
        
        CLI::write("Starting WebSocket server on port {$port}...", 'green');
        
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new \App\Libraries\SimpleWebSocketServer()
                )
            ),
            $port,
            '0.0.0.0'  // Bind to all interfaces
        );

        CLI::write("WebSocket server running on ws://localhost:{$port}", 'green');
        $server->run();
    }
}
