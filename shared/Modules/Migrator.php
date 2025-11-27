<?php
declare(strict_types=1);

namespace Shared\Modules;

use Shared\DB;

/**
 * Applies all *.sql under $dir once per tenant DB (or global DB in row_guard).
 * Records applied files in tenant_module_migrations table.
 */
final class Migrator
{
    public static function run(string $moduleKey, string $dir): void
    {
        if (!is_dir($dir)) return; // no migrations to run

        $pdo = method_exists(DB::class, 'tenant') ? DB::tenant() : DB::pdo();

        $pdo->exec("
          CREATE TABLE IF NOT EXISTS tenant_module_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_key VARCHAR(190) NOT NULL,
            filename   VARCHAR(255) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_mod_file (module_key, filename)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $applied = [];
        $st = $pdo->prepare("SELECT filename FROM tenant_module_migrations WHERE module_key=?");
        $st->execute([$moduleKey]);
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) $applied[$r['filename']] = true;

        $files = glob(rtrim($dir,'/').'/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $fn = basename($file);
            if (isset($applied[$fn])) continue;

            $sql = trim((string)file_get_contents($file));
            if ($sql === '') continue;

            $pdo->beginTransaction();
            try {
                // split by ; only on top-level (simple split good enough for our plain DDL)
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    if ($stmt === '') continue;
                    $pdo->exec($stmt);
                }
                $ins = $pdo->prepare("INSERT INTO tenant_module_migrations (module_key, filename) VALUES (?,?)");
                $ins->execute([$moduleKey, $fn]);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}