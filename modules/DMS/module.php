<?php
declare(strict_types=1);

/**
 * Discovery shim so the app can read manifest/nav/routes structure.
 * Keeps defaults sane if manifest is partially missing.
 */
$manifest = @include __DIR__ . '/manifest.php';
if (!is_array($manifest)) { $manifest = []; }

$slug  = (string)($manifest['slug']    ?? 'dms');
$name  = (string)($manifest['name']    ?? 'Distribution Management System');
$icon  = (string)($manifest['icon']    ?? 'fa-solid fa-truck-fast');
$ver   = (string)($manifest['version'] ?? '1.0.0');
$perm  = (array)  ($manifest['permissions'] ?? ['dms.view']);

// Optional: read nav for CP tiles/menus
$navFile = __DIR__ . '/nav.php';
$nav     = is_file($navFile) ? (include $navFile) : [];

/**
 * IMPORTANT:
 * - entry_path is the mount used by routers/tiles to enter the module.
 * - For tenant-aware links, always prefer /t/{slug}/apps/dms/... in menus/routes.
 */
return [
  'slug'            => $slug,
  'namespace'       => 'Modules\\DMS',
  'name'            => $name,
  'icon'            => $icon,
  'version'         => $ver,

  'enabled'         => (bool)($manifest['enabled'] ?? true),
  'default_enabled' => (bool)($manifest['default_enabled'] ?? false),

  // Keep permissions consistent with manifest to avoid drift
  'permissions'     => $perm,

  // For navigation builders (Control Panel tiles/menus)
  'menu'            => $nav,

  // Match the actual tenant mount (POS convention mirrored for DMS)
  // Use this as the base when constructing module URLs.
  'entry_path'      => '/t/{slug}/apps/' . $slug,
];