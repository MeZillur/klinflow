<?php
/**
 * BizFlow module manifest
 * - Auto-discoverable by ModulesCatalog
 * - Used by CP and tenant app shell
 */
return [
    'slug'                   => 'bizflow',
    'name'                   => 'BizFlow',
    'icon'                   => 'fa-solid fa-store',
    'version'                => '1.0.0',
    'enabled'                => true,
    'auto_include_on_org_create' => true,
    'description'            => 'Lightweight sales and purchase flow for small shops.',
    'entry_path'             => '/apps/bizflow',

    // Optional default nav items for tenants
    'menu' => [
        ['label' => 'Sales', 'href' => '/t/{slug}/apps/bizflow/sales', 'icon' => 'fa-cash-register'],
        ['label' => 'Purchases', 'href' => '/t/{slug}/apps/bizflow/purchases', 'icon' => 'fa-cart-shopping'],
        ['label' => 'Reports', 'href' => '/t/{slug}/apps/bizflow/reports', 'icon' => 'fa-chart-line'],
    ],
];