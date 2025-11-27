<?php
declare(strict_types=1);

// If the main front controller passed $__KF_MODULE__, use its ctx
$ctx = [
  'org'         => $__KF_MODULE__['org']         ?? [],
  'slug'        => $__KF_MODULE__['slug']        ?? '',
  'method'      => $__KF_MODULE__['method']      ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
  'tail'        => $__KF_MODULE__['tail']        ?? '',
  'module_key'  => $__KF_MODULE__['module_key']  ?? 'school',
  'module_dir'  => $__KF_MODULE__['module_dir']  ?? __DIR__,
  'module_base' => $__KF_MODULE__['module_base'] ?? '',
];

// Simple router: if you add more pages later, expand here
require __DIR__ . '/routes.php';