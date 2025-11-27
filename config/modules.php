<?php
declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Catalog (all discoverable modules)
    | - Keys are canonical, lowercase, dashless or dashed (your choice, be consistent).
    | - Each module folder should contain a `manifest.php` that returns an array.
    |--------------------------------------------------------------------------
    */
    'catalog' => [
        'dms' => [
            // Optional metadata if you want to avoid reading the manifest on every request.
            // If omitted, the loader will read /modules/DMS/manifest.php.
            'path'   => __DIR__ . '/../modules/DMS/manifest.php',
            'title'  => 'Dealership Management',
            'aliases'=> ['DMS','dealership-management','dealership_management'],
        ],
        // 'pos'   => [...],
        // 'hms'   => [...],
        // 'bhata' => [...],
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Modules (globally allowed to be assigned to orgs)
    | - Must be a subset of keys in 'catalog'.
    |--------------------------------------------------------------------------
    */
    'enabled' => [
        'dms',
        // 'pos', 'hms', 'bhata',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Directories
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'base'         => __DIR__ . '/../modules',
        'manifestName' => 'manifest.php',   // <- make this explicit and consistent
    ],

    /*
    |--------------------------------------------------------------------------
    | Loader Options
    |--------------------------------------------------------------------------
    */
    'loader' => [
        // Normalize keys so 'DMS' / 'dealership-management' collapse to 'dms'
        'normalizer' => static function (string $k): string {
            return preg_replace('/[^a-z0-9]/', '', strtolower($k));
        },
        // Caching manifests is optional; set a TTL in seconds (0 = off)
        'cache_ttl' => 0,
        // Throw or log when a manifest is missing
        'strict'    => false,
    ],
];