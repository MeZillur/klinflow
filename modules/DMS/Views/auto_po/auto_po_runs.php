<?php
declare(strict_types=1);
/**
 * View: Auto-PO — Saved runs
 * - Content is built into $slot; if DMS shell exists, it’s required with $slot
 * - Robust $module_base and sidenav discovery (no double shell)
 *
 * Expects:
 *   - array  $rows         // rows from dms_auto_po_runs or view
 *   - array  $org (optional)
 *   - string $module_base (optional)
 */

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ---------- title ---------- */
$title = $title ?? 'Auto-PO · Saved runs';

/* ---------- module_base (slug-safe) ---------- */
$base = rtrim((string)($module_base ?? ''), '/');
if ($base === '') {
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
  if ($slug === '' && isset($_SERVER['REQUEST_URI'])
      && preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
    $slug = $m[1];
  }
  $base = $slug !== '' ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

/* ---------- optional sidenav path for shell ---------- */
$moduleSidenav = $moduleSidenav ?? null;
$root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
if (!$moduleSidenav) {
  foreach ([
    $root.'/modules/DMS/Views/shared/sidenav.php',
    $root.'/modules/DMS/Views/shared/partials/sidenav.php',
    $root.'/modules/dms/Views/shared/sidenav.php',
    $root.'/modules/dms/Views/shared/partials/sidenav.php',
  ] as $cand) { if (is_file($cand)) { $moduleSidenav = $cand; break; } }
}

/* ---------- slot (content only) ---------- */
ob_start(); ?>
<style>
  :root{ --brand:#228B22; }
  .ap-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px}
  .ap-btn{appearance:none;border:1px solid #cbd5e1;border-radius:10px;padding:8px 12px;background:#fff;cursor:pointer;font-weight:600}
  .ap-btn:hover{background:#f8fafc}
  .ap-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid #e5e7eb}
  .ap-badge--ok{background:#ecfdf5;border-color:#d1fae5;color:#065f46}
  table{width:100%;border-collapse:collapse}
  th,td{border-top:1px solid #e5e7eb;padding:10px;text-align:left;font-size:14px}
  th{background:#f8fafc}
  .muted{color:#64748b;font-size:12px}
</style>

<div class="ap-card" role="region" aria-labelledby="apo-runs">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:8px;flex-wrap:wrap">
    <h1 id="apo-runs" style="margin:0;font-weight:700">Auto-PO · Saved runs</h1>
    <div style="display:flex;gap:8px">
      <a class="ap-btn" href="<?= $h($base.'/auto-po') ?>">← Back to generator</a>
    </div>
  </div>

  <div style="overflow:auto">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Created</th>
          <th>Status</th>
          <th>Params</th>
          <th style="width:280px">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <?php
          $id = (int)($r['id'] ?? 0);
          $status = strtoupper((string)($r['status'] ?? 'DRAFT'));
          // support both view or plain table column names
          $limitVal = (int)($r['limit_val'] ?? $r['limit'] ?? 0);
          $minShort = (int)($r['min_shortage'] ?? 0);
          $prefer   = (string)($r['prefer_supplier'] ?? '');
        ?>
        <tr>
          <td>#<?= $id ?></td>
          <td><?= $h((string)($r['created_at'] ?? '')) ?></td>
          <td>
            <span class="ap-badge <?= $status==='APPROVED' ? 'ap-badge--ok':'' ?>">
              <?= $h($status) ?>
            </span>
          </td>
          <td class="muted">
            limit=<?= $limitVal ?>, min=<?= $minShort ?><?= $prefer !== '' ? ', prefer='.$h($prefer) : '' ?>
          </td>
          <td style="white-space:nowrap;display:flex;gap:8px">
            <a class="ap-btn" href="<?= $h($base.'/auto-po/run/'.$id) ?>">Open</a>
            
            
          </td>
        </tr>
      <?php endforeach; if (empty($rows)): ?>
        <tr><td colspan="5" class="muted">No runs yet. Use “Save run snapshot” on the generator page.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$slot = ob_get_clean();

/* ---------- render via shell if available, else raw ---------- */
$shell = null;
foreach ([
  $root.'/modules/DMS/Views/shared/layouts/shell.php',
  $root.'/modules/dms/Views/shared/layouts/shell.php',
] as $p) { if (is_file($p)) { $shell = $p; break; } }

if ($shell) {
  // expose to shell
  $module_base = $base;
  require $shell;
} else {
  echo $slot;
}