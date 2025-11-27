<?php declare(strict_types=1);
$rows = $rows ?? [];
$q    = (string)($q ?? '');
$base = $module_base ?? '';
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!-- If your layout doesn’t already load FA, this tiny SVG eye is a fallback -->
<style>
  .icon-eye{width:1.05rem;height:1.05rem;vertical-align:-3px}
</style>

<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Product Categories</h1>
    <a href="<?= $h($base.'/categories/create') ?>"
       class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
      New Category
    </a>
  </div>

  <form method="get" class="mb-4">
    <div class="flex gap-2">
      <input name="q" value="<?= $h($q) ?>" placeholder="Search by name or code…"
             class="flex-1 rounded-lg border px-3 py-2">
      <button class="px-3 py-2 rounded-lg border">Search</button>
    </div>
  </form>

  <div class="overflow-x-auto bg-white dark:bg-gray-800 border rounded-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-900/40">
        <tr>
          <th class="text-left px-3 py-2">Code</th>
          <th class="text-left px-3 py-2">Name</th>
          <th class="text-right px-3 py-2">Products</th>
          <th class="text-right px-3 py-2">Suppliers</th>
          <th class="text-left px-3 py-2">Status</th>
          <th class="text-right px-3 py-2">Created</th>
          <th class="px-3 py-2 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No categories yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr class="border-t border-gray-100 dark:border-gray-700/50">
            <td class="px-3 py-2 font-mono"><?= $h($r['code'] ?: '—') ?></td>
            <td class="px-3 py-2"><?= $h($r['name']) ?></td>
            <td class="px-3 py-2 text-right"><?= (int)($r['product_count'] ?? 0) ?></td>
            <td class="px-3 py-2 text-right"><?= (int)($r['supplier_count'] ?? 0) ?></td>
            <td class="px-3 py-2">
              <?php if ((int)$r['is_active'] === 1): ?>
                <span class="text-emerald-700 bg-emerald-100 text-xs px-2 py-1 rounded-full">Active</span>
              <?php else: ?>
                <span class="text-gray-600 bg-gray-100 text-xs px-2 py-1 rounded-full">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="px-3 py-2 text-right"><?= $h(substr((string)$r['created_at'],0,10)) ?></td>
            <td class="px-3 py-2 text-right">
              <a class="inline-flex items-center gap-1 text-slate-700 hover:text-blue-700 mr-3"
                 href="<?= $h($base.'/categories/'.$r['id']) ?>" title="View">
                <!-- Use FA if you load it: <i class="fa-solid fa-eye"></i> -->
                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <span class="sr-only">View</span>
              </a>
              <a class="text-blue-600 hover:underline"
                 href="<?= $h($base.'/categories/'.$r['id'].'/edit') ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>