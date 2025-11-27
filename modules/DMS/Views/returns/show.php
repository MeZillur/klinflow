<?php declare(strict_types=1);
/** @var array $hdr @var array $lines @var string $module_base */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$posted = !empty($hdr['posted_at']);
?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold">Return <?= $h($hdr['return_no'] ?: ('#'.$hdr['id'])) ?></h1>
      <div class="text-sm text-slate-600"><?= $h($hdr['customer_name'] ?: '') ?> • <?= $h($hdr['return_date'] ?: '') ?></div>
    </div>
    <div>
      <?php if ($posted): ?>
        <form method="post" action="<?= $h($module_base) ?>/returns/unpost">
          <input type="hidden" name="return_id" value="<?= (int)$hdr['id'] ?>">
          <button class="px-3 py-2 rounded-lg border">Unpost</button>
        </form>
      <?php else: ?>
        <form method="post" action="<?= $h($module_base) ?>/returns/post">
          <input type="hidden" name="return_id" value="<?= (int)$hdr['id'] ?>">
          <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white">Post</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="rounded-xl border overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left">SKU</th>
          <th class="px-3 py-2 text-left">Product</th>
          <th class="px-3 py-2 text-right">Qty</th>
          <th class="px-3 py-2 text-left">Note</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $r): ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= $h($r['sku'] ?? '') ?></td>
            <td class="px-3 py-2"><?= $h($r['product_name'] ?? '') ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($r['qty'] ?? 0),2) ?></td>
            <td class="px-3 py-2"><?= $h($r['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lines): ?>
          <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No lines.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>