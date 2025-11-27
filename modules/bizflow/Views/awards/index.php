<?php
/**
 * BizFlow — Awards index
 *
 * Expects:
 * - string $module_base  e.g. /t/{slug}/apps/bizflow
 * - array  $org
 * - array  $awards  (from AwardsController::index)
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$org         = $org ?? [];
$orgName     = trim((string)($org['name'] ?? ''));
$slug        = (string)($org['slug'] ?? '');

$awards      = $awards ?? [];
$today       = date('Y-m-d');

$pathQuotes  = $module_base . '/quotes';
$pathAwards  = $module_base . '/awards';

$brand       = '#228B22';
?>
<div class="space-y-6"
     x-data="{ q:'', quick:'all' }">

  <!-- HEADER + TABS -->
  <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div class="min-w-0">
      <div class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 uppercase">
        <i class="fa-regular fa-circle-check text-[11px]"></i>
        <span>Awards workspace</span>
      </div>
      <h1 class="mt-2 text-2xl md:text-3xl font-semibold tracking-tight">
        Awards<?= $orgName ? ' — '.$h($orgName) : '' ?>
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 max-w-2xl">
        See which quotations have been approved or awarded. From here you can start purchase orders
        and move towards delivery and invoicing.
      </p>
    </div>

    <!-- Page-local nav tabs: aligned from RIGHT edge to LEFT -->
    <nav class="flex flex-row-reverse flex-wrap gap-1 text-xs md:text-[13px]">
            
      <a href="<?= $h($module_base) ?>/orders"
         class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1">
        <i class="fa-solid fa-cart-flatbed text-[11px]"></i>
        <span>Orders</span>
      </a>
      <a href="<?= $h($module_base) ?>/ltas"
         class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1">
        <i class="fa-regular fa-user text-[11px]"></i>
        <span>LTA</span>
      </a>
      <a href="<?= $h($pathAwards) ?>"
         class="px-3 py-1.5 border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 flex items-center gap-1">
        <i class="fa-regular fa-circle-check text-[11px]"></i>
        <span>Awards</span>
      </a>
      <a href="<?= $h($pathQuotes) ?>"
         class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1">
        <i class="fa-regular fa-file-lines text-[11px]"></i>
        <span>Quotes</span>
      </a>
    </nav>
  </div>

  <!-- FILTER BAR -->
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
                 placeholder="Search award no, customer, quote…"
                 class="w-full text-xs bg-transparent border-0 focus:outline-none focus:ring-0 placeholder:text-gray-400">
        </div>
      </div>

      <!-- Helper text -->
      <div class="flex items-center gap-2 justify-end text-[11px] text-gray-500">
        <i class="fa-regular fa-lightbulb text-amber-500"></i>
        <span>Awards are generated from approved quotes. Go to a quote to create an award.</span>
      </div>
    </div>
  </section>

  <!-- AWARDS TABLE -->
  <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
    <div class="px-3 py-2 md:px-4 md:py-3 flex items-center justify-between text-xs text-gray-500">
      <div class="flex items-center gap-2">
        <i class="fa-regular fa-circle-dot text-[11px] text-emerald-600"></i>
        <span>
          <?= count($awards) ? $h(count($awards).' awards found') : 'No awards yet' ?>
        </span>
      </div>
      <div class="hidden md:flex items-center gap-2">
        <span class="text-[11px]">
          Open awards can be turned into purchase orders and invoices. Use the right-side icons to view,
          jump back to the quote, issue invoices, or (later) see the linked PO.
        </span>
      </div>
    </div>

    <?php if (!$awards): ?>
      <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-300">
        <p class="mb-2">
          No awards have been created yet. Approve a quote to generate the first award.
        </p>
        <a href="<?= $h($pathQuotes) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm hover:shadow-md rounded-md"
           style="background:<?= $h($brand) ?>;">
          <i class="fa-regular fa-file-lines text-[13px]"></i>
          <span>Go to quotes</span>
        </a>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
          <thead class="bg-gray-50 dark:bg-gray-800/70 text-[11px] uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-3 py-2 text-left">Award</th>
            <th class="px-3 py-2 text-left min-w-[180px]">Customer</th>
            <th class="px-3 py-2 text-left min-w-[150px]">From quote</th>
            <th class="px-3 py-2 text-left w-40">Dates</th>
            <th class="px-3 py-2 text-right w-32">Total (BDT)</th>
            <th class="px-3 py-2 text-left w-40">Status</th>
            <th class="px-3 py-2 text-right w-44">Actions</th>
          </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php foreach ($awards as $a): ?>
            <?php
            $id           = (int)$a['id'];
            $awardNo      = trim((string)($a['award_no'] ?? 'A-'.$id));
            $statusRaw    = strtolower(trim((string)($a['status'] ?? '')));
            if ($statusRaw === '') $statusRaw = 'draft';
            $statusLabel  = ucfirst($statusRaw);

            $custName     = trim((string)($a['customer_name'] ?? ''));
            $custCont     = trim((string)($a['customer_contact'] ?? ''));
            $extRef       = trim((string)($a['external_ref'] ?? ''));

            $date         = (string)($a['date'] ?? '');
            $valid        = (string)($a['valid_until'] ?? ''); // optional
            $total        = (float)($a['grand_total'] ?? 0);

            $quoteId      = (int)($a['quote_id'] ?? 0);
            $quoteNoRef   = trim((string)($a['quote_no'] ?? ''));
            $quoteUrl     = $quoteId ? $module_base . '/quotes/' . $quoteId : null;

            $hasPurchase  = ((int)($a['has_purchase'] ?? 0) === 1) || ((int)($a['purchase_id'] ?? 0) > 0);

            // Invoice mapping (simple flags; controller can hydrate these later)
			$invoiceId  = (int)($a['invoice_id'] ?? 0);
			$hasInvoice = $invoiceId > 0 || (int)($a['has_invoice'] ?? 0) === 1;

			// IMPORTANT: always go via /awards/{id}/invoice
			$invoiceUrl = $module_base . '/awards/' . $id . '/invoice';

            $detailUrl    = $module_base . '/awards/' . $id;

            // Status color
            $statusClass = 'bg-gray-100 text-gray-700 border-gray-300';
            if ($statusRaw === 'approved' || $statusRaw === 'confirmed') {
                $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            } elseif (in_array($statusRaw, ['ordered'], true)) {
                $statusClass = 'bg-sky-50 text-sky-700 border-sky-200';
            } elseif (in_array($statusRaw, ['completed'], true)) {
                $statusClass = 'bg-indigo-50 text-indigo-700 border-indigo-200';
            } elseif (in_array($statusRaw, ['cancelled'], true)) {
                $statusClass = 'bg-rose-50 text-rose-700 border-rose-200';
            }

            // quick filter buckets
            $isApprovedOrConfirmed = in_array($statusRaw, ['approved', 'confirmed'], true);
            $isOpen     = !in_array($statusRaw, ['cancelled'], true);

            // search haystack
            $searchHaystack = strtolower(
                trim($awardNo.' '.$custName.' '.$custCont.' '.$extRef.' '.$quoteNoRef.' '.$statusRaw)
            );
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                x-show="
                  (
                    quick === 'all' ||
                    (quick === 'open' && <?= $isOpen ? 'true' : 'false' ?>) ||
                    (quick === 'approved' && <?= $isApprovedOrConfirmed ? 'true' : 'false' ?>)
                  )
                  && (q === '' || '<?= $h($searchHaystack) ?>'.includes(q.toLowerCase()))
                "
                x-cloak
                x-transition>
              <!-- AWARD SUMMARY -->
              <td class="px-3 py-2 align-top">
                <a href="<?= $h($detailUrl) ?>" class="block">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">
                      <?= $h($awardNo) ?>
                    </span>
                    <span class="inline-flex items-center px-1.5 py-0.5 border text-[10px] rounded-full bg-emerald-50 text-emerald-700 border-emerald-200">
                      Award
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

              <!-- FROM QUOTE -->
              <td class="px-3 py-2 align-top text-xs">
                <?php if ($quoteUrl): ?>
                  <a href="<?= $h($quoteUrl) ?>" class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-300 hover:underline">
                    <i class="fa-regular fa-file-lines text-[11px]"></i>
                    <span><?= $h($quoteNoRef ?: ('Quote #'.$quoteId)) ?></span>
                  </a>
                <?php else: ?>
                  <span class="text-gray-500">—</span>
                <?php endif; ?>
              </td>

              <!-- DATES -->
              <td class="px-3 py-2 align-top text-xs">
                <?php if ($date): ?>
                  <div>Award: <?= $h($date) ?></div>
                <?php endif; ?>
                <?php if ($valid): ?>
                  <div class="text-gray-500">
                    Valid: <?= $h($valid) ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- TOTAL -->
              <td class="px-3 py-2 align-top text-right text-xs">
                <span class="font-semibold">
                  <?= number_format($total, 2, '.', ',') ?>
                </span>
              </td>

              <!-- STATUS -->
              <td class="px-3 py-2 align-top text-xs">
                <span class="inline-flex items-center px-2 py-0.5 border text-[11px] rounded-full <?= $h($statusClass) ?>">
                  <?= $h($statusLabel) ?>
                </span>
                <div class="text-[10px] text-gray-500 mt-0.5">
                  Purchase order linking will be enabled from award details (next step)
                </div>
              </td>

              <!-- ACTIONS (RIGHT, INLINE) -->
              <td class="px-3 py-2 align-top text-right">
                <div class="inline-flex items-center justify-end gap-1.5">
                  <!-- View award -->
                  <a href="<?= $h($detailUrl) ?>"
                     class="inline-flex items-center justify-center w-7 h-7 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800"
                     title="View award">
                    <i class="fa-regular fa-eye text-[11px]"></i>
                  </a>

                  <!-- Back to quote -->
                  <?php if ($quoteUrl): ?>
                    <a href="<?= $h($quoteUrl) ?>"
                       class="inline-flex items-center justify-center w-7 h-7 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800"
                       title="View source quote">
                      <i class="fa-regular fa-file-lines text-[11px]"></i>
                    </a>
                  <?php endif; ?>

                  <!-- Issue / view invoice (ALWAYS via /awards/{id}/invoice) -->
                  <?php $canInvoice = $isApprovedOrConfirmed; ?>
                  <?php if ($hasInvoice): ?>
                    <a href="<?= $h($invoiceUrl) ?>"
                       class="inline-flex items-center justify-center w-7 h-7 rounded border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700"
                       title="View invoice">
                      <i class="fa-solid fa-receipt text-[11px]"></i>
                    </a>
                  <?php else: ?>
                    <a href="<?= $h($canInvoice ? $invoiceUrl : '#') ?>"
                       class="inline-flex items-center justify-center w-7 h-7 rounded border bg-white dark:bg-gray-900
                              <?= $canInvoice
                                   ? 'border-emerald-600 text-emerald-700 hover:bg-emerald-50'
                                   : 'border-gray-300 dark:border-gray-600 text-gray-400 cursor-not-allowed opacity-60' ?>"
                       title="<?= $canInvoice
                           ? 'Issue invoice from this award'
                           : 'Invoice will be enabled once the award is approved / confirmed' ?>"
                       <?php if (!$canInvoice): ?>aria-disabled="true" onclick="return false;"<?php endif; ?>>
                      <i class="fa-solid fa-file-invoice text-[11px]"></i>
                    </a>
                  <?php endif; ?>

                  <!-- PO badge placeholder -->
                  <?php if ($hasPurchase): ?>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-[10px] text-gray-500 cursor-default"
                          title="Purchase order already linked (see award details)">
                      PO
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-[10px] text-gray-400 cursor-default"
                          title="Purchase order linking will be enabled from award details (next step)">
                      PO
                    </span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <!-- HOW TO USE THIS PAGE -->
  <section class="border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-xs md:text-sm text-gray-600 dark:text-gray-300">
    <h2 class="font-semibold mb-1 text-sm">How to use this page</h2>
    <ul class="list-disc list-inside space-y-0.5">
      <li>Use <strong>Quotes → Create award</strong> to bring approved RFQs into this awards list.</li>
      <li>Filter by <strong>Open / Approved / All</strong> to focus on awards that still need purchase or delivery planning.</li>
      <li>Click the <strong>Award number</strong> to open full details and later start purchase orders.</li>
      <li>Use the <strong>invoice icon</strong> on the right to issue or view the sales invoice generated from the award.</li>
      <li>Use the <strong>source quote icon</strong> on the right to jump back to the original quotation.</li>
    </ul>
  </section>

</div>