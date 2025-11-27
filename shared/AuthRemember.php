<?php
declare(strict_types=1);

namespace Shared;

use Shared\DB;
use PDO;

/**
 * Persistent "remember me" for Control Panel (and optionally Tenant) with:
 * - selector:validator split token (validator hashed at rest)
 * - per-user soft cap of active tokens (if created_at is present)
 * - scope-aware cookie name to avoid collisions across modules
 * - optional device binding via ua_hash/ip columns (if present)
 * - constant-time validation and automatic rotation on use
 *
 * Table expected: cp_remember_tokens
 * Columns (recommended):
 *   id BIGINT PK, cp_user_id BIGINT, selector VARCHAR(64) UNIQUE,
 *   validator_hash CHAR(64), expires_at DATETIME,
 *   created_at DATETIME, last_used_at DATETIME,
 *   ua_hash CHAR(64) NULL, ip VARCHAR(45) NULL
 * Add appropriate indexes: (cp_user_id), (selector UNIQUE), (expires_at)
 */
final class AuthRemember
{
    /* ---------- Defaults (can be overridden via setters) ---------- */

    private const BASE_COOKIE            = 'KLINFLOW_RM';
    private const DEFAULT_DAYS           = 30;
    private const MAX_TOKENS_PER_USER    = 5;   // enforced when created_at exists
    private const DEFAULT_SAMESITE       = 'Lax'; // 'Lax' or 'Strict' or 'None'
    private const DEFAULT_SCOPE          = 'cp';  // cookie suffix: KLINFLOW_RM_CP

    /** Current runtime config */
    private static string $scope         = self::DEFAULT_SCOPE;   // cp / tenant / etc.
    private static ?string $cookieDomain = null;                  // e.g. '.klinflow.com' or null
    private static string $sameSite      = self::DEFAULT_SAMESITE;
    private static int $defaultDays      = self::DEFAULT_DAYS;

    /* ------------------------ public API ------------------------ */

    /**
     * Configure runtime options (optional).
     * Example: AuthRemember::configure(scope: 'cp', domain: '.klinflow.com', sameSite: 'Lax', defaultDays: 30);
     */
    public static function configure(?string $scope = null, ?string $domain = null, ?string $sameSite = null, ?int $defaultDays = null): void
    {
        if ($scope !== null)      self::$scope = self::cleanScope($scope);
        if ($domain !== null)     self::$cookieDomain = ($domain === '') ? null : $domain;
        if ($sameSite !== null)   self::$sameSite = in_array($sameSite, ['Lax','Strict','None'], true) ? $sameSite : self::DEFAULT_SAMESITE;
        if ($defaultDays !== null && $defaultDays > 0) self::$defaultDays = $defaultDays;
    }

    /** Attempt auto-login for CP (or current scope) from remember cookie. No-op if anything is off. */
    public static function autoLogin(): void
    {
        self::ensureSession();

        // Already logged in to CP? (scope 'cp' defaults to cp_user)
        $sessionKey = self::sessionKeyForScope();
        if (!empty($_SESSION[$sessionKey])) return;

        $cookieName = self::cookieName();
        $cookie     = $_COOKIE[$cookieName] ?? '';
        if ($cookie === '' || !self::tableExists('cp_remember_tokens')) return;

        // Opportunistic cleanup first
        self::pruneExpired();

        [$selector, $validator] = self::splitCookie($cookie);
        if ($selector === '' || $validator === '') { self::clearCurrent(); return; }

        $pdo = DB::pdo();
        $row = self::fetchTokenRow($pdo, $selector);
        if (!$row) { self::clearCurrent(); return; }

        // Expired?
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
            self::clearBySelector($selector);
            self::clearCurrent();
            return;
        }

        // Device binding checks (optional)
        if (self::hasColumn($pdo,'cp_remember_tokens','ua_hash')) {
            $curUa = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
            $dbUa  = (string)($row['ua_hash'] ?? '');
            if ($dbUa !== '' && !hash_equals($dbUa, $curUa)) {
                // UA changed vs bound token → treat as suspicious
                self::clearBySelector($selector);
                self::clearCurrent();
                return;
            }
        }
        if (self::hasColumn($pdo,'cp_remember_tokens','ip')) {
            $curIp = self::clientIpForStore() ?? '';
            $dbIp  = (string)($row['ip'] ?? '');
            // Do NOT hard fail on IP mismatch (mobile networks), but you may log/notify here if desired.
        }

