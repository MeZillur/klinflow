<?php
declare(strict_types=1);
/**
 * @var array  $cust
 * @var array  $stats
 * @var string $base
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* Derive extra fields */
$address = trim(($cust['address_line1'] ?? '') . ' ' . ($cust['address_line2'] ?? ''));
$address = $address !== '' ? $address : '—';

$created = $cust['created_at'] ?? null;
$ageDays = $created ? floor((time() - strtotime($created)) / 86400) : null;
$ageText = $ageDays !== null ? $ageDays . ' days' : '—';
?>
<div class="max-w-5xl mx-auto px-4 py-6">

  <!-- Header -->
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-brand">Customer Details</h1>

    <div class="flex gap-2">
      <a href="<?= $h($base) ?>/customers"
         class="btn btn-outline">← Back</a>

      <a href="<?= $h($base) ?>/customers/<?= (int)$cust['id'] ?>/edit"
         class="btn btn-primary">Edit</a>
    </div>
  </div>

  <!-- Top Summary Cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

    <div class="card shadow-sm border border-brand/20">
      <div class="text-xs text-gray-500 mb-1">Orders</div>
      <div class="text-xl font-semibold"><?= (int)($stats['orders'] ?? 0) ?></div>
    </div>

    <div class="card shadow-sm border border-brand/20">
      <div class="text-xs text-gray-500 mb-1">Total Spent</div>
      <div class="text-xl font-semibold">
        <?= number_format((float)($stats['spent'] ?? 0), 2) ?>
      </div>
    </div>

    <div class="card shadow-sm border border-brand/20">
      <div class="text-xs text-gray-500 mb-1">Rewards</div>
      <div class="text-xl font-semibold">
        <?= number_format((float)($stats['rewards'] ?? 0), 2) ?>
      </div>
    </div>

    <div class="card shadow-sm border border-brand/20">
      <div class="text-xs text-gray-500 mb-1">Customer Age</div>
      <div class="text-xl font-semibold"><?= $h($ageText) ?></div>
    </div>

  </div>

  <!-- Main Customer Information -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Profile -->
    <div class="card">
      <h2 class="font-semibold mb-3 text-gray-600">Profile</h2>
      <div class="space-y-2 text-sm">
        <div><span class="font-medium text-gray-500">Code:</span> <?= $h($cust['code'] ?? '—') ?></div>
        <div><span class="font-medium text-gray-500">Name:</span> <?= $h($cust['name'] ?? '—') ?></div>
        <div><span class="font-medium text-gray-500">Phone:</span> <?= $h($cust['phone'] ?? '—') ?></div>
        <div><span class="font-medium text-gray-500">Email:</span> <?= $h($cust['email'] ?? '—') ?></div>

        <div><span class="font-medium text-gray-500">Address:</span> <?= $h($address) ?></div>
        <div><span class="font-medium text-gray-500">City:</span> <?= $h($cust['city'] ?? '—') ?></div>
        <div><span class="font-medium text-gray-500">Country:</span> <?= $h($cust['country'] ?? '—') ?></div>

        <div class="pt-2">
          <span class="font-medium text-gray-500">Status:</span>
          <?php if ((int)($cust['is_active'] ?? 1) === 1): ?>
            <span class="badge" style="background:var(--brand);color:#fff;">Active</span>
          <?php else: ?>
            <span class="badge bg-gray-200 text-gray-700">Inactive</span>
          <?php endif; ?>
        </div>

        <div class="pt-2">
          <span class="font-medium text-gray-500">Created:</span>
          <?= $h($cust['created_at'] ?? '—') ?>
        </div>
        <div>
          <span class="font-medium text-gray-500">Updated:</span>
          <?= $h($cust['updated_at'] ?? '—') ?>
        </div>
      </div>
    </div>

    <!-- Activity -->
    <div class="card">
      <h2 class="font-semibold mb-3 text-gray-600">Recent Activity</h2>
      <div class="space-y-2 text-sm">

        <div><span class="font-medium text-gray-500">Total Orders:</span>
          <?= (int)($stats['orders'] ?? 0) ?>
        </div>

        <div><span class="font-medium text-gray-500">Total Spent:</span>
          <?= number_format((float)($stats['spent'] ?? 0), 2) ?>
        </div>

        <div><span class="font-medium text-gray-500">Reward Points:</span>
          <?= number_format((float)($stats['rewards'] ?? 0), 2) ?>
        </div>

        <?php if (!empty($cust['notes'])): ?>
          <div class="pt-3">
            <span class="font-medium text-gray-500">Notes:</span><br>
            <div class="mt-1 p-2 rounded border text-sm"><?= $h($cust['notes']) ?></div>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </div>

  <!-- Footer Buttons -->
  <div class="mt-6 flex gap-3">
    <a href="<?= $h($base) ?>/customers" class="btn btn-outline">Back to Customers</a>
    <a href="<?= $h($base) ?>/customers/<?= (int)$cust['id'] ?>/edit" class="btn btn-primary">Edit Customer</a>
  </div>

</div>