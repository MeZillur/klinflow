<?php
declare(strict_types=1);
/** @var array $missing */  /** @var string $module_base */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-4xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-4" style="color:#228B22">Missing dms_account_map Keys</h1>

  <?php if (empty($missing)): ?>
    <div class="p-4 rounded-xl border text-emerald-700 bg-emerald-50">
      ✅ All required account map keys exist.
    </div>
  <?php else: ?>
    <ul class="list-disc pl-6 space-y-1 text-amber-700">
      <?php foreach ($missing as $m): ?>
        <li><?= $h($m['map_key'] ?? '') ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="mt-4">
    <a href="<?= $h(($module_base ?? '/apps/dms').'/reports/health') ?>"
       class="text-sm underline text-slate-600 hover:text-slate-900">
       ← Back to Health Dashboard
    </a>
  </div>
</div>
