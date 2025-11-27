<?php
declare(strict_types=1);
/**
 * Hotelflow sidenav (module-scoped, brand-driven)
 * - Uses $module_base and $org passed by the shell/router.
 * - Accents use CSS var(--brand) defined in the module shell.
 *
 * @var string $module_base
 * @var array  $org
 */

$h      = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base   = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$active = function(string $href) use ($path){ return strpos($path, $href) === 0; };

$slug   = (string)($org['slug'] ?? '');
$brand  = 'HotelFlow';

/** Build the HMS menu (module-local) */
$menu = function (string $base): array {
  $b = rtrim($base, '/');
  return [
    ['key'=>'main','icon'=>'fa-gauge-high','label'=>'Main','children'=>[
      ['label'=>'Dashboard','href'=>"$b/dashboard"],
    ]],

    ['key'=>'frontdesk','icon'=>'fa-bell-concierge','label'=>'Front Desk','children'=>[
      ['label'=>'Arrivals','href'=>"$b/frontdesk/arrivals"],
      ['label'=>'In-house','href'=>"$b/frontdesk/inhouse"],
      ['label'=>'Departures','href'=>"$b/frontdesk/departures"],
      ['label'=>'Availability','href'=>"$b/frontdesk/room-status"],
    ]],

    ['key'=>'reservations','icon'=>'fa-calendar-check','label'=>'Reservations','children'=>[
      ['label'=>'All Reservations','href'=>"$b/reservations"],
      ['label'=>'Create Reservation','href'=>"$b/reservations/create"],
      ['label'=>'Calendar','href'=>"$b/reservations/calendar"],
      ['label'=>'Groups / Blocks','href'=>"$b/reservations/groups"],
    ]],

    ['key'=>'rooms','icon'=>'fa-door-closed','label'=>'Rooms & Types','children'=>[
      ['label'=>'Rooms','href'=>"$b/rooms"],
      ['label'=>'Room Types','href'=>"$b/rooms/types"],
      ['label'=>'Floors','href'=>"$b/rooms/floors"],
    ]],

    ['key'=>'hk','icon'=>'fa-broom','label'=>'Housekeeping','children'=>[
      ['label'=>'Board','href'=>"$b/hk"],
      ['label'=>'Tasks','href'=>"$b/hk/tasks"],
      ['label'=>'Lost & Found','href'=>"$b/hk/lost-found"],
    ]],
    
    [
  'key'   => 'finance',
  'icon'  => 'fa-file-invoice-dollar',
  'label' => 'Accounting & Finance',
  'children' => [
    ['label' => 'Dashboard',     'href' => "$b/billing"],
    ['label' => 'Folios',        'href' => "$b/billing/folios"],
    ['label' => 'Payments',      'href' => "$b/billing/payments"],
    ['label' => 'Invoices',      'href' => "$b/billing/invoices"],
    ['label' => 'Credit Notes',  'href' => "$b/billing/credit-notes"],
    ['label' => 'City Ledger',   'href' => "$b/billing/city-ledger"],


    // Admin / setup
    ['label' => 'Invoice Settings', 'href' => "$b/billing/settings"],
   
  ],
],

   [
  'key'   => 'inventory',
  'icon'  => 'fa-boxes-stacked',
  'label' => 'Inventory & Rates',
  'children' => [
    
    ['label' => 'Dashboard',   'href' => "$b/inventory/dashboard"],
    ['label' => 'Products',    'href' => "$b/inventory/products"],
    ['label' => 'Categories',  'href' => "$b/inventory/categories"],
    ['label' => 'Purchases',   'href' => "$b/inventory/purchases"],
    ['label' => 'Stock',       'href' => "$b/inventory/stock"],

    // 🔹 Rates Section
    ['label' => 'Availability Calendar',       'href' => "$b/rates/availability"],
    ['label' => 'Rate Plans',                  'href' => "$b/rates/rate-plans"],
    ['label' => 'Overrides / Seasons',         'href' => "$b/rates/overrides"],
    ['label' => 'Restrictions (CTA/CTD/LOS)',  'href' => "$b/rates/restrictions"],
    ['label' => 'Allotments',                  'href' => "$b/rates/allotments"],
    ['label' => 'Yield Rules',                 'href' => "$b/rates/yield-rules"],
  ]
],

    ['key'=>'billing','icon'=>'fa-file-invoice-dollar','label'=>'Billing','children'=>[
      ['label'=>'Invoices','href'=>"$b/billing/invoices/create"],
      ['label'=>'Folios','href'=>"$b/billing/folios"],
      ['label'=>'Payments','href'=>"$b/billing/payments"],
      ['label'=>'Invoices','href'=>"$b/billing/invoices"],
      ['label'=>'Credit Notes','href'=>"$b/billing/credit-notes"],
      ['label'=>'City Ledger','href'=>"$b/billing/city-ledger"],
    ]],

    ['key'=>'crm','icon'=>'fa-user-group','label'=>'Guests & CRM','children'=>[
      ['label'=>'Guests','href'=>"$b/guests"],
      ['label'=>'Companies','href'=>"$b/companies"],
      ['label'=>'Travel Agents','href'=>"$b/agents"],
    ]],

    ['key'=>'maintenance','icon'=>'fa-screwdriver-wrench','label'=>'Maintenance','children'=>[
      ['label'=>'Tickets','href'=>"$b/maintenance"],
      ['label'=>'Out of Order / Service','href'=>"$b/maintenance/ooo"],
    ]],

    ['key'=>'audit','icon'=>'fa-moon','label'=>'Night Audit','children'=>[
      ['label'=>'Run Audit','href'=>"$b/audit"],
      ['label'=>'Snapshots','href'=>"$b/audit/snapshots"],
      ['label'=>'Locks','href'=>"$b/audit/locks"],
    ]],

    ['key'=>'distribution','icon'=>'fa-cloud-arrow-up','label'=>'Distribution','children'=>[
      ['label'=>'Channels','href'=>"$b/distribution/channels"],
      ['label'=>'Mapping','href'=>"$b/distribution/mapping"],
      ['label'=>'BE / Promo Codes','href'=>"$b/distribution/booking-engine"],
      ['label'=>'Sync Logs','href'=>"$b/distribution/logs"],
    ]],

    ['key'=>'reports','icon'=>'fa-chart-line','label'=>'Reports','children'=>[
      ['label'=>'Revenue','href'=>"$b/reports/revenue"],
      ['label'=>'Occupancy / Pace','href'=>"$b/reports/occ"],
      ['label'=>'Payments','href'=>"$b/reports/payments"],
      ['label'=>'Tax Summary','href'=>"$b/reports/tax"],
    ]],

    ['key'=>'settings','icon'=>'fa-gear','label'=>'Settings','children'=>[
      ['label'=>'Property','href'=>"$b/settings/property"],
      ['label'=>'Taxes & Fees','href'=>"$b/settings/taxes"],
      ['label'=>'Revenue Codes','href'=>"$b/settings/revenue-codes"],
      ['label'=>'Payment Methods','href'=>"$b/settings/payment-methods"],
      ['label'=>'Users & Roles','href'=>"$b/settings/users"],
    ]],
  ];
};

