<?php
declare(strict_types=1);
/**
 * View: Auto-PO Run (Show)
 * Content-only by default; will self-wire shell+sidenav if front didn’t wrap.
 *
 * Expects:
 *   - array  $run         (row from dms_auto_po_runs)
 *   - array  $items       (rows from dms_auto_po_items)
 *   - array  $org         (name, logo/logo_url, address/addr, phone, email)
 *   - string $module_base (optional)
 *   - array  $suppliers   (optional [id => name] map)
 */

use Shared\Csrf;

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';

/* ---------- Org ---------- */
$org        = is_array($org ?? null) ? $org : [];
$orgName    = trim((string)($org['name'] ?? ''));
$orgLogo    = (string)($org['logo'] ?? ($org['logo_url'] ?? '/public/assets/brand/logo.png'));
$orgAddr    = trim((string)($org['address'] ?? ($org['addr'] ?? '')));
$orgPhone   = trim((string)($org['phone'] ?? ''));
$orgEmail   = trim((string)($org['email'] ?? ''));

/* ---------- Base (slug-safe) ---------- */
$base = rtrim((string)($module_base ?? ($org['module_base'] ?? '')), '/');
if ($base === '') {
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
  if ($slug === '' && isset($_SERVER['REQUEST_URI']) &&
      preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
    $slug = $m[1];
  }
  $base = $slug !== '' ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

/* ---------- CSRF (tenant) ---------- */
$csrf = class_exists(Csrf::class)
  ? Csrf::token('tenant')
  : (function(){
      if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
      foreach (['csrf_token_tenant','_csrf_tenant','_csrf','csrf','XSRF-TOKEN','KFIN_CSRF'] as $k) {
        if (!empty($_SESSION[$k])) return (string)$_SESSION[$k];
      }
      return '';
    })();

/* ---------- Run meta ---------- */
$runId     = (int)($run['id'] ?? 0);
$runStatus = strtoupper((string)($run['status'] ?? 'DRAFT'));
$createdAt = (string)($run['created_at'] ?? '');

/* ---------- Supplier helper ---------- */
$suppliers     = is_array($suppliers ?? null) ? $suppliers : [];
$supplierLabel = function($sid) use ($suppliers) {
  $sid = (int)$sid;
  if ($sid === 0) return '';
  return isset($suppliers[$sid]) ? ($suppliers[$sid]." (#$sid)") : "#$sid";
};

/* ---------- Email helper ---------- */
$subject   = rawurlencode("Auto-PO Run #$runId — ".$orgName);
$lines     = ["Auto-PO Run #$runId", "Status: $runStatus", "Created: $createdAt", "", "Items:"];
foreach ($items as $it) {
  $lines[] = sprintf("- %s (PID %d), Suggest %s %s @ %s",
    (string)($it['name']??''),
    (int)($it['product_id']??0),
    rtrim(rtrim(number_format((float)($it['suggested_qty']??0),2,'.',''), '0'), '.'),
    (string)($it['unit']??''),
    rtrim(rtrim(number_format((float)($it['unit_price']??0),2,'.',''), '0'), '.')
  );
}
$emailBody = rawurlencode(implode("\n", $lines));
$mailto    = "mailto:?subject={$subject}&body={$emailBody}";

/* ============================================================
 * SLOT (content)
 * ============================================================ */
ob_start();
?>
<style>
  :root { --brand: <?= $brand ?>; }
  @media print {
    .no-print { display: none !important; }
    .print-card { box-shadow: none !important; border: 0 !important; }
    .page-break { page-break-before: always; }
  }
  .btn { appearance:none; border:1px solid #d1d5db; background:#fff; padding:8px 12px; border-radius:10px; font-weight:600; cursor:pointer; }
  .btn--brand { background:var(--brand); border-color:var(--brand); color:#fff; }
  .badge { display:inline-block; padding:2px 8px; border:1px solid #e5e7eb; border-radius:999px; font-size:12px; }
  .badge--ok { background:#ecfdf5; border-color:#d1fae5; color:#065f46; }
  .card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:16px; }
  table { width:100%; border-collapse: collapse; }
  thead th { background:#f8fafc; text-align:left; font-size:14px; padding:10px; border-top:1px solid #e5e7eb; }
  tbody td { font-size:14px; padding:10px; border-top:1px solid #e5e7eb; }
  .t-right { text-align:right; }
  .muted { color:#64748b; }
  .header-grid { display:grid; grid-template-columns:auto 1fr; gap:16px; align-items:center; }
  .org-logo { max-height:56px; max-width:220px; object-fit:contain; }
  .doc-title { font-weight:800; font-size:20px; margin:0; }
  .doc-sub { color:#64748b; font-size:12px; }
  .sig-line { height:1px; background:#e5e7eb; margin-top:28px; }
  .sig-label { font-size:12px; color:#374151; margin-top:6px; }
</style>

<div class="space-y-4">

  <!-- toolbar -->
  <div class="no-print" style="display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;">
    <div style="display:flex; align-items:center; gap:8px;">
      <a class="btn" href="<?= $h($base) ?>/auto-po/runs">← Saved runs</a>
      <a class="btn"  href="<?= $h($mailto) ?>">Share via email</a>
      <a class="btn"  href="<?= $h($base) ?>/auto-po/run/<?= $runId ?>/csv">Download CSV</a>
      <a class="btn"  target="_blank" rel="noopener" href="<?= $h($base) ?>/auto-po/run/<?= $runId ?>/pdf">Download PDF</a>
      <button class="btn" onclick="window.print()">Print</button>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
      <form method="post" action="<?= $h($base) ?>/auto-po/run/<?= $runId ?>/commit" style="margin:0; display:flex; gap:8px; align-items:center;">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <button class="btn btn--brand" type="submit">Commit to Purchases</button>
      </form>
      <span class="badge <?= $runStatus==='COMMITTED'?'badge--ok':'' ?>"><?= $h($runStatus) ?></span>
    </div>
  </div>

  <!-- printable card -->
  <div class="card print-card" role="region" aria-labelledby="doc-title">

    <!-- org header -->
    <div class="header-grid">
      <img class="org-logo" src="<?= $h($orgLogo) ?>" alt="Logo">
      <div>
        <h1 id="doc-title" class="doc-title">Auto-PO Run #<?= (int)$runId ?></h1>
        <div class="doc-sub">
          <?= $h($orgName ?: 'Organization') ?>
          <?php if ($orgAddr): ?> · <?= $h($orgAddr) ?><?php endif; ?>
          <?php if ($orgPhone): ?> · <?= $h($orgPhone) ?><?php endif; ?>
          <?php if ($orgEmail): ?> · <?= $h($orgEmail) ?><?php endif; ?>
        </div>
        <div class="doc-sub">Created: <?= $h($createdAt ?: date('Y-m-d H:i')) ?></div>
      </div>
    </div>

    <!-- items -->
    <div style="margin-top:14px; overflow:auto;">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th class="t-right">PID</th>
            <th>Supplier</th>
            <th class="t-right">On-hand</th>
            <th class="t-right">Reorder</th>
            <th class="t-right">Target</th>
            <th class="t-right">Suggest</th>
            <th class="t-right">Price</th>
            <th class="t-right">Line</th>
          </tr>
        </thead>
        <tbody>
          <?php $grand = 0.0; ?>
          <?php foreach ($items as $it):
            $qty   = (float)($it['suggested_qty'] ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            $line  = $qty * $price; $grand += $line;
          ?>
          <tr>
            <td><?= $h((string)($it['name'] ?? '')) ?></td>
            <td class="t-right"><?= (int)($it['product_id'] ?? 0) ?></td>
            <td><?= $h($supplierLabel($it['supplier_id'] ?? 0)) ?></td>
            <td class="t-right"><?= rtrim(rtrim(number_format((float)($it['onhand'] ?? 0),2,'.',''), '0'), '.') ?></td>
            <td class="t-right"><?= rtrim(rtrim(number_format((float)($it['reorder_point'] ?? 0),2,'.',''), '0'), '.') ?></td>
            <td class="t-right"><?= rtrim(rtrim(number_format((float)($it['target_level'] ?? 0),2,'.',''), '0'), '.') ?></td>
            <td class="t-right"><?= rtrim(rtrim(number_format($qty,2,'.',''), '0'), '.') ?></td>
            <td class="t-right"><?= number_format($price, 2) ?></td>
            <td class="t-right"><?= number_format($line, 2) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr>
            <td colspan="8" class="t-right" style="font-weight:700;">Estimated total</td>
            <td class="t-right" style="font-weight:700;"><?= number_format($grand, 2) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- notes -->
    <div class="muted" style="margin-top:10px;">
      <b>What next?</b> Use <i>Commit to Purchases</i> to create purchase headers and lines, grouped by supplier.
    </div>

    <!-- signatures -->
    <div class="page-break" style="margin-top:32px;"></div>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:28px; margin-top:8px;">
      <div>
        <div class="sig-line"></div>
        <div class="sig-label">Prepared by</div>
      </div>
      <div>
        <div class="sig-line"></div>
        <div class="sig-label">Approved by</div>
      </div>
    </div>

    <!-- footer -->
    <div style="margin-top:22px; font-size:12px; color:#6b7280; text-align:center;">
      Generated by KlinFlow · Auto-PO · <?= $h($orgName ?: 'Organization') ?>
    </div>
  </div>
</div>
<?php
$slot = ob_get_clean();

/* ============================================================
 * OPTIONAL SHELL WIRING (only if front hasn't wrapped us)
 * ============================================================ */
$root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
$shell = null;
foreach ([
  $root.'/modules/DMS/Views/shared/layouts/shell.php',
  $root.'/modules/dms/Views/shared/layouts/shell.php',
] as $p) { if (is_file($p)) { $shell = $p; break; } }

/* Try to locate a sidenav if shell expects it */
$moduleSidenav = $moduleSidenav ?? null;
if (!$moduleSidenav) {
  foreach ([
    $root.'/modules/DMS/Views/shared/sidenav.php',
    $root.'/modules/DMS/Views/shared/partials/sidenav.php',
    $root.'/modules/dms/Views/shared/sidenav.php',
    $root.'/modules/dms/Views/shared/partials/sidenav.php',
  ] as $p) { if (is_file($p)) { $moduleSidenav = $p; break; } }
}

/* Expose expected vars to shell and render (or just echo slot) */
if ($shell) {
  $title = 'Auto-PO Run #'.$runId;
  $module_base = $base;
  require $shell;
} else {
  echo $slot;
}