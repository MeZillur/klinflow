<?php
/**
 * modules/DMS/Views/orders/create.php
 * Content-only view. Shell wraps. Brand #228B22.
 * - Uses controller-provided $postUrl (fallbacks to $module_base)
 * - CSRF: portable fallback ensures `_csrf` exists
 * - Renders flash errors so failures are visible
 * - Items builder posts items[n][product_id|product_name|qty|unit_price|line_total]
 * - Promo lines are optional and don't affect payable totals
 * - Keyboard: Ctrl/Cmd+Enter (Save), "+" add paid, Shift+"+" add promo
 * - Improvements:
 *    • Order No shows next immediate number (readonly), refreshed from API
 *    • Status tabs correctly bind hidden value (id fix: __status)
 *    • Live “Save New Customer” (AJAX) to create + bind customer instantly
 *    • Mobile-thin layout and better product input width
 *    • Top-right mini menu (Orders / Create / Invoices / Challan) wired to real routes
 */
declare(strict_types=1);

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/** Context --------------------------------------------------------------- */
$module_base = $module_base ?? '';                    // /t/{slug}/apps/dms
$endpoints   = $endpoints   ?? [];
$hydrateUrl  = $hydrateUrl  ?? null;

// Provide safe endpoint fallbacks (controller can override)
$endpoints += [
  'createCustomer' => ($endpoints['createCustomer'] ?? ($module_base.'/api/customers')),
  'nextOrderNo'    => ($endpoints['nextOrderNo']    ?? ($module_base.'/api/orders/next-no')),
];

$isEdit      = !empty($order) && is_array($order) && !empty($order['id']);
$oid         = $isEdit ? (int)$order['id'] : 0;

$srId        = $isEdit ? (int)($order['sr_user_id']  ?? 0) : (int)($srId ?? 0);
$dsrId       = $isEdit ? (int)($order['dsr_user_id'] ?? 0) : (int)($dsrId ?? 0);

$st          = $isEdit ? (string)($order['status'] ?? ($st ?? 'draft'))          : ($st ?? 'draft');
$dt          = $isEdit ? (string)($order['discount_type'] ?? ($dt ?? 'amount'))  : ($dt ?? 'amount');
$dv          = $isEdit ? (float) ($order['discount_value'] ?? 0)                 : (float)($dv ?? 0.0);

$od          = $isEdit ? (string)($order['order_date'] ?? date('Y-m-d'))         : date('Y-m-d');
$dd          = $isEdit ? (string)($order['delivery_date'] ?? $od)                : date('Y-m-d');

$cid         = $isEdit ? (int)($order['customer_id'] ?? 0)  : 0;
$supplierId  = $isEdit ? (int)($order['supplier_id'] ?? 0)  : 0;

$custName    = (string)($order['customer_name'] ?? '');
$suppName    = (string)($order['supplier_name'] ?? '');
$srName      = (string)($order['sr_user_name'] ?? '');
$dsrName     = (string)($order['dsr_user_name'] ?? '');

// If controller passed a precomputed next number, we can display it immediately
$nextNo      = (string)($next_no ?? '');

/** Prefer controller-provided postUrl; fallback to module_base ----------- */
$postUrl = $postUrl
    ?? ($endpoints['postUrl'] ?? null)
    ?? ($isEdit ? ($module_base.'/orders/'.$oid) : ($module_base.'/orders'));

/** Top-right mini menu (no sidenav; must match DMS router) --------------- */
$ordersBase   = rtrim($module_base, '/') . '/orders';
$salesBase    = rtrim($module_base, '/') . '/sales';
$challanBase  = rtrim($module_base, '/') . '/challan';

// On this view we either create (fresh) or sometimes reuse for edit
$currentKey = $isEdit ? 'orders' : 'create';

$tabs = [
    [
        'key'   => 'orders',
        'label' => 'Order List',
        'href'  => $ordersBase,                 // /orders
    ],
    [
        'key'   => 'create',
        'label' => 'Create Order',
        'href'  => $ordersBase . '/create',     // /orders/create
    ],
    [
        'key'   => 'invoices',
        'label' => 'Invoices',
        'href'  => $salesBase,                  // /sales (invoice hub; /invoices → redirect)
    ],
    [
        'key'   => 'challan',
        'label' => 'Challan',
        'href'  => $challanBase,                // /challan (DmsChallanController)
    ],
];

