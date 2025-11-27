<?php
declare(strict_types=1);

// Hard facts about this module
$MODULE_KEY = 'medflow';

// Current request path (no query string)
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Extract slug + tail relative to “…/apps/medflow”
$slug = '';
$tail = '';
if (preg_match('~^/t/([^/]+)/apps/'.$MODULE_KEY.'(?P<tail>/.*)?$~i', $uri, $m)) {
    $slug = $m[1];
    $tail = isset($m['tail']) ? ltrim($m['tail'], '/') : '';
} else {
    http_response_code(404);
    echo 'Module base not found for MedFlow.'; exit;
}

// Base like: /t/{slug}/apps/medflow   (use the same casing as in URL)
$moduleBase = '/t/'.$slug.'/apps/'.$MODULE_KEY;

// Tenant ctx (best effort)
$tenantCtx = \App\Middleware\TenantResolver::ctx() ?: [];

$ctx = [
  'tenant_slug' => $slug,
  'module_key'  => $MODULE_KEY,
  'module_dir'  => __DIR__,
  'module_base' => $moduleBase,
  'org'         => $tenantCtx,
];

// hand off to router; DO NOT recompute $tail there
require __DIR__ . '/routes.php';