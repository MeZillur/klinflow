<?php
declare(strict_types=1);
/**
 * Edit Bank Account
 * Expects $b (row) and optional flashes in session.
 */
$h   = fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashErrors  = $_SESSION['flash_errors'] ?? [];
unset($_SESSION['flash_success'], $_SESSION['flash_errors']);
?>
<div x-data="{show:true}" class="space-y-6">
  <!-- Toasts -->
  <?php if ($flashSuccess): ?>
    <div x-show="show" x-transition
         class="rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 px-4 py-3 flex items-start justify-between">
      <div class="pr-4 font-medium"><?= $h($flashSuccess) ?></div>
      <button class="text-emerald-900/70 hover:text-emerald-900" @click="show=false">✕</button>
    </div>
  <?php endif; ?>
  <?php if (!empty($flashErrors)): ?>
    <div x-show="show" x-transition
         class="rounded-lg border border-rose-300 bg-rose-50 text-rose-800 px-4 py-3">
      <div class="font-semibold mb-1">Please fix the following:</div>
      <ul class="list-disc pl-5 space-y-0.5">
        <?php foreach ($flashErrors as $e): ?>
          <li><?= $h($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="text-right mt-2">
        <button class="text-rose-900/70 hover:text-rose-900" @click="show=false">Dismiss</button>
      </div>
    </div>
  <?php endif; ?>

  <div class="flex items-center justify-between flex-wrap gap-3">
    <h1 class="text-xl font-semibold">Edit Bank Account</h1>
    <a href="../../bank-accounts" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">← Back</a>
  </div>

  <form action="" method="post"
        class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-white dark:bg-gray-900 p-6 rounded-xl border">
    <div>
      <label class="text-sm font-medium">Bank Name</label>
      <input type="text" name="bank_name" value="<?= $h($b['bank_name'] ?? '') ?>"
             class="mt-1 w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900" required>
    </div>

    <div>
      <label class="text-sm font-medium">Account Name</label>
      <input type="text" name="account_name" value="<?= $h($b['account_name'] ?? '') ?>"
             class="mt-1 w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900" required>
    </div>

    <div>
      <label class="text-sm font-medium">Account Number</label>
      <input type="text" name="account_no" value="<?= $h($b['account_no'] ?? '') ?>"
             class="mt-1 w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900" required>
    </div>

    <div>
      <label class="text-sm font-medium">Branch</label>
      <input type="text" name="branch" value="<?= $h($b['branch'] ?? '') ?>"
             class="mt-1 w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </div>

    <div>
      <label class="text-sm font-medium">Routing Number</label>
      <input type="text" name="routing_no" value="<?= $h($b['routing_no'] ?? '') ?>"
             class="mt-1 w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </div>

    <div>
      <label class="text-sm font-medium">Current Balance</label>
      <input type="text" value="৳ <?= number_format((float)($b['current_balance'] ?? 0),2) ?>" readonly
             class="mt-1 w-full border rounded-lg px-3 py-2 bg-gray-50 dark:bg-gray-800 text-right">
    </div>

    <div class="flex items-center gap-2">
      <input type="checkbox" id="is_master" name="is_master" value="1" <?= !empty($b['is_master']) ? 'checked' : '' ?>>
      <label for="is_master" class="text-sm font-medium">Set as Master Account</label>
    </div>

    <div>
      <label class="text-sm font-medium">Status</label>
      <select name="status" class="mt-1 w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
        <option value="active" <?= ($b['status'] ?? '') === 'inactive' ? '' : 'selected' ?>>Active</option>
        <option value="inactive" <?= ($b['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>

    <div class="md:col-span-2 pt-3 flex justify-between">
      <form action="../make-master" method="post" onsubmit="return confirm('Make this the master bank?');">
        <button class="px-3 py-2 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800" type="submit">
          Make Master
        </button>
      </form>
      <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        Update Account
      </button>
    </div>
  </form>
</div>

<!-- Alpine (only if your layout doesn’t already include it) -->
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>