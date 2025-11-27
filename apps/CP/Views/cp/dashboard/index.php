<?php
// apps/CP/Views/cp/dashboard/index.php
// Content-only view. Do NOT include any shell here.
// The controller supplies $kpi, $trendLabels, $trendValues, $orgs, $filters, $pagination.

$h         = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$money     = fn($n)=>'৳'.number_format((float)$n, 2);
$filters   = $filters    ?? ['q'=>'','status'=>'','plan'=>''];
$pagination= $pagination ?? ['page'=>1,'perPage'=>10,'total'=>0,'totalPages'=>1];
$brandColor= '#228B22';

/**
 * Normalize a raw module key to a friendly name.
 */
$prettyModule = function(string $key): string {
    $k = strtolower(trim($key));
    $map = [
        'pos'        => 'Retail POS',
        'retailpos'  => 'Retail POS',
        'hotel'      => 'HotelFlow',
        'hotelflow'  => 'HotelFlow',
        'bhata'      => 'Bhata',
        'school'     => 'School',
        'med'        => 'MedFlow',
        'medflow'    => 'MedFlow',
        'dms'        => 'DMS',
        'docs'       => 'DMS',
        'accounting' => 'Accounting',
        'gl'         => 'GL',
    ];
    if (isset($map[$k])) return $map[$k];
    $k = preg_replace('/[_\-]+/',' ', $k);
    return ucwords($k);
};

/**
 * Extract raw module keys for an org, then convert to friendly names.
 */
$modules_for_org = function(array $o) use ($prettyModule): array {
    $out = [];

    // 1) Direct list in 'modules'
    if (!empty($o['modules'])) {
        if (is_array($o['modules'])) {
            foreach ($o['modules'] as $item) {
                $key = is_array($item) ? ($item['key'] ?? $item['name'] ?? '') : (string)$item;
                if ($key !== '') $out[] = $key;
            }
        } elseif (is_string($o['modules'])) {
            $out = array_map('trim', explode(',', $o['modules']));
        }
    }

    // 2) enabled_modules as assoc
    if (empty($out) && !empty($o['enabled_modules']) && is_array($o['enabled_modules'])) {
        foreach ($o['enabled_modules'] as $k => $v) {
            if ($v) $out[] = $k;
        }
    }

    // 3) 'apps' variants
    if (empty($out) && !empty($o['apps'])) {
        if (is_string($o['apps'])) {
            $out = array_map('trim', explode(',', $o['apps']));
        } elseif (is_array($o['apps'])) {
            foreach ($o['apps'] as $a) {
                if (is_array($a)) {
                    $k = $a['key'] ?? $a['module'] ?? $a['name'] ?? '';
                    if ($k !== '') $out[] = $k;
                } else {
                    $out[] = (string)$a;
                }
            }
        }
    }

    // 4) last chance: boolean flags like pos_enabled, dms_enabled
    if (empty($out)) {
        foreach ($o as $k => $v) {
            if (is_bool($v) && $v === true && preg_match('/^([a-z0-9]+)_enabled$/i', (string)$k, $m)) {
                $out[] = $m[1];
            }
        }
    }

    // Dedup + prettify
    $out = array_values(array_unique(array_filter(array_map('strval', $out))));
    $out = array_map($prettyModule, $out);

    return $out;
};

/**
 * Rich info per module (friendly name, icon, module path key).
 */
$moduleMeta = function(string $friendlyName): array {
    $map = [
        'Retail POS' => ['icon' => 'fa-cash-register',           'key' => 'pos'],
        'HotelFlow'  => ['icon' => 'fa-hotel',                   'key' => 'hotelflow'],
        'Bhata'      => ['icon' => 'fa-route',                   'key' => 'bhata'],
        'School'     => ['icon' => 'fa-school',                  'key' => 'school'],
        'MedFlow'    => ['icon' => 'fa-notes-medical',           'key' => 'medflow'],
        'DMS'        => ['icon' => 'fa-truck-ramp-box',          'key' => 'dms'],
        'Accounting' => ['icon' => 'fa-file-invoice-dollar',     'key' => 'accounting'],
        'GL'         => ['icon' => 'fa-scale-balanced',          'key' => 'gl'],
    ];
    $meta = $map[$friendlyName] ?? null;
    if ($meta === null) {
        $meta = ['icon' => 'fa-layer-group', 'key' => strtolower(str_replace(' ', '', $friendlyName))];
    }
    return $meta + ['label' => $friendlyName];
};

