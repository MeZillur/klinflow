<?php
declare(strict_types=1);

/**
 * DMS — Prepare Delivery Challan
 *
 * Expects from controller:
 *   - ?array  $invoice      dms_sales row
 *   - array   $lines        dms_sale_items rows
 *   - string  $module_base  like /t/{slug}/apps/dms
 *   - (optional) string $csrf_field pre-rendered hidden CSRF input
 */

$h    = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/dms'), '/');

$inv  = is_array($invoice ?? null) ? $invoice : null;
$rows = is_array($lines   ?? null) ? $lines   : [];

/* ---------- Labels ---------- */
$invoiceLabel = '';
$customerName = '';
$invoiceDate  = '';
if ($inv) {
    $invoiceLabel = (string)($inv['sale_no'] ?? $inv['invoice_no'] ?? ('#'.($inv['id'] ?? '')));
    $customerName = (string)($inv['customer_name'] ?? '');
    $invoiceDate  = substr((string)($inv['sale_date'] ?? $inv['invoice_date'] ?? ''), 0, 10);
}
?>
<div class="kf-wrap">
  <!-- Header / breadcrumb -->
  <header class="kf-header">
    <div>
      <div class="kf-eyebrow">Dispatch &amp; Delivery</div>
      <h1 class="kf-title">Prepare Delivery Challan</h1>
      <p class="kf-sub">
        Confirm which items you are dispatching from this invoice and create a delivery challan.
      </p>
    </div>

    <?php if ($inv): ?>
    <div class="kf-header-meta">
      <div class="kf-pill">
        <span class="kf-pill-label">Invoice</span>
        <span class="kf-pill-value"><?= $h($invoiceLabel) ?></span>
      </div>
      <div class="kf-pill">
        <span class="kf-pill-label">Customer</span>
        <span class="kf-pill-value"><?= $h($customerName ?: '—') ?></span>
      </div>
      <div class="kf-pill">
        <span class="kf-pill-label">Date</span>
        <span class="kf-pill-value"><?= $h($invoiceDate ?: '—') ?></span>
      </div>
      <a class="kf-link" href="<?= $h($base) ?>/sales/<?= $h((string)($inv['id'] ?? 0)) ?>" target="_blank">
        View invoice
      </a>
    </div>
    <?php endif; ?>
  </header>

  <!-- Lookups / quick actions -->
  <section class="kf-card kf-lookup">
    <div class="kf-lookup-row">
      <label class="kf-label">Invoice (ID or number)</label>
      <input id="inv_lookup"
             class="kf-ip"
             style="min-width:260px"
             placeholder="Search &amp; select invoice"
             autocomplete="off"
             data-kf-lookup="invoices"
             data-kf-min="1"
             data-kf-limit="30"
             data-kf-target-id="#__inv_id"
             data-kf-target-name="#inv_lookup" />
      <input type="hidden" id="__inv_id" value="<?= $h((string)($inv['id'] ?? 0)) ?>">

      <button id="btn_load" class="kf-btn kf-btn-brand" <?= $inv ? '' : 'disabled' ?>>Load</button>

      <span class="kf-muted kf-lookup-hint">
        Or type invoice ID and press <strong>Enter</strong>.
      </span>
    </div>

    <div class="kf-lookup-row kf-lookup-row-second">
      <label class="kf-label">Master challan (multiple invoices)</label>
      <input id="master_lookup"
             class="kf-ip"
             style="min-width:360px"
             placeholder="Search &amp; select invoices"
             autocomplete="off"
             data-kf-lookup="invoices"
             data-kf-min="1"
             data-kf-limit="50"
             data-kf-multi="1" />
      <input type="hidden" id="__master_ids" value="">
      <button id="btn_first" class="kf-btn">Load first selected</button>
      <a class="kf-btn kf-btn-ghost" href="<?= $h($base) ?>/challan">
        Back to challan board
      </a>
      <a class="kf-btn kf-btn-brand-outline" href="<?= $h($base) ?>/challan/master">
        Open Master Challan UI
      </a>
    </div>
  </section>

  <!-- Main form -->
  <form method="post"
        action="<?= $h($base) ?>/challan"
        id="challanForm"
        class="kf-form"
        autocomplete="off">

    <?php
    // CSRF fallback: either global csrf_field() helper or $csrf_field variable from controller
    if (function_exists('csrf_field')) {
        echo csrf_field();
    } elseif (!empty($csrf_field ?? '')) {
        echo $csrf_field;
    }
    ?>

    <input type="hidden" name="invoice_id" id="form_invoice_id"
           value="<?= $h((string)($inv['id'] ?? 0)) ?>">

    <?php if ($inv && $rows): ?>

      <!-- Dispatch header (ship-to / vehicle) -->
      <section class="kf-card kf-grid">
        <div class="kf-col">
          <h2 class="kf-section-title">Dispatch details</h2>
          <div class="kf-field">
            <label class="kf-label">Ship to name</label>
            <input name="ship_to_name" class="kf-ip"
                   placeholder="Customer / delivery contact"
                   value="<?= $h($customerName) ?>">
          </div>
          <div class="kf-field">
            <label class="kf-label">Ship to address</label>
            <textarea name="ship_to_addr" class="kf-ip kf-ip-textarea"
                      placeholder="Full delivery address"></textarea>
          </div>
        </div>

        <div class="kf-col">
          <h2 class="kf-section-title kf-section-title-spacer">&nbsp;</h2>
          <div class="kf-field kf-field-inline">
            <label class="kf-label">Vehicle no.</label>
            <input name="vehicle_no" class="kf-ip" placeholder="Eg. DHA-11-1234">
          </div>
          <div class="kf-field kf-field-inline">
            <label class="kf-label">Driver name</label>
            <input name="driver_name" class="kf-ip" placeholder="Driver / courier person">
          </div>
          <div class="kf-field">
            <label class="kf-label">Remarks</label>
            <input name="remarks" class="kf-ip" placeholder="Optional note for this challan">
          </div>
        </div>

        <div class="kf-col kf-summary">
          <div class="kf-summary-box">
            <div class="kf-summary-row">
              <span class="kf-summary-label">Invoice total</span>
              <span class="kf-summary-value">
                <?= $h(number_format((float)($inv['grand_total'] ?? 0), 2)) ?>
              </span>
            </div>
            <div class="kf-summary-row">
              <span class="kf-summary-label">Already dispatched</span>
              <span class="kf-summary-value" id="sumAlready">0.00</span>
            </div>
            <div class="kf-summary-row kf-summary-row-strong">
              <span class="kf-summary-label">This challan amount</span>
              <span class="kf-summary-value" id="grandTotal">0.00</span>
            </div>
          </div>

          <div class="kf-field">
            <label class="kf-label">Payment received now (optional)</label>
            <div class="kf-flex">
              <input type="number"
                     step="0.01"
                     min="0"
                     name="payment_received"
                     class="kf-ip kf-ip-amount"
                     placeholder="0.00">
              <button class="kf-btn kf-btn-brand" type="submit">
                Create challan
              </button>
            </div>
            <p class="kf-muted kf-small">
              If you enter an amount, it will be recorded against this challan as a collection.
            </p>
          </div>
        </div>
      </section>

      <!-- Line items -->
      <section class="kf-card kf-table-wrap">
        <div class="kf-table-header">
          <h2 class="kf-section-title">Items from invoice</h2>
          <label class="kf-check-all">
            <input type="checkbox" id="chk_all" checked>
            <span>Select all</span>
          </label>
        </div>

        <div class="kf-table-scroll">
          <table class="kf-tbl">
            <thead>
              <tr>
                <th class="kf-th-select"></th>
                <th class="kf-th-sl">SL</th>
                <th class="kf-th-text">Item</th>
                <th class="kf-th-num">Invoice qty</th>
                <th class="kf-th-num">Dispatch qty</th>
                <th class="kf-th-num">Unit price</th>
                <th class="kf-th-num">Line total</th>
              </tr>
            </thead>
            <tbody id="tbodyLines">
            <?php
            $i = 1;
            foreach ($rows as $r):
                $sid = (int)($r['id'] ?? $r['sale_item_id'] ?? 0);
                $qty = (float)($r['qty'] ?? 0);
                $up  = (float)($r['unit_price'] ?? $r['price'] ?? 0);
                $nm  = (string)($r['product_name'] ?? $r['name'] ?? '');
                $pid = $r['product_id'] ?? null;
            ?>
              <tr class="kf-line">
                <td class="kf-td-select">
                  <input type="checkbox" class="kf-rowchk" checked>
                  <input type="hidden" name="lines[<?= $sid ?>][sale_item_id]" value="<?= $h((string)$sid) ?>">
                  <input type="hidden" name="lines[<?= $sid ?>][product_id]"   value="<?= $h((string)($pid ?? '')) ?>">
                  <input type="hidden" name="lines[<?= $sid ?>][product_name]" value="<?= $h($nm) ?>">
                  <input type="hidden" name="lines[<?= $sid ?>][unit_price]"   value="<?= $h((string)$up) ?>">
                  <input type="hidden" name="lines[<?= $sid ?>][ordered_qty]"  value="<?= $h((string)$qty) ?>">
                </td>
                <td class="kf-td-sl"><?= $i++ ?></td>
                <td class="kf-td-text">
                  <div class="kf-name"><?= $h($nm) ?></div>
                </td>
                <td class="kf-td-num">
                  <span class="kf-ordered" data-ordered="<?= $h((string)$qty) ?>">
                    <?= number_format($qty, 2) ?>
                  </span>
                </td>
                <td class="kf-td-num">
                  <input class="kf-ip kf-ip-qty kf-right"
                         type="number"
                         step="0.01"
                         min="0"
                         max="<?= $h((string)$qty) ?>"
                         name="lines[<?= $sid ?>][qty]"
                         value="<?= $h((string)$qty) ?>">
                </td>
                <td class="kf-td-num">
                  <span class="kf-unit" data-unit-price="<?= $h((string)$up) ?>">
                    <?= number_format($up, 2) ?>
                  </span>
                </td>
                <td class="kf-td-num">
                  <span class="kf-line-total">0.00</span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="6" class="kf-td-total-label">This challan amount</th>
                <th class="kf-td-total-value">
                  <span id="grandTotalFoot">0.00</span>
                </th>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

    <?php else: ?>

      <section class="kf-card">
        <p class="kf-muted">
          Pick an invoice above to load items and prepare a challan.
        </p>
      </section>

    <?php endif; ?>

    <!-- How to use this page -->
    <section class="kf-card kf-help">
      <h2 class="kf-section-title">How to use this page</h2>
      <ol class="kf-help-list">
        <li>Select an invoice using the lookup box at the top or type its ID and press <strong>Enter</strong>.</li>
        <li>Review and edit the dispatch details (ship-to, vehicle, driver and remarks).</li>
        <li>For each item, adjust the <strong>Dispatch qty</strong> if you are sending a partial quantity, or untick rows you are not sending now.</li>
        <li>Check the <strong>This challan amount</strong> summary on the right to confirm totals.</li>
        <li>If you collected money at dispatch time, enter the amount in <strong>Payment received now</strong>.</li>
        <li>Click <strong>Create challan</strong> to save and move to the challan screen.</li>
      </ol>
    </section>

  </form>
