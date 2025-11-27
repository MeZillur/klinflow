<?php
/**
 * Payments edit
 *
 * Expected:
 *  - $base          string module base
 *  - $payment       array (or $pay)
 *  - $bankAccounts  array (optional)
 *  - $customers     array (optional)
 *  - $suppliers     array (optional)
 */

$h            = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base         = $base ?? '/apps/pos';
$brand        = '#228B22';
$bankAccounts = $bankAccounts ?? [];
$customers    = $customers ?? [];
$suppliers    = $suppliers ?? [];

$p = $payment ?? ($pay ?? []);
$id = (int)($p['id'] ?? 0);

$paymentDate = $p['payment_date'] ?? $p['date'] ?? $p['txn_date'] ?? date('Y-m-d');
$direction   = strtolower((string)($p['direction'] ?? 'in'));
$type        = strtolower((string)($p['type'] ?? 'customer'));
$method      = $p['method'] ?? $p['payment_method'] ?? 'cash';
$status      = strtolower((string)($p['status'] ?? 'posted'));

$customerId  = (int)($p['customer_id'] ?? 0);
$supplierId  = (int)($p['supplier_id'] ?? 0);
$otherParty  = $p['other_party'] ?? '';

$bankId      = (int)($p['bank_account_id'] ?? 0);
$regId       = (int)($p['cash_register_id'] ?? 0);

$amount      = (float)($p['amount'] ?? $p['total'] ?? 0);
$reference   = $p['reference'] ?? $p['ref_no'] ?? '';
$memo        = $p['memo'] ?? '';
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-money-check-dollar text-emerald-500"></i>
          <span>Edit Payment #<?= $h($id ?: '-') ?></span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Update payment details. Sensitive fields affect accounting, so edit carefully.
        </p>
      </div>
      <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="<?= $h($base) ?>/payments"
           class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-arrow-left text-xs"></i>
          Back
        </a>
        <?php if ($id): ?>
          <a href="<?= $h($base) ?>/payments/<?= $id ?>"
             class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
            <i class="fa fa-eye text-xs"></i>
            View
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Horizontal layout: form + guidance -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
      <form method="post"
            action="<?= $h($base) ?>/payments/<?= $id ?>"
            class="lg:col-span-2 space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm">

        <!-- Date + direction -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Payment Date
            </label>
            <input type="date" name="payment_date" value="<?= $h(substr((string)$paymentDate,0,10)) ?>"
                   class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Direction
            </label>
            <div class="flex flex-wrap gap-2 text-xs">
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="direction" value="in"
                       <?= $direction === 'out' ? '' : 'checked' ?>
                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <span>Money In (Received)</span>
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="direction" value="out"
                       <?= $direction === 'out' ? 'checked' : '' ?>
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
              Payment Type
            </label>
            <select name="type"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50">
              <option value="customer"   <?= $type === 'customer'   ? 'selected' : '' ?>>Customer</option>
              <option value="supplier"   <?= $type === 'supplier'   ? 'selected' : '' ?>>Supplier</option>
              <option value="expense"    <?= $type === 'expense'    ? 'selected' : '' ?>>Expense</option>
              <option value="income"     <?= $type === 'income'     ? 'selected' : '' ?>>Other Income</option>
              <option value="adjustment" <?= $type === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Method
            </label>
            <select name="method"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50">
              <?php
              $methods = [
                  'cash'          => 'Cash',
                  'bank_transfer' => 'Bank Transfer',
                  'card'          => 'Card',
                  'mobile_money'  => 'Mobile Money',
                  'cheque'        => 'Cheque',
                  'other'         => 'Other',
              ];
              foreach ($methods as $k=>$label):
              ?>
                <option value="<?= $h($k) ?>" <?= strtolower($method) === $k ? 'selected' : '' ?>>
                  <?= $h($label) ?>
                </option>
              <?php endforeach; ?>
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
                  <?php $cid = (int)($c['id'] ?? 0); ?>
                  <option value="<?= $cid ?>" <?= $cid === $customerId ? 'selected' : '' ?>>
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
                  <?php $sid = (int)($s['id'] ?? 0); ?>
                  <option value="<?= $sid ?>" <?= $sid === $supplierId ? 'selected' : '' ?>>
                    <?= $h($s['name'] ?? $s['supplier_name'] ?? 'Unnamed') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Other Party</label>
              <input name="other_party" value="<?= $h($otherParty) ?>"
                     class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-50"
                     placeholder="Optional free-text party name">
            </div>
          </div>
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
                <?php $bid = (int)($b['id'] ?? 0); ?>
                <option value="<?= $bid ?>" <?= $bid === $bankId ? 'selected' : '' ?>>
                  <?= $h($b['name'] ?? ($b['bank_name'] ?? 'Bank')) ?>
                  <?php if (!empty($b['account_no'])): ?>
                    (<?= $h($b['account_no']) ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Cash Register
            </label>
            <select name="cash_register_id"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50">
              <option value="">— Select Register (for cash) —</option>
              <!-- populate from controller if available -->
            </select>
          </div>
        </div>

        <!-- Amount + reference -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Amount
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">৳</span>
              <input type="number" name="amount" step="0.01" min="0"
                     value="<?= $h(number_format($amount,2,'.','')) ?>"
                     class="w-full pl-7 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Reference
            </label>
            <input name="reference" value="<?= $h($reference) ?>"
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
                    placeholder="Describe this payment in short."><?= $h($memo) ?></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            Status
          </label>
          <select name="status"
                  class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50">
            <option value="posted"   <?= $status === 'posted'   ? 'selected' : '' ?>>Posted</option>
            <option value="pending"  <?= $status === 'pending'  ? 'selected' : '' ?>>Pending</option>
            <option value="void"     <?= $status === 'void'     ? 'selected' : '' ?>>Void / Cancelled</option>
          </select>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between pt-2">
          <p class="text-[11px] text-gray-400 dark:text-gray-500">
            Amount / direction change করলে accounting impact পরিবর্তন হবে – প্রয়োজন না হলে বারবার edit করবেন না।
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
              Update Payment
            </button>
          </div>
        </div>
      </form>

      <!-- Guidance right -->
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4 space-y-2">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <i class="fa fa-lightbulb text-amber-400"></i>
          <span>Payment edit করার আগে ভাবুন</span>
        </h2>
        <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
          <li>• Posted payment change মানে accounting history change – শুধু genuine mistake হলে edit করুন।</li>
          <li>• Amount ভুল থাকলে অনেক সময় নতুন correction payment / adjustment করা ভালো, পুরনোটা void করা ভালো।</li>
          <li>• Direction (In/Out) ভুল হলে সেই entry পুরো উল্টো হয়ে যায় – এই ফিল্ড পরিবর্তনের আগে দুইবার চিন্তা করুন।</li>
          <li>• Party পরিবর্তন করলে customer / supplier balance ও বদলাবে, তাই strong reason ছাড়া change করবেন না।</li>
        </ul>
        <p class="text-[11px] text-gray-400 dark:text-gray-500">
          Tip: বড় পরিবর্তন করার আগে current reports (ledger, customer / supplier statement) download করে রেখে দিন – fallback হিসেবে কাজে লাগবে।
        </p>
      </div>
    </div>
  </div>
</div>