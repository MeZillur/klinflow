<?php
declare(strict_types=1);

return [
  'slug'            => 'school',
  'namespace'       => 'Modules\\school',   // 👈 lowercase to match folder
  'name'            => 'SchoolFlow',
  'version'         => '1.0.0',
  'enabled'         => true,
  'default_enabled' => false,
  'permissions'     => ['school.view'],
  'default_path'    => '/t/{slug}/apps/school',

  // use module-owned shell
  'layout'          => __DIR__ . '/Views/shared/layouts/shell.php',
];