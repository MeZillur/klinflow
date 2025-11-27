<?php
declare(strict_types=1);

namespace App\Services;

use Shared\DB;

/**
 * ModulesCatalog
 * - Scans /modules/module.php manifests
 * - Upserts into cp_modules by module_key (unique)
 * - Fixes past mistakes (e.g., mis-slugged rows)
 * - Idempotent and safe to call on every CP createForm render
 */
final class ModulesCatalog
{
    /** Root /modules path */
    private static function modulesRoot(): string
    {
        return defined('BASE_PATH') ? BASE_PATH.'/modules' : dirname(__DIR__, 2).'/modules';
    }

    /** Read all manifests found on disk */
    private static function readManifests(): array
    {
        $root = self::modulesRoot();
        if (!is_dir($root)) return [];

        $list = [];
        foreach (glob($root.'/*/*/module.php') as $file) {
            /** @var array|null $m */
            $m = @include $file;
            if (!is_array($m)) continue;

            $key = strtolower(trim((string)($m['slug'] ?? $m['key'] ?? '')));
            if ($key === '') continue;

            $list[$key] = [
                'module_key' => $key,
                'slug'       => $key,
                'name'       => (string)($m['title'] ?? $m['name'] ?? ucfirst($key)),
                'version'    => (string)($m['version'] ?? '1.0.0'),
                'is_active'  => (int)($m['enabled'] ?? 1) ? 1 : 0,
            ];
        }
        return $list;
    }

    /** Upsert a single row by module_key (unique). Returns affected ID (or existing). */
    private static function upsertModule(array $row): int
    {
        $pdo = DB::pdo();

        // Existing?
        $sel = $pdo->prepare("SELECT id FROM cp_modules WHERE module_key = ? LIMIT 1");
        $sel->execute([$row['module_key']]);
        $id = (int)$sel->fetchColumn();

        if ($id > 0) {
            $upd = $pdo->prepare("
                UPDATE cp_modules
                   SET slug = ?, name = ?, version = ?, is_active = ?, updated_at = NOW()
                 WHERE id = ?
                 LIMIT 1
            ");
            $upd->execute([$row['slug'], $row['name'], $row['version'], $row['is_active'], $id]);
            return $id;
        }

        $ins = $pdo->prepare("
            INSERT INTO cp_modules (module_key, slug, name, version, is_active, created_at, updated_at)
            VALUES (?,?,?,?,?, NOW(), NOW())
        ");
        $ins->execute([$row['module_key'], $row['slug'], $row['name'], $row['version'], $row['is_active']]);
        return (int)$pdo->lastInsertId();
    }

    /** Fix obvious historical mistakes (e.g., HotelFlow saved as POS) */
    private static function healKnownIssues(): void
    {
        $pdo = DB::pdo();

        // If we have a row named HotelFlow but module_key wrongly set to 'pos', correct it.
        $q = $pdo->query("SELECT id FROM cp_modules WHERE module_key='pos' AND LOWER(name) LIKE 'hotelflow%' LIMIT 1");
        $wrong = (int)$q->fetchColumn();
        if ($wrong > 0) {
            $pdo->prepare("UPDATE cp_modules SET module_key='hotelflow', slug='hotelflow', updated_at=NOW() WHERE id=?")
                ->execute([$wrong]);
        }
    }

    /** Public: run sync safely (no throw) */
    public static function syncSafe(): void
    {
        try { self::sync(); } catch (\Throwable $e) {
            // Don't crash the page; log instead
            if (class_exists(Logger::class) && method_exists(Logger::class, 'error')) {
                Logger::error('[ModulesCatalog] sync failed: '.$e->getMessage());
            } else {
                error_log('[ModulesCatalog] sync failed: '.$e->getMessage());
            }
        }
    }

    /** Core sync */
    public static function sync(): void
    {
        // 1) Read manifests on disk
        $manifests = self::readManifests();

        // 2) Ensure canonical 4 (fallback if manifests are missing)
        foreach ([
            'bhata'     => 'BhataFlow (Smart Brick Field)',
            'dms'       => 'Dealership Management System',
            'pos'       => 'Point of Sale (POS)',
            'hotelflow' => 'HotelFlow',
        ] as $k => $title) {
            if (!isset($manifests[$k])) {
                $manifests[$k] = [
                    'module_key' => $k,
                    'slug'       => $k,
                    'name'       => $title,
                    'version'    => '1.0.0',
                    'is_active'  => 1,
                ];
            }
        }

        // 3) Upsert all
        foreach ($manifests as $row) self::upsertModule($row);

        // 4) Heal historical mistakes
        self::healKnownIssues();
    }
}