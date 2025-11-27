<?php
declare(strict_types=1);

/**
 * Expense create form
 *
 * Expected (optional, safe if missing):
 *  - $base               string  module base (/apps/pos or /t/{slug}/apps/pos)
 *  - $branches           array   list of branches          [id, name]
 *  - $expenseAccounts    array   main GL expense accounts  [id, code, name]
 *  - $expenseSubAccounts array   sub-accounts              [id, parent_id, code, name]
 *  - $bankAccounts       array   bank accounts             [id, name, bank_name, account_no]
 */

$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

$base               = $base               ?? '/apps/pos';
$branches           = $branches           ?? [];
$expenseAccounts    = $expenseAccounts    ?? [];
$expenseSubAccounts = $expenseSubAccounts ?? [];
$bankAccounts       = $bankAccounts       ?? [];
$today              = date('Y-m-d');
$brand              = '#228B22';

// optional "old" data if controller sets it in session for validation
$old = $_SESSION['form_old'] ?? [];
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-receipt text-emerald-500"></i>
          <span>New Expense</span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Record an expense and route it through the correct GL account and branch.
        </p>
      </div>
      <a href="<?= $h($base) ?>/expenses"
         class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-arrow-left text-xs"></i>
        Back
      </a>
    </div>

    <!-- Top nav chips -->
    <div class="flex flex-wrap gap-2">
      <a href="<?= $h($base) ?>/banking/accounts"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-landmark text-[10px]"></i> HQ Accounts
      </a>
      <a href="<?= $h($base) ?>/banking/deposits"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-arrow-up-from-bracket text-[10px]"></i> Deposits
      </a>
      <a href="<?= $h($base) ?>/payments"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-arrow-right-arrow-left text-[10px]"></i> Payments
      </a>
      <a href="<?= $h($base) ?>/expenses"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-emerald-500/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40">
        <i class="fa fa-receipt text-[10px]"></i> Expenses
      </a>
    </div>

    <!-- Form -->
    <form method="post"
          action="<?= $h($base) ?>/expenses"
          class="space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm">

      <!-- Row 1: Date + Branch -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Expense Date <span class="text-red-500">*</span>
          </label>
          <input type="date"
                 name="expense_date"
                 value="<?= $h($old['expense_date'] ?? $today) ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Branch
          </label>
          <?php if (!empty($branches)): ?>
            <select name="branch_id"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
              <option value="">HQ / General</option>
              <?php foreach ($branches as $b): ?>
                <?php $bid = (int)($b['id'] ?? 0); ?>
                <option value="<?= $bid ?>"
                  <?= (string)($old['branch_id'] ?? '') === (string)$bid ? 'selected' : '' ?>>
                  <?= $h($b['name'] ?? ('Branch #'.$bid)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" value="HQ / General" disabled
                   class="w-full px-3 py-2.5 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-500 dark:text-gray-400">
          <?php endif; ?>
        </div>
      </div>

      <!-- Row 2: Main account + Sub-account -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Expense Account (main) <span class="text-red-500">*</span>
          </label>
          <select name="gl_account_id" required
                  class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
            <option value="">— Select main expense account —</option>
            <?php foreach ($expenseAccounts as $acc): ?>
              <?php $id = (int)($acc['id'] ?? 0); ?>
              <option value="<?= $id ?>"
                <?= (string)($old['gl_account_id'] ?? '') === (string)$id ? 'selected' : '' ?>>
                <?= $h(($acc['code'] ?? '').' — '.($acc['name'] ?? 'Expense')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
            This is the main GL expense account in your accounting module.
          </p>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Sub-account
          </label>
          <select name="gl_subaccount_id"
                  class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
            <option value="">— Optional sub-account —</option>
            <?php foreach ($expenseSubAccounts as $sub): ?>
              <?php
                $sid   = (int)($sub['id'] ?? 0);
                $pcode = $sub['parent_code'] ?? $sub['parent_id'] ?? '';
              ?>
              <option value="<?= $sid ?>"
                <?= (string)($old['gl_subaccount_id'] ?? '') === (string)$sid ? 'selected' : '' ?>>
                <?= $h(($sub['code'] ?? '').' — '.($sub['name'] ?? '')) ?>
                <?= $pcode ? $h(' ('. $pcode .')') : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
            Use this when you want more detailed analysis under the main account.
          </p>
        </div>
      </div>

      <!-- Row 3: Payee + Reference -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Payee / Supplier
          </label>
          <input name="payee"
                 value="<?= $h($old['payee'] ?? '') ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
                 placeholder="Person / company you are paying">
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Reference
          </label>
          <input name="reference"
                 value="<?= $h($old['reference'] ?? '') ?>"
                 class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
                 placeholder="Bill / voucher / invoice no">
        </div>
      </div>

      <!-- Row 4: Amount + Method + Bank account -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Amount <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">৳</span>
            <input name="amount" type="number" min="0.01" step="0.01" required
                   value="<?= $h($old['amount'] ?? '') ?>"
                   class="w-full pl-7 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
                   placeholder="0.00">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Method
          </label>
          <select name="method"
                  class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
            <?php
              $methodOld = strtolower((string)($old['method'] ?? 'cash'));
              $methods = ['cash'=>'Cash','bank'=>'Bank transfer','cheque'=>'Cheque','card'=>'Card','mobile'=>'Mobile wallet'];
            ?>
            <?php foreach ($methods as $key => $label): ?>
              <option value="<?= $h($key) ?>" <?= $methodOld===$key ? 'selected' : '' ?>>
                <?= $h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Bank Account (if paid via bank)
          </label>
          <select name="bank_account_id"
                  class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
            <option value="">— Not from bank —</option>
            <?php foreach ($bankAccounts as $b): ?>
              <?php $bid = (int)($b['id'] ?? 0); ?>
              <option value="<?= $bid ?>"
                <?= (string)($old['bank_account_id'] ?? '') === (string)$bid ? 'selected' : '' ?>>
                <?= $h(($b['bank_name'] ?? '').' — '.($b['name'] ?? '')) ?>
                <?php if (!empty($b['account_no'])): ?>
                  <?= $h(' ('. $b['account_no'] .')') ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Description -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Description / Notes
        </label>
        <textarea name="description" rows="3"
                  class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
                  placeholder="Short explanation that will also help in GL reporting."><?= $h($old['description'] ?? '') ?></textarea>
      </div>

      <!-- Status -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Status
        </label>
        <select name="status"
                class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
          <?php
            $statusOld = strtolower((string)($old['status'] ?? 'approved'));
            $statuses = ['draft'=>'Draft','approved'=>'Approved','paid'=>'Paid','void'=>'Void'];
          ?>
          <?php foreach ($statuses as $key => $label): ?>
            <option value="<?= $h($key) ?>" <?= $statusOld===$key ? 'selected' : '' ?>>
              <?= $h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Footer buttons -->
      <div class="flex items-center justify-between pt-2">
        <p class="text-[11px] text-gray-400 dark:text-gray-500">
          This expense will be linked to the selected GL accounts so your core accounting stays in sync.
        </p>
        <div class="flex gap-2">
          <a href="<?= $h($base) ?>/expenses"
             class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
            <i class="fa fa-times text-xs"></i>
            Cancel
          </a>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
                  style="background:<?= $brand ?>;">
            <i class="fa fa-check text-xs"></i>
            Save Expense
          </button>
        </div>
      </div>
    </form>

    <!-- Guidance block BELOW the form -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4">
      <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">
        Posting logic (how this connects to GL)
      </h2>
      <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
        <li>• POS will store this row in <strong>pos_expenses</strong> with the selected <strong>gl_account_id</strong> and <strong>gl_subaccount_id</strong>.</li>
        <li>• The controller can then create a GL voucher like: <em>Dr Expense (main/sub)</em> &nbsp; / &nbsp; <em>Cr Cash or Bank</em>.</li>
        <li>• Branch, method and bank account fields give the accounting module all context it needs for accurate posting.</li>
        <li>• Status <strong>Draft</strong> can be used for unapproved bills; <strong>Approved</strong> for authorized; <strong>Paid</strong> when the GL entry is fully posted.</li>
      </ul>
    </div>

  </div>
</div>