<?php
namespace App\Libraries;

class HardwareLogger
{
    private $logFile;
    private $maxLogSize = 10485760; // 10MB

    public function __construct()
    {
        $this->logFile = WRITEPATH . 'logs/hardware_activity.log';
        $this->ensureLogDirectory();
    }

    public function logActivity($hardwareId, $activity, $data = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'hardware_id' => $hardwareId,
            'activity' => $activity,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $logLine = "[{$timestamp}] [{$hardwareId}] {$activity}: " . json_encode($data) . "\n";
        
        $this->writeToLog($logLine);
        $this->rotateLogIfNeeded();
    }

    public function logHeartbeat($hardwareId, $status = 'online')
    {
        $this->logActivity($hardwareId, 'HEARTBEAT', [
            'status' => $status,
            'signal_strength' => $_SERVER['HTTP_X_SIGNAL'] ?? null
        ]);
    }

    public function logCommand($hardwareId, $command, $commandId, $status = 'sent')
    {
        $this->logActivity($hardwareId, 'COMMAND', [
            'command' => $command,
            'command_id' => $commandId,
            'status' => $status
        ]);
    }

    public function logStatusUpdate($hardwareId, $isLocked, $previousState = null)
    {
        $this->logActivity($hardwareId, 'STATUS_UPDATE', [
            'is_locked' => $isLocked,
            'previous_state' => $previousState,
            'state_changed' => $previousState !== null && $previousState !== $isLocked
        ]);
    }

    public function logError($hardwareId, $error, $context = [])
    {
        $this->logActivity($hardwareId, 'ERROR', [
            'error' => $error,
            'context' => $context
        ]);
    }

    public function getRecentActivity($hardwareId = null, $limit = 100)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse(array_slice($lines, -$limit * 2)); // Get more lines to filter

        $activities = [];
        foreach ($lines as $line) {
            if ($hardwareId && strpos($line, "[{$hardwareId}]") === false) {
                continue;
            }
            
            $activities[] = $this->parseLine($line);
            
            if (count($activities) >= $limit) {
                break;
            }
        }

        return array_slice($activities, 0, $limit);
    }

    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    private function writeToLog($logLine)
    {
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    private function rotateLogIfNeeded()
    {
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $backupFile = $this->logFile . '.' . date('Y-m-d-H-i-s');
            rename($this->logFile, $backupFile);
            
            // Keep only last 5 backup files
            $this->cleanupOldLogs();
        }
    }

    private function cleanupOldLogs()
    {
        $logDir = dirname($this->logFile);
        $pattern = basename($this->logFile) . '.*';
        $files = glob($logDir . '/' . $pattern);
        
        if (count($files) > 5) {
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $filesToDelete = array_slice($files, 0, count($files) - 5);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }

    private function parseLine($line)
    {
        // Parse: [2025-11-13 00:28:47] [ESP32_TEST_001] HEARTBEAT: {"status":"online"}
        if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?): (.*)/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'hardware_id' => $matches[2],
                'activity' => $matches[3],
                'data' => json_decode($matches[4], true) ?: $matches[4]
            ];
        }
        
        return ['raw' => $line];
    }
}
