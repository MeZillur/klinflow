<?php
declare(strict_types=1);
/**
 * Inventory → Transfers (CONTENT-ONLY)
 *
 * Inputs:
 *   $base        – module base (/t/{slug}/apps/pos)
 *   $csrf        – CSRF token (optional)
 *   $branches    – (optional) list of branches [id, name, code, is_main?]
 *   $transfers   – (optional) list of recent transfers:
 *                  [
 *                    [
 *                      'id'          => int,
 *                      'created_at'  => 'Y-m-d H:i:s',
 *                      'from_branch' => 'Main Warehouse',
 *                      'to_branch'   => 'Storefront',
 *                      'items_count' => 5,
 *                      'status'      => 'sent|received|draft|cancelled',
 *                      'ref'         => 'note / reference',
 *                    ],
 *                    ...
 *                  ]
 */

$h       = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand   = '#16a34a'; // emerald-ish
$branches  = $branches  ?? [];
$transfers = $transfers ?? [];

/** Detect main branch from list (is_main=1, or name contains "main", else first) */
$mainBranch = null;
foreach ($branches as $b) {
    if (!empty($b['is_main']) && (int)$b['is_main'] === 1) {
        $mainBranch = $b;
        break;
    }
}
if (!$mainBranch && !empty($branches)) {
    foreach ($branches as $b) {
        if (stripos($b['name'] ?? '', 'main') !== false) {
            $mainBranch = $b;
            break;
        }
    }
}
if (!$mainBranch && !empty($branches)) {
    $mainBranch = $branches[0];
}

$mainName = $mainBranch['name'] ?? 'Main Warehouse';
$mainCode = $mainBranch['code'] ?? '';
$fromLabel = $mainName . ($mainCode ? ' — '.$mainCode : '');

