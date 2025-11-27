<?php
/**
 * BizFlow — LTAs index (Tailwind)
 *
 * Expected:
 * - array  $org
 * - string $module_base
 * - array  $ltas
 * - array  $filters
 * - array  $suppliers
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org         = $org         ?? [];
$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$ltas        = $ltas        ?? [];
$filters     = $filters     ?? [];
$suppliers   = $suppliers   ?? [];

$orgName = trim((string)($org['name'] ?? ''));

$qFilter        = (string)($filters['q']           ?? '');
$supplierFilter = (string)($filters['supplier']    ?? '');
$statusFilter   = (string)($filters['status']      ?? '');
$activeOnly     = !empty($filters['active_only']);

function ltaStatusLabel(string $status): string {
    return match (strtolower($status)) {
        'active'    => 'Active',
        'on_hold'   => 'On hold',
        'closed'    => 'Closed',
        'cancelled' => 'Cancelled',
        'draft'     => 'Draft',
        default     => ucfirst($status ?: 'Unknown'),
    };
}
function ltaStatusClass(string $status): string {
    return match (strtolower($status)) {
        'active'    => 'bg-emerald-100 text-emerald-800 border border-emerald-300',
        'on_hold'   => 'bg-amber-100 text-amber-800 border border-amber-300',
        'closed'    => 'bg-slate-200 text-slate-800 border border-slate-300',
        'cancelled' => 'bg-rose-100 text-rose-800 border border-rose-300',
        'draft'     => 'bg-sky-100 text-sky-800 border border-sky-300',
        default     => 'bg-slate-200 text-slate-800 border border-slate-300',
    };
}
function calloffLabel(?string $policy): string {
    $policy = strtolower((string)$policy);
    return match ($policy) {
        'po_only'        => 'PO only',
        'invoice_only'   => 'Invoice only',
        'po_and_invoice' => 'PO + Invoice',
        default          => 'Not set',
    };
}
function money_bd($v): string {
    return number_format((float)$v, 2, '.', ',');
}
?>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-4 text-slate-900">

  <!-- Header + tabs -->
  <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
    <div>
      <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
        <span>Long Term Agreements (LTAs)</span>
        <?php if ($orgName !== ''): ?>
          <span class="text-slate-500">• <?= $h($orgName) ?></span>
        <?php endif; ?>
      </div>
      <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">
        Framework contracts overview
      </h1>
      <p class="mt-1 text-xs md:text-sm text-slate-500">
        Track multi-year supplier contracts, monitor call-off usage, and keep your remaining ceiling value clear.
      </p>
    </div>

    <div class="flex flex-col items-end gap-3">
      <nav class="flex flex-wrap justify-end gap-2 text-xs md:text-sm">
        <a href="<?= $h($module_base) ?>/quotes"
           class="inline-flex items-center rounded-full border border-transparent bg-slate-100 px-3 py-1 text-slate-600 hover:bg-slate-200">
          Quotes
        </a>
        <a href="<?= $h($module_base) ?>/awards"
           class="inline-flex items-center rounded-full border border-transparent bg-slate-100 px-3 py-1 text-slate-600 hover:bg-slate-200">
          Awards
        </a>
        <a href="<?= $h($module_base) ?>/ltas"
           class="inline-flex items-center rounded-full border border-emerald-500 bg-emerald-50 px-3 py-1 font-semibold text-emerald-800">
          LTAs
        </a>
        <a href="<?= $h($module_base) ?>/purchases"
           class="inline-flex items-center rounded-full border border-transparent bg-slate-100 px-3 py-1 text-slate-600 hover:bg-slate-200">
          Purchases
        </a>
        <a href="<?= $h($module_base) ?>/invoices"
           class="inline-flex items-center rounded-full border border-transparent bg-slate-100 px-3 py-1 text-slate-600 hover:bg-slate-200">
          Invoices
        </a>
      </nav>

      <a href="<?= $h($module_base) ?>/ltas/create"
         class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
        <i class="fa-regular fa-file-lines"></i>
        <span>New LTA</span>
      </a>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" action="<?= $h($module_base) ?>/ltas"
        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.2fr)_auto_auto] md:items-end">
      <div>
        <label for="f_q" class="block text-xs font-semibold text-slate-600">
          Search by LTA no / title
        </label>
        <input id="f_q"
               type="search"
               name="q"
               value="<?= $h($qFilter) ?>"
               placeholder="Type part of LTA no, title or supplier name…"
               class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        <p class="mt-1 text-[11px] text-slate-500">
          Quick text search across LTA number, title and supplier name.
        </p>
      </div>

      <div>
        <label for="f_supplier" class="block text-xs font-semibold text-slate-600">
          Supplier (autocomplete)
        </label>
        <input id="f_supplier"
               list="supplierList"
               name="supplier"
               value="<?= $h($supplierFilter) ?>"
               placeholder="Start typing supplier name…"
               class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        <?php if ($suppliers): ?>
          <datalist id="supplierList">
            <?php foreach ($suppliers as $s): ?>
              <?php
              $name  = trim((string)($s['name'] ?? ''));
              $code  = trim((string)($s['code'] ?? ''));
              $label = $name !== '' ? $name : 'Supplier #'.(int)($s['id'] ?? 0);
              if ($code !== '') {
                  $label .= ' ('.$code.')';
              }
              ?>
              <option value="<?= $h($label) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        <?php endif; ?>
        <p class="mt-1 text-[11px] text-slate-500">
          Browser autocomplete lists known suppliers. You can also paste a name directly.
        </p>
      </div>

      <div>
        <label for="f_status" class="block text-xs font-semibold text-slate-600">
          Status
        </label>
        <select id="f_status"
                name="status"
                class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
          <option value="">All statuses</option>
          <?php
          $statuses = ['draft','active','on_hold','closed','cancelled'];
          foreach ($statuses as $st):
              $sel = ($statusFilter === $st) ? ' selected' : '';
          ?>
            <option value="<?= $h($st) ?>"<?= $sel ?>>
              <?= $h(ltaStatusLabel($st)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="mt-1 text-[11px] text-slate-500">
          Narrow down to active, closed, draft, etc.
        </p>
      </div>

      <div class="flex flex-col gap-2">
        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
          <input type="checkbox"
                 name="active_only"
                 value="1"
                 class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                 <?= $activeOnly ? 'checked' : '' ?>>
          <span>Show active only</span>
        </label>
      </div>

      <div class="flex gap-2">
        <button type="submit"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
          <i class="fa-solid fa-magnifying-glass"></i>
          <span>Apply filters</span>
        </button>
        <a href="<?= $h($module_base) ?>/ltas"
           class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
          <i class="fa-solid fa-rotate-left"></i>
          <span>Reset</span>
        </a>
      </div>
    </div>
  </form>

  <!-- LTAs table -->
  <div class="rounded-2xl border border-slate-200 bg-white p-3 md:p-4 shadow-sm">
    <?php if (!$ltas): ?>
      <div class="py-8 text-center text-sm text-slate-500">
        No LTAs found for these filters yet.
        Use <span class="font-semibold">“New LTA”</span> to register a framework contract, or adjust your filters above.
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500">
          <tr>
            <th class="px-3 py-2 w-1/3">LTA</th>
            <th class="px-3 py-2 w-1/4">Supplier</th>
            <th class="px-3 py-2 w-1/5">Term</th>
            <th class="px-3 py-2 w-1/6">Status / Type</th>
            <th class="px-3 py-2 w-1/4">Contract value &amp; usage</th>
          </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
          <?php foreach ($ltas as $lta): ?>
            <?php
            $id           = (int)($lta['id'] ?? 0);
            $ltaNo        = trim((string)($lta['lta_no'] ?? ''));
            $title        = trim((string)($lta['title'] ?? ''));
            $supplierName = trim((string)($lta['supplier_name'] ?? ''));
            $status       = trim((string)($lta['status'] ?? 'draft'));
            $currency     = trim((string)($lta['currency'] ?? 'BDT'));
            $startDate    = (string)($lta['start_date'] ?? '');
            $endDate      = (string)($lta['end_date']   ?? '');
            $createdAt    = (string)($lta['created_at'] ?? '');

            $meta = [];
            if (!empty($lta['meta_json'])) {
                $tmp = json_decode((string)$lta['meta_json'], true);
                if (is_array($tmp)) {
                    $meta = $tmp;
                }
            }
            $policy = (string)($meta['calloff_policy'] ?? '');

            $ceiling   = (float)($lta['ceiling_total'] ?? 0);
            $used      = (float)($lta['used_total']    ?? 0);
            $remaining = $ceiling > 0 ? max($ceiling - $used, 0) : 0;
            $usagePct  = $ceiling > 0 ? min(100, max(0, ($used / $ceiling) * 100)) : 0;
            $rowUrl    = $module_base.'/ltas/'.$id;
            ?>
            <tr class="cursor-pointer hover:bg-slate-50" data-href="<?= $h($rowUrl) ?>">
              <td class="px-3 py-3 align-top">
                <div class="text-sm font-semibold text-slate-900">
                  <?= $h($ltaNo !== '' ? $ltaNo : ('LTA-'.$id)) ?>
                </div>
                <div class="mt-0.5 text-xs text-slate-500">
                  <?= $h($title !== '' ? $title : 'No title set') ?>
                  <?php if ($createdAt !== ''): ?>
                    • Created <?= $h(substr($createdAt, 0, 10)) ?>
                  <?php endif; ?>
                </div>
              </td>

              <td class="px-3 py-3 align-top">
                <div class="text-sm font-medium text-slate-900">
                  <?= $h($supplierName !== '' ? $supplierName : '—') ?>
                </div>
                <div class="mt-0.5 text-xs text-slate-500">
                  <?php if (!empty($meta['supplier_ref'])): ?>
                    Ref: <?= $h($meta['supplier_ref']) ?>
                  <?php else: ?>
                    No supplier reference
                  <?php endif; ?>
                </div>
              </td>

              <td class="px-3 py-3 align-top">
                <div class="text-sm text-slate-900">
                  <?php if ($startDate || $endDate): ?>
                    <?= $h($startDate ?: '—') ?> → <?= $h($endDate ?: 'open-ended') ?>
                  <?php else: ?>
                    <span class="text-slate-500">Not set</span>
                  <?php endif; ?>
                </div>
                <div class="mt-0.5 text-xs text-slate-500">
                  <?php if ($endDate): ?>
                    Framework term with defined end date.
                  <?php else: ?>
                    Open-ended until closed or cancelled.
                  <?php endif; ?>
                </div>
              </td>

              <td class="px-3 py-3 align-top">
                <div class="mb-1">
                  <?php $cls = ltaStatusClass($status); ?>
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium <?= $cls ?>">
                    <?= $h(ltaStatusLabel($status)) ?>
                  </span>
                </div>
                <div class="text-xs text-slate-500">
                  <?= $h(calloffLabel($policy)) ?>
                </div>
              </td>

              <td class="px-3 py-3 align-top">
                <div class="min-w-[180px] space-y-1">
                  <div class="flex justify-between text-[11px] text-slate-600">
                    <span>Ceiling: <?= $h($currency) ?> <?= money_bd($ceiling) ?></span>
                  </div>
                  <div class="h-1.5 rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-emerald-600"
                         style="width: <?= $h((string)round($usagePct, 1)) ?>%;"></div>
                  </div>
                  <div class="flex justify-between text-[11px] text-slate-600">
                    <span>Used: <?= money_bd($used) ?></span>
                    <span>Remaining: <?= money_bd($remaining) ?></span>
                  </div>
                  <div class="text-right text-[11px] text-slate-500">
                    <?= $ceiling > 0 ? money_bd($usagePct).'%' : '0.00%' ?> used
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- How to use -->
  <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/70 p-4 text-xs md:text-sm text-slate-600">
    <h2 class="mb-1 text-sm font-semibold text-slate-800">
      How to use this page
    </h2>
    <ul class="list-disc space-y-1 pl-5">
      <li>
        Use the <span class="font-semibold">New LTA</span> button to register a new framework contract after an award is granted
        or directly from your procurement workflow.
      </li>
      <li>
        Filter by <span class="font-semibold">text search</span>,
        <span class="font-semibold">Supplier (autocomplete)</span> and
        <span class="font-semibold">Status</span> to quickly find the LTA you’re looking for.
      </li>
      <li>
        Click any row to open the <span class="font-semibold">LTA details</span> page, where you can see line items,
        call-off history and remaining ceilings.
      </li>
      <li>
        The <span class="font-semibold">usage bar</span> shows how much of the LTA ceiling value has been used via purchase
        orders or invoices, and how much is still available.
      </li>
      <li>
        Use the <span class="font-semibold">“Show active only”</span> checkbox when you want to hide closed or cancelled LTAs
        and focus on live contracts.
      </li>
      <li>
        Once this is wired end-to-end, call-offs from <span class="font-semibold">Purchases</span> and
        <span class="font-semibold">Invoices</span> will automatically update the used / remaining values here.
      </li>
    </ul>
  </div>
</div>

<script>
  (function () {
    const rows = document.querySelectorAll('table tbody tr[data-href]');
    rows.forEach(function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.closest('a,button')) return;
        const href = row.getAttribute('data-href');
        if (href) window.location.href = href;
      });
    });
  })();
</script>