/**
 * Aggregate module usage across all orgs.
 */
$moduleUsage = [];
if (!empty($orgs) && is_array($orgs)) {
    foreach ($orgs as $o) {
        $mods = $modules_for_org((array)$o);
        foreach ($mods as $m) {
            $moduleUsage[$m] = ($moduleUsage[$m] ?? 0) + 1;
        }
    }
}
arsort($moduleUsage);
$topModuleUsage = array_slice($moduleUsage, 0, 5, true);

$totalOrgs = (int)($kpi['total_orgs'] ?? ($pagination['total'] ?? (is_array($orgs ?? null) ? count($orgs) : 0)));
?>
<div class="space-y-6">

  <!-- HERO / HEADER -->
  <section class="relative overflow-hidden rounded-3xl border bg-gradient-to-tr from-emerald-600 to-emerald-500 text-white px-5 py-6 sm:px-8 sm:py-7">
    <div class="absolute inset-0 pointer-events-none opacity-20"
         style="background-image: radial-gradient(circle at 0 0, rgba(255,255,255,0.3), transparent 50%), radial-gradient(circle at 100% 100%, rgba(16,185,129,0.6), transparent 55%);">
    </div>

    <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.15em] opacity-90">
          <span class="inline-flex h-2 w-2 rounded-full bg-emerald-200 animate-pulse"></span>
          Platform Control Panel
        </div>
        <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight">
          Owner Dashboard
        </h1>
        <p class="mt-1 text-sm sm:text-base text-emerald-100 max-w-xl">
          Monitor all tenants, see which modules are active per organization, and jump into any workspace in one click.
        </p>
      </div>

      <!-- Quick CP shortcuts -->
      <div class="bg-white/10 border border-white/20 rounded-2xl px-4 py-3 sm:px-5 sm:py-4 backdrop-blur flex flex-col gap-2 min-w-[220px]">
        <div class="text-xs font-medium uppercase tracking-[0.16em] text-emerald-50">
          CP Shortcuts
        </div>
        <div class="flex flex-wrap gap-2 text-xs sm:text-[13px]">
          <a href="/cp/organizations" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 hover:bg-white/25 transition">
            <i class="fa-solid fa-building text-emerald-50"></i><span>Organizations</span>
          </a>
          <a href="/cp/users" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 hover:bg-white/25 transition">
            <i class="fa-solid fa-user-shield text-emerald-50"></i><span>CP Users</span>
          </a>
          <a href="/cp/maintenance" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 hover:bg-white/25 transition">
            <i class="fa-solid fa-screwdriver-wrench text-emerald-50"></i><span>Maintenance</span>
          </a>
          <a href="/cp/password" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 hover:bg-white/25 transition">
            <i class="fa-solid fa-key text-emerald-50"></i><span>Change Password</span>
          </a>
        </div>
        <div class="flex justify-end">
          <a href="/cp/organizations/create"
             class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-white text-emerald-700 px-3 py-1.5 text-xs font-medium hover:bg-emerald-50 transition">
            <i class="fa-solid fa-plus"></i><span>New Organization</span>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- FILTER ROW -->
  <section class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
      <h2 class="text-sm font-semibold text-slate-800 tracking-wide flex items-center gap-2">
        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
        Tenants overview
      </h2>
      <p class="mt-1 text-xs text-slate-500">
        Use search and filters to quickly locate an organization. Module badges below show active apps per tenant.
      </p>
    </div>

    <form method="get" action="/cp/dashboard"
          class="w-full lg:w-auto grid grid-cols-2 sm:grid-cols-5 gap-2">
      <div class="col-span-2 sm:col-span-2">
        <input type="text" name="q" value="<?= $h($filters['q']) ?>" placeholder="Search orgs by name / slug…"
               class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-[<?= $brandColor ?>] focus:border-[<?= $brandColor ?>]" />
      </div>
      <div class="col-span-1">
        <select name="status"
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[<?= $brandColor ?>] focus:border-[<?= $brandColor ?>]">
          <option value="">All status</option>
          <?php foreach (['active','trial','suspended','past_due','inactive'] as $st): ?>
            <option value="<?= $h($st) ?>" <?= $filters['status']===$st?'selected':'' ?>>
              <?= $h(ucfirst(str_replace('_',' ',$st))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-span-1">
        <select name="plan"
                class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-xs sm:text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[<?= $brandColor ?>] focus:border-[<?= $brandColor ?>]">
          <option value="">All plans</option>
          <?php foreach (['free','starter','pro','enterprise'] as $pl): ?>
            <option value="<?= $h($pl) ?>" <?= $filters['plan']===$pl?'selected':'' ?>>
              <?= $h(ucfirst($pl)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-span-2 sm:col-span-1 flex gap-2">
        <button type="submit"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-[<?= $brandColor ?>] text-white text-xs sm:text-sm font-medium shadow-sm hover:bg-emerald-700 transition">
          <i class="fa-solid fa-filter"></i><span>Apply</span>
        </button>
        <a href="/cp/dashboard"
           class="hidden sm:inline-flex items-center justify-center px-3 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm text-slate-600 hover:bg-slate-50 transition">
          Reset
        </a>
      </div>
    </form>
  </section>

  <!-- KPIs + TREND + MODULE USAGE -->
  <section class="grid grid-cols-1 xl:grid-cols-4 gap-4">
    <!-- KPI cards -->
    <div class="xl:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="rounded-2xl border border-slate-100 bg-white shadow-sm/50 px-4 py-3">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">Active orgs</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">
              <?= number_format((int)($kpi['active_orgs'] ?? 0)) ?>
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-emerald-50 flex items-center justify-center">
            <i class="fa-solid fa-circle-nodes text-emerald-600 text-sm"></i>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white shadow-sm/50 px-4 py-3">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">Past due / Susp.</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">
              <?= number_format((int)($kpi['past_due'] ?? 0)) ?>
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-rose-50 flex items-center justify-center">
            <i class="fa-solid fa-triangle-exclamation text-rose-500 text-sm"></i>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white shadow-sm/50 px-4 py-3">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">New last 7 days</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">
              <?= number_format((int)($kpi['new_7d'] ?? 0)) ?>
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-sky-50 flex items-center justify-center">
            <i class="fa-solid fa-sparkles text-sky-500 text-sm"></i>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-100 bg-white shadow-sm/50 px-4 py-3">
        <div class="flex items-center justify-between gap-2">
          <div>
            <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">MRR (active + trial)</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">
              <?= $money($kpi['mrr'] ?? 0) ?>
            </div>
          </div>
          <div class="h-9 w-9 rounded-2xl bg-amber-50 flex items-center justify-center">
            <i class="fa-solid fa-coins text-amber-500 text-sm"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Trend -->
    <div class="xl:col-span-1 rounded-2xl border border-slate-100 bg-white shadow-sm/50 px-4 py-3">
      <div class="flex items-center justify-between gap-2">
        <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">
          New orgs per period
        </div>
        <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
          Trend
        </span>
      </div>
      <div class="mt-3 h-32">
        <canvas id="orgTrend" class="w-full h-full"></canvas>
      </div>
    </div>

    <!-- Module usage summary -->
    <div class="xl:col-span-1 rounded-2xl border border-slate-100 bg-white shadow-sm/50 px-4 py-3 flex flex-col">
      <div class="flex items-center justify-between gap-2 mb-2">
        <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">
          Module usage across orgs
        </div>
        <span class="text-[10px] text-slate-400">
          Total orgs: <?= number_format($totalOrgs) ?>
        </span>
      </div>
      <?php if (!empty($topModuleUsage)): ?>
        <ul class="mt-1 space-y-1.5 text-xs">
          <?php foreach ($topModuleUsage as $friendly => $cnt): ?>
            <?php $meta = $moduleMeta($friendly); ?>
            <li class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <span class="inline-flex h-7 w-7 rounded-full bg-emerald-50 text-emerald-700 items-center justify-center">
                  <i class="fa-solid <?= $h($meta['icon']) ?> text-[11px]"></i>
                </span>
                <span class="text-slate-700"><?= $h($friendly) ?></span>
              </div>
              <div class="flex items-center gap-1 text-[11px] text-slate-500">
                <span class="px-2 py-0.5 rounded-full bg-slate-50 border border-slate-100">
                  <?= number_format($cnt) ?> orgs
                </span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="mt-2 text-xs text-slate-400">No module data yet. Add modules to organizations to see usage here.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- ORGANIZATIONS GRID / LIST -->
  <section class="space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-semibold text-slate-800 tracking-wide">
          Organizations (<?= number_format($pagination['total'] ?? $totalOrgs) ?>)
        </h2>
        <a href="/cp/organizations"
           class="inline-flex items-center gap-1.5 text-[11px] text-slate-500 hover:text-slate-700">
          <span>View all</span><i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
        </a>
      </div>

      <!-- Layout toggle -->
      <div class="inline-flex items-center self-start sm:self-auto rounded-xl border border-slate-200 bg-white p-0.5 text-[11px]" id="orgViewToggle">
        <button type="button" id="btnOrgGrid"
                class="px-2.5 py-1.5 rounded-lg flex items-center gap-1 text-emerald-700 bg-emerald-50 border border-emerald-100 shadow-sm/30">
          <i class="fa-solid fa-border-all text-[11px]"></i><span>Grid</span>
        </button>
        <button type="button" id="btnOrgList"
                class="px-2.5 py-1.5 rounded-lg flex items-center gap-1 text-slate-500">
          <i class="fa-solid fa-list text-[11px]"></i><span>List</span>
        </button>
      </div>
    </div>

    <?php if (!empty($orgs)): ?>

      <!-- GRID VIEW -->
      <div id="orgGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <?php foreach ($orgs as $o): ?>
          <?php
            $o    = (array)$o;
            $id   = (int)($o['id'] ?? 0);
            $slug = (string)($o['slug'] ?? '');
            $plan = (string)($o['plan'] ?? '');
            $status = (string)($o['status'] ?? '');
            $mods = $modules_for_org($o);
            $statusClass = 'bg-slate-100 text-slate-700 border-slate-200';
            if ($status === 'active') $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            elseif ($status === 'trial') $statusClass = 'bg-sky-50 text-sky-700 border-sky-200';
            elseif (in_array($status, ['suspended','past_due'], true)) $statusClass = 'bg-rose-50 text-rose-700 border-rose-200';

            $tenantDashboardUrl = $slug !== '' ? "/t/{$slug}/dashboard" : '';
          ?>
          <article class="rounded-2xl border border-slate-100 bg-white shadow-sm/40 px-4 py-3.5 flex flex-col gap-3">
            <!-- Top row: name + status/plan -->
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-semibold text-slate-900 truncate">
                    <?= $h($o['name'] ?? '') ?>
                  </h3>
                  <?php if ($plan !== ''): ?>
                    <span class="inline-flex items-center rounded-full bg-slate-50 border border-slate-200 px-2 py-0.5 text-[11px] text-slate-600">
                      <i class="fa-solid fa-layer-group mr-1"></i><?= $h(ucfirst($plan)) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-1 text-[11px] text-slate-500">
                  <?php if ($slug !== ''): ?>
                    <span class="inline-flex items-center gap-1">
                      <i class="fa-solid fa-link"></i><code class="text-[11px]"><?= $h($slug) ?></code>
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($o['created_at'])): ?>
                    <span class="inline-flex items-center gap-1">
                      <span class="mx-1 text-slate-300">•</span>
                      <i class="fa-regular fa-clock"></i>
                      <span><?= $h((string)$o['created_at']) ?></span>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="flex flex-col items-end gap-1">
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] <?= $statusClass ?>">
                  <span class="h-1.5 w-1.5 rounded-full mr-1.5 <?= $status === 'active' ? 'bg-emerald-500' : ($status === 'trial' ? 'bg-sky-500' : 'bg-slate-400') ?>"></span>
                  <?= $h(str_replace('_',' ', $status ?: 'unknown')) ?>
                </span>
                <?php if (isset($o['monthly_price'])): ?>
                  <span class="text-[11px] text-slate-500">
                    <?= $money($o['monthly_price']) ?>/mo
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Active modules section -->
            <div class="border-t border-dashed border-slate-100 pt-2">
              <div class="flex items-center justify-between gap-2 mb-1.5">
                <span class="text-[11px] uppercase tracking-[0.16em] text-slate-500">
                  Active modules
                </span>
                <?php if ($mods): ?>
                  <span class="text-[10px] text-slate-400">
                    <?= count($mods) ?> module<?= count($mods) !== 1 ? 's' : '' ?>
                  </span>
                <?php endif; ?>
              </div>
              <?php if ($mods): ?>
                <div class="flex flex-wrap gap-1.5">
                  <?php
                    $maxBadges = 6;
                    $shown = 0;
                    foreach ($mods as $mname):
                      $shown++;
                      if ($shown > $maxBadges) break;
                      $meta = $moduleMeta($mname);
                      $modPathKey = $meta['key'] ?? '';
                      $modHref = ($slug !== '' && $modPathKey !== '') ? "/t/{$slug}/apps/{$modPathKey}" : '';
                  ?>
                    <?php if ($modHref): ?>
                      <a href="<?= $h($modHref) ?>" target="_blank"
                         class="group inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] text-emerald-700 hover:bg-emerald-600 hover:text-white transition">
                        <i class="fa-solid <?= $h($meta['icon']) ?> text-[10px]"></i>
                        <span><?= $h($meta['label']) ?></span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px] opacity-60 group-hover:opacity-100"></i>
                      </a>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-700">
                        <i class="fa-solid <?= $h($meta['icon']) ?> text-[10px]"></i>
                        <span><?= $h($meta['label']) ?></span>
                      </span>
                    <?php endif; ?>
                  <?php endforeach; ?>

                  <?php if (count($mods) > $maxBadges): ?>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-500">
                      +<?= count($mods) - $maxBadges ?> more
                    </span>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <p class="text-[11px] text-slate-400">
                  No modules attached yet. Configure in organization settings.
                </p>
              <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between gap-2 pt-1">
              <div class="flex flex-wrap gap-1.5 text-[11px]">
                <?php if (!empty($tenantDashboardUrl)): ?>
                  <a href="<?= $h($tenantDashboardUrl) ?>" target="_blank"
                     class="inline-flex items-center gap-1.5 text-emerald-700 hover:text-emerald-800">
                    <i class="fa-solid fa-gauge-high text-[11px]"></i><span>Tenant dashboard</span>
                  </a>
                <?php endif; ?>
              </div>
              <div class="flex gap-1.5">
                <!-- Add Branch button -->
                <a href="/cp/organizations/<?= $h((string)$id) ?>/branches/create"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[11px] border border-emerald-200 text-emerald-700 hover:bg-emerald-50">
                  <i class="fa-solid fa-code-branch text-[10px]"></i><span>Add branch</span>
                </a>

                <!-- Edit button -->
                <a href="/cp/organizations/<?= $h((string)$id) ?>/edit"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[11px] border border-slate-200 text-slate-700 hover:bg-slate-50">
                  <i class="fa-solid fa-pen-to-square text-[10px]"></i><span>Edit</span>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- LIST VIEW -->
      <div id="orgList" class="hidden rounded-2xl border border-slate-100 bg-white shadow-sm/40 overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-100 text-xs font-medium text-slate-600 flex items-center justify-between">
          <span>Organizations list</span>
          <span class="text-[11px] text-slate-400">Switch back to grid for cards</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 text-slate-500">
              <tr>
                <th class="py-2.5 px-4 text-left font-medium">Name</th>
                <th class="py-2.5 px-4 text-left font-medium hidden sm:table-cell">Slug</th>
                <th class="py-2.5 px-4 text-left font-medium hidden md:table-cell">Plan</th>
                <th class="py-2.5 px-4 text-left font-medium">Status</th>
                <th class="py-2.5 px-4 text-left font-medium hidden lg:table-cell">Modules</th>
                <th class="py-2.5 px-4 text-right font-medium hidden sm:table-cell">Price</th>
                <th class="py-2.5 px-4 text-left font-medium hidden md:table-cell">Created</th>
                <th class="py-2.5 px-4 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($orgs as $o): ?>
                <?php
                  $o    = (array)$o;
                  $id   = (int)($o['id'] ?? 0);
                  $slug = (string)($o['slug'] ?? '');
                  $plan = (string)($o['plan'] ?? '');
                  $status = (string)($o['status'] ?? '');
                  $mods = $modules_for_org($o);
                  $statusClass = 'bg-slate-100 text-slate-700';
                  if ($status === 'active') $statusClass = 'bg-emerald-50 text-emerald-700';
                  elseif ($status === 'trial') $statusClass = 'bg-sky-50 text-sky-700';
                  elseif (in_array($status, ['suspended','past_due'], true)) $statusClass = 'bg-rose-50 text-rose-700';

                  $tenantDashboardUrl = $slug !== '' ? "/t/{$slug}/dashboard" : '';
                ?>
                <tr>
                  <td class="py-2.5 px-4 align-top">
                    <div class="font-medium text-slate-900"><?= $h($o['name'] ?? '') ?></div>
                    <div class="mt-0.5 text-[11px] text-slate-500 sm:hidden">
                      <?php if ($slug !== ''): ?>
                        <span class="inline-flex items-center gap-1">
                          <i class="fa-solid fa-link"></i><code><?= $h($slug) ?></code>
                        </span>
                      <?php endif; ?>
                      <?php if ($plan !== ''): ?>
                        <span class="mx-1 text-slate-300">•</span><?= $h(ucfirst($plan)) ?>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="py-2.5 px-4 align-top hidden sm:table-cell">
                    <?php if ($slug !== ''): ?>
                      <code class="text-[12px]"><?= $h($slug) ?></code>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-4 align-top hidden md:table-cell">
                    <?= $h(ucfirst($plan)) ?>
                  </td>
                  <td class="py-2.5 px-4 align-top">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] <?= $statusClass ?>">
                      <?= $h(str_replace('_',' ', $status ?: 'unknown')) ?>
                    </span>
                  </td>
                  <td class="py-2.5 px-4 align-top hidden lg:table-cell">
                    <?php if ($mods): ?>
                      <div class="flex flex-wrap gap-1">
                        <?php foreach ($mods as $mname): ?>
                          <?php $meta = $moduleMeta($mname); ?>
                          <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-[11px]">
                            <i class="fa-solid <?= $h($meta['icon']) ?> text-[10px]"></i>
                            <span><?= $h($meta['label']) ?></span>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-[11px] text-slate-400">None</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-4 align-top text-right hidden sm:table-cell">
                    <?php if (isset($o['monthly_price'])): ?>
                      <?= $money($o['monthly_price']) ?>/mo
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-4 align-top hidden md:table-cell">
                    <?= $h((string)($o['created_at'] ?? '')) ?>
                  </td>
                  <td class="py-2.5 px-4 align-top text-right whitespace-nowrap">
                    <!-- Add Branch (list view) -->
                    <a href="/cp/organizations/<?= $h((string)$id) ?>/branches/create"
                       class="inline-flex items-center gap-1 text-[11px] text-emerald-700 hover:text-emerald-900 mr-2">
                      <i class="fa-solid fa-code-branch text-[10px]"></i><span>Add branch</span>
                    </a>

                    <!-- Edit (list view) -->
                    <a href="/cp/organizations/<?= $h((string)$id) ?>/edit"
                       class="inline-flex items-center gap-1 text-[11px] text-slate-600 hover:text-slate-900">
                      <i class="fa-solid fa-pen-to-square text-[10px]"></i><span>Edit</span>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-6 text-center text-sm text-slate-500">
        No organizations found. <a href="/cp/organizations/create" class="text-emerald-700 font-medium hover:underline">Create the first organization</a>.
      </div>
    <?php endif; ?>

    <!-- Pagination -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs sm:text-sm text-slate-600">
      <div>
        Page <?= (int)$pagination['page'] ?> of <?= (int)$pagination['totalPages'] ?>,
        <?= (int)$pagination['total'] ?> total
      </div>
      <div class="inline-flex items-center gap-1">
        <?php
          $build = function($p) use ($filters, $pagination) {
            $p = max(1, min((int)$pagination['totalPages'], (int)$p));
            $qs = http_build_query([
              'q'     => $filters['q'],
              'status'=> $filters['status'],
              'plan'  => $filters['plan'],
              'page'  => $p,
              'per'   => $pagination['perPage']
            ]);
            return '/cp/dashboard?'.$qs;
          };
          $p = (int)$pagination['page'];
        ?>
        <a class="px-3 py-1.5 rounded-xl border border-slate-200 <?= $p<=1?'opacity-50 pointer-events-none':'' ?>"
           href="<?= $build($p-1) ?>">Prev</a>
        <a class="px-3 py-1.5 rounded-xl border border-slate-200 <?= $p>=$pagination['totalPages']?'opacity-50 pointer-events-none':'' ?>"
           href="<?= $build($p+1) ?>">Next</a>
      </div>
    </div>
  </section>
