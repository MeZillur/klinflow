<?php
/** @var array  $rows   normalized by controller (id, group_name, status, start_date, end_date)
 *  @var string $module_base
 *  @var string|null $schema_note
 */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$active = 'groups';
?>
<div class="max-w-[1100px] mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold tracking-tight">Group Reservations</h1>
    <div class="flex gap-2">
      <a href="<?= $h($base) ?>/reservations" class="px-3 py-2 rounded-lg border hover:bg-slate-50">Back</a>
    </div>
  </div>

  <?php include __DIR__.'/_tabs.php'; ?>

  <?php if (!empty($schema_note)): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
      <div class="font-semibold mb-1">Schema note</div>
      <div class="text-sm"><?= $h($schema_note) ?></div>
      <details class="mt-2 text-sm">
        <summary class="cursor-pointer">Minimal table you can create now (MariaDB safe)</summary>
<pre class="mt-2 p-3 bg-white/70 rounded border overflow-auto text-xs"><?=
$h(
"CREATE TABLE IF NOT EXISTS hms_group_blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'open',   -- planned/definite/cancelled
  start_date DATE NULL,
  end_date   DATE NULL,
  company_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_org (org_id),
  KEY idx_dates (start_date, end_date),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
) ?></pre>
      </details>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="get" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
    <div class="lg:col-span-2">
      <label class="text-sm text-slate-600">Search</label>
      <input type="text" name="q" value="<?= $h((string)($_GET['q'] ?? '')) ?>"
             placeholder="Group name / code / company…" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Status</label>
      <?php $st = (string)($_GET['status'] ?? ''); ?>
      <select name="status" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        <?php foreach ([''=>'Any','planned'=>'Planned','definite'=>'Definite','open'=>'Open','tentative'=>'Tentative','cancelled'=>'Cancelled'] as $k=>$v): ?>
          <option value="<?= $h($k) ?>" <?= $st===$k?'selected':'' ?>><?= $h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex gap-2 lg:col-span-2">
      <button class="px-4 py-2 rounded-lg text-white w-full sm:w-auto" style="background:var(--brand)">Apply</button>
      <a href="<?= $h($base) ?>/reservations/groups" class="px-4 py-2 rounded-lg border w-full sm:w-auto">Reset</a>
    </div>
  </form>

  <!-- Table -->
  <div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-700">
        <tr>
          <th class="text-left px-3 py-2">Group</th>
          <th class="text-left px-3 py-2">Dates</th>
          <th class="text-left px-3 py-2">Status</th>
          <th class="text-right px-3 py-2">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($rows)): ?>
          <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No groups found.</td></tr>
        <?php else: foreach ($rows as $g): ?>
          <?php
            $name  = (string)($g['group_name'] ?? ('Group #'.(int)$g['id']));
            $sd    = $g['start_date'] ?? null;
            $ed    = $g['end_date']   ?? null;
            $range = ($sd && $ed) ? ($sd.' → '.$ed) : ($sd ?: ($ed ?: '—'));
            $status= strtolower((string)($g['status'] ?? 'open'));
            $badge = match ($status) {
              'definite'  => 'bg-emerald-50 text-emerald-700 border-emerald-200',
              'planned'   => 'bg-sky-50 text-sky-700 border-sky-200',
              'tentative' => 'bg-gray-50 text-gray-700 border-gray-200',
              'cancelled' => 'bg-red-50 text-red-700 border-red-200',
              default     => 'bg-amber-50 text-amber-700 border-amber-200',
            };
            $href = $base.'/reservations?q='.rawurlencode($name);
          ?>
          <tr>
            <td class="px-3 py-2">
              <div class="font-medium"><?= $h($name) ?></div>
              <div class="text-xs text-slate-500">#<?= (int)$g['id'] ?></div>
            </td>
            <td class="px-3 py-2"><?= $h($range) ?></td>
            <td class="px-3 py-2">
              <span class="inline-block text-xs px-2 py-0.5 rounded-full border <?= $badge ?>"><?= $h(ucfirst($status)) ?></span>
            </td>
            <td class="px-3 py-2 text-right">
              <a href="<?= $h($href) ?>" class="px-2 py-1 rounded border hover:bg-slate-50">View reservations</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- (Optional) Quick-create drawer placeholder -->
  <details class="mt-4">
    <summary class="cursor-pointer text-sm font-semibold">Need a minimal groups table?</summary>
    <div class="mt-2 text-sm text-slate-600">
      Create the table shown in the Schema note above, then refresh this page.
    </div>
  </details>
</div>