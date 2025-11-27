<?php
declare(strict_types=1);

/**
 * BhataFlow sidenav (final)
 * - Shows all major sections; subpages appear as submenu items.
 * - Active detection is path-aware and prefix-matches within the module base.
 * - Remembers last-open group per tenant slug in localStorage.
 *
 * Expects (from shell/controller):
 *   $module_base  e.g. "/t/{slug}/apps/bhata"
 *   $org          array with ['slug'=>...]
 */
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $h, string $n): bool { return $n === '' || strpos($h, $n) === 0; }
}

$h    = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ''), '/') ?: '/apps/bhata';
$slug = (string)($org['slug'] ?? '');
$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

/** Active (exact or subtree) */
$A = static function (string $href) use ($path): bool {
  if ($href === '') return false;
  $href = rtrim($href, '/');
  $p    = rtrim($path, '/');
  return $p === $href || str_starts_with($p, $href . '/');
};

/** Groups (top-level sections) */
$groups = [
  [
    'key'   => 'overview',
    'icon'  => 'fa-gauge-high',
    'label' => 'Overview',
    'items' => [
      ['label' => 'Dashboard', 'href' => "$base/dashboard"],
      ['label' => 'Landing',   'href' => "$base/landing"],
    ],
  ],

  /* ───────────────────────── Production ───────────────────────── */
  [
    'key'   => 'production',
    'icon'  => 'fa-industry',
    'label' => 'Production',
    'items' => [
      ['label'=>'Production Home',  'href'=>"$base/production"],
      ['label'=>'Moulding (Green)','href'=>"$base/production/moulding"],
      ['label'=>'Kiln & Firing',    'href'=>"$base/production/firing"],
      ['label'=>'Dispatch',         'href'=>"$base/production/dispatch"],
      // Optional (only if you add these controllers/views later)
      ['label'=>'Clamps',           'href'=>"$base/production/clamps"],
    ],
  ],

  /* ───────────────────────── Materials ───────────────────────── */
  [
    'key'   => 'materials',
    'icon'  => 'fa-boxes-stacked',
    'label' => 'Materials',
    'items' => [
      ['label'=>'Clay',      'href'=>"$base/materials/clay"],
      ['label'=>'Coal / Fuel','href'=>"$base/materials/coal"],
      ['label'=>'Sand',      'href'=>"$base/materials/sand"],
      ['label'=>'Stock',     'href'=>"$base/materials/stock"],
      ['label'=>'Vouchers',  'href'=>"$base/materials/vouchers"],
    ],
  ],

  /* ─────────────────────────── HR ─────────────────────────────── */
  [
    'key'   => 'hr',
    'icon'  => 'fa-user-check',
    'label' => 'HR',
    'items' => [
      ['label'=>'Attendance',  'href'=>"$base/hr/attendance"],
      ['label'=>'Piece Rate',  'href'=>"$base/hr/piece-rate"],
      ['label'=>'Advances',    'href'=>"$base/hr/advances"],
      ['label'=>'Payroll',     'href'=>"$base/hr/payroll"],
    ],
  ],

  /* ─────────────────────── Finance / Banking ─────────────────── */
  [
    'key'   => 'finance',
    'icon'  => 'fa-money-check-dollar',
    'label' => 'Finance',
    'items' => [
      ['label'=>'Expenses',   'href'=>"$base/finance/expenses"],
      ['label'=>'Banking',    'href'=>"$base/finance/banking"],
      ['label'=>'Cash Book',  'href'=>"$base/finance/cashbook"],
      ['label'=>'Reconcile',  'href'=>"$base/banking/reconcile"],
    ],
  ],

  /* ───────────────────── Accounting / GL ─────────────────────── */
  [
    'key'   => 'gl',
    'icon'  => 'fa-scale-balanced',
    'label' => 'Accounting / GL',
    'items' => [
      ['label'=>'Journals', 'href'=>"$base/gl/journals"],
      // If you later expose COA/Reports here you can add:
      // ['label'=>'Chart of Accounts', 'href'=>"$base/accounts/coa"],
      // ['label'=>'Ledger',            'href'=>"$base/accounts/ledger"],
      // ['label'=>'Trial Balance',     'href'=>"$base/accounts/trial-balance"],
      // ['label'=>'P&L',               'href'=>"$base/accounts/profit-and-loss"],
      // ['label'=>'Balance Sheet',     'href'=>"$base/accounts/balance-sheet"],
    ],
  ],

  /* ───────────────────────── Reports ─────────────────────────── */
  [
    'key'   => 'reports',
    'icon'  => 'fa-chart-line',
    'label' => 'Reports',
    'items' => [
      ['label'=>'All Reports',   'href'=>"$base/reports"],
      ['label'=>'Production',    'href'=>"$base/reports/production"],
      ['label'=>'Sales',         'href'=>"$base/reports/sales"],
      ['label'=>'Finance',       'href'=>"$base/reports/finance"],
      ['label'=>'HR',            'href'=>"$base/reports/hr"],
    ],
  ],

  /* ───────────────────────── Settings ────────────────────────── */
  [
    'key'   => 'settings',
    'icon'  => 'fa-gear',
    'label' => 'Settings',
    'items' => [
      ['label'=>'Settings Home',   'href'=>"$base/settings"],
      ['label'=>'Wage Rates',      'href'=>"$base/settings/wage-rates"],
      ['label'=>'Grades (Master)', 'href'=>"$base/settings/masters/grades"],
      ['label'=>'Sizes (Master)',  'href'=>"$base/settings/masters/sizes"],
      ['label'=>'Locations',       'href'=>"$base/settings/masters/locations"],
      ['label'=>'Pits',            'href'=>"$base/settings/masters/pits"],
    ],
  ],
];

