<?php
declare(strict_types=1);

/**
 * New Category page
 * Expected (optional) vars:
 *  - $ctx['module_base']
 *  - $parents  : list of parent categories  (id, name)
 *  - $brands   : list of brands             (id, name)
 *  - $_SESSION['pos_old'], $_SESSION['pos_errors']
 */

/** helpers + safe defaults */
$h        = $h        ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base     = $base     ?? ($ctx['module_base'] ?? '/apps/pos');

$parents  = is_array($parents ?? null) ? $parents : [];
$brands   = is_array($brands  ?? null) ? $brands  : [];

$old      = $_SESSION['pos_old']    ?? [];
$errs     = $_SESSION['pos_errors'] ?? [];
unset($_SESSION['pos_old'], $_SESSION['pos_errors']);

/** compact lists for simple matching / selects */
$parentList = array_values(array_map(
  fn($r)=>['id'=>(int)($r['id']??0),'name'=>trim((string)($r['name']??''))],
  $parents
));

$brandList = array_values(array_map(
  fn($r)=>['id'=>(int)($r['id']??0),'name'=>trim((string)($r['name']??''))],
  $brands
));
?>
<style>
:root { --brand:#228B22; }
.kf-btn{
  background:var(--brand);color:#fff;border-radius:.65rem;
  padding:.65rem .95rem;font-weight:600;transition:.15s;
}
.kf-btn:hover{filter:brightness(.95)}
.kf-secondary{
  border:1px solid #d1d5db;border-radius:.65rem;
  padding:.55rem .85rem;background:transparent;
}
.dark .kf-secondary{border-color:#374151;color:#e5e7eb;background:transparent}
.kf-field{
  border:1px solid #e5e7eb;border-radius:.65rem;
  padding:.65rem .75rem;width:100%;background:#fff;transition:border-color .15s;
}
.kf-field:focus{
  outline:none;border-color:var(--brand);
  box-shadow:0 0 0 3px rgba(34,139,34,.18);
}
.dark .kf-field{background:#0f172a;border-color:#334155;color:#e5e7eb}
.kf-help{font-size:.78rem;color:#6b7280}
.dark .kf-help{color:#94a3b8}
.kf-err{color:#dc2626;font-size:.78rem;margin-top:.25rem}
.kf-chip{
  font-size:.75rem;border:1px solid rgba(34,139,34,.25);
  color:#065f46;background:rgba(34,139,34,.08);
  border-radius:999px;padding:.15rem .55rem;
}
.dark .kf-chip{color:#86efac;border-color:#166534;background:rgba(22,101,52,.24)}
.kf-card{
  background:#ffffff;border:1px solid #e5e7eb;
  border-radius:1rem;padding:1.25rem;
}
.dark .kf-card{background:#0b1020;border-color:#1f2937}
.kf-toolbar a{
  border:1px solid transparent;border-radius:.55rem;
  padding:.45rem .7rem;
}
.kf-toolbar a:hover{background:rgba(34,139,34,.08)}
.dark .kf-toolbar a:hover{background:rgba(22,101,52,.25)}
.kf-badge{
  font-size:.72rem;border-radius:.6rem;
  padding:.15rem .45rem;border:1px solid #cbd5e1;
  color:#334155;background:#f8fafc;
}
.dark .kf-badge{border-color:#334155;color:#cbd5e1;background:#0b1220}
.kf-disabled{background:#f3f4f6;color:#6b7280;cursor:not-allowed}
.dark .kf-disabled{background:#111827;color:#9ca3af}
</style>

<div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 lg:py-8"
     x-data="catForm(
       <?= json_encode($old['name']       ?? '', JSON_UNESCAPED_UNICODE) ?>,
       <?= json_encode($old['parent_id']  ?? '', JSON_UNESCAPED_UNICODE) ?>,
       <?= json_encode($old['parent_label'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
       <?= json_encode($old['code']       ?? '', JSON_UNESCAPED_UNICODE) ?>
     )">

  <!-- Page header -->
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-extrabold tracking-tight">New Category</h1>

    <!-- Top-right toolbar -->
    <nav class="kf-toolbar flex items-center gap-2">
      <a href="<?= $h($base) ?>/categories" title="Categories">Categories</a>
      <a href="<?= $h($base) ?>/products"   title="Products">Products</a>
      <a href="<?= $h($base) ?>/inventory"  title="Inventory">Inventory</a>
      <a href="<?= $h($base) ?>/sales"      title="Sales">Sales</a>
      <a href="<?= $h($base) ?>/reports"    title="Reports">Reports</a>
      <a href="<?= $h($base) ?>/settings"   title="Settings">Settings</a>
      <a href="<?= $h($base) ?>/categories" class="kf-secondary">Back</a>
      <button type="submit" form="kf-cat-form" class="kf-btn">Save</button>
    </nav>
  </div>

  <!-- Content grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Main form -->
    <div class="lg:col-span-2 kf-card space-y-5">
      <form id="kf-cat-form" method="post" action="<?= $h($base) ?>/categories" @submit="beforeSubmit" class="space-y-5">
        <!-- Name -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="font-semibold">Name <span class="text-red-600">*</span></label>
            <span class="kf-badge">Category</span>
          </div>
          <input name="name" x-model="name" @input="maybeGen()" class="kf-field" required>
          <?php if(isset($errs['name'])): ?>
            <div class="kf-err"><?= $h($errs['name']) ?></div>
          <?php endif; ?>
          <div class="kf-help mt-1">Example: Laptop, Grocery, Accessories</div>
        </div>

        <!-- Parent Category -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="font-semibold">Parent category</label>
            <span class="kf-badge" x-show="parentId">Parent selected</span>
          </div>

          <input type="hidden" name="parent_id" :value="parentId">
          <input type="hidden" name="parent_label" :value="parentLabel">

          <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input id="parentLookup" class="kf-field sm:flex-1" type="search"
                   placeholder="Type to search parent… (or leave empty)"
                   autocomplete="off" x-model="parentLabel">
            <button type="button" class="kf-secondary sm:w-auto" @click="clearParent()">Clear</button>
          </div>

          <div class="kf-help mt-1">
            Current:
            <template x-if="parentId">
              <span class="kf-chip">
                <span x-text="parentLabel || ('#'+parentId)"></span>
              </span>
            </template>
            <template x-if="!parentId">
              <span class="text-gray-500">None</span>
            </template>
          </div>

          <!-- No-JS fallback -->
          <noscript>
            <select name="parent_id" class="kf-field mt-2">
              <option value="">(None)</option>
              <?php foreach ($parentList as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= $h($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </noscript>
        </div>

        <!-- Brand (optional, on same page) -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="font-semibold">Brand (optional)</label>
            <span class="kf-badge">Brand</span>
          </div>
          <select name="brand_id" class="kf-field">
            <option value="">No specific brand</option>
            <?php
              $oldBrandId = isset($old['brand_id']) ? (int)$old['brand_id'] : 0;
              foreach ($brandList as $b):
                $bid   = (int)$b['id'];
                $sel   = $bid === $oldBrandId ? 'selected' : '';
            ?>
              <option value="<?= $bid ?>" <?= $sel ?>><?= $h($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="kf-help mt-1">
            Choose a default brand for this category (useful if most products under this
            category belong to a single brand). You can still override brand on each product.
          </div>
        </div>

        <!-- Auto Code -->
        <div>
          <label class="font-semibold block mb-1">Auto Code</label>
          <input type="text" readonly x-model="code" name="code"
                 class="kf-field kf-disabled" tabindex="-1">
          <div class="kf-help mt-1">
            Format: <b>AAA-PPP-####</b> — first 3 letters of category &amp; parent + sequence
            (starts at 0001).
          </div>
        </div>

        <!-- Active -->
        <div class="flex items-center gap-2 pt-1">
          <input type="checkbox" name="is_active" value="1"
                 class="w-4 h-4" <?= isset($old['is_active']) ? 'checked' : 'checked' ?>>
          <span class="text-sm">Active</span>
        </div>

        <!-- Bottom buttons -->
        <div class="flex flex-wrap gap-2 pt-2">
          <a href="<?= $h($base) ?>/categories" class="kf-secondary">Cancel</a>
          <button class="kf-btn" type="submit">Save</button>
        </div>
      </form>
    </div>

    <!-- Side info -->
    <aside class="kf-card space-y-4">
      <div>
        <div class="font-semibold mb-1">Tips</div>
        <p class="kf-help">
          Codes are generated automatically but your controller should final-check uniqueness.
          If you already use your own coding scheme, you can keep this field read-only and
          assign in the controller.
        </p>
      </div>
      <div>
        <div class="font-semibold mb-1">Sequence rule</div>
        <p class="kf-help">
          The sequence increments per <b>prefix</b> (CAT-PAR-####). Controller snippet should
          fetch <code>MAX(code)</code> with the same prefix and add 1.
        </p>
      </div>
      <div>
        <div class="font-semibold mb-1">Dark mode</div>
        <p class="kf-help">
          All panels respect the shell’s <code>dark</code> class and remain readable.
        </p>
      </div>
    </aside>
  </div>
</div>

<script>
function catForm(initialName, initialParentId, initialParentLabel, initialCode){
  return {
    name: initialName || '',
    parentId: initialParentId || '',
    parentLabel: initialParentLabel || '',
    code: initialCode || '',
    seq: 1,

    three(s){
      return (String(s||'').replace(/[^A-Za-z0-9]/g,'').toUpperCase().slice(0,3) || 'XXX');
    },
    gen(){
      const cat3 = this.three(this.name);
      const par3 = this.parentId ? this.three(this.parentLabel) : 'GEN';
      const num  = String(this.seq).padStart(4,'0');
      return `${cat3}-${par3}-${num}`;
    },
    maybeGen(){
      this.code = this.gen();
    },

    setParent(id,label){
      this.parentId    = id ? String(id) : '';
      this.parentLabel = label || '';
      this.maybeGen();
    },
    clearParent(){
      this.setParent('', '');
    },

    beforeSubmit(){
      // nothing extra yet; controller does validation / uniqueness
    },

    init(){
      const el  = document.getElementById('parentLookup');
      const self = this;

      // Try global KF.lookup first, fallback to local search
      function bindKF(){
        if (!window.KF || !KF.lookup || typeof KF.lookup.bind !== 'function') return false;
        KF.lookup.bind({
          el,
          entity: 'category',
          onPick: (r)=>{
            const id   = r && (r.id || r.value);
            const name = r && (r.label || r.name);
            if (id){ self.setParent(id, name || `#${id}`); }
          }
        });
        el.addEventListener('input', function(){ if (!this.value.trim()) self.clearParent(); });
        return true;
      }

      if (!bindKF()){
        const rows = <?= json_encode($parentList, JSON_UNESCAPED_UNICODE) ?>;
        el.addEventListener('input', function(){
          const q = this.value.trim().toLowerCase();
          if (!q){ self.clearParent(); return; }
          const byId   = rows.find(r => String(r.id) === q);
          const byName = rows.find(r => (r.name||'').toLowerCase() === q);
          const hit    = byId || byName;
          if (hit) self.setParent(hit.id, hit.name);
        });
      }

      if (!this.code){
        this.maybeGen();
      }
    }
  }
}
</script>