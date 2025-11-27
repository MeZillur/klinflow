<?php
declare(strict_types=1);

/**
 * BizFlow — LTAs (Long-Term Agreements) — Create (Final)
 *
 * Robust implementation:
 * - One default line on load
 * - Add/remove rows working
 * - KF.lookup for supplier and items (if present)
 * - Item pick fills fields and recalculates totals
 *
 * Expected variables (safely defaulted):
 * - array  $org
 * - string $module_base
 * - array  $suppliers   (optional, used in noscript fallback)
 * - string $today       (Y-m-d)
 * - string $csrf
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org         = $org ?? [];
$orgName     = trim((string)($org['name'] ?? ''));
$module_base = rtrim((string)($module_base ?? $base ?? '/apps/bizflow'), '/');
$today       = $today ?? date('Y-m-d');
$csrf        = (string)($csrf ?? ($ctx['csrf'] ?? ''));
$suppliers   = $suppliers ?? [];
?>
<style>
  :root{--kf:#228B22;}
  .lta-shell{max-width:1200px;margin:0 auto;padding:16px;color:#111827;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;}
  .lta-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;}
  .lta-title{font-size:20px;font-weight:700}
  .lta-sub{font-size:12px;color:#6b7280;margin-top:6px}
  .grid-2{display:grid;grid-template-columns:1fr 360px;gap:12px}
  @media (max-width:1024px){ .grid-2{grid-template-columns:1fr} }
  .panel{background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px}
  .row{display:flex;gap:10px;flex-wrap:wrap}
  .col{flex:1 1 0;min-width:160px}
  .label{font-size:13px;font-weight:600;margin-bottom:6px}
  .input,.select,.textarea{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px;font-size:13px}
  .textarea{min-height:80px}
  .items-panel{margin-top:14px}
  .items-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .btn{background:var(--kf);color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;font-weight:600}
  .btn.secondary{background:#eef2f7;color:#111}
  .items-table{width:100%;border-collapse:collapse}
  .items-table th,.items-table td{padding:8px;border-bottom:8px solid #f9fafb;vertical-align:top}
  .item-input{padding:6px;border:1px solid #d1d5db;border-radius:8px;width:100%;font-size:13px}
  .text-right{text-align:right}
  .small{font-size:12px;color:#6b7280}
  .remove-btn{background:transparent;border:none;color:#ef4444;cursor:pointer;font-weight:700}
  /* Ensure lookup dropdowns appear above content */
  .kf-suggest, .choices__list--dropdown { z-index: 999999 !important; }
</style>