/** Normalize edit lines (paid) ------------------------------------------ */
$normPaid = [];
if ($isEdit && !empty($order['items']) && is_array($order['items'])) {
  foreach ($order['items'] as $ln) {
    $qty   = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
    $price = (float)($ln['price'] ?? $ln['unit_price'] ?? $ln['rate'] ?? 0);
    $normPaid[] = [
      'product_id'   => (int)($ln['product_id'] ?? 0),
      'qty'          => $qty,
      'price'        => $price,
      'product_name' => (string)($ln['product_name'] ?? ''),
    ];
  }
}

/** Normalize edit promo lines ------------------------------------------- */
$normPromo = [];
if ($isEdit && !empty($order['promo_items']) && is_array($order['promo_items'])) {
  foreach ($order['promo_items'] as $ln) {
    $qty = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
    $normPromo[] = [
      'product_id'   => (int)($ln['product_id'] ?? 0),
      'qty'          => $qty,
      'product_name' => (string)($ln['product_name'] ?? ''),
    ];
  }
}
?>
<style>
  :root { --brand:#228B22; }
  .kf-form * { border-radius:0 !important; }
  .kf-form { font-size: 14px; }
  .kf-form input[type="text"],
  .kf-form input[type="number"],
  .kf-form input[type="date"],
  .kf-form select {
    height: 40px !important; line-height: 40px !important;
    border: 1px solid #d1d5db !important; background:#fff !important;
    box-shadow: none !important; font-size:.875rem !important; padding:0 .75rem !important;
  }
  .kf-form .seg-title  { font-weight:600; color:#0f172a; margin:1.25rem 0 .4rem; }
  .kf-form .seg-kicker { font-size:.78rem; color:#64748b; margin-bottom:.75rem; }
  .kf-form .tab { background:#fff; border:1px solid #e5e7eb; }
  .kf-form .tab--on { background:var(--brand); color:#fff; border-color:var(--brand); }
  .kf-form table th, .kf-form table td { border-color:#e5e7eb; }

  .w-prod { width: 50%; }
  @media (max-width: 640px) {
    .kf-form { font-size: 13px; }
    .kf-form h1 { font-size: 1rem; }
    .kf-form input[type="text"],
    .kf-form input[type="number"],
    .kf-form input[type="date"],
    .kf-form select {
      height: 36px !important; line-height: 36px !important;
      padding: 0 .5rem !important;
    }
    .kf-form .seg-title { margin: .9rem 0 .35rem; }
    .kf-form .seg-kicker { font-size: .72rem; }
    .kf-form table { font-size: .82rem; }
    .w-prod { width: 60%; }
  }

  .btn { border:1px solid #d1d5db; padding:.5rem .75rem; }
  .btn-primary { background:#228B22; color:#fff; border-color:#228B22; }
  .btn-ghost { background:#fff; color:#0f172a; }
  .muted { color:#64748b; font-size:.78rem; }
</style>

<div
  id="dms-orders-create-root"
  class="kf-form max-w-7xl mx-auto px-4 py-6"
  data-module-base="<?= h($module_base) ?>"
>
  <div class="flex items-center justify-between mb-4 gap-4">
    <div>
      <h1 class="text-xl font-semibold text-slate-900 flex items-center gap-2">
        <span>New Orders</span>
        <span class="text-xs px-2 py-0.5 rounded-full"
              style="background:rgba(34,139,34,0.08);color:#166534;">
          <?= $isEdit ? 'Edit Order' : 'Create Order' ?>
        </span>
      </h1>
      <p class="muted mt-1">
        Capture distributor orders with promo tracking and full lookup support.
      </p>
      <div class="text-xs text-slate-500 space-x-2 mt-1">
        <span>Shortcuts:</span>
        <span class="border px-1">⌘/Ctrl</span> + <span class="border px-1">Enter</span> Save
        <span class="hidden md:inline"> · </span>
        <span class="hidden md:inline"><span class="border px-1">+</span> add paid</span>
        <span class="hidden md:inline"> · </span>
        <span class="hidden md:inline"><span class="border px-1">Shift</span> + <span class="border px-1">+</span> add promo</span>
      </div>
    </div>

    <!-- Top-right subpage menu (wired to real routes) -->
    <nav class="flex flex-wrap justify-end gap-2 text-xs sm:text-sm">
      <?php foreach ($tabs as $tab):
          $active = ($tab['key'] === $currentKey);
      ?>
        <a
          href="<?= h($tab['href']) ?>"
          class="px-3 py-1.5 rounded-full border
                 <?= $active
                    ? 'text-white'
                    : 'text-slate-700 hover:bg-slate-50'; ?>"
          style="<?= $active
                    ? 'background:#228B22;border-color:#228B22;'
                    : 'background:#ffffff;border-color:#e5e7eb;'
                 ?>"
        >
          <?= h($tab['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <?php if (!empty($flashErrors ?? [])): ?>
    <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded text-red-800">
      <ul class="list-disc ml-5">
        <?php foreach ($flashErrors as $e): ?>
          <li><?= h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form id="orderForm" method="POST" action="<?= h($postUrl) ?>">
    <?php
    $csrfInput = function_exists('csrf_field') ? (string)csrf_field() : '';
    if (!str_contains($csrfInput, 'name="_csrf"')) {
      if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
      $tok = $_SESSION['_csrf'] ?? ($_SESSION['csrf'] ?? '');
      $csrfInput .= '<input type="hidden" name="_csrf" value="'.h($tok).'">';
    }
    echo $csrfInput;
    ?>

    <!-- Hidden fields -->
    <input type="hidden" name="order_no" id="order_no" value="<?= h($nextNo) ?>">

    <input type="hidden" name="sr_user_id"  id="sr_user_id"  value="<?= (int)($srId ?: 0) ?>">
    <input type="hidden" name="dsr_user_id" id="dsr_user_id" value="<?= (int)($dsrId ?: 0) ?>">

    <input type="hidden" name="customer_id"   id="customer_id"   value="<?= (int)$cid ?: '' ?>">
    <input type="hidden" name="customer_name" id="customer_name" value="<?= h($custName) ?>">

    <input type="hidden" name="supplier_id"   id="supplier_id"   value="<?= (int)$supplierId ?: '' ?>">
    <input type="hidden" name="supplier_name" id="supplier_name" value="<?= h($suppName) ?>">

    <input type="hidden" name="discount_type" id="__disc_type" value="<?= h($dt) ?: 'amount' ?>">
    <input type="hidden" name="status"        id="__status"    value="<?= h($st) ?: 'draft' ?>">
    <input type="hidden" name="grand_total"   id="grand_total" value="0.00">

    <!-- SEGMENT 1 · Assignees & Dates -->
    <div>
      <div class="seg-title">Segment 1 — Assignees & Dates</div>
      <div class="seg-kicker">Pick SR/DSR and schedule order &amp; delivery dates.</div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Order No</label>
          <input id="order_no_display" class="w-full border px-3 py-2 bg-slate-50" value="<?= h($nextNo ?: '—') ?>" readonly>
          <div class="muted mt-1">Auto-generated; not editable.</div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">SR</label>
          <input id="sr_input" class="w-full border px-3 py-2"
                 placeholder="Search SR…" autocomplete="off"
                 value="<?= h($srName) ?>"
                 data-kf-lookup="users"
                 data-kf-param-role="sr"
                 data-kf-target-id="#sr_user_id"
                 data-kf-target-name="#sr_input">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div>
          <label class="block text-sm font-medium mb-1">DSR (Optional)</label>
          <input id="dsr_input" class="w-full border px-3 py-2"
                 placeholder="Search DSR…" autocomplete="off"
                 value="<?= h($dsrName) ?>"
                 data-kf-lookup="users"
                 data-kf-param-role="dsr"
                 data-kf-target-id="#dsr_user_id"
                 data-kf-target-name="#dsr_input">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Order Date</label>
            <input type="date" name="order_date" value="<?= h(substr($od,0,10)) ?>" class="w-full border">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Delivery Date</label>
            <input type="date" name="delivery_date" value="<?= h(substr($dd,0,10)) ?>" class="w-full border">
          </div>
        </div>
      </div>
    </div>

    <!-- SEGMENT 2 · Parties -->
    <div>
      <div class="seg-title">Segment 2 — Parties</div>
      <div class="seg-kicker">Select the Customer and Supplier for this order.</div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2">
          <label class="block text-sm font-medium mb-1">Customer</label>
          <div class="flex items-center gap-2">
            <input id="customer_input" class="flex-1 border px-3 py-2"
                   placeholder="Search customers…" autocomplete="off"
                   value="<?= h($custName) ?>"
                   data-kf-lookup="customers"
                   data-kf-target-id="#customer_id"
                   data-kf-target-name="#customer_name"
                   data-kf-target-code="#customer_code">
            <input id="customer_code" type="text" readonly class="w-40 border bg-white text-slate-700" placeholder="CID-000000" value="">
          </div>
          <div class="mt-2 flex items-center gap-3">
            <button type="button" id="toggle_new_customer" class="text-sm" style="color:var(--brand)">+ Add new customer</button>
            <span class="muted">or pick existing via lookup</span>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Supplier</label>
          <div class="flex items-center gap-2">
            <input id="supplier_input" class="flex-1 border px-3 py-2"
                   placeholder="Search suppliers…" autocomplete="off"
                   value="<?= h($suppName) ?>"
                   data-kf-lookup="suppliers"
                   data-kf-target-id="#supplier_id"
                   data-kf-target-name="#supplier_name"
                   data-kf-target-code="#supplier_code">
            <input id="supplier_code" type="text" readonly class="w-40 border bg-white text-slate-700" placeholder="SUP-000000" value="">
          </div>
        </div>
      </div>

      <div id="new_customer_box" class="hidden border p-4 mt-3">
        <div class="text-sm font-medium mb-2">New Customer</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs text-slate-600">Name</label>
            <input type="text" id="nc_name" class="mt-1 w-full border" placeholder="Customer name">
          </div>
          <div>
            <label class="block text-xs text-slate-600">Phone</label>
            <input type="text" id="nc_phone" class="mt-1 w-full border" placeholder="01XXXXXXXXX">
          </div>
          <div>
            <label class="block text-xs text-slate-600">Address</label>
            <input type="text" id="nc_address" class="mt-1 w-full border" placeholder="Address (optional)">
          </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
          <button type="button" id="nc_save" class="btn btn-primary">Save New Customer</button>
          <button type="button" id="nc_cancel" class="btn btn-ghost">Cancel</button>
          <span id="nc_msg" class="muted"></span>
        </div>
      </div>
    </div>

    <!-- SEGMENT 3 · Status & Discount -->
    <div>
      <div class="seg-title">Segment 3 — Status & Discount</div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <div id="__status_tabs" class="flex flex-wrap gap-2">
            <?php foreach (['draft'=>'DRAFT','confirmed'=>'CONFIRMED','cancelled'=>'CANCELLED'] as $k=>$label): $on = $st === $k; ?>
              <button type="button" data-val="<?= $k ?>" class="tab px-3 py-2 text-sm <?= $on ? 'tab--on' : '' ?>"><?= $label ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Discount Type</label>
            <div id="__disc_tabs" class="flex flex-wrap gap-2">
              <?php foreach (['amount'=>'Amount','percent'=>'Percent'] as $k=>$label): $on = $dt === $k; ?>
                <button type="button" data-val="<?= $k ?>" class="tab px-3 py-2 text-sm <?= $on ? 'tab--on' : '' ?>"><?= $label ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Discount Value</label>
            <input type="number" step="0.01" min="0" name="discount_value" id="discount_value" class="w-full border" value="<?= h($dv) ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- SEGMENT 4 · Order Items (paid) -->
    <div>
      <div class="seg-title">Segment 4 — Order Items (paid)</div>
      <div class="seg-kicker">Use the product lookup; unit price fills automatically. Edit if needed.</div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border">
          <thead>
            <tr class="bg-gray-50">
              <th class="px-3 py-2 text-left w-prod">Product</th>
              <th class="px-3 py-2 text-right w-[10%]">Qty</th>
              <th class="px-3 py-2 text-right w-[15%]">Unit Price</th>
              <th class="px-3 py-2 text-right w-[21%]">Line Total</th>
              <th class="px-3 py-2 w-[4%]"></th>
            </tr>
          </thead>
          <tbody id="lines"></tbody>
          <tfoot>
            <tr>
              <td colspan="5" class="px-3 py-2">
                <button type="button" id="addLine" class="btn">+ Add paid line</button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- SEGMENT 5 · Promotional Products (free) -->
    <div>
      <div class="seg-title">Segment 5 — Promotional Products (free)</div>
      <div class="seg-kicker">Promo items are free and do not affect the invoice amount.</div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border">
          <thead>
            <tr class="bg-gray-50">
              <th class="px-3 py-2 text-left w-[60%]">Product (promo)</th>
              <th class="px-3 py-2 text-right w-[14%]">Qty</th>
              <th class="px-3 py-2 text-right w-[22%]">Est. Unit Value</th>
              <th class="px-3 py-2 w-[4%]"></th>
            </tr>
          </thead>
          <tbody id="promoLines"></tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="px-3 py-2">
                <button type="button" id="addPromoLine" class="btn">+ Add promotional line</button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-3">
        <div class="border p-3">
          <div class="text-xs text-slate-500">Promo Items (count)</div>
          <div class="text-lg font-semibold" id="t_promo_count">0</div>
        </div>
        <div class="border p-3">
          <div class="text-xs text-slate-500">Promo Estimated Value</div>
          <div class="text-lg font-semibold" id="t_promo_value">0.00</div>
        </div>
        <div class="border p-3">
          <div class="text-xs text-slate-500">Invoice Impact</div>
          <div class="text-lg font-semibold">0.00</div>
        </div>
      </div>
    </div>

    <!-- SEGMENT 6 · Totals & Submit -->
    <div>
      <div class="seg-title">Segment 6 — Totals &amp; Submit</div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="border p-3">
          <div class="text-xs text-slate-500">Items Subtotal</div>
          <div class="text-lg font-semibold" id="t_sub">0.00</div>
        </div>
        <div class="border p-3">
          <div class="text-xs text-slate-500">Discount</div>
          <div class="text-lg font-semibold" id="t_disc">0.00</div>
        </div>
        <div class="border p-3">
          <div class="text-xs text-slate-500">Grand Total</div>
          <div class="text-lg font-semibold" id="t_grand">0.00</div>
        </div>
      </div>

      <div class="flex justify-end mt-4">
        <button type="submit" id="saveBtn" class="btn btn-primary">
          <?= $isEdit ? 'Update Order' : 'Save Order' ?>
        </button>
      </div>
    </div>
  </form>

  <!-- How to use this page -->
  <div class="mt-8 border-t pt-4 text-xs text-slate-600 space-y-1">
    <div class="font-semibold text-slate-800 mb-1">How to use this page</div>
    <ol class="list-decimal ml-4 space-y-1">
      <li>Select SR / DSR and set the order &amp; delivery dates in <b>Segment 1</b>.</li>
      <li>Pick or create the <b>Customer</b> (and Supplier if needed) in <b>Segment 2</b>. New customers are saved via AJAX and bound to this order.</li>
      <li>Choose order <b>Status</b> and <b>Discount</b> mode in <b>Segment 3</b>. Totals will refresh automatically.</li>
      <li>Add paid product lines in <b>Segment 4</b>. Use product lookup; quantities and prices calculate line totals and grand total.</li>
      <li>Add free promo items in <b>Segment 5</b> if applicable. They do not affect the invoice amount but are tracked.</li>
      <li>Review the summary in <b>Segment 6</b>. Press <b>Ctrl/⌘ + Enter</b> to save quickly, or click <b><?= $isEdit ? 'Update Order' : 'Save Order' ?></b>.</li>
    </ol>
  </div>
</div>

<script>
const API_BASE    = '<?=h($module_base)?>'.replace(/\/$/,'');
const HYDRATE_URL = <?= $hydrateUrl ? json_encode($hydrateUrl) : 'null' ?>;
const ENDPOINTS   = <?= json_encode($endpoints, JSON_UNESCAPED_SLASHES) ?>;

const money = n => (Math.round((+n || 0) * 100) / 100).toFixed(2);

const form        = document.getElementById('orderForm');

const statusHidden = document.getElementById('__status');
const statusTabs   = document.getElementById('__status_tabs');
const discHidden   = document.getElementById('__disc_type');
const discTabs     = document.getElementById('__disc_tabs');

const orderNoHidden  = document.getElementById('order_no');
const orderNoDisplay = document.getElementById('order_no_display');

const tbody      = document.getElementById('lines');
const pbody      = document.getElementById('promoLines');
const tSub       = document.getElementById('t_sub');
const tDisc      = document.getElementById('t_disc');
const tGrand     = document.getElementById('t_grand');
const tPromoCnt  = document.getElementById('t_promo_count');
const tPromoVal  = document.getElementById('t_promo_value');
const gTotal     = document.getElementById('grand_total');
const discountInput = document.getElementById('discount_value');

function attachTabs(box, hidden){
  if(!box||!hidden) return;
  box.addEventListener('click', e=>{
    const b = e.target.closest('button[data-val]'); if(!b) return;
    hidden.value = b.dataset.val;
    box.querySelectorAll('button[data-val]').forEach(x=>x.classList.remove('tab--on'));
    b.classList.add('tab--on');
    recomputeTotals();
  });
}
attachTabs(statusTabs, statusHidden);
attachTabs(discTabs,   discHidden);

async function refreshNextOrderNo() {
  try {
    if (orderNoHidden.value) {
      orderNoDisplay.value = orderNoHidden.value;
      return;
    }
    const r = await fetch(ENDPOINTS.nextOrderNo, {
      headers:{
        'Accept':'application/json',
        'X-Requested-With':'XMLHttpRequest'
      },
      credentials:'include'
    });
    if (!r.ok) throw new Error('HTTP '+r.status);
    const js = await r.json();
    const next = (js && (js.next_no || js.nextNo || js.order_no)) || '';
    if (next) {
      orderNoHidden.value  = next;
      orderNoDisplay.value = next;
    } else {
      orderNoDisplay.value = '—';
    }
  } catch (_) {
    orderNoDisplay.value = orderNoHidden.value || '—';
  }
}

function recomputeTotals(){
  let sub=0;
  tbody.querySelectorAll('tr').forEach(tr=>{
    const q=+(tr.querySelector('.qty')?.value||0);
    const p=+(tr.querySelector('.price')?.value||0);
    sub+=q*p;
    const ln = tr.querySelector('.line'); if (ln) ln.textContent = money(q*p);
  });
  const dt = discHidden.value;
  const dv = +(discountInput?.value||0);
  const disc = dt==='percent' ? Math.min(sub, sub*(dv/100)) : Math.min(sub, dv);
  tSub.textContent   = money(sub);
  tDisc.textContent  = money(disc);
  const grand = Math.max(0, sub - disc);
  tGrand.textContent = money(grand);
  gTotal.value       = money(grand);

  let promoCount = 0, promoValue = 0;
  pbody.querySelectorAll('tr').forEach(tr=>{
    const q=+(tr.querySelector('.pqty')?.value||0);
    const p=+(tr.querySelector('.pprice')?.value||0);
    if (q>0) promoCount += q;
    promoValue += q*p;
  });
  tPromoCnt.textContent = String(promoCount);
  tPromoVal.textContent = money(promoValue);
}
discountInput?.addEventListener('input', recomputeTotals);

function addPaidLine(pid=0, pname='', qty=1, price=0){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="px-3 py-2">
      <input class="prod_input w-full border px-3 py-2"
             placeholder="Search product…" autocomplete="off"
             value="${(pname||'').replace(/"/g,'&quot;')}"
             data-kf-lookup="products">
      <input type="hidden" class="pid"  value="${pid||0}">
    </td>
    <td class="px-3 py-2 text-right">
      <input type="number" step="0.01" min="0"
             class="qty w-28 text-right border"
             value="${qty||1}">
    </td>
    <td class="px-3 py-2 text-right">
      <input type="number" step="0.01" min="0"
             class="price w-32 text-right border"
             value="${price||0}">
    </td>
    <td class="px-3 py-2 text-right"><span class="line">0.00</span></td>
    <td class="px-3 py-2 text-right"><button type="button" class="rm btn">✕</button></td>
  `;
  const qInp  = tr.querySelector('.qty');
  const pInp  = tr.querySelector('.price');
  const pidEl = tr.querySelector('.pid');
  const prodInput = tr.querySelector('.prod_input');

  const sync  = ()=>{
    const ln = tr.querySelector('.line');
    if (ln) ln.textContent = money((+qInp.value||0) * (+pInp.value||0));
    recomputeTotals();
  };

  qInp.addEventListener('input', sync);
  pInp.addEventListener('input', sync);
  tr.querySelector('.rm').addEventListener('click', ()=>{
    tr.remove();
    recomputeTotals();
  });

  tbody.appendChild(tr);

  if (window.KF && KF.lookup && typeof KF.lookup.bind === 'function') {
    try {
      KF.lookup.bind({
        el: tr,
        entity: 'products',
        min: 1,
        limit: 50,
        onPick(rowData) {
          pidEl.value = rowData.id ?? pidEl.value ?? 0;
          const label = rowData.label || rowData.name || rowData.code || '';
          if (prodInput.hasAttribute('contenteditable')) prodInput.textContent = label;
          else prodInput.value = label;
          if (rowData.unit_price != null) {
            pInp.value = Number(rowData.unit_price);
          }
          sync();
        }
      });
    } catch (e) {
      window.KF?.rescan?.(tr);
    }
  } else {
    window.KF?.rescan?.(tr);
  }

  sync();
}

function addPromoLine(pid=0, pname='', qty=1, price=0){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="px-3 py-2">
      <input class="pprod_input w-full border px-3 py-2"
             placeholder="Search product (promo)…"
             autocomplete="off"
             value="${(pname||'').replace(/"/g,'&quot;')}"
             data-kf-lookup="products">
      <input type="hidden" class="ppid" value="${pid||0}">
    </td>
    <td class="px-3 py-2 text-right">
      <input type="number" step="0.01" min="0"
             class="pqty w-28 text-right border"
             value="${qty||1}">
    </td>
    <td class="px-3 py-2 text-right">
      <input type="number" step="0.01" min="0"
             class="pprice w-32 text-right border"
             value="${price||0}"
             title="Estimated unit value (optional)">
    </td>
    <td class="px-3 py-2 text-right"><button type="button" class="rm btn">✕</button></td>
  `;
  const pqty = tr.querySelector('.pqty');
  const pprice = tr.querySelector('.pprice');
  const ppid = tr.querySelector('.ppid');
  const pprodInput = tr.querySelector('.pprod_input');

  const sync = ()=>recomputeTotals();
  pqty.addEventListener('input', sync);
  pprice.addEventListener('input', sync);
  tr.querySelector('.rm').addEventListener('click', ()=>{
    tr.remove();
    recomputeTotals();
  });

  pbody.appendChild(tr);

  if (window.KF && KF.lookup && typeof KF.lookup.bind === 'function') {
    try {
      KF.lookup.bind({
        el: tr,
        entity: 'products',
        min: 1,
        limit: 50,
        onPick(rowData) {
          ppid.value = rowData.id ?? ppid.value ?? 0;
          const label = rowData.label || rowData.name || rowData.code || '';
          if (pprodInput.hasAttribute('contenteditable')) pprodInput.textContent = label;
          else pprodInput.value = label;
          if (rowData.unit_price != null &&
              (!pprice.value || Number(pprice.value) === 0)) {
            pprice.value = Number(rowData.unit_price);
          }
          sync();
        }
      });
    } catch (e) {
      window.KF?.rescan?.(tr);
    }
  } else {
    window.KF?.rescan?.(tr);
  }

  sync();
}

document.getElementById('addLine')
  .addEventListener('click', ()=>{
    addPaidLine();
    tbody.querySelector('tr:last-child .prod_input')?.focus();
  });

document.getElementById('addPromoLine')
  .addEventListener('click', ()=>{
    addPromoLine();
    pbody.querySelector('tr:last-child .pprod_input')?.focus();
  });

const newBtn    = document.getElementById('toggle_new_customer');
const newBox    = document.getElementById('new_customer_box');
const ncBtn     = document.getElementById('nc_save');
const ncCancel  = document.getElementById('nc_cancel');
const ncMsg     = document.getElementById('nc_msg');

newBtn?.addEventListener('click', ()=> newBox.classList.toggle('hidden'));
ncCancel?.addEventListener('click', ()=>{
  newBox.classList.add('hidden');
  ncMsg.textContent='';
});

async function saveNewCustomer() {
  const name = document.getElementById('nc_name').value.trim();
  const phone= document.getElementById('nc_phone').value.trim();
  const addr = document.getElementById('nc_address').value.trim();
  if (!name) { ncMsg.textContent = 'Name is required.'; return; }

  ncBtn.disabled = true;
  ncMsg.textContent = 'Saving…';
  try {
    const fd = new FormData();
    fd.append('name', name);
    if (phone) fd.append('phone', phone);
    if (addr)  fd.append('address', addr);

    const csrf = form.querySelector('input[name="_csrf"]')?.value || '';
    if (csrf) fd.append('_csrf', csrf);

    const r = await fetch(ENDPOINTS.createCustomer, {
      method: 'POST',
      headers: { 'X-Requested-With':'XMLHttpRequest' },
      credentials: 'include',
      body: fd
    });
    if (!r.ok) throw new Error('HTTP '+r.status);
    const js = await r.json();

    if (js && (js.ok || js.success)) {
      const cust = js.customer || js.data || {};
      const id   = +(cust.id || 0);
      const nameOut = String(cust.name || cust.label || '').trim();
      const codeOut = String(cust.code || cust.customer_code || '').trim();

      if (id > 0 && nameOut) {
        document.getElementById('customer_id').value    = String(id);
        document.getElementById('customer_name').value  = nameOut;
        document.getElementById('customer_input').value = nameOut;
        const codeBox = document.getElementById('customer_code');
        if (codeBox) codeBox.value = codeOut || codeBox.value;

        newBox.classList.add('hidden');
        document.getElementById('nc_name').value    = '';
        document.getElementById('nc_phone').value   = '';
        document.getElementById('nc_address').value = '';
        ncMsg.textContent = 'Customer added.';
      } else {
        throw new Error('Invalid response payload.');
      }
    } else {
      const msg = (js && (js.message||js.error)) || 'Failed to create customer.';
      throw new Error(msg);
    }
  } catch (err) {
    ncMsg.textContent = String(err.message || err || 'Error');
  } finally {
    ncBtn.disabled = false;
    setTimeout(()=>{ ncMsg.textContent=''; }, 2500);
  }
}
ncBtn?.addEventListener('click', saveNewCustomer);

let submitted = false;
form.addEventListener('submit', (event)=>{
  if (submitted) { event.preventDefault(); return false; }

  form.querySelectorAll('.dyn-item').forEach(n=>n.remove());

  let idx = 0;
  tbody.querySelectorAll('tr').forEach(tr=>{
    const pid   = +(tr.querySelector('.pid')?.value||0);
    let label   = (tr.querySelector('.prod_input')?.value||'').trim();
    if (label === '' && pid > 0) { label = `PID-${pid}`; }
    const qty   = +(tr.querySelector('.qty')?.value||0);
    const price = +(tr.querySelector('.price')?.value||0);

    if ((label === '' && pid === 0) || qty <= 0) return;

    const add = (n,v)=>{
      const i=document.createElement('input');
      i.type='hidden';
      i.className='dyn-item';
      i.name=`items[${idx}][${n}]`;
      i.value=String(v??'');
      form.appendChild(i);
    };

    if (pid) add('product_id', pid);
    add('product_name', label);
    add('qty',        qty.toFixed(2));
    add('unit_price', price.toFixed(2));
    add('line_total', (qty*price).toFixed(2));
    idx++;
  });

  if (idx === 0) {
    alert('Please add at least one product line with quantity.');
    event.preventDefault();
    return false;
  }

  let pidx = 0;
  pbody.querySelectorAll('tr').forEach(tr=>{
    const pid   = +(tr.querySelector('.ppid')?.value||0);
    const label = (tr.querySelector('.pprod_input')?.value||'').trim();
    const qty   = +(tr.querySelector('.pqty')?.value||0);
    const price = +(tr.querySelector('.pprice')?.value||0);
    if ((label === '' && pid === 0) || qty <= 0) return;

    const add = (n,v)=>{
      const i=document.createElement('input');
      i.type='hidden';
      i.className='dyn-item';
      i.name=`promo_items[${pidx}][${n}]`;
      i.value=String(v??'');
      form.appendChild(i);
    };

    if (pid) add('product_id', pid);
    add('product_name', label);
    add('qty', qty.toFixed(2));
    add('unit_price', (price||0).toFixed(2));
    pidx++;
  });

  submitted = true;
});

(function boot(){
  const pack = <?= $isEdit ? json_encode([
    'id'=>$oid, 'customer_id'=>$cid, 'supplier_id'=>$supplierId,
    'status'=>$st, 'discount_type'=>$dt, 'discount_value'=>$dv,
    'order_date'=>substr($od,0,10), 'delivery_date'=>substr($dd,0,10),
    'items'=>$normPaid, 'promo_items'=>$normPromo,
  ]) : 'null' ?>;

  if (pack?.items?.length){
    tbody.innerHTML='';
    for (const ln of pack.items) {
      addPaidLine(+ln.product_id||0, ln.product_name||'', +ln.qty||1, +ln.price||0);
    }
  } else {
    addPaidLine();
  }

  if (pack?.promo_items?.length){
    pbody.innerHTML='';
    for (const ln of pack.promo_items) {
      addPromoLine(+ln.product_id||0, ln.product_name||'', +ln.qty||1, 0);
    }
  }

  refreshNextOrderNo();

  if (HYDRATE_URL) {
    fetch(HYDRATE_URL, {
      headers:{
        'Accept':'application/json',
        'X-Requested-With':'XMLHttpRequest'
      },
      credentials:'include'
    })
    .then(r=>r.ok?r.json():null)
    .then(js=>{
      if (!js) return;
      const items = Array.isArray(js.items)
        ? js.items
        : (Array.isArray(js.lines)?js.lines:[]);
      if (items?.length){
        tbody.innerHTML='';
        for (const ln of items){
          addPaidLine(
            +ln.product_id||0,
            String(ln.product_name||''),
            +ln.qty||1,
            +(ln.unit_price||ln.price||0)||0
          );
        }
      }
      const promos = Array.isArray(js.promo_items) ? js.promo_items : [];
      if (promos.length){
        pbody.innerHTML='';
        for (const ln of promos){
          addPromoLine(
            +ln.product_id||0,
            String(ln.product_name||''),
            +ln.qty||1,
            +(ln.unit_price||0)||0
          );
        }
      }
      recomputeTotals();
    })
    .catch(()=>{});
  }

  window.KF?.rescan?.(document);
  recomputeTotals();
})();

document.addEventListener('keydown', (e)=>{
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    e.preventDefault();
    form.requestSubmit();
  }
  if (e.key === '+') {
    if (e.shiftKey) {
      e.preventDefault();
      addPromoLine();
      pbody.querySelector('tr:last-child .pprod_input')?.focus();
    } else {
      e.preventDefault();
      addPaidLine();
      tbody.querySelector('tr:last-child .prod_input')?.focus();
    }
  }
});
</script>