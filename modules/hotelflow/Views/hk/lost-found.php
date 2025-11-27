<?php
declare(strict_types=1);

/** @var array  $rows */
/** @var array  $schema */
/** @var string $module_base */

$h    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = rtrim($module_base ?? '/apps/hotelflow', '/');
$today = date('Y-m-d');

$stats = [
    'total'    => count($rows),
    'logged'   => 0,
    'returned' => 0,
    'discarded'=> 0,
    'today'    => 0,
];

foreach ($rows as $r) {
    $status = strtolower((string)($r['status'] ?? 'logged'));
    if ($status === 'returned') {
        $stats['returned']++;
    } elseif ($status === 'discarded') {
        $stats['discarded']++;
    } else {
        $stats['logged']++;
    }

    $d = (string)($r['date_found'] ?? substr((string)($r['created_at'] ?? ''), 0, 10));
    if ($d === $today) {
        $stats['today']++;
    }
}
?>
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

  <!-- Header / hero -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">Housekeeping</p>
      <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-slate-900">
        Lost &amp; Found Control Center
      </h1>
      <p class="mt-1 text-sm text-slate-600 max-w-xl">
        Log items in seconds, track status, and hand them back to guests without losing the audit trail.
      </p>
    </div>
    <div class="flex items-center gap-3">
      <div class="hidden sm:flex flex-col text-right text-xs text-slate-500">
        <span>Hotel date</span>
        <span class="font-semibold text-slate-800"><?= $h($today) ?></span>
      </div>
      <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 border border-emerald-100 shadow-sm">
        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
        HK Board Online
      </span>
    </div>
  </div>

  <!-- Metrics strip -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm px-4 py-3">
      <p class="text-xs text-slate-500">Items logged (all time)</p>
      <p class="mt-1 text-xl font-semibold text-slate-900"><?= $h((string)$stats['total']) ?></p>
    </div>
    <div class="rounded-2xl bg-emerald-50 border border-emerald-100 shadow-sm px-4 py-3">
      <p class="text-xs text-emerald-700">Active (waiting with hotel)</p>
      <p class="mt-1 text-xl font-semibold text-emerald-900"><?= $h((string)$stats['logged']) ?></p>
    </div>
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm px-4 py-3">
      <p class="text-xs text-slate-500">Returned to guest</p>
      <p class="mt-1 text-xl font-semibold text-slate-900"><?= $h((string)$stats['returned']) ?></p>
    </div>
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm px-4 py-3">
      <p class="text-xs text-slate-500">Logged today</p>
      <p class="mt-1 text-xl font-semibold text-slate-900"><?= $h((string)$stats['today']) ?></p>
    </div>
  </div>

  <!-- Main two-column layout -->
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,2fr)] items-start">

    <!-- New item form -->
    <section class="rounded-3xl bg-white border border-slate-100 shadow-sm p-5 sm:p-6 space-y-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-base font-semibold text-slate-900">Log a new item</h2>
          <p class="mt-1 text-xs text-slate-500">
            Capture enough detail now so future night audits and security checks are painless.
          </p>
        </div>
        <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-1 text-[10px] font-medium text-slate-500 border border-slate-200">
          #<?= $h((string)($stats['total'] + 1)) ?> next
        </span>
      </div>

      <form action="<?= $h($base . '/housekeeping/lost-found') ?>"
            method="post"
            enctype="multipart/form-data"
            class="space-y-4">

        <!-- Row: date + room -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Date found
            </label>
            <input type="date"
                   name="date_found"
                   value="<?= $h($today) ?>"
                   class="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Room / location tag
              <span class="text-[10px] text-slate-400">(search by room)</span>
            </label>
            <div class="mt-1 relative">
              <input id="lf-room"
                     type="text"
                     name="room_no"
                     autocomplete="off"
                     data-kf-lookup="rooms"
                     placeholder="e.g. 504 • Deluxe King"
                     class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
            </div>
          </div>
        </div>

        <!-- Row: place + item -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Found at
            </label>
            <input type="text"
                   name="place"
                   placeholder="Frontdesk, corridor, restaurant, lobby..."
                   class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Item
            </label>
            <input type="text"
                   name="item"
                   placeholder="Laptop bag, passport, wallet, room key..."
                   class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-semibold text-slate-700">
            Description / unique identifiers
          </label>
          <textarea name="description"
                    rows="3"
                    placeholder="Colour, brand, identifiers, where exactly it was found..."
                    class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50"></textarea>
        </div>

        <!-- Row: guest + contact -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Guest (optional)
              <span class="text-[10px] text-slate-400">(lookup)</span>
            </label>
            <div class="mt-1 relative">
              <input id="lf-guest"
                     type="text"
                     name="guest_name"
                     autocomplete="off"
                     data-kf-lookup="guests"
                     placeholder="Search guest name or room..."
                     class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Guest contact
            </label>
            <input type="text"
                   name="contact"
                   placeholder="+8801..., email, WeChat ID etc."
                   class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
          </div>
        </div>

        <!-- Row: found by + status -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Found by (staff)
              <span class="text-[10px] text-slate-400">(lookup)</span>
            </label>
            <div class="mt-1 relative">
              <input id="lf-staff"
                     type="text"
                     name="found_by"
                     autocomplete="off"
                     data-kf-lookup="staff"
                     placeholder="Search staff name / ID..."
                     class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Status
            </label>
            <select name="status"
                    class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50">
              <option value="logged">Logged / in custody</option>
              <option value="returned">Returned to guest</option>
              <option value="discarded">Discarded / destroyed</option>
            </select>
          </div>
        </div>

        <!-- Row: storage location + photo -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Storage location
            </label>
            <input type="text"
                   name="location"
                   placeholder="Safe no., locker, HK office shelf..."
                   class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700">
              Attach photo / document
              <span class="text-[10px] text-slate-400">(jpg, png, pdf, max 5&nbsp;MB)</span>
            </label>
            <input type="file"
                   name="photo"
                   class="mt-1 block w-full text-xs text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-800 hover:file:bg-emerald-100" />
          </div>
        </div>

        <div class="flex items-center justify-between pt-2">
          <p class="text-[11px] text-slate-400">
            Submitting will create a time-stamped record against this hotel.
          </p>
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50">
            <span>Save to Lost &amp; Found</span>
          </button>
        </div>
      </form>
    </section>

    <!-- Existing items list -->
    <section class="rounded-3xl bg-white border border-slate-100 shadow-sm p-5 sm:p-6 space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 class="text-base font-semibold text-slate-900">Registry</h2>
          <p class="mt-1 text-xs text-slate-500">
            Latest <?= $h((string)count($rows)) ?> items for this property. Use quick search to filter.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <input id="lf-search"
                 type="search"
                 placeholder="Search by room, item, guest, status..."
                 class="block w-48 sm:w-56 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 focus:ring-offset-emerald-50" />
        </div>
      </div>

      <?php if (empty($schema['lf'])): ?>
        <div class="mt-3 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs text-amber-800">
          <strong>Heads up:</strong> lost &amp; found table is not provisioned yet. The form will not persist data
          until the schema is created.
        </div>
      <?php endif; ?>

      <div class="mt-2 overflow-hidden rounded-2xl border border-slate-100 bg-slate-50">
        <div class="max-h-[480px] overflow-auto">
          <table class="min-w-full text-xs">
            <thead class="bg-slate-100/70 text-slate-500 sticky top-0 z-10">
            <tr>
              <th class="px-3 py-2 text-left font-semibold">Item</th>
              <th class="px-3 py-2 text-left font-semibold">Room / place</th>
              <th class="px-3 py-2 text-left font-semibold">Guest</th>
              <th class="px-3 py-2 text-left font-semibold">Status</th>
              <th class="px-3 py-2 text-left font-semibold">Found</th>
              <th class="px-3 py-2 text-right font-semibold">Actions</th>
            </tr>
            </thead>
            <tbody id="lf-table-body" class="divide-y divide-slate-100 bg-white">
            <?php if (!$rows): ?>
              <tr>
                <td colspan="6" class="px-3 py-6 text-center text-xs text-slate-400">
                  No items logged yet. First item you enter will appear here instantly.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): 
                $status = strtolower((string)($row['status'] ?? 'logged'));
                $badgeClasses = match ($status) {
                    'returned'  => 'bg-emerald-50 text-emerald-800 border-emerald-100',
                    'discarded' => 'bg-slate-100 text-slate-700 border-slate-200',
                    default     => 'bg-amber-50 text-amber-800 border-amber-100',
                };
                $dateFound = (string)($row['date_found'] ?? substr((string)($row['created_at'] ?? ''), 0, 10));
              ?>
              <tr class="hover:bg-emerald-50/30 transition-colors"
                  data-search="<?= $h(strtolower(
                      ($row['room_no'] ?? '') . ' ' .
                      ($row['place'] ?? '')   . ' ' .
                      ($row['item'] ?? '')    . ' ' .
                      ($row['guest_name'] ?? '') . ' ' .
                      ($row['found_by'] ?? '')   . ' ' .
                      ($row['status'] ?? '')
                  )) ?>">
                <td class="px-3 py-2 align-top">
                  <div class="font-medium text-slate-900"><?= $h((string)($row['item'] ?? 'Unknown item')) ?></div>
                  <?php if (!empty($row['description'])): ?>
                    <div class="mt-0.5 text-[11px] text-slate-500 line-clamp-2">
                      <?= $h((string)$row['description']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 align-top text-[11px] text-slate-600">
                  <?php if (!empty($row['room_no'])): ?>
                    <div class="font-medium text-slate-800"><?= $h((string)$row['room_no']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($row['place'])): ?>
                    <div class="mt-0.5"><?= $h((string)$row['place']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 align-top text-[11px] text-slate-600">
                  <?php if (!empty($row['guest_name'])): ?>
                    <div class="font-medium text-slate-800"><?= $h((string)$row['guest_name']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($row['contact'])): ?>
                    <div class="mt-0.5"><?= $h((string)$row['contact']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 align-top">
                  <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium <?= $badgeClasses ?>">
                    <?= $h(ucfirst($status)) ?>
                  </span>
                  <?php if (!empty($row['found_by'])): ?>
                    <div class="mt-1 text-[10px] text-slate-500">
                      by <?= $h((string)$row['found_by']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 align-top text-[11px] text-slate-600">
                  <div><?= $h($dateFound ?: '—') ?></div>
                  <?php if (!empty($row['location'])): ?>
                    <div class="mt-0.5 text-slate-500">Stored: <?= $h((string)$row['location']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 align-top text-right text-[11px] text-slate-500">
                  <div class="inline-flex flex-col gap-1 items-end">
                    <form action="<?= $h($base . '/housekeeping/lost-found/' . (int)$row['id'] . '/status') ?>"
                          method="post"
                          class="inline-flex gap-1">
                      <input type="hidden" name="status" value="<?= $h($status === 'returned' ? 'logged' : 'returned') ?>">
                      <button type="submit"
                              class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 hover:bg-emerald-100">
                        <?= $status === 'returned' ? 'Mark active' : 'Mark returned' ?>
                      </button>
                    </form>
                    <form action="<?= $h($base . '/housekeeping/lost-found/' . (int)$row['id'] . '/delete') ?>"
                          method="post"
                          onsubmit="return confirm('Delete this record from Lost & Found?');">
                      <button type="submit"
                              class="rounded-full border border-slate-200 px-2 py-0.5 text-[10px] text-slate-500 hover:bg-slate-100">
                        Delete
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <!-- How to use this page -->
  <section class="rounded-3xl bg-slate-900 text-slate-50 px-5 py-5 sm:px-6 sm:py-6 mt-4">
    <h3 class="text-sm font-semibold">How to use this page</h3>
    <ol class="mt-2 space-y-1.5 text-xs text-slate-200 list-decimal list-inside">
      <li>Use <strong>“Log a new item”</strong> whenever HK or security finds anything. Fill room, place, and item clearly.</li>
      <li>Search guest or staff using the smart fields &mdash; the lookup connects to your hotel’s guest and staff lists.</li>
      <li>Set the <strong>status</strong> to “Logged” while the item stays with the hotel, then change to “Returned” when handed over.</li>
      <li>Use “Storage location” to write exactly where the item is kept. This helps night audit and security teams.</li>
      <li>When an item is no longer needed in the register, use <strong>Delete</strong> from the registry list on the right.</li>
    </ol>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Bind unified lookup for room, guest, staff
  if (window.KF && KF.lookup && typeof KF.lookup.bind === 'function') {
    ['lf-room', 'lf-guest', 'lf-staff'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        KF.lookup.bind({ el: el });
      }
    });
  }

  // Simple client-side search filter
  var searchInput = document.getElementById('lf-search');
  var tbody = document.getElementById('lf-table-body');
  if (searchInput && tbody) {
    searchInput.addEventListener('input', function () {
      var q = (this.value || '').toLowerCase().trim();
      var rows = tbody.querySelectorAll('tr[data-search]');
      rows.forEach(function (row) {
        var text = row.getAttribute('data-search') || '';
        var show = !q || text.indexOf(q) !== -1;
        row.style.display = show ? '' : 'none';
      });
    });
  }
});
</script>