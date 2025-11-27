<?php
declare(strict_types=1);

namespace App\Services;

final class Sms
{
    /** Minimal stub. Swap body to hit your provider later. */
    public static function send(string $toE164, string $message, string $purpose = 'general'): bool
    {
        // TODO: integrate provider (Twilio, Teletalk, etc.)
        // For now: log to file so you can verify flow without sending.
        $line = sprintf("[%s] %s | %s | %s\n", date('c'), $purpose, $toE164, $message);
        @file_put_contents(__DIR__.'/../../storage/logs/sms.log', $line, FILE_APPEND);
        return true;
    }
}