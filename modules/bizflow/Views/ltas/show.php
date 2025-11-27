<?php
/**
 * BizFlow — LTA details
 *
 * Expected:
 * - array  $org
 * - string $module_base
 * - array  $lta
 * - array  $meta
 * - array  $items
 * - array  $calloff_summary
 * - array  $calloffs_by_item
 * - array  $order_stats
 * - array  $invoice_stats
 * - array  $inventory (item_id => stock_qty)
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org         = $org         ?? [];
$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$lta         = $lta         ?? [];
$meta        = $meta        ?? [];
$items       = $items       ?? [];
$summary     = $calloff_summary  ?? [];
$byItem      = $calloffs_by_item ?? [];
$orderStats  = $order_stats      ?? [];
$invoiceStats= $invoice_stats    ?? [];
$inventory   = $inventory        ?? [];

$orgName = trim((string)($org['name'] ?? ''));

// Header fields
$id        = (int)($lta['id'] ?? 0);
$ltaNo     = trim((string)($lta['lta_no'] ?? ''));
$title     = trim((string)($lta['title'] ?? ''));
$status    = trim((string)($lta['status'] ?? 'draft'));
$statusLbl = trim((string)($lta['status_label'] ?? ucfirst($status ?: 'Draft')));

$customerName = trim((string)($lta['customer_name'] ?? ''));
$customerCode = trim((string)($lta['customer_code'] ?? ''));

$refNo     = trim((string)($lta['reference_no'] ?? ''));
$currency  = trim((string)($lta['currency'] ?? 'BDT'));
$startDate = (string)($lta['start_date'] ?? '');
$endDate   = (string)($lta['end_date']   ?? '');
$createdAt = (string)($lta['created_at'] ?? '');

$calloffPolicy = (string)($lta['calloff_policy'] ?? '');
$frameworkType = (string)($meta['framework_type'] ?? 'lta');
$notes         = trim((string)($meta['notes'] ?? ''));

// Money / usage
$ceiling   = (float)($lta['ceiling_total'] ?? 0);
$usedHdr   = (float)($lta['used_total']    ?? 0);
$usedCalc  = (float)($summary['amount_total'] ?? 0);
$used      = $usedHdr > 0 ? $usedHdr : $usedCalc;
$remaining = $ceiling > 0 ? max($ceiling - $used, 0) : 0;
$usagePct  = $ceiling > 0 ? min(100, max(0, ($used / $ceiling) * 100)) : 0;

$totalCalloffs = (int)($summary['total_calloffs'] ?? 0);
$poCount       = (int)($summary['po_count'] ?? 0);
$invCount      = (int)($summary['invoice_count'] ?? 0);
$qtyTotal      = (float)($summary['qty_total'] ?? 0);

// Orders stats
$ordTotal   = (int)($orderStats['total_orders']            ?? 0);
$ordServed  = (int)($orderStats['served_orders']           ?? 0);
$ordPending = (int)($orderStats['pending_delivery_orders'] ?? 0);
$ordAmount  = (float)($orderStats['total_order_amount']    ?? 0.0);
$ordPendAmt = (float)($orderStats['pending_delivery_amount'] ?? 0.0);

// Invoice stats
$invTotal       = (int)($invoiceStats['total_invoices']         ?? 0);
$invAmount      = (float)($invoiceStats['invoiced_amount']      ?? 0.0);
$invPaid        = (float)($invoiceStats['paid_amount']          ?? 0.0);
$invPend        = (float)($invoiceStats['pending_payment_amount'] ?? 0.0);
$invPendCount   = (int)($invoiceStats['pending_invoices']       ?? 0);

/** @return string */
function lta_status_badge_class(string $s): string {
    $s = strtolower($s);
    return match ($s) {
        'active'    => 'bg-emerald-100 text-emerald-800 border border-emerald-300',
        'suspended' => 'bg-amber-100 text-amber-800 border border-amber-300',
        'closed'    => 'bg-gray-100 text-gray-700 border border-gray-300',
        'expired'   => 'bg-rose-100 text-rose-800 border border-rose-300',
        'draft'     => 'bg-sky-100 text-sky-800 border border-sky-300',
        default     => 'bg-gray-100 text-gray-700 border border-gray-300',
    };
}

