<?php
declare(strict_types=1);
/** Expected:
 *  - $module_base (string) e.g. "/t/{slug}/apps/medflow"
 *  - $org (array) optional, used only to key localStorage per-tenant
 */
$h   = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$mb  = $module_base ?? '/apps/medflow';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$slug= isset($org['slug']) ? (string)$org['slug'] : '';
$LSK = 'kf:nav:open:medflow:' . $slug;

/** helper: mark active */
$active = function (string $href) use ($uri) {
  return (strpos($uri, $href) === 0) ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-900/20' : 'hover:bg-gray-100 dark:hover:bg-gray-700';
};

/** groups config */
$groups = [
  [
    'key'   => 'overview',
    'icon'  => 'fa-house',
    'label' => 'Overview',
    'items' => [
      ['text'=>'Dashboard','href'=>$mb],
    ],
  ],
  [
    'key'   => 'sales',
    'icon'  => 'fa-receipt',
    'label' => 'Sales',
    'items' => [
      ['text'=>'New Sale (POS)',  'href'=>$mb.'/sales/new'],
      ['text'=>'Sales History',   'href'=>$mb.'/sales'],
      ['text'=>'Holds / Quotes',  'href'=>$mb.'/sales/holds'],
      ['text'=>'Returns',         'href'=>$mb.'/sales/returns'],
      ['text'=>'Customers',       'href'=>$mb.'/customers'],
      ['text'=>'Customer Payments','href'=>$mb.'/customers/payments'],
    ],
  ],
  [
    'key'   => 'purchases',
    'icon'  => 'fa-file-invoice-dollar',
    'label' => 'Purchases',
    'items' => [
      ['text'=>'New Purchase',     'href'=>$mb.'/purchases/new'],
      ['text'=>'Purchase History', 'href'=>$mb.'/purchases'],
      ['text'=>'Purchase Returns', 'href'=>$mb.'/purchases/returns'],
      ['text'=>'Suppliers',        'href'=>$mb.'/suppliers'],
      ['text'=>'Supplier Payments','href'=>$mb.'/suppliers/payments'],
    ],
  ],
  [
    'key'   => 'inventory',
    'icon'  => 'fa-boxes-stacked',
    'label' => 'Inventory',
    'items' => [
      ['text'=>'Items (Medicines)', 'href'=>$mb.'/inventory/items'],
      ['text'=>'Batches & Expiry',  'href'=>$mb.'/inventory/batches'],
      ['text'=>'Stock Movements',   'href'=>$mb.'/inventory/stock-moves'],
      ['text'=>'Adjustments',       'href'=>$mb.'/inventory/adjustments'],
      ['text'=>'Transfers',         'href'=>$mb.'/inventory/transfers'],
      ['text'=>'Stock Count',       'href'=>$mb.'/inventory/stock-count'],
      ['text'=>'Low Stock Alerts',  'href'=>$mb.'/inventory/low-stock'],
      ['text'=>'Near Expiry',       'href'=>$mb.'/inventory/near-expiry'],
    ],
  ],
  [
    'key'   => 'masters',
    'icon'  => 'fa-sitemap',
    'label' => 'Masters',
    'items' => [
      ['text'=>'Categories',        'href'=>$mb.'/masters/categories'],
      ['text'=>'Brands / Mfr',      'href'=>$mb.'/masters/brands'],
      ['text'=>'Units & Conversion','href'=>$mb.'/masters/units'],
      ['text'=>'Tax Rates',         'href'=>$mb.'/masters/taxes'],
      ['text'=>'Pricing Rules',     'href'=>$mb.'/masters/pricing-rules'],
    ],
  ],
  [
    'key'   => 'reports',
    'icon'  => 'fa-chart-line',
    'label' => 'Reports',
    'items' => [
      ['text'=>'Sales Summary',     'href'=>$mb.'/reports/sales-summary'],
      ['text'=>'Sales by Item',     'href'=>$mb.'/reports/sales-by-item'],
      ['text'=>'Returns Summary',   'href'=>$mb.'/reports/returns'],
      ['text'=>'Purchase Summary',  'href'=>$mb.'/reports/purchase-summary'],
      ['text'=>'Stock Valuation',   'href'=>$mb.'/reports/stock-valuation'],
      ['text'=>'Expiry Report',     'href'=>$mb.'/reports/expiry'],
      ['text'=>'Tax Report',        'href'=>$mb.'/reports/tax'],
      ['text'=>'Fast/Slow Movers',  'href'=>$mb.'/reports/movers'],
    ],
  ],
  [
    'key'   => 'settings',
    'icon'  => 'fa-gear',
    'label' => 'Settings',
    'items' => [
      ['text'=>'Outlet / Store',    'href'=>$mb.'/settings/store'],
      ['text'=>'Payment Methods',   'href'=>$mb.'/settings/payments'],
      ['text'=>'Numbering & Codes', 'href'=>$mb.'/settings/numbering'],
      ['text'=>'Print Templates',   'href'=>$mb.'/settings/printing'],
      ['text'=>'Users & Roles',     'href'=>$mb.'/settings/users'],
      ['text'=>'Preferences',       'href'=>$mb.'/settings/preferences'],
    ],
  ],
  [
    'key'   => 'help',
    'icon'  => 'fa-circle-info',
    'label' => 'Help',
    'items' => [
      ['text'=>'Docs',               'href'=>$mb.'/help/docs'],
      ['text'=>'Keyboard Shortcuts', 'href'=>$mb.'/help/shortcuts'],
      ['text'=>'About MedFlow',      'href'=>$mb.'/help/about'],
    ],
  ],
];
?>
<!-- Brand block -->
<div class="p-3 pb-2">
  <div class="flex items-center gap-3 px-2 py-3 rounded-xl bg-white/70 dark:bg-white/5">
    <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white grid place-items-center font-bold">
      <?= strtoupper(substr($slug !== '' ? $slug : 'MF', 0, 2)) ?>
    </div>
    <div class="leading-tight min-w-0">
      <div class="text-xl font-extrabold truncate"><?= $h($slug !== '' ? ucfirst($slug) : 'MedFlow') ?></div>
      <div class="text-xs text-slate-400 truncate">MedFlow</div>
    </div>
  </div>
