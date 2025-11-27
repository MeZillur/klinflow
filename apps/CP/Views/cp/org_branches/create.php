<?php
/** @var array $org */
/** @var string $csrf */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="p-6">
  <h1 class="text-xl font-semibold mb-4">
    Add branch for <?= $h($org['name'] ?? '') ?>
  </h1>
  <p class="text-sm text-slate-500 mb-4">
    If you’re seeing this, routing + view resolution are OK.
  </p>
</div>