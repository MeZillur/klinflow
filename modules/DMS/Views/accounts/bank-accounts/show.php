<?php
declare(strict_types=1);
/**
 * Developer notes:
 * - Accepts both $recent_txns (new) and $tx (legacy) from controller.
 * - If controller provided all-time stats on $account (_stat_*), those are preferred for the top cards.
 * - If gl_account_id is missing or no rows were received, a visible hint explains why the list/totals might be empty.
 * - Client-side pagination + CSV + print included.
 *
 * @var array|null $account      Single bank account row or null if not found
 * @var array      $recent_txns  Optional: transactions (id,date,type,ref_no,description,amount,balance_after)
 * @var string     $module_base
 */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt  = fn($n)=>number_format((float)$n, 2);
$base = rtrim((string)($module_base ?? ''), '/');

if (!$account): ?>
  <div class="p-8">
    <h1 class="text-xl font-semibold mb-3">Bank Account</h1>
    <div class="rounded-xl border bg-white dark:bg-slate-900 p-6">
      <p class="text-slate-600 dark:text-slate-300 mb-4">Account not found.</p>
      <a href="<?= $h($base) ?>/bank-accounts" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700">
        <i class="fa-solid fa-arrow-left-long"></i> Back to list
      </a>
    </div>
  </div>
  <?php return; endif;

/* ---------- Account fields ---------- */
$accId    = (int)($account['id'] ?? 0);
$code     = (string)($account['code'] ?? '');
$bank     = (string)($account['bank_name'] ?? $account['bank'] ?? '');
$accName  = (string)($account['account_name'] ?? $account['name'] ?? '');
$accNo    = (string)($account['account_no'] ?? $account['number'] ?? '');
$branch   = (string)($account['branch'] ?? '');
$status   = strtolower((string)($account['status'] ?? 'active'));
$isMaster = (int)($account['is_master'] ?? 0) === 1;
$currBal  = (float)($account['current_balance'] ?? $account['balance'] ?? 0);
$openBal  = (float)($account['opening_balance'] ?? 0);
$created  = substr((string)($account['created_at'] ?? ''), 0, 19);
$updated  = substr((string)($account['updated_at'] ?? ''), 0, 19);
$glId     = (int)($account['gl_account_id'] ?? 0);

/* ---------- Normalize input rows (works with $recent_txns or $tx) ---------- */
$rowsIn = [];
if (isset($recent_txns) && is_array($recent_txns)) {
  $rowsIn = $recent_txns;
} elseif (isset($tx) && is_array($tx)) {
  $rowsIn = $tx;
}

$norm = [];
foreach ($rowsIn as $row) {
  $amt  = (float)($row['amount'] ?? 0);
  // accept several date keys, trim to 'YYYY-MM-DD'
  $date = (string)($row['date'] ?? $row['jdate'] ?? $row['created_at'] ?? '');
  $date = substr($date, 0, 10);

  // reference/journal no
  $ref  = (string)($row['ref_no'] ?? $row['reference'] ?? $row['jno'] ?? '');

  // description/memo
  $desc = (string)($row['description'] ?? $row['memo'] ?? $row['note'] ?? '');

  // type: prefer provided; else infer from sign
  $typ  = strtolower((string)($row['type'] ?? ''));
  if ($typ === '') $typ = $amt >= 0 ? 'deposit' : 'withdrawal';

  // id for stable ordering
  $rid = (int)($row['id'] ?? ($row['entry_id'] ?? 0));

  $norm[] = [
    'id'            => $rid,
    'date'          => $date,
    'type'          => $typ,
    'ref_no'        => $ref,
    'description'   => $desc,
    'amount'        => $amt,
    'balance_after' => isset($row['balance_after']) ? (float)$row['balance_after'] : null,
  ];
}

/* ---------- Sort ASC to compute running balance correctly ---------- */
usort($norm, function($a, $b) {
  $da = $a['date'] ?? ''; $db = $b['date'] ?? '';
  $cmp = strcmp($da, $db);
  if ($cmp !== 0) return $cmp;
  return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
});

