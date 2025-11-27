<?php
declare(strict_types=1);

namespace App\Services;

use Shared\DB;

final class ModuleAccess
{
    /* ---------------------------------------------------------------------
     * Basic utilities
     * ------------------------------------------------------------------- */

    /** Sanitize a module key from URL/user input (lowercase, [a-z0-9_-]) */
    public static function sanitizeKey(?string $k): string
    {
        $k = strtolower(trim((string)$k));
        return preg_replace('/[^a-z0-9_-]/', '', $k) ?: '';
    }

    /** Resolve org_id by slug; returns null if not found */
    public static function orgIdBySlug(string $slug): ?int
    {
        $pdo = DB::pdo();
        $q = $pdo->prepare("SELECT id FROM cp_organizations WHERE slug = ? LIMIT 1");
        $q->execute([$slug]);
        $val = $q->fetchColumn();
        return $val !== false ? (int)$val : null;
    }

    /** Is a module enabled for an org? (joins cp_org_modules → cp_modules) */
    public static function isEnabledForOrg(int $orgId, string $key): bool
    {
        $key = self::sanitizeKey($key);
        if ($key === '') return false;

        $pdo = DB::pdo();
        $q = $pdo->prepare("
            SELECT 1
            FROM cp_org_modules om
            JOIN cp_modules m ON m.id = om.module_id
            WHERE om.org_id = ? AND m.module_key = ? AND om.enabled = 1 AND m.is_active = 1
            LIMIT 1
        ");
        $q->execute([$orgId, $key]);
        return (bool)$q->fetchColumn();
    }

    /** File system dir of a module (supports DMS/dms, etc.) */
    public static function moduleDir(string $key): ?string
    {
        $key = self::sanitizeKey($key);
        $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $candidates = [
            $root . '/modules/' . strtoupper($key),
            $root . '/modules/' . ucfirst($key),
            $root . '/modules/' . $key,
        ];
        foreach ($candidates as $dir) {
            if (is_dir($dir)) return $dir;
        }
        return null;
    }

    /** Load a module's nav.php (array structure decided by the module) */
    public static function loadModuleNav(string $key): array
    {
        $dir = self::moduleDir($key);
        if (!$dir) return [];
        $nav = $dir . '/nav.php';
        if (!is_file($nav)) return [];
        $items = @require $nav;
        return is_array($items) ? $items : [];
    }

    /** Check if a table has a given column (MySQL/MariaDB) */
    private static function hasColumn(string $table, string $column): bool
    {
        try {
            $pdo = DB::pdo();
            $sql = "
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :t
                  AND COLUMN_NAME = :c
                LIMIT 1
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':t' => $table, ':c' => $column]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable $e) {
            return false; // fail closed if information_schema unavailable
        }
    }

    /* ---------------------------------------------------------------------
     * Module enablement and defaults
     * ------------------------------------------------------------------- */

    /** List of enabled module keys for an org slug (sanitized keys) */
    public static function enabledModulesFor(string $orgSlug): array
    {
        $orgId = self::orgIdBySlug($orgSlug);
        if (!$orgId) return [];

        $pdo = DB::pdo();

        // Build ORDER BY depending on schema
        $order = self::hasColumn('cp_modules', 'sort_order')
            ? "COALESCE(m.sort_order, 999), m.name, m.module_key"
            : "m.name, m.module_key";

        $sql = "
            SELECT m.module_key
            FROM cp_org_modules om
            JOIN cp_modules m ON m.id = om.module_id
            WHERE om.org_id = ? AND om.enabled = 1 AND m.is_active = 1
            ORDER BY {$order}
        ";
        $q = $pdo->prepare($sql);
        $q->execute([$orgId]);

        $rows = $q->fetchAll(\PDO::FETCH_COLUMN);
        $out = [];
        foreach ($rows as $k) {
            $k = self::sanitizeKey((string)$k);
            if ($k !== '') $out[] = $k;
        }
        return array_values(array_unique($out));
    }

