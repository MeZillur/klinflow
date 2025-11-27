<?php
declare(strict_types=1);

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

/** @var array $rows  list of customers (id, code, name, phone, email, status, created_at) */
/** @var string $module_base injected by BaseController->view() */
$rows = $rows ?? [];
$q    = trim((string)($_GET['q'] ?? ''));
?>
<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Customers</h1>
    <a href="<?= h($module_base) ?>/customers/create"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
      <i class="fa-solid fa-user-plus"></i><span>New Customer</span>
    </a>
  </div>

  <!-- Toolbar -->
  <form method="get" class="mb-4">
    <div class="flex items-center gap-2">
      <div class="flex-1 relative">
        <input type="text" name="q" value="<?= h($q) ?>"
               class="w-full rounded-lg border px-3 py-2 pl-9"
               placeholder="Search name, code, phone…">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
      </div>
      <?php if ($q !== ''): ?>
        <a href="<?= h($module_base) ?>/customers" class="px-3 py-2 rounded-lg border hover:bg-slate-50">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!$rows): ?>
    <div class="rounded-xl border border-dashed p-10 text-center text-slate-500">
      <div class="text-lg mb-2">No customers yet.</div>
      <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
         href="<?= h($module_base) ?>/customers/create">
        <i class="fa-solid fa-user-plus"></i><span>Add your first customer</span>
      </a>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto rounded-xl border">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left w-[22%]">Name</th>
            <th class="px-3 py-2 text-left w-[16%]">Code</th>
            <th class="px-3 py-2 text-left w-[16%]">Phone</th>
            <th class="px-3 py-2 text-left w-[26%]">Email</th>
            <th class="px-3 py-2 text-left w-[10%]">Status</th>
            <th class="px-3 py-2 text-right w-[10%]">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="border-t">
              <td class="px-3 py-2">
                <a class="font-medium hover:underline"
                   href="<?= h($module_base) ?>/customers/<?= (int)$r['id'] ?>">
                  <?= h($r['name'] ?? '') ?>
                </a>
              </td>
              <td class="px-3 py-2"><?= h($r['code'] ?? '') ?></td>
              <td class="px-3 py-2"><?= h($r['phone'] ?? '') ?></td>
              <td class="px-3 py-2"><?= h($r['email'] ?? '') ?></td>
              <td class="px-3 py-2">
                <?php $st = strtolower((string)($r['status'] ?? 'active')); ?>
                <span class="px-2 py-0.5 rounded-full text-[11px]
                             <?= $st==='inactive' ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-700' ?>">
                  <?= h(ucfirst($st)) ?>
                </span>
              </td>
              <td class="px-3 py-2 text-right">
                <div class="inline-flex items-center gap-2">
                  <a class="text-blue-600 hover:underline"
                     href="<?= h($module_base) ?>/customers/<?= (int)$r['id'] ?>">View</a>
                  <a class="text-slate-700 hover:underline"
                     href="<?= h($module_base) ?>/customers/<?= (int)$r['id'] ?>/edit">Edit</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>