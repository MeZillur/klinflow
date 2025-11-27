<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Stateless HMAC signed links with TTL.
 * Usage:
 *   $url = SignedLink::tenantDoc($tenantSlug, 'invoice', $id, 7*24*60*60); // 7 days
 *   // Produces /share/dms/{tenant}/invoice/{id}?exp=...&sig=...
 */
final class SignedLink
{
    /** Resolve a secret key safely from env/config. */
    private static function key(): string
    {
        // Prefer APP_KEY, fallback to hash of server + file.
        $k = getenv('APP_KEY') ?: '';
        if ($k !== '') return hash('sha256', $k, true);
        $seed = ($_SERVER['HTTP_HOST'] ?? 'localhost') . '|' . __FILE__;
        return hash('sha256', $seed, true);
    }

    /** Build a canonical string for signing. */
    private static function canon(string $method, string $path, array $q): string
    {
        ksort($q);
        return strtoupper($method) . "\n" . $path . "\n" . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /** Generate signature. */
    private static function sign(string $method, string $path, array $q): string
    {
        $data = self::canon($method, $path, $q);
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::key(), true)), '+/', '-_'), '=');
    }

    /** Verify signature & expiry. */
    public static function verify(string $method, string $path, array $query): bool
    {
        $exp = isset($query['exp']) ? (int)$query['exp'] : 0;
        $sig = (string)($query['sig'] ?? '');
        if ($exp < time() || $sig === '') return false;

        $q = $query;
        unset($q['sig']); // sign everything else
        $expect = self::sign($method, $path, $q);
        // constant-time compare
        return hash_equals($expect, $sig);
    }

    /**
     * Public share URL for a tenant document:
     *   /share/dms/{tenant}/{type}/{id}?exp=...&sig=...
     * $ttl seconds from now (default 7 days)
     */
    public static function tenantDoc(string $tenantSlug, string $type, int $id, int $ttl = 604800): string
    {
        $tenantSlug = trim($tenantSlug);
        $type = trim($type);
        if ($tenantSlug === '' || $type === '' || $id <= 0) {
            throw new \InvalidArgumentException('Invalid inputs for signed link.');
        }
        $path = "/share/dms/{$tenantSlug}/{$type}/{$id}";
        $qs   = ['exp' => time() + $ttl];
        $qs['sig'] = self::sign('GET', $path, $qs);

        // base URL from config
        $base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://')
              . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $base . $path . '?' . http_build_query($qs);
    }
}