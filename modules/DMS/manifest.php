<?php
declare(strict_types=1);

return [
    // Core identity
    'slug'            => 'dms',
    'namespace'       => 'Modules\\DMS',
    'name'            => 'Distribution Management System',
    'version'         => '1.0.0',
    'icon'            => 'fa-solid fa-truck-fast',

    // Enablement flags
    'enabled'         => true,
    'default_enabled' => false,

    // Access control (supplier-first)
    'permissions'     => [
        'dms.view',
        'dms.manage.suppliers',
        'dms.manage.purchases',
        'dms.manage.sales',
        'dms.manage.inventory',
        'dms.manage.accounts',
        'dms.manage.reporting',
    ],

    // Tenant entry (what ModuleAccess/navs can link to)
    // {slug} is replaced at runtime with the org slug.
    'default_path'    => '/t/{slug}/apps/dms/dashboard',

    // Module shell to use for all module views.
    // Resolved by Shared\View relative to modules/DMS/Views/...
    'layout'          => 'Views/shared/layouts/shell.php',

    // Description
    'description'     => 'Integrated distribution management including suppliers, purchases, sales, inventory, accounting, and reporting for each tenant organization.',

    'author'          => [
        'name'  => 'KlinFlow Core Team',
        'email' => 'support@klinflow.com',
        'url'   => 'https://klinflow.com',
    ],
];