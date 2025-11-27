<?php
declare(strict_types=1);
/**
 * Purchases — Coming Soon placeholder
 *
 * Vars: $base, $title, $ctx
 */
$h    = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
?>
<div class="px-6 py-8 max-w-5xl mx-auto space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl
                     bg-emerald-600/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
          <i class="fa fa-file-invoice" aria-hidden="true"></i>
        </span>
        Purchases
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Supplier purchases, GRNs, and stock costing are on the roadmap.
      </p>
    </div>

    <a href="<?= $h($base) ?>/dashboard"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm
              border border-gray-200 dark:border-gray-700
              text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left-long text-xs" aria-hidden="true"></i>
      Back to POS Dashboard
    </a>
  </div>

  <!-- Main card -->
  <div class="rounded-2xl border border-dashed border-emerald-400/60
              bg-emerald-50/60 dark:bg-gray-900/80 dark:border-emerald-500/60
              shadow-sm">
    <div class="px-6 py-6 md:px-8 md:py-8 flex flex-col md:flex-row gap-6 md:gap-10">
      <div class="flex-1 space-y-3">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full
                    bg-white dark:bg-gray-900 border border-emerald-200/80
                    text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
          <i class="fa fa-circle-notch fa-spin text-[10px]" aria-hidden="true"></i>
          Purchases module is in development
        </div>

        <h2 class="text-xl md:text-2xl font-semibold text-gray-900 dark:text-gray-50">
          Soon you’ll be able to manage supplier orders from here.
        </h2>

        <p class="text-sm text-gray-600 dark:text-gray-400">
          This screen will handle purchase orders, goods receipt notes (GRN),
          purchase returns, and landed-cost adjustments.  
          For now, you can continue using:
        </p>

        <ul class="mt-2 space-y-1.5 text-sm text-gray-700 dark:text-gray-300">
          <li class="flex items-start gap-2">
            <span class="mt-1 text-emerald-500">
              <i class="fa fa-check-circle" aria-hidden="true"></i>
            </span>
            <span><strong>Suppliers</strong> module to maintain vendor profiles.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-1 text-emerald-500">
              <i class="fa fa-check-circle" aria-hidden="true"></i>
            </span>
            <span><strong>Inventory &amp; Transfers</strong> for stock movements between branches.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="mt-1 text-emerald-500">
              <i class="fa fa-check-circle" aria-hidden="true"></i>
            </span>
            <span><strong>Sales Register</strong> for day-to-day POS operations.</span>
          </li>
        </ul>
      </div>

      <!-- Roadmap column -->
      <div class="w-full md:w-64 lg:w-72">
        <div class="h-full rounded-2xl bg-white/80 dark:bg-gray-900/90
                    border border-gray-200 dark:border-gray-700
                    p-4 flex flex-col justify-between">
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                Roadmap snapshot
              </p>
              <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/40
                           text-amber-700 dark:text-amber-300">
                Phase 2
              </span>
            </div>

            <ol class="mt-1 space-y-2 text-xs text-gray-600 dark:text-gray-300">
              <li class="flex gap-2">
                <span class="mt-0.5 text-emerald-500">
                  <i class="fa fa-circle-dot text-[9px]" aria-hidden="true"></i>
                </span>
                <span>Purchase order list &amp; basic GRN.</span>
              </li>
              <li class="flex gap-2">
                <span class="mt-0.5 text-emerald-500">
                  <i class="fa fa-circle-dot text-[9px]" aria-hidden="true"></i>
                </span>
                <span>Supplier-wise purchase history &amp; dues.</span>
              </li>
              <li class="flex gap-2">
                <span class="mt-0.5 text-emerald-500">
                  <i class="fa fa-circle-dot text-[9px]" aria-hidden="true"></i>
                </span>
                <span>Integration with inventory costing &amp; accounting.</span>
              </li>
            </ol>
          </div>

          <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 text-[11px]
                      text-gray-500 dark:text-gray-400">
            Need purchases earlier for this organisation?
            <br>
            Configure from the main admin panel when the module is ready.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick navigation -->
  <div class="flex flex-wrap gap-3 text-sm">
    <a href="<?= $h($base) ?>/suppliers"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200
              dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-truck-field" aria-hidden="true"></i>
      Go to Suppliers
    </a>
    <a href="<?= $h($base) ?>/inventory"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200
              dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-boxes-stacked" aria-hidden="true"></i>
      Go to Inventory
    </a>
    <a href="<?= $h($base) ?>/sales/register"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white shadow
              bg-emerald-600 hover:bg-emerald-700">
      <i class="fa fa-cash-register" aria-hidden="true"></i>
      Open Sales Register
    </a>
  </div>
</div>