/** Build To-branch options (all except main) */
$toBranches = [];
foreach ($branches as $b) {
    if ($mainBranch && (int)$b['id'] === (int)$mainBranch['id']) continue;
    $label = $b['name'] ?? ('Branch #'.(int)$b['id']);
    if (!empty($b['code'])) $label .= ' — '.$b['code'];
    $toBranches[] = [
        'id'    => (int)$b['id'],
        'label' => $label,
    ];
}
?>
<div class="px-6 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <div class="inline-flex items-center gap-2">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl"
              style="background: <?= $brand ?>; color:#fff;">
          <i class="fa fa-truck-ramp-box"></i>
        </span>
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            Transfers
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Move stock from the main warehouse to other branches.
          </p>
        </div>
      </div>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        <i class="fa fa-lock mr-1"></i>
        This screen is <strong>main branch only</strong>. “From Location” is locked to the main warehouse.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <a href="<?= $h($base) ?>/inventory"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-sm
                text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
        <i class="fa fa-warehouse"></i>
        Inventory
      </a>
      <a href="<?= $h($base) ?>/inventory/movements"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-white shadow"
         style="background: <?= $brand ?>;">
        <i class="fa fa-right-left"></i>
        View Movements
      </a>
    </div>
  </div>

  <!-- New Transfer Card -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-gray-900 dark:text-gray-100">
        New Transfer <span class="text-xs font-normal text-gray-400">(draft)</span>
      </h2>
      <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
        <i class="fa fa-circle-info"></i>
        Items will update branch stock once the transfer is marked as sent / received (backend logic later).
      </span>
    </div>

    <form method="post"
          action="<?= $h($base) ?>/inventory/transfers/create">
      <?php if (!empty($csrf)): ?>
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
            <i class="fa fa-location-arrow text-xs"></i>
            From Location
          </label>
          <div class="mt-1 relative">
            <input type="text"
                   class="w-full rounded-lg px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800
                          border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 cursor-not-allowed"
                   value="<?= $h($fromLabel) ?>"
                   readonly>
            <input type="hidden" name="from_branch_id" value="<?= $mainBranch ? (int)$mainBranch['id'] : 0 ?>">
            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">
              <i class="fa fa-lock"></i>
            </span>
          </div>
        </div>

        <div>
          <label class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
            <i class="fa fa-location-dot text-xs"></i>
            To Location
          </label>
          <div class="mt-1">
            <?php if ($toBranches): ?>
              <select name="to_branch_id"
                      required
                      class="w-full rounded-lg px-3 py-2 text-sm border border-gray-300 dark:border-gray-700
                             dark:bg-gray-800 dark:text-gray-100">
                <option value="">Select branch…</option>
                <?php foreach ($toBranches as $bOpt): ?>
                  <option value="<?= (int)$bOpt['id'] ?>"><?= $h($bOpt['label']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="text"
                     class="w-full rounded-lg px-3 py-2 text-sm border border-gray-300 dark:border-gray-700
                            bg-gray-50 dark:bg-gray-800 text-gray-500"
                     value="Create at least one additional branch to transfer to."
                     readonly>
            <?php endif; ?>
          </div>
        </div>

        <div>
          <label class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
            <i class="fa fa-receipt text-xs"></i>
            Reference
          </label>
          <input type="text"
                 name="reference"
                 class="mt-1 w-full rounded-lg px-3 py-2 text-sm border border-gray-300 dark:border-gray-700
                        dark:bg-gray-800 dark:text-gray-100"
                 placeholder="Optional note or reference…">
        </div>
      </div>

      <!-- Items placeholder (wire later) -->
      <div class="mt-5">
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
            <i class="fa fa-box text-xs"></i>
            Items
          </label>
          <span class="text-xs text-gray-400">
            Quick UI only — connect to product search and inventory API later.
          </span>
        </div>

        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-4 space-y-3 text-sm">
          <div class="flex flex-wrap items-center gap-3">
            <input type="text"
                   name="draft_sku"
                   class="flex-1 min-w-[160px] rounded-lg px-3 py-2 text-sm border border-gray-300 dark:border-gray-700
                          dark:bg-gray-800 dark:text-gray-100"
                   placeholder="Scan or type SKU / barcode (mock)">
            <input type="number"
                   name="draft_qty"
                   class="w-28 rounded-lg px-3 py-2 text-sm border border-gray-300 dark:border-gray-700
                          dark:bg-gray-800 dark:text-gray-100"
                   value="1" min="1" step="1">
            <button type="button"
                    class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold rounded-lg border
                           border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200
                           hover:bg-gray-100 dark:hover:bg-gray-800"
                    onclick="alert('Line-item builder will connect to product API later 🤝');">
              <i class="fa fa-plus"></i>
              Add line (mock)
            </button>
          </div>

          <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            For now this section is illustrative. In the real flow you’ll pick products from inventory,
            choose quantities, and see availability per branch before sending.
          </div>
        </div>
      </div>

      <div class="mt-5 flex flex-wrap items-center gap-2">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white shadow
                       hover:opacity-95"
                style="background: <?= $brand ?>;">
          <i class="fa fa-file-circle-plus"></i>
          Create Draft
        </button>
        <a href="<?= $h($base) ?>/inventory"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm border border-gray-300 dark:border-gray-700
                  text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
          <i class="fa fa-xmark"></i>
          Cancel
        </a>
      </div>
    </form>
  </div>

  <!-- Transfer History / Logs -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
          <i class="fa fa-clock-rotate-left text-xs text-gray-600 dark:text-gray-300"></i>
        </span>
        <div>
          <h2 class="font-semibold text-gray-900 dark:text-gray-100">
            Transfer History
          </h2>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Date, time, source & destination branches, item count, and status.
          </p>
        </div>
      </div>

      <!-- Simple quick filters (UI only for now) -->
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="text-gray-500 dark:text-gray-400">Quick filter:</span>
        <button type="button"
                class="px-2 py-1 rounded-full border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300
                       hover:bg-gray-100 dark:hover:bg-gray-800"
                onclick="alert('Hook up date filter backend later')">
          Today
        </button>
        <button type="button"
                class="px-2 py-1 rounded-full border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300
                       hover:bg-gray-100 dark:hover:bg-gray-800"
                onclick="alert('Hook up date filter backend later')">
          This week
        </button>
        <button type="button"
                class="px-2 py-1 rounded-full border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300
                       hover:bg-gray-100 dark:hover:bg-gray-800"
                onclick="alert('Hook up date filter backend later')">
          This month
        </button>
      </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
          <tr>
            <th class="px-4 py-3 font-semibold">Date / Time</th>
            <th class="px-4 py-3 font-semibold">From</th>
            <th class="px-4 py-3 font-semibold">To</th>
            <th class="px-4 py-3 font-semibold text-right">Items</th>
            <th class="px-4 py-3 font-semibold text-center">Status</th>
            <th class="px-4 py-3 font-semibold">Reference</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($transfers)): ?>
          <tr>
            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400 text-sm">
              <i class="fa fa-circle-info mr-1"></i>
              No transfers recorded yet. Create your first draft above.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($transfers as $t):
            $status = strtolower((string)($t['status'] ?? 'draft'));
            $badgeClasses = match ($status) {
              'sent'      => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
              'received'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
              'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
              default     => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
            };
          ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
              <td class="px-4 py-2">
                <?= $h($t['created_at'] ?? '') ?>
              </td>
              <td class="px-4 py-2">
                <?= $h($t['from_branch'] ?? $fromLabel) ?>
              </td>
              <td class="px-4 py-2">
                <?= $h($t['to_branch'] ?? '') ?>
              </td>
              <td class="px-4 py-2 text-right">
                <?= number_format((int)($t['items_count'] ?? 0)) ?>
              </td>
              <td class="px-4 py-2 text-center">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?= $badgeClasses ?>">
                  <?php if ($status === 'received'): ?>
                    <i class="fa fa-circle-check text-[11px]"></i>
                  <?php elseif ($status === 'sent'): ?>
                    <i class="fa fa-paper-plane text-[11px]"></i>
                  <?php elseif ($status === 'cancelled'): ?>
                    <i class="fa fa-ban text-[11px]"></i>
                  <?php else: ?>
                    <i class="fa fa-file-lines text-[11px]"></i>
                  <?php endif; ?>
                  <?= strtoupper($status) ?>
                </span>
              </td>
              <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-300">
                <?= $h($t['ref'] ?? '') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>