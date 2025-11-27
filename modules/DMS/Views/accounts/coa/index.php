<?php
/**
 * Vars:
 * - $accounts: list rows (array)
 * - $parents:  [id => name]
 * - $editRow:  array|null
 * - $types:    [key => label]
 * - $brandColor (string)
 * - $module_base (string, provided by shell)
 * - $org (ctx array from shell)
 */
declare(strict_types=1);

$base    = isset($module_base) ? (string)$module_base : '/apps/dms';
$orgName = htmlspecialchars((string)($org['org']['name'] ?? ($org['name'] ?? 'Organization')), ENT_QUOTES, 'UTF-8');

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$accounts = is_array($accounts ?? null) ? $accounts : [];
$parents  = is_array($parents  ?? null) ? $parents  : [];
$types    = is_array($types    ?? null) ? $types    : [
  'asset' => 'Asset',
  'liability' => 'Liability',
  'equity' => 'Equity',
  'income' => 'Income',
  'expense' => 'Expense',
];

$brandColor = (string)($brandColor ?? '#16a34a');
$editRow = is_array($editRow ?? null) ? $editRow : null;
?>
<div class="container mx-auto max-w-6xl px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-semibold">Chart of Accounts</h1>
      <p class="text-sm text-gray-600 dark:text-gray-300">
        Manage account groups and ledgers for <strong><?= $orgName ?></strong>
      </p>
    </div>
    <button id="btn-add" type="button" class="inline-flex items-center px-3 py-2 rounded text-white" style="background:<?= h($brandColor) ?>;">
      <i class="fa fa-plus-circle mr-2"></i> Add Account
    </button>
  </div>

  <!-- table -->
  <div class="overflow-auto rounded border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th class="px-3 py-2 text-left">Code</th>
          <th class="px-3 py-2 text-left">Name</th>
          <th class="px-3 py-2 text-left">Type</th>
          <th class="px-3 py-2 text-left">Parent</th>
          <th class="px-3 py-2 text-left">Active</th>
          <th class="px-3 py-2 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$accounts): ?>
        <tr>
          <td colspan="6" class="px-3 py-6 text-center text-gray-500">No accounts found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($accounts as $r): ?>
          <?php
            $pid = isset($r['parent_id']) ? (int)$r['parent_id'] : 0;
            $parentLabel = $pid > 0 ? ($parents[$pid] ?? ('#' . $pid)) : '—';
            $isActive = (int)($r['is_active'] ?? 0) === 1;
          ?>
          <tr class="border-t">
            <td class="px-3 py-2 font-mono"><?= h($r['code'] ?? '') ?></td>
            <td class="px-3 py-2"><?= h($r['name'] ?? '') ?></td>
            <td class="px-3 py-2 capitalize"><?= h($r['type'] ?? '') ?></td>
            <td class="px-3 py-2"><?= h($parentLabel) ?></td>
            <td class="px-3 py-2">
              <?php if ($isActive): ?>
                <span class="inline-flex items-center text-emerald-600"><i class="fa fa-check-circle mr-1"></i> Active</span>
              <?php else: ?>
                <span class="inline-flex items-center text-gray-400"><i class="fa fa-circle mr-1"></i> Inactive</span>
              <?php endif; ?>
            </td>
            <td class="px-3 py-2 text-right">
              <a class="inline-flex items-center px-2 py-1 rounded border"
                 href="<?= h($base . '/accounts/coa?edit=' . (int)($r['id'] ?? 0)) ?>">
                <i class="fa fa-pen mr-1"></i> Edit
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- form panel (create/update) -->
  <div id="panel" class="mt-6 rounded border p-4<?= $editRow ? '' : ' hidden' ?>">
    <h2 class="text-lg font-medium mb-3"><?= $editRow ? 'Edit Account' : 'Add Account' ?></h2>

    <form method="post" action="<?= h($base . '/accounts/coa') ?>" class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
      <?php if ($editRow): ?>
        <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
      <?php endif; ?>

      <label class="block">
        <div class="text-xs mb-1">Code</div>
        <input name="code" required value="<?= h($editRow['code'] ?? '') ?>" class="w-full border rounded px-2 py-1">
      </label>

      <label class="block">
        <div class="text-xs mb-1">Account name</div>
        <input name="name" required value="<?= h($editRow['name'] ?? '') ?>" class="w-full border rounded px-2 py-1">
      </label>

      <label class="block">
        <div class="text-xs mb-1">Type</div>
        <select name="type" class="w-full border rounded px-2 py-1">
          <?php foreach ($types as $k => $label): ?>
            <?php $sel = (($editRow['type'] ?? '') === $k) ? 'selected' : ''; ?>
            <option value="<?= h($k) ?>" <?= $sel ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="block">
        <div class="text-xs mb-1">Parent (optional)</div>
        <input name="parent_id"
               value="<?= ($editRow && isset($editRow['parent_id']) && (int)$editRow['parent_id'] > 0) ? (int)$editRow['parent_id'] : '' ?>"
               class="w-full border rounded px-2 py-1"
               placeholder="Parent ID">
      </label>

      <label class="block">
        <div class="text-xs mb-1">Active</div>
        <?php
          $checked = 'checked';
          if ($editRow !== null) {
              $checked = ((int)($editRow['is_active'] ?? 0) === 1) ? 'checked' : '';
          }
        ?>
        <input type="checkbox" name="is_active" value="1" <?= $checked ?>>
      </label>

      <div class="md:col-span-2 flex gap-2 mt-2">
        <a href="<?= h($base . '/accounts/coa') ?>" class="px-3 py-2 rounded border inline-flex items-center">
          <i class="fa fa-times mr-1"></i> Cancel
        </a>
        <button type="submit" class="px-3 py-2 rounded text-white inline-flex items-center" style="background:<?= h($brandColor) ?>;">
          <i class="fa fa-save mr-1"></i> Save
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    var btn = document.getElementById('btn-add');
    var panel = document.getElementById('panel');
    if (btn && panel) {
      btn.addEventListener('click', function () {
        panel.classList.remove('hidden');
        try { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); } catch(e) {}
      });
    }
  })();
</script>