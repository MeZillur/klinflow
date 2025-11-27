<?php
/**
 * Payments create
 *
 * Expected:
 *  - $base          string module base (/apps/pos or /t/{slug}/apps/pos)
 *  - $bankAccounts  array  (optional) HQ bank accounts
 *  - $customers     array  (optional) customers list
 *  - $suppliers     array  (optional) suppliers list
 */

$h            = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base         = $base         ?? '/apps/pos';
$brand        = '#228B22';
$bankAccounts = $bankAccounts ?? [];
$customers    = $customers    ?? [];
$suppliers    = $suppliers    ?? [];
$today        = date('Y-m-d');
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Header row -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-money-check-dollar text-emerald-500"></i>
          <span>New Payment</span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Receive from customer, pay supplier, record expense or other movement via bank / cash.
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500">
          আজকের তারিখ:
          <span class="font-medium text-gray-700 dark:text-gray-300"><?= $h($today) ?></span>
        </p>
      </div>

      <!-- Top nav chips -->
      <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="<?= $h($base) ?>/banking/accounts"
           class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
          <i class="fa fa-landmark text-[10px]"></i> HQ Accounts
        </a>
        <a href="<?= $h($base) ?>/banking/cash-registers"
           class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
          <i class="fa fa-cash-register text-[10px]"></i> Registers
        </a>
        <a href="<?= $h($base) ?>/banking/deposits"
           class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
          <i class="fa fa-arrow-up-from-bracket text-[10px]"></i> Deposits
        </a>
        <a href="<?= $h($base) ?>/payments"
           class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-emerald-500/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40">
          <i class="fa fa-money-check-dollar text-[10px]"></i> Payments
        </a>
      </div>
    </div>

    <!-- Horizontal layout: form left, guidance right -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
      <!-- Form -->
      <form method="post"
            action="<?= $h($base) ?>/payments"
            class="lg:col-span-2 space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm">

        <!-- Date + direction -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Payment Date
            </label>
            <input type="date" name="payment_date" value="<?= $h($today) ?>"
                   class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Direction <span class="text-red-500">*</span>
            </label>
            <div class="flex flex-wrap gap-2 text-xs">
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="direction" value="in" checked
                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <span>Money In (Received)</span>
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="direction" value="out"
                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <span>Money Out (Paid)</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Type + method -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Payment Type <span class="text-red-500">*</span>
            </label>
            <select name="type"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
              <option value="customer">Customer (Due receive)</option>
              <option value="supplier">Supplier (Bill payment)</option>
              <option value="expense">Expense</option>
              <option value="income">Other Income</option>
              <option value="adjustment">Adjustment</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Method
            </label>
            <select name="method"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="card">Card</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="cheque">Cheque</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>

        <!-- Party selector -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Party (Customer / Supplier / Other)
          </label>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Customer</label>
              <select name="customer_id"
                      class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-50">
                <option value="">— Select —</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= (int)($c['id'] ?? 0) ?>">
                    <?= $h($c['name'] ?? $c['customer_name'] ?? 'Unnamed') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Supplier</label>
              <select name="supplier_id"
                      class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-50">
                <option value="">— Select —</option>
                <?php foreach ($suppliers as $s): ?>
                  <option value="<?= (int)($s['id'] ?? 0) ?>">
                    <?= $h($s['name'] ?? $s['supplier_name'] ?? 'Unnamed') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Other Party</label>
              <input name="other_party"
                     class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-50"
                     placeholder="Optional free-text party name">
            </div>
          </div>
          <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
            Customer payment দিলে শুধু customer select করুন, supplier payment এ supplier select করুন। Other/expense হলে Other Party লিখলেই হবে।
          </p>
        </div>

        <!-- Bank / Cash -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Bank Account (HQ)
            </label>
            <select name="bank_account_id"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50">
              <option value="">— Select Bank (for non-cash) —</option>
              <?php foreach ($bankAccounts as $b): ?>
                <option value="<?= (int)($b['id'] ?? 0) ?>">
                  <?= $h($b['name'] ?? ($b['bank_name'] ?? 'Bank')) ?>
                  <?php if (!empty($b['account_no'])): ?>
                    (<?= $h($b['account_no']) ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
              Bank transfer / cheque / card হলে এখানে কোন bank account এ impact হবে সেটা select করুন।
            </p>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Cash Register
            </label>
            <select name="cash_register_id"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50">
              <option value="">— Select Register (for cash) —</option>
              <!-- you can populate from controller later -->
            </select>
            <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
              Cash payment হলে কোন কাউন্টার / drawer থেকে টাকা আসছে / যাচ্ছে সেটি select করুন।
            </p>
          </div>
        </div>

        <!-- Amount + reference -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Amount <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">৳</span>
              <input type="number" name="amount" step="0.01" min="0"
                     required
                     class="w-full pl-7 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
                     placeholder="0.00">
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Reference
            </label>
            <input name="reference"
                   class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50"
                   placeholder="Invoice no / cheque no / transaction ID">
          </div>
        </div>

        <!-- Memo + status -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Notes / Memo
          </label>
          <textarea name="memo" rows="3"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50"
                    placeholder="Extra details about this payment (উদাহরণ: March rent, partial payment ইত্যাদি)।"></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Status
          </label>
          <select name="status"
                  class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50">
            <option value="posted">Posted</option>
            <option value="pending">Pending</option>
            <option value="void">Void / Cancelled</option>
          </select>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between pt-2">
          <p class="text-[11px] text-gray-400 dark:text-gray-500">
            সব payment master bank account বা branch cash register এর মাধ্যমে GL-এ পোস্ট হবে।
          </p>
          <div class="flex gap-2">
            <a href="<?= $h($base) ?>/payments"
               class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
              <i class="fa fa-times text-xs"></i>
              Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
                    style="background:<?= $brand ?>;">
              <i class="fa fa-check text-xs"></i>
              Save Payment
            </button>
          </div>
        </div>
      </form>

      <!-- Guidance (right side) -->
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4 space-y-2">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <i class="fa fa-lightbulb text-amber-400"></i>
          <span>Payment ফর্ম ব্যবহারের গাইডলাইন</span>
        </h2>
        <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
          <li>১) <strong>Direction = Money In</strong> হলে customer থেকে টাকা আসবে, <strong>Money Out</strong> হলে supplier / expense এর দিকে টাকা যাবে।</li>
          <li>২) Customer / Supplier payment গুলোতে party ঠিকভাবে select করলে later statement / aging report অনেক সহজ হবে।</li>
          <li>৩) Cash হলে অবশ্যই <strong>Cash Register</strong> select করুন, bank হলে <strong>Bank Account</strong> select করুন – এতে GL ঠিকমতো hit করবে।</li>
          <li>৪) Reference ফিল্ডে invoice no / cheque no / trx ID দিলে পরে audit বা মিলাতে অনেক সুবিধা হবে।</li>
          <li>৫) HQ user সব branch-এর payments দেখতে পারবে, branch user শুধু নিজের branch scope-এর payment দেখতে পারবে।</li>
        </ul>
        <p class="text-[11px] text-gray-400 dark:text-gray-500">
          Tip: বড় organization-এ একটা simple policy follow করুন – সব manual payment এখান দিয়ে create না করলে বই কখনই clean দেখাবে না।
        </p>
      </div>

    </div>
  </div>
</div>