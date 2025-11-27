<?php
declare(strict_types=1);
/** @var array $accounts @var array $coa @var array $bankGlChoices @var string $today @var string $module_base */

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$flashErrors  = $_SESSION['flash_errors']  ?? [];
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_errors'], $_SESSION['flash_success']);

$val = function(string $k, $def='') use ($old) { return array_key_exists($k,$old) ? $old[$k] : $def; };
?>
<div class="space-y-6">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <h1 class="text-xl font-semibold">Record Expense</h1>
    <a href="<?= $h(($module_base ?? '').'/expenses') ?>" class="px-3 py-2 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">Back to list</a>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="rounded-lg p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm"><?= $h($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if ($flashErrors): ?>
    <div class="rounded-lg p-3 bg-rose-50 border border-rose-200 text-rose-800 text-sm">
      <div class="font-medium mb-1">Please fix the following:</div>
      <ul class="list-disc ml-5">
        <?php foreach ($flashErrors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (empty($accounts)): ?>
    <div class="rounded-lg p-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm">
      No bank accounts found. Please <a class="underline" href="<?= $h(($module_base ?? '').'/bank-accounts/create') ?>">create a bank account</a> first.
    </div>
  <?php endif; ?>

  <form method="post" action="<?= $h(($module_base ?? '').'/expenses') ?>" class="space-y-6">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <label class="text-sm">
        <div class="mb-1 text-slate-600">Date</div>
        <input type="date" name="trans_date" value="<?= $h($val('trans_date', $today ?? date('Y-m-d'))) ?>"
               class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900" required>
      </label>
      <label class="text-sm">
        <div class="mb-1 text-slate-600">Amount</div>
        <input type="number" step="0.01" min="0.01" name="amount" value="<?= $h($val('amount')) ?>"
               class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900" required>
      </label>
      <label class="text-sm">
        <div class="mb-1 text-slate-600">Reference No (auto if empty)</div>
        <input type="text" name="ref_no" value="<?= $h($val('ref_no')) ?>"
               class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900" placeholder="EXP-2025-00001">
      </label>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <label class="text-sm">
        <div class="mb-1 text-slate-600">Bank Account</div>
        <select name="bank_account_id" id="bankSel"
                class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900" required>
          <option value="">— Select —</option>
          <?php foreach ($accounts as $a): ?>
            <?php
              $id    = (int)$a['id'];
              $name  = (string)($a['account_name'] ?? 'Account');
              $no    = (string)($a['account_no'] ?? '');
              $gl    = (int)($a['gl_account_id'] ?? 0);
              $label = trim($name.($no ? ' — '.$no : ''));
              $sel   = ((int)$val('bank_account_id') === $id) ? 'selected' : '';
            ?>
            <option value="<?= $id ?>" data-gl="<?= $gl ?>" <?= $sel ?>><?= $h($label) ?><?= $gl ? '' : ' *' ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-slate-500 mt-1">* means the bank is not linked to a GL yet; pick a GL below to link automatically.</p>
      </label>

      <label class="text-sm">
        <div class="mb-1 text-slate-600">Expense Category (COA)</div>
        <select name="category_id" id="catSel" class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900" required>
          <option value="">— Select —</option>
          <?php foreach ($coa as $c): ?>
            <?php
              $cid = (int)$c['id'];
              $txt = trim(($c['code'] ?? '').' — '.($c['name'] ?? '').' ('.($c['type'] ?? '').')');
            ?>
            <option value="<?= $cid ?>" <?= ((int)$val('category_id')===$cid)?'selected':''; ?>><?= $h($txt) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <!-- GL LINK helper (only if chosen bank has no gl_account_id) -->
    <div id="fixGlWrap" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
      <label class="text-sm">
        <div class="mb-1 text-slate-600">Link GL Account to selected Bank</div>
        <select name="fix_bank_gl_id" id="fixBankGl"
                class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900">
          <option value="">— Choose GL account —</option>
          <?php foreach (($bankGlChoices ?? []) as $g): ?>
            <?php
              $gid = (int)$g['id'];
              $lbl = trim(($g['code'] ?? '').' — '.($g['name'] ?? '').' ('.($g['type'] ?? '').')');
            ?>
            <option value="<?= $gid ?>" <?= ((int)$val('fix_bank_gl_id')===$gid)?'selected':''; ?>><?= $h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($bankGlChoices)): ?>
          <p class="text-xs text-amber-600 mt-2">
            No cash/bank GL accounts found. Create an <em>Asset / Cash / Bank</em> account in your COA and return here.
          </p>
        <?php else: ?>
          <p class="text-xs text-slate-500 mt-1">We’ll save this link and continue. You only do this once per bank.</p>
        <?php endif; ?>
      </label>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <label class="text-sm">
        <div class="mb-1 text-slate-600">Payee (optional)</div>
        <input type="text" name="payee" value="<?= $h($val('payee')) ?>"
               class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900" placeholder="Vendor / counterparty">
      </label>
      <label class="text-sm">
        <div class="mb-1 text-slate-600">Memo (optional)</div>
        <input type="text" name="note" value="<?= $h($val('note')) ?>"
               class="w-full rounded-lg border px-3 py-2 bg-white dark:bg-gray-900" placeholder="Short description">
      </label>
    </div>

    <div class="flex justify-end gap-2">
      <a href="<?= $h(($module_base ?? '').'/expenses') ?>" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">Cancel</a>
      <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save</button>
    </div>
  </form>
</div>

<script>
(function(){
  const bankSel   = document.getElementById('bankSel');
  const fixGlWrap = document.getElementById('fixGlWrap');
  const fixBankGl = document.getElementById('fixBankGl');

  function selectedBankGL() {
    const opt = bankSel.options[bankSel.selectedIndex];
    return opt ? (parseInt(opt.getAttribute('data-gl') || '0', 10) || 0) : 0;
  }

  function syncFixer(){
    const gl = selectedBankGL();

    // Toggle visibility
    const show = gl <= 0;
    fixGlWrap.classList.toggle('hidden', !show);

    // Make the GL select required only when visible
    if (fixBankGl) {
      fixBankGl.required = show;
      // If there’s exactly one candidate, autopick it
      if (show && fixBankGl.options.length === 2 && !fixBankGl.value) {
        fixBankGl.selectedIndex = 1;
      }
    }
  }

  bankSel.addEventListener('change', syncFixer);

  // Initial state
  syncFixer();
})();
</script>