<?php
declare(strict_types=1);

return [
  'slug'            => 'bhata',                     // matches URL /apps/bhata
  'namespace'       => 'Modules\\Bhata',            // matches folder modules/Bhata
  'name'            => 'BhataFlow (Smart Brick Field)',
  'version'         => '1.0.0',
  'enabled'         => true,
  'default_enabled' => false,
  'permissions'     => ['bhata.view'],
  'default_path'    => '/t/{slug}/apps/bhata',

  // Module-owned shell
  'layout'          => __DIR__ . '/Views/shared/layouts/shell.php',
];