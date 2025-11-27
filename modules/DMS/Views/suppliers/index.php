<?php declare(strict_types=1);
/** @var array $rows */
/** @var string $module_base */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$rows = $rows ?? [];
$base = $module_base ?? '';
$ok   = $_SESSION['flash_ok']  ?? null; unset($_SESSION['flash_ok']);
$err  = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
?>
<div class="flex items-center justify-between mb-5">
  <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Suppliers</h1>
  <a href="<?= $h($base.'/suppliers/create') ?>"
     class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
    <i class="fa-solid fa-user-plus"></i><span>New Supplier</span>
  </a>
</div>

<?php if ($ok): ?>
  <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2">
    <i class="fa-solid fa-circle-check"></i><?= $h($ok) ?>
  </div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="mb-4 rounded-lg bg-rose-50 text-rose-800 px-4 py-3 text-sm flex items-center gap-2">
    <i class="fa-solid fa-triangle-exclamation"></i><?= $h($err) ?>
  </div>
<?php endif; ?>

<div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50 dark:bg-slate-800/60">
      <tr class="text-slate-600 dark:text-slate-300">
        <th class="px-3 py-3 text-left font-semibold">Name</th>
        <th class="px-3 py-3 text-left font-semibold">Phone</th>
        <th class="px-3 py-3 text-left font-semibold">Email</th>
        <th class="px-3 py-3 text-left font-semibold">Status</th>
        <th class="px-3 py-3 text-right font-semibold">Opening</th>
        <th class="px-3 py-3 text-right font-semibold">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
      <?php foreach ($rows as $r): ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
          <td class="px-3 py-3">
            <div class="font-medium text-slate-900 dark:text-slate-100"><?= $h($r['name'] ?? '') ?></div>
            <?php if (!empty($r['address'])): ?>
              <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[30ch]">
                <?= $h($r['address']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td class="px-3 py-3 text-slate-700 dark:text-slate-300"><?= $h($r['phone'] ?? '') ?></td>
          <td class="px-3 py-3">
            <?php if (!empty($r['email'])): ?>
              <a class="text-sky-600 hover:underline dark:text-sky-400" href="mailto:<?= $h($r['email']) ?>">
                <?= $h($r['email']) ?>
              </a>
            <?php else: ?>
              <span class="text-slate-400">—</span>
            <?php endif; ?>
          </td>
          <td class="px-3 py-3">
            <?php $st = strtolower((string)($r['status'] ?? 'active')); ?>
            <?php if ($st === 'inactive'): ?>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200">inactive</span>
            <?php else: ?>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">active</span>
            <?php endif; ?>
          </td>
          <td class="px-3 py-3 text-right font-mono text-slate-900 dark:text-slate-100">
            <?= number_format((float)($r['opening_balance'] ?? 0), 2) ?>
          </td>
          <td class="px-3 py-3">
            <div class="flex justify-end gap-2">
              <a href="<?= $h($base.'/suppliers/'.(int)$r['id']) ?>"
                 class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                <i class="fa-regular fa-eye"></i><span class="hidden sm:inline">View</span>
              </a>
              <a href="<?= $h($base.'/suppliers/'.(int)$r['id'].'/edit') ?>"
                 class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
                <i class="fa-regular fa-pen-to-square"></i><span class="hidden sm:inline">Edit</span>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$rows): ?>
        <tr>
          <td colspan="6" class="px-3 py-10 text-center text-slate-500 dark:text-slate-400">
            No suppliers yet.
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>