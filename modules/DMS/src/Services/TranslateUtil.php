<?php
declare(strict_types=1);

namespace Modules\DMS\Services;

/**
 * Lightweight Google Translate v2 client with on-disk caching.
 *
 * - Reads env: GOOGLE_TRANSLATE_API_KEY, DMS_TRANSLATE_DEFAULT_SOURCE
 * - Local file cache at modules/DMS/storage/i18n-cache.json (per-tenant safe to share)
 * - Safe to call in web reqs (small timeouts + defensive)
 */
final class TranslateUtil
{
    private const CACHE_FILE = 'storage/i18n-cache.json'; // relative to module dir
    private const TIMEOUT    = 4; // seconds
    private static ?array $cache = null;
    private static string $cachePath = '';

    /** Is translation enabled (has API key)? */
    public static function isEnabled(): bool
    {
        $key = (string)(getenv('GOOGLE_TRANSLATE_API_KEY') ?: '');
        return $key !== '';
    }

    /** Translate a single string; returns original on failure. */
    public static function translate(string $moduleDir, string $text, string $target, ?string $source=null): string
    {
        $text   = trim($text);
        if ($text === '') return $text;
        if (!self::isEnabled()) return $text;
        $source = $source ?: (string)(getenv('DMS_TRANSLATE_DEFAULT_SOURCE') ?: 'en');
        if (strtolower($source) === strtolower($target)) return $text;

        self::bootCache($moduleDir);
        $key = self::cacheKey($text, $source, $target);
        if (isset(self::$cache[$key])) return (string)self::$cache[$key];

        // Heuristics: skip obvious placeholders/ids to avoid nonsense translations
        if (preg_match('~[{][^}]+[}]~', $text)) { // has {placeholders}
            return $text;
        }
        if (preg_match('~^[A-Za-z0-9_.-]{1,32}$~', $text)) { // likely an identifier
            return $text;
        }

        $out = self::callGoogleV2($text, $target, $source);
        if ($out !== null && $out !== '') {
            self::$cache[$key] = $out;
            self::saveCache();
            return $out;
        }
        return $text;
    }

    /** Batch translate; returns array keeping input order. */
    public static function batchTranslate(string $moduleDir, array $texts, string $target, ?string $source=null): array
    {
        $res = [];
        foreach ($texts as $t) {
            $res[] = self::translate($moduleDir, (string)$t, $target, $source);
        }
        return $res;
    }

    /* ───────────────────────── Internals ───────────────────────── */

    private static function bootCache(string $moduleDir): void
    {
        if (self::$cache !== null) return;
        $path = rtrim($moduleDir, '/').'/'.self::CACHE_FILE;
        self::$cachePath = $path;

        // ensure dir exists
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $json = is_file($path) ? @file_get_contents($path) : '';
        $data = $json ? json_decode($json, true) : null;
        self::$cache = is_array($data) ? $data : [];
    }

    private static function saveCache(): void
    {
        if (!self::$cachePath) return;
        // best-effort atomic write
        $tmp = self::$cachePath.'.tmp';
        @file_put_contents($tmp, json_encode(self::$cache, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        @rename($tmp, self::$cachePath);
    }

    private static function cacheKey(string $text, string $source, string $target): string
    {
        return hash('sha256', $source.'→'.$target.'|'.$text);
    }

    /**
     * Calls Google Translate v2: https://translation.googleapis.com/language/translate/v2
     * Uses q=<text>, target, source, key
     */
    private static function callGoogleV2(string $text, string $target, string $source): ?string
    {
        $apiKey = (string)getenv('GOOGLE_TRANSLATE_API_KEY');
        if ($apiKey === '') return null;

        $endpoint = 'https://translation.googleapis.com/language/translate/v2';
        $payload  = http_build_query([
            'q'      => $text,
            'target' => $target,
            'source' => $source,
            'format' => 'text',
            'key'    => $apiKey,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint.'?'.$payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $code !== 200 || !$body) return null;
        $json = json_decode($body, true);
        $t    = $json['data']['translations'][0]['translatedText'] ?? null;
        return is_string($t) ? $t : null;
    }
}