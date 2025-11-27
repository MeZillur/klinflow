<?php
declare(strict_types=1);

use Modules\school\Controllers\DashboardController;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Context from main front controller
$ctx = [
  'tenant_slug' => $__KF_MODULE__['slug']        ?? '',
  'module_key'  => $__KF_MODULE__['module_key']  ?? 'schoolflow',
  'module_dir'  => $__KF_MODULE__['module_dir']  ?? __DIR__,
  'module_base' => $__KF_MODULE__['module_base'] ?? '',
  'org'         => \App\Middleware\TenantResolver::ctx() ?? [],
];

// Tail after /t/{slug}/apps/schoolflow
$tail = trim((string)($__KF_MODULE__['tail'] ?? ''), '/');
$seg  = $tail === '' ? [] : explode('/', $tail);
$first = $seg[0] ?? '';

// ---- routes ----
if ($first === '' || $first === 'dashboard') {
    (new DashboardController())->index($ctx); exit;
}

// Future: add students, classes, fees, etc.
// e.g. if ($first === 'students') { ... }

http_response_code(404);
echo 'Not found';