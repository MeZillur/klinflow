<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$module_base = $module_base ?? ($moduleBase ?? '/apps/dms'); ?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Stakeholders (SR/DSR)</h1>
  <a href="<?= h($module_base) ?>/stakeholders/create" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">+ New</a>
</div>

<form method="get" class="mb-3 grid grid-cols-1 sm:grid-cols-6 gap-2">
  <input name="q" value="<?= h($q ?? '') ?>" placeholder="Search name/phone/code…" class="sm:col-span-4 rounded-lg border px-3 py-2">
  <select name="role" class="sm:col-span-1 rounded-lg border px-3 py-2">
    <option value="">All Roles</option>
    <option value="sr"  <?= (($role??'')==='sr')?'selected':'' ?>>SR</option>
    <option value="dsr" <?= (($role??'')==='dsr')?'selected':'' ?>>DSR</option>
  </select>
  <button class="rounded-lg border px-3 py-2">Filter</button>
</form>

<div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl border">
  <table class="min-w-full text-sm">
    <thead class="bg-gray-50 dark:bg-gray-900/40">
      <tr>
        <th class="px-3 py-2 text-left">Code</th>
        <th class="px-3 py-2 text-left">Name</th>
        <th class="px-3 py-2 text-left">Role</th>
        <th class="px-3 py-2 text-left">Phone</th>
        <th class="px-3 py-2 text-left">Email</th>
        <th class="px-3 py-2 text-left">Territory</th>
        <th class="px-3 py-2 text-left">Status</th>
        <th class="px-3 py-2"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows??[]) as $r): ?>
      <tr class="border-t">
        <td class="px-3 py-2"><?= h($r['code']) ?></td>
        <td class="px-3 py-2 font-medium"><?= h($r['name']) ?></td>
        <td class="px-3 py-2 uppercase"><?= h($r['role']) ?></td>
        <td class="px-3 py-2"><?= h($r['phone']??'') ?></td>
        <td class="px-3 py-2"><?= h($r['email']??'') ?></td>
        <td class="px-3 py-2"><?= h($r['territory']??'') ?></td>
        <td class="px-3 py-2"><?= h($r['status']) ?></td>
        <td class="px-3 py-2 text-right">
          <a class="px-2 py-1 text-blue-600 hover:underline" href="<?= h($module_base) ?>/stakeholders/<?= (int)$r['id'] ?>">View</a>
          <a class="px-2 py-1 text-slate-600 hover:underline" href="<?= h($module_base) ?>/stakeholders/<?= (int)$r['id'] ?>/edit">Edit</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">No stakeholders yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>