/** @return string */
function lta_calloff_policy_label(?string $p): string {
    $p = strtolower((string)$p);
    return match ($p) {
        'po_only'        => 'Call-offs via Purchase Orders',
        'invoice_only'   => 'Call-offs via Invoices',
        'po_and_invoice' => 'Call-offs via PO & Invoice',
        default          => 'Call-off policy not set',
    };
}

/** @return string */
function money_bd($v): string {
    return number_format((float)$v, 2, '.', ',');
}
?>

<div class="max-w-6xl mx-auto px-4 py-4 space-y-5">

  <!-- Header + tabs -->
  <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
    <div class="space-y-2">
      <div class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800">
        <span>Long Term Agreement</span>
        <?php if ($orgName !== ''): ?>
          <span class="text-gray-400">•</span>
          <span><?= $h($orgName) ?></span>
        <?php endif; ?>
      </div>

      <div class="flex items-center gap-3">
        <h1 class="text-xl md:text-2xl font-extrabold tracking-tight text-gray-900">
          <?= $h($ltaNo !== '' ? $ltaNo : 'LTA-'.$id) ?>
        </h1>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $h(lta_status_badge_class($status)) ?>">
          <?= $h($statusLbl) ?>
        </span>
      </div>

      <p class="text-xs md:text-sm text-gray-600">
        <?= $title !== '' ? $h($title) : 'No title set for this LTA yet.' ?>
      </p>

      <p class="text-xs text-gray-500">
        Issued to:
        <span class="font-medium text-gray-800">
          <?= $customerName !== '' ? $h($customerName) : 'Customer not linked' ?>
        </span>
        <?php if ($customerCode !== ''): ?>
          <span class="text-gray-400">•</span>
          <span class="text-gray-500">Code: <?= $h($customerCode) ?></span>
        <?php endif; ?>
      </p>
    </div>

    <div class="space-y-2 text-right">
      <!-- Tabs row (right-aligned) -->
      <div class="flex flex-row flex-wrap justify-end gap-2 text-xs">
        <a href="<?= $h($module_base) ?>/quotes" class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-gray-600 hover:bg-gray-100">
          Quotes
        </a>
        <a href="<?= $h($module_base) ?>/awards" class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-gray-600 hover:bg-gray-100">
          Awards
        </a>
        <a href="<?= $h($module_base) ?>/ltas" class="inline-flex items-center gap-1 rounded-full border border-emerald-500 bg-emerald-50 px-3 py-1 text-emerald-800">
          LTAs
        </a>
        <a href="<?= $h($module_base) ?>/purchases" class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-gray-600 hover:bg-gray-100">
          Purchases
        </a>
        <a href="<?= $h($module_base) ?>/invoices" class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-gray-600 hover:bg-gray-100">
          Invoices
        </a>
      </div>

      <div class="flex justify-end">
        <a href="<?= $h($module_base) ?>/ltas/<?= $id ?>/edit"
           class="inline-flex items-center rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">
          Edit LTA
        </a>
      </div>
    </div>
  </div>

  <!-- Top 4 cards: Contract / Call-offs / Orders / Invoices -->
  <div class="grid gap-4 md:grid-cols-4">
    <!-- Contract value card -->
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
        Contract value
      </p>
      <div class="mt-2 space-y-2">
        <div class="flex items-center justify-between text-xs text-gray-600">
          <span>Ceiling</span>
          <span class="font-semibold text-gray-900">
            <?= $h($currency) ?> <?= money_bd($ceiling) ?>
          </span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-600">
          <span>Used by call-offs</span>
          <span class="font-semibold text-emerald-700">
            <?= $h($currency) ?> <?= money_bd($used) ?>
          </span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-600">
          <span>Remaining</span>
          <span class="font-semibold <?= $remaining <= 0 && $ceiling > 0 ? 'text-rose-600' : 'text-gray-900' ?>">
            <?= $h($currency) ?> <?= money_bd($remaining) ?>
          </span>
        </div>

        <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100">
          <div class="h-1.5 rounded-full bg-emerald-500"
               style="width: <?= $ceiling > 0 ? $h(min(100, max(0, $usagePct))) : '0' ?>%;"></div>
        </div>
        <p class="mt-1 text-right text-[11px] text-gray-500">
          <?= $ceiling > 0 ? money_bd($usagePct) . '% of ceiling used' : 'No ceiling set yet' ?>
        </p>
      </div>
    </div>

    <!-- Call-offs card -->
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
        Call-off activity
      </p>
      <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-700">
        <div>
          <p class="text-[11px] text-gray-500">Total call-offs</p>
          <p class="mt-0.5 text-base font-semibold text-gray-900"><?= $totalCalloffs ?></p>
        </div>
        <div>
          <p class="text-[11px] text-gray-500">Total quantity</p>
          <p class="mt-0.5 text-base font-semibold text-gray-900"><?= money_bd($qtyTotal) ?></p>
        </div>
        <div>
          <p class="text-[11px] text-gray-500">From POs</p>
          <p class="mt-0.5 text-sm font-semibold text-gray-900"><?= $poCount ?></p>
        </div>
        <div>
          <p class="text-[11px] text-gray-500">From Invoices</p>
          <p class="mt-0.5 text-sm font-semibold text-gray-900"><?= $invCount ?></p>
        </div>
      </div>
      <p class="mt-2 text-[11px] text-gray-500">
        Posting from Purchases and Invoices to <code class="font-mono">biz_lta_calloffs</code>
        keeps this up to date automatically.
      </p>
    </div>

    <!-- Orders card (pending delivery) -->
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
        Orders vs. delivery
      </p>
      <div class="mt-2 space-y-1.5 text-xs text-gray-700">
        <div class="flex items-center justify-between">
          <span>Total orders</span>
          <span class="font-semibold text-gray-900"><?= $ordTotal ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span>Served (received/closed)</span>
          <span class="font-semibold text-emerald-700"><?= $ordServed ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span>Pending delivery</span>
          <span class="font-semibold <?= $ordPending > 0 ? 'text-amber-700' : 'text-gray-800' ?>">
            <?= $ordPending ?>
          </span>
        </div>
        <div class="flex items-center justify-between text-[11px] text-gray-600 pt-1">
          <span>Order value</span>
          <span><?= $h($currency) ?> <?= money_bd($ordAmount) ?></span>
        </div>
        <div class="flex items-center justify-between text-[11px] <?= $ordPendAmt > 0 ? 'text-amber-700' : 'text-gray-600' ?>">
          <span>Pending delivery value</span>
          <span><?= $h($currency) ?> <?= money_bd($ordPendAmt) ?></span>
        </div>
      </div>
    </div>

    <!-- Invoices card (pending payment) -->
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
        Invoices &amp; payment
      </p>
      <div class="mt-2 space-y-1.5 text-xs text-gray-700">
        <div class="flex items-center justify-between">
          <span>Total invoices</span>
          <span class="font-semibold text-gray-900"><?= $invTotal ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span>Pending invoices</span>
          <span class="font-semibold <?= $invPendCount > 0 ? 'text-amber-700' : 'text-gray-800' ?>">
            <?= $invPendCount ?>
          </span>
        </div>
        <div class="flex items-center justify-between text-[11px] text-gray-600 pt-1">
          <span>Invoiced amount</span>
          <span><?= $h($currency) ?> <?= money_bd($invAmount) ?></span>
        </div>
        <div class="flex items-center justify-between text-[11px] text-emerald-700">
          <span>Paid amount</span>
          <span><?= $h($currency) ?> <?= money_bd($invPaid) ?></span>
        </div>
        <div class="flex items-center justify-between text-[11px] <?= $invPend > 0 ? 'text-rose-700' : 'text-gray-600' ?>">
          <span>Pending payment</span>
          <span><?= $h($currency) ?> <?= money_bd($invPend) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Optional warning if remaining <= 0 -->
  <?php if ($ceiling > 0 && $remaining <= 0): ?>
    <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800">
      <div class="mt-0.5 h-2 w-2 rounded-full bg-rose-500"></div>
      <div>
        <p class="font-semibold">LTA ceiling fully utilised</p>
        <p>
          The total value of call-offs has reached or exceeded the agreed ceiling. Consider extending
          this LTA, closing it, or raising a new agreement before placing additional orders.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- Items / call-off usage + inventory -->
  <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
    <div class="flex items-center justify-between gap-2 pb-3 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-900">
        LTA items, call-off usage &amp; inventory
      </h2>
      <p class="text-[11px] text-gray-500">
        Each line shows contract ceiling, call-offs, remaining room on LTA, and on-hand stock.
      </p>
    </div>

    <?php if (!$items): ?>
      <div class="py-6 text-center text-sm text-gray-500">
        No items have been added to this LTA yet.
      </div>
    <?php else: ?>
      <?php
      // track if any inventory shortage found for banner
      $hasInventoryShortage = false;
      ?>
      <div class="-mx-2 mt-2 overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-y-1 text-xs">
          <thead>
          <tr class="text-[11px] uppercase tracking-wide text-gray-500">
            <th class="px-2 py-2 text-left">Line</th>
            <th class="px-2 py-2 text-left">Item</th>
            <th class="px-2 py-2 text-left">Description</th>
            <th class="px-2 py-2 text-right">Unit</th>
            <th class="px-2 py-2 text-right">Ceiling qty</th>
            <th class="px-2 py-2 text-right">Called qty</th>
            <th class="px-2 py-2 text-right">Remaining qty</th>
            <th class="px-2 py-2 text-right">On-hand stock</th>
            <th class="px-2 py-2 text-right">Ceiling price</th>
            <th class="px-2 py-2 text-right">Called amount</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $row): ?>
            <?php
            $ltaItemId = (int)($row['id'] ?? 0);
            $lineNo    = (int)($row['line_no'] ?? 0);

            $nameLocal  = trim((string)($row['item_name'] ?? ''));
            $nameMaster = trim((string)($row['item_name_master'] ?? ''));
            $itemName   = $nameLocal !== '' ? $nameLocal : $nameMaster;

            $codeLocal  = trim((string)($row['item_code'] ?? ''));
            $codeMaster = trim((string)($row['item_code_master'] ?? ''));
            $itemCode   = $codeLocal !== '' ? $codeLocal : $codeMaster;

            $desc   = trim((string)($row['description'] ?? ''));
            $unit   = trim((string)($row['unit'] ?? 'pcs'));
            $uPrice = (float)($row['unit_price'] ?? 0);
            $maxQty = $row['max_qty'] !== null ? (float)$row['max_qty'] : null;
            $minCalloffQty = $row['min_calloff_qty'] !== null ? (float)$row['min_calloff_qty'] : null;

            $stats  = $byItem[$ltaItemId] ?? [
                'qty_total'     => 0.0,
                'amount_total'  => 0.0,
                'po_count'      => 0,
                'invoice_count' => 0,
            ];
            $usedQty   = (float)($stats['qty_total'] ?? 0);
            $usedAmt   = (float)($stats['amount_total'] ?? 0);
            $remQty    = $maxQty !== null ? max($maxQty - $usedQty, 0) : null;

            // Inventory on-hand for the linked master item
            $masterItemId = (int)($row['item_id'] ?? 0);
            $onHand       = $masterItemId > 0 && isset($inventory[$masterItemId])
                ? (float)$inventory[$masterItemId]
                : 0.0;

            // Contract usage shortage (LTA quantity reached)
            $contractShortage = $maxQty !== null && $remQty <= 0 && $usedQty > 0;

            // Inventory shortage: if min_calloff_qty set, use that as minimum;
            // otherwise treat <= 0 as not available.
            if ($minCalloffQty !== null) {
                $inventoryShort = $onHand < $minCalloffQty;
            } else {
                $inventoryShort = $onHand <= 0;
            }

            if ($inventoryShort) {
                $hasInventoryShortage = true;
            }
            ?>
            <tr class="align-top">
              <td class="px-2 py-1.5 text-[11px] text-gray-500">
                <?= $lineNo ?: $ltaItemId ?>
              </td>
              <td class="px-2 py-1.5">
                <div class="text-xs font-semibold text-gray-900">
                  <?= $h($itemName !== '' ? $itemName : 'Unnamed item') ?>
                </div>
                <div class="text-[11px] text-gray-500">
                  <?php if ($itemCode !== ''): ?>
                    Code: <?= $h($itemCode) ?>
                  <?php else: ?>
                    &nbsp;
                  <?php endif; ?>
                </div>
              </td>
              <td class="px-2 py-1.5">
                <div class="max-w-xs whitespace-pre-line text-[11px] text-gray-600">
                  <?= $desc !== '' ? $h($desc) : '—' ?>
                </div>
              </td>
              <td class="px-2 py-1.5 text-right text-[11px] text-gray-700">
                <?= $h($unit) ?>
              </td>
              <td class="px-2 py-1.5 text-right text-[11px] text-gray-700">
                <?= $maxQty !== null ? money_bd($maxQty) : '∞' ?>
              </td>
              <td class="px-2 py-1.5 text-right text-[11px] text-gray-700">
                <?= money_bd($usedQty) ?>
              </td>
              <td class="px-2 py-1.5 text-right text-[11px] <?= $contractShortage ? 'text-rose-600 font-semibold' : 'text-gray-700' ?>">
                <?= $maxQty !== null ? money_bd($remQty) : '—' ?>
              </td>
              <td class="px-2 py-1.5 text-right text-[11px] <?= $inventoryShort ? 'text-rose-700 font-semibold' : 'text-gray-700' ?>">
                <?= money_bd($onHand) ?>
                <?php if ($minCalloffQty !== null): ?>
                  <span class="text-[10px] text-gray-400">(min call-off <?= money_bd($minCalloffQty) ?>)</span>
                <?php endif; ?>
              </td>
              <td class="px-2 py-1.5 text-right text-[11px] text-gray-700">
                <?= $h($currency) ?> <?= money_bd($uPrice) ?>
              </td>
              <td class="px-2 py-1.5 text-right text-[11px] text-gray-700">
                <?= $h($currency) ?> <?= money_bd($usedAmt) ?>
              </td>
            </tr>
            <?php if ($contractShortage || $inventoryShort): ?>
              <tr>
                <td></td>
                <td colspan="9" class="px-2 pb-2 text-[11px] <?= $inventoryShort ? 'text-rose-700' : 'text-amber-700' ?>">
                  <?php if ($inventoryShort): ?>
                    Inventory for this item is below the LTA minimum level.
                    Plan a purchase to rebuild stock before accepting more orders under this LTA.
                  <?php elseif ($contractShortage): ?>
                    This item has reached its agreed LTA quantity. Consider extending this LTA
                    or creating a new agreement before placing additional orders.
                  <?php endif; ?>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($hasInventoryShortage): ?>
        <div class="mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
          <div class="mt-1 h-1.5 w-1.5 rounded-full bg-amber-500"></div>
          <p>
            One or more LTA items currently have <strong>low or zero stock</strong> in inventory.
            Use the Purchases module to raise new orders against this LTA so that future call-offs
            can be delivered on time.
          </p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- How to use this page -->
  <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-xs text-gray-600">
    <h2 class="mb-1 text-sm font-semibold text-gray-800">How to use this page</h2>
    <ul class="list-disc space-y-1 pl-5">
      <li>
        Review the <strong>LTA header</strong> to confirm the customer, term, and contract ceiling.
      </li>
      <li>
        Use the top cards to see <strong>ceiling vs used</strong>, total call-offs, and
        high-level <strong>orders vs delivery</strong> and <strong>invoices vs payment</strong>.
      </li>
      <li>
        In the items table, check <strong>Ceiling qty</strong> vs <strong>Called qty</strong> to
        understand how much capacity remains for each line under this LTA.
      </li>
      <li>
        The <strong>On-hand stock</strong> column shows live inventory per item. Red values indicate
        that stock is below the LTA minimum call-off quantity or has run out.
      </li>
      <li>
        When you see inventory shortages, move to the <strong>Purchases</strong> module to raise new
        orders linked to this LTA so you can continue serving call-offs without interruption.
      </li>
      <li>
        Once you wire GRNs and inventory posting fully, this page will update automatically from
        <code class="font-mono">biz_inventory_moves</code> and <code class="font-mono">biz_lta_calloffs</code>
        without any manual adjustments.
      </li>
    </ul>
  </div>
</div>