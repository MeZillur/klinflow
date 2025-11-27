<?php
declare(strict_types=1);
/*
==============================================================================
KlinFlow DMS Sidenav (module-scoped, refined)
------------------------------------------------------------------------------
Inputs (injected by shell / controller):
- string $module_base  (e.g. "/t/{slug}/apps/dms")
- array  $org          (expects ['slug'=>...])
No sessions, no DB. Pure view.
All URLs in this file are module-local and start with $module_base.
ASCII-only comments.
==============================================================================
*/

/* ────────────────────────────────────────────────────────────────────
 * SEGMENT 1: Helpers and current context
 * ────────────────────────────────────────────────────────────────── */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/dms'), '/');
$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$starts = static function (string $hay, string $needle): bool {
  return $needle !== '' && strncmp($hay, $needle, strlen($needle)) === 0;
};
$active = function(string $href) use ($path, $starts): bool {
  return $starts($path, $href);
};

$slug  = (string)($org['slug'] ?? '');
$brand = $slug !== '' ? ucfirst($slug) : 'KlinFlow';

/* ────────────────────────────────────────────────────────────────────
 * SEGMENT 2: Menu model (module-local)
 * ────────────────────────────────────────────────────────────────── */
$menu = function (string $b): array {
  $b = rtrim($b, '/');
  return [
    [
      'key'=>'main', 'icon'=>'fa-gauge-high', 'label'=>'Main Dashboard',
      'children'=>[
        ['label'=>'Dashboard',      'href'=>"$b/dashboard"],
        ['label'=>'Quick Overview', 'href'=>"$b/reports"],
      ]
    ],

    [
      'key'=>'sales', 'icon'=>'fa-bag-shopping', 'label'=>'Sales',
      'children'=>[
        ['label'=>'All Orders',        'href'=>"$b/orders"],
        ['label'=>'Create Order',      'href'=>"$b/orders/create"],
        ['label'=>'Sales / Invoices',  'href'=>"$b/sales"],
        ['label'=>'Create Invoice',    'href'=>"$b/sales/create"],
        ['label'=>'Payments',          'href'=>"$b/payments"],
        ['label'=>'Returns',           'href'=>"$b/returns"],
      ]
    ],

    [
      'key'=>'products', 'icon'=>'fa-boxes-stacked', 'label'=>'Products & Purchases',
      'children'=>[
        ['label'=>'Products',      'href'=>"$b/products"],
        ['label'=>'Categories',    'href'=>"$b/categories"],
        ['label'=>'Purchases',     'href'=>"$b/purchases"],
        ['label'=>'New Purchase',  'href'=>"$b/purchases/create"],
      ]
    ],

    [
      'key'=>'inventory', 'icon'=>'fa-warehouse', 'label'=>'Inventory',
      'children'=>[
        ['label'=>'Overview',           'href'=>"$b/inventory"],
        ['label'=>'Adjustments',        'href'=>"$b/inventory/adjust"],
        ['label'=>'Aging',              'href'=>"$b/inventory/aging"],
        ['label'=>'Damage Reports',     'href'=>"$b/inventory/damage"],
        ['label'=>'Free Stock — Receive','href'=>"$b/free/receive"],
        ['label'=>'Free Stock — Issue',  'href'=>"$b/free/issue"],
        ['label'=>'Free Stock — Movements','href'=>"$b/free/movements"],
        ['label'=>'Free Stock — Inventory','href'=>"$b/free/inventory"],
      ]
    ],

    [
      'key'=>'accounts', 'icon'=>'fa-chart-line', 'label'=>'Accounting & Cash',
      'children'=>[
        ['label'=>'Accounting Dashboard', 'href'=>"$b/accounts"],
        ['label'=>'Cash Book',            'href'=>"$b/accounts/cash-book"],
        ['label'=>'Mobile Bank Book',     'href'=>"$b/accounts/mobile-bank-book"],
        ['label'=>'Chart of Accounts',    'href'=>"$b/accounts/coa"],

        ['label'=>'Journals',       'href'=>"$b/accounts/journals"],
        ['label'=>'Ledger',         'href'=>"$b/accounts/ledger"],
        ['label'=>'Trial Balance',  'href'=>"$b/accounts/trial-balance"],
        ['label'=>'Profit & Loss',  'href'=>"$b/accounts/profit-and-loss"],
        ['label'=>'Balance Sheet',  'href'=>"$b/accounts/balance-sheet"],

        ['label'=>'Bank Accounts',        'href'=>"$b/bank-accounts"],
        ['label'=>'New Bank Account',     'href'=>"$b/bank-accounts/create"],

        ['label'=>'All Expenses',   'href'=>"$b/expenses"],
        ['label'=>'Post Expenses',  'href'=>"$b/expenses/create"],
      ]
    ],

    [
      'key'=>'customers', 'icon'=>'fa-user-group', 'label'=>'Clients',
      'children'=>[
        ['label'=>'Customers',       'href'=>"$b/customers"],
        ['label'=>'Add Customer',    'href'=>"$b/customers/create"],
        ['label'=>'Credit Summary',  'href'=>"$b/customers/credit-summary"],
      ]
    ],

    [
      'key'=>'stakeholders', 'icon'=>'fa-people-group', 'label'=>'Stakeholders',
      'children'=>[
        ['label'=>'Suppliers',   'href'=>"$b/suppliers"],
        ['label'=>'Add Supplier','href'=>"$b/suppliers/create"],
        ['label'=>'Performance', 'href'=>"$b/stakeholders/performance"],
        ['label'=>'SR Listing',  'href'=>"$b/stakeholders/sr"],
        ['label'=>'Create SR',   'href'=>"$b/stakeholders/sr/create"],
      ]
    ],
  ];
};

