<?php
declare(strict_types=1);
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$module_base = $module_base ?? '';
$stakeholders = $stakeholders ?? [];   // [{id, code, name}]
$customers    = $customers ?? [];      // [{id, name}]
$suppliers    = $suppliers ?? [];      // [{id, name}]
$visits       = $visits ?? [];

// old form values (after validation error)
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$old_stakeholder = (int)($old['stakeholder_id'] ?? 0);
$old_customer    = (int)($old['customer_id'] ?? 0);
$old_supplier    = (int)($old['dealer_id'] ?? 0);
$old_date        = (string)($old['visit_date'] ?? date('Y-m-d'));
$old_status      = (string)($old['status'] ?? 'planned');
$old_notes       = (string)($old['notes'] ?? '');
?>
<div class="mb-4 flex items-center justify-between">
  <h1 class="text-xl font-semibold">Stakeholder Visit Plan</h1>
  <a href="<?= h($module_base) ?>/stakeholders" class="px-3 py-2 rounded-lg border hover:bg-slate-100 dark:hover:bg-gray-700">
    Back to Stakeholders
  </a>
</div>

<!-- Flash messages -->
<?php if (!empty($_SESSION['flash_errors'])): ?>
  <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-2">
    <?= h(implode(' ', (array)$_SESSION['flash_errors'])) ?>
  </div>
  <?php unset($_SESSION['flash_errors']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="mb-4 rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-2">
    <?= h($_SESSION['flash_success']) ?>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<!-- Create Visit Form -->
<form method="POST" action="<?= h($module_base) ?>/stakeholders/visit" class="rounded-xl border bg-white dark:bg-gray-800 shadow-sm p-6 mb-6 space-y-4">
  <div class="grid md:grid-cols-2 gap-4">
    <!-- Stakeholder (Choices.js) -->
    <div>
      <label class="text-sm font-medium text-slate-600 dark:text-gray-300">Stakeholder</label>
      <select
        name="stakeholder_id"
        id="stakeholder_id"
        required
        data-choices
        data-choices-placeholder="Select stakeholder"
        class="mt-1 w-full border rounded-lg"
      >
        <option value="">Select stakeholder</option>
        <?php foreach ($stakeholders as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $old_stakeholder===(int)$s['id']?'selected':'' ?>>
            <?= h($s['name'].' ('.$s['code'].')') ?>
          </option>
        <?php endforeach ?>
      </select>
      <?php if (!$stakeholders): ?>
        <p class="text-xs text-amber-600 mt-1">No SR/DSR found. Add stakeholders first.</p>
      <?php endif; ?>
    </div>

    <div>
      <label class="text-sm font-medium text-slate-600 dark:text-gray-300">Visit Date</label>
      <input type="date" name="visit_date" value="<?= h($old_date) ?>" class="mt-1 w-full border rounded-lg px-3 py-2 bg-transparent focus:outline-none focus:ring-2 focus:ring-[--brand]" required>
    </div>

    <!-- Customer (Choices.js) -->
    <div>
      <label class="text-sm font-medium text-slate-600 dark:text-gray-300">Customer</label>
      <select
        name="customer_id"
        id="customer_id"
        data-choices
        data-choices-placeholder="Select customer (or leave empty)"
        class="mt-1 w-full border rounded-lg"
      >
        <option value="">-- none --</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $old_customer===(int)$c['id']?'selected':'' ?>>
            <?= h($c['name']) ?>
          </option>
        <?php endforeach ?>
      </select>
      <p class="text-xs text-slate-500 mt-1">If you choose a customer, leave Supplier empty.</p>
      <?php if (!$customers): ?>
        <p class="text-xs text-amber-600 mt-1">No customers found.</p>
      <?php endif; ?>
    </div>

    <!-- Supplier (Choices.js) -->
    <div>
      <label class="text-sm font-medium text-slate-600 dark:text-gray-300">Supplier</label>
      <select
        name="dealer_id"
        id="dealer_id"
        data-choices
        data-choices-placeholder="Select supplier (or leave empty)"
        class="mt-1 w-full border rounded-lg"
      >
        <option value="">-- none --</option>
        <?php foreach ($suppliers as $sp): ?>
          <option value="<?= (int)$sp['id'] ?>" <?= $old_supplier===(int)$sp['id']?'selected':'' ?>>
            <?= h($sp['name']) ?>
          </option>
        <?php endforeach ?>
      </select>
      <p class="text-xs text-slate-500 mt-1">If you choose a supplier, leave Customer empty.</p>
      <?php if (!$suppliers): ?>
        <p class="text-xs text-amber-600 mt-1">No suppliers found.</p>
      <?php endif; ?>
    </div>

    <div>
      <label class="text-sm font-medium text-slate-600 dark:text-gray-300">Status</label>
      <select name="status" class="mt-1 w-full border rounded-lg px-3 py-2 bg-transparent focus:outline-none focus:ring-2 focus:ring-[--brand]">
        <?php foreach (['planned','done','missed','cancelled'] as $st): ?>
          <option value="<?= h($st) ?>" <?= $old_status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
        <?php endforeach ?>
      </select>
    </div>

    <div>
      <label class="text-sm font-medium text-slate-600 dark:text-gray-300">Notes</label>
      <input type="text" name="notes" value="<?= h($old_notes) ?>" placeholder="Optional notes" class="mt-1 w-full border rounded-lg px-3 py-2 bg-transparent focus:outline-none focus:ring-2 focus:ring-[--brand]">
    </div>
  </div>

  <div class="pt-3">
    <button type="submit" class="bg-[--brand] text-white font-medium px-4 py-2 rounded-lg hover:opacity-90">Save Visit</button>
  </div>
</form>

<!-- Recent Visits -->
<div class="rounded-xl border bg-white dark:bg-gray-800 shadow-sm overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50 dark:bg-gray-700 text-slate-600 dark:text-gray-200 uppercase text-xs">
      <tr>
        <th class="px-4 py-2 text-left">Date</th>
        <th class="px-4 py-2 text-left">Stakeholder</th>
        <th class="px-4 py-2 text-left">Customer</th>
        <th class="px-4 py-2 text-left">Supplier</th>
        <th class="px-4 py-2 text-center">Status</th>
        <th class="px-4 py-2 text-left">Notes</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($visits as $v): ?>
      <tr class="border-t border-slate-100 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700/30">
        <td class="px-4 py-2"><?= h($v['visit_date'] ?? '') ?></td>
        <td class="px-4 py-2"><?= h($v['stakeholder_name'] ?? '') ?></td>
        <td class="px-4 py-2"><?= h($v['customer_name'] ?? '') ?></td>
        <td class="px-4 py-2"><?= h($v['supplier_name'] ?? '') ?></td>
        <td class="px-4 py-2 text-center">
          <span class="px-2 py-1 rounded text-xs font-medium
            <?= match($v['status'] ?? '') {
              'done' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
              'missed' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
              'cancelled' => 'bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
              default => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300'
            } ?>">
            <?= ucfirst(h($v['status'] ?? '')) ?>
          </span>
        </td>
        <td class="px-4 py-2"><?= h($v['notes'] ?? '') ?></td>
      </tr>
      <?php endforeach ?>
      <?php if (!$visits): ?>
      <tr><td colspan="6" class="px-4 py-3 text-center text-slate-400">No visit plans yet.</td></tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<script>
// Mutually exclusive: picking a customer clears supplier, and vice versa
(function(){
  const cust = document.getElementById('customer_id');
  const supp = document.getElementById('dealer_id');
  if (!cust || !supp) return;
  cust.addEventListener('change', () => { if (cust.value) { supp.value = ''; document.dispatchEvent(new CustomEvent('kf:choices:scan')); } });
  supp.addEventListener('change', () => { if (supp.value) { cust.value = ''; document.dispatchEvent(new CustomEvent('kf:choices:scan')); } });
});

// Ensure Choices scans after load (and on PJAX/partial loads you can re-dispatch)
document.addEventListener('DOMContentLoaded', function(){
  document.dispatchEvent(new CustomEvent('kf:choices:scan'));
});
</script>