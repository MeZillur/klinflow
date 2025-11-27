<?php
declare(strict_types=1);

/** @var string $base @var array $haveCols */

$h    = $h    ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
$have = array_flip($haveCols ?? []);
$brand = '#228B22';
?>
<div class="px-6 py-6">
  <!-- Header -->
  <div class="flex items-center justify-between mb-6 gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-truck-field text-emerald-600" aria-hidden="true"></i>
        <span>New Supplier</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Add a supplier for purchases, inventory and price tracking.
      </p>
    </div>
    <a href="<?= $h($base) ?>/suppliers"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
              text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left" aria-hidden="true"></i>
      Back to list
    </a>
  </div>

  <!-- Form card -->
  <section class="max-w-4xl">
    <form method="post"
          action="<?= $h($base) ?>/suppliers"
          class="space-y-6 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl p-5 md:p-6 shadow-sm">

      <!-- Basic info -->
      <div class="border-b border-gray-100 dark:border-gray-800 pb-4 mb-2">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <i class="fa fa-id-badge text-emerald-500 text-xs" aria-hidden="true"></i>
          Basic Information
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          Minimum required: supplier name. Other fields are optional and depend on your database columns.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Name <span class="text-red-500">*</span>
          </label>
          <input name="name" required
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>

        <?php if (isset($have['code'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Code
          </label>
          <input name="code"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                 placeholder="e.g. SUP-001">
        </div>
        <?php endif; ?>

        <?php if (isset($have['contact'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Contact Person
          </label>
          <input name="contact"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <?php endif; ?>

        <?php if (isset($have['phone'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Phone
          </label>
          <input name="phone"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                 placeholder="+8801…">
        </div>
        <?php endif; ?>

        <?php if (isset($have['email'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Email
          </label>
          <input name="email" type="email"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                 placeholder="supplier@example.com">
        </div>
        <?php endif; ?>

        <?php if (isset($have['tax_no'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Tax / VAT No.
          </label>
          <input name="tax_no"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <?php endif; ?>
      </div>

      <!-- Address -->
      <?php if (isset($have['address']) || isset($have['address_line1']) || isset($have['city'])): ?>
      <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <i class="fa fa-location-dot text-sky-500 text-xs" aria-hidden="true"></i>
          Address
        </h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
        <?php if (isset($have['address'])): ?>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
              Address
            </label>
            <textarea name="address" rows="2"
                      class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                             bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                             resize-y focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
          </div>
        <?php else: ?>
          <?php if (isset($have['address_line1'])): ?>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
              Address Line 1
            </label>
            <input name="address_line1"
                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                          bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                          focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
          </div>
          <?php endif; ?>

          <?php if (isset($have['address_line2'])): ?>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
              Address Line 2
            </label>
            <input name="address_line2"
                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                          bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                          focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
          </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($have['city'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            City
          </label>
          <input name="city"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <?php endif; ?>

        <?php if (isset($have['state'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            State / Division
          </label>
          <input name="state"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <?php endif; ?>

        <?php if (isset($have['postal_code'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Postal Code
          </label>
          <input name="postal_code"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <?php endif; ?>

        <?php if (isset($have['country'])): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Country
          </label>
          <input name="country"
                 class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                        bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                 placeholder="Bangladesh">
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Notes & status -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 pt-2">
        <?php if (isset($have['notes'])): ?>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Notes
          </label>
          <textarea name="notes" rows="3"
                    class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-700
                           bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-gray-100
                           resize-y focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                    placeholder="Payment terms, special conditions, contact window…"></textarea>
        </div>
        <?php endif; ?>

        <div class="flex flex-col justify-between gap-3">
          <?php if (isset($have['is_active'])): ?>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
              Status
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
              <input type="checkbox" name="is_active" value="1" checked
                     class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
              <span>Active supplier</span>
            </label>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Uncheck to hide this supplier from new purchases.
            </p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Footer actions -->
      <div class="pt-4 mt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Press <span class="font-semibold">Ctrl + S</span> (or tap Save) to create the supplier.
        </p>
        <div class="flex gap-2">
          <a href="<?= $h($base) ?>/suppliers"
             class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700
                    text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
            <i class="fa fa-xmark" aria-hidden="true"></i>
            Cancel
          </a>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white shadow"
                  style="background: <?= $brand ?>;">
            <i class="fa fa-save" aria-hidden="true"></i>
            Save Supplier
          </button>
        </div>
      </div>
    </form>
  </section>
</div>