/* Compute which groups should be open on first paint (server-side hint) */
$sections = [];
foreach ($menu($base) as $g) {
  $open = false;
  foreach (($g['children'] ?? []) as $c) {
    $href = (string)($c['href'] ?? '');
    if ($href !== '' && $active($href)) { $open = true; break; }
  }
  $g['__open'] = $open;
  $sections[]  = $g;
}
?>
<nav class="p-3 text-[15px] select-none" role="navigation" aria-label="DMS primary">
  <!-- SEGMENT A: Brand header (module-scoped) -->
  <div class="flex items-center gap-3 px-2 py-3 mb-3">
    <div class="h-10 w-10 rounded-xl text-white grid place-items-center font-bold"
         style="background: var(--brand, #228B22)">
      <?= $h(strtoupper(substr($brand, 0, 2))) ?>
    </div>
    <div class="leading-tight min-w-0">
      <div class="text-xl font-extrabold truncate"><?= $h($brand) ?></div>
      <div class="text-xs text-slate-400 truncate"><?= $h($slug) ?></div>
    </div>
  </div>

  <!-- SEGMENT B: Overview quick link -->
  <div class="px-2 pt-1 pb-2 border-b border-slate-200 dark:border-white/10">
    <div class="text-[11px] tracking-wider uppercase text-slate-400 mb-2">Overview</div>
    <?php $is = $active($base.'/dashboard'); ?>
    <a href="<?= $h($base) ?>/dashboard"
       class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5"
       style="<?= $is ? 'background: color-mix(in oklab, var(--brand, #228B22) 16%, white); color: #064e3b;' : '' ?>">
      <i class="fa-solid fa-gauge-high" style="color: var(--brand, #228B22)"></i>
      <span>App Dashboard</span>
    </a>
  </div>

  <!-- SEGMENT C: Grouped modules -->
  <div class="px-2 pt-4">
    <div class="text-[11px] tracking-wider uppercase text-slate-400 mb-2">Modules</div>

    <?php foreach ($sections as $sec): ?>
      <?php
        $groupId = (string)$sec['key'];
        $open    = (bool)$sec['__open'];
      ?>
      <div class="mb-1 kf-group"
           data-group="<?= $h($groupId) ?>"
           aria-expanded="<?= $open ? 'true' : 'false' ?>">
        <!-- Group head -->
        <button type="button"
                class="kf-head w-full flex justify-between items-center px-3 py-2 rounded-lg cursor-pointer hover:bg-slate-100 focus:outline-none focus-ring"
                aria-controls="sub-<?= $h($groupId) ?>"
                aria-haspopup="true"
                aria-expanded="<?= $open ? 'true' : 'false' ?>"
                <?= $open ? 'data-open="1"' : '' ?>>
          <span class="flex items-center gap-3">
            <i class="fa-solid <?= $h((string)$sec['icon']) ?>" style="color: var(--brand, #228B22)"></i>
            <span class="truncate font-medium"><?= $h((string)$sec['label']) ?></span>
          </span>
          <i class="fa-solid fa-chevron-right text-slate-400 transition-transform <?= $open ? 'rotate-90':'' ?>"></i>
        </button>

        <!-- Group body -->
        <div id="sub-<?= $h($groupId) ?>"
             class="kf-sub ml-3 pl-3 mt-1 border-l border-slate-200 <?= $open ? '' : 'hidden' ?>">
          <?php foreach ($sec['children'] as $c): ?>
            <?php $href = (string)$c['href']; $isLink = $active($href); ?>
            <a href="<?= $h($href) ?>"
               class="block px-3 py-2 rounded-lg hover:bg-slate-100"
               style="<?= $isLink ? 'background: color-mix(in oklab, var(--brand, #228B22) 16%, white); color:#064e3b;' : '' ?>">
              <?= $h((string)$c['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- SEGMENT D: Footer -->
  <div class="px-3 pt-6 text-xs text-slate-400">v1.0 · <?= date('Y') ?></div>
</nav>

<!-- SEGMENT E: Minimal styles (brand + focus) -->
<style>
  .kf-head { transition: background .2s; }
  .kf-group[aria-expanded="true"] .fa-chevron-right { transform: rotate(90deg); }
  .focus-ring:focus { outline: 0; box-shadow: 0 0 0 3px color-mix(in oklab, var(--brand, #228B22), transparent 75%); }
</style>

<!-- SEGMENT F: Behavior (persist open group; keyboard; single-open) -->
<script>
(function(){
  var slug = <?= json_encode($slug, JSON_UNESCAPED_SLASHES) ?> || '';
  var KEY  = 'kf:nav:open:' + slug;

  function save(id){ try { localStorage.setItem(KEY, id || ''); } catch(e){} }
  function read(){ try { return localStorage.getItem(KEY) || ''; } catch(e){ return ''; } }

  function setOpen(group, open){
    if(!group) return;
    group.setAttribute('aria-expanded', open ? 'true' : 'false');
    var sub  = group.querySelector('.kf-sub');
    var head = group.querySelector('.kf-head');
    if (sub)  sub.classList.toggle('hidden', !open);
    if (head) head.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function openOnly(id){
    document.querySelectorAll('.kf-group').forEach(function(g){
      setOpen(g, g.dataset.group === id && id !== '');
    });
    save(id);
  }

  // Click toggle (single-open)
  document.addEventListener('click', function(e){
    var head = e.target.closest('.kf-head');
    if(!head) return;
    var group = head.closest('.kf-group');
    var id    = group ? (group.dataset.group || '') : '';
    var curr  = read();
    openOnly(curr === id ? '' : id);
  });

  // Keyboard support (Enter/Space toggles)
  document.addEventListener('keydown', function(e){
    var head = e.target.closest && e.target.closest('.kf-head');
    if(!head) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      head.click();
    }
  }, true);

  // Boot: prefer server-open; then persisted
  var persisted = read();
  if (persisted) {
    openOnly(persisted);
  } else {
    // Ensure at most one server-marked group stays open
    var firstOpen = document.querySelector('.kf-group[aria-expanded="true"]');
    if (firstOpen) openOnly(firstOpen.dataset.group || '');
  }
})();
</script>