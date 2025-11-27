<?php
/**
 * Warehouse profile
 *
 * Expects:
 * - array  $org
 * - string $module_base
 * - array  $warehouse
 * - array  $inv_stats
 * - array  $recent_moves
 */

$h    = $h ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName = trim((string)($org['name'] ?? ''));
$id   = (int)($warehouse['id'] ?? 0);
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div>
      <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Warehouse</p>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight flex items-center gap-2">
        <?= $h($warehouse['name'] ?? '') ?>
        <?php if (!empty($warehouse['is_primary'])): ?>
          <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
            Primary
          </span>
        <?php endif; ?>
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        Code <span class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded"><?= $h($warehouse['code'] ?? '') ?></span>
        · <?= $h($warehouse['type'] ?? '') ?> · <?= $orgName !== '' ? $h($orgName) : 'Organisation' ?>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 justify-end text-xs sm:text-sm">
      <a href="<?= $h($base . '/warehouse') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Back to list</a>
      <a href="<?= $h($base . '/warehouse/' . $id . '/edit') ?>"
         class="px-3 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
        Edit warehouse
      </a>
    </div>
  </div>

  <!-- Top metrics -->
  <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="border border-slate-200 rounded-lg p-4 bg-white">
      <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Tracked SKUs</p>
      <p class="mt-2 text-2xl font-semibold"><?= (int)($inv_stats['sku_count'] ?? 0) ?></p>
      <p class="mt-1 text-xs text-slate-500">Number of unique items with movements in this warehouse.</p>
    </div>
    <div class="border border-slate-200 rounded-lg p-4 bg-white">
      <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Inventory movements</p>
      <p class="mt-2 text-2xl font-semibold"><?= (int)($inv_stats['move_count'] ?? 0) ?></p>
      <p class="mt-1 text-xs text-slate-500">Total posted movements logged for this location.</p>
    </div>
    <div class="border border-slate-200 rounded-lg p-4 bg-white">
      <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Last movement</p>
      <p class="mt-2 text-lg font-semibold">
        <?= $h($inv_stats['last_movement'] ?? '—') ?>
      </p>
      <p class="mt-1 text-xs text-slate-500">Date of the most recent inventory entry.</p>
    </div>
  </section>

  <!-- Details + address -->
  <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2 bg-white border border-slate-200 rounded-lg p-4">
      <h2 class="text-sm font-semibold text-slate-800 mb-3">Location details</h2>
      <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
        <div>
          <dt class="text-xs font-medium text-slate-500 uppercase">Type</dt>
          <dd class="mt-0.5 text-slate-800"><?= $h($warehouse['type'] ?? '') ?></dd>
        </div>
        <div>
          <dt class="text-xs font-medium text-slate-500 uppercase">Status</dt>
          <dd class="mt-0.5 text-slate-800">
            <?= !empty($warehouse['is_active']) ? 'Active' : 'Inactive' ?>
          </dd>
        </div>
        <div>
          <dt class="text-xs font-medium text-slate-500 uppercase">Contact person</dt>
          <dd class="mt-0.5 text-slate-800"><?= $h($warehouse['contact_name'] ?? '—') ?></dd>
        </div>
        <div>
          <dt class="text-xs font-medium text-slate-500 uppercase">Phone</dt>
          <dd class="mt-0.5 text-slate-800"><?= $h($warehouse['phone'] ?? '—') ?></dd>
        </div>
        <div>
          <dt class="text-xs font-medium text-slate-500 uppercase">Email</dt>
          <dd class="mt-0.5 text-slate-800"><?= $h($warehouse['email'] ?? '—') ?></dd>
        </div>
        <div>
          <dt class="text-xs font-medium text-slate-500 uppercase">Geo (lat, lng)</dt>
          <dd class="mt-0.5 text-slate-800">
            <?php if (!empty($warehouse['lat']) || !empty($warehouse['lng'])): ?>
              <?= $h($warehouse['lat'] . ', ' . $warehouse['lng']) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </dd>
        </div>
      </dl>

      <div class="mt-4 border-t border-slate-100 pt-3 text-sm">
        <h3 class="text-xs font-medium text-slate-500 uppercase mb-1">Address</h3>
        <p class="text-slate-800">
          <?php
          $parts = [];
          foreach (['address_line1','address_line2','city','district','state','postcode','country'] as $key) {
              if (!empty($warehouse[$key])) $parts[] = $warehouse[$key];
          }
          echo $h($parts ? implode(', ', $parts) : '—');
          ?>
        </p>
      </div>

      <?php if (!empty($warehouse['notes'])): ?>
        <div class="mt-4 border-t border-slate-100 pt-3 text-sm">
          <h3 class="text-xs font-medium text-slate-500 uppercase mb-1">Notes</h3>
          <p class="text-slate-800 whitespace-pre-line">
            <?= $h($warehouse['notes']) ?>
          </p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick meta -->
    <div class="bg-white border border-slate-200 rounded-lg p-4 text-sm space-y-2">
      <h2 class="text-sm font-semibold text-slate-800 mb-2">Meta</h2>
      <p class="text-xs text-slate-500">
        Created at: <span class="text-slate-800"><?= $h($warehouse['created_at'] ?? '') ?></span>
      </p>
      <p class="text-xs text-slate-500">
        Updated at: <span class="text-slate-800"><?= $h($warehouse['updated_at'] ?? '') ?></span>
      </p>
    </div>
  </section>

  <!-- Recent inventory movements -->
  <section class="bg-white border border-slate-200 rounded-lg p-4">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-sm font-semibold text-slate-800">Recent inventory movements</h2>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase tracking-wide">
          <tr class="text-left">
            <th class="px-3 py-2">Date</th>
            <th class="px-3 py-2">Item</th>
            <th class="px-3 py-2">Type</th>
            <th class="px-3 py-2 text-right">Quantity</th>
            <th class="px-3 py-2">Reference</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php if (empty($recent_moves)): ?>
          <tr>
            <td colspan="5" class="px-3 py-4 text-center text-sm text-slate-500">
              No movements recorded yet for this warehouse.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recent_moves as $m): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-3 py-2 text-xs text-slate-600">
                <?= $h($m['date'] ?? '') ?>
              </td>
              <td class="px-3 py-2 text-xs">
                <?php if (!empty($m['item_code']) || !empty($m['item_name'])): ?>
                  <span class="font-mono"><?= $h($m['item_code'] ?? '') ?></span>
                  <?php if (!empty($m['item_name'])): ?>
                    <span class="text-slate-500 ml-1">— <?= $h($m['item_name']) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 text-xs text-slate-600">
                <?= $h($m['move_type'] ?? '') ?>
              </td>
              <td class="px-3 py-2 text-xs text-right font-mono">
                <?= $h($m['qty'] ?? '') ?>
              </td>
              <td class="px-3 py-2 text-xs text-slate-600">
                <?= $h($m['reference'] ?? $m['note'] ?? '') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- How to use this page -->
  <section class="mt-4 border border-emerald-100 bg-emerald-50/60 text-emerald-900 rounded-lg p-4 text-sm">
    <h2 class="font-semibold mb-1">How to use this page</h2>
    <ul class="list-disc pl-5 space-y-1 text-xs sm:text-sm">
      <li>Check basic warehouse details before using it in purchases, transfers, or adjustments.</li>
      <li>Use the <strong>Edit warehouse</strong> button to correct address or contact details without affecting past documents.</li>
      <li>Review <strong>Recent inventory movements</strong> to investigate stock discrepancies or unexpected transfers.</li>
    </ul>
  </section>

</div>