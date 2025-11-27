<?php
declare(strict_types=1);
/**
 * Edit Category — content-only view
 *
 * Expects from controller:
 *   $base               : module base (/t/{slug}/apps/pos)
 *   $cat (array)        : id, name, code, is_active, parent_id?, parent_name?
 *   $parents (optional) : array of [id, name] for local fallback search
 *
 * POST target:  POST <?= htmlspecialchars(($base ?? '/apps/pos'), ENT_QUOTES) ?>/categories/{id}
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base  = $base ?? '/apps/pos';
$cat   = is_array($cat ?? null) ? $cat : [];
$old   = $_SESSION['pos_old']    ?? [];
$errs  = $_SESSION['pos_errors'] ?? [];
unset($_SESSION['pos_old'], $_SESSION['pos_errors']);

$id         = (int)($cat['id'] ?? 0);
$name       = (string)($old['name']       ?? ($cat['name']       ?? ''));
$code       = (string)($cat['code']       ?? '');
$isActive   = (int)(array_key_exists('is_active',$old) ? !!$old['is_active'] : ($cat['is_active'] ?? 1));
$parentId   = (string)($old['parent_id']  ?? ($cat['parent_id']  ?? ''));
$parentName = (string)($old['parent_name']?? ($cat['parent_name']?? ''));

$parents    = is_array($parents ?? null) ? $parents : [];
$parentList = array_values(array_map(
  fn($r)=>['id'=>(int)($r['id']??0),'name'=>trim((string)($r['name']??''))],
  $parents
));
?>
<style>
:root { --brand:#228B22; }
.kf-btn{background:var(--brand);color:#fff;border-radius:.65rem;padding:.65rem .95rem;font-weight:600;transition:.15s;}
.kf-btn:hover{filter:brightness(.95)}
.kf-secondary{border:1px solid #d1d5db;border-radius:.65rem;padding:.55rem .85rem;background:transparent}
.dark .kf-secondary{border-color:#374151;color:#e5e7eb;background:transparent}
.kf-field{border:1px solid #e5e7eb;border-radius:.65rem;padding:.65rem .75rem;width:100%;background:#fff;transition:border-color .15s}
.kf-field:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(34,139,34,.18)}
.dark .kf-field{background:#0f172a;border-color:#334155;color:#e5e7eb}
.kf-read{background:#f3f4f6;color:#6b7280;cursor:not-allowed}
.dark .kf-read{background:#111827;color:#9ca3af}
.kf-help{font-size:.78rem;color:#6b7280}
.dark .kf-help{color:#94a3b8}
.kf-err{color:#dc2626;font-size:.78rem;margin-top:.25rem}
.kf-chip{font-size:.75rem;border:1px solid rgba(34,139,34,.25);color:#065f46;background:rgba(34,139,34,.08);border-radius:999px;padding:.15rem .55rem}
.dark .kf-chip{color:#86efac;border-color:#166534;background:rgba(22,101,52,.24)}
.kf-card{background:#ffffff;border:1px solid #e5e7eb;border-radius:1rem;padding:1.25rem}
.dark .kf-card{background:#0b1020;border-color:#1f2937}
.kf-toolbar a{border:1px solid transparent;border-radius:.55rem;padding:.45rem .7rem}
.kf-toolbar a:hover{background:rgba(34,139,34,.08)}
.dark .kf-toolbar a:hover{background:rgba(22,101,52,.25)}
.kf-badge{font-size:.72rem;border-radius:.6rem;padding:.15rem .45rem;border:1px solid #cbd5e1;color:#334155;background:#f8fafc}
.dark .kf-badge{border-color:#334155;color:#cbd5e1;background:#0b1220}
</style>

<div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 lg:py-8"
     x-data="catEditForm(<?= json_encode([
       'id'=>$id, 'name'=>$name, 'code'=>$code,
       'parentId'=>$parentId, 'parentName'=>$parentName
     ], JSON_UNESCAPED_UNICODE) ?>)">

  <!-- Header + toolbar -->
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-extrabold tracking-tight">Edit Category</h1>
    <nav class="kf-toolbar flex items-center gap-2">
      <a href="<?= $h($base) ?>/categories">Categories</a>
      <a href="<?= $h($base) ?>/products">Products</a>
      <a href="<?= $h($base) ?>/inventory">Inventory</a>
      <a href="<?= $h($base) ?>/sales">Sales</a>
      <a href="<?= $h($base) ?>/reports">Reports</a>
      <a href="<?= $h($base) ?>/settings">Settings</a>
      <a href="<?= $h($base) ?>/categories" class="kf-secondary">Back</a>
      <button type="submit" form="kf-cat-edit" class="kf-btn">Update</button>
    </nav>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Main form -->
    <div class="lg:col-span-2 kf-card space-y-5">
      <form id="kf-cat-edit" method="post" action="<?= $h($base) ?>/categories/<?= $id ?>" class="space-y-5">
        <!-- Name -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="font-semibold">Name <span class="text-red-600">*</span></label>
            <span class="kf-badge">#<?= $id ?></span>
          </div>
          <input name="name" x-model="name" class="kf-field" required>
          <?php if(isset($errs['name'])): ?>
            <div class="kf-err"><?= $h($errs['name']) ?></div>
          <?php endif; ?>
          <div class="kf-help mt-1">Example: Communication Gadgets, Grocery, Accessories</div>
        </div>

        <!-- Parent (lookup) -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="font-semibold">Parent category</label>
            <span class="kf-badge" x-show="parentId">Selected</span>
          </div>

          <input type="hidden" name="parent_id" :value="parentId">

          <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input id="parentLookup" class="kf-field sm:flex-1" type="search"
                   placeholder="Type to search parent… (or leave empty)"
                   autocomplete="off" x-model="parentLabel">
            <button type="button" class="kf-secondary sm:w-auto" @click="clearParent()">Clear</button>
          </div>

          <div class="mt-1 kf-help">
            Current:
            <template x-if="parentId">
              <span class="kf-chip">#<span x-text="parentId"></span> <span x-text="parentLabel"></span></span>
            </template>
            <template x-if="!parentId"><span class="text-gray-500">None</span></template>
          </div>

          <noscript>
            <select name="parent_id" class="kf-field mt-2">
              <option value="">(None)</option>
              <?php foreach ($parentList as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (string)$p['id']===$parentId?'selected':'' ?>>
                  <?= $h($p['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </noscript>
        </div>

        <!-- Code (read-only) -->
        <div>
          <label class="font-semibold block mb-1">Code</label>
          <input type="text" class="kf-field kf-read" value="<?= $h($code) ?>" readonly tabindex="-1">
          <div class="kf-help mt-1">
            Codes are immutable here. If you must change codes, handle it via data migration to maintain references.
          </div>
        </div>

        <!-- Active -->
        <div class="flex items-center gap-2 pt-1">
          <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked':'' ?> class="w-4 h-4">
          <span class="text-sm">Active</span>
        </div>

        <!-- Bottom actions -->
        <div class="flex flex-wrap gap-2 pt-2">
          <a href="<?= $h($base) ?>/categories" class="kf-secondary">Cancel</a>
          <button class="kf-btn" type="submit">Update</button>
        </div>
      </form>
    </div>

    <!-- Side info / tips -->
    <aside class="kf-card space-y-4">
      <div>
        <div class="font-semibold mb-1">What can I edit?</div>
        <p class="kf-help">Change the name, parent, and status. The code is shown for reference and stays read-only to protect links and reports.</p>
      </div>
      <div>
        <div class="font-semibold mb-1">Parent behavior</div>
        <p class="kf-help">Moving a category under a new parent affects where products appear in grouped listings and tiles. It won’t touch product stock or sales history.</p>
      </div>
      <div>
        <div class="font-semibold mb-1">Dark mode</div>
        <p class="kf-help">This screen adapts to the shell’s theme for comfortable reading.</p>
      </div>
    </aside>
  </div>
</div>

<script>
function catEditForm(init){
  return {
    name: init.name || '',
    parentId: String(init.parentId || ''),
    parentLabel: init.parentName || '',
    init(){
      const el = document.getElementById('parentLookup');
      const self = this;

      function bindKF(){
        if (!window.KF || !KF.lookup || typeof KF.lookup.bind!=='function') return false;
        KF.lookup.bind({
          el,
          entity: 'category',
          onPick: (r)=>{
            const id = r && (r.id || r.value);
            const name = r && (r.label || r.name);
            if (id){ self.parentId = String(id); self.parentLabel = name || `#${id}`; }
          }
        });
        el.addEventListener('input', function(){ if (!this.value.trim()) { self.parentId=''; self.parentLabel=''; } });
        return true;
      }

      if (!bindKF()){
        // Local fallback: simple exact match by id or name
        const rows = <?= json_encode($parentList, JSON_UNESCAPED_UNICODE) ?>;
        el.addEventListener('input', function(){
          const q = this.value.trim().toLowerCase();
          if (!q){ self.parentId=''; self.parentLabel=''; return; }
          const byId = rows.find(r => String(r.id)===q);
          const byName = rows.find(r => (r.name||'').toLowerCase() === q);
          const m = byId || byName;
          if (m){ self.parentId=String(m.id); self.parentLabel=m.name; }
        });
      }
    },
    clearParent(){ this.parentId=''; this.parentLabel=''; }
  }
}
</script>