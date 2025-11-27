<?php declare(strict_types=1);

/** Minimal escaper */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$module_base = isset($module_base) ? (string)$module_base : '';

/** Robust input objects */
$s           = is_array($s ?? null) ? $s : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$visits      = is_array($visits ?? null) ? $visits : [];

/** Simple helpers */
$money = fn($n) => number_format((float)$n, 2);
$badge = function(string $status): string {
  $k = strtolower(trim($status));
  $cls = match ($k) {
    'active'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'inactive' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    'planned'  => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    'done','confirmed','posted','paid'
               => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    'missed','cancelled','canceled'
               => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
    default    => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-300',
  };
  return '<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold '.$cls.'">'.h(ucfirst($k ?: '—')).'</span>';
};

/** Fields with fallbacks */
$photo      = trim((string)($s['photo_path']     ?? ''));
$idDoc      = trim((string)($s['id_proof_path']  ?? ''));
$role       = strtoupper((string)($s['role']     ?? ''));
$status     = (string)($s['status'] ?? '');
$code       = (string)($s['code']   ?? '—');
$name       = (string)($s['name']   ?? '');
$phone      = (string)($s['phone']  ?? '');
$email      = (string)($s['email']  ?? '');
$territory  = (string)($s['territory'] ?? '');
$notes      = (string)($s['notes'] ?? '');
$createdAt  = (string)($s['created_at'] ?? '');
$idType     = (string)($s['id_proof_type'] ?? '');
$idNo       = (string)($s['id_proof_no']   ?? '');

$supplierId   = (int)($s['supplier_id'] ?? 0);
$supplierName = (string)($s['supplier_name'] ?? '');
$target       = isset($s['monthly_target']) && $s['monthly_target'] !== null ? (float)$s['monthly_target'] : null;

$lmId   = (int)($s['line_manager_id'] ?? 0);
$lmName = (string)($s['line_manager_name'] ?? '');