/* ---------- Compute running balance from opening balance ---------- */
$bal = (float)$openBal;
for ($i = 0; $i < count($norm); $i++) {
  $bal += (float)$norm[$i]['amount'];
  if ($norm[$i]['balance_after'] === null) {
    $norm[$i]['balance_after'] = $bal;
  }
}

/* ---------- Pagination (client-side) ---------- */
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = (int)($_GET['per'] ?? 50);
$per     = max(10, min(200, $per));
$total   = count($norm);
$pages   = max(1, (int)ceil($total / $per));
$rev     = array_reverse($norm); // show latest first
$offset  = ($page - 1) * $per;
$display = array_slice($rev, $offset, $per);
$hasPrev = $page > 1;
$hasNext = $page < $pages;

/* ---------- Metrics: prefer controller-provided all-time stats if present ---------- */
$calcDeposits    = array_reduce($norm, fn($s,$r)=>$s + (float)max(0,$r['amount']), 0.0);
$calcWithdrawals = array_reduce($norm, fn($s,$r)=>$s + (float)max(0,-$r['amount']), 0.0);
$calcCount       = $total;

$mtx = [
  'deposits'    => isset($account['_stat_deposits'])    ? (float)$account['_stat_deposits']    : $calcDeposits,
  'withdrawals' => isset($account['_stat_withdrawals']) ? (float)$account['_stat_withdrawals'] : $calcWithdrawals,
  'tx_count'    => isset($account['_stat_tx_count'])    ? (int)$account['_stat_tx_count']      : $calcCount,
];

