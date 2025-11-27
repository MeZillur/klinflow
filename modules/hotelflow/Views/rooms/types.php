<?php
/** @var array  $rows */
/** @var array  $schema */
/** @var string $module_base */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base     = isset($module_base) ? rtrim((string)$module_base, '/') : '/t/hotel/apps/hotelflow';
$hasTable = (bool)($schema['exists']      ?? false);
$hasName  = (bool)($schema['name']        ?? false);
$hasDesc  = (bool)($schema['description'] ?? false);
?>
<div class="p-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Room Types</h1>
      <p class="mt-1 text-sm text-slate-600">
        Define sellable room categories like <strong>Deluxe King</strong>, <strong>Twin</strong>, <strong>Suite</strong>.
        These will be used on reservations, ARI and reports.
      </p>
    </div>

    <div class="inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800 border border-emerald-100">
      <i class="fa-solid fa-bed text-[13px]"></i>
      <span>
        Room types are the backbone of your pricing & availability.
      </span>
    </div>
  </div>

  <!-- Main layout: Add panel + list -->
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.3fr)] items-start">

    <!-- Left: Add new room type -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5">
      <div class="flex items-center justify-between gap-2 mb-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Add room type</h2>
          <p class="text-xs text-slate-500">
            Keep names short and clear; use description for marketing text.
          </p>
        </div>
      </div>

      <?php if ($hasTable): ?>
        <form method="post" action="<?= $h($base) ?>/rooms/types" class="space-y-4">
          <div class="flex flex-col xl:flex-row gap-3">
            <?php if ($hasName): ?>
              <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-slate-700 mb-1">
                  Name
                </label>
                <input
                  type="text"
                  name="name"
                  placeholder="Deluxe King"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
                <p class="mt-1 text-[11px] text-slate-500">
                  What the guest and staff see everywhere.
                </p>
              </div>
            <?php endif; ?>

            <?php if ($hasDesc): ?>
              <div class="flex-[1.4] min-w-[200px]">
                <label class="block text-xs font-medium text-slate-700 mb-1">
                  Description (optional)
                </label>
                <input
                  type="text"
                  name="description"
                  placeholder="Spacious king room with city view"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
                <p class="mt-1 text-[11px] text-slate-500">
                  Short selling text for OTA / confirmation emails.
                </p>
              </div>
            <?php endif; ?>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 mt-3">
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-full bg-[#228B22] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-500"
            >
              <i class="fa-solid fa-plus text-[11px]"></i>
              Add Type
            </button>
          </div>
        </form>
      <?php else: ?>
        <p class="text-sm text-slate-600">
          <code>hms_room_types</code> table not found. Once the schema is present, this panel will
          let you define room type catalogue used across reservations and ARI.
        </p>
      <?php endif; ?>
    </div>

    <!-- Right: Existing room types table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Existing room types</h2>
          <p class="text-xs text-slate-500">
            Total: <?= count($rows) ?> type<?= count($rows) === 1 ? '' : 's' ?>.
          </p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50/60">
            <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
              <th class="px-4 py-2 w-16">ID</th>
              <?php if ($hasName): ?>
                <th class="px-4 py-2 w-[30%]">Name</th>
              <?php endif; ?>
              <?php if ($hasDesc): ?>
                <th class="px-4 py-2">Description</th>
              <?php endif; ?>
              <th class="px-4 py-2 w-32 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (!$rows): ?>
              <tr>
                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                  No room types defined yet. Start by adding at least one standard room (e.g. Deluxe King).
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $id   = (int)($row['id']   ?? 0);
                  $name = (string)($row['name'] ?? '');
                  $desc = (string)($row['description'] ?? '');
                ?>
                <tr class="hover:bg-slate-50/70">
                  <td class="px-4 py-2 text-xs text-slate-500">#<?= $h($id) ?></td>

                  <?php if ($hasName): ?>
                    <td class="px-4 py-2 align-top">
                      <form method="post" action="<?= $h($base) ?>/rooms/types/<?= $id ?>/update" class="space-y-1">
                        <input
                          type="text"
                          name="name"
                          value="<?= $h($name) ?>"
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                    <?php endif; ?>

                  <?php if ($hasDesc): ?>
                    <?php if ($hasName): ?>
                      <!-- continue same form row when both fields exist -->
                      </td>
                      <td class="px-4 py-2 align-top">
                        <input
                          type="text"
                          name="description"
                          value="<?= $h($desc) ?>"
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                      </td>
                    <?php else: ?>
                      <td class="px-4 py-2 align-top">
                        <form method="post" action="<?= $h($base) ?>/rooms/types/<?= $id ?>/update" class="space-y-1">
                          <input
                            type="text"
                            name="description"
                            value="<?= $h($desc) ?>"
                            class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                          >
                      </td>
                    <?php endif; ?>
                  <?php endif; ?>

                  <td class="px-4 py-2 align-top text-right">
                    <?php if ($hasTable): ?>
                      <div class="flex justify-end gap-2">
                        <!-- Save -->
                        <button
                          type="submit"
                          class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-slate-900"
                        >
                          <i class="fa-regular fa-floppy-disk text-[11px]"></i>
                          Save
                        </button>
                        </form>

                        <!-- Delete -->
                        <form method="post"
                              action="<?= $h($base) ?>/rooms/types/<?= $id ?>/delete"
                              onsubmit="return confirm('Delete this room type? Existing rooms & reservations linked to this type may need to be updated manually.');">
                          <button
                            type="submit"
                            class="inline-flex items-center gap-1 rounded-full border border-rose-200 px-3 py-1.5 text-[11px] font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-rose-400"
                          >
                            <i class="fa-regular fa-trash-can text-[11px]"></i>
                            Delete
                          </button>
                        </form>
                      </div>
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
        How to use the Room Types page
      </h2>
    </div>
    <ul class="list-disc list-inside text-[13px] leading-relaxed text-slate-700">
      <li>Create 3–6 clear room types (e.g. <strong>Standard</strong>, <strong>Deluxe King</strong>, <strong>Suite</strong>) instead of many tiny variations.</li>
      <li>Use <strong>Name</strong> for short labels and <strong>Description</strong> for extra selling points (view, size, benefits).</li>
      <li>Every room and every rate plan should be attached to one room type for clean ARI & reporting.</li>
      <li>You can safely edit the description later; existing reservations will display the new text automatically.</li>
      <li>Deleting a room type is advanced — only do it when no rooms/rates depend on it.</li>
    </ul>
  </div>
</div>