</div>

<!-- Groups -->
<nav class="px-3 pb-4 text-[15px] select-none" id="mf-nav">
  <?php foreach ($groups as $g):
    $open = false;
    foreach ($g['items'] as $it) {
      if (strpos($uri, $it['href']) === 0) { $open = true; break; }
    }
  ?>
  <div class="mb-1 kf-group border-b border-gray-100 dark:border-white/10"
       data-group-key="<?= $h($g['key']) ?>" aria-expanded="<?= $open?'true':'false' ?>">
    <button type="button"
            class="kf-group-head w-full flex items-center justify-between px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
      <span class="flex items-center gap-3">
        <i class="fa-solid <?= $h($g['icon']) ?>" style="color:var(--brand)"></i>
        <span class="truncate font-medium"><?= $h($g['label']) ?></span>
      </span>
      <i class="kf-chev fa-solid fa-chevron-right text-slate-400 transition-transform <?= $open?'rotate-90':'' ?>"></i>
    </button>
    <div class="kf-sub ml-3 pl-3 mt-1 border-l border-gray-200 dark:border-white/10 <?= $open?'':'hidden' ?>">
      <?php foreach ($g['items'] as $it): ?>
        <a href="<?= $h($it['href']) ?>"
           class="flex items-center gap-2 px-3 py-2 rounded-lg <?= $active($it['href']) ?>">
          <i class="fa-solid fa-circle text-[8px]" style="color:currentColor"></i>
          <span><?= $h($it['text']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="px-3 pt-3 text-xs text-gray-400">MedFlow · v0.1</div>
</nav>

<script>
(function(){
  const NAV = document.getElementById('mf-nav');
  if (!NAV) return;
  const KEY = <?= json_encode($LSK) ?>;

  function readOpen(){
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch(e){ return []; }
  }
  function writeOpen(arr){
    try { localStorage.setItem(KEY, JSON.stringify(arr||[])); } catch(e){}
  }
  function setOpen(group, open){
    if(!group) return;
    const sub  = group.querySelector('.kf-sub');
    const chev = group.querySelector('.kf-chev');
    group.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (sub)  sub.classList.toggle('hidden', !open);
    if (chev) chev.classList.toggle('rotate-90', !!open);
  }

  // restore
  const openSet = new Set(readOpen());
  NAV.querySelectorAll('.kf-group[data-group-key]').forEach(g=>{
    const key = g.getAttribute('data-group-key') || '';
    const isOpen = g.getAttribute('aria-expanded') === 'true' || openSet.has(key);
    setOpen(g, isOpen);
  });

  // toggle & persist
  NAV.addEventListener('click', (e)=>{
    const head = e.target.closest('.kf-group-head');
    if(!head) return;
    e.preventDefault();
    const group = head.closest('.kf-group');
    const key = group?.getAttribute('data-group-key') || '';
    const nowOpen = group.getAttribute('aria-expanded') !== 'true';
    const set = new Set(readOpen());
    if (nowOpen) set.add(key); else set.delete(key);
    writeOpen(Array.from(set));
    setOpen(group, nowOpen);
  });
})();
</script>