</div>

<style>
  :root {
    --kf-brand:#228B22;
    --kf-tint:rgba(34,139,34,.06);
    --kf-bdr:rgba(15,23,42,.10);
    --kf-text:#0f172a;
    --kf-muted:#64748b;
    --kf-bg:#ffffff;
    --kf-line:#e5e7eb;
  }
  .kf-wrap{max-width:1200px;margin:0 auto;padding:1.5rem 1rem 2.5rem;}
  .kf-header{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:1.25rem;margin-bottom:1.25rem;}
  .kf-eyebrow{font-size:.75rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--kf-muted);margin-bottom:.1rem;}
  .kf-title{font-size:1.6rem;font-weight:600;color:var(--kf-text);margin:0;}
  .kf-sub{margin:.35rem 0 0;font-size:.9rem;color:var(--kf-muted);max-width:32rem;}
  .kf-header-meta{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:flex-end;}
  .kf-pill{display:flex;flex-direction:column;padding:.35rem .6rem;border-radius:999px;background:var(--kf-tint);border:1px solid rgba(34,139,34,.12);min-width:120px;}
  .kf-pill-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--kf-muted);}
  .kf-pill-value{font-size:.85rem;font-weight:600;color:var(--kf-text);}
  .kf-link{font-size:.8rem;color:var(--kf-brand);text-decoration:none;margin-left:.25rem;}
  .kf-link:hover{text-decoration:underline;}

  .kf-card{background:var(--kf-bg);border-radius:.9rem;border:1px solid var(--kf-bdr);padding:1rem 1.1rem;margin-bottom:1rem;box-shadow:0 10px 30px rgba(15,23,42,.04);}
  .kf-lookup-row{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:.4rem;}
  .kf-lookup-row-second{margin-top:.35rem;}
  .kf-label{font-size:.8rem;font-weight:500;color:var(--kf-muted);min-width:130px;}
  .kf-ip{border:1px solid var(--kf-line);border-radius:.6rem;padding:.4rem .55rem;font-size:.9rem;color:var(--kf-text);background:var(--kf-bg);min-width:0;}
  .kf-ip:focus{outline:2px solid rgba(34,139,34,.18);outline-offset:1px;border-color:var(--kf-brand);}
  .kf-ip-textarea{min-height:70px;resize:vertical;}
  .kf-ip-qty{max-width:6.5rem;}
  .kf-ip-amount{max-width:7.5rem;text-align:right;}
  .kf-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:.7rem;border:1px solid var(--kf-line);padding:.4rem .85rem;font-size:.8rem;font-weight:500;color:var(--kf-text);background:#f9fafb;cursor:pointer;white-space:nowrap;}
  .kf-btn:hover{background:#f3f4f6;}
  .kf-btn:disabled{opacity:.5;cursor:not-allowed;}
  .kf-btn-brand{background:var(--kf-brand);border-color:var(--kf-brand);color:#fff;}
  .kf-btn-brand:hover{background:#1d7a1d;}
  .kf-btn-ghost{background:transparent;}
  .kf-btn-brand-outline{border-color:var(--kf-brand);color:var(--kf-brand);background:rgba(34,139,34,.04);}
  .kf-muted{color:var(--kf-muted);}
  .kf-lookup-hint{font-size:.8rem;}
  .kf-small{font-size:.75rem;margin-top:.15rem;}

  .kf-form{margin-top:.6rem;}

  .kf-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1.1fr) minmax(0,1fr);gap:1rem;align-items:flex-start;}
  .kf-col{display:flex;flex-direction:column;gap:.65rem;}
  .kf-section-title{font-size:.95rem;font-weight:600;color:var(--kf-text);margin:0 0 .25rem;}
  .kf-section-title-spacer{visibility:hidden;}
  .kf-field{display:flex;flex-direction:column;gap:.2rem;}
  .kf-field-inline{flex-direction:row;align-items:center;}
  .kf-field-inline .kf-label{min-width:90px;}
  .kf-summary{align-self:stretch;}
  .kf-summary-box{border-radius:.9rem;border:1px dashed var(--kf-line);padding:.65rem .75rem;margin-bottom:.7rem;background:linear-gradient(135deg,rgba(34,139,34,.04),rgba(34,139,34,.01));}
  .kf-summary-row{display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.15rem;color:var(--kf-muted);}
  .kf-summary-row-strong{font-weight:600;color:var(--kf-text);margin-top:.15rem;}
  .kf-summary-label{margin-right:.5rem;}
  .kf-summary-value{text-align:right;min-width:80px;}

  .kf-flex{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;}

  .kf-table-wrap{padding-top:.75rem;}
  .kf-table-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem;}
  .kf-check-all{display:flex;align-items:center;gap:.35rem;font-size:.8rem;color:var(--kf-muted);}
  .kf-table-scroll{overflow-x:auto;border-radius:.8rem;border:1px solid var(--kf-line);}
  .kf-tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:.85rem;}
  .kf-tbl thead{background:#f9fafb;}
  .kf-tbl th,.kf-tbl td{padding:.45rem .55rem;border-bottom:1px solid var(--kf-line);}
  .kf-tbl th:first-child{border-top-left-radius:.8rem;}
  .kf-tbl th:last-child{border-top-right-radius:.8rem;}
  .kf-th-select,.kf-td-select{width:38px;text-align:center;}
  .kf-th-sl,.kf-td-sl{width:38px;text-align:right;color:var(--kf-muted);}
  .kf-th-text,.kf-td-text{text-align:left;}
  .kf-th-num,.kf-td-num{text-align:right;}
  .kf-name{font-weight:500;}
  .kf-ordered{font-variant-numeric:tabular-nums;color:var(--kf-muted);}
  .kf-unit,.kf-line-total,.kf-summary-value,.kf-td-total-value span{font-variant-numeric:tabular-nums;}
  .kf-right{text-align:right;}

  .kf-td-total-label{text-align:right;font-weight:600;padding:.6rem .55rem;}
  .kf-td-total-value{text-align:right;font-weight:700;padding:.6rem .55rem;}

  .kf-help-list{margin:.3rem 0 0;padding-left:1.2rem;font-size:.85rem;color:var(--kf-muted);}
  .kf-help-list li{margin:.15rem 0;}

  @media (max-width:960px){
    .kf-grid{grid-template-columns:minmax(0,1fr);}
    .kf-summary{order:-1;}
  }
</style>

<script>
(function(){
  const base = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;

  function recalcTotals(){
    let grand = 0;
    const rows = document.querySelectorAll('.kf-line');
    rows.forEach(function(tr){
      const chk  = tr.querySelector('.kf-rowchk');
      const qtyI = tr.querySelector('.kf-ip-qty');
      const unit = tr.querySelector('.kf-unit');
      const out  = tr.querySelector('.kf-line-total');
      if (!qtyI || !unit || !out) return;

      let qty  = parseFloat(qtyI.value || '0') || 0;
      let u    = parseFloat(unit.dataset.unitPrice || '0') || 0;
      let line = (chk && chk.checked) ? (qty * u) : 0;

      out.textContent = line.toFixed(2);
      grand += line;
    });

    const g1 = document.getElementById('grandTotal');
    const g2 = document.getElementById('grandTotalFoot');
    if (g1) g1.textContent = grand.toFixed(2);
    if (g2) g2.textContent = grand.toFixed(2);
  }

  document.addEventListener('DOMContentLoaded', function(){
    // Let global KF lookup rescan
    if (window.KF && typeof KF.rescan === 'function') {
      KF.rescan(document);
    }

    // Initial totals
    recalcTotals();

    // Per-row events
    document.querySelectorAll('.kf-line').forEach(function(tr){
      const qtyI = tr.querySelector('.kf-ip-qty');
      const chk  = tr.querySelector('.kf-rowchk');
      if (qtyI) qtyI.addEventListener('input', recalcTotals);
      if (chk)  chk.addEventListener('change', recalcTotals);
    });

    // Select all
    const chkAll = document.getElementById('chk_all');
    if (chkAll) {
      chkAll.addEventListener('change', function(){
        const on = !!chkAll.checked;
        document.querySelectorAll('.kf-rowchk').forEach(function(c){
          c.checked = on;
        });
        recalcTotals();
      });
    }

    // Load single invoice
    const btnLoad   = document.getElementById('btn_load');
    const invLookup = document.getElementById('inv_lookup');
    const invHidden = document.getElementById('__inv_id');

    function goLoad(){
      let id = 0;
      if (invHidden && invHidden.value) {
        id = parseInt(invHidden.value, 10) || 0;
      }
      if (!id && invLookup && invLookup.value) {
        id = parseInt(invLookup.value, 10) || 0;
      }
      if (!id) return;
      window.location.href = base + '/challan/prepare?invoice_id=' + id;
    }

    if (btnLoad) {
      btnLoad.addEventListener('click', function(ev){
        ev.preventDefault();
        goLoad();
      });
    }
    if (invLookup) {
      invLookup.addEventListener('keydown', function(ev){
        if (ev.key === 'Enter') {
          ev.preventDefault();
          goLoad();
        }
      });
    }

    // Master: load first selected id from hidden list if available
    const btnFirst = document.getElementById('btn_first');
    const masterHidden = document.getElementById('__master_ids');
    if (btnFirst && masterHidden) {
      btnFirst.addEventListener('click', function(ev){
        ev.preventDefault();
        const raw = (masterHidden.value || '').trim();
        if (!raw) return;
        const parts = raw.split(',').map(function(v){return parseInt(v,10)||0;}).filter(function(v){return v>0;});
        if (!parts.length) return;
        window.location.href = base + '/challan/prepare?invoice_id=' + parts[0];
      });
    }
  });
})();
</script>