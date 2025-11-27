<?php
$h    = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
$rows = $rows ?? [];
$q    = $q ?? '';
$from = $from ?? '';
$to   = $to ?? '';
$page = $page ?? 1;
$pages= $pages ?? 1;
$total= $total ?? 0;
?>
<div class="px-4 md:px-6 py-6 max-w-6xl mx-auto space-y-5">
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">GL Journals</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Source journals from the core accounting engine. Read-only, no delete.
      </p>
    </div>
    <a href="<?= $h($base) ?>/accounting/gl"
       class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left text-xs"></i> Back
    </a>
  </div>

  <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <input name="q" value="<?= $h($q) ?>" placeholder="Search (no, memo, type)"
           class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
    <input type="date" name="from" value="<?= $h($from) ?>"
           class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
    <input type="date" name="to" value="<?= $h($to) ?>"
           class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
    <button class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm">
      Filter
    </button>
  </form>

  <div class="text-xs text-gray-400">
    <?= (int)$total ?> journals found. Page <?= (int)$page ?> / <?= (int)$pages ?>.
  </div>

  <div class="rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-900">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">No</th>
          <th class="px-3 py-2 text-left">Type</th>
          <th class="px-3 py-2 text-left">Memo</th>
          <th class="px-3 py-2 text-left"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
            No journals found.
          </td>
        </tr>
      <?php else: foreach ($rows as $j): ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
          <td class="px-3 py-2 whitespace-nowrap">
            <?= $h(isset($j['jdate']) ? date('d M Y', strtotime((string)$j['jdate'])) : '') ?>
          </td>
          <td class="px-3 py-2 whitespace-nowrap"><?= $h($j['jno'] ?? '') ?></td>
          <td class="px-3 py-2 whitespace-nowrap text-xs"><?= $h($j['jtype'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($j['memo'] ?? '') ?></td>
          <td class="px-3 py-2 text-right">
            <a href="<?= $h($base) ?>/accounting/gl/journals/<?= (int)($j['id'] ?? 0) ?>"
               class="text-xs text-emerald-700 dark:text-emerald-300 underline">
              View
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="mt-4 flex flex-wrap gap-2">
      <?php for ($p=1; $p<=$pages; $p++): ?>
        <a href="?<?= http_build_query(['q'=>$q,'from'=>$from,'to'=>$to,'page'=>$p]) ?>"
           class="px-3 py-1 rounded-lg border text-xs
                  <?= $p===$page ? 'bg-gray-100 dark:bg-gray-800' : 'bg-white dark:bg-gray-900' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>