<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MonitorHardwareLogs extends BaseCommand
{
    protected $group = 'Hardware';
    protected $name = 'hardware:logs';
    protected $description = 'Monitor hardware activity logs in real-time';

    public function run(array $params)
    {
        $logFile = WRITEPATH . 'logs/hardware_activity.log';
        
        CLI::write("=== Smart Lock Hardware Activity Monitor ===", 'green');
        CLI::write("Log file: " . $logFile, 'yellow');
        CLI::write("Press Ctrl+C to exit", 'light_gray');
        CLI::write("============================================", 'green');
        CLI::newLine();

        // Create log directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Touch log file if it doesn't exist
        if (!file_exists($logFile)) {
            touch($logFile);
            CLI::write("Created log file: " . $logFile, 'cyan');
        }

        // Get initial file size
        $lastSize = filesize($logFile);
        $handle = fopen($logFile, 'r');
        
        if (!$handle) {
            CLI::error("Cannot open log file: " . $logFile);
            return;
        }

        // Seek to end of file
        fseek($handle, 0, SEEK_END);

        CLI::write("Monitoring hardware activity... (waiting for logs)", 'cyan');
        CLI::newLine();

        while (true) {
            clearstatcache();
            $currentSize = filesize($logFile);
            
            if ($currentSize > $lastSize) {
                // New content available
                $newContent = fread($handle, $currentSize - $lastSize);
                $lines = explode("\n", trim($newContent));
                
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    $this->displayLogLine($line);
                }
                
                $lastSize = $currentSize;
            }
            
            usleep(100000); // Sleep 100ms
        }
        
        fclose($handle);
    }

    private function displayLogLine($line)
    {
        $timestamp = date('H:i:s');
        
        // Color coding based on activity type
        if (strpos($line, 'HEARTBEAT') !== false) {
            CLI::write("[$timestamp] " . $line, 'green');
        } elseif (strpos($line, 'COMMAND') !== false) {
            if (strpos($line, '"status":"completed"') !== false) {
                CLI::write("[$timestamp] " . $line, 'light_green');
            } else {
                CLI::write("[$timestamp] " . $line, 'yellow');
            }
        } elseif (strpos($line, 'STATUS_UPDATE') !== false) {
            if (strpos($line, '"state_changed":true') !== false) {
                CLI::write("[$timestamp] " . $line, 'light_cyan');
            } else {
                CLI::write("[$timestamp] " . $line, 'cyan');
            }
        } elseif (strpos($line, 'ERROR') !== false) {
            CLI::write("[$timestamp] " . $line, 'red');
        } else {
            CLI::write("[$timestamp] " . $line, 'white');
        }
    }
}
