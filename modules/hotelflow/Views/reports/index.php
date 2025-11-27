<?php
/** @var string $title */
/** @var array  $groups */
/** @var string $today */
/** @var array  $ctx */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($ctx['module_base'] ?? '/apps/hotelflow'), '/');
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-[11px] text-slate-500 mb-1">
        HotelFlow · Analytics workspace
      </p>
      <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
        Reports &amp; analytics
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 text-[11px] border border-emerald-100">
          <i class="fa-regular fa-sparkles"></i>
          <span>2035-ready</span>
        </span>
      </h1>
      <p class="text-slate-500 text-sm mt-1">
        One hub for operations, revenue, accounting and audit reports — same data, different lenses.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/frontdesk"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-bell-concierge"></i>
        <span>Frontdesk</span>
      </a>
      <a href="<?= $h($base) ?>/accounting"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-bangladeshi-taka-sign"></i>
        <span>Accounting</span>
      </a>
      <a href="<?= $h($base) ?>/night-audit"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-moon"></i>
        <span>Night audit</span>
      </a>
    </div>
  </div>

  <!-- Quick filter pills (visual only for now) -->
  <div class="flex flex-wrap gap-2 text-[11px]">
    <button type="button"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-800 font-medium">
      <i class="fa-solid fa-table-cells-large"></i>
      <span>All reports</span>
    </button>
    <button type="button"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border border-slate-200 bg-white text-slate-700">
      <i class="fa-solid fa-bell-concierge"></i>
      <span>Frontdesk</span>
    </button>
    <button type="button"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border border-slate-200 bg-white text-slate-700">
      <i class="fa-solid fa-bangladeshi-taka-sign"></i>
      <span>Revenue</span>
    </button>
    <button type="button"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border border-slate-200 bg-white text-slate-700">
      <i class="fa-solid fa-share-nodes"></i>
      <span>Channels</span>
    </button>
    <button type="button"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border border-slate-200 bg-white text-slate-700">
      <i class="fa-solid fa-user-shield"></i>
      <span>Audit</span>
    </button>
  </div>

  <!-- Catalog: groups + cards -->
  <section class="space-y-6">
    <?php if (!empty($groups)): ?>
      <?php foreach ($groups as $group): ?>
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-sm font-semibold text-slate-900">
                <?= $h($group['label'] ?? 'Reports') ?>
              </h2>
              <p class="text-[11px] text-slate-500">
                Focused views for <?= $h(strtolower($group['label'] ?? 'this area')) ?>.
              </p>
            </div>
          </div>

          <div class="grid gap-3 md:grid-cols-3">
            <?php foreach ($group['reports'] as $r): ?>
              <a href="<?= $h($r['href'] ?? ($base.'/reports/'.$r['key'])) ?>"
                 class="group rounded-2xl border border-slate-200 bg-white p-4 flex flex-col justify-between hover:border-emerald-300 hover:shadow-sm transition">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-50 text-slate-600 text-[10px] border border-slate-200">
                      <span><?= $h($r['tag'] ?? 'Report') ?></span>
                    </div>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 group-hover:text-emerald-700">
                      <?= $h($r['name'] ?? 'Report') ?>
                    </h3>
                    <p class="mt-1 text-[11px] text-slate-500">
                      <?= $h($r['desc'] ?? '') ?>
                    </p>
                  </div>
                  <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-700">
                    <i class="<?= $h($r['icon'] ?? 'fa-regular fa-chart-bar') ?>"></i>
                  </div>
                </div>
                <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500">
                  <span><?= $h($today) ?></span>
                  <span class="inline-flex items-center gap-1 text-emerald-700 group-hover:gap-1.5 transition-all">
                    <span>Open report</span>
                    <i class="fa-solid fa-arrow-right-long text-[9px]"></i>
                  </span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
        Reports controller not loaded yet. Once you add it, this page will show your full analytics catalog.
      </div>
    <?php endif; ?>
  </section>

  <!-- How to use this page -->
  <section class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Reports page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>
        Start from the <strong>group titles</strong> (Frontdesk, Revenue, Channels, Accounting, HK, Audit) to decide
        which team or question you are answering.
      </li>
      <li>
        Inside each group, pick a <strong>report card</strong>. Each card explains what it shows and who it is for
        (Daily ops, Owner view, Audit, etc.).
      </li>
      <li>
        Click <strong>Open report</strong> to jump into the detail screen for that report
        (right now some reports are “coming soon” stubs until we wire the SQL).
      </li>
      <li>
        Use the tiny menu at the top (Frontdesk, Accounting, Night audit) to switch quickly between
        transactional screens and the analytics hub.
      </li>
      <li>
        When we go live with management reporting, this same page can host <strong>saved views</strong> for owners,
        GMs and auditors with their own default filters.
      </li>
    </ol>
    <p class="mt-1">
      Tip: Later we can add a <strong>date filter and export buttons</strong> on each report so you can send PDF / Excel
      to owners with one click after night audit.
    </p>
  </section>
</div>