<?php
/** @var array $accounts */
/** @var string $today */
$base = $module_base ?? ($org['module_base'] ?? '/apps/dms');
$old  = $_SESSION['form_old']     ?? [];
$errs = $_SESSION['flash_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['flash_errors']);
?>
<div class="container mx-auto max-w-5xl px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Post Manual Journal</h1>
    <a href="<?= htmlspecialchars($base) ?>/accounts/journals"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border">
      <i class="fa-solid fa-list"></i> Journals
    </a>
  </div>

  <?php if ($errs): ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="list-disc pl-5 m-0">
        <?php foreach ($errs as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($base) ?>/accounts/journals" id="jForm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-sm mb-1">Date</label>
        <input type="date" name="jdate" value="<?= htmlspecialchars($old['jdate'] ?? $today) ?>"
               class="w-full rounded-lg border px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm mb-1">Type</label>
        <select name="jtype" class="w-full rounded-lg border px-3 py-2">
          <?php foreach (['GENERAL','PAYMENT','RECEIPT','ADJUSTMENT','OPENING'] as $t): ?>
            <option value="<?= $t ?>" <?= (($old['jtype'] ?? 'GENERAL')===$t)?'selected':''; ?>>
              <?= $t ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm mb-1">Memo</label>
        <input type="text" name="memo" value="<?= htmlspecialchars($old['memo'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="Optional memo">
      </div>
    </div>

    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs mb-1 text-gray-500">Ref Table (optional)</label>
        <input type="text" name="ref_table" value="<?= htmlspecialchars($old['ref_table'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="e.g. sales">
      </div>
      <div>
        <label class="block text-xs mb-1 text-gray-500">Ref ID (optional)</label>
        <input type="text" name="ref_id" value="<?= htmlspecialchars($old['ref_id'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="e.g. 123">
      </div>
    </div>

    <div class="rounded-xl border overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-3 py-2 w-[42%]">Account</th>
            <th class="text-left px-3 py-2">Memo</th>
            <th class="text-right px-3 py-2 w-[14%]">Debit</th>
            <th class="text-right px-3 py-2 w-[14%]">Credit</th>
            <th class="px-2 py-2 w-[6%]"></th>
          </tr>
        </thead>
        <tbody id="lines">
          <?php
          $rows = max(2, count($old['line_account_id'] ?? []));
          for ($i=0; $i<$rows; $i++):
            $oa = (int)($old['line_account_id'][$i] ?? 0);
            $om = (string)($old['line_memo'][$i]       ?? '');
            $od = (string)($old['line_debit'][$i]      ?? '');
            $oc = (string)($old['line_credit'][$i]     ?? '');
          ?>
          <tr class="border-t">
            <td class="px-3 py-2">
              <select name="line_account_id[]" class="w-full rounded-lg border px-2 py-1">
                <option value="">— select —</option>
                <?php foreach ($accounts as $a): ?>
                  <option value="<?= (int)$a['id'] ?>" <?= $oa===(int)$a['id']?'selected':''; ?>>
                    <?= htmlspecialchars($a['code'].' — '.$a['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="px-3 py-2">
              <input type="text" name="line_memo[]" value="<?= htmlspecialchars($om) ?>"
                     class="w-full rounded-lg border px-2 py-1" placeholder="optional">
            </td>
            <td class="px-3 py-2 text-right">
              <input type="number" step="0.01" name="line_debit[]" value="<?= htmlspecialchars($od) ?>"
                     class="w-full rounded-lg border px-2 py-1 text-right dr" min="0">
            </td>
            <td class="px-3 py-2 text-right">
              <input type="number" step="0.01" name="line_credit[]" value="<?= htmlspecialchars($oc) ?>"
                     class="w-full rounded-lg border px-2 py-1 text-right cr" min="0">
            </td>
            <td class="px-2 py-2 text-center">
              <button type="button" class="remove-row px-2 py-1 rounded-lg border">
                <i class="fa-solid fa-trash"></i>
              </button>
            </td>
          </tr>
          <?php endfor; ?>
        </tbody>
        <tfoot>
          <tr class="border-t bg-gray-50">
            <td class="px-3 py-2" colspan="2">
              <button type="button" id="addRow" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg"
                style="background:#2563eb;color:#fff;">
                <i class="fa-solid fa-plus"></i> Add line
              </button>
            </td>
            <td class="px-3 py-2 text-right font-medium">
              <span id="tDr">0.00</span>
            </td>
            <td class="px-3 py-2 text-right font-medium">
              <span id="tCr">0.00</span>
            </td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="mt-6 flex items-center gap-3">
      <a href="<?= htmlspecialchars($base) ?>/accounts/journals"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border">
        <i class="fa-solid fa-xmark"></i> Cancel
      </a>
      <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg"
              style="background:#2563eb;color:#fff;">
        <i class="fa-solid fa-floppy-disk"></i> Save Journal
      </button>
    </div>
  </form>
</div>

<script>
(function(){
  const lines = document.getElementById('lines');
  const addRow = document.getElementById('addRow');
  const fmt = n => (Math.round(n*100)/100).toFixed(2);

  function recalc(){
    let dr=0, cr=0;
    document.querySelectorAll('#lines .dr').forEach(i=>{ dr += parseFloat(i.value||0); });
    document.querySelectorAll('#lines .cr').forEach(i=>{ cr += parseFloat(i.value||0); });
    document.getElementById('tDr').textContent = fmt(dr);
    document.getElementById('tCr').textContent = fmt(cr);
  }
  document.addEventListener('input', e=>{
    if (e.target.matches('.dr,.cr')) recalc();
  });
  lines.addEventListener('click', e=>{
    if (e.target.closest('.remove-row')) {
      const tr = e.target.closest('tr');
      if (document.querySelectorAll('#lines tr').length > 1) tr.remove();
      recalc();
    }
  });
  addRow?.addEventListener('click', ()=>{
    const last = lines.querySelector('tr:last-child');
    const clone = last.cloneNode(true);
    clone.querySelectorAll('input').forEach(i=>{ i.value=''; });
    clone.querySelector('select').selectedIndex = 0;
    lines.appendChild(clone);
  });
  recalc();
})();
</script>