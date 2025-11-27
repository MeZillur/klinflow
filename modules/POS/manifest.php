<?php
declare(strict_types=1);

return [
    // Identity
    'slug'      => 'pos',
    'namespace' => 'Modules\\POS',
    'name'      => 'Point of Sale',
    'icon'      => 'fa-cash-register',
    'version'   => '1.0.0',

    // Enablement
    'enabled'         => true,
    'default_enabled' => false, // enable per-org in CP

    // POS permissions (align with your ACL)
    'permissions' => [
        'pos.view',
        'pos.sell',
        'pos.product.crud',
        'pos.customer.crud',
        'pos.report.view',
        'pos.admin',
    ],

    // Where "Open module" buttons should land
    // (routes handle '' and 'dashboard')
    'default_path' => '/t/{slug}/apps/pos',

    // Use the module's own shell (absolute path so it never mis-resolves)
    'layout' => __DIR__ . '/Views/shared/layouts/shell.php',

    // CP tiles / quick links
    'menu' => [
        ['label' => 'POS Dashboard', 'path' => '/t/{slug}/apps/pos', 'perm' => 'pos.view'],
    ],
];