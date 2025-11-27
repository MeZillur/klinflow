<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Simple file-based logger
 * --------------------------------------------------
 * Writes messages to /storage/logs/app.log
 * (or a given channel file).
 */
final class Logger
{
    /**
     * Write a message to a log file.
     *
     * @param string $message  Message to log
     * @param string $channel  Log filename (without extension)
     */
    public static function write(string $message, string $channel = 'app'): void
    {
        $root = dirname(__DIR__, 2); // project root
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $file = $logDir . '/' . $channel . '.log';
        $date = date('Y-m-d H:i:s');
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '-';
        $msg  = "[{$date}] [{$ip}] {$message}\n";

        @file_put_contents($file, $msg, FILE_APPEND | LOCK_EX);
    }

    /**
     * Convenience alias for writing exceptions.
     *
     * @param \Throwable $e
     * @param string $context
     */
    public static function exception(\Throwable $e, string $context = 'Exception'): void
    {
        $msg = "{$context}: " . $e->getMessage() . " in " .
               $e->getFile() . ':' . $e->getLine() .
               "\n" . $e->getTraceAsString();
        self::write($msg, 'error');
    }

    /**
     * Clear all logs in storage/logs (use carefully).
     */
    public static function clearAll(): void
    {
        $root = dirname(__DIR__, 2);
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) return;

        foreach (glob($logDir . '/*.log') as $file) {
            @unlink($file);
        }
    }
}