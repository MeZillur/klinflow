<?php
declare(strict_types=1);

return [
    // Identity
    'slug'      => 'bizflow',
    'namespace' => 'Modules\\BizFlow',
    'name'      => 'BizFlow',
    'icon'      => 'fa-diagram-project',
    'version'   => '1.0.0',

    // Enablement
    'enabled'         => true,
    'default_enabled' => false, // enable per-org in CP

    // BizFlow permissions (adjust to your ACL later)
    'permissions' => [
        'bizflow.view',
        'bizflow.quotes',
        'bizflow.orders',
        'bizflow.invoices',
        'bizflow.admin',
    ],

    // VERY IMPORTANT: always tenant-aware
    'default_path' => '/t/{slug}/apps/bizflow',

    // Use BizFlow's own shell (like POS/DMS)
    'layout' => __DIR__ . '/Views/shared/layouts/shell.php',

    // CP tiles / quick links (can expand later)
    'menu' => [
        ['label' => 'BizFlow Dashboard', 'path' => '/t/{slug}/apps/bizflow',        'perm' => 'bizflow.view'],
        ['label' => 'BizFlow Quotes',    'path' => '/t/{slug}/apps/bizflow/quotes', 'perm' => 'bizflow.quotes'],
    ],
];