<?php
declare(strict_types=1);

/**
 * View: Auto Purchase Orders (Index)
 * - Slug-safe module_base
 * - Proper CSRF realm ('tenant')
 * - Endpoints with safe defaults
 * - Uses shell once (via $slot) if present; otherwise renders raw
 */

use Shared\Csrf;

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$title = $title ?? 'Auto Purchase Orders';

/* ---------- module_base (slug-safe) ---------- */
$base = rtrim((string)($module_base ?? ''), '/');
if ($base === '') {
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
  if ($slug === '' && !empty($_SERVER['REQUEST_URI']) &&
      preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
    $slug = $m[1];
  }
  $base = $slug !== '' ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

/* ---------- CSRF ---------- */
$csrf = class_exists(Csrf::class) ? Csrf::token('tenant') : (function(){
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  return (string)($_SESSION['_csrf'] ?? $_SESSION['csrf'] ?? $_SESSION['XSRF-TOKEN'] ?? '');
})();

/* ---------- endpoints ---------- */
$eps = is_array($endpoints ?? null) ? $endpoints : [];
$eps += [
  'runs'            => $base.'/auto-po/runs',
  'preview'         => $base.'/auto-po/run',
  'saveRun'         => $base.'/auto-po/run',
  'lookupSuppliers' => $base.'/api/lookup/suppliers',
];

/* ---------- optional sidenav path for shell ---------- */
$moduleSidenav = $moduleSidenav ?? null;
if (!$moduleSidenav) {
  $root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
  foreach ([
    $root.'/modules/DMS/Views/shared/sidenav.php',
    $root.'/modules/DMS/Views/shared/partials/sidenav.php',
    $root.'/modules/dms/Views/shared/sidenav.php',
    $root.'/modules/dms/Views/shared/partials/sidenav.php',
  ] as $p) { if (is_file($p)) { $moduleSidenav = $p; break; } }
}

/* ---------- begin content slot ---------- */
ob_start();
?>
<style>
  :root{ --brand:#228B22; }
  .ap-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px}
  .ap-btn{appearance:none;border:1px solid #d1d5db;border-radius:12px;padding:10px 14px;background:#fff;cursor:pointer;font-weight:600}
  .ap-btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
  .ap-input{height:40px;border:1px solid #d1d5db;border-radius:12px;padding:0 12px;background:#fff;width:100%}
  .ap-help{color:#64748b;font-size:12px}
  .ap-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width: 720px){ .ap-grid{grid-template-columns:1fr} }
</style>

<div class="ap-card" role="region" aria-labelledby="apo-title">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:8px;flex-wrap:wrap">
    <div>
      <h1 id="apo-title" style="margin:0;font-weight:700">Auto Purchase Orders</h1>
      <div class="ap-help">Preview suggestions or save a snapshot to review/commit later.</div>
    </div>
    <div style="display:flex;gap:8px">
      <a class="ap-btn" href="<?= $h($eps['runs']) ?>">View saved runs</a>
      <a class="ap-btn" target="_blank" rel="noopener" href="<?= $h($eps['preview'].'?pretty=1') ?>">Preview / JSON</a>
    </div>
  </div>

  <form id="apoForm" method="post" action="<?= $h($eps['saveRun']) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

    <div class="ap-grid" style="margin-top:8px;margin-bottom:8px">
      <div>
        <label class="ap-help" for="pref_supp">Prefer supplier (optional)</label>
        <div style="display:flex;gap:8px">
          <input id="pref_supp_name" class="ap-input"
                 placeholder="Type to search supplier…"
                 data-kf-lookup="suppliers"
                 data-kf-endpoint="<?= $h($eps['lookupSuppliers']) ?>"
                 data-kf-target-id="#pref_supp"
                 data-kf-target-name="#pref_supp_name">
          <input type="hidden" name="prefer_supplier_id" id="pref_supp" value="">
          <button type="button" class="ap-btn"
                  onclick="document.getElementById('pref_supp').value='';document.getElementById('pref_supp_name').value='';">
            Clear
          </button>
        </div>
        <div class="ap-help" style="margin-top:6px">
          Used to bias vendor grouping / pricing; items aren’t strictly limited unless your backend enforces it.
        </div>
      </div>

      <div>
        <div class="ap-grid">
          <label>
            <span class="ap-help">Max items</span>
            <input class="ap-input" type="number" name="limit" value="100" min="1" max="500">
          </label>
          <label>
            <span class="ap-help">Min shortage</span>
            <input class="ap-input" type="number" name="min_shortage" value="1" min="0">
          </label>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
      <!-- IMPORTANT: name="save" ensures controller enters snapshot branch -->
      <button class="ap-btn ap-btn--primary" type="submit" name="save" value="1">
        Save run snapshot
      </button>
      <a class="ap-btn" target="_blank" rel="noopener"
         href="<?= $h($eps['preview'].'?pretty=1&debug=1') ?>">Preview JSON with filters</a>
    </div>
  </form>

  <div style="margin-top:16px">
    <div style="font-weight:600;margin-bottom:6px">What happens next?</div>
    <ol class="ap-help" style="margin:0;padding-left:18px">
      <li><b>Save run snapshot</b> writes one row to <code>dms_auto_po_runs</code> and many to <code>dms_auto_po_items</code>.</li>
      <li>Open the run from <i>View saved runs</i>, review items, then <b>commit</b> to create POs grouped by supplier.</li>
    </ol>
  </div>
</div>

<script>
/* KF lookup binder: uses global KF if present; else a tiny fetch fallback */
(function(){
  const sel = '[data-kf-lookup]';
  function bind(node){
    node = node || document;
    if (window.KF && typeof KF.rescan === 'function') { KF.rescan(node); return; }
    node.querySelectorAll(sel).forEach(inp=>{
      if (inp.__kfBound) return; inp.__kfBound = true;
      const ep = inp.getAttribute('data-kf-endpoint');
      const idSel = inp.getAttribute('data-kf-target-id');
      const nmSel = inp.getAttribute('data-kf-target-name');
      if (!ep) return;
      let last = ''; let timer = 0;
      inp.addEventListener('input', function(){
        const q = this.value.trim();
        if (q.length < 2 || q === last) return; last = q;
        clearTimeout(timer);
        timer = setTimeout(async ()=>{
          try{
            const r = await fetch(ep+'?q='+encodeURIComponent(q));
            const js = await r.json();
            const it = (js.items||[])[0]; // first match fallback
            if (it){
              if (idSel) document.querySelector(idSel).value = it.id || '';
              if (nmSel) document.querySelector(nmSel).value = it.name || it.label || '';
            }
          }catch(_){}
        }, 150);
      });
    });
  }
  bind();
})();
</script>
<?php
$slot = ob_get_clean();

/* ---------- render via shell if available, else raw ---------- */
$root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
$shell = null;
foreach ([
  $root.'/modules/DMS/Views/shared/layouts/shell.php',
  $root.'/modules/dms/Views/shared/layouts/shell.php',
] as $p) { if (is_file($p)) { $shell = $p; break; } }

if ($shell) {
  $module_base = $base; // expose to shell
  require $shell;
} else {
  echo $slot;
}