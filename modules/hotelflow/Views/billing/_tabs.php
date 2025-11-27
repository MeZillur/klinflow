<?php
/** @var string $module_base @var string $active */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$tabs = [
  ['key'=>'folios',       'label'=>'Folios',       'href'=>"$base/billing/folios"],
  ['key'=>'payments',     'label'=>'Payments',     'href'=>"$base/billing/payments"],
  ['key'=>'invoices',     'label'=>'Invoices',     'href'=>"$base/billing/invoices"],
  ['key'=>'credit-notes', 'label'=>'Credit Notes', 'href'=>"$base/billing/credit-notes"],
  ['key'=>'city-ledger',  'label'=>'City Ledger',  'href'=>"$base/billing/city-ledger"],
  ['key'=>'settings',     'label'=>'Settings',     'href'=>"$base/billing/settings"],
];
?>
<div class="flex flex-wrap gap-2 border-b border-slate-200 mb-4">
  <?php foreach ($tabs as $t): $is = ($active ?? '') === $t['key']; ?>
    <a href="<?= $h($t['href']) ?>"
       class="px-3 py-2 rounded-t-md border-b-2 <?= $is?'border-emerald-600 text-emerald-700 font-semibold':'border-transparent hover:border-slate-300' ?>">
       <?= $h($t['label']) ?>
    </a>
  <?php endforeach; ?>
</div>