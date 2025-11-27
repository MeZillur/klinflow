<?php
declare(strict_types=1);
/** @var array $branches @var string $base */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-4xl mx-auto py-4">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-bold tracking-tight">New Stock Transfer</h1>
      <p class="text-sm text-gray-500">
        Move items from one branch to another. Inventory is adjusted automatically.
      </p>
    </div>
    <a href="<?= $h($base) ?>/stock-transfers"
       class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to list</a>
  </div>

  <form method="post" action="<?= $h($base) ?>/stock-transfers" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-semibold mb-1">From branch</label>
        <select name="from_branch_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
          <option value="">-- Select --</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>">
              <?= $h($b['name'] ?? ('Branch #'.$b['id'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">To branch</label>
        <select name="to_branch_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
          <option value="">-- Select --</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>">
              <?= $h($b['name'] ?? ('Branch #'.$b['id'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-semibold mb-1">Transfer date</label>
        <input type="date" name="transfer_date"
               value="<?= $h(date('Y-m-d')) ?>"
               class="w-full border border-gray-300 rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Reference</label>
        <input type="text" name="reference" class="w-full border border-gray-300 rounded-lg px-3 py-2"
               placeholder="Optional ref / slip no.">
      </div>
      <div>
        <label class="block text-sm font-semibold mb-1">Notes</label>
        <input type="text" name="notes" class="w-full border border-gray-300 rounded-lg px-3 py-2"
               placeholder="Optional notes">
      </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-900 text-gray-100">
          <tr>
            <th class="px-3 py-2 text-left w-1/2">Product ID</th>
            <th class="px-3 py-2 text-left">Qty</th>
            <th class="px-3 py-2 text-right">
              <button type="button" id="addRowBtn"
                      class="inline-flex items-center px-2 py-1 text-xs border border-gray-500 rounded-lg">
                + Add row
              </button>
            </th>
          </tr>
        </thead>
        <tbody id="rowsBody">
          <?php for ($i = 0; $i < 3; $i++): ?>
          <tr class="border-t border-gray-100">
            <td class="px-3 py-2">
              <input type="number" name="product_id[]"
                     class="w-full border border-gray-300 rounded-lg px-2 py-1"
                     placeholder="Product ID">
            </td>
            <td class="px-3 py-2">
              <input type="number" step="0.01" min="0" name="qty[]"
                     class="w-full border border-gray-300 rounded-lg px-2 py-1"
                     placeholder="Qty">
            </td>
            <td class="px-3 py-2 text-right">
              <button type="button"
                      class="removeRow text-xs text-red-600 hover:underline">
                Remove
              </button>
            </td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
      <p class="px-3 py-2 text-xs text-gray-500">
        For now use Product ID + quantity. Later we can upgrade this table to use the same product
        search as Sales Register.
      </p>
    </div>

    <div class="flex justify-end gap-3">
      <a href="<?= $h($base) ?>/stock-transfers"
         class="px-4 py-2 rounded-lg bg-red-100 text-red-700 text-sm font-semibold">
        Cancel
      </a>
      <button type="submit"
              class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold">
        Save Transfer
      </button>
    </div>
  </form>
</div>

<script>
(function() {
  const body = document.getElementById('rowsBody');
  const addBtn = document.getElementById('addRowBtn');

  function addRow() {
    const tr = document.createElement('tr');
    tr.className = 'border-t border-gray-100';
    tr.innerHTML = `
      <td class="px-3 py-2">
        <input type="number" name="product_id[]"
               class="w-full border border-gray-300 rounded-lg px-2 py-1"
               placeholder="Product ID">
      </td>
      <td class="px-3 py-2">
        <input type="number" step="0.01" min="0" name="qty[]"
               class="w-full border border-gray-300 rounded-lg px-2 py-1"
               placeholder="Qty">
      </td>
      <td class="px-3 py-2 text-right">
        <button type="button"
                class="removeRow text-xs text-red-600 hover:underline">
          Remove
        </button>
      </td>
    `;
    body.appendChild(tr);
  }

  body.addEventListener('click', function(e) {
    const btn = e.target.closest('.removeRow');
    if (!btn) return;
    const tr = btn.closest('tr');
    if (tr && body.children.length > 1) {
      tr.remove();
    }
  });

  addBtn.addEventListener('click', function() {
    addRow();
  });
})();
</script>