<div class="lta-shell">
  <div class="lta-header">
    <div>
      <div class="lta-title">New Long-Term Agreement<?= $orgName ? ' — '.$h($orgName) : '' ?></div>
      <div class="lta-sub">Define supplier, validity and ceiling. Then add LTA lines (items/services).</div>
    </div>
    <div>
      <a href="<?= $h($module_base) ?>/ltas" class="btn secondary" style="display:inline-flex;align-items:center;gap:8px;padding:8px 10px">← Back</a>
    </div>
  </div>

  <form id="ltaForm" action="<?= $h($module_base) ?>/ltas" method="post" autocomplete="off">
    <?php if ($csrf !== ''): ?>
      <input type="hidden" name="_token" value="<?= $h($csrf) ?>">
    <?php endif; ?>

    <div class="grid-2">
      <div>
        <div class="panel" id="ltaInfoTop">
          <!-- Row 1 -->
          <div class="row" style="margin-bottom:10px">
            <div class="col">
              <div class="label">LTA title</div>
              <input name="title" class="input" placeholder="e.g. ICT equipment framework 2025–2027">
            </div>
            <div style="width:220px">
              <div class="label">Status</div>
              <select name="status" class="select">
                <option value="draft">Draft</option>
                <option value="active" selected>Active</option>
                <option value="on_hold">On hold</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div style="width:160px">
              <div class="label">Currency</div>
              <select name="currency" id="lta_currency" class="select">
                <option value="BDT" selected>BDT</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
              </select>
            </div>
          </div>

          <!-- Row 2 -->
          <div class="row">
            <div class="col">
              <div class="label">Supplier</div>
              <div style="position:relative">
                <input id="supplier_lookup" class="input" type="text" placeholder="Start typing supplier name…" autocomplete="off">
                <input type="hidden" id="supplier_id" name="supplier_id" value="">
              </div>
              <noscript>
                <select name="supplier_id" class="select" style="margin-top:6px">
                  <option value="">— Select supplier —</option>
                  <?php foreach ($suppliers as $s): 
                    $sid = (int)($s['id'] ?? 0);
                    $label = $h(trim((string)($s['name'] ?? 'Supplier #' . $sid)));
                  ?>
                    <option value="<?= $sid ?>"><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </noscript>
              <div class="small">Supplier holding this framework</div>
            </div>

            <div class="col">
              <div class="label">Start date</div>
              <input class="input" type="date" name="start_date" value="<?= $h($today) ?>">
            </div>

            <div style="width:220px">
              <div class="label">End date</div>
              <input class="input" type="date" name="end_date">
            </div>
          </div>
        </div>

        <div class="panel" style="margin-top:12px">
          <div class="label">Reference / contract no.</div>
          <input name="reference_no" class="input" placeholder="Framework agreement ref / contract no.">
          <div class="label" style="margin-top:10px">Maximum contract value (optional)</div>
          <input name="max_value" class="input" type="number" step="0.01" min="0" placeholder="0.00">
        </div>

        <div class="panel" style="margin-top:12px">
          <div class="label">Scope & notes</div>
          <textarea name="notes" class="textarea" placeholder="Short description of scope"></textarea>
          <div style="height:8px"></div>
          <div class="label">Internal remarks</div>
          <textarea name="internal_notes" class="textarea" placeholder="Internal only"></textarea>
        </div>
      </div>

      <div>
        <div class="panel">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <div style="font-weight:700">Actions</div>
            <div>
              <button type="button" id="btnAddLineTop" class="btn secondary" style="margin-right:8px">+ Add line</button>
              <button type="submit" class="btn">Save LTA</button>
            </div>
          </div>

          <div style="font-size:13px;color:#374151">
            <div style="margin-bottom:8px"><strong>Estimated ceiling</strong></div>
            <div style="font-size:18px" id="estimatedTotal">0.00</div>
            <div class="small" style="margin-top:8px">Sum of (unit_price × max_qty) across all lines.</div>
          </div>
        </div>

        <div class="panel" style="margin-top:12px">
          <div style="font-weight:700;margin-bottom:8px">Quick tips</div>
          <ul class="small">
            <li>Use supplier lookup to ensure supplier identity is consistent.</li>
            <li>Pick items to auto-fill unit, price and description.</li>
            <li>Leave End date empty for open-ended LTA if allowed.</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="items-panel">
      <div class="panel">
        <div class="items-header">
          <div style="font-weight:700">LTA lines (items & services)</div>
          <div class="small">Define items/services with unit, currency, price and maximum quantity.</div>
        </div>

        <table class="items-table" id="itemsTable" aria-label="LTA lines">
          <thead>
            <tr>
              <th style="width:40px"></th>
              <th>Item / service</th>
              <th>Description</th>
              <th style="width:90px">Unit</th>
              <th style="width:90px">Currency</th>
              <th style="width:110px">Unit price</th>
              <th style="width:110px">Max qty</th>
              <th style="width:60px"></th>
            </tr>
          </thead>
          <tbody id="itemsBody"></tbody>
        </table>
      </div>
    </div>

  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const itemsBody = document.getElementById('itemsBody');
  const addBtnTop = document.getElementById('btnAddLineTop');
  const estimatedEl = document.getElementById('estimatedTotal');
  const currencySelect = document.getElementById('lta_currency');
  const supplierInput = document.getElementById('supplier_lookup');
  const supplierIdInput = document.getElementById('supplier_id');

  // escape helper
  function esc(s){ return (s==null)?'':String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  // Create one line by default and functions for add/remove
  function createLine(data = {}) {
    const idx = itemsBody.children.length;
    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
      <td style="text-align:right;padding:8px">${idx+1}</td>
      <td style="padding:8px">
        <div style="position:relative">
          <input class="item-name item-input" name="items[${idx}][name]" placeholder="Item / service name" autocomplete="off" value="${esc(data.name)}"/>
          <input type="hidden" class="item-id" name="items[${idx}][item_id]" value="${esc(data.item_id)}"/>
        </div>
        <div style="margin-top:6px">
          <input class="item-code item-input" name="items[${idx}][code]" placeholder="Code (optional)" value="${esc(data.code)}"/>
        </div>
      </td>
      <td style="padding:8px"><input class="item-desc item-input" name="items[${idx}][description]" placeholder="Description" value="${esc(data.description)}"/></td>
      <td style="padding:8px"><input class="item-unit item-input" name="items[${idx}][unit]" value="${esc(data.unit || 'pcs')}"/></td>
      <td style="padding:8px"><input class="item-currency item-input" name="items[${idx}][currency]" value="${esc(data.currency || (currencySelect ? currencySelect.value : 'BDT'))}"/></td>
      <td style="padding:8px">
        <input class="item-price item-input" type="number" step="0.01" min="0" name="items[${idx}][unit_price]" value="${(data.unit_price!=null)?Number(data.unit_price):0}"/>
        <div class="small" style="text-align:right;margin-top:6px">Line total: <span class="line-total">0.00</span></div>
      </td>
      <td style="padding:8px"><input class="item-max item-input" type="number" step="0.001" min="0" name="items[${idx}][max_qty]" value="${(data.max_qty!=null)?data.max_qty:''}"/></td>
      <td style="padding:8px;text-align:center"><button type="button" class="remove-row remove-btn" title="Remove">✕</button></td>
    `;
    itemsBody.appendChild(tr);
    bindLine(tr);
    updateAllTotals();
    return tr;
  }

  function bindLine(tr) {
    const nameInput = tr.querySelector('.item-name');
    const idInput = tr.querySelector('.item-id');
    const codeInput = tr.querySelector('.item-code');
    const descInput = tr.querySelector('.item-desc');
    const unitInput = tr.querySelector('.item-unit');
    const currencyInput = tr.querySelector('.item-currency');
    const priceInput = tr.querySelector('.item-price');
    const maxInput = tr.querySelector('.item-max');
    const totalSpan = tr.querySelector('.line-total');
    const removeBtn = tr.querySelector('.remove-row');

    function recalcRow() {
      const p = Number(priceInput.value || 0);
      const q = Number(maxInput.value || 0);
      const t = p * q;
      totalSpan.textContent = t.toLocaleString('en-BD',{minimumFractionDigits:2,maximumFractionDigits:2});
      updateAllTotals();
    }

    // input events
    [priceInput, maxInput].forEach(i => i && i.addEventListener('input', recalcRow));

    // remove handler (delegated also works but keep direct)
    removeBtn.addEventListener('click', () => {
      tr.remove();
      // re-index rows
      Array.from(itemsBody.children).forEach((r,i)=>r.querySelector('td').textContent = i+1);
      updateAllTotals();
    });

    // currency change effect
    if (currencySelect) currencySelect.addEventListener('change', () => {
      if (!currencyInput.value) currencyInput.value = currencySelect.value;
      recalcRow();
    });

    // Bind KF.lookup for item names if available
    if (window.KF && KF.lookup && typeof KF.lookup.bind === 'function') {
      try {
        KF.lookup.bind({
          el: nameInput,
          entity: 'items',
          min: 1,
          limit: 30,
          onPick: function (r) {
            // fill fields defensively
            if (r.id != null) idInput.value = r.id;
            if (r.label) nameInput.value = r.label;
            if (r.code) codeInput.value = r.code;
            if (r.description) descInput.value = r.description;
            if (r.unit) unitInput.value = r.unit;
            if (r.currency) currencyInput.value = r.currency;
            const priceVal = r.unit_price ?? r.price ?? r.sale_price ?? null;
            if (priceVal != null) priceInput.value = Number(priceVal);
            // recalc after pick
            recalcRow();
          }
        });
      } catch (err) {
        console.warn('KF.lookup.bind error (items):', err);
      }
    }

    // initial recalc
    recalcRow();
  }

  function updateAllTotals(){
    let sum = 0;
    itemsBody.querySelectorAll('tr').forEach(tr => {
      const p = Number(tr.querySelector('.item-price').value || 0);
      const q = Number(tr.querySelector('.item-max').value || 0);
      sum += p * q;
    });
    estimatedEl.textContent = sum.toLocaleString('en-BD',{minimumFractionDigits:2,maximumFractionDigits:2});
    // update currency label in case top select changed
    // (visual elsewhere)
  }

  // Add one default line
  createDefault();

  function createDefault(){
    // Ensure itemsBody exists
    if (!itemsBody) return;
    // Clear any existing rows then add one default row
    itemsBody.innerHTML = '';
    createLine();
  }

  // Add line via top button
  if (addBtnTop) {
    addBtnTop.addEventListener('click', () => createLine());
  }

  // Also expose top add button inside left panel (if present)
  const topAddBtns = document.querySelectorAll('#btnAddLineTop, #btnAddLine');
  topAddBtns.forEach(b => b.addEventListener('click', () => createLine()));

  // Supplier lookup bind (single)
  if (supplierInput && window.KF && KF.lookup && typeof KF.lookup.bind === 'function') {
    try {
      KF.lookup.bind({
        el: supplierInput,
        entity: 'suppliers',
        min: 1,
        limit: 30,
        onPick: function (r) {
          if (r.id != null) supplierIdInput.value = r.id;
          if (r.label) supplierInput.value = r.label;
        }
      });
    } catch (err) { console.warn('KF.lookup.bind error (supplier):', err); }
  }

  // If KF.rescan exists, call once for safety after small delay
  if (window.KF && typeof KF.rescan === 'function') {
    setTimeout(() => { try { KF.rescan(document); } catch (e) { /* ignore */ } }, 100);
  }

  // Expose debug helpers
  window.LTA = window.LTA || {};
  window.LTA.addLine = createLine;
  window.LTA.recalc = updateAllTotals;

  // Helper functions used by event handlers
  function createLine(data) { return createLineInner(data); }
  function createLineInner(data) { return (function(d){ return (function(){ const el = createLine(d); return el; })(); })(data); }
});
</script>