$sections = [];
foreach ($menu($base) as $g) {
  $open = false;
  foreach (($g['children'] ?? []) as $c) {
    $href = (string)($c['href'] ?? '');
    if ($href !== '' && strpos($path, $href) === 0) { $open = true; break; }
  }
  $g['__open'] = $open;
  $sections[] = $g;
}
?>
<nav class="p-3 text-[15px] select-none">
  <!-- Brand header -->
  <div class="flex items-center gap-3 px-2 py-3 mb-3">
    <div class="h-10 w-10 rounded-xl text-white grid place-items-center font-bold" style="background:var(--brand);">
      HF
    </div>
    <div class="leading-tight min-w-0">
      <div class="text-xl font-extrabold truncate"><?= $h($brand) ?></div>
      <div class="text-xs text-slate-400 truncate"><?= $h($slug) ?></div>
    </div>
  </div>

  <!-- Overview -->
  <div class="px-2 pt-1 pb-2 border-b border-slate-200 dark:border-white/10">
    <div class="text-[11px] tracking-wider uppercase text-slate-400 mb-2">Overview</div>
    <?php $is = $active($base.'/dashboard'); ?>
    <a href="<?= $h($base) ?>/dashboard"
       class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 <?= $is?'hf-active':'' ?>">
      <i class="fa-solid fa-gauge-high" style="color:var(--brand)"></i><span>App Dashboard</span>
    </a>
  </div>

  <!-- Groups -->
  <div class="px-2 pt-4">
    <div class="text-[11px] tracking-wider uppercase text-slate-400 mb-2">Modules</div>
    <?php foreach ($sections as $sec): ?>
      <?php $groupId = $h((string)$sec['key']); $open = (bool)$sec['__open']; ?>
      <div class="mb-1 hf-group" data-group="<?= $groupId ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>">
        <div class="hf-head flex justify-between items-center px-3 py-2 rounded-lg cursor-pointer hover:bg-slate-100 <?= $open ? 'hf-open' : '' ?>">
          <span class="flex items-center gap-3">
            <i class="fa-solid <?= $h((string)$sec['icon']) ?>" style="color:var(--brand)"></i>
            <span class="truncate font-medium"><?= $h((string)$sec['label']) ?></span>
          </span>
          <i class="fa-solid fa-chevron-right text-slate-400 transition-transform <?= $open ? 'rotate-90':'' ?>"></i>
        </div>
        <div class="hf-sub ml-3 pl-3 mt-1 border-l border-slate-200 <?= $open ? '' : 'hidden' ?>">
          <?php foreach ($sec['children'] as $c): ?>
            <?php $href = (string)$c['href']; $is = $active($href); ?>
            <a href="<?= $h($href) ?>"
               class="block px-3 py-2 rounded-lg hover:bg-slate-100 <?= $is ? 'hf-active' : '' ?>">
              <?= $h((string)$c['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="px-3 pt-6 text-xs text-slate-400">v1.0 · <?= date('Y') ?></div>
</nav>

<style>
  /* Brand-aware accents (pulls from :root --brand) */
  .hf-active{
    color:var(--brand)!important;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    border-left: 3px solid var(--brand);
  }
  .dark .hf-active{
    background: color-mix(in srgb, var(--brand) 18%, transparent);
  }
  .hf-head.hf-open{
    background: color-mix(in srgb, var(--brand) 10%, transparent);
  }
  .dark .hf-head.hf-open{
    background: color-mix(in srgb, var(--brand) 16%, transparent);
  }
</style>

<script>
(function(){
  const slug = <?= json_encode($slug) ?> || '';
  const KEY  = 'hf:nav:open:' + slug;

  function save(k){ try{ localStorage.setItem(KEY, k||''); }catch(e){} }
  function read(){ try{ return localStorage.getItem(KEY)||''; }catch(e){ return ''; } }

  function setOpen(group, open){
    if(!group) return;
    group.setAttribute('aria-expanded', open?'true':'false');
    const sub  = group.querySelector('.hf-sub');
    const head = group.querySelector('.hf-head');
    if(sub){ sub.classList.toggle('hidden', !open); }
    if(head){ head.classList.toggle('hf-open', !!open); }
  }

  function openOnly(id){
    document.querySelectorAll('.hf-group').forEach(g=>{
      setOpen(g, g.dataset.group === id);
    });
    save(id);
  }

  document.addEventListener('click', (e)=>{
    const head = e.target.closest('.hf-head');
    if(!head) return;
    const group = head.closest('.hf-group');
    const id = group?.dataset.group || '';
    const curr = read();
    openOnly(curr === id ? '' : id);
  });

  // Boot: prefer persisted id
  const persisted = read();
  if(persisted) openOnly(persisted);
})();
</script>