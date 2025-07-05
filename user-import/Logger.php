<?php
/**
 * Logger for User Import
 * 
 * Handles detailed logging for debugging and monitoring
 */

declare(strict_types=1);

class Logger
{
    private string $logFile;
    private string $logLevel;
    private array $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARN' => 2,
        'ERROR' => 3
    ];
    
    public function __construct(string $logFile, string $logLevel = 'INFO')
    {
        $this->logFile = $logFile;
        $this->logLevel = strtoupper($logLevel);
        
        // Create log directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warn(string $message, array $context = []): void
    {
        $this->log('WARN', $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Write log entry
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Check if we should log this level
        if ($this->levels[$level] < $this->levels[$this->logLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        // Write to file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running from CLI
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }
    
    /**
     * Clear log file
     */
    public function clear(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
    
    /**
     * Get log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
    
    /**
     * Get log file size in bytes
     */
    public function getLogFileSize(): int
    {
        return file_exists($this->logFile) ? filesize($this->logFile) : 0;
    }
    
    /**
     * Get last N lines from log file
     */
    public function getLastLines(int $lines = 50): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $file = new SplFileObject($this->logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        $logLines = [];
        
        $file->seek($startLine);
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logLines[] = $line;
            }
            $file->next();
        }
        
        return $logLines;
    }
} 