    /**
     * Default module for an org (used for post-login redirect).
     * Strategy:
     *  1) If cp_organizations.default_module column exists, and value is enabled → use it
     *  2) Else pick the first enabled module by a preferred priority list
     *  3) Else first enabled (by enabledModulesFor order)
     */
    public static function defaultModuleFor(string $orgSlug): ?string
    {
        $orgId = self::orgIdBySlug($orgSlug);
        if (!$orgId) return null;

        // Enabled modules list (used by all branches)
        $enabled = self::enabledModulesFor($orgSlug);

        // 1) Org-configured default if column exists
        if (!empty($enabled) && self::hasColumn('cp_organizations', 'default_module')) {
            try {
                $pdo = DB::pdo();
                $def = $pdo->prepare("SELECT default_module FROM cp_organizations WHERE id = ? LIMIT 1");
                $def->execute([$orgId]);
                $configured = self::sanitizeKey((string)$def->fetchColumn());
                if ($configured !== '' && in_array($configured, $enabled, true)) {
                    return $configured;
                }
            } catch (\Throwable $e) {
                // ignore; proceed to priority/default
            }
        }

        if (!$enabled) return null;

        // 2) Priority order (tweak as needed)
        $priority = ['pos','dms','hotelflow','bhataflow','edu'];
        foreach ($priority as $p) {
            if (in_array($p, $enabled, true)) return $p;
        }

        // 3) First enabled
        return $enabled[0] ?? null;
    }

    /* ---------------------------------------------------------------------
     * Sidebar data (merged model when a module doesn't provide its own)
     * ------------------------------------------------------------------- */

    /**
     * Build a merged sidebar model for a tenant scope.
     * Return shape used by UiResolver::navData() fallback:
     * [
     *   'core'    => ['section'=>'Core','items'=>[...]],
     *   'modules' => [
     *      ['key'=>'dms','label'=>'DMS','icon'=>'fa-...','section'=>'Modules','items'=>[...]],
     *      ...
     *   ]
     * ]
     */
    public static function sidenavFor(string $orgSlug): array
    {
        $orgId = self::orgIdBySlug($orgSlug) ?? 0;
        $pdo   = DB::pdo();

        // CORE section (kept minimal since module dashboards are primary)
        $core = [
            'section' => 'Core',
            'items'   => [
                ['label' => 'Users',    'href' => "/t/{$orgSlug}/users",    'icon' => 'fa-users'],
                ['label' => 'Settings', 'href' => "/t/{$orgSlug}/settings", 'icon' => 'fa-gear'],
            ],
        ];

        // ORDER BY depends on schema
        $order = self::hasColumn('cp_modules', 'sort_order')
            ? "COALESCE(m.sort_order, 999), m.name, m.module_key"
            : "m.name, m.module_key";

        $sql = "
            SELECT m.module_key,
                   COALESCE(m.name, m.slug, m.module_key) AS label,
                   COALESCE(m.icon, 'fa-puzzle-piece')     AS icon
            FROM cp_org_modules om
            JOIN cp_modules m ON m.id = om.module_id
            WHERE om.org_id = ? AND om.enabled = 1 AND m.is_active = 1
            ORDER BY {$order}
        ";
        $q = $pdo->prepare($sql);
        $q->execute([$orgId]);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC);

        $mods = [];
        foreach ($rows as $r) {
            $key = self::sanitizeKey($r['module_key'] ?? '');
            if ($key === '') continue;

            // Try module-defined nav
            $def = self::loadModuleNav($key);

            // Normalized fallback if module has no nav.php
            if (!is_array($def) || empty($def)) {
                $def = [
                    'key'   => $key,
                    'label' => ucfirst($key),
                    'icon'  => $r['icon'] ?: 'fa-puzzle-piece',
                    'items' => [
                        ['label' => ucfirst($key).' Dashboard', 'href' => "/t/{$orgSlug}/{$key}"],
                    ],
                ];
            }

            // Fill placeholders and children
            $normalize = function (array $item) use ($orgSlug): array {
                $href = (string)($item['href'] ?? '#');
                $href = str_replace(
                    ['{slug}','{org}','{:base}', ':base'],
                    [$orgSlug,$orgSlug,"/t/{$orgSlug}", "/t/{$orgSlug}"],
                    $href
                );
                $item['href'] = $href;

                if (!empty($item['children']) && is_array($item['children'])) {
                    $kids = [];
                    foreach ($item['children'] as $c) {
                        $cHref = (string)($c['href'] ?? '#');
                        $cHref = str_replace(
                            ['{slug}','{org}','{:base}', ':base'],
                            [$orgSlug,$orgSlug,"/t/{$orgSlug}", "/t/{$orgSlug}"],
                            $cHref
                        );
                        $c['href'] = $cHref;
                        $kids[] = $c;
                    }
                    $item['children'] = $kids;
                }
                return $item;
            };

            $items = [];
            foreach ((array)($def['items'] ?? []) as $it) {
                $items[] = $normalize($it);
            }

            $mods[] = [
                'key'     => $key,
                'label'   => (string)($def['label'] ?? $r['label']),
                'icon'    => (string)($def['icon']  ?? $r['icon'] ?? 'fa-puzzle-piece'),
                'section' => 'Modules',
                'items'   => $items,
            ];
        }

        return ['core' => $core, 'modules' => $mods];
    }
}