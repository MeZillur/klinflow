<?php
declare(strict_types=1);
/** @var string $module_base */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<nav class="p-3 space-y-1 text-sm">
  <a class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
     href="<?= $h($module_base) ?>">
    <i class="fa-solid fa-house"></i> <span>Dashboard</span>
  </a>
  <!-- Add more links here later -->
</nav>