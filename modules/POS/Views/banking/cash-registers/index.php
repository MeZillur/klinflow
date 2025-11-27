<?php
$h      = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base   = $base ?? '/apps/pos';
$rows   = $rows ?? [];
$search = $search ?? '';
$brand  = '#228B22';
?>
<div class="px-6 py-6 space-y-5">
  <!-- Header + tabs -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-cash-register text-emerald-500"></i>
        <span>Cash Registers</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Per-branch tills / drawers used for daily POS sales.
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <a href="<?= $h($base) ?>/banking/accounts"
         class="inline-flex items-center gap-1 h-9 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50">
        <i class="fa fa-landmark text-[11px]"></i>
        HQ Accounts
      </a>
      <a href="<?= $h($base) ?>/banking/cash-registers"
         class="inline-flex items-center gap-1 h-9 px-3 rounded-full text-xs font-semibold border border-emerald-500/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40">
        <i class="fa fa-cash-register text-[11px]"></i>
        Registers
      </a>
      <a href="<?= $h($base) ?>/banking/deposits"
         class="inline-flex items-center gap-1 h-9 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50">
        <i class="fa fa-arrow-up-from-bracket text-[11px]"></i>
        Deposits
      </a>
      <a href="<?= $h($base) ?>/banking/cash-registers/create"
         class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-semibold
                text-white shadow-sm"
         style="background:<?= $brand ?>;">
        <span class="fa fa-plus text-xs"></span>
        <span>New Register</span>
      </a>
    </div>
  </div>

  <!-- Filter -->
  <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <form method="get" class="flex items-center gap-2 flex-1 min-w-[220px]">
      <div class="relative flex-1">
        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-xs">
          <i class="fa fa-search"></i>
        </span>
        <input name="q"
               value="<?= $h($search) ?>"
               class="w-full pl-7 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500"
               placeholder="Search by name or code">
      </div>
      <?php if ($search !== ''): ?>
        <a href="<?= $h($base) ?>/banking/cash-registers"
           class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
          Clear
        </a>
      <?php endif; ?>
    </form>
    <p class="text-xs text-gray-400 dark:text-gray-500">
      Tip: One register per counter / till is usually ideal.
    </p>
  </div>

  <!-- Table -->
  <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
    <?php if (!$rows): ?>
      <div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
        <i class="fa fa-circle-info mb-2 text-lg"></i>
        <div>No registers yet. Create the first one for this branch.</div>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/80 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <tr>
              <th class="px-4 py-2 text-left">Register</th>
              <th class="px-3 py-2 text-left">Code</th>
              <th class="px-3 py-2 text-left">Branch</th>
              <th class="px-3 py-2 text-right">Opening Float</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-4 py-2 text-right">Opened / Closed</th>
              <th class="px-4 py-2 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php foreach ($rows as $r): ?>
            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/70">
              <td class="px-4 py-2">
                <div class="font-medium text-gray-900 dark:text-gray-50">
                  <?= $h($r['name'] ?? '') ?>
                </div>
              </td>
              <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                <?= $h($r['code'] ?? '—') ?>
              </td>
              <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                <?php if (!empty($r['branch_id'])): ?>
                  Branch #<?= (int)$r['branch_id'] ?>
                <?php else: ?>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] text-gray-600 dark:text-gray-300">
                    <i class="fa fa-building text-[10px]"></i> HQ
                  </span>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">
                ৳<?= number_format((float)($r['opening_float'] ?? 0), 2) ?>
              </td>
              <td class="px-3 py-2">
                <?php
                  $status = (string)($r['status'] ?? 'open');
                  $pillClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                  if ($status === 'closed') $pillClass = 'bg-gray-100 text-gray-700 border-gray-200';
                  if ($status === 'inactive') $pillClass = 'bg-red-50 text-red-700 border-red-200';
                ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] border <?= $h($pillClass) ?>">
                  <?= ucfirst($h($status)) ?>
                </span>
              </td>
              <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">
                <?php if (!empty($r['opened_at'])): ?>
                  <?= $h(date('d M Y H:i', strtotime((string)$r['opened_at']))) ?>
                <?php endif; ?>
                <?php if (!empty($r['closed_at'])): ?>
                  <br>
                  <span class="text-[11px] text-gray-400 dark:text-gray-500">
                    Closed: <?= $h(date('d M Y H:i', strtotime((string)$r['closed_at']))) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2 text-right">
                <div class="inline-flex items-center gap-2">
                  <a href="<?= $h($base) ?>/banking/cash-registers/<?= (int)$r['id'] ?>/edit"
                     class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <i class="fa fa-pen text-[10px] mr-1"></i> Edit
                  </a>
                  <?php if (($r['status'] ?? 'open') === 'open'): ?>
                    <form method="post"
                          action="<?= $h($base) ?>/banking/cash-registers/<?= (int)$r['id'] ?>/close"
                          onsubmit="return confirm('Close this register?');">
                      <button type="submit"
                              class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs border border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100">
                        <i class="fa fa-lock text-[10px] mr-1"></i> Close
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>