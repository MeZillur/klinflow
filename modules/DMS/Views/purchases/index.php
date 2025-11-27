<?php
/** @var array $rows */
/** @var string $module_base */

$fmtMoney = fn($n) => number_format((float)$n, 2);
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="p-6 space-y-5" x-data="{ q: '' }">
  <!-- Header bar -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3">
      <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white grid place-items-center">
        <i class="fa-solid fa-file-invoice text-lg"></i>
      </div>
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Purchases</h1>
        <p class="text-sm text-slate-500">Track vendor bills and stock receipts</p>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <!-- Quick stats -->
      <div class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700">
        <i class="fa-solid fa-layer-group"></i>
        <span class="text-sm font-medium"><?= count($rows) ?> records</span>
      </div>

      <!-- New Purchase -->
      <a href="<?= $h($module_base) ?>/purchases/create"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 transition focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-1">
        <i class="fa-solid fa-plus"></i>
        <span class="font-semibold">New Purchase</span>
      </a>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="relative w-full sm:w-80">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
      <input x-model="q" type="search" placeholder="Search bill no, dealer…"
             class="w-full pl-10 pr-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-400" />
    </div>

    <!-- (Optional) filters can go here -->
  </div>

  <?php if (!$rows): ?>
    <!-- Empty state -->
    <div class="grid place-items-center rounded-2xl border border-dashed border-slate-300 p-12 text-center bg-white">
      <div class="h-12 w-12 rounded-2xl bg-slate-100 text-emerald-600 grid place-items-center mb-3">
        <i class="fa-solid fa-cart-shopping"></i>
      </div>
      <h2 class="text-lg font-semibold mb-1">No purchases yet</h2>
      <p class="text-sm text-slate-500 mb-4">Create your first purchase to receive stock and record payables.</p>
      <a href="<?= $h($module_base) ?>/purchases/create"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="fa-solid fa-plus"></i> New Purchase
      </a>
    </div>
  <?php else: ?>
    <!-- Table -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="px-4 py-3 text-left font-semibold">#</th>
              <th class="px-4 py-3 text-left font-semibold">Bill No</th>
              <th class="px-4 py-3 text-left font-semibold">Bill Date</th>
              <th class="px-4 py-3 text-left font-semibold">Dealer</th>
              <th class="px-4 py-3 text-right font-semibold">Grand Total</th>
              <th class="px-4 py-3 text-left font-semibold">Status</th>
              <th class="px-4 py-3 text-right font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100" x-data="{ q: $root.__x ? $root.__x.$data.q : '' }" x-init="$watch('$root.__x.$data.q', v => q = v)">
          <?php
          $sum = 0.0;
          foreach ($rows as $i => $r):
            $id     = (int)($r['id'] ?? 0);
            $no     = (string)($r['bill_no'] ?? $r['no'] ?? $id);
            $date   = (string)($r['bill_date'] ?? $r['date'] ?? '');
            $dealer = (string)($r['dealer_name'] ?? $r['supplier_name'] ?? '');
            $total  = (float)($r['grand_total'] ?? $r['total'] ?? 0);
            $status = strtolower((string)($r['status'] ?? 'draft'));
            $sum   += $total;

            // badge color
            $badge = match ($status) {
              'confirmed','posted','received' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
              'cancelled','void'              => 'bg-rose-50 text-rose-700 ring-rose-200',
              default                         => 'bg-amber-50 text-amber-700 ring-amber-200',
            };
          ?>
            <tr
              x-show="['<?= $h($no) ?>','<?= $h($dealer) ?>','<?= $h($date) ?>'].join(' ').toLowerCase().includes(q.toLowerCase())"
              class="hover:bg-slate-50">
              <td class="px-4 py-3 text-slate-500"><?= $i + 1 ?></td>
              <td class="px-4 py-3">
                <a href="<?= $h($module_base) ?>/purchases/<?= $id ?>"
                   class="font-semibold text-slate-800 hover:text-emerald-700">
                  <?= $h($no) ?>
                </a>
              </td>
              <td class="px-4 py-3"><?= $h($date) ?></td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-building-user text-slate-400"></i>
                  <span class="truncate max-w-[240px]"><?= $h($dealer ?: '—') ?></span>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums">৳ <?= $fmtMoney($total) ?></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full ring-1 text-xs <?= $badge ?>">
                  <i class="fa-solid fa-circle-dot text-[10px]"></i> <?= $h(ucfirst($status)) ?>
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end items-center gap-2">
                  <a href="<?= $h($module_base) ?>/purchases/<?= $id ?>"
                     class="inline-flex items-center justify-center h-8 w-8 rounded-lg hover:bg-slate-100"
                     title="View">
                    <i class="fa-solid fa-eye text-slate-600"></i>
                  </a>
                  <a href="<?= $h($module_base) ?>/purchases/<?= $id ?>/edit"
                     class="inline-flex items-center justify-center h-8 w-8 rounded-lg hover:bg-slate-100"
                     title="Edit">
                    <i class="fa-solid fa-pen-to-square text-slate-600"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot class="bg-slate-50">
            <tr>
              <td class="px-4 py-3 font-semibold text-slate-600" colspan="4">Total</td>
              <td class="px-4 py-3 text-right font-extrabold text-slate-900 tabular-nums">৳ <?= $fmtMoney($sum) ?></td>
              <td class="px-4 py-3" colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Alpine.js (tiny) for search if not already loaded in shell) -->
<script>
  window.Alpine || document.write('<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer><\/script>');
</script>