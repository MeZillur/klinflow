<?php
/**
 * GL Journals index
 *
 * Expected (from controller, but all are optional / safe defaults):
 *  - $base     string  module base (/apps/pos or /t/{slug}/apps/pos)
 *  - $rows     array   list of journals
 *  - $filters  array   ['from','to','type','source','q','branch_id']
 *  - $summary  array   ['count','dr_total','cr_total']
 */

$h       = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base    = $base    ?? '/apps/pos';
$rows    = $rows    ?? [];
$filters = $filters ?? [];
$summary = $summary ?? [];

$from     = $filters['from']      ?? '';
$to       = $filters['to']        ?? '';
$type     = $filters['type']      ?? '';
$source   = $filters['source']    ?? '';
$q        = $filters['q']         ?? '';
$branchId = (int)($filters['branch_id'] ?? 0);

$count    = (int)($summary['count']     ?? count($rows));
$drTotal  = (float)($summary['dr_total']?? 0);
$crTotal  = (float)($summary['cr_total']?? 0);
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-book text-emerald-500" aria-hidden="true"></i>
          <span>GL Journals</span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          All postings from payments, expenses, sales and manual entries.
        </p>
      </div>

      <a href="<?= $h($base) ?>/accounting"
         class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700
                text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900
                hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-arrow-left text-xs" aria-hidden="true"></i>
        <span>Back to Accounting</span>
      </a>
    </div>

    <!-- Top summary cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400 uppercase mb-1">
          Journals
        </div>
        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-50">
          <?= number_format($count) ?>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-emerald-100 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-900/30">
        <div class="text-xs font-semibold tracking-wide text-emerald-700 dark:text-emerald-200 uppercase mb-1">
          Total Debit
        </div>
        <div class="text-2xl font-semibold text-emerald-800 dark:text-emerald-100">
          <?= number_format($drTotal, 2) ?>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-sky-100 dark:border-sky-900 bg-sky-50 dark:bg-sky-900/30">
        <div class="text-xs font-semibold tracking-wide text-sky-700 dark:text-sky-200 uppercase mb-1">
          Total Credit
        </div>
        <div class="text-2xl font-semibold text-sky-800 dark:text-sky-100">
          <?= number_format($crTotal, 2) ?>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">
        <div class="text-xs font-semibold tracking-wide text-gray-500 dark:text-gray-400 uppercase mb-1">
          Difference
        </div>
        <?php $diff = $drTotal - $crTotal; ?>
        <div class="text-2xl font-semibold <?= abs($diff) < 0.01 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
          <?= number_format($diff, 2) ?>
        </div>
        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
          Should be zero; non-zero means broken posting.
        </p>
      </div>
    </div>

    <!-- Filters -->
    <form method="get"
          class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3
                 flex flex-wrap items-end gap-3 text-sm">
      <div class="flex-1 min-w-[180px]">
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
          Search (memo / journal no / source)
        </label>
        <input name="q" value="<?= $h($q) ?>"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-50
                      focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
          From
        </label>
        <input type="date" name="from" value="<?= $h($from) ?>"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-50
                      focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
          To
        </label>
        <input type="date" name="to" value="<?= $h($to) ?>"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-50
                      focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
          Type
        </label>
        <?php $types = ['' => 'All', 'EXP' => 'Expenses', 'PAY' => 'Payments', 'SAL' => 'Sales', 'MAN' => 'Manual']; ?>
        <select name="type"
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                       bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-50
                       focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= $h($key) ?>" <?= $type === $key ? 'selected' : '' ?>>
              <?= $h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
          Source
        </label>
        <?php $sources = ['' => 'All', 'payments' => 'Payments', 'expenses' => 'Expenses', 'sales' => 'Sales', 'manual' => 'Manual']; ?>
        <select name="source"
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                       bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-50
                       focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
          <?php foreach ($sources as $key => $label): ?>
            <option value="<?= $h($key) ?>" <?= $source === $key ? 'selected' : '' ?>>
              <?= $h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 rounded-lg border border-gray-300
                       bg-gray-50 hover:bg-gray-100 text-gray-800 text-sm">
          <i class="fa fa-filter text-xs" aria-hidden="true"></i>
          <span>Filter</span>
        </button>
        <a href="<?= $h($base) ?>/gl/journals"
           class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-transparent
                  text-xs text-gray-500 hover:text-gray-800">
          <i class="fa fa-rotate-left text-[11px]" aria-hidden="true"></i>
          <span>Reset</span>
        </a>
      </div>
    </form>

    <!-- Journals table -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <span><?= $count ?> journal<?= $count === 1 ? '' : 's' ?> found</span>
      </div>

      <?php if (empty($rows)): ?>
        <div class="py-10 flex flex-col items-center justify-center text-center gap-2 text-sm text-gray-500 dark:text-gray-400">
          <div class="h-10 w-10 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400">
            <i class="fa fa-book-open" aria-hidden="true"></i>
          </div>
          <p>No journals yet.</p>
          <p class="text-xs text-gray-400 dark:text-gray-500">
            As you post payments, expenses, sales, journals will appear here automatically.
          </p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-gray-800 dark:text-gray-100">
            <thead class="bg-gray-50 dark:bg-gray-800/70 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
              <tr>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Journal No</th>
                <th class="px-4 py-2 text-left">Type</th>
                <th class="px-4 py-2 text-left">Source</th>
                <th class="px-4 py-2 text-left">Memo</th>
                <th class="px-4 py-2 text-right">Debit</th>
                <th class="px-4 py-2 text-right">Credit</th>
                <th class="px-4 py-2 text-right">Lines</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
            <?php foreach ($rows as $j): ?>
              <?php
                $id      = (int)($j['id'] ?? $j['journal_id'] ?? 0);
                $date    = $j['jdate'] ?? $j['date'] ?? '';
                $date    = $date ? date('d M Y', strtotime((string)$date)) : '';
                $jno     = $j['jno'] ?? $j['journal_no'] ?? ('J#'.$id);
                $jtype   = strtoupper((string)($j['jtype'] ?? $j['type'] ?? ''));
                $src     = $j['source'] ?? $j['source_module'] ?? '';
                $memo    = $j['memo'] ?? '';
                $dr      = (float)($j['dr_total'] ?? $j['total_debit'] ?? 0);
                $cr      = (float)($j['cr_total'] ?? $j['total_credit'] ?? 0);
                $lines   = (int)($j['lines'] ?? $j['lines_count'] ?? 0);

                $typeBadge = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
                if ($jtype === 'EXP') $typeBadge = 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200';
                elseif ($jtype === 'PAY') $typeBadge = 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-200';
                elseif ($jtype === 'SAL') $typeBadge = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200';
                elseif ($jtype === 'MAN') $typeBadge = 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200';
              ?>
              <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/70">
                <td class="px-4 py-2 whitespace-nowrap"><?= $h($date) ?></td>
                <td class="px-4 py-2 whitespace-nowrap">
                  <a href="<?= $h($base) ?>/gl/journals/<?= $id ?>"
                     class="text-emerald-700 dark:text-emerald-300 hover:underline">
                    <?= $h($jno) ?>
                  </a>
                </td>
                <td class="px-4 py-2">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] <?= $typeBadge ?>">
                    <?= $h($jtype ?: '—') ?>
                  </span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                  <?= $h($src ?: '—') ?>
                </td>
                <td class="px-4 py-2 max-w-xs truncate text-xs text-gray-700 dark:text-gray-200">
                  <?= $h($memo) ?>
                </td>
                <td class="px-4 py-2 text-right"><?= number_format($dr, 2) ?></td>
                <td class="px-4 py-2 text-right"><?= number_format($cr, 2) ?></td>
                <td class="px-4 py-2 text-right text-xs text-gray-500 dark:text-gray-400">
                  <?= $lines ?: '—' ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>