<?php
/**
 * modules/hotelflow/manifest.php
 *
 * Module manifest for HotelFlow (HMS)
 * Used by modules.php, index.php, and routes.php
 * to determine mount paths, namespace, and shell layout.
 */

return [
    // Module identity
    'slug'        => 'hotelflow',                   // folder + key under /t/{slug}/apps/{key}
    'namespace'   => 'Modules\\hotelflow',          // PSR-4 base namespace
    'name'        => 'HotelFlow',                   // Human-friendly module name
    'enabled'     => true,                          // Module activation flag

    // Default route mount
    'default_path' => '/t/{slug}/apps/hotelflow',   // canonical tenant path

    // Optional layout shell (used by Shared\View)
    // keep absolute path — consistent with BaseController wrapping rules
    'layout'      => __DIR__ . '/Views/shared/layouts/shell.php',
];