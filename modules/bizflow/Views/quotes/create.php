<?php
/**
 * BizFlow — New Quote
 *
 * Expects:
 * - string $module_base
 * - array  $org
 * - array  $customers (optional)
 * - array  $items      (biz_items for this org, item_type='stock')
 * - string $today
 * - string $valid_until
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$orgName     = trim((string)($org['name'] ?? ''));
$customers   = $customers ?? [];
$items       = $items ?? [];
$today       = $today ?? date('Y-m-d');
$validUntil  = $valid_until ?? date('Y-m-d', strtotime('+7 days'));
?>
<style>
/* KF.lookup suggestion dropdown always on top */
.kf-suggest {
  z-index: 99999 !important;
}
</style>

<div class="space-y-6" x-data="bizQuoteCreate('<?= $h($module_base) ?>')">
  <!-- HEADER -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1 uppercase">
        <i class="fa-regular fa-file-lines"></i>
        <span>New quote</span>
      </div>
      <h1 class="mt-3 text-2xl md:text-3xl font-semibold tracking-tight">
        Create quote<?= $orgName ? ' — '.$h($orgName) : '' ?>
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 max-w-2xl">
        Use this screen to build a quotation from scratch. Add stock items, service items, or mix both.
        Later you’ll send, print, and download from the quotes list.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <a href="<?= $h($module_base) ?>/quotes"
         class="inline-flex items-center gap-1 px-3 py-2 text-xs border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa-solid fa-chevron-left text-[11px]"></i>
        <span>Back to quotes</span>
      </a>

      <!-- Save draft -->
      <button type="button"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 rounded-none"
              style="background:var(--brand);border-radius:0"
              @click="saveDraft()">
        <i class="fa-regular fa-floppy-disk"></i>
        <span>Save draft</span>
      </button>

      <!-- Final save (no email yet – only persists) -->
      <button type="button"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-emerald-700 border border-emerald-600 bg-white hover:bg-emerald-50 dark:bg-gray-900"
              @click="saveQuote()">
        <i class="fa-regular fa-circle-check"></i>
        <span>Save quote</span>
      </button>
    </div>
  </div>

  <!-- FORM -->
  <form id="quoteForm" class="space-y-5" @submit.prevent="saveDraft()">
    <!-- TOP GRID: CUSTOMER + META -->
    <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)] gap-4">
      <!-- Customer panel -->
      <div class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-4">
        <h2 class="text-sm font-semibold mb-1">Customer</h2>

        <!-- Customer via KF.lookup -->
        <div class="space-y-1">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
            Customer<span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <input type="text"
                   id="customer_lookup"
                   class="w-full pl-3 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                   placeholder="Start typing customer name…"
                   x-model="customerName"
                   x-init="
                     if (window.KF && KF.lookup && typeof KF.lookup.bind === 'function') {
                       const self = this;
                       KF.lookup.bind({
                         el: $el,
                         entity: 'customers',
                         min: 1,
                         limit: 30,
                         onPick(row) {
                           self.customerId   = row.id || '';
                           self.customerName = row.label || row.name || '';
                           const hid = document.getElementById('customer_id');
                           if (hid) hid.value = self.customerId;
                         }
                       });
                     }
                   ">
            <input type="hidden" id="customer_id" name="customer_id" x-model="customerId">
          </div>
        </div>

        <!-- Fallback select -->
        <?php if ($customers): ?>
          <div class="space-y-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
              Or pick from list
            </label>
            <select class="w-full text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-2"
                    data-choices
                    x-on:change="
                      customerId    = $event.target.value;
                      customerName  = $event.target.options[$event.target.selectedIndex].dataset.label || '';
                      document.getElementById('customer_id').value = customerId;
                    ">
              <option value="">— Select customer —</option>
              <?php foreach ($customers as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                        data-label="<?= $h($c['name']) ?>">
                  <?= $h($c['name']) ?><?= !empty($c['code']) ? ' — '.$h($c['code']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
              Contact person
            </label>
            <input type="text" name="customer_contact"
                   x-model="customerContact"
                   class="w-full text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
          </div>
          <div class="space-y-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
              Customer reference
            </label>
            <input type="text" name="customer_reference"
                   x-model="customerReference"
                   class="w-full text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                   placeholder="RFQ / tender ID, etc.">
          </div>
        </div>
      </div>

      <!-- Quote meta -->
      <div class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
        <h2 class="text-sm font-semibold mb-1">Quote details</h2>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
              Quote date
            </label>
            <input type="date" name="date"
                   x-model="date"
                   value="<?= $h($today) ?>"
                   class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
          </div>
          <div class="space-y-1">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
              Valid until
            </label>
            <input type="date" name="valid_until"
                   x-model="validUntil"
                   value="<?= $h($validUntil) ?>"
                   class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
          </div>
        </div>

        <div class="space-y-1">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
            External reference
          </label>
          <input type="text" name="external_ref"
                 x-model="externalRef"
                 class="w-full text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                 placeholder="Your internal reference, project name, etc.">
        </div>

        <div class="space-y-2">
          <span class="block text-xs font-medium text-gray-600 dark:text-gray-300">
            Quote type
          </span>
          <div class="flex flex-wrap gap-2 text-xs">
            <button type="button"
                    class="px-2.5 py-1 border border-gray-300 dark:border-gray-600"
                    :class="type==='mixed' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'"
                    @click="type='mixed'">
              Mixed (items + services)
            </button>
            <button type="button"
                    class="px-2.5 py-1 border border-gray-300 dark:border-gray-600"
                    :class="type==='stock' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'"
                    @click="type='stock'">
              Stock items only
            </button>
            <button type="button"
                    class="px-2.5 py-1 border border-gray-300 dark:border-gray-600"
                    :class="type==='service' ? 'bg-sky-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'"
                    @click="type='service'">
              Service only
            </button>
          </div>
          <p class="text-[11px] text-gray-500">
            Inventory businesses usually use <strong>Stock</strong> or <strong>Mixed</strong>.
            Service-only businesses can choose <strong>Service only</strong> and skip stock tracking entirely.
          </p>
        </div>
      </div>
    </section>

    <!-- LINE ITEMS -->
    <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pb-6">
      <div class="px-4 py-3 flex items-center justify-between border-b border-gray-100 dark:border-gray-800">
        <div class="flex items-center gap-2 text-sm font-semibold">
          <i class="fa-solid fa-list"></i>
          <span>Quote lines</span>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500">
          <span class="hidden sm:inline">Add:</span>
          <button type="button"
                  class="px-2 py-1 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1"
                  @click="addRow('item')">
            <i class="fa-solid fa-box-open text-[11px]"></i>
            <span>Item</span>
          </button>
          <button type="button"
                  class="px-2 py-1 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 flex items-center gap-1"
                  @click="addRow('service')">
            <i class="fa-solid fa-briefcase text-[11px]"></i>
            <span>Service</span>
          </button>
        </div>
      </div>

      <div class="overflow-x-auto overflow-y-visible relative pt-2">
        <table class="min-w-full text-xs align-middle">
          <thead class="bg-gray-50 dark:bg-gray-800/70 text-[11px] uppercase tracking-wide text-gray-500">
          <tr>
            <th rowspan="2" class="px-3 py-2 text-left w-6"></th>
            <th colspan="2" class="px-3 py-1 text-left">Item &amp; specification</th>
            <th rowspan="2" class="px-3 py-2 text-left w-20">Qty</th>
            <th rowspan="2" class="px-3 py-2 text-left w-24">Unit</th>
            <th rowspan="2" class="px-3 py-2 text-right w-28">Unit price</th>
            <th rowspan="2" class="px-3 py-2 text-right w-24">Disc %</th>
            <th rowspan="2" class="px-3 py-2 text-right w-32">Line total</th>
            <th rowspan="2" class="px-3 py-2 text-left w-28">Type</th>
          </tr>
          <tr>
            <th class="px-3 py-1 text-left">Item</th>
            <th class="px-3 py-1 text-left">Key features / specification</th>
          </tr>
          </thead>

          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <template x-for="(row, idx) in rows" :key="row.key">
            <tr class="align-middle">
              <!-- Remove button -->
              <td class="px-3 py-2">
                <button type="button"
                        class="text-rose-500 hover:text-rose-700"
                        title="Remove line"
                        @click="removeRow(idx)">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </td>

              <!-- ITEM CELL -->
              <td class="px-3 py-2 align-top min-w-[240px]">
                <!-- Hidden product_id -->
                <input type="hidden"
                       :id="'line_item_'+idx"
                       :name="'lines['+idx+'][product_id]'"
                       x-model="row.product_id">

                <!-- NEW: Item lookup via KF.lookup -->
                <template x-if="row.kind !== 'service'">
                  <div class="relative">
                    <input type="text"
                           :id="'line_item_input_'+idx"
                           autocomplete="off"
                           class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                           placeholder="Start typing item name or code…"
                           x-model="row.name"
                           :data-kf-target-id="'#line_item_'+idx"
                           :data-kf-target-unit="'#line_unit_'+idx"
                           :data-kf-target-price="'#line_price_'+idx"
                           :data-kf-target-description="'#line_desc_'+idx"
                           data-kf-lookup="items"
                           data-kf-debounce="160"
                           @change="onItemLookupChange($event, idx)"
                    />
                    <!-- noscript: old select fallback (only if JS fully off) -->
                    <noscript>
                      <select
                        :id="'line_item_select_'+idx"
                        class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
                        name="<?= 'lines[0][product_id]' /* fallback only for noscript */ ?>"
                      >
                        <option value="">Search &amp; select item…</option>
                        <?php foreach ($items as $it): ?>
                          <option
                            value="<?= (int)$it['id'] ?>"
                            data-name="<?= $h($it['name']) ?>"
                            data-code="<?= $h($it['code'] ?? '') ?>"
                            data-unit="<?= $h($it['unit'] ?? $it['uom_id'] ?? '') ?>"
                            data-price="<?= $h($it['sale_price'] ?? $it['unit_price'] ?? 0) ?>"
                            data-description="<?= $h($it['description'] ?? '') ?>"
                          >
                            <?= $h($it['name']) ?>
                            <?php if (!empty($it['code'])): ?>
                              (<?= $h($it['code']) ?>)
                            <?php endif; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </noscript>
                  </div>
                </template>

                <!-- Free-text for service lines -->
                <template x-if="row.kind === 'service'">
                  <input type="text"
                         :id="'line_service_'+idx"
                         :name="'lines['+idx+'][service_name]'"
                         x-model="row.name"
                         class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 mt-0.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                         placeholder="Service name…">
                </template>
              </td>

              <!-- DESCRIPTION -->
              <td class="px-3 py-2 align-top">
                <textarea
                  :id="'line_desc_'+idx"
                  :name="'lines['+idx+'][description]'"
                  x-model="row.description"
                  rows="2"
                  class="w-full text-[11px] border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 resize-y"
                  placeholder="Key features / specification…"></textarea>
              </td>

              <!-- QTY -->
              <td class="px-3 py-2 align-middle">
                <input type="number" min="0" step="0.01"
                       :name="'lines['+idx+'][qty]'"
                       x-model.number="row.qty"
                       @input="recalc(idx)"
                       class="w-full text-right text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
              </td>

              <!-- UNIT -->
              <td class="px-3 py-2 align-middle">
                <input type="text"
                       :id="'line_unit_'+idx"
                       :name="'lines['+idx+'][unit]'"
                       x-model="row.unit"
                       class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                       placeholder="pcs / hour / lot">
              </td>

              <!-- UNIT PRICE -->
              <td class="px-3 py-2 align-middle">
                <input type="number" min="0" step="0.01"
                       :id="'line_price_'+idx"
                       :name="'lines['+idx+'][unit_price]'"
                       x-model.number="row.unit_price"
                       @input="recalc(idx)"
                       class="w-full text-right text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
              </td>

              <!-- DISCOUNT % -->
              <td class="px-3 py-2 align-middle">
                <input type="number" min="0" max="100" step="0.01"
                       :name="'lines['+idx+'][discount_pct]'"
                       x-model.number="row.discount_pct"
                       @input="recalc(idx)"
                       class="w-full text-right text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
              </td>

              <!-- LINE TOTAL -->
              <td class="px-3 py-2 text-right align-middle">
                <input type="hidden"
                       :name="'lines['+idx+'][line_total]'"
                       :value="Number(row.total || 0).toFixed(2)">
                <input type="text"
                       readonly
                       class="w-full text-right text-xs font-semibold border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-2 py-1.5"
                       :value="formatMoney(row.total)">
              </td>

              <!-- TYPE PILL -->
              <td class="px-3 py-2 align-middle">
                <span class="inline-flex px-3 py-0.5 rounded-full border text-[11px]"
                      :class="row.kind==='service'
                        ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200'
                        : 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'">
                  <template x-if="row.kind==='service'">Service</template>
                  <template x-if="row.kind!=='service'">Item</template>
                </span>
              </td>
            </tr>
          </template>
          </tbody>
        </table>
      </div>

      <!-- TOTALS -->
      <div class="px-4 pt-4 border-t border-gray-100 dark:border-gray-800 mt-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="text-[11px] text-gray-500 max-w-md">
          <p><strong>Tip:</strong> Inventory businesses should make sure item lines are linked to real stock items so future orders can reserve stock. Service-only businesses can keep all lines as free-text services.</p>
        </div>
        <div class="space-y-1 text-sm w-full max-w-xs">
          <div class="flex justify-between gap-4">
            <span class="text-gray-600 dark:text-gray-300">Subtotal</span>
            <span x-text="formatMoney(subtotal)"></span>
          </div>
          <div class="flex justify-between gap-4 text-xs text-gray-500">
            <span>Discounts</span>
            <span x-text="'- '+formatMoney(discountTotal)"></span>
          </div>
          <div class="flex justify-between gap-2 items-center text-xs text-gray-600 pt-1 border-t border-gray-200 dark:border-gray-700 mt-1">
            <span>VAT (NBR) on net</span>
            <div class="flex items-center gap-1">
              <input type="number" min="0" max="100" step="0.01"
                     class="w-16 text-right border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-1.5 py-1 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                     x-model.number="vatPercent"
                     @input="recalcAll()">
              <span>%</span>
            </div>
            <span class="ml-auto" x-text="formatMoney(vatAmount)"></span>
          </div>
          <div class="flex justify-between gap-4 text-sm font-semibold border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
            <span>Total (BDT)</span>
            <span x-text="formatMoney(total)"></span>
          </div>
        </div>
      </div>
    </section>

    <!-- TERMS -->
    <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-2">
      <h2 class="text-sm font-semibold">Terms &amp; notes</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
            Payment terms
          </label>
          <textarea name="payment_terms" rows="3"
                    class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="e.g. 50% advance, balance within 15 days of delivery."></textarea>
        </div>
        <div class="space-y-1">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">
            Delivery terms / notes
          </label>
          <textarea name="delivery_terms" rows="3"
                    class="w-full text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="e.g. Delivery within 7 working days after PO / work order."></textarea>
        </div>
      </div>
    </section>
  </form>

  <!-- HOW TO USE -->
  <section class="border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-4 py-4 text-sm space-y-2">
    <h2 class="font-semibold text-gray-800 dark:text-gray-100 text-sm">
      How to use this page
    </h2>
    <ul class="list-disc pl-5 space-y-1 text-gray-700 dark:text-gray-300 text-[13px]">
      <li>Select a <strong>customer</strong> using the unified lookup or fallback list.</li>
      <li>Set <strong>quote date</strong>, <strong>valid until</strong>, and <strong>quote type</strong> (stock / service / mixed).</li>
      <li>Add <strong>item lines</strong> (linked to inventory) and/or <strong>service lines</strong> (free-text).</li>
      <li>After picking an item, <strong>unit, price and qty</strong> auto-fill; then adjust discounts and VAT% as needed.</li>
      <li>Fill in <strong>payment</strong> and <strong>delivery terms</strong> so your quote is ready for tenders and RFQs.</li>
      <li>Use <strong>Save draft</strong> or <strong>Save quote</strong>. Sending, printing and downloading will be done later from the quotes list.</li>
    </ul>
  </section>
</div>

<script>
function bizQuoteCreate(moduleBase) {
  const MODULE_BASE = String(moduleBase || '<?= $h($module_base) ?>');

  return {
    /* -----------------------------
     * STATE
     * --------------------------- */
    customerId: '',
    customerName: '',
    customerContact: '',
    customerReference: '',
    date: '<?= $h($today) ?>',
    validUntil: '<?= $h($validUntil) ?>',
    externalRef: '',
    type: 'mixed',   // mixed | stock | service

    rows: [
      {
        key: Date.now(),
        kind: 'item',        // item | service
        product_id: '',
        name: '',
        description: '',
        qty: 1,
        unit: 'pcs',
        unit_price: 0,
        discount_pct: 0,
        total: 0
      }
    ],

    subtotal: 0,
    discountTotal: 0,
    vatPercent: 0,
    vatAmount: 0,
    total: 0,

    /* -----------------------------
     * INIT
     * --------------------------- */
    init() {
      // Re-scan for Choices / KF.lookup etc. inside this component
      if (window.KF && typeof KF.rescan === 'function') {
        KF.rescan(this.$root);
      }
      // Ensure totals are consistent with default row
      this.recalcAll();
    },

    /* -----------------------------
     * ROW OPS
     * --------------------------- */
    addRow(kind) {
      this.rows.push({
        key: Date.now() + Math.random(),
        kind: kind || 'item',
        product_id: '',
        name: '',
        description: '',
        qty: 1,
        unit: (kind === 'service') ? 'hour' : 'pcs',
        unit_price: 0,
        discount_pct: 0,
        total: 0
      });

      this.recalcAll();

      this.$nextTick(() => {
        if (window.KF && typeof KF.rescan === 'function') {
          KF.rescan(this.$root);
        }
      });
    },

    removeRow(idx) {
      this.rows.splice(idx, 1);
      this.recalcAll();
    },

    /* -----------------------------
     * WHEN PRODUCT SELECT CHANGES (old select, kept for fallback)
     * --------------------------- */
    onItemChange(event, idx) {
      const select = event.target;
      const opt    = select.selectedOptions[0];
      if (!opt) return;

      const r = this.rows[idx];
      if (!r) return;

      r.product_id = Number(select.value || 0);
      r.name       = opt.dataset.name || opt.textContent.trim();
      r.unit       = opt.dataset.unit || r.unit || 'pcs';

      if (opt.dataset.price) {
        r.unit_price = Number(opt.dataset.price);
      }
      if (!r.description && opt.dataset.description) {
        r.description = opt.dataset.description;
      }

      // Sync DOM inputs so backend sees correct values
      const hid   = document.getElementById('line_item_' + idx);
      const unit  = document.getElementById('line_unit_' + idx);
      const price = document.getElementById('line_price_' + idx);
      const desc  = document.getElementById('line_desc_' + idx);

      if (hid)   hid.value   = r.product_id;
      if (unit)  unit.value  = r.unit;
      if (price) price.value = r.unit_price;
      if (desc && r.description) desc.value = r.description;

      this.recalc(idx);
    },

    /* -----------------------------
     * WHEN KF.lookup WRITES DOM TARGETS
     * --------------------------- */
    onItemLookupChange(event, idx) {
      const input = event.target;
      const r = this.rows[idx];
      if (!r) return;

      const hid   = document.getElementById('line_item_' + idx);
      const unit  = document.getElementById('line_unit_' + idx);
      const price = document.getElementById('line_price_' + idx);
      const desc  = document.getElementById('line_desc_' + idx);

      r.name       = input.value || r.name || '';
      r.product_id = hid ? Number(hid.value || 0) : (r.product_id || 0);
      r.unit       = unit ? (unit.value || r.unit) : r.unit;
      r.unit_price = price ? Number(price.value || 0) : (r.unit_price || 0);
      r.description = desc ? (desc.value || r.description) : r.description;

      this.recalc(idx);
    },

    /* -----------------------------
     * CALC
     * --------------------------- */
    recalc(idx) {
      const row = this.rows[idx];
      if (!row) return;

      const gross = (Number(row.qty) || 0) * (Number(row.unit_price) || 0);
      const disc  = gross * ((Number(row.discount_pct) || 0) / 100);
      row.total   = gross - disc;

      this.recalcAll();
    },

    recalcAll() {
      let sub     = 0;
      let discSum = 0;

      this.rows.forEach((r) => {
        const gross = (Number(r.qty) || 0) * (Number(r.unit_price) || 0);
        const disc  = gross * ((Number(r.discount_pct) || 0) / 100);
        sub     += gross;
        discSum += disc;
      });

      this.subtotal      = sub;
      this.discountTotal = discSum;

      const net = sub - discSum;
      const v   = Number(this.vatPercent) || 0;

      // VAT (NBR) on net
      this.vatAmount = (net > 0 && v > 0) ? (net * v / 100) : 0;
      this.total     = net + this.vatAmount;
    },

    formatMoney(v) {
      const n = Number(v || 0);
      return n.toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },

    /* -----------------------------
     * SAVE ACTIONS
     * --------------------------- */
    async saveDraft() {
      await this._submit('draft');
    },

    async saveQuote() {
      // Controller expects 'send' for non-draft
      await this._submit('send');
    },

    /* -----------------------------
     * SUBMIT CORE
     * --------------------------- */
    async _submit(mode) {
      try {
        const form = document.getElementById('quoteForm');
        if (!form) return;

        const base = MODULE_BASE.replace(/\/$/, '');
        const url  = base + '/quotes';

        // Ensure latest totals before building FormData
        this.recalcAll();

        const fd = new FormData(form);
        fd.append('_mode', mode);
        fd.append('quote_type', this.type);
        fd.append('vat_percent', String(this.vatPercent || 0));

        const res = await fetch(url, {
          method: 'POST',
          body: fd,
          credentials: 'include',
          headers: { 'Accept': 'application/json' }
        });

        if (!res.ok) {
          alert('Failed to save quote (HTTP ' + res.status + ').');
          return;
        }

        const js = await res.json().catch(() => ({}));

        if (js && js.redirect) {
          window.location.href = js.redirect;
        } else if (js && js.id) {
          window.location.href = base + '/quotes/' + js.id;
        } else {
          window.location.href = base + '/quotes';
        }
      } catch (e) {
        console.error(e);
        alert('Failed to save quote: Unexpected error.');
      }
    }
  };
}
</script>