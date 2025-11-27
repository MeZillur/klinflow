<?php
/**
 * BizFlow — Bulk item import preview
 *
 * Vars expected:
 * - array  $org
 * - string $module_base
 * - array  $rows      each row:
 *       [
 *         'line'     => int,
 *         'status'   => 'ok'|'warning'|'error',
 *         'messages' => string[]   // human friendly issues
 *         'data'     => [          // flat item fields
 *             'name'           => string,
 *             'code'           => string|null,
 *             'category_name'  => string|null,
 *             'item_type'      => 'product'|'service'|null,
 *             'unit'           => string|null,
 *             'purchase_price' => float|null,
 *             'selling_price'  => float|null,
 *             'track_inventory'=> int|null,
 *             'is_active'      => int|null,
 *         ]
 *       ]
 * - array  $stats     ['total'=>..,'ok'=>..,'warning'=>..,'error'=>..]
 * - string $filename
 * - string $bulk_token
 * - string $title
 */

$h        = $h ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base     = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$org      = $org ?? [];
$orgName  = trim((string)($org['name'] ?? ''));

$stats = array_merge(
    ['total' => 0, 'ok' => 0, 'warning' => 0, 'error' => 0],
    $stats ?? []
);

$hasErrors   = (int)$stats['error']   > 0;
$hasWarnings = (int)$stats['warning'] > 0;
$hasOk       = (int)$stats['ok']      > 0;

// NEW: importable = OK + Warning (only Error skipped)
$importableCount = (int)$stats['ok'] + (int)$stats['warning'];
$canImport       = $importableCount > 0;

