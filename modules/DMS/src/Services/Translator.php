<?php
declare(strict_types=1);

namespace Modules\DMS;

use Modules\DMS\Services\TranslateUtil;   // (A) add this

final class I18n
{
    /** @var array<string,array<string,string>> */
    private static array $dict = [];         // ['en'=>[k=>v], 'bn'=>[k=>v]]
    private static string $locale = 'en';
    private static string $moduleDir = '';
    private static bool $booted = false;

    /** Mark parameter explicitly nullable to avoid deprecation warnings */
    public static function boot(array $ctx, string $moduleDir, ?callable $onMiss = null): void // (D)
    {
        if (self::$booted) return;
        self::$booted    = true;
        self::$moduleDir = rtrim($moduleDir,'/');

        // 1) base dictionaries from disk (you already had this in your file)
        self::$dict['en'] = is_file(self::path('i18n/en.php')) ? require self::path('i18n/en.php') : [];
        self::$dict['bn'] = is_file(self::path('i18n/bn.php')) ? require self::path('i18n/bn.php') : [];

        // 2) merged runtime cache (auto-generated translations, if any)
        $rtBn = self::runtimeBn(); // array
        if ($rtBn) self::$dict['bn'] = array_replace(self::$dict['bn'], $rtBn);

        // 3) locale from cookie/session (your existing logic)
        self::$locale = self::detectLocale($ctx);

        // 4) optional on-miss handler (kept for BC)
        if ($onMiss) $onMiss();
    }

    public static function setLocale(array $ctx, string $locale, bool $persist = false): void
    {
        $locale = in_array($locale, ['en','bn'], true) ? $locale : 'en';
        self::$locale = $locale;
        if ($persist) {
            $_SESSION['dms_locale'] = $locale;
            setcookie('dms_locale', $locale, time()+86400*365, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }

    public static function strings(): array { return self::$dict[self::$locale] ?? []; }
    public static function locale(): string { return self::$locale; }

    /** Translate a string key (returns key if not found) */
    public static function ts(string $key): string
    {
        $loc = self::$locale ?: 'en';
        $val = self::$dict[$loc][$key] ?? null;

        if ($val === null && $loc === 'bn') {
            // (C) attempt auto-translation and persist in runtime cache
            $val = self::__auto_translate_if_missing($key, 'bn', 'en');
        }

        return ($val ?? $key);
    }

    /** Translate with placeholder replacements: __t('Hello :name', ['name'=>'Zara']) */
    public static function t(string $key, array $repl = []): string
    {
        $s = self::ts($key);
        foreach ($repl as $k=>$v) {
            $s = str_replace(':'.$k, (string)$v, $s);
        }
        return $s;
    }

    /* ─────────────────────────── Internals ─────────────────────────── */

    private static function path(string $rel): string
    {
        return self::$moduleDir.'/'.ltrim($rel,'/');
    }

    private static function detectLocale(array $ctx): string
    {
        $q = strtolower((string)($_GET['hl'] ?? ''));
        if (in_array($q, ['en','bn'], true)) {
            $_SESSION['dms_locale']=$q;
            setcookie('dms_locale', $q, time()+86400*365, '/', '', isset($_SERVER['HTTPS']), true);
            return $q;
        }
        $cookie = strtolower((string)($_COOKIE['dms_locale'] ?? ''));
        if (in_array($cookie, ['en','bn'], true)) return $cookie;
        $sess = strtolower((string)($_SESSION['dms_locale'] ?? ''));
        if (in_array($sess, ['en','bn'], true)) return $sess;
        return 'en';
    }

    private static function runtimeBnPath(): string
    {
        return self::path('storage/i18n-runtime-bn.php');
    }

    /** Load runtime BN additions (generated translations) */
    private static function runtimeBn(): array
    {
        $p = self::runtimeBnPath();
        return is_file($p) ? (require $p ?: []) : [];
    }

    /** Append/merge a new BN translation into runtime file */
    private static function runtimeBnSave(array $kv): void
    {
        $p   = self::runtimeBnPath();
        $dir = dirname($p);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $current = self::runtimeBn();
        $merged  = $kv + $current; // keep first translation wins; change to array_replace() if you want overwrite
        $export  = "<?php\nreturn ".var_export($merged, true).";\n";
        @file_put_contents($p, $export);
        // also reflect in-memory
        self::$dict['bn'] = $merged + (self::$dict['bn'] ?? []);
    }

    /**
     * (B) If BN missing, auto-translate via Google and persist into runtime dictionary.
     * Soft-fails to original English if API not configured or request fails.
     */
    private static function __auto_translate_if_missing(string $key, string $target, string $source): string
    {
        // Guard: feature only if API key is present
        if (!TranslateUtil::isEnabled()) return $key;

        // Keep short strings and UI labels snappy; cap length
        if (mb_strlen($key, 'UTF-8') > 220) return $key;

        $translated = TranslateUtil::translate(self::$moduleDir, $key, $target, $source);
        if ($translated && $translated !== $key) {
            self::runtimeBnSave([$key => $translated]);
            return $translated;
        }
        return $key;
    }
}

/* ───── Facade functions you already call from views ───── */

function i18n_boot(array $ctx, string $moduleDir): void
{
    I18n::boot($ctx, $moduleDir, null);
}

function __ts(string $k): string { return I18n::ts($k); }
function __t(string $k, array $r = []): string { return I18n::t($k, $r); }
function __getLocale(): string { return I18n::locale(); }