$emgName = (string)($s['emergency_contact_name'] ?? '');
$emgPhone= (string)($s['emergency_contact_phone'] ?? '');
$emgRel  = (string)($s['emergency_contact_relation'] ?? '');
?>
<div class="mb-4 flex items-center justify-between">
  <h1 class="text-xl font-semibold">Stakeholder: <?= h($name ?: ('#'.(int)($s['id'] ?? 0))) ?></h1>
  <a class="px-3 py-2 rounded-lg border hover:bg-slate-50 dark:hover:bg-slate-800"
     href="<?= h($module_base) ?>/stakeholders/<?= (int)($s['id'] ?? 0) ?>/edit">Edit</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <!-- Left: Identity card -->
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800">
    <div class="flex items-start gap-3 mb-3">
      <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
        <?php if ($photo !== ''): ?>
          <img src="<?= h($photo) ?>" alt="Photo" class="w-full h-full object-cover">
        <?php else: ?>
          <span class="text-xs text-slate-500">No photo</span>
        <?php endif; ?>
      </div>
      <div>
        <div class="text-xs uppercase text-slate-500">Code</div>
        <div class="font-semibold leading-tight"><?= h($code) ?></div>
        <div class="mt-1 flex items-center gap-1 text-xs text-slate-600">
          <?php if ($role): ?>
            <span class="inline-block px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-900"><?= h($role) ?></span>
          <?php endif; ?>
          <?= $status !== '' ? $badge($status) : '' ?>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2 text-sm">
      <div>
        <div class="text-xs text-slate-500">Phone</div>
        <div class="font-medium"><?= h($phone ?: '—') ?></div>
      </div>
      <div>
        <div class="text-xs text-slate-500">Email</div>
        <div class="font-medium"><?= h($email ?: '—') ?></div>
      </div>
      <div class="col-span-2">
        <div class="text-xs text-slate-500">Territory</div>
        <div class="font-medium"><?= h($territory ?: '—') ?></div>
      </div>
      <?php if ($notes !== ''): ?>
        <div class="col-span-2">
          <div class="text-xs text-slate-500">Notes</div>
          <div class="font-medium break-words"><?= h($notes) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ID proof -->
    <div class="mt-4">
      <div class="text-xs uppercase text-slate-500 mb-1">Identity Proof</div>
      <div class="text-sm">
        <div><span class="text-slate-500">Type:</span> <span class="font-medium"><?= h($idType ?: '—') ?></span></div>
        <div><span class="text-slate-500">No:</span> <span class="font-medium"><?= h($idNo ?: '—') ?></span></div>
        <div class="mt-1">
          <?php if ($idDoc !== ''): ?>
            <a href="<?= h($idDoc) ?>" target="_blank" class="text-emerald-600 hover:underline">View / Download</a>
          <?php else: ?>
            <span class="text-slate-500">No file uploaded</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Middle: Relationships & KPIs -->
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800">
    <div class="font-semibold mb-2">Relationships & KPIs</div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
      <div>
        <div class="text-xs text-slate-500">Supplier</div>
        <div class="font-medium">
          <?php
            echo h($supplierName !== '' ? $supplierName : ($supplierId ? ('#'.$supplierId) : '—'));
          ?>
        </div>
      </div>
      <div>
        <div class="text-xs text-slate-500">Monthly Target</div>
        <div class="font-medium"><?= $target !== null ? '৳ '.$money($target) : '—' ?></div>
      </div>

      <div>
        <div class="text-xs text-slate-500">Line Manager</div>
        <div class="font-medium">
          <?= h($lmName !== '' ? $lmName : ($lmId ? ('#'.$lmId) : '—')) ?>
        </div>
      </div>
      <div>
        <div class="text-xs text-slate-500">Created</div>
        <div class="font-medium"><?= h($createdAt ?: '—') ?></div>
      </div>
    </div>

    <!-- Emergency contact -->
    <div class="mt-4">
      <div class="text-xs uppercase text-slate-500 mb-1">Emergency Contact</div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
        <div>
          <div class="text-slate-500">Name</div>
          <div class="font-medium"><?= h($emgName ?: '—') ?></div>
        </div>
        <div>
          <div class="text-slate-500">Phone</div>
          <div class="font-medium"><?= h($emgPhone ?: '—') ?></div>
        </div>
        <div>
          <div class="text-slate-500">Relation</div>
          <div class="font-medium"><?= h($emgRel ?: '—') ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Assignments -->
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800">
    <div class="flex items-center justify-between mb-2">
      <div class="font-semibold">Recent Assignments</div>
      <a class="text-sm text-emerald-600 hover:underline" href="<?= h($module_base) ?>/stakeholders/assign">Manage</a>
    </div>
    <div class="divide-y">
      <?php if ($assignments): foreach ($assignments as $a): ?>
        <?php
          $cid = (int)($a['customer_id'] ?? 0);
          $did = (int)($a['dealer_id']   ?? 0); // may be 0 if schema uses supplier_id
          $cname = (string)($a['customer_name'] ?? '');
          $sname = (string)($a['supplier_name'] ?? ($a['dealer_name'] ?? ''));
        ?>
        <div class="py-2 text-sm">
          <div class="font-medium">
            <?php if ($cid > 0): ?>
              Customer: <?= h($cname !== '' ? $cname : '#'.$cid) ?>
            <?php elseif ($did > 0): ?>
              Supplier: <?= h($sname !== '' ? $sname : '#'.$did) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </div>
          <div class="text-xs text-slate-500"><?= h((string)($a['assigned_on'] ?? '')) ?></div>
          <?php if (!empty($a['notes'])): ?>
            <div class="text-xs mt-1 break-words"><?= h((string)$a['notes']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; else: ?>
        <div class="py-6 text-center text-slate-500">No assignments yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent visits table -->
<div class="mt-4 p-4 rounded-xl border bg-white dark:bg-gray-800">
  <div class="flex items-center justify-between mb-2">
    <div class="font-semibold">Recent Visits</div>
    <a class="text-sm text-emerald-600 hover:underline" href="<?= h($module_base) ?>/stakeholders/visit">Plan visit</a>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-900/40">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Party</th>
          <th class="px-3 py-2 text-left">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php if ($visits): foreach ($visits as $v): ?>
          <?php
            $vDate = substr((string)($v['visit_date'] ?? ''), 0, 10);
            $cId   = (int)($v['customer_id'] ?? 0);
            $dId   = (int)($v['dealer_id']   ?? 0);
            $party = trim(($cId ? "Customer #$cId" : '') . ($cId && $dId ? ' · ' : '') . ($dId ? "Supplier #$dId" : ''));
          ?>
          <tr>
            <td class="px-3 py-2"><?= h($vDate ?: '—') ?></td>
            <td class="px-3 py-2"><?= h($party ?: '—') ?></td>
            <td class="px-3 py-2"><?= $badge((string)($v['status'] ?? '')) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">No visits recorded.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>