<?php /** @var array $org */ ?>
<?php $title = $title ?? 'Report'; $module_base = $module_base ?? '/apps/dms'; ?>
<?php $asOf = $asOf ?? date('Y-m-d'); $level = $level ?? 3; ?>

<?php $print = ($_GET['print'] ?? '') === '1'; ?>
<?php if (!isset($shell)) $shell = 'tenant'; ?>

<div class="kf-report px-4 md:px-6 lg:px-8 py-5 text-slate-900 dark:text-slate-100">
  <div class="flex items-center justify-between gap-3 mb-4 print:hidden">
    <h1 class="text-xl md:text-2xl font-semibold"><?= htmlspecialchars($title) ?></h1>
    <div class="flex items-center gap-2">
      <form method="get" class="flex items-center gap-2">
        <label class="text-sm opacity-80">As of</label>
        <input type="date" name="as_of" value="<?= htmlspecialchars($asOf) ?>"
               class="kf-input" />
        <?php if (isset($level)): ?>
          <label class="text-sm opacity-80">Level</label>
          <select name="level" class="kf-input">
            <?php for ($i=1; $i<=6; $i++): ?>
              <option value="<?= $i ?>" <?= $level==$i?'selected':'' ?>><?= $i ?></option>
            <?php endfor; ?>
          </select>
        <?php endif; ?>
        <?php if (isset($showZero)): ?>
          <label class="inline-flex items-center gap-1 text-sm"><input type="checkbox" name="zero" value="1" <?= $showZero?'checked':'' ?> /> Show zero</label>
        <?php endif; ?>
        <button class="kf-btn">Apply</button>
      </form>
      <a href="?<?= http_build_query(array_merge($_GET,['print'=>'1'])) ?>"
         class="kf-btn kf-btn-secondary">Print</a>
    </div>
  </div>

  <div class="print:!p-0 print:!m-0">
    <?= $content ?? '' ?>
  </div>
</div>

<style>
  .kf-input{border:1px solid var(--tw-slate-300,#CBD5E1);background:transparent;border-radius:.5rem;padding:.4rem .6rem}
  .dark .kf-input{border-color:#334155;color:#E2E8F0}
  .kf-btn{background:#111827;color:#fff;border-radius:.5rem;padding:.45rem .8rem}
  .kf-btn-secondary{background:#1118270D;color:#111827;border:1px solid #11182726}
  @media print{.print\:hidden{display:none!important} body{background:#fff}}
</style>

<?php if ($print): ?>
<script>window.addEventListener('load',()=>{ setTimeout(()=>window.print(), 150); });</script>
<?php endif; ?>