<?php
declare(strict_types=1);
/** @var string $title @var string $heading @var string $message @var array $actions @var string $module_base */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$heading = $heading ?? ($title ?? 'Coming soon');
$message = $message ?? 'This screen is under construction.';
?>
<div class="max-w-3xl mx-auto p-6">
  <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 text-center">
    <div class="text-5xl mb-3">🚧</div>
    <h1 class="text-2xl font-semibold mb-2"><?= $h($heading) ?></h1>
    <p class="text-gray-600 dark:text-gray-300 mb-6"><?= $h($message) ?></p>
    <div class="flex flex-wrap gap-2 justify-center">
      <?php foreach (($actions ?? []) as $a): ?>
        <a class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800"
           href="<?= $h($a['href'] ?? $module_base) ?>">
          <i class="fa-solid <?= $h($a['icon'] ?? 'fa-arrow-left') ?> mr-1"></i><?= $h($a['label'] ?? 'Back') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>