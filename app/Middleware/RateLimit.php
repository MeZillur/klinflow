<?php
declare(strict_types=1);

namespace App\Middleware;

/**
 * Very simple file-based rate limiter.
 * Stores counters under storage/cache/ratelimit/{key}.txt
 * Good enough for small bursts (login, forgot password).
 */
final class RateLimit
{
    /**
     * Throttle by a key (ip, email, route, etc.)
     *
     * @param string $key        Unique key to throttle (e.g. "login:ip:1.2.3.4")
     * @param int    $maxHits    Allowed attempts in the window
     * @param int    $windowSec  Rolling window in seconds
     *
     * @return bool  True if allowed, false if blocked
     */
    public static function allow(string $key, int $maxHits, int $windowSec): bool
    {
        if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__, 2));
        $dir = BASE_PATH . '/storage/cache/ratelimit';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $file = $dir . '/' . sha1($key) . '.txt';

        $now = time();
        $hits = [];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw) {
                $hits = array_filter(array_map('intval', explode("\n", trim($raw))), fn($t) => $t > ($now - $windowSec));
            }
        }

        $hits[] = $now;

        if (count($hits) > $maxHits) {
            // Save and block
            @file_put_contents($file, implode("\n", $hits));
            return false;
        }

        @file_put_contents($file, implode("\n", $hits));
        return true;
    }
}