/* Which group to open by default (based on current URL) */
$openKey = '';
foreach ($groups as $g) {
  foreach ($g['items'] as $it) {
    if ($A((string)$it['href'])) { $openKey = $g['key']; break 2; }
  }
}
?>
<nav class="px-3 py-3 text-[15px] select-none">
  <div class="text-[11px] tracking-wider uppercase text-slate-400 mb-2">BhataFlow</div>

  <?php foreach ($groups as $g): ?>
    <?php
      $gid    = $h($g['key']);
      $isOpen = ($openKey === $g['key']);
      $icon   = $h($g['icon']);
      $label  = $h($g['label']);
    ?>
    <div class="mb-1 kf-group" data-group-key="<?= $gid ?>" aria-expanded="<?= $isOpen ? 'true':'false' ?>">
      <button type="button"
        class="kf-group-head w-full flex justify-between items-center px-3 py-2 rounded-lg cursor-pointer hover:bg-slate-100 dark:hover:bg-white/5 <?= $isOpen ? 'bg-emerald-50':'' ?>">
        <span class="flex items-center gap-3">
          <i class="fa-solid <?= $icon ?> text-emerald-600"></i>
          <span class="truncate font-medium"><?= $label ?></span>
        </span>
        <i class="kf-chev fa-solid fa-chevron-right text-slate-400 transition-transform <?= $isOpen ? 'rotate-90':'' ?>"></i>
      </button>

      <div class="kf-sub ml-3 pl-3 mt-1 border-l border-slate-200 dark:border-white/10 <?= $isOpen ? '' : 'hidden' ?>">
        <?php foreach ($g['items'] as $it):
          $href = (string)$it['href']; $active = $A($href); ?>
          <a href="<?= $h($href) ?>"
             class="block px-3 py-2 rounded-lg <?= $active ? 'bg-emerald-50 text-emerald-800' : 'hover:bg-slate-100 dark:hover:bg-white/5' ?>">
            <?= $h((string)$it['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="px-3 pt-6 pb-2 text-xs text-slate-400">v1.0 • <?= date('Y') ?></div>
</nav>

<style>
  .kf-group-head { transition: background .2s; }
  .kf-group[aria-expanded="true"] .kf-chev { transform: rotate(90deg); }
</style>

<script>
(function(){
  var slug = <?= json_encode($slug) ?> || '';
  var KEY  = 'kf:bf:nav:open:' + slug;
  function read(){ try { return localStorage.getItem(KEY) || '' } catch(_) { return '' } }
  function save(v){ try { localStorage.setItem(KEY, v || '') } catch(_) {} }

  function setOpen(g, open){
    if(!g) return;
    g.setAttribute('aria-expanded', open ? 'true' : 'false');
    var sub  = g.querySelector('.kf-sub');
    var head = g.querySelector('.kf-group-head');
    var chev = g.querySelector('.kf-chev');
    if (sub)  sub.classList.toggle('hidden', !open);
    if (head) head.classList.toggle('bg-emerald-50', !!open);
    if (chev) chev.classList.toggle('rotate-90', !!open);
  }

  function openOnly(id){
    document.querySelectorAll('.kf-group').forEach(function(g){
      setOpen(g, g.getAttribute('data-group-key') === id);
    });
    save(id);
  }

  // click to toggle (open-one behavior)
  document.addEventListener('click', function(e){
    var head = e.target.closest && e.target.closest('.kf-group-head');
    if (!head) return;
    var g  = head.closest('.kf-group');
    var id = g ? g.getAttribute('data-group-key') : '';
    var curr = read();
    openOnly(curr === id ? '' : id);
  });

  // restore persisted
  (function(){
    var persisted = read();
    if (persisted) openOnly(persisted);
  })();
})();
</script>