        // Constant-time compare of hashed validator
        $calc = hash('sha256', $validator);
        if (!hash_equals((string)$row['validator_hash'], $calc)) {
            // Possible token theft → remove it
            self::clearBySelector($selector);
            self::clearCurrent();
            return;
        }

        // Fetch user (minimal hydrate)
        $u = $pdo->prepare("SELECT id,name,email,role,is_active FROM cp_users WHERE id=? LIMIT 1");
        $u->execute([(int)$row['cp_user_id']]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            self::clearBySelector($selector);
            self::clearCurrent();
            return;
        }

        // Success → rotate session ID to prevent fixation
        @session_regenerate_id(true);

        // Establish scoped session payload
        $_SESSION[$sessionKey] = [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['name'],
            'email' => (string)$user['email'],
            'role'  => (string)$user['role'],
        ];

        // Rotate validator (+ refresh last_used_at, ip/ua if supported)
        self::rotate($selector);
    }

    /** Issue a remember cookie for a CP user (or current scope) */
    public static function issue(int $cpUserId, ?int $days = null): void
    {
        if (!self::tableExists('cp_remember_tokens')) return;

        $days = $days ?? self::$defaultDays;

        $selector  = bin2hex(random_bytes(12)); // 24 printable
        $validator = bin2hex(random_bytes(32)); // 64 printable
        $hash      = hash('sha256', $validator);
        $expAt     = date('Y-m-d H:i:s', time() + max(1, $days) * 86400);

        $pdo = DB::pdo();

        // Optional columns
        $hasCreatedAt  = self::hasColumn($pdo, 'cp_remember_tokens', 'created_at');
        $hasLastUsedAt = self::hasColumn($pdo, 'cp_remember_tokens', 'last_used_at');
        $hasIp         = self::hasColumn($pdo, 'cp_remember_tokens', 'ip');
        $hasUaHash     = self::hasColumn($pdo, 'cp_remember_tokens', 'ua_hash');

        $cols = ['cp_user_id','selector','validator_hash','expires_at'];
        $vals = [ $cpUserId,   $selector,  $hash,           $expAt     ];
        if ($hasCreatedAt)  { $cols[]='created_at';   $vals[] = date('Y-m-d H:i:s'); }
        if ($hasLastUsedAt) { $cols[]='last_used_at'; $vals[] = date('Y-m-d H:i:s'); }
        if ($hasIp)         { $cols[]='ip';           $vals[] = self::clientIpForStore(); }
        if ($hasUaHash)     { $cols[]='ua_hash';      $vals[] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''); }

        $sql = "INSERT INTO cp_remember_tokens (".implode(',', $cols).") VALUES (".
               implode(',', array_fill(0, count($cols), '?')).")";
        $pdo->prepare($sql)->execute($vals);

        // Enforce a soft cap per user (keeps newest)
        if ($hasCreatedAt) self::capTokensForUser($pdo, $cpUserId, self::MAX_TOKENS_PER_USER);

        self::setCookie($selector, $validator, $days);
    }

    /** Clear current cookie and matching DB token (if present) */
    public static function clearCurrent(): void
    {
        $cookieName = self::cookieName();
        $cookie     = $_COOKIE[$cookieName] ?? '';
        if ($cookie !== '') {
            [$selector] = self::splitCookie($cookie);
            if ($selector !== '' && self::tableExists('cp_remember_tokens')) {
                self::clearBySelector($selector);
            }
        }
        self::clearCookie();
    }

    /** Delete all tokens for a user */
    public static function clearAllForUser(int $cpUserId): void
    {
        if (!self::tableExists('cp_remember_tokens')) return;
        $pdo = DB::pdo();
        $pdo->prepare("DELETE FROM cp_remember_tokens WHERE cp_user_id=?")->execute([$cpUserId]);
    }

    /* ------------------------ internals ------------------------- */

    private static function ensureSession(): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            // keep minimal flags; CSRF helper already strengthens
            @ini_set('session.use_only_cookies','1');
            @session_start();
        }
    }

    private static function tableExists(string $table): bool
    {
        try {
            $pdo = DB::pdo();
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function hasColumn(PDO $pdo, string $table, string $col): bool
    {
        try {
            $st = $pdo->prepare("
                SELECT 1
                  FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = ?
                   AND column_name  = ?
                 LIMIT 1
            ");
            $st->execute([$table, $col]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function fetchTokenRow(PDO $pdo, string $selector): ?array
    {
        $sel = "cp_user_id, validator_hash, expires_at";
        if (self::hasColumn($pdo,'cp_remember_tokens','ua_hash')) $sel .= ", ua_hash";
        if (self::hasColumn($pdo,'cp_remember_tokens','ip'))      $sel .= ", ip";

        $sql = "SELECT {$sel} FROM cp_remember_tokens WHERE selector=? LIMIT 1";
        $q = $pdo->prepare($sql);
        $q->execute([$selector]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function pruneExpired(): void
    {
        try {
            $pdo = DB::pdo();
            $pdo->prepare("DELETE FROM cp_remember_tokens WHERE expires_at < NOW()")->execute();
        } catch (\Throwable) { /* ignore */ }
    }

    private static function capTokensForUser(PDO $pdo, int $cpUserId, int $keep): void
    {
        try {
            // Delete older tokens, keep N newest by created_at (fallback to id)
            $hasCreatedAt = self::hasColumn($pdo,'cp_remember_tokens','created_at');
            $order = $hasCreatedAt ? "created_at DESC, id DESC" : "id DESC";
            $sql = "
                DELETE t FROM cp_remember_tokens t
                JOIN (
                  SELECT id FROM cp_remember_tokens
                  WHERE cp_user_id = ?
                  ORDER BY {$order}
                  LIMIT 18446744073709551615 OFFSET ?
                ) old ON old.id = t.id
            ";
            $pdo->prepare($sql)->execute([$cpUserId, max(0,$keep)]);
        } catch (\Throwable) { /* ignore */ }
    }

    private static function rotate(string $selector): void
    {
        if (!self::tableExists('cp_remember_tokens')) return;

        $pdo = DB::pdo();

        $validator = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $validator);

        $hasLastUsedAt = self::hasColumn($pdo,'cp_remember_tokens','last_used_at');
        $hasUaHash     = self::hasColumn($pdo,'cp_remember_tokens','ua_hash');
        $hasIp         = self::hasColumn($pdo,'cp_remember_tokens','ip');

        $sets = ["validator_hash = ?","expires_at = DATE_ADD(NOW(), INTERVAL ".self::$defaultDays." DAY)"];
        $args = [$hash];

        if ($hasLastUsedAt) { $sets[] = "last_used_at = NOW()"; }
        if ($hasUaHash)     { $sets[] = "ua_hash = ?"; $args[] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''); }
        if ($hasIp)         { $sets[] = "ip = ?";      $args[] = self::clientIpForStore(); }

        $args[] = $selector;

        $sql = "UPDATE cp_remember_tokens SET ".implode(', ', $sets)." WHERE selector=? LIMIT 1";
        $pdo->prepare($sql)->execute($args);

        self::setCookie($selector, $validator, self::$defaultDays);
    }

    private static function clearBySelector(string $selector): void
    {
        try {
            $pdo = DB::pdo();
            $pdo->prepare("DELETE FROM cp_remember_tokens WHERE selector=?")->execute([$selector]);
        } catch (\Throwable) { /* ignore */ }
    }

    private static function splitCookie(string $cookie): array
    {
        $parts = explode(':', $cookie, 2);
        return [ $parts[0] ?? '', $parts[1] ?? '' ];
    }

    private static function cookieName(): string
    {
        // e.g., KLINFLOW_RM_CP or KLINFLOW_RM_TENANT (uppercased)
        $suffix = strtoupper(self::$scope);
        return self::BASE_COOKIE . '_' . $suffix;
    }

    private static function sessionKeyForScope(): string
    {
        // Keep default CP behavior
        return (self::$scope === 'cp') ? 'cp_user' : self::$scope . '_user';
    }

    private static function setCookie(string $selector, string $validator, int $days): void
    {
        $value = $selector . ':' . $validator;
        $exp   = time() + max(1, $days) * 86400;

        $params = [
            'expires'  => $exp,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => self::$sameSite,
        ];
        if (self::$cookieDomain !== null) {
            $params['domain'] = self::$cookieDomain;
        }

        setcookie(self::cookieName(), $value, $params);
    }

    private static function clearCookie(): void
    {
        $params = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => self::$sameSite,
        ];
        if (self::$cookieDomain !== null) {
            $params['domain'] = self::$cookieDomain;
        }

        setcookie(self::cookieName(), '', $params);
    }

    private static function clientIpForStore(): ?string
    {
        $ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') return null;
        $ip = trim(explode(',', $ip)[0]);
        return $ip ?: null;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    private static function cleanScope(string $scope): string
    {
        $scope = strtolower(preg_replace('~[^a-z0-9_]+~i', '', $scope) ?: self::DEFAULT_SCOPE);
        return $scope;
    }
}