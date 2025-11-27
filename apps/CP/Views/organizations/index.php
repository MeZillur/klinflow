<?php
declare(strict_types=1);
/**
 * cp/organizations/index.php — content only (no layout includes)
 * Supports BOTH payload shapes:
 *   A) $orgs, $filters, $pagination
 *   B) $rows, $q, $plan, $status  (we'll normalize into A)
 */

/* ---------- Compatibility shims ---------- */
$orgs = $orgs ?? ($rows ?? []);
$filters = $filters ?? [
  'q'      => $q      ?? '',
  'plan'   => $plan   ?? '',
  'status' => $status ?? '',
];
if (!isset($pagination)) {
  $pagination = [
    'page'       => (int)($_GET['page'] ?? 1),
    'total'      => is_array($orgs) ? count($orgs) : 0,
    'totalPages' => 1, // simple list view (no server pagination yet)
  ];
}

/* ---------- Helpers ---------- */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmtMoney = fn($n)=>number_format((float)$n, 2);
?>
<div class="card" style="padding:16px;border-radius:12px">
  <!-- Filters -->
  <form method="get" action="/cp/organizations" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
    <input type="text" name="q" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Search name/slug/email"
           style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;min-width:240px">
    <select name="status" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px">
      <?php $st = (string)($filters['status'] ?? ''); ?>
      <option value="">Status (all)</option>
      <?php foreach (['active','trial','past_due','suspended','inactive'] as $opt): ?>
        <option value="<?= $opt ?>" <?= $st===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="plan" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px">
      <?php $pl = (string)($filters['plan'] ?? ''); ?>
      <option value="">Plan (all)</option>
      <?php foreach (['free','starter','pro','enterprise','trial'] as $opt): ?>
        <option value="<?= $opt ?>" <?= $pl===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Filter</button>
    <?php if (($filters['q'] ?? '')!=='' || ($filters['status'] ?? '')!=='' || ($filters['plan'] ?? '')!==''): ?>
      <a href="/cp/organizations" class="btn">Reset</a>
    <?php endif; ?>
    <span style="margin-left:auto;color:#6b7280;font-size:13px">
      <?= (int)($pagination['total'] ?? 0) ?> total
    </span>
  </form>

  <!-- Table -->
  <div style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;color:#6b7280;font-size:13px">
          <th style="padding:10px;border-bottom:1px solid #e5e7eb">Name</th>
          <th style="padding:10px;border-bottom:1px solid #e5e7eb">Slug</th>
          <th style="padding:10px;border-bottom:1px solid #e5e7eb">Plan</th>
          <th style="padding:10px;border-bottom:1px solid #e5e7eb">Status</th>
          <th style="padding:10px;border-bottom:1px solid #e5e7eb">Owner</th>
          <th style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right">MRR</th>
          <th style="padding:10px;border-bottom:1px solid #e5e7eb">Created</th>
          <th style="padding:10px;border-bottom:1px solid #e5e7eb">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($orgs)): ?>
        <?php foreach ($orgs as $o): ?>
          <tr>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:600"><?= $h($o['name'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9"><code><?= $h($o['slug'] ?? '') ?></code></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9"><?= $h(ucfirst((string)($o['plan'] ?? ''))) ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9">
              <span style="padding:2px 8px;border-radius:999px;font-size:12px;background:#eef2ff;color:#3730a3">
                <?= $h((string)($o['status'] ?? '')) ?>
              </span>
            </td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9"><?= $h($o['owner_email'] ?? '') ?></td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;text-align:right">
              ৳<?= $fmtMoney($o['monthly_price'] ?? 0) ?>
            </td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9">
              <?php
                $ts = strtotime((string)($o['created_at'] ?? ''));
                echo $h($ts ? date('d M Y', $ts) : '');
              ?>
            </td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9">
              <a class="btn" href="/cp/organizations/<?= (int)($o['id'] ?? 0) ?>/edit">Edit</a>
              <?php if (!empty($o['slug'])): ?>
                <a class="btn" href="/t/<?= $h($o['slug']) ?>/dashboard" target="_blank" rel="noopener">Open Tenant</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="8" style="padding:14px;color:#64748b;text-align:center">No organizations found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- (Optional) simple pager notice; real paging can come later -->
  <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:12px">
      <div class="btn" style="pointer-events:none">
        Page <?= (int)($pagination['page'] ?? 1) ?> of <?= (int)($pagination['totalPages'] ?? 1) ?>
      </div>
    </div>
  <?php endif; ?>
</div>