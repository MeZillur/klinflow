<?php
declare(strict_types=1);
/** @var array $e */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
$errs = $_SESSION['pos_errors'] ?? [];
$old  = $_SESSION['pos_old'] ?? [];
unset($_SESSION['pos_errors'], $_SESSION['pos_old']);

$val = fn($k,$df='') => $h($old[$k] ?? ($e[$k] ?? $df));
?>
<div class="max-w-3xl mx-auto p-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Edit Expense</h1>
    <a href="<?= $h($base) ?>/expenses" class="px-3 py-2 border rounded-lg">Back</a>
  </div>

  <form method="post" action="<?= $h($base) ?>/expenses/<?= (int)$e['expense_id'] ?>" class="space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Expense No</label>
        <input name="expense_no" value="<?= $val('expense_no') ?>" class="px-3 py-2 rounded-lg border w-full" required>
      </div>
      <div>
        <label class="block text-sm font-medium">Date</label>
        <input type="date" name="voucher_date" value="<?= $val('voucher_date', date('Y-m-d')) ?>"
               class="px-3 py-2 rounded-lg border w-full" required>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium">Payee</label>
        <input name="payee_name" value="<?= $val('payee_name') ?>" class="px-3 py-2 rounded-lg border w-full">
      </div>
      <div>
        <label class="block text-sm font-medium">Supplier</label>
        <select name="supplier_id" class="px-3 py-2 rounded-lg border w-full">
          <option value="">(None)</option>
          <?php foreach ($sups ?? [] as $s): $sel = ((int)($e['supplier_id'] ?? 0) === (int)$s['id']) ? 'selected':''; ?>
            <option value="<?= (int)$s['id'] ?>" <?= $sel ?>><?= $h($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium">Description</label>
      <input name="description" value="<?= $val('description') ?>" class="px-3 py-2 rounded-lg border w-full">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium">Method</label>
        <input name="payment_method" value="<?= $val('payment_method','Cash') ?>" class="px-3 py-2 rounded-lg border w-full">
      </div>
      <div>
        <label class="block text-sm font-medium">Bank Account</label>
        <select name="bank_account_id" class="px-3 py-2 rounded-lg border w-full">
          <option value="">(None)</option>
          <?php foreach ($banks ?? [] as $b): $sel = ((int)($e['bank_account_id'] ?? 0) === (int)$b['id']) ? 'selected':''; ?>
            <option value="<?= (int)$b['id'] ?>" <?= $sel ?>><?= $h($b['name'].' — '.$b['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium">Status</label>
        <?php $st = (string)($e['status'] ?? 'approved'); ?>
        <select name="status" class="px-3 py-2 rounded-lg border w-full">
          <?php foreach (['approved','paid','draft','void'] as $x): ?>
            <option value="<?= $x ?>" <?= $st===$x?'selected':'' ?>><?= ucfirst($x) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium">Subtotal</label>
        <input name="subtotal" type="number" step="0.01" value="<?= $h($money((int)($e['subtotal_cents'] ?? 0))) ?>"
               class="px-3 py-2 rounded-lg border w-full">
      </div>
      <div>
        <label class="block text-sm font-medium">Total *</label>
        <input name="total" type="number" step="0.01" value="<?= $h($money((int)($e['total_cents'] ?? 0))) ?>"
               class="px-3 py-2 rounded-lg border w-full" required>
      </div>
      <div>
        <label class="block text-sm font-medium">Paid Amount</label>
        <input name="paid_amount" type="number" step="0.01" value="<?= $h($money((int)($e['paid_amount_cents'] ?? 0))) ?>"
               class="px-3 py-2 rounded-lg border w-full">
      </div>
    </div>

    <?php if (!empty($errs)): ?>
      <div class="text-sm text-rose-600">
        <?php foreach ($errs as $m): ?><div><?= $h($m) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="flex gap-2">
      <a href="<?= $h($base) ?>/expenses" class="px-3 py-2 border rounded-lg">Cancel</a>
      <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">Update</button>
    </div>
  </form>
</div>