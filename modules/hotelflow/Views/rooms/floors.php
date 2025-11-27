<?php
/** @var array $rows */
/** @var array $schema */
/** @var string $module_base */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base      = isset($module_base) ? rtrim((string)$module_base, '/') : '/t/hotel/apps/hotelflow';
$hasTable  = (bool)($schema['table']  ?? false);
$hasName   = (bool)($schema['name']   ?? false);
$hasLabel  = (bool)($schema['label']  ?? false);
$hasSort   = (bool)($schema['sort']   ?? false);
?>
<div class="p-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Floors</h1>
      <p class="mt-1 text-sm text-slate-600">
        Define your floor labels (e.g. <strong>L1 / Ground</strong>, <strong>1st</strong>, <strong>Rooftop</strong>) so rooms and housekeeping
        are easy to read at the front desk and on reports.
      </p>
    </div>

    <?php if ($hasTable): ?>
      <div class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800 border border-emerald-100">
        <i class="fa-solid fa-layer-group text-[13px]"></i>
        <span>
          Floors are shared across reservations, housekeeping and room status.
        </span>
      </div>
    <?php else: ?>
      <div class="inline-flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 border border-amber-100">
        <i class="fa-solid fa-circle-info text-[13px]"></i>
        <span>
          No <code>hms_floors</code> table detected. Showing floors derived from rooms (read-only).
        </span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Main layout: form + list -->
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.3fr)] items-start">

    <!-- Left: Add / edit panel -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5">
      <div class="flex items-center justify-between gap-2 mb-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Add floor</h2>
          <p class="text-xs text-slate-500">
            Use short codes for <em>Name</em> and friendly text for <em>Label</em>.
          </p>
        </div>
      </div>

      <?php if ($hasTable): ?>
        <form method="post" action="<?= $h($base) ?>/rooms/floors" class="space-y-4">
          <!-- Horizontal row -->
          <div class="flex flex-col xl:flex-row gap-3">
            <?php if ($hasName): ?>
              <div class="flex-1 min-w-[120px]">
                <label class="block text-xs font-medium text-slate-700 mb-1">
                  Name (code)
                </label>
                <input
                  type="text"
                  name="name"
                  placeholder="L1 / Ground"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
                <p class="mt-1 text-[11px] text-slate-500">
                  Short technical code used internally.
                </p>
              </div>
            <?php endif; ?>

            <?php if ($hasLabel): ?>
              <div class="flex-[1.4] min-w-[160px]">
                <label class="block text-xs font-medium text-slate-700 mb-1">
                  Label (display)
                </label>
                <input
                  type="text"
                  name="label"
                  placeholder="Ground Floor / 1st Floor"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
                <p class="mt-1 text-[11px] text-slate-500">
                  What staff will actually see on screen.
                </p>
              </div>
            <?php endif; ?>

            <?php if ($hasSort): ?>
              <div class="w-full xl:w-24">
                <label class="block text-xs font-medium text-slate-700 mb-1">
                  Order
                </label>
                <input
                  type="number"
                  name="sort_order"
                  value="0"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
                <p class="mt-1 text-[11px] text-slate-500">
                  Lower = appears first.
                </p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 mt-3">
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-full bg-[#228B22] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-500"
            >
              <i class="fa-solid fa-plus text-[11px]"></i>
              Add Floor
            </button>
          </div>
        </form>
      <?php else: ?>
        <p class="text-sm text-slate-600">
          Floors are currently derived from room records. Once you add the <code>hms_floors</code>
          table, this panel will let you create and manage official floor definitions.
        </p>
      <?php endif; ?>
    </div>

    <!-- Right: Floors table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Existing floors</h2>
          <p class="text-xs text-slate-500">
            Total: <?= count($rows) ?> floor<?= count($rows) === 1 ? '' : 's' ?>.
          </p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50/60">
            <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
              <th class="px-4 py-2 w-16">ID</th>
              <?php if ($hasName): ?>
                <th class="px-4 py-2">Name</th>
              <?php endif; ?>
              <?php if ($hasLabel): ?>
                <th class="px-4 py-2">Label</th>
              <?php endif; ?>
              <?php if ($hasSort): ?>
                <th class="px-4 py-2 w-24 text-right">Order</th>
              <?php endif; ?>
              <th class="px-4 py-2 w-24 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (!$rows): ?>
              <tr>
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">
                  No floors defined yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $id    = (int)($row['id'] ?? 0);
                  $name  = (string)($row['name']  ?? ($row['derived'] ?? false ? '—' : ''));
                  $label = (string)($row['label'] ?? '');
                  $sort  = (string)($row['sort_order'] ?? '');
                  $derived = !empty($row['derived']);
                ?>
                <tr class="hover:bg-slate-50/70">
                  <td class="px-4 py-2 text-xs text-slate-500">#<?= $h($id) ?></td>

                  <?php if ($hasName): ?>
                    <td class="px-4 py-2 text-slate-900">
                      <?= $h($name !== '' ? $name : '—') ?>
                      <?php if ($derived): ?>
                        <span class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                          Derived
                        </span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>

                  <?php if ($hasLabel): ?>
                    <td class="px-4 py-2 text-slate-700">
                      <?= $h($label !== '' ? $label : '—') ?>
                    </td>
                  <?php endif; ?>

                  <?php if ($hasSort): ?>
                    <td class="px-4 py-2 text-right text-slate-700">
                      <?= $h($sort !== '' ? $sort : '0') ?>
                    </td>
                  <?php endif; ?>

                  <td class="px-4 py-2 text-right">
                    <?php if ($hasTable && !$derived): ?>
                      <form method="post"
                            action="<?= $h($base) ?>/rooms/floors/<?= $id ?>/delete"
                            class="inline-block"
                            onsubmit="return confirm('Delete this floor? This will not delete any rooms, only the label.');">
                        <button
                          type="submit"
                          class="inline-flex items-center gap-1 rounded-full border border-slate-300 px-3 py-1 text-[11px] font-medium text-slate-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700"
                        >
                          <i class="fa-regular fa-trash-can text-[11px]"></i>
                          Delete
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-[11px] text-slate-400">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- How to use this page -->
  <div class="mt-4 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-4 text-sm text-slate-700 space-y-2">
    <div class="flex items-center gap-2">
      <i class="fa-regular fa-lightbulb text-amber-500 text-base"></i>
      <h2 class="text-sm font-semibold text-slate-900">
        How to use the Floors page
      </h2>
    </div>
    <ul class="list-disc list-inside text-[13px] leading-relaxed text-slate-700">
      <li><strong>Name</strong> is a short code (e.g. <code>L1</code>, <code>G</code>, <code>R</code>) used in exports and APIs.</li>
      <li><strong>Label</strong> is what front desk and housekeeping will see (e.g. “Ground Floor / Lobby Level”).</li>
      <li><strong>Order</strong> controls the sorting in room lists and dashboards (0 = appears at the top).</li>
      <li>After creating floors, go to the <strong>Rooms</strong> page and assign each room to the correct floor.</li>
      <li>If you later change a label, existing rooms and reservations will automatically show the new label.</li>
    </ul>
  </div>
</div>