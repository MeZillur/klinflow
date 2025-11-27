<?php
declare(strict_types=1);
/** @var array $totals @var array $recent */
$h    = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
$title = $title ?? 'Accounting Overview';
?>
<div class="max-w-6xl mx-auto p-6">

  <!-- Header + Actions (right-aligned) -->
  <div class="mb-6 flex items-center justify-between gap-3">
    <h1 class="text-2xl font-bold"><?= $h($title) ?></h1>

    <!-- Desktop actions -->
    <nav class="hidden md:flex items-center gap-2">
      <a href="<?= $h($base) ?>/expenses"
         class="px-3 py-2 rounded-lg border hover:bg-gray-50">Expenses</a>
      <a href="<?= $h($base) ?>/accounts/payments"
         class="px-3 py-2 rounded-lg border hover:bg-gray-50">Payments</a>
      <a href="<?= $h($base) ?>/banking/accounts"
         class="px-3 py-2 rounded-lg border hover:bg-gray-50">Bank Accounts</a>
      <a href="<?= $h($base) ?>/gl/journals"
         class="px-3 py-2 rounded-lg border hover:bg-gray-50">Journals</a>
      <a href="<?= $h($base) ?>/gl/ledger"
         class="px-3 py-2 rounded-lg border hover:bg-gray-50">Ledger</a>
      <a href="<?= $h($base) ?>/gl/chart"
         class="px-3 py-2 rounded-lg border hover:bg-gray-50">Chart of A/C</a>
    </nav>

    <!-- Mobile compact menu (no JS needed) -->
    <details class="md:hidden relative">
      <summary class="list-none cursor-pointer px-3 py-2 rounded-lg border hover:bg-gray-50 select-none">
        Menu
      </summary>
      <div class="absolute right-0 mt-2 w-56 rounded-lg border bg-white shadow-lg overflow-hidden z-10">
        <a href="<?= $h($base) ?>/expenses" class="block px-3 py-2 hover:bg-gray-50">Expenses</a>
        <a href="<?= $h($base) ?>/accounts/payments" class="block px-3 py-2 hover:bg-gray-50">Payments</a>
        <a href="<?= $h($base) ?>/accounts/receipts" class="block px-3 py-2 hover:bg-gray-50">Receipts</a>
        <a href="<?= $h($base) ?>/banking/accounts" class="block px-3 py-2 hover:bg-gray-50">Bank Accounts</a>
        <a href="<?= $h($base) ?>/gl/journals" class="block px-3 py-2 hover:bg-gray-50">Journals</a>
        <a href="<?= $h($base) ?>/gl/chart" class="block px-3 py-2 hover:bg-gray-50">Chart of A/C</a>
      </div>
    </details>
  </div>

  <!-- Totals Grid (2x2 small, 4 across on md) -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php
      $cards = [
        ['label' => 'Assets',      'value' => $totals['asset']     ?? 0, 'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        ['label' => 'Liabilities', 'value' => $totals['liability'] ?? 0, 'color' => 'bg-rose-50 text-rose-700 border-rose-200'],
        ['label' => 'Income',      'value' => $totals['income']    ?? 0, 'color' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        ['label' => 'Expenses',    'value' => $totals['expense']   ?? 0, 'color' => 'bg-amber-50 text-amber-700 border-amber-200'],
      ];
    ?>
    <?php foreach ($cards as $c): ?>
      <div class="p-4 border rounded-xl <?= $c['color'] ?> shadow-sm">
        <div class="text-xs uppercase tracking-wide text-gray-600 mb-1"><?= $h($c['label']) ?></div>
        <div class="text-2xl font-semibold"><?= number_format((float)$c['value'], 2) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Quick Links (2x2 small, 3 across on md) -->
  <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
    <a href="<?= $h($base) ?>/accounts/payments" class="p-4 border rounded-xl hover:bg-gray-50 flex justify-between items-center">
      <div>
        <div class="font-semibold text-gray-800">Payments</div>
        <div class="text-xs text-gray-500">Record and manage outgoing payments</div>
      </div>
      <span class="text-gray-400">→</span>
    </a>
    <a href="<?= $h($base) ?>/banking/accounts" class="p-4 border rounded-xl hover:bg-gray-50 flex justify-between items-center">
      <div>
        <div class="font-semibold text-gray-800">Bank Accounts</div>
        <div class="text-xs text-gray-500">View balances and reconcile</div>
      </div>
      <span class="text-gray-400">→</span>
    </a>
    <a href="<?= $h($base) ?>/gl/journals" class="p-4 border rounded-xl hover:bg-gray-50 flex justify-between items-center">
      <div>
        <div class="font-semibold text-gray-800">Journal Entries</div>
        <div class="text-xs text-gray-500">Browse and post general ledger entries</div>
      </div>
      <span class="text-gray-400">→</span>
    </a>
  </div>

  <!-- Recent Activity (2x2 small, 2x2 md+) -->
  <div class="grid grid-cols-2 md:grid-cols-2 gap-4 mb-8">
    <div class="border rounded-xl p-4 bg-white">
      <h2 class="font-semibold mb-3 text-gray-700">Recent Payments</h2>
      <?php if (empty($recent['payments'])): ?>
        <p class="text-sm text-gray-500">No recent payments found.</p>
      <?php else: ?>
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-2 py-1 text-left">Date</th>
              <th class="px-2 py-1 text-left">Method</th>
              <th class="px-2 py-1 text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recent['payments'] as $p): ?>
            <tr class="border-b">
              <td class="px-2 py-1"><?= $h($p['payment_date'] ?? '') ?></td>
              <td class="px-2 py-1"><?= $h($p['method'] ?? '') ?></td>
              <td class="px-2 py-1 text-right"><?= number_format((float)($p['amount'] ?? 0),2) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="border rounded-xl p-4 bg-white">
      <h2 class="font-semibold mb-3 text-gray-700">Recent Journals</h2>
      <?php if (empty($recent['journals'])): ?>
        <p class="text-sm text-gray-500">No journal entries found.</p>
      <?php else: ?>
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-2 py-1 text-left">Date</th>
              <th class="px-2 py-1 text-left">Memo</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recent['journals'] as $j): ?>
            <tr class="border-b">
              <td class="px-2 py-1"><?= $h($j['entry_date'] ?? '') ?></td>
              <td class="px-2 py-1"><?= $h($j['memo'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Guidance Section (Bottom) -->
  <div class="mt-8 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
    <h2 class="font-semibold text-emerald-700 mb-2">Guidance</h2>
    <ul class="list-disc pl-5 text-sm text-emerald-800 space-y-1">
      <li>Balances reflect totals from your <strong>General Ledger</strong> by account type.</li>
      <li>Use <strong>Expenses</strong> or <strong>Payments</strong> to capture cash outflows.</li>
      <li>Review <strong>Journal Entries</strong> for accruals and manual adjustments.</li>
      <li><strong>Bank Accounts</strong> link to GL accounts and can be reconciled later.</li>
      <li>If all values are zero, start by posting a sample transaction or journal entry.</li>
    </ul>
  </div>
</div>