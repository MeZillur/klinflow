<?php
declare(strict_types=1);
/** @var string $type @var array $categories @var array $banks @var string $base @var string $today */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$isIncome = $type === 'income';
?>
<div class="px-6 py-6">
  <div class="max-w-xl">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50 mb-1">
      <?= $isIncome ? 'Record Income' : 'Record Expense' ?>
    </h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
      Link every movement to a bank account and category. Later this can post directly into Accounting.
    </p>

    <form method="post" action="<?= $h($base) ?>/money"
          class="space-y-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">

      <input type="hidden" name="type" value="<?= $h($type) ?>">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Date
          </label>
          <input type="date" name="entry_date" value="<?= $h($today) ?>"
                 class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2
                        bg-white dark:bg-gray-900 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Amount (৳)
          </label>
          <input type="number" name="amount" step="0.01" min="0"
                 class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-right
                        bg-white dark:bg-gray-900 text-sm" required>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Category
        </label>
        <select name="category_id"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 bg-white dark:bg-gray-900 text-sm">
          <option value="">— Select —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= $h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-gray-400">Example: Sales income, Rent expense, Utility bill, etc.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Bank account
        </label>
        <select name="bank_account_id"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 bg-white dark:bg-gray-900 text-sm">
          <option value="">— Not linked / cash —</option>
          <?php foreach ($banks as $b): ?>
            <option value="<?= (int)$b['id'] ?>">
              <?= $h($b['bank_name'].' — '.$b['account_name']) ?>
              <?php if (!empty($b['account_no'])): ?>
                (<?= $h($b['account_no']) ?>)
              <?php endif; ?>
              <?= !empty($b['is_master']) ? ' [Main]' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Reference / Voucher No.
          </label>
          <input type="text" name="ref_no"
                 class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2
                        bg-white dark:bg-gray-900 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Payment Method
          </label>
          <input type="text" name="payment_method" placeholder="Cash, Bank transfer, Card…"
                 class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2
                        bg-white dark:bg-gray-900 text-sm">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Description
        </label>
        <textarea name="description" rows="3"
                  class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2
                         bg-white dark:bg-gray-900 text-sm resize-vertical"
                  placeholder="<?= $isIncome ? 'Example: Online sales settlement for invoice #123' : 'Example: Shop rent for November' ?>"></textarea>
      </div>

      <div class="flex items-center justify-between pt-2">
        <a href="<?= $h($base) ?>/money"
           class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700
                  text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white shadow"
                style="background:<?= $isIncome ? '#16a34a' : '#dc2626' ?>;">
          <i class="fa <?= $isIncome ? 'fa-arrow-down-long' : 'fa-arrow-up-long' ?> mr-2"></i>
          Save <?= $isIncome ? 'Income' : 'Expense' ?>
        </button>
      </div>
    </form>
  </div>
</div>