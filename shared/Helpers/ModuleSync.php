<?php
declare(strict_types=1);

namespace Shared\Helpers;

use Shared\DB;
use App\Services\ModulesCatalog;
use PDO;

final class ModuleSync
{
    /**
     * Idempotently sync /modules/** manifests into cp_modules.
     * - Upserts module rows keyed by module_key (or slug).
     * - Persists name/version/is_active=1.
     * - Optionally persists default_path and layout for observability.
     * - Returns [created=>n, updated=>n].
     */
    public static function syncFromFilesystem(): array
    {
        $pdo = DB::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $hasColumn = static function (string $col) use ($pdo): bool {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cp_modules' AND COLUMN_NAME = ?
            ");
            $stmt->execute([$col]);
            return (int)$stmt->fetchColumn() > 0;
        };

        $hasIndex = static function (string $index) use ($pdo): bool {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cp_modules' AND INDEX_NAME = ?
            ");
            $stmt->execute([$index]);
            return (int)$stmt->fetchColumn() > 0;
        };

        $execSafe = static function (string $sql) use ($pdo): void {
            try { $pdo->exec($sql); } catch (\Throwable $e) {
                // non-fatal: log and continue
                @file_put_contents(
                    dirname(__DIR__, 2) . '/storage/logs/app.log',
                    date('c') . " [ModuleSync DDL] " . $e->getMessage() . "\n",
                    FILE_APPEND
                );
            }
        };

        // --- Ensure schema (portable) ---
        if (!$hasColumn('name'))        $execSafe("ALTER TABLE cp_modules ADD COLUMN name VARCHAR(191) NOT NULL AFTER id");
        if (!$hasColumn('version'))     $execSafe("ALTER TABLE cp_modules ADD COLUMN version VARCHAR(32) NULL");
        if (!$hasColumn('is_active'))   $execSafe("ALTER TABLE cp_modules ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
        if (!$hasColumn('created_at'))  $execSafe("ALTER TABLE cp_modules ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        if (!$hasColumn('updated_at'))  $execSafe("ALTER TABLE cp_modules ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        // Optional observability columns (do not use for routing at runtime)
        $storePaths = true; // set false if you don't want to persist these
        if ($storePaths && !$hasColumn('default_path')) $execSafe("ALTER TABLE cp_modules ADD COLUMN default_path VARCHAR(255) NULL");
        if ($storePaths && !$hasColumn('layout'))       $execSafe("ALTER TABLE cp_modules ADD COLUMN layout VARCHAR(255) NULL");

        $hasModuleKeyCol = $hasColumn('module_key');
        $hasSlugCol      = $hasColumn('slug');

        if ($hasModuleKeyCol && !$hasIndex('uq_cp_modules_key')) {
            $execSafe("ALTER TABLE cp_modules ADD CONSTRAINT uq_cp_modules_key UNIQUE KEY (module_key)");
        } elseif ($hasSlugCol && !$hasIndex('uq_cp_modules_slug')) {
            $execSafe("ALTER TABLE cp_modules ADD CONSTRAINT uq_cp_modules_slug UNIQUE KEY (slug)");
        }

        $created = 0; $updated = 0;

        // --- Prepare UPSERT statements using LAST_INSERT_ID trick ---
        if ($hasModuleKeyCol) {
            $sql = "
                INSERT INTO cp_modules (module_key, name, version, is_active, default_path, layout, created_at, updated_at)
                VALUES (:key, :name, :ver, 1, :dpath, :layout, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    name = VALUES(name),
                    version = VALUES(version),
                    is_active = 1,
                    default_path = VALUES(default_path),
                    layout = VALUES(layout),
                    updated_at = VALUES(updated_at)
            ";
        } elseif ($hasSlugCol) {
            $sql = "
                INSERT INTO cp_modules (slug, name, version, is_active, default_path, layout, created_at, updated_at)
                VALUES (:key, :name, :ver, 1, :dpath, :layout, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    name = VALUES(name),
                    version = VALUES(version),
                    is_active = 1,
                    default_path = VALUES(default_path),
                    layout = VALUES(layout),
                    updated_at = VALUES(updated_at)
            ";
        } else {
            // Safety: if neither key exists, do nothing
            return ['created' => 0, 'updated' => 0];
        }

        $stmt = $pdo->prepare($sql);

        foreach (ModulesCatalog::all() as $slug => $meta) {
            // Normalize manifest meta
            $key     = (string)$slug;                         // use slug as logical key
            $name    = (string)($meta['name']    ?? $slug);
            $version = (string)($meta['version'] ?? '');
            $dpath   = isset($meta['default_path']) ? (string)$meta['default_path'] : null;
            $layout  = isset($meta['layout'])       ? (string)$meta['layout']       : null;

            $stmt->execute([
                ':key'    => $key,
                ':name'   => $name,
                ':ver'    => $version,
                ':dpath'  => $dpath,
                ':layout' => $layout,
            ]);

            // rowCount(): 1 insert, 2 update, 0 no-change (driver-dependent)
            $affected = $stmt->rowCount();
            if ($affected === 1) {
                $created++;
            } else {
                // Treat 0 and 2 as "updated" to keep counters monotonic
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}