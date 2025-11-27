<?php
/**
 * BizFlow — Quotes index
 *
 * Expects:
 * - string $module_base  e.g. /t/{slug}/apps/bizflow
 * - array  $org
 * - array  $quotes  (from QuotesController::index)
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$org         = $org ?? [];
$orgName     = trim((string)($org['name'] ?? ''));
$slug        = (string)($org['slug'] ?? '');

$quotes      = $quotes ?? [];
$today       = date('Y-m-d');

$pathQuotes  = $module_base . '/quotes';
$pathCreate  = $pathQuotes . '/create';

$brand       = '#228B22';
?>
<div class="space-y-6"
     x-data="{ q:'', quick:'open' }">

  <!-- HEADER + TABS (TABS RIGHT → LEFT) -->
  <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div class="min-w-0">
      <div class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 uppercase">
        <i class="fa-regular fa-file-lines text-[11px]"></i>
        <span>Quotes workspace</span>
      </div>
      <h1 class="mt-2 text-2xl md:text-3xl font-semibold tracking-tight">
        Quotes<?= $orgName ? ' — '.$h($orgName) : '' ?>
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 max-w-2xl">
        Track every quotation from draft to approved or rejected. Works for inventory-based and service-only businesses, RFQ and tender flows.
      </p>
    </div>

    <!-- Page-local nav tabs: aligned from RIGHT edge to LEFT -->
    <nav class="flex flex-row-reverse flex-wrap gap-1 text-xs md:text-[13px]">
      <a href="<?= $h($module_base) ?>/ltas"
         class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1">
        <i class="fa-solid fa-sliders text-[11px]"></i>
        <span>LTA</span>
      </a>
      <a href="<?= $h($module_base) ?>/awards"
         class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1">
        <i class="fa-regular fa-chart-bar text-[11px]"></i>
        <span>Awards</span>
      </a>
      <a href="<?= $h($module_base) ?>/orders"
         class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1">
        <i class="fa-solid fa-cart-flatbed text-[11px]"></i>
        <span>Orders</span>
      </a>
      <a href="<?= $h($module_base) ?>/customers"
         class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1">
        <i class="fa-regular fa-user text-[11px]"></i>
        <span>Customers</span>
      </a>
      <a href="<?= $h($pathQuotes) ?>"
         class="px-3 py-1.5 border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 flex items-center gap-1">
        <i class="fa-regular fa-file-lines text-[11px]"></i>
        <span>Quotes</span>
      </a>
    </nav>
  </div>

  <!-- FILTER BAR + PRIMARY ACTION -->
  <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-3 md:px-4 md:py-3">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div class="flex flex-wrap items-center gap-2 text-xs md:text-[13px]">
        <!-- Quick filter -->
        <div class="inline-flex border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/80 rounded-md overflow-hidden">
          <button type="button"
                  class="px-2.5 py-1 border-r border-gray-300 dark:border-gray-600"
                  :class="quick==='open' ? 'bg-emerald-600 text-white' : 'text-gray-700 dark:text-gray-200 bg-transparent'"
                  @click="quick='open'">
            Open
          </button>
          <button type="button"
                  class="px-2.5 py-1 border-r border-gray-300 dark:border-gray-600"
                  :class="quick==='approved' ? 'bg-emerald-600 text-white' : 'text-gray-700 dark:text-gray-200 bg-transparent'"
                  @click="quick='approved'">
            Approved
          </button>
          <button type="button"
                  class="px-2.5 py-1"
                  :class="quick==='all' ? 'bg-emerald-600 text-white' : 'text-gray-700 dark:text-gray-200 bg-transparent'"
                  @click="quick='all'">
            All
          </button>
        </div>

        <!-- Search -->
        <div class="flex items-center border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1 min-w-[200px] rounded-md">
          <i class="fa-solid fa-magnifying-glass text-[11px] text-gray-400 mr-1.5"></i>
          <input type="text"
                 x-model="q"
                 placeholder="Search quote no, customer, ref…"
                 class="w-full text-xs bg-transparent border-0 focus:outline-none focus:ring-0 placeholder:text-gray-400">
        </div>
      </div>

      <!-- Primary action -->
      <div class="flex items-center gap-2 justify-end">
        <a href="<?= $h($pathCreate) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm hover:shadow-md rounded-md"
           style="background:<?= $h($brand) ?>;">
          <i class="fa-regular fa-file-lines text-[13px]"></i>
          <span>New quote</span>
        </a>
      </div>
    </div>
  </section>

  <!-- QUOTES TABLE -->
  <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
    <div class="px-3 py-2 md:px-4 md:py-3 flex items-center justify-between text-xs text-gray-500">
      <div class="flex items-center gap-2">
        <i class="fa-regular fa-circle-dot text-[11px] text-emerald-600"></i>
        <span>
          <?= count($quotes) ? $h(count($quotes).' quotes found') : 'No quotes yet' ?>
        </span>
      </div>
      <div class="hidden md:flex items-center gap-2">
        <span class="text-[11px]">
          Click a status pill to mark the quote as Approved / Accepted / Rejected.
          Use the right icons to edit, download PDF, or start an email.
        </span>
      </div>
    </div>

    <?php if (!$quotes): ?>
      <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-300">
        <p class="mb-2">
          No quotes have been created yet for this organisation.
        </p>
        <a href="<?= $h($pathCreate) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm hover:shadow-md rounded-md"
           style="background:<?= $h($brand) ?>;">
          <i class="fa-regular fa-file-lines text-[13px]"></i>
          <span>Create the first quote</span>
        </a>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
          <thead class="bg-gray-50 dark:bg-gray-800/70 text-[11px] uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-3 py-2 text-left">Quote</th>
            <th class="px-3 py-2 text-left min-w-[180px]">Customer</th>
            <th class="px-3 py-2 text-left w-40">Dates</th>
            <th class="px-3 py-2 text-right w-32">Total (BDT)</th>
            <th class="px-3 py-2 text-left w-40">Status</th>
            <th class="px-3 py-2 text-right w-32">Actions</th>
          </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php foreach ($quotes as $q): ?>
            <?php
            $id        = (int)$q['id'];
            $qno       = trim((string)($q['quote_no'] ?? 'Q-'.$id));
            $custName  = trim((string)($q['customer_name'] ?? ''));
            $custCont  = trim((string)($q['customer_contact'] ?? ''));
            $extRef    = trim((string)($q['external_ref'] ?? ''));
            $type      = trim((string)($q['quote_type'] ?? 'mixed'));
            $status    = strtolower(trim((string)($q['status'] ?? '')));
            if ($status === '') $status = 'draft';

            $date      = (string)($q['date'] ?? '');
            $valid     = (string)($q['valid_until'] ?? '');
            $total     = (float)($q['grand_total'] ?? 0);

            $detailUrl = $module_base . '/quotes/' . $id;
            $editUrl   = $module_base . '/quotes/' . $id . '/edit';
            $printUrl  = $module_base . '/quotes/' . $id . '/print';
            $statusUrl = $module_base . '/quotes/' . $id . '/status';

            // mailto link
            $subject   = rawurlencode('Quotation '.$qno);
            $bodyText  = "Dear Sir/Madam,%0D%0A%0D%0A".
                         "Please find our quotation here:%0D%0A".
                         $printUrl.
                         "%0D%0A%0D%0ARegards,%0D%0A".$orgName;
            $mailtoUrl = 'mailto:?subject='.$subject.'&body='.$bodyText;

            $wonSet    = ['approved','accepted'];
            $expired   = ($valid && $valid < $today && !in_array($status, $wonSet, true));

            // type pill
            $typeLabel = 'Mixed';
            $typeClass = 'bg-gray-50 text-gray-600 border-gray-200';
            if ($type === 'stock')   { $typeLabel = 'Stock';   $typeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200'; }
            if ($type === 'service') { $typeLabel = 'Service'; $typeClass = 'bg-sky-50 text-sky-700 border-sky-200'; }

            // quick filter sets
            $isApproved = in_array($status, $wonSet, true);
            $isOpen     = ($status !== 'rejected');

            // search haystack (lowercased)
            $searchHaystack = strtolower(
                trim($qno.' '.$custName.' '.$custCont.' '.$extRef.' '.$status.' '.$type)
            );
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                x-show="
                  (
                    quick === 'all' ||
                    (quick === 'open' && <?= $isOpen ? 'true' : 'false' ?>) ||
                    (quick === 'approved' && <?= $isApproved ? 'true' : 'false' ?>)
                  )
                  &&
                  (
                    q === '' ||
                    <?= json_encode($searchHaystack) ?>.indexOf(q.toLowerCase()) !== -1
                  )
                "
                x-cloak
                x-transition>
              <!-- QUOTE SUMMARY -->
              <td class="px-3 py-2 align-top">
                <a href="<?= $h($detailUrl) ?>" class="block">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                      <?= $h($qno) ?>
                    </span>
                    <span class="inline-flex items-center px-1.5 py-0.5 border text-[10px] rounded-full <?= $h($typeClass) ?>">
                      <?= $h($typeLabel) ?>
                    </span>
                  </div>
                  <?php if ($extRef !== ''): ?>
                    <div class="text-[11px] text-gray-500 mt-0.5">
                      Ref: <?= $h($extRef) ?>
                    </div>
                  <?php endif; ?>
                </a>
              </td>

              <!-- CUSTOMER -->
              <td class="px-3 py-2 align-top">
                <div class="flex flex-col gap-0.5">
                  <div class="text-xs font-medium"><?= $h($custName ?: '—') ?></div>
                  <?php if ($custCont !== ''): ?>
                    <div class="text-[11px] text-gray-500"><?= $h($custCont) ?></div>
                  <?php endif; ?>
                </div>
              </td>

              <!-- DATES -->
              <td class="px-3 py-2 align-top text-xs">
                <?php if ($date): ?>
                  <div>Date: <?= $h($date) ?></div>
                <?php endif; ?>
                <?php if ($valid): ?>
                  <div class="<?= $expired ? 'text-rose-600 font-semibold' : 'text-gray-500' ?>">
                    Valid: <?= $h($valid) ?><?= $expired ? ' (expired)' : '' ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- TOTAL -->
              <td class="px-3 py-2 align-top text-right text-xs">
                <span class="font-semibold">
                  <?= number_format($total, 2, '.', ',') ?>
                </span>
              </td>

              <!-- STATUS PILL GROUP -->
              <td class="px-3 py-2 align-top text-xs">
                <div class="inline-flex rounded-full border border-gray-300 dark:border-gray-600 overflow-hidden"
                     data-status-root
                     data-status-url="<?= $h($statusUrl) ?>"
                     data-current-status="<?= $h($status) ?>">
                  <?php
                  $statuses = [
                    'approved' => 'Approved',
                    'accepted' => 'Accepted',
                    'rejected' => 'Rejected',
                  ];
                  foreach ($statuses as $code => $label):
                    $isActive = ($status === $code);
                    ?>
                    <button type="button"
                            data-status="<?= $h($code) ?>"
                            class="px-2 py-0.5 text-[10px] border-r border-gray-300 dark:border-gray-600 last:border-r-0
                                   <?= $isActive ? 'bg-emerald-600 text-white' : 'bg-transparent text-gray-600 dark:text-gray-300' ?>">
                      <?= $h($label) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </td>

              <!-- ACTIONS (RIGHT, INLINE) -->
              <td class="px-3 py-2 align-top text-right">
                <div class="inline-flex items-center justify-end gap-1.5">
                  <!-- Edit -->
                  <a href="<?= $h($editUrl) ?>"
                     class="inline-flex items-center justify-center w-7 h-7 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800"
                     title="Edit quote">
                    <i class="fa-regular fa-pen-to-square text-[11px]"></i>
                  </a>
                  <!-- PDF -->
                  <a href="<?= $h($printUrl) ?>"
                     target="_blank" rel="noopener"
                     class="inline-flex items-center justify-center w-7 h-7 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800"
                     title="Download / print PDF">
                    <i class="fa-regular fa-file-pdf text-[11px]"></i>
                  </a>
                  <!-- Email via default client -->
                  <a href="<?= $h($mailtoUrl) ?>"
                     class="inline-flex items-center justify-center w-7 h-7 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800"
                     title="Send via email app">
                    <i class="fa-regular fa-envelope text-[11px]"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
/**
 * BizFlow Quotes index — inline status pills
 * POSTs to /quotes/{id}/status with status=approved|accepted|rejected
 */
(function () {
  var roots = document.querySelectorAll('[data-status-root]');
  if (!roots.length) return;

  roots.forEach(function (root) {
    var url = root.getAttribute('data-status-url');
    if (!url) return;

    function setActive(value) {
      root.querySelectorAll('[data-status]').forEach(function (btn) {
        var v = btn.getAttribute('data-status');
        if (v === value) {
          btn.classList.add('bg-emerald-600', 'text-white');
          btn.classList.remove('bg-transparent', 'text-gray-600');
        } else {
          btn.classList.remove('bg-emerald-600', 'text-white');
          btn.classList.add('bg-transparent', 'text-gray-600');
        }
      });
      root.setAttribute('data-current-status', value);
    }

    root.querySelectorAll('[data-status]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var value = btn.getAttribute('data-status');
        if (!value) return;

        var params = new URLSearchParams();
        params.set('status', value); // value is approved | accepted | rejected

        fetch(url, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: params.toString()
        }).then(function (res) {
          if (!res.ok) {
            alert('Failed to update status (HTTP ' + res.status + ').');
            return;
          }
          setActive(value);
        }).catch(function (err) {
          console.error(err);
          alert('Unexpected error while updating status.');
        });
      });
    });
  });
})();
</script>