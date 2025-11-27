<?php
/**
 * @var array $filters [
 *   'as_of' => 'YYYY-MM-DD' | null,
 *   'from'  => 'YYYY-MM-DD' | null,
 *   'to'    => 'YYYY-MM-DD' | null,
 *   'level' => 1..6 | null,
 *   'show_zero' => bool|null,
 * ]
 * @var string $applyAction  POST to same route or GET with querystring—your controllers can decide
 * @var array  $extras       Additional right-side controls (rendered raw HTML buttons/links)
 */
$filters   = $filters   ?? [];
$applyAction = $applyAction ?? '';
$extras    = $extras    ?? [];
?>
<form method="get" action="<?= htmlspecialchars($applyAction ?: ($_SERVER['REQUEST_URI'] ?? '')) ?>"
      class="flex flex-wrap items-center gap-2">
  <?php if (isset($filters['as_of'])): ?>
    <label class="text-sm opacity-70">As of</label>
    <input type="date" name="as_of" value="<?= htmlspecialchars($filters['as_of']) ?>"
           class="rounded-md px-2 py-1 bg-[var(--c-bg-2)] border border-[var(--c-border)]" />
  <?php endif; ?>

  <?php if (isset($filters['from']) || isset($filters['to'])): ?>
    <label class="text-sm opacity-70 ml-2">From</label>
    <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>"
           class="rounded-md px-2 py-1 bg-[var(--c-bg-2)] border border-[var(--c-border)]" />
    <label class="text-sm opacity-70 ml-2">To</label>
    <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>"
           class="rounded-md px-2 py-1 bg-[var(--c-bg-2)] border border-[var(--c-border)]" />
  <?php endif; ?>

  <?php if (isset($filters['level'])): ?>
    <label class="text-sm opacity-70 ml-2">Level</label>
    <select name="level" class="rounded-md px-2 py-1 bg-[var(--c-bg-2)] border border-[var(--c-border)]">
      <?php for ($i=1; $i<=6; $i++): ?>
        <option value="<?= $i ?>" <?= (int)($filters['level'])===$i?'selected':'' ?>><?= $i ?></option>
      <?php endfor; ?>
    </select>
  <?php endif; ?>

  <?php if (isset($filters['show_zero'])): ?>
    <label class="inline-flex items-center gap-1 ml-2 text-sm">
      <input type="checkbox" name="show_zero" value="1" <?= $filters['show_zero']?'checked':'' ?> />
      Show zero
    </label>
  <?php endif; ?>

  <button type="submit"
          class="ml-2 rounded-md px-3 py-1 bg-[var(--c-green)] text-white hover:opacity-90">
    Apply
  </button>
  <button type="button" onclick="window.print()"
          class="rounded-md px-3 py-1 bg-[var(--c-muted)] hover:opacity-90">
    Print
  </button>

  <?php foreach ($extras as $html) echo $html; ?>
</form>