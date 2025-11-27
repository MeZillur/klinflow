<?php
declare(strict_types=1);

/**
 * config/app.php
 * - Core app settings
 * - Auto-discovers modules from /modules/** or /Modules/** with module.php or manifest.php
 * - Emits:
 *     ['modules']           => normalized meta, keyed by slug
 *     ['apps']              => compact catalog for CP UI, keyed by slug
 *     ['auto_enable_apps']  => slugs default-enabled for new orgs
 *
 * Expected module manifest (module.php or manifest.php) should return an array like:
 *   return [
 *     'slug'            => 'dms',
 *     'namespace'       => 'Modules\\DMS',
 *     'name'            => 'Dealership Management',
 *     'icon'            => 'fa-solid fa-warehouse',
 *     'version'         => '1.0.0',
 *     'enabled'         => true,
 *     'default_enabled' => true,       // <-- included automatically for new orgs
 *     'permissions'     => ['dms.view'],
 *     'menu'            => [
 *       ['label' => 'Dashboard', 'path' => '/dms', 'perm' => 'dms.view'],
 *     ],
 *   ];
 */

$cfg = [
    'name'        => getenv('APP_NAME') ?: 'KlinFlow',
    'env'         => getenv('APP_ENV') ?: 'production',
    'debug'       => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'timezone'    => getenv('APP_TZ') ?: 'Asia/Dhaka',
    'force_https' => filter_var(getenv('FORCE_HTTPS') ?: false, FILTER_VALIDATE_BOOLEAN),

    'url' => [
        'base'   => (
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://'
        ) . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        'assets' => '/assets',
    ],

    'pagination' => [
        'per_page' => 10,
    ],
];

/* -------------------------------------------------------------------------
 | Robust auto-discovery of modules
 |------------------------------------------------------------------------- */
$ROOT = realpath(__DIR__ . '/..') ?: dirname(__DIR__);     // project root
$SCAN_DIRS = array_values(array_unique(array_filter([
    is_dir($ROOT . '/modules') ? $ROOT . '/modules' : null,
    is_dir($ROOT . '/Modules') ? $ROOT . '/Modules' : null, // Windows/macOS devs sometimes use Caps
])));

$modules = [];   // full normalized meta keyed by slug
$apps    = [];   // compact catalog keyed by slug
$auto    = [];   // slugs to enable by default

/**
 * Normalize a module meta array safely.
 */
$normalize = static function (array $m): array {
    // helper that collapses 'DMS', 'dealership-management', 'Dealership Management' → 'dealershipmanagement'
    $norm = static fn(string $s): string => preg_replace('/[^a-z0-9]/i', '', strtolower($s));

    // 1) decide the raw slug
    $rawSlug = (string)($m['slug'] ?? '');
    $rawKey  = (string)($m['key']  ?? '');            // support manifests that use 'key'
    $name    = (string)($m['name'] ?? '');
    $ns      = (string)($m['namespace'] ?? '');

    if ($rawSlug === '') {
        if ($rawKey !== '') {
            $rawSlug = strtolower($rawKey);          // prefer 'key' when slug absent
        } elseif ($name !== '') {
            $rawSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        } elseif ($ns !== '') {
            $rawSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $ns));
        }
        $rawSlug = trim($rawSlug, '-');
    }

    // 2) alias resolution: if aliases present and any alias normalizes to a known canonical, use that
    $aliases = array_map('strval', $m['aliases'] ?? []);
    $aliNorm = array_map($norm, $aliases);

    $slug = $rawSlug;

    // If someone shipped only a Name and no slug/key, raw may be 'dealership-management'
    // Prefer canonical short slug when aliases indicate it (e.g., 'dms')
    if ($rawSlug !== '') {
        $rawNorm = $norm($rawSlug);
        // If any alias normalizes to 'dms', force canonical 'dms'
        if (in_array('dms', $aliNorm, true) || in_array('dms', array_map($norm, ['dms']), true)) {
            // When a short alias exists, pick the short one
            if (in_array('dms', array_map($norm, $aliases), true) || $norm($rawSlug) === 'dms' || $norm($rawKey) === 'dms') {
                $slug = 'dms';
            }
        }
        // Generic rule: if key normalizes to something shorter than the name-derived slug, prefer key
        if ($rawKey !== '' && strlen($norm($rawKey)) < strlen($rawNorm)) {
            $slug = strtolower($rawKey);
        }
    }

    // Final guard
    $slug = $slug ?: ($rawKey ?: ($rawSlug ?: 'module'));

    $title = (string)($m['name'] ?? ($slug ?: 'Unknown Module'));

    return [
        'slug'            => $slug,
        'namespace'       => $ns,
        'name'            => $title,
        'icon'            => (string)($m['icon'] ?? 'fa-solid fa-puzzle-piece'),
        'version'         => (string)($m['version'] ?? '0.0.0'),
        'enabled'         => (bool)  ($m['enabled'] ?? true),
        'default_enabled' => (bool)  ($m['default_enabled'] ?? false),
        'permissions'     => array_values(array_unique(array_map('strval', $m['permissions'] ?? []))),
        'menu'            => is_array($m['menu'] ?? null) ? $m['menu'] : [],
        '_raw'            => $m,
    ];
};

/**
 * Load a manifest file and return array meta or [].
 */
$loadManifest = static function (string $file): array {
    if (!is_readable($file)) return [];
    try {
        $ret = (static function (string $f) {
            /** @noinspection PhpIncludeInspection */
            $data = include $f;
            return is_array($data) ? $data : [];
        })($file);
        return $ret ?: [];
    } catch (\Throwable $e) {
        return [];
    }
};

/**
 * Scan a directory for module manifests.
 */
$scanDir = static function (string $dir) use ($loadManifest, $normalize, &$modules, &$apps, &$auto): void {
    // Accept either module.php or manifest.php
    $patterns = [
        rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'module.php',
        rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'manifest.php',
    ];

    $files = [];
    foreach ($patterns as $pat) {
        $hits = glob($pat) ?: [];
        if ($hits) $files = array_merge($files, $hits);
    }

    foreach (array_unique($files) as $file) {
        $meta = $loadManifest($file);
        if (!$meta) continue;

        $mod = $normalize($meta);
        $slug = $mod['slug'];
        if ($slug === '') continue;

        // de-dupe by slug; prefer first seen (or overwrite, your call)
        $modules[$slug] = $mod;

        // compact app card for CP
        $apps[$slug] = [
            'title'           => $mod['name'],
            'icon'            => $mod['icon'],
            'module'          => $mod['namespace'] ?: $slug,
            'slug'            => $slug,
            'default_enabled' => $mod['default_enabled'],
            'permissions'     => $mod['permissions'],
            'entry_path'      => '/' . $slug,   // default landing route
        ];

        if (!empty($mod['default_enabled'])) {
            $auto[] = $slug;
        }
    }
};

foreach ($SCAN_DIRS as $dir) {
    $scanDir($dir);
}

// Final exposure for controllers (e.g., OrganizationsController)
$cfg['modules']          = $modules;  // normalized, keyed by slug
$cfg['apps']             = $apps;     // compact cards
$cfg['auto_enable_apps'] = array_values(array_unique($auto));

return $cfg;