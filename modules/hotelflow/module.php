<?php
declare(strict_types=1);

/**
 * Discovery shim so the app can read manifest/nav/routes structure.
 * Used by the kernel to list modules, build menus, and resolve entry paths.
 */

$manifest = @include __DIR__ . '/manifest.php';
if (!is_array($manifest)) { $manifest = []; }

$slug = (string)($manifest['slug'] ?? 'hotelflow');
$name = (string)($manifest['name'] ?? 'HotelFlow');
$icon = (string)($manifest['icon'] ?? 'fa-solid fa-hotel');
$ver  = (string)($manifest['version'] ?? '0.1.0');

// Optional module menu (tiles/left-nav hints)
$navFile = __DIR__ . '/nav.php';
$nav     = is_file($navFile) ? (include $navFile) : [];

return [
    'slug'            => $slug,
    'namespace'       => (string)($manifest['namespace'] ?? 'Modules\\hotelflow'),
    'name'            => $name,
    'icon'            => $icon,
    'version'         => $ver,
    'enabled'         => (bool)($manifest['enabled'] ?? true),
    'default_enabled' => (bool)($manifest['default_enabled'] ?? false),

    // Permissions your app can enforce globally
    'permissions'     => ['hms.view','hms.manage'],

    // For navigation builders (CP tiles / tenant app switcher)
    'menu'            => $nav,

    // IMPORTANT: matches the actual mount (/t/{slug}/apps/hotelflow)
    // Kernel typically prefixes with /t/{slug} when rendering tenant links.
    'entry_path'      => '/apps/' . $slug,
];