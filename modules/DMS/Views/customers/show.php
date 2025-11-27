<?php
declare(strict_types=1);

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

/** @var array $customer */
/** @var array $summary optional numbers: orders, invoices, receivable */
$customer = $customer ?? [];
$id   = (int)($customer['id'] ?? 0);
$code = (string)($customer['code'] ?? '');
$summary = $summary ?? []; // optional, safe defaults below
?>
<div class="max-w-5xl mx-auto">
  <!-- Header -->
  <div class="flex items-start justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold"><?= h($customer['name'] ?? 'Customer') ?></h1>
      <div class="text-slate-500">
        <span class="mr-3">Code: <span class="font-mono"><?= h($code) ?></span></span>
        <?php $st = strtolower((string)($customer['status'] ?? 'active')); ?>
        <span class="px-2 py-0.5 rounded-full text-[11px]
                     <?= $st==='inactive' ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-700' ?>">
          <?= h(ucfirst($st)) ?>
        </span>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= h($module_base) ?>/customers/<?= $id ?>/edit"
         class="px-3 py-2 rounded-lg border hover:bg-slate-50">Edit</a>
      <a href="<?= h($module_base) ?>/orders/create?customer_id=<?= $id ?>"
         class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">New Order</a>
    </div>
  </div>

  <!-- Info cards -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="p-4 rounded-xl border bg-slate-50">
      <div class="text-xs text-slate-500">Total Orders</div>
      <div class="text-xl font-semibold"><?= number_format((int)($summary['orders'] ?? 0)) ?></div>
    </div>
    <div class="p-4 rounded-xl border bg-slate-50">
      <div class="text-xs text-slate-500">Invoices</div>
      <div class="text-xl font-semibold"><?= number_format((int)($summary['invoices'] ?? 0)) ?></div>
    </div>
    <div class="p-4 rounded-xl border bg-slate-50">
      <div class="text-xs text-slate-500">Receivable</div>
      <div class="text-xl font-semibold">৳ <?= number_format((float)($summary['receivable'] ?? 0), 2) ?></div>
    </div>
  </div>

  <!-- Details -->
  <div class="rounded-xl border overflow-hidden">
    <div class="bg-slate-50 px-4 py-2 font-medium">Details</div>
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-y-3">
      <div>
        <div class="text-xs text-slate-500">Phone</div>
        <div><?= h($customer['phone'] ?? '—') ?></div>
      </div>
      <div>
        <div class="text-xs text-slate-500">Email</div>
        <div><?= h($customer['email'] ?? '—') ?></div>
      </div>
      <div class="sm:col-span-2">
        <div class="text-xs text-slate-500">Address</div>
        <div><?= h($customer['address'] ?? '—') ?></div>
      </div>
      <?php if (!empty($customer['notes'])): ?>
      <div class="sm:col-span-2">
        <div class="text-xs text-slate-500">Notes</div>
        <div class="whitespace-pre-wrap"><?= h($customer['notes']) ?></div>
      </div>
      <?php endif; ?>
      <?php if (!empty($customer['created_at']) || !empty($customer['updated_at'])): ?>
      <div class="sm:col-span-2 text-xs text-slate-500 mt-2">
        Created: <?= h($customer['created_at'] ?? 'n/a') ?> ·
        Updated: <?= h($customer['updated_at'] ?? 'n/a') ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="mt-6 flex flex-wrap items-center gap-2">
    <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= h($module_base) ?>/customers">Back</a>
    <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= h($module_base) ?>/orders?customer_id=<?= $id ?>">View Orders</a>
    <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= h($module_base) ?>/invoices?customer_id=<?= $id ?>">View Invoices</a>
  </div>
</div>