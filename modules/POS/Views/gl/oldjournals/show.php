<?php
/**
 * GL Journal details
 *
 * Expected:
 *  - $base     string
 *  - $journal  array  (single header row)
 *  - $lines    array  (entries)
 */

$h       = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base    = $base    ?? '/apps/pos';
$journal = $journal ?? [];
$lines   = $lines   ?? [];

$id      = (int)($journal['id'] ?? $journal['journal_id'] ?? 0);
$jno     = $journal['jno']      ?? $journal['journal_no'] ?? ('J#'.$id);
$jdate   = $journal['jdate']    ?? $journal['date'] ?? '';
$jdate   = $jdate ? date('d M Y', strtotime((string)$jdate)) : '';
$jtype   = strtoupper((string)($journal['jtype'] ?? $journal['type'] ?? ''));
$memo    = $journal['memo']     ?? '';
$source  = $journal['source']   ?? $journal['source_module'] ?? '';
$created = $journal['created_at'] ?? '';
$user    = $journal['created_by_name'] ?? $journal['created_by'] ?? '';
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-5xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
      <div>
        <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">GL Journal</p>
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-book text-emerald-500" aria-hidden="true"></i>
          <span><?= $h($jno) ?></span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          <?= $h($jdate) ?> · Type: <?= $h($jtype ?: '—') ?> · Source: <?= $h($source ?: '—') ?>
        </p>
      </div>
      <a href="<?= $h($base) ?>/gl/journals"
         class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700
                text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900
                hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-arrow-left text-xs" aria-hidden="true"></i>
        <span>Back to Journals</span>
      </a>
    </div>

    <!-- Meta -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Memo</div>
        <div class="text-gray-800 dark:text-gray-100 min-h-[1.5rem]">
          <?= $memo !== '' ? $h($memo) : '<span class="text-gray-400 dark:text-gray-500">—</span>' ?>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Created</div>
        <div class="text-gray-800 dark:text-gray-100 min-h-[1.5rem]">
          <?= $created ? $h($created) : '—' ?>
        </div>
        <?php if ($user): ?>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            by <?= $h($user) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
        <?php
          $dr = 0.0; $cr = 0.0;
          foreach ($lines as $ln) {
              $dr += (float)($ln['dr'] ?? $ln['debit'] ?? 0);
              $cr += (float)($ln['cr'] ?? $ln['credit'] ?? 0);
          }
        ?>
        <div class="flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
          <span>Totals</span>
          <span>Difference</span>
        </div>
        <div class="flex items-center justify-between">
          <div class="text-sm">
            <div class="text-emerald-700 dark:text-emerald-300">
              Dr <?= number_format($dr, 2) ?>
            </div>
            <div class="text-sky-700 dark:text-sky-300">
              Cr <?= number_format($cr, 2) ?>
            </div>
          </div>
          <?php $diff = $dr - $cr; ?>
          <div class="text-right text-base font-semibold <?= abs($diff) < 0.01 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' ?>">
            <?= number_format($diff, 2) ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Lines table -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
        <?= count($lines) ?> line<?= count($lines) === 1 ? '' : 's' ?>
      </div>

      <?php if (empty($lines)): ?>
        <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
          No entries for this journal.
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-gray-800 dark:text-gray-100">
            <thead class="bg-gray-50 dark:bg-gray-800/70 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
              <tr>
                <th class="px-4 py-2 text-left">Account</th>
                <th class="px-4 py-2 text-left">Code</th>
                <th class="px-4 py-2 text-left">Line Memo</th>
                <th class="px-4 py-2 text-right">Debit</th>
                <th class="px-4 py-2 text-right">Credit</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
            <?php foreach ($lines as $ln): ?>
              <?php
                $code = $ln['code'] ?? $ln['account_code'] ?? '';
                $name = $ln['account_name'] ?? $ln['name'] ?? '';
                $lm   = $ln['memo'] ?? '';
                $dr   = (float)($ln['dr'] ?? $ln['debit'] ?? 0);
                $cr   = (float)($ln['cr'] ?? $ln['credit'] ?? 0);
              ?>
              <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/70">
                <td class="px-4 py-2"><?= $h($name ?: '—') ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                  <?= $h($code ?: '—') ?>
                </td>
                <td class="px-4 py-2 text-xs text-gray-700 dark:text-gray-200">
                  <?= $h($lm) ?>
                </td>
                <td class="px-4 py-2 text-right"><?= number_format($dr, 2) ?></td>
                <td class="px-4 py-2 text-right"><?= number_format($cr, 2) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>