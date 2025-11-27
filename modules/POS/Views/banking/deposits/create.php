<?php
$h            = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base         = $base ?? '/apps/pos';
$brand        = '#228B22';
$bankAccounts = $bankAccounts ?? [];
$cashRegisters= $cashRegisters ?? [];
$today        = $today ?? date('Y-m-d');
?>
<div class="px-6 py-6 max-w-4xl mx-auto space-y-8">

  <!-- Header -->
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-arrow-up-from-bracket text-emerald-500"></i>
        <span>New Deposit</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Move branch cash into an HQ bank account.
      </p>
    </div>
    <a href="<?= $h($base) ?>/banking/deposits"
       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left text-xs"></i>
      Back
    </a>
  </div>

  <!-- Top Nav -->
  <div class="flex flex-wrap gap-2">
    <a href="<?= $h($base) ?>/banking/accounts"
       class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
      <i class="fa fa-landmark text-[10px]"></i> HQ Accounts
    </a>
    <a href="<?= $h($base) ?>/banking/cash-registers"
       class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
      <i class="fa fa-cash-register text-[10px]"></i> Registers
    </a>
    <a href="<?= $h($base) ?>/banking/deposits"
       class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-emerald-500/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40">
      <i class="fa fa-arrow-up-from-bracket text-[10px]"></i> Deposits
    </a>
  </div>

  <!-- FORM -->
  <form method="post"
        action="<?= $h($base) ?>/banking/deposits"
        class="space-y-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-6 py-6 shadow-sm">

    <!-- Row 1 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
          Deposit Date <span class="text-red-500">*</span>
        </label>
        <input type="date" name="deposited_at" value="<?= $h($today) ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-emerald-500/70">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
          Amount <span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">৳</span>
          <input name="amount" type="number" step="0.01" min="0.01" required
                 class="w-full pl-7 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-emerald-500/70"
                 placeholder="0.00">
        </div>
      </div>
    </div>

    <!-- Row 2 -->
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
        Deposit To (HQ Bank) <span class="text-red-500">*</span>
      </label>
      <select name="bank_account_id" required
              class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-emerald-500/70">
        <option value="">— Select bank account —</option>
        <?php foreach ($bankAccounts as $b): ?>
          <option value="<?= (int)$b['id'] ?>">
            <?= $h(($b['bank_name'] ?? '').' — '.($b['name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Row 3 -->
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
        From Cash Register (optional)
      </label>
      <select name="cash_register_id"
              class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-emerald-500/70">
        <option value="">— Not linked to a register —</option>
        <?php foreach ($cashRegisters as $r): ?>
          <option value="<?= (int)$r['id'] ?>">
            <?= $h($r['name'] ?? 'Register #'.$r['id']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Row 4 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
          Method
        </label>
        <input name="method"
               value="Cash"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-emerald-500/70"
               placeholder="e.g. Cash, EFT, Cheque">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
          Reference
        </label>
        <input name="reference"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-emerald-500/70"
               placeholder="Bank slip no / remarks">
      </div>
    </div>

    <!-- Notes -->
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
        Notes
      </label>
      <textarea name="notes" rows="3"
                class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:ring-2 focus:ring-emerald-500/70"
                placeholder="Optional: who deposited, shift, etc."></textarea>
    </div>

    <!-- Submit Row -->
    <div class="flex items-center justify-between pt-2">
      <p class="text-[11px] text-gray-400 dark:text-gray-500">
        Deposits will later sync into the accounting module as bank receipts.
      </p>
      <div class="flex gap-2">
        <a href="<?= $h($base) ?>/banking/deposits"
           class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-times text-xs"></i>
          Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
                style="background:<?= $brand ?>;">
          <i class="fa fa-check text-xs"></i>
          Save Deposit
        </button>
      </div>
    </div>

  </form>


  <!-- Guidance (moved to bottom, full width) -->
  <div class="bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5">
    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">How deposits work</h2>
    <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
      <li>• Outlets collect cash → deposit to HQ bank.</li>
      <li>• Selecting a cash register helps track branch drawer balances.</li>
      <li>• Reference can be the bank slip number or remarks.</li>
      <li>• Deposits sync later into the accounting module.</li>
    </ul>
  </div>

</div>