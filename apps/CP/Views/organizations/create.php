<?php
/**
 * views/cp/organizations/create.php
 *
 * Expects (from controller):
 * - $csrf          string
 * - $error         string|null
 * - $old           array (sticky form values)
 * - $modules       array of ['id','name','module_key']  // DB-only, active modules
 * - $planDefaults  array (optional; kept for future)
 * - $openTrial     bool (optional UI hint)
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$old = is_array($old ?? null) ? $old : [];
$modules = is_array($modules ?? null) ? $modules : [];

// Normalize DB rows into "choices" for the UI
$choices = [];
foreach ($modules as $m) {
  $id  = isset($m['id']) ? (int)$m['id'] : null;
  $key = (string)($m['module_key'] ?? '');
  if ($id && $key !== '') {
    $choices[] = [
      'id'    => $id,
      'key'   => $key,
      'label' => (string)($m['name'] ?? ucfirst($key)),
      'from'  => 'db',
    ];
  }
}
// Sort by label
usort($choices, static fn($a,$b) => strcasecmp($a['label'], $b['label']));
?>
<style>
  .kf-input { background-color:#fff; }
  .dark .kf-input { background-color:#0f172a; border-color:#334155; color:#e5e7eb; }
  .kf-card { background-color:#fff; }
  .dark .kf-card { background-color:#111827; }
  .kf-border { border-color:#e5e7eb; }
  .dark .kf-border { border-color:#374151; }
  .kf-muted { color:#6b7280; }
  .dark .kf-muted { color:#9ca3af; }
  [x-cloak]{ display:none !important; }
  .btn { display:inline-flex; align-items:center; justify-content:center; gap:.5rem; font-weight:600; }
  .btn-brand { background:#16a34a; color:white; }
  .btn-brand:hover { background:#15803d; }
  .btn-ghost { background:transparent; border:1px solid var(--tw-prose-borders,#e5e7eb); }
</style>

<div
  x-data="orgForm()"
  x-init="init()"
  class="max-w-4xl mx-auto"
>
  <a href="/cp/organizations" class="text-sm kf-muted hover:underline">&larr; Back</a>
  <h1 class="text-2xl font-semibold mt-2 mb-1">Create Organization</h1>
  <p class="kf-muted mb-6">Fill the organization's basic information. You can manage settings later.</p>

  <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>

  <form method="post" action="/cp/organizations" class="space-y-6">
    <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">

    <!-- Organization Name -->
    <div>
      <label class="block text-sm font-medium mb-1">Organization Name</label>
      <input
        name="name"
        x-model="name"
        @input="maybeSlug()"
        value="<?= $h($old['name'] ?? '') ?>"
        required
        class="kf-input w-full rounded-lg border kf-border px-3 py-2"
      >
    </div>

    <!-- Slug -->
    <div>
      <label class="block text-sm font-medium mb-1">Slug (optional)</label>
      <input
        name="slug"
        x-model="slug"
        value="<?= $h($old['slug'] ?? '') ?>"
        placeholder="e.g. acme"
        class="kf-input w-full rounded-lg border kf-border px-3 py-2"
      >
      <div class="text-xs kf-muted mt-1">Leave blank to auto-generate from name.</div>
    </div>

    <!-- Plan / Status / Price -->
    <div class="grid sm:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Plan</label>
        <select
          name="plan"
          x-model="plan"
          @change="onPlanChanged()"
          class="kf-input w-full rounded-lg border kf-border px-3 py-2"
        >
          <?php foreach (['trial','starter','growth','enterprise'] as $p): ?>
            <option value="<?= $p ?>" <?= (($old['plan'] ?? 'starter')===$p)?'selected':'' ?>><?= ucfirst($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="status" class="kf-input w-full rounded-lg border kf-border px-3 py-2">
          <?php foreach (['active','trial','suspended','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= (($old['status'] ?? 'active')===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Monthly Price</label>
        <input
          name="monthly_price"
          type="number"
          step="0.01"
          value="<?= $h($old['monthly_price'] ?? '0.00') ?>"
          class="kf-input w-full rounded-lg border kf-border px-3 py-2"
        >
      </div>
    </div>

    <!-- Trial dates (inline, only when plan=trial) -->
    <div class="grid sm:grid-cols-2 gap-4" x-show="plan === 'trial'" x-cloak>
      <div>
        <label class="block text-sm font-medium mb-1">Trial Start</label>
        <input type="date" x-model="trial_start" class="kf-input w-full rounded-lg border kf-border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Trial End</label>
        <input type="date" x-model="trial_end" class="kf-input w-full rounded-lg border kf-border px-3 py-2">
      </div>
    </div>

    <!-- Owner info -->
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Owner Email</label>
        <input
          name="owner_email"
          type="email"
          value="<?= $h($old['owner_email'] ?? '') ?>"
          required
          class="kf-input w-full rounded-lg border kf-border px-3 py-2"
        >
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Owner Mobile (optional)</label>
        <input
          name="owner_mobile"
          value="<?= $h($old['owner_mobile'] ?? '') ?>"
          class="kf-input w-full rounded-lg border kf-border px-3 py-2"
        >
      </div>
    </div>

    <!-- Address -->
    <div>
      <label class="block text-sm font-medium mb-1">Company Address</label>
      <textarea
        name="company_address"
        rows="3"
        class="kf-input w-full rounded-lg border kf-border px-3 py-2"
      ><?= $h($old['company_address'] ?? '') ?></textarea>
    </div>

    <!-- Modules -->
    <div class="kf-card rounded-xl border kf-border p-4">
      <div class="flex items-center justify-between gap-2">
        <label class="block text-sm font-medium">Modules</label>
        <div class="flex items-center gap-2">
          <button type="button" class="text-xs px-2 py-1 rounded-lg border kf-border" @click="selectAll()">Select all</button>
          <button type="button" class="text-xs px-2 py-1 rounded-lg border kf-border" @click="clearAll()">Clear</button>
        </div>
      </div>

      <?php if (!$choices): ?>
        <div class="mt-3 rounded-lg border-dashed border kf-border p-3 text-sm kf-muted">
          No active modules found in <code>cp_modules</code>. Add modules or enable them first.
        </div>
      <?php else: ?>
        <div class="grid sm:grid-cols-2 gap-2 mt-3">
          <?php
          $oldSelected = array_map('strval', (array)($old['modules'] ?? []));
          foreach ($choices as $c):
            $value = (string)$c['id']; // We post numeric IDs only (DB authoritative)
            $checked = in_array($value, $oldSelected, true);
          ?>
            <label class="group flex items-center justify-between gap-3 border kf-border rounded-lg px-3 py-2 hover:bg-slate-50">
              <span class="flex items-center gap-2">
                <!-- IMPORTANT: independent selection (no :value binding) -->
                <input
                  type="checkbox"
                  name="modules[]"
                  value="<?= $h($value) ?>"
                  x-model="selected"
                  <?= $checked ? 'checked' : '' ?>
                >
                <span class="text-sm"><?= $h($c['label']) ?></span>
                <span class="text-xs kf-muted">(<?= $h($c['key']) ?>)</span>
              </span>
              <span class="text-[10px] px-2 py-0.5 rounded-full border kf-border kf-muted">DB</span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2 pt-1">
      <button class="btn btn-brand rounded-lg px-4 py-2">Create Organization</button>
      <a href="/cp/organizations" class="btn btn-ghost rounded-lg px-4 py-2">Cancel</a>
    </div>

    <!-- Hidden inputs for trial dates -->
    <input type="hidden" name="trial_start" :value="trial_start">
    <input type="hidden" name="trial_end"   :value="trial_end">
  </form>
</div>

<script>
function orgForm(){
  const CHOICES = <?= json_encode($choices, JSON_UNESCAPED_SLASHES) ?>; // [{id,key,label,from}]
  const OLD     = <?= json_encode(array_map('strval', (array)($old['modules'] ?? [])), JSON_UNESCAPED_SLASHES) ?>;

  const slugify = (s) =>
    (s || '').toLowerCase()
      .normalize('NFKD').replace(/[\u0300-\u036f]/g,'')
      .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');

  return {
    // core fields
    name: '<?= $h($old['name'] ?? '') ?>',
    slug: '<?= $h($old['slug'] ?? '') ?>',
    plan: '<?= $h($old['plan'] ?? 'starter') ?>',
    trial_start: '<?= $h($old['trial_start'] ?? '') ?>',
    trial_end:   '<?= $h($old['trial_end'] ?? '') ?>',

    // module selections (strings of IDs)
    selected: [...OLD],

    init(){
      // nothing fancy here; we keep the selection independent per checkbox
      // If you later want plan-based auto-includes, add them here carefully.
      <?php if (!empty($openTrial)): ?>
      // Small UX nicety from controller hint: open trial inputs if coming back
      this.plan = 'trial';
      <?php endif; ?>
    },

    maybeSlug(){
      if (!this.slug || this.slug.trim()===''){
        this.slug = slugify(this.name || '');
      }
    },
    onPlanChanged(){
      // You can auto-set default 14-day period here if becoming trial and empty:
      if (this.plan === 'trial' && (!this.trial_start || !this.trial_end)) {
        const now = new Date();
        const pad = n => String(n).padStart(2,'0');
        const toDateStr = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
        const start = new Date(now);
        const end = new Date(now); end.setDate(end.getDate()+14);
        this.trial_start = toDateStr(start);
        this.trial_end   = toDateStr(end);
      }
    },

    // UI helpers
    selectAll(){
      this.selected = CHOICES.map(c => String(c.id));
    },
    clearAll(){
      this.selected = [];
    },
  }
}
</script>