$rows = $rows ?? [];
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-8">

  <!-- ============================================================
       1) Header + top actions
  ============================================================= -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="space-y-1">
      <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800">
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
        <span>Bulk import preview · BDT</span>
      </div>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
        <?= $h($title ?? 'Bulk item import — Preview') ?>
      </h1>
      <p class="text-sm text-slate-600">
        Preview for <span class="font-mono"><?= $h($filename ?? 'upload.csv') ?></span>
        — <?= $orgName !== '' ? $h($orgName) : 'this organisation' ?>.
      </p>
      <p class="mt-1 text-xs text-slate-500">
        <strong>OK</strong> and <strong>Warning</strong> rows will be imported.
        Only <span class="font-semibold text-red-700">Error</span> rows are skipped (they stay in your CSV so you can fix later).
      </p>
    </div>

    <div class="flex items-center gap-1 justify-end">
      <a href="<?= $h($base . '/items') ?>"
         class="px-3 py-1.5 text-xs sm:text-sm border border-slate-200 rounded-md hover:bg-slate-50 text-slate-700">
        Items list
      </a>
      <a href="<?= $h($base . '/items/create') ?>"
         class="px-3 py-1.5 text-xs sm:text-sm border border-slate-200 rounded-md hover:bg-slate-50 text-slate-700">
        New item
      </a>
      <a href="<?= $h($base . '/items/create') ?>#bulk"
         class="px-3 py-1.5 text-xs sm:text-sm border border-emerald-500 bg-emerald-500 text-white rounded-md">
        Back to bulk upload
      </a>
    </div>
  </div>

  <!-- ============================================================
       2) Summary strip + confirm actions
  ============================================================= -->
  <section class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
    <!-- Summary badges -->
    <div class="px-4 py-3 border-b border-slate-200 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-base font-semibold">Preview summary</h2>
        <p class="text-xs text-slate-500">
          Check each row before confirming. Rows marked
          <span class="font-semibold text-emerald-700">OK</span> and
          <span class="font-semibold text-amber-700">Warning</span> will be imported.
          Error rows are ignored.
        </p>
      </div>

      <div class="flex flex-wrap gap-2 text-xs">
        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-slate-50 border border-slate-200 text-slate-700">
          <span class="w-2 h-2 rounded-full bg-slate-400"></span>
          Total:
          <strong><?= (int)$stats['total'] ?></strong>
        </span>

        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          OK:
          <strong><?= (int)$stats['ok'] ?></strong>
        </span>

        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800">
          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          Warnings:
          <strong><?= (int)$stats['warning'] ?></strong>
        </span>

        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-red-50 border border-red-200 text-red-800">
          <span class="w-2 h-2 rounded-full bg-red-500"></span>
          Errors:
          <strong><?= (int)$stats['error'] ?></strong>
        </span>
      </div>
    </div>

    <?php if ((int)$stats['total'] === 0): ?>
      <div class="px-4 py-6 text-sm text-slate-600">
        No valid data rows detected in this file.
        Please go back and upload a CSV with at least one item row.
      </div>
    <?php else: ?>
      <!-- Confirm / CTA row -->
      <div class="px-4 py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100">
        <div class="space-y-1 text-xs text-slate-600">
          <?php if ($hasErrors): ?>
            <p>
              <span class="font-semibold text-red-700">Errors present:</span>
              these rows <strong>will not</strong> be imported. Fix them in Excel and upload again if needed.
            </p>
          <?php endif; ?>
          <?php if ($hasWarnings): ?>
            <p>
              <span class="font-semibold text-amber-700">Warnings:</span>
              rows will still import, but double-check prices, units and categories.
            </p>
          <?php endif; ?>
          <p>
            All prices are treated as <strong>Bangladeshi Taka (BDT)</strong> across BizFlow.
          </p>
        </div>

        <form action="<?= $h($base . '/items/bulk-commit') ?>"
              method="post"
              class="flex items-center gap-2">
          <input type="hidden" name="bulk_token" value="<?= $h($bulk_token) ?>">

          <button type="submit"
                  <?= $canImport ? '' : 'disabled' ?>
                  class="px-4 py-2 text-xs sm:text-sm rounded-md font-medium text-white shadow-sm transition
                         <?= $canImport
                              ? 'bg-emerald-600 hover:bg-emerald-700'
                              : 'bg-slate-400 cursor-not-allowed' ?>">
            Confirm &amp; import
            <span class="ml-1">
              (<?= $importableCount ?> row<?= $importableCount === 1 ? '' : 's' ?> without errors)
            </span>
          </button>
        </form>
      </div>

      <!-- ========================================================
           3) Detailed rows table (with red / green marks)
      ========================================================= -->
      <div class="px-4 pb-4 overflow-x-auto">
        <table class="min-w-full text-xs border border-slate-200 rounded-xl overflow-hidden">
          <thead class="bg-slate-50 text-slate-700 border-b border-slate-200">
            <tr>
              <th class="px-2 py-2 text-left font-semibold w-14">Line</th>
              <th class="px-2 py-2 text-left font-semibold w-28">Status</th>

              <!-- Item identity section -->
              <th class="px-2 py-2 text-left font-semibold">
                Item
                <span class="block text-[10px] font-normal text-slate-400">
                  name + code
                </span>
              </th>
              <th class="px-2 py-2 text-left font-semibold w-32">
                Type &amp; unit
              </th>

              <!-- Commercial section -->
              <th class="px-2 py-2 text-left font-semibold w-40">
                Category
              </th>
              <th class="px-2 py-2 text-right font-semibold w-32">
                Selling price (BDT)
              </th>

              <!-- Health / messages -->
              <th class="px-2 py-2 text-left font-semibold">
                Import notes
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
          <?php foreach ($rows as $row): ?>
            <?php
              $status   = strtolower((string)($row['status'] ?? 'ok'));
              $data     = is_array($row['data'] ?? null) ? $row['data'] : [];
              $messages = $row['messages'] ?? [];

              $line = (int)($row['line'] ?? 0);

              $name   = trim((string)($data['name'] ?? ''));
              $code   = trim((string)($data['code'] ?? ''));
              $cat    = trim((string)($data['category_name'] ?? ''));
              $type   = strtolower((string)($data['item_type'] ?? 'product'));
              $unit   = trim((string)($data['unit'] ?? ''));
              $sp     = $data['selling_price'] ?? null;
              $active = (int)($data['is_active'] ?? 1);
              $track  = (int)($data['track_inventory'] ?? 1);

              // Row colour + status pill
              $rowClass   = 'bg-white';
              $badgeClass = 'bg-emerald-50 text-emerald-800 border border-emerald-200';
              $badgeLabel = 'OK';
              $iconClass  = 'text-emerald-600';
              $iconName   = 'fa-check-circle';

              if ($status === 'warning') {
                  $rowClass   = 'bg-amber-50/20';
                  $badgeClass = 'bg-amber-50 text-amber-800 border border-amber-200';
                  $badgeLabel = 'Warning';
                  $iconClass  = 'text-amber-500';
                  $iconName   = 'fa-exclamation-circle';
              } elseif ($status === 'error') {
                  $rowClass   = 'bg-red-50/50';
                  $badgeClass = 'bg-red-50 text-red-800 border border-red-200';
                  $badgeLabel = 'Error';
                  $iconClass  = 'text-red-600';
                  $iconName   = 'fa-times-circle';
              }

              $typeLabel = $type === 'service' ? 'Service' : 'Product';
              $typePill  = $type === 'service'
                  ? 'bg-sky-50 text-sky-700 border border-sky-200'
                  : 'bg-emerald-50 text-emerald-700 border border-emerald-200';

              $spText = $sp !== null
                  ? number_format((float)$sp, 2, '.', ',')
                  : '';
            ?>
            <tr class="<?= $rowClass ?>">
              <!-- Line -->
              <td class="px-2 py-2 align-top text-slate-500 font-mono text-[11px]">
                <?= $line ?>
              </td>

              <!-- Status (red / amber / green) -->
              <td class="px-2 py-2 align-top">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] <?= $badgeClass ?>">
                  <i class="fa <?= $iconName ?> <?= $iconClass ?> text-[11px]"></i>
                  <?= $h($badgeLabel) ?>
                </span>
              </td>

              <!-- Item (name + code) -->
              <td class="px-2 py-2 align-top">
                <div class="flex flex-col">
                  <span class="text-slate-900 font-medium">
                    <?= $h($name !== '' ? $name : '— (no name)') ?>
                  </span>
                  <span class="mt-0.5 text-[11px] text-slate-500">
                    Code:
                    <span class="font-mono">
                      <?= $h($code !== '' ? $code : 'will auto-generate') ?>
                    </span>
                  </span>
                  <div class="mt-1 flex flex-wrap gap-1 text-[10px] text-slate-500">
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5">
                      <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                      <?= $active ? 'Active' : 'Inactive' ?>
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5">
                      <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                      <?= $track ? 'Track stock' : 'No stock tracking' ?>
                    </span>
                  </div>
                </div>
              </td>

              <!-- Type & unit section -->
              <td class="px-2 py-2 align-top">
                <div class="space-y-1">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium <?= $typePill ?>">
                    <?= $h($typeLabel) ?>
                  </span>
                  <div class="text-[11px] text-slate-600">
                    Unit:
                    <span class="font-mono">
                      <?= $h($unit !== '' ? $unit : '—') ?>
                    </span>
                  </div>
                </div>
              </td>

              <!-- Category -->
              <td class="px-2 py-2 align-top text-slate-700">
                <?= $cat !== '' ? $h($cat) : '<span class="text-slate-400">Uncategorised</span>' ?>
              </td>

              <!-- Selling price (BDT) -->
              <td class="px-2 py-2 align-top text-right">
                <?php if ($spText !== ''): ?>
                  <span class="inline-flex items-baseline gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-800 border border-emerald-100">
                    <span class="text-[10px] font-medium">BDT</span>
                    <span class="font-mono"><?= $h($spText) ?></span>
                  </span>
                <?php else: ?>
                  <span class="text-[11px] text-red-600">
                    Missing price
                  </span>
                <?php endif; ?>
              </td>

              <!-- Messages / issues -->
              <td class="px-2 py-2 align-top text-slate-700">
                <?php if (!empty($messages)): ?>
                  <ul class="list-disc list-inside space-y-0.5">
                    <?php foreach ($messages as $m): ?>
                      <li><?= $h($m) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <span class="text-[11px] text-slate-400">No issues detected</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <p class="mt-2 text-[11px] text-slate-500">
          Note: this preview may be limited to the first batch of rows (soft limit around 2,000 lines)
          to keep the page fast.
        </p>
      </div>
    <?php endif; ?>
  </section>

  <!-- ============================================================
       4) How to use this page
  ============================================================= -->
  <section class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3">
    <h3 class="text-sm font-semibold text-slate-800 mb-2">How to use this page</h3>
    <ol class="list-decimal list-inside text-xs text-slate-700 space-y-1.5">
      <li>
        Scan the coloured <strong>Status</strong> pills:
        <span class="text-emerald-700 font-medium">green = OK</span>,
        <span class="text-amber-700 font-medium">amber = warning</span>,
        <span class="text-red-700 font-medium">red = error</span>.
      </li>
      <li>
        Use the <strong>Import notes</strong> column to understand what went wrong
        (missing name, invalid prices, duplicate codes, etc.).
      </li>
      <li>
        When you are happy, click
        <span class="font-medium">“Confirm &amp; import”</span> —
        BizFlow will import all rows without errors (OK + Warning).
      </li>
      <li>
        To fix problem rows, correct them in your original Excel/CSV file,
        then go back to <span class="font-medium">Bulk upload</span> and upload again.
      </li>
      <li>
        Remember: all amounts here are <strong>BDT</strong>, so that quotes, orders, tenders
        and invoices stay consistent for your team.
      </li>
    </ol>
  </section>
</div>