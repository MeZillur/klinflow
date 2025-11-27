<?php
/** @var string      $module_base */
/** @var array<int,array> $rows */
/** @var array<string,string> $ddl */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$filterType = trim((string)($_GET['rule_type'] ?? ''));
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- SEGMENT 1: Header + mini menu -->
  <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Yield Rules</h1>
      <p class="text-sm text-slate-500">
        Smart rules that auto-adjust prices based on occupancy, pickup or period — your mini revenue manager.
      </p>
    </div>

    <!-- Tiny rates navigation -->
    <nav class="flex flex-wrap gap-1 text-xs">
      <a href="<?= $h($base) ?>/rates"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Summary
      </a>
      <a href="<?= $h($base) ?>/rates/availability"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Availability
      </a>
      <a href="<?= $h($base) ?>/rates/rate-plans"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Rate Plans
      </a>
      <a href="<?= $h($base) ?>/rates/overrides"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Overrides
      </a>
      <a href="<?= $h($base) ?>/rates/restrictions"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Restrictions
      </a>
      <a href="<?= $h($base) ?>/rates/allotments"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Allotments
      </a>
      <a href="<?= $h($base) ?>/rates/yield-rules"
         class="px-3 py-1.5 rounded-full border border-emerald-500 text-emerald-700 bg-emerald-50">
        Yield Rules
      </a>
    </nav>
  </div>

  <!-- SEGMENT 2: Filter + add rule CTA -->
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <form method="get" class="flex flex-wrap gap-2 items-center">
      <input type="hidden" name="first" value="rates">
      <input type="hidden" name="second" value="yield-rules">

      <label class="text-xs text-slate-500">
        Rule type
      </label>
      <select name="rule_type"
              class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
        <option value="">All</option>
        <option value="occupancy" <?= $filterType==='occupancy'?'selected':'' ?>>Occupancy based</option>
        <option value="pickup"    <?= $filterType==='pickup'?'selected':'' ?>>Pickup based</option>
        <option value="period"    <?= $filterType==='period'?'selected':'' ?>>Period / seasonal</option>
      </select>

      <button type="submit"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-50">
        <i class="fa-solid fa-filter text-slate-500"></i>
        <span>Apply</span>
      </button>
    </form>

    <button type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold text-white shadow-sm hover:shadow-md transition"
            style="background:var(--brand)">
      <i class="fa-solid fa-wand-magic-sparkles"></i>
      <span>New yield rule (coming soon)</span>
    </button>
  </div>

  <!-- SEGMENT 3: Yield rules list -->
  <div class="grid gap-4 md:grid-cols-2">
    <?php if (empty($rows)): ?>
      <div class="md:col-span-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-sm text-slate-600">
        <div class="font-semibold text-slate-800 mb-1">No yield rules configured yet.</div>
        <p class="text-xs text-slate-500">
          Start by defining simple rules like: “If occupancy &gt; 80% then increase BAR by 500 BDT” or
          “If pickup in last 3 days is slow, reduce rate by 5%”.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($rows as $row):
        $id        = (int)($row['id'] ?? 0);
        $name      = (string)($row['name'] ?? '');
        $ruleType  = (string)($row['rule_type'] ?? '');
        $threshold = (float)($row['threshold'] ?? 0);
        $actionRaw = (string)($row['action_json'] ?? '');
        $actionArr = null;
        if ($actionRaw !== '') {
            $decoded = json_decode($actionRaw, true);
            if (is_array($decoded)) {
                $actionArr = $decoded;
            }
        }

        $typeLabel = match ($ruleType) {
          'occupancy' => 'Occupancy based',
          'pickup'    => 'Pickup based',
          'period'    => 'Period / seasonal',
          default     => ($ruleType !== '' ? ucfirst($ruleType) : 'Uncategorised'),
        };

        $tagCls = 'bg-slate-100 text-slate-700';
        if ($ruleType === 'occupancy') $tagCls = 'bg-emerald-50 text-emerald-700';
        if ($ruleType === 'pickup')    $tagCls = 'bg-sky-50 text-sky-700';
        if ($ruleType === 'period')    $tagCls = 'bg-amber-50 text-amber-700';
      ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col gap-2">
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="text-sm font-semibold text-slate-900">
                <?= $h($name !== '' ? $name : ('Rule #'.$id)) ?>
              </div>
              <div class="mt-1 inline-flex items-center gap-1">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium <?= $tagCls ?>">
                  <i class="fa-solid fa-gauge-high mr-1 text-[10px]"></i>
                  <?= $h($typeLabel) ?>
                </span>
                <span class="text-[11px] text-slate-500">
                  Threshold: <?= number_format($threshold, 2) ?>
                </span>
              </div>
            </div>

            <div class="flex flex-col items-end gap-1 text-[11px] text-slate-400">
              <button type="button"
                      class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 text-[11px] text-slate-600 hover:bg-slate-50">
                <i class="fa-regular fa-pen-to-square"></i>
                Edit
              </button>
              <button type="button"
                      class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-rose-200 text-[11px] text-rose-600 hover:bg-rose-50">
                <i class="fa-regular fa-trash-can"></i>
                Delete
              </button>
            </div>
          </div>

          <div class="text-xs text-slate-600 mt-1">
            <?php if ($actionArr): ?>
              <div class="font-semibold text-slate-800 mb-1">Action</div>
              <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($actionArr as $k => $v): ?>
                  <li><span class="font-medium"><?= $h((string)$k) ?>:</span> <?= $h(is_scalar($v) ? (string)$v : json_encode($v)) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php elseif ($actionRaw !== ''): ?>
              <div class="font-semibold text-slate-800 mb-1">Action</div>
              <pre class="text-[11px] bg-slate-50 rounded-lg p-2 border border-slate-200 overflow-x-auto"><?= $h($actionRaw) ?></pre>
            <?php else: ?>
              <span class="text-[11px] text-slate-400">
                No action definition saved yet. This rule is incomplete.
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- SEGMENT 4: How to use this page -->
  <div class="mt-2 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-xs text-slate-600 space-y-2">
    <div class="font-semibold text-slate-800 flex items-center gap-2">
      <i class="fa-solid fa-circle-info text-emerald-600"></i>
      <span>How to use the Yield Rules page</span>
    </div>
    <ul class="list-disc list-inside space-y-1">
      <li><strong>Think strategy first:</strong> Decide your basic rules: when occupancy is high, increase rates; when pickup is slow, offer discounts.</li>
      <li><strong>Keep rules simple:</strong> Start with 1–2 rules only (e.g. “>80% occupancy → +500 BDT”). Too many rules = noisy pricing.</li>
      <li><strong>Combine with ARI:</strong> Yield rules sit on top of base rates, overrides and restrictions to generate final sell rates.</li>
      <li><strong>Monitor results:</strong> Track how rules impact occupancy and ADR, and refine thresholds over time.</li>
    </ul>
    <p class="pt-1 text-[11px] text-slate-500">
      Later we can connect these rules to a background job & channel manager so that OTA/website prices update automatically.
    </p>
  </div>
</div>