</div>

<!-- Chart.js (only if we have data) -->
<?php if (!empty($trendLabels)): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
    (function(){
      const el = document.getElementById('orgTrend');
      if (!el) return;
      const labels = <?= json_encode(array_values($trendLabels)) ?>;
      const values = <?= json_encode(array_values($trendValues)) ?>;
      new Chart(el, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'New orgs',
            data: values,
            borderWidth: 2,
            tension: .35,
            fill: false
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: {
              ticks: { color: '#64748b', font: { size: 10 } },
              grid: { display: false }
            },
            y: {
              beginAtZero: true,
              ticks: { color: '#94a3b8', font: { size: 10 }, precision: 0 },
              grid: { color: 'rgba(148,163,184,0.15)' }
            }
          },
          elements: {
            point: { radius: 2, hoverRadius: 3 }
          }
        }
      });
      el.parentElement.style.minHeight = '130px';
    })();
  </script>
<?php endif; ?>

<!-- Layout toggle JS (Grid/List, default = grid, persisted) -->
<script>
  (function(){
    const gridBtn = document.getElementById('btnOrgGrid');
    const listBtn = document.getElementById('btnOrgList');
    const grid    = document.getElementById('orgGrid');
    const list    = document.getElementById('orgList');
    if (!gridBtn || !listBtn || !grid || !list) return;

    function setMode(mode) {
      const isGrid = mode === 'grid';
      grid.classList.toggle('hidden', !isGrid);
      list.classList.toggle('hidden', isGrid);

      // button styles
      if (isGrid) {
        gridBtn.classList.add('bg-emerald-50','text-emerald-700','border','border-emerald-100','shadow-sm');
        listBtn.classList.remove('bg-emerald-50','text-emerald-700','border','border-emerald-100','shadow-sm');
        listBtn.classList.add('text-slate-500');
      } else {
        listBtn.classList.add('bg-emerald-50','text-emerald-700','border','border-emerald-100','shadow-sm');
        gridBtn.classList.remove('bg-emerald-50','text-emerald-700','border','border-emerald-100','shadow-sm');
        gridBtn.classList.add('text-slate-500');
      }

      try { localStorage.setItem('cp_org_view', mode); } catch (e) {}
    }

    gridBtn.addEventListener('click', function(){ setMode('grid'); });
    listBtn.addEventListener('click', function(){ setMode('list'); });

    // initial mode from localStorage
    let initial = 'grid';
    try {
      const stored = localStorage.getItem('cp_org_view');
      if (stored === 'list') initial = 'list';
    } catch (e) {}
    setMode(initial);
  })();
</script>