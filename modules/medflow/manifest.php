<?php
declare(strict_types=1);

return [
  'slug'         => 'medflow',
  'namespace'    => 'Modules\\medflow',
  'name'         => 'MedFlow (Pharmacy)',
  'version'      => '0.1.0',
  'enabled'      => true,
  'default_path' => '/t/{slug}/apps/medflow',
  'layout'       => __DIR__ . '/Views/shared/layouts/shell.php', // ← module shell
];