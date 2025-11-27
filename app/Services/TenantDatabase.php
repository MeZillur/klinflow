<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Shared\DB;

final class TenantDatabase
{
    /**
     * Idempotent provisioning for a tenant database & migrations.
     * Requires env vars: TENANT_DB_PREFIX, DB_HOST, DB_ROOT_USER, DB_ROOT_PASS
     */
    public static function provision(int $orgId, string $slug, string $plan): void
    {
        $prefix = getenv('TENANT_DB_PREFIX') ?: 'kf_org_';
        $dbName = $prefix . preg_replace('/[^a-z0-9_]/i','_', $slug);

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $root = getenv('DB_ROOT_USER') ?: getenv('DB_USER');     // fallback
        $pass = getenv('DB_ROOT_PASS') ?: getenv('DB_PASS');     // fallback

        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $root, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci");

        // Basic core tables (add your module migrations as needed)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$dbName}`.tenant_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            org_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            username VARCHAR(64) NULL,
            role ENUM('owner','admin','manager','member') NOT NULL DEFAULT 'member',
            mobile VARCHAR(32) NULL,
            password_hash VARCHAR(255) NOT NULL,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            last_login_ip VARCHAR(45) NULL,
            failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tu_org_email (org_id,email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci");

        // You can branch migrations by $plan if needed.
    }
}