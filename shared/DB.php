<?php
declare(strict_types=1);

namespace Shared;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;          // global (CP/default) connection
    private static ?PDO $tenant = null;       // current-tenant connection (db_per_org)
    private static string $mode = 'row_guard';// 'row_guard' | 'db_per_org'

    /* ----------------------- Public API ----------------------- */

    /** Global/default PDO (always available) */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $driver = getenv('DB_DRIVER') ?: 'mysql';
        if ($driver !== 'mysql') throw new PDOException("Only mysql is supported.");

        $dsn  = self::buildDsn(
            getenv('DB_HOST') ?: '127.0.0.1',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: ''
        );
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASS') ?: '';

        self::$pdo = self::connect($dsn, $user, $pass);
        self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

        // cache mode for fast access
        $m = strtolower((string)(getenv('ISOLATION_MODE') ?: getenv('MULTI_TENANCY_MODE') ?: 'row_guard'));
        self::$mode = in_array($m, ['row_guard','db_per_org'], true) ? $m : 'row_guard';

        return self::$pdo;
    }

    /** Current tenant PDO. In row_guard mode this just returns self::pdo(). */
    public static function tenant(): PDO
    {
        if (self::mode() === 'db_per_org') {
            return self::$tenant instanceof PDO ? self::$tenant : self::pdo();
        }
        return self::pdo();
    }

    /** Current multi-tenancy mode */
    public static function mode(): string
    {
        if (!self::$pdo) self::pdo();
        return self::$mode;
    }

    /* ------------------- Provisioning helpers ------------------ */

    /**
     * Ensure the tenant DB exists and switch self::$tenant to it.
     * Safe to call repeatedly. No-ops in row_guard mode.
     *
     * @param array $org Row with at least: id, slug, (optional) db_name/db_user/db_pass
     * @return bool true if tenant handle is ready
     */
    public static function provisionTenantDb(array $org): bool
    {
        self::pdo(); // ensure globals loaded/mode cached

        if (self::mode() !== 'db_per_org') {
            // single DB mode — use global handle as tenant
            self::$tenant = self::$pdo;
            return true;
        }

        $orgId  = (int)($org['id'] ?? 0);
        $slug   = (string)($org['slug'] ?? '');
        if ($orgId <= 0 || $slug === '') return false;

        // Allow per-org overrides (optional future fields)
        $dbName = $org['db_name'] ?? null;
        $dbUser = $org['db_user'] ?? null;
        $dbPass = $org['db_pass'] ?? null;

        if (!$dbName) {
            $tpl = trim((string)(getenv('TENANT_DB_DSN_TEMPLATE') ?: ''));
            if ($tpl === '') {
                // fallback: kf_org_<ID>
                $dbName = 'kf_org_' . $orgId;
            } else {
                // If template has %ID% we keep name in DSN, else derive a name too
                // For clarity we still name the schema kf_org_<ID>
                $dbName = 'kf_org_' . $orgId;
            }
        }

        // Credentials: either per-org, or global tenant creds from .env
        $dbUser = (string)($dbUser ?: (getenv('TENANT_DB_USER') ?: getenv('DB_USER') ?: ''));
        $dbPass = (string)($dbPass ?: (getenv('TENANT_DB_PASS') ?: getenv('DB_PASS') ?: ''));

        $host   = getenv('DB_HOST') ?: '127.0.0.1';
        $port   = (int)(getenv('DB_PORT') ?: 3306);

        // 1) Create DB if missing (use global connection)
        $global = self::pdo();
        $dbNameSafe = preg_replace('/[^a-zA-Z0-9_]+/', '_', $dbName);
        $global->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameSafe}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

        // 2) Connect tenant handle to that DB
        $dsnTenant = self::buildDsn($host, $port, $dbNameSafe);
        self::$tenant = self::connect($dsnTenant, $dbUser, $dbPass);
        self::$tenant->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

        return true;
    }

    /**
     * Create base schema inside the tenant DB (idempotent).
     * Call this after provisionTenantDb().
     */
    public static function ensureTenantSchema(): void
    {
        $pdo = self::tenant();

        // tenant_users
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                username VARCHAR(120) DEFAULT NULL,
                mobile VARCHAR(30) DEFAULT NULL,
                role ENUM('owner','manager','employee','viewer') NOT NULL DEFAULT 'employee',
                password_hash VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_org_email (org_id, email),
                UNIQUE KEY uq_org_username (org_id, username),
                KEY idx_org (org_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // password resets
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_password_resets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id INT UNSIGNED NOT NULL,
                email VARCHAR(190) NOT NULL,
                token CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token (token),
                KEY idx_org (org_id),
                KEY idx_email (email),
                KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // invites
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_user_invites (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id INT UNSIGNED NOT NULL,
                email VARCHAR(190) NOT NULL,
                role ENUM('owner','manager','employee','viewer') NOT NULL DEFAULT 'employee',
                token CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token (token),
                KEY idx_org (org_id),
                KEY idx_email (email),
                KEY idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // audit logs
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id INT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                action VARCHAR(120) NOT NULL,
                meta JSON DEFAULT NULL,
                ip VARBINARY(16) DEFAULT NULL,
                ua VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_org (org_id),
                KEY idx_user (user_id),
                KEY idx_action (action),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /* ------------------- Internal small helpers ------------------- */

    private static function connect(string $dsn, string $user, string $pass): PDO
    {
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, $user, $pass, $opt);
    }

    private static function buildDsn(string $host, int $port, string $db): string
    {
        $charset = 'utf8mb4';
        return "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    }
}