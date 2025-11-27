<?php
declare(strict_types=1);
/** @var array $cust @var string $base */

$h   = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$old = $_SESSION['pos_old']    ?? [];
$err = $_SESSION['pos_errors'] ?? [];
unset($_SESSION['pos_old'], $_SESSION['pos_errors']);

$val = fn(string $k) => $old[$k] ?? ($cust[$k] ?? '');
?>
<div class="max-w-4xl mx-auto px-4 py-6">
  <!-- Header -->
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-brand">Edit Customer</h1>
    <a href="<?= $h($base) ?>/customers"
       class="btn btn-outline">← Back</a>
  </div>

  <!-- Form Card -->
  <div class="card">
    <form method="post"
          action="<?= $h($base) ?>/customers<?= '/' . (int)$cust['id'] ?>"
          class="grid grid-cols-1 md:grid-cols-2 gap-4">

      <!-- Code -->
      <div>
        <label class="block text-sm font-medium mb-1">Code</label>
        <input name="code"
               value="<?= $h($val('code')) ?>"
               class="input"
               placeholder="Leave as-is to keep current code">
        <?php if (isset($err['code'])): ?>
          <div class="text-xs text-red-600 mt-1"><?= $h($err['code']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Name -->
      <div>
        <label class="block text-sm font-medium mb-1">Name <span class="text-red-500">*</span></label>
        <input name="name"
               value="<?= $h($val('name')) ?>"
               class="input">
        <?php if (isset($err['name'])): ?>
          <div class="text-xs text-red-600 mt-1"><?= $h($err['name']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Phone -->
      <div>
        <label class="block text-sm font-medium mb-1">Phone / Mobile</label>
        <input name="phone"
               value="<?= $h($val('phone')) ?>"
               class="input">
      </div>

      <!-- Email -->
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input name="email"
               value="<?= $h($val('email')) ?>"
               class="input">
        <?php if (isset($err['email'])): ?>
          <div class="text-xs text-red-600 mt-1"><?= $h($err['email']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Address Line 1 -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Address line 1</label>
        <input name="address_line1"
               value="<?= $h($val('address_line1')) ?>"
               class="input">
      </div>

      <!-- Address Line 2 -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Address line 2</label>
        <input name="address_line2"
               value="<?= $h($val('address_line2')) ?>"
               class="input">
      </div>

      <!-- City -->
      <div>
        <label class="block text-sm font-medium mb-1">City</label>
        <input name="city"
               value="<?= $h($val('city')) ?>"
               class="input">
      </div>

      <!-- State -->
      <div>
        <label class="block text-sm font-medium mb-1">State/Region</label>
        <input name="state"
               value="<?= $h($val('state')) ?>"
               class="input">
      </div>

      <!-- Postal Code -->
      <div>
        <label class="block text-sm font-medium mb-1">Postal code</label>
        <input name="postal_code"
               value="<?= $h($val('postal_code')) ?>"
               class="input">
      </div>

      <!-- Country -->
      <div>
        <label class="block text-sm font-medium mb-1">Country</label>
        <input name="country"
               value="<?= $h($val('country')) ?>"
               class="input">
      </div>

      <!-- Notes -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Notes</label>
        <textarea name="notes"
                  rows="3"
                  class="input"><?= $h($val('notes')) ?></textarea>
      </div>

      <!-- Active -->
      <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="is_active" value="1"
                 <?= (int)($val('is_active') === '' ? ($cust['is_active'] ?? 1) : $val('is_active')) ? 'checked' : '' ?>>
          <span>Active</span>
        </label>
      </div>

      <!-- Actions -->
      <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
        <button type="submit" class="btn btn-primary">Update Customer</button>
        <a href="<?= $h($base) ?>/customers" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>