// Surface a helpful hint when GL link/rows are missing.
$showGlHint = ($glId <= 0) || (empty($rowsIn) && $mtx['tx_count'] === 0);
?>
<div class="p-6 space-y-6">

  <!-- Header -->
  <div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
      <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 text-sm">
        <a href="<?= $h($base) ?>/bank-accounts" class="hover:underline">Bank Accounts</a>
        <span>›</span>
        <span class="truncate">#<?= $h($code ?: $accId) ?></span>
      </div>
      <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
        <?= $h($accName ?: ($bank ?: 'Bank Account')) ?>
      </h1>
      <div class="mt-2 flex flex-wrap items-center gap-2">
        <?php if ($code): ?>
          <span class="badge">
            <i class="fa-solid fa-hashtag"></i> <?= $h($code) ?>
          </span>
        <?php endif; ?>
        <?php if ($isMaster): ?>
          <span class="badge badge--emerald">
            <i class="fa-solid fa-star"></i> Master
          </span>
        <?php endif; ?>
        <span class="badge <?= $status==='active' ? 'badge--blue' : '' ?>">
          <i class="fa-solid fa-circle-dot text-[10px]"></i> <?= $h(ucfirst($status)) ?>
        </span>
        <?php if ($glId > 0): ?>
          <span class="badge badge--slate-outline" title="Linked GL Account ID">
            <i class="fa-solid fa-book"></i> GL #<?= (int)$glId ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-2">
      <!-- Theme toggle -->
      <button type="button" class="btn btn--ghost" id="themeToggle" title="Toggle theme">
        <i class="fa-solid fa-circle-half-stroke"></i><span class="ml-2 hidden sm:inline">Theme</span>
      </button>

      <a href="<?= $h($base) ?>/bank-accounts" class="btn btn--ghost">
        <i class="fa-solid fa-arrow-left-long"></i><span class="ml-2 hidden sm:inline">Back</span>
      </a>

      <a href="<?= $h($base) ?>/bank-accounts/<?= $accId ?>/edit" class="btn btn--white">
        <i class="fa-regular fa-pen-to-square"></i><span class="ml-2 hidden sm:inline">Edit</span>
      </a>

      <?php if (!$isMaster): ?>
        <form method="post"
              action="<?= $h($base) ?>/bank-accounts/<?= $accId ?>/make-master"
              class="inline"
              onsubmit="return confirm('Make this the master bank account?');">
          <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
          <button class="btn btn--primary" type="submit">
            <i class="fa-solid fa-crown"></i><span class="ml-2 hidden sm:inline">Make Master</span>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Info hint -->
  <?php if ($showGlHint): ?>
  <div class="rounded-xl border dark:border-slate-800 bg-amber-50 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 p-4">
    <div class="flex items-start gap-3">
      <i class="fa-solid fa-circle-info mt-0.5"></i>
      <div class="text-sm leading-6">
        <strong>Heads up:</strong> No GL-linked activity received.
        <?php if ($glId <= 0): ?>
          This bank account has no <code>gl_account_id</code> linked. Link a Bank-type GL (e.g., “Cash at Bank”) so deposits/withdrawals appear here.
        <?php else: ?>
          GL linked (ID <?= (int)$glId ?>) but no rows came from controller. Ensure payment journals are posted (DR Bank, CR Counterparty).
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Top cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="card">
      <div class="card__title">Current Balance</div>
      <div class="card__value">৳ <?= $fmt($currBal) ?></div>
    </div>
    <div class="card">
      <div class="card__title">Opening Balance</div>
      <div class="card__value">৳ <?= $fmt($openBal) ?></div>
    </div>
    <div class="card">
      <div class="card__title">Deposits (total)</div>
      <div class="card__value">৳ <?= $fmt($mtx['deposits']) ?></div>
    </div>
    <div class="card">
      <div class="card__title">Transactions</div>
      <div class="card__value"><?= (int)$mtx['tx_count'] ?></div>
    </div>
  </div>

  <!-- Modern account details (center section) -->
  <div class="rounded-2xl border bg-white dark:bg-slate-900 dark:border-slate-800 p-6">
    <div class="flex items-start justify-between gap-4 mb-4">
      <div class="font-semibold text-slate-900 dark:text-slate-100">Bank Details</div>
      <div class="flex gap-2">
        <button type="button" class="btn btn--white" id="btnPrint"><i class="fa-solid fa-print"></i><span class="ml-2 hidden sm:inline">Print</span></button>
        <button type="button" class="btn btn--white" id="btnCsv"><i class="fa-regular fa-file-lines"></i><span class="ml-2 hidden sm:inline">Export CSV</span></button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="detail">
        <div class="detail__label"><i class="fa-solid fa-building-columns"></i> Bank</div>
        <div class="detail__value"><?= $h($bank ?: '—') ?></div>
      </div>
      <div class="detail">
        <div class="detail__label"><i class="fa-regular fa-id-badge"></i> Account Name</div>
        <div class="detail__value"><?= $h($accName ?: '—') ?></div>
      </div>
      <div class="detail">
        <div class="detail__label"><i class="fa-regular fa-credit-card"></i> Account Number</div>
        <div class="detail__value flex items-center gap-2">
          <span class="font-mono"><?= $h($accNo ?: '—') ?></span>
          <?php if ($accNo): ?>
            <button class="copy-btn" type="button" data-copy="<?= $h($accNo) ?>" title="Copy">
              <i class="fa-regular fa-copy"></i>
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($branch): ?>
      <div class="detail">
        <div class="detail__label"><i class="fa-solid fa-location-dot"></i> Branch</div>
        <div class="detail__value"><?= $h($branch) ?></div>
      </div>
      <?php endif; ?>
      <div class="detail">
        <div class="detail__label"><i class="fa-regular fa-calendar"></i> Created</div>
        <div class="detail__value"><?= $h($created ?: '—') ?></div>
      </div>
      <div class="detail">
        <div class="detail__label"><i class="fa-regular fa-clock"></i> Last Updated</div>
        <div class="detail__value"><?= $h($updated ?: '—') ?></div>
      </div>
    </div>
  </div>

  <!-- Transactions -->
  <div class="rounded-2xl border overflow-hidden bg-white dark:bg-slate-900 dark:border-slate-800">
    <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/60">
      <div class="font-semibold">Transactions</div>
      <div class="text-sm text-slate-500 dark:text-slate-400">Page <?= (int)$page ?> of <?= (int)$pages ?> (<?= (int)$total ?> total rows received)</div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300">
          <tr>
            <th class="px-4 py-2 text-left">Date</th>
            <th class="px-4 py-2 text-left">Type</th>
            <th class="px-4 py-2 text-left">Reference</th>
            <th class="px-4 py-2 text-left">Description</th>
            <th class="px-4 py-2 text-right">Amount</th>
            <th class="px-4 py-2 text-right">Balance After</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($display)): foreach ($display as $t):
            $dt   = substr((string)($t['date'] ?? ''), 0, 10);
            $type = strtolower((string)($t['type'] ?? ''));
            $amt  = (float)($t['amount'] ?? 0);
            $balA = (float)($t['balance_after'] ?? 0);
            $typeLabel = $type ?: ($amt >= 0 ? 'Deposit' : 'Withdrawal');

            $tag  = $typeLabel==='Deposit' || $type==='deposit' ? 'badge--emerald-outline'
                  : ($typeLabel==='Withdrawal' || $type==='withdrawal' ? 'badge--rose-outline'
                  :  'badge--slate-outline');
          ?>
            <tr class="border-t hover:bg-slate-50 dark:hover:bg-slate-800/60">
              <td class="px-4 py-2"><?= $h($dt ?: '—') ?></td>
              <td class="px-4 py-2">
                <span class="badge <?= $tag ?>">
                  <?= $h(ucfirst($typeLabel)) ?>
                </span>
              </td>
              <td class="px-4 py-2"><?= $h($t['ref_no'] ?? '—') ?></td>
              <td class="px-4 py-2"><?= $h($t['description'] ?? '—') ?></td>
              <td class="px-4 py-2 text-right <?= $amt>=0?'text-emerald-700 dark:text-emerald-400':'text-rose-700 dark:text-rose-400' ?>">
                ৳ <?= $fmt($amt) ?>
              </td>
              <td class="px-4 py-2 text-right">৳ <?= $fmt($balA) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">No transactions to show.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination footer -->
    <div class="flex items-center justify-between p-4 border-t dark:border-slate-800 bg-white dark:bg-slate-900">
      <div class="text-sm text-slate-600 dark:text-slate-300">
        Showing <?= (int)min($per, max(0, $total - $offset)) ?> of <?= (int)$total ?> • Per page:
        <a class="link" href="?per=25">25</a> ·
        <a class="link" href="?per=50">50</a> ·
        <a class="link" href="?per=100">100</a> ·
        <a class="link" href="?per=200">200</a>
      </div>
      <div class="flex gap-2">
        <a class="btn btn--white <?= $hasPrev?'':'opacity-50 pointer-events-none' ?>" href="<?= $hasPrev ? ('?page='.($page-1).'&per='.$per) : '#' ?>">
          <i class="fa-solid fa-chevron-left"></i><span class="ml-2 hidden sm:inline">Prev</span>
        </a>
        <a class="btn btn--white <?= $hasNext?'':'opacity-50 pointer-events-none' ?>" href="<?= $hasNext ? ('?page='.($page+1).'&per='.$per) : '#' ?>">
          <span class="mr-2 hidden sm:inline">Next</span><i class="fa-solid fa-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<style>
  /* --- Buttons / badges / cards (light + dark) --- */
  .btn{display:inline-flex;align-items:center;border-radius:.75rem;padding:.5rem .75rem;font-weight:600;border:1px solid transparent;transition:.15s}
  .btn--ghost{background:#f3f4f6;color:#0f172a}
  .btn--white{background:#fff;border-color:#e5e7eb;color:#0f172a}
  .btn--primary{background:#059669;color:#fff}
  .btn:hover{transform:translateY(-1px)}
  .badge{display:inline-flex;align-items:center;gap:.375rem;border-radius:.5rem;padding:.125rem .5rem;font-size:12px}
  .badge i{font-size:12px}
  .badge--emerald{background:#d1fae5;color:#065f46}
  .badge--blue{background:#dbeafe;color:#1d4ed8}
  .badge--emerald-outline{border:1px solid #34d399;color:#065f46;background:transparent;padding:.0625rem .5rem}
  .badge--rose-outline{border:1px solid #fb7185;color:#991b1b;background:transparent;padding:.0625rem .5rem}
  .badge--slate-outline{border:1px solid #cbd5e1;color:#334155;background:transparent;padding:.0625rem .5rem}
  .card{border:1px solid #e5e7eb;border-radius:16px;padding:16px;background:#fff}
  .card__title{color:#6b7280;font-size:12px;margin-bottom:6px}
  .card__value{font-size:22px;font-weight:700}
  .detail__label{color:#64748b;font-size:12px;margin-bottom:4px;display:flex;align-items:center;gap:.5rem}
  .detail__value{font-weight:600;color:#0f172a}
  .copy-btn{height:28px;width:28px;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;color:#475569}
  .copy-btn:hover{background:#f8fafc}
  .link{color:#2563eb;text-decoration:none}
  .link:hover{text-decoration:underline}

  /* --- Dark mode --- */
  @media (prefers-color-scheme: dark) {
    :root:not(.light){color-scheme:dark}
  }
  .dark .btn--ghost{background:#1f2937;color:#e5e7eb}
  .dark .btn--white{background:#0b1220;border-color:#1f2937;color:#e5e7eb}
  .dark .card{background:#0b1220;border-color:#1f2937}
  .dark .card__title{color:#94a3b8}
  .dark .card__value{color:#e2e8f0}
  .dark .detail__value{color:#e2e8f0}
  .dark .badge--slate-outline{border-color:#475569;color:#cbd5e1}
  .dark .copy-btn{background:#0b1220;border-color:#1f2937;color:#cbd5e1}
  .dark .copy-btn:hover{background:#111827}
</style>

<script>
  // ---- theme toggle (persists) ----
  (function(){
    const key='klf_theme';
    const root=document.documentElement;
    const saved=localStorage.getItem(key);
    if(saved==='dark') root.classList.add('dark');
    if(saved==='light') root.classList.add('light');
    const btn=document.getElementById('themeToggle');
    if(btn){
      btn.addEventListener('click', ()=>{
        if(root.classList.contains('dark')){ root.classList.remove('dark'); root.classList.add('light'); localStorage.setItem(key,'light'); }
        else { root.classList.add('dark'); root.classList.remove('light'); localStorage.setItem(key,'dark'); }
      }, {passive:true});
    }
  })();

  // Copy account number
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.copy-btn'); if(!btn) return;
    const txt = btn.getAttribute('data-copy') || '';
    navigator.clipboard?.writeText(txt);
    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
    setTimeout(()=>btn.innerHTML = '<i class="fa-regular fa-copy"></i>', 1200);
  }, {passive:true});

  // Print
  document.getElementById('btnPrint')?.addEventListener('click', ()=>{ window.print(); }, {passive:true});

  // CSV Export (from currently displayed page data for clarity)
  document.getElementById('btnCsv')?.addEventListener('click', ()=>{
    const data = <?= json_encode(array_map(fn($r)=>[
      'date'=>$r['date'],
      'type'=>$r['type'],
      'reference'=>$r['ref_no'],
      'description'=>$r['description'],
      'amount'=>$r['amount'],
      'balance_after'=>$r['balance_after'],
    ], $display), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const esc = v => (''+v).replace(/"/g,'""');
    const rows = [
      ['Date','Type','Reference','Description','Amount','Balance After'],
      ...data.map(r=>[r.date,r.type,r.reference,r.description,r.amount,r.balance_after])
    ];
    const csv = rows.map(r=>r.map(v=>'"'+esc(v)+'"').join(',')).join('\r\n');
    const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'bank-account-<?= $h($code ?: $accId) ?>-page<?= (int)$page ?>.csv';
    document.body.appendChild(a); a.click(); a.remove();
  }, {passive:true});
</script>