<?php
/** @var array       $rows        Existing rate plans (id, name, code, currency, created_at?) */
/** @var array|null  $ddl         DDL helper from controller */
/** @var string|null $module_base */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base = isset($module_base)
    ? rtrim((string)$module_base, '/')
    : '/hotel/apps/hotelflow';

$brand = '#228B22';

$ddlSql = $ddl['hms_rate_plans'] ?? null;
?>
<div class="p-6 space-y-8">

  <!-- Top bar / header -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
        Rate plans
      </h1>
      <p class="mt-1 text-sm text-slate-500 max-w-2xl">
        Define sellable rate products like <span class="font-medium">BAR</span>, corporate deals, OTA specials,
        and long-stay offers. Each plan can later have its own pricing, rules, and channels.
      </p>
    </div>

    <!-- Tiny related menu -->
    <div class="flex flex-wrap justify-start lg:justify-end gap-2 text-sm">
      <a href="<?= $h($base) ?>/rates"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-gauge-high mr-1"></i> Rates hub
      </a>
      <a href="<?= $h($base) ?>/rates/availability"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-calendar-days mr-1"></i> Availability
      </a>
      <a href="<?= $h($base) ?>/rates/overrides"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-sun-plant-wilt mr-1"></i> Seasons / overrides
      </a>
      <a href="<?= $h($base) ?>/rates/restrictions"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-ban mr-1"></i> Restrictions
      </a>
      <a href="<?= $h($base) ?>/rates/allotments"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-people-arrows mr-1"></i> Allotments
      </a>
    </div>
  </div>

  <!-- Main layout: new plan (left) + list (right) -->
  <div class="grid gap-6 xl:grid-cols-[minmax(0,340px)_minmax(0,1fr)] items-start">

    <!-- Create rate plan card -->
    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 space-y-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl"
                  style="background: rgba(34,139,34,0.08); color: <?= $h($brand) ?>;">
              <i class="fa-solid fa-plus"></i>
            </span>
            New rate plan
          </h2>
          <p class="mt-1 text-xs text-slate-500">
            Start with BAR, corporate, and OTA plans. You can attach detailed pricing later.
          </p>
        </div>
      </div>

      <form method="post"
            action="<?= $h($base) ?>/rates/rate-plans/store"
            class="space-y-4">

        <!-- CSRF placeholder (wire from BaseController if available) -->
        <?php if (function_exists('csrf_field')): ?>
          <?= csrf_field() ?>
        <?php endif; ?>

        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-700">
            Plan name
          </label>
          <input type="text" name="name"
                 class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                        focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                 placeholder="BAR • Best Available Rate" required>
          <p class="text-[11px] text-slate-400">
            Clear, guest-friendly name. Example: “BAR”, “Corporate – NGO”, “Long Stay 7+ Nights”.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Code (optional)
            </label>
            <input type="text" name="code"
                   class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                          focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                   placeholder="BAR, CORP, OTA_BKG">
            <p class="text-[11px] text-slate-400">
              Short internal code used in integrations &amp; exports.
            </p>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Currency
            </label>
            <select name="currency"
                    class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                           focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
              <option value="">Same as property default</option>
              <option value="BDT">BDT – Bangladeshi Taka</option>
              <option value="USD">USD – US Dollar</option>
              <option value="EUR">EUR – Euro</option>
            </select>
            <p class="text-[11px] text-slate-400">
              Optional override. Most properties keep everything in BDT.
            </p>
          </div>
        </div>

        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-700">
            Notes (internal)
          </label>
          <textarea name="notes"
                    rows="2"
                    class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                           focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    placeholder="Usage, included services, target channel (e.g. OTA, direct, corporate)…"></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
          <p class="text-[11px] text-slate-400">
            You can attach seasonal prices, LOS rules and channel mapping after saving.
          </p>
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm"
                  style="background: <?= $h($brand) ?>;">
            <i class="fa-solid fa-floppy-disk text-[11px]"></i>
            Create plan
          </button>
        </div>
      </form>
    </section>

    <!-- Existing plans list -->
    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
      <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
          <i class="fa-solid fa-layer-group text-[<?= $h($brand) ?>]"></i>
          <span>Existing plans</span>
        </div>
        <div class="text-xs text-slate-400">
          <?= count($rows) ?> plan<?= count($rows) === 1 ? '' : 's' ?>
        </div>
      </div>

      <?php if (!$rows): ?>
        <div class="px-4 py-6 text-center text-sm text-slate-400">
          No rate plans yet. Create your first plan on the left to get started.
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100">Name</th>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100 w-32">Code</th>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100 w-32">Currency</th>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100 w-40">Created</th>
                <th class="px-3 py-2 text-right font-semibold border-b border-slate-100 w-32">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $r):
                $id   = (int)($r['id'] ?? 0);
                $name = (string)($r['name'] ?? '');
                $code = (string)($r['code'] ?? '');
                $cur  = (string)($r['currency'] ?? '');
                $created = (string)($r['created_at'] ?? '');
            ?>
              <tr class="hover:bg-slate-50/70">
                <td class="px-3 py-2 align-middle">
                  <form method="post"
                        action="<?= $h($base) ?>/rates/rate-plans/<?= $id ?>/update"
                        class="space-y-1">
                    <?php if (function_exists('csrf_field')): ?>
                      <?= csrf_field() ?>
                    <?php endif; ?>
                    <input type="text" name="name"
                           value="<?= $h($name) ?>"
                           class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <input type="hidden" name="_inline" value="1">
                </td>
                <td class="px-3 py-2 align-middle">
                    <input type="text" name="code"
                           value="<?= $h($code) ?>"
                           class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </td>
                <td class="px-3 py-2 align-middle">
                    <select name="currency"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs
                                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                      <option value="" <?= $cur === '' ? 'selected' : '' ?>>Property default</option>
                      <option value="BDT" <?= $cur === 'BDT' ? 'selected' : '' ?>>BDT</option>
                      <option value="USD" <?= $cur === 'USD' ? 'selected' : '' ?>>USD</option>
                      <option value="EUR" <?= $cur === 'EUR' ? 'selected' : '' ?>>EUR</option>
                    </select>
                </td>
                <td class="px-3 py-2 align-middle text-xs text-slate-500">
                  <?= $created !== '' ? $h($created) : '<span class="text-slate-400">—</span>' ?>
                </td>
                <td class="px-3 py-2 align-middle text-right whitespace-nowrap space-x-1">
                  <button type="submit"
                          class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-100">
                    <i class="fa-solid fa-floppy-disk text-[10px]"></i>
                    Save
                  </button>
                  </form>

                  <form method="post"
                        action="<?= $h($base) ?>/rates/rate-plans/<?= $id ?>/delete"
                        class="inline-block"
                        onsubmit="return confirm('Delete this rate plan? This may impact mappings and ARI.');">
                    <?php if (function_exists('csrf_field')): ?>
                      <?= csrf_field() ?>
                    <?php endif; ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-[11px] font-medium text-rose-700 hover:bg-rose-50">
                      <i class="fa-regular fa-trash-can text-[10px]"></i>
                      Delete
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- Optional DDL helper -->
  <?php if ($ddlSql): ?>
    <details class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
      <summary class="cursor-pointer font-semibold text-slate-700 flex items-center gap-2">
        <i class="fa-solid fa-database text-slate-500"></i>
        Tech note: rate plans table (for admins)
      </summary>
      <p class="mt-2 mb-2 text-[11px] text-slate-500">
        If saving fails or this list is always empty, your database may be missing
        <code class="font-mono text-[11px]">hms_rate_plans</code>. A DBA can provision it with the SQL below.
      </p>
      <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 text-slate-100 p-3 text-[10px] leading-snug"><?= $h($ddlSql) ?></pre>
    </details>
  <?php endif; ?>

  <!-- How to use this page -->
  <section class="mt-2 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4">
    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
      <i class="fa-solid fa-circle-question text-slate-500"></i>
      How to use this page
    </h2>
    <ol class="mt-2 text-xs sm:text-sm text-slate-600 space-y-1.5 list-decimal list-inside">
      <li>
        <span class="font-medium">Create core plans</span> on the left:
        usually “BAR”, “Corporate”, “OTA”, and any long-stay or package offers you sell.
      </li>
      <li>
        Use a <span class="font-medium">short code</span> (BAR, CORP, OTA_BKG) to keep exports and
        integrations clean.
      </li>
      <li>
        Stick to <span class="font-medium">one main currency</span> (BDT for Bangladesh) unless you truly
        need multi-currency contracts.
      </li>
      <li>
        Maintain notes for each plan so new team members quickly understand
        <span class="font-medium">who it is for</span> (direct, OTA, corporate) and what it includes.
      </li>
      <li>
        After defining plans, move to <span class="font-medium">Availability, Overrides, Restrictions</span>
        and <span class="font-medium">Allotments</span> to fine-tune prices, seasons, LOS and channel rules.
      </li>
      <li>
        Review this list at least once per quarter to retire old campaigns and keep
        your ARI structure simple and “Klin”.
      </li>
    </ol>
  </section>

</div>