<?php
declare(strict_types=1);
/** @var string $module_base */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $module_base ?? '/apps/medflow';
?>
<div class="p-6 space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold"><i class="fa-solid fa-rotate-left"></i> New Sales Return</h1>
    <a href="<?= $h($base.'/sales/returns') ?>" class="text-[#228B22] hover:underline">
      ← Back to Returns
    </a>
  </div>

  <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center text-gray-600 dark:text-gray-300">
    <div class="text-lg font-medium mb-2">We’re wiring the return form next.</div>
    <div class="text-sm">You’ll be able to search a sale, pick the items to return, set quantities, and issue a refund/credit note.</div>
  </div>
</div>