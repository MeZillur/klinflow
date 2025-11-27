<?php
$h     = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base  = $base ?? '/apps/pos';
$brand = '#228B22';
$today = date('Y-m-d');
?>
<div class="px-4 md:px-6 py-6">

  <div class="max-w-6xl mx-auto space-y-6"><!-- WIDER LAYOUT -->

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-cash-register text-emerald-500"></i>
          <span>New Cash Register</span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          One register per POS counter / till for clean closing each day.
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500">
          আজকের তারিখ:
          <span class="font-medium text-gray-700 dark:text-gray-300"><?= $h(date('d M Y')) ?></span>
        </p>
      </div>
      <a href="<?= $h($base) ?>/banking/cash-registers"
         class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-arrow-left text-xs"></i>
        Back
      </a>
    </div>

    <!-- Chip Nav -->
    <div class="flex flex-wrap gap-2">
      <a href="<?= $h($base) ?>/banking/accounts"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-landmark text-[10px]"></i> HQ Accounts
      </a>
      <a href="<?= $h($base) ?>/banking/cash-registers"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-emerald-500/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40">
        <i class="fa fa-cash-register text-[10px]"></i> Registers
      </a>
      <a href="<?= $h($base) ?>/banking/deposits"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-arrow-up-from-bracket text-[10px]"></i> Deposits
      </a>
    </div>

    <!-- Horizontal Layout (Form left + Guide right) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- FORM (LEFT 2 columns) -->
      <div class="lg:col-span-2">
        <form method="post"
              action="<?= $h($base) ?>/banking/cash-registers"
              class="space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm">

          <!-- Name + code -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                Register Name <span class="text-red-500">*</span>
              </label>
              <input name="name" required
                     class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/70">
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                Code
              </label>
              <input name="code"
                     class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm">
            </div>
          </div>

          <!-- Opening float + date -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                Opening Float
              </label>
              <div class="relative">
                <span class="absolute left-3 inset-y-0 flex items-center text-gray-400">৳</span>
                <input name="opening_float" type="number" step="0.01"
                       class="w-full pl-8 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm">
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                Opened On
              </label>
              <input type="date" name="opened_at" value="<?= $h($today) ?>"
                     class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm">
            </div>
          </div>

          <!-- Status -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Status
            </label>
            <select name="status"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm">
              <option value="open">Open</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Notes
            </label>
            <textarea name="notes" rows="3"
                      class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm"></textarea>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between pt-2">
            <p class="text-[11px] text-gray-400 dark:text-gray-500">
              Branch users will only see registers assigned to their branch.
            </p>
            <div class="flex gap-2">
              <a href="<?= $h($base) ?>/banking/cash-registers"
                 class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm">
                Cancel
              </a>
              <button type="submit"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
                      style="background:<?= $brand ?>;">
                <i class="fa fa-check text-xs"></i> Save Register
              </button>
            </div>
          </div>

        </form>
      </div>

      <!-- GUIDANCE (RIGHT column) -->
      <div class="space-y-4">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
            <i class="fa fa-lightbulb text-amber-400"></i>
            <span>কীভাবে Cash Register ব্যবহার করবেন</span>
          </h2>
          <ul class="mt-2 text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
            <li>• প্রতিটি POS কাউন্টারের জন্য আলাদা register তৈরি করুন।</li>
            <li>• Opening Float = শিফট শুরুর টাকার পরিমাণ।</li>
            <li>• Status “Open” হলে register active থাকে।</li>
            <li>• Branch user শুধু নিজের branch-এর register দেখতে পাবে।</li>
            <li>• HQ সব শাখার register একসাথে দেখতে পারবে।</li>
          </ul>
        </div>

        <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-2xl px-4 py-4">
          <h3 class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
            ছোট্ট টিপস
          </h3>
          <ul class="mt-2 text-[13px] text-emerald-800 dark:text-emerald-100 space-y-1.5">
            <li>• Morning / Evening shift আলাদা register নিলে tracking সহজ হয়।</li>
            <li>• Register অনুযায়ী cash variance খুঁজে পাওয়া সহজ।</li>
            <li>• Counter বন্ধ হলে শুধু Status inactive করলেই যথেষ্ট।</li>
          </ul>
        </div>

      </div>
    </div>

  </div>
</div>