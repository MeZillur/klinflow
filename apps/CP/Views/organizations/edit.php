<?php
declare(strict_types=1);
/**
 * Vars expected (defensive):
 * - string $csrf
 * - array  $org
 * - ?string $error
 * - array  $modules  (each: id, name, module_key|slug)
 * - array  $selected OR array $selMods (selected module IDs)
 */
$brandColor = '#228B22';
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/** Normalize inputs safely */
$org       = is_array($org ?? null) ? $org : [];
$modules   = is_array($modules ?? null) ? $modules : [];
$selectedA = isset($selected) && is_array($selected) ? $selected : [];
$selModsA  = isset($selMods)  && is_array($selMods)  ? $selMods  : [];
$chosenIds = array_map('intval', array_values(array_unique(array_merge($selectedA, $selModsA))));
$chosenSet = array_fill_keys($chosenIds, true);

$orgId   = (int)($org['id'] ?? 0);
$orgPlan = (string)($org['plan'] ?? 'starter');
?>

<div class="max-w-4xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <a href="/cp/organizations" class="text-sm text-gray-600 hover:underline">&larr; Back</a>
    <h1 class="text-2xl font-semibold">Edit Organization</h1>
    <span></span>
  </div>

  <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>

  <form method="post" action="/cp/organizations/<?= $orgId ?>" class="space-y-6" id="orgEditForm">
    <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">

    <!-- Top row: name + slug -->
    <div class="grid md:grid-cols-2 gap-4">
      <label class="block">
        <span class="block text-sm font-medium mb-1">Organization Name</span>
        <input name="name" value="<?= $h($org['name'] ?? '') ?>" required class="w-full rounded-lg border border-gray-300 px-3 py-2">
      </label>
      <label class="block">
        <span class="block text-sm font-medium mb-1">Slug</span>
        <input name="slug" value="<?= $h($org['slug'] ?? '') ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2">
      </label>
    </div>

    <!-- Owner contacts -->
    <div class="grid md:grid-cols-2 gap-4">
      <label class="block">
        <span class="block text-sm font-medium mb-1">Owner Email</span>
        <input name="owner_email" value="<?= $h($org['owner_email'] ?? '') ?>" required type="email"
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
      </label>
      <label class="block">
        <span class="block text-sm font-medium mb-1">Owner Mobile</span>
        <input name="owner_mobile" value="<?= $h($org['owner_mobile'] ?? '') ?>"
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
      </label>
    </div>

    <!-- Address -->
    <label class="block">
      <span class="block text-sm font-medium mb-1">Organization Address</span>
      <textarea name="company_address" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2"><?= $h($org['company_address'] ?? '') ?></textarea>
    </label>

    <!-- Plan / Status / Price -->
    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Plan</label>
        <select name="plan" id="planSel" class="w-full rounded-lg border border-gray-300 px-3 py-2">
          <?php foreach (['trial','starter','growth','enterprise'] as $p): ?>
            <option value="<?= $p ?>" <?= ($orgPlan===$p?'selected':'') ?>><?= ucfirst($p) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="openTrialBtn"
                class="mt-2 inline-flex items-center gap-2 px-3 py-2 rounded-lg"
                style="background:<?= $brandColor ?>;color:#fff;<?= $orgPlan==='trial'?'':'display:none;' ?>">
          Set Trial Dates
        </button>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
          <?php foreach (['active','trial','suspended','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= (($org['status'] ?? '')===$s?'selected':'') ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Monthly Price</label>
        <input name="monthly_price" type="number" step="0.01" value="<?= $h((string)($org['monthly_price'] ?? '0.00')) ?>"
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
      </div>
    </div>

    <!-- Modules -->
    <div>
      <div class="text-sm font-medium mb-2">Modules</div>
      <?php if (empty($modules)): ?>
        <div class="text-gray-500 text-sm">No modules available.</div>
      <?php else: ?>
        <div class="grid md:grid-cols-2 gap-3">
          <?php foreach ($modules as $m):
              $mid = (int)($m['id'] ?? 0);
              $isChecked = isset($chosenSet[$mid]);
              $mname = (string)($m['name'] ?? ('Module #'.$mid));
              $mkey  = (string)($m['module_key'] ?? ($m['slug'] ?? ''));
          ?>
          <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" name="modules[]" value="<?= $mid ?>" <?= $isChecked?'checked':'' ?>>
            <div>
              <div class="font-medium"><?= $h($mname) ?></div>
              <?php if ($mkey !== ''): ?>
                <div class="text-xs text-gray-500">Key: <?= $h($mkey) ?></div>
              <?php endif; ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="pt-2 flex items-center gap-2">
      <button class="px-4 py-2 rounded-lg text-white" style="background:<?= $brandColor ?>">Save Changes</button>

      <?php if ($orgId > 0): ?>
        <form method="post" action="/cp/organizations/<?= $orgId ?>/delete" class="inline"
              onsubmit="return confirm('Delete this organization? This action cannot be undone.');">
          <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">
          <button type="submit" class="px-4 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50">
            Delete Organization
          </button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Trial drawer -->
    <div id="trialDrawer" style="display:none" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/40" onclick="KF_trialClose()"></div>
      <aside class="absolute top-0 right-0 w-full max-w-md h-full bg-white dark:bg-gray-900 shadow-xl p-6 overflow-auto">
        <h3 class="text-xl font-semibold mb-2">Trial Settings</h3>
        <p class="text-gray-500 mb-4">Pick a start and end date for the trial.</p>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">Trial Start</label>
            <input type="date" name="trial_start" value="<?= $h($org['trial_start'] ?? '') ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Trial End</label>
            <input type="date" name="trial_end" value="<?= $h($org['trial_end'] ?? '') ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2">
          </div>
        </div>
        <div class="mt-6 flex gap-2">
          <button type="button" class="btn btn-ghost" onclick="KF_trialClose()">Close</button>
          <button type="button" class="px-3 py-2 rounded-lg text-white" style="background:<?= $brandColor ?>" onclick="KF_trialClose()">Done</button>
        </div>
      </aside>
    </div>
  </form>
</div>

<script>
(function(){
  const planSel = document.getElementById('planSel');
  const trialBtn = document.getElementById('openTrialBtn');
  if (planSel && trialBtn) {
    const update = () => { trialBtn.style.display = (planSel.value === 'trial') ? '' : 'none'; };
    planSel.addEventListener('change', update);
    update();
    trialBtn.addEventListener('click', () => KF_trialOpen());
  }
})();
function KF_trialOpen(){ const d=document.getElementById('trialDrawer'); if(d) d.style.display='block'; }
function KF_trialClose(){ const d=document.getElementById('trialDrawer'); if(d) d.style.display='none'; }
</script>