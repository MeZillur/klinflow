<?php
declare(strict_types=1);

namespace App\Services;

use Shared\DB;
use Throwable;

/**
 * ModulesCatalog
 * -------------------------------------------------------------
 * - Scans /modules/<Module>/module.php or manifest.php
 * - Upserts into cp_modules by module_key (unique)
 * - Handles auto_include_on_org_create when available
 * - Heals common mis-slugged rows (e.g., HotelFlow as POS)
 * - Idempotent: safe to call every page load or boot
 * -------------------------------------------------------------
 */
final class ModulesCatalog
{
    /** Root /modules path */
    private static function modulesRoot(): string
    {
        return defined('BASE_PATH')
            ? BASE_PATH . '/modules'
            : dirname(__DIR__, 2) . '/modules';
    }

    /* -------------------------------------------------------------
     * Helpers: schema checks
     * ------------------------------------------------------------- */

    /** Does a table exist? */
    private static function hasTable(\PDO $pdo, string $table): bool
    {
        try {
            $q = $pdo->prepare("
                SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                LIMIT 1
            ");
            $q->execute([$table]);
            return (bool)$q->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /** Does a column exist? */
    private static function hasColumn(\PDO $pdo, string $table, string $col): bool
    {
        try {
            $q = $pdo->prepare("
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                LIMIT 1
            ");
            $q->execute([$table, $col]);
            return (bool)$q->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /* -------------------------------------------------------------
     * Read manifests from disk
     * ------------------------------------------------------------- */

    private static function readManifests(): array
    {
        $root = self::modulesRoot();
        if (!is_dir($root)) return [];

        $list = [];

        // Support both module.php and manifest.php (one level deep)
        $candidates = array_merge(
            glob($root . '/*/module.php') ?: [],
            glob($root . '/*/manifest.php') ?: []
        );

        foreach ($candidates as $file) {
            /** @var array|null $m */
            $m = @include $file;
            if (!is_array($m)) continue;

            $key = strtolower(trim((string)($m['slug'] ?? $m['key'] ?? '')));
            if ($key === '') continue;

            $list[$key] = [
                'module_key'                 => $key,
                'slug'                       => $key,
                'name'                       => (string)($m['title'] ?? $m['name'] ?? ucfirst($key)),
                'version'                    => (string)($m['version'] ?? '1.0.0'),
                'is_active'                  => (int)(!empty($m['enabled']) ? 1 : 0),
                'auto_include_on_org_create' => (int)(!empty($m['auto_include_on_org_create']) ? 1 : 0),
                'icon'                       => (string)($m['icon'] ?? ''),
            ];
        }

        return $list;
    }

    /* -------------------------------------------------------------
     * Upsert one module (schema-aware, no fatal errors)
     * ------------------------------------------------------------- */

    private static function upsertModule(array $row): int
    {
        $pdo = DB::pdo();

        if (!self::hasTable($pdo, 'cp_modules')) {
            // Table missing → skip silently
            return 0;
        }

        // Check available columns
        $has = [
            'module_key'  => self::hasColumn($pdo, 'cp_modules', 'module_key'),
            'slug'        => self::hasColumn($pdo, 'cp_modules', 'slug'),
            'name'        => self::hasColumn($pdo, 'cp_modules', 'name'),
            'version'     => self::hasColumn($pdo, 'cp_modules', 'version'),
            'is_active'   => self::hasColumn($pdo, 'cp_modules', 'is_active'),
            'auto_inc'    => self::hasColumn($pdo, 'cp_modules', 'auto_include_on_org_create'),
            'icon'        => self::hasColumn($pdo, 'cp_modules', 'icon'),
            'updated_at'  => self::hasColumn($pdo, 'cp_modules', 'updated_at'),
            'created_at'  => self::hasColumn($pdo, 'cp_modules', 'created_at'),
        ];

        // Fallback lookup column
        $lookupCol = $has['module_key'] ? 'module_key' : ($has['slug'] ? 'slug' : null);
        if ($lookupCol === null) return 0;

        $lookupVal = $has['module_key'] ? $row['module_key'] : $row['slug'];

        // Lookup existing ID
        $sel = $pdo->prepare("SELECT id FROM cp_modules WHERE {$lookupCol} = ? LIMIT 1");
        $sel->execute([$lookupVal]);
        $id = (int)$sel->fetchColumn();

        /* ---------- UPDATE path ---------- */
        if ($id > 0) {
            $set = [];
            $vals = [];

            if ($has['slug'])       { $set[] = 'slug=?';       $vals[] = $row['slug']; }
            if ($has['name'])       { $set[] = 'name=?';       $vals[] = $row['name']; }
            if ($has['version'])    { $set[] = 'version=?';    $vals[] = $row['version']; }
            if ($has['is_active'])  { $set[] = 'is_active=?';  $vals[] = $row['is_active']; }
            if ($has['auto_inc'])   { $set[] = 'auto_include_on_org_create=?'; $vals[] = $row['auto_include_on_org_create']; }
            if ($has['icon'])       { $set[] = 'icon=?';       $vals[] = $row['icon']; }
            if ($has['updated_at']) { $set[] = 'updated_at=NOW()'; }

            if ($set) {
                $sql = "UPDATE cp_modules SET " . implode(',', $set) . " WHERE id=? LIMIT 1";
                $upd = $pdo->prepare($sql);
                $upd->execute(array_merge($vals, [$id]));
            }
            return $id;
        }

        /* ---------- INSERT path ---------- */
        $cols = [];
        $vals = [];
        $args = [];

        if ($has['module_key']) { $cols[] = 'module_key'; $vals[] = '?'; $args[] = $row['module_key']; }
        if ($has['slug'])       { $cols[] = 'slug';       $vals[] = '?'; $args[] = $row['slug']; }
        if ($has['name'])       { $cols[] = 'name';       $vals[] = '?'; $args[] = $row['name']; }
        if ($has['version'])    { $cols[] = 'version';    $vals[] = '?'; $args[] = $row['version']; }
        if ($has['is_active'])  { $cols[] = 'is_active';  $vals[] = '?'; $args[] = $row['is_active']; }
        if ($has['auto_inc'])   { $cols[] = 'auto_include_on_org_create'; $vals[] = '?'; $args[] = $row['auto_include_on_org_create']; }
        if ($has['icon'])       { $cols[] = 'icon';       $vals[] = '?'; $args[] = $row['icon']; }
        if ($has['created_at']) { $cols[] = 'created_at'; $vals[] = 'NOW()'; }
        if ($has['updated_at']) { $cols[] = 'updated_at'; $vals[] = 'NOW()'; }

        if ($cols) {
            $sql = "INSERT INTO cp_modules (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            $ins = $pdo->prepare($sql);
            $ins->execute($args);
            return (int)$pdo->lastInsertId();
        }

        return 0;
    }

    /* -------------------------------------------------------------
     * Heal known mistakes (legacy)
     * ------------------------------------------------------------- */
    private static function healKnownIssues(): void
    {
        $pdo = DB::pdo();
        try {
            $q = $pdo->query("
                SELECT id
                FROM cp_modules
                WHERE module_key='pos' AND LOWER(name) LIKE 'hotelflow%'
                LIMIT 1
            ");
            $wrong = (int)$q->fetchColumn();
            if ($wrong > 0) {
                $pdo->prepare("
                    UPDATE cp_modules
                       SET module_key='hotelflow',
                           slug='hotelflow',
                           updated_at=NOW()
                     WHERE id=? LIMIT 1
                ")->execute([$wrong]);
            }
        } catch (Throwable) {
            // ignore errors safely
        }
    }

    /* -------------------------------------------------------------
     * Public sync methods
     * ------------------------------------------------------------- */

    /** Safe wrapper (no-throw) */
    public static function syncSafe(): void
    {
        try {
            self::sync();
        } catch (Throwable $e) {
            error_log('[ModulesCatalog] sync failed: ' . $e->getMessage());
        }
    }

    /** Core sync logic */
    public static function sync(): void
    {
        $manifests = self::readManifests();

        // Ensure canonical system modules always exist
        foreach ([
            'bhata'     => 'BhataFlow (Smart Brick Field)',
            'dms'       => 'Dealership Management System',
            'pos'       => 'Point of Sale (POS)',
            'hotelflow' => 'HotelFlow',
        ] as $k => $title) {
            if (!isset($manifests[$k])) {
                $manifests[$k] = [
                    'module_key'                 => $k,
                    'slug'                       => $k,
                    'name'                       => $title,
                    'version'                    => '1.0.0',
                    'is_active'                  => 1,
                    'auto_include_on_org_create' => 1,
                    'icon'                       => '',
                ];
            }
        }

        // Perform upserts
        foreach ($manifests as $row) {
            self::upsertModule($row);
        }

        // Heal known naming issues
        self::healKnownIssues();
    }
}