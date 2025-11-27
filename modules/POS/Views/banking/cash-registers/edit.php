<?php
$h     = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base  = $base ?? '/apps/pos';
$reg   = $reg ?? [];
$brand = '#228B22';

$opened = !empty($reg['opened_at']) ? date('Y-m-d', strtotime((string)$reg['opened_at'])) : date('Y-m-d');
?>
<div class="px-6 py-6 max-w-3xl space-y-5">
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-cash-register text-emerald-500"></i>
        <span>Edit Register</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Update the register name, code or status.
      </p>
    </div>
    <a href="<?= $h($base) ?>/banking/cash-registers"
       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left text-xs"></i>
      Back
    </a>
  </div>

  <form method="post"
        action="<?= $h($base) ?>/banking/cash-registers/<?= (int)($reg['id'] ?? 0) ?>"
        class="space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Register Name <span class="text-red-500">*</span>
        </label>
        <input name="name" required
               value="<?= $h($reg['name'] ?? '') ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Code
        </label>
        <input name="code"
               value="<?= $h($reg['code'] ?? '') ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Opened On
        </label>
        <input type="date"
               value="<?= $h($opened) ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-sm text-gray-500 dark:text-gray-400"
               disabled>
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Status
        </label>
        <?php $status = (string)($reg['status'] ?? 'open'); ?>
        <select name="status"
                class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
          <option value="open"     <?= $status === 'open'     ? 'selected' : '' ?>>Open</option>
          <option value="closed"   <?= $status === 'closed'   ? 'selected' : '' ?>>Closed</option>
          <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
        Notes
      </label>
      <textarea name="notes" rows="3"
                class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"><?= $h($reg['notes'] ?? '') ?></textarea>
    </div>

    <div class="flex items-center justify-between pt-2">
      <p class="text-[11px] text-gray-400 dark:text-gray-500">
        Closing amounts are usually logged automatically by end-of-day reports.
      </p>
      <div class="flex gap-2">
        <a href="<?= $h($base) ?>/banking/cash-registers"
           class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-times text-xs"></i>
          Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
                style="background:<?= $brand ?>;">
          <i class="fa fa-check text-xs"></i>
          Save Changes
        </button>
      </div>
    </div>
  </form>
</div>