<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use DateTimeImmutable;
use PDO;

final class AccountingDashboardController extends BaseController
{
    /* =========================================================================
     *                          UTILITIES / METADATA
     * ========================================================================= */

    private function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1
        ");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    private function hasColumn(PDO $pdo, string $table, string $col): bool
    {
        $st = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    }

    private function orgIdFromCtx(array $ctx): int
    {
        return (int)($ctx['org']['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0));
    }

    /**
     * Resolve important single GL accounts by map_key or fallback heuristics.
     * These are used for balances (AR/AP/Cash-like) and single-account COGS fallback.
     */
    private function resolveKeyAccounts(PDO $pdo, int $orgId): array
    {
        $out = [
            'revenue' => 0, 'cogs' => 0, 'ar' => 0, 'ap' => 0,
            'cash' => 0, 'bank' => 0, 'mobile' => 0,
        ];

        // Mapping
        try {
            $s = $pdo->prepare("SELECT LOWER(map_key) mk, account_id FROM dms_account_map WHERE org_id=?");
            $s->execute([$orgId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $mk = (string)$r['mk'];
                if (array_key_exists($mk, $out)) {
                    $out[$mk] = (int)$r['account_id'];
                }
            }
        } catch (\Throwable) {
            // ignore mapping errors; fallbacks below
        }

        // Fallbacks
        $pick = function(array $rows, callable $match): int {
            foreach ($rows as $r) if ($match($r)) return (int)$r['id'];
            return 0;
        };

        $ga = $pdo->prepare("SELECT id, code, name, LOWER(type) AS type FROM dms_gl_accounts WHERE org_id=? ORDER BY code");
        $ga->execute([$orgId]);
        $accs = $ga->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($out['ar'] === 0)
            $out['ar'] = $pick($accs, fn($r)=>$r['type']==='accounts receivable' || $r['type']==='receivable');

        if ($out['ap'] === 0)
            $out['ap'] = $pick($accs, fn($r)=>$r['type']==='accounts payable' || $r['type']==='payable');

        if ($out['bank'] === 0)
            $out['bank'] = $pick($accs, fn($r)=>in_array($r['type'], ['bank','cash at bank','bank account'], true));

        if ($out['cash'] === 0)
            $out['cash'] = $pick($accs, fn($r)=>$r['type']==='cash' || str_contains(strtolower($r['name']), 'cash'));

        if ($out['mobile'] === 0)
            $out['mobile'] = $pick($accs, fn($r)=>str_contains(strtolower($r['name']), 'bkash') || str_contains(strtolower($r['name']), 'nagad') || str_contains(strtolower($r['name']), 'mobile'));

        if ($out['revenue'] === 0)
            $out['revenue'] = $pick($accs, fn($r)=>$r['type']==='income' || $r['type']==='revenue' || str_contains(strtolower($r['name']), 'sales'));

        if ($out['cogs'] === 0)
            $out['cogs'] = $pick($accs, fn($r)=>str_contains(strtolower($r['name']), 'cogs') || str_contains(strtolower($r['name']), 'cost of goods'));

        return $out;
    }

    /* =========================================================================
     *                        ACCOUNT TREE HELPERS (DESCENDANTS)
     * ========================================================================= */

    /** Build account tree for descendant lookups (id => children) */
    private function loadAccounts(PDO $pdo, int $orgId): array
    {
        $st = $pdo->prepare("SELECT id, parent_id, LOWER(type) AS type, name FROM dms_gl_accounts WHERE org_id=?");
        $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byId = []; $children = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $pid = (int)($r['parent_id'] ?? 0);
            $r['id'] = $id;
            $r['parent_id'] = $pid;
            $byId[$id] = $r;
            $children[$pid][] = $id;
        }
        return ['byId'=>$byId, 'children'=>$children];
    }

    private function collectDescendants(int $rootId, array $children): array
    {
        $out = [$rootId];
        $stack = [$rootId];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($children[$cur] ?? [] as $cid) {
                $out[] = $cid;
                $stack[] = $cid;
            }
        }
        return array_values(array_unique($out));
    }

    /** All REVENUE account ids (mapped parent + descendants OR fallback income/revenue/"sales") */
    private function revenueAccountIds(PDO $pdo, int $orgId): array
    {
        $map = $pdo->prepare("SELECT account_id FROM dms_account_map WHERE org_id=? AND map_key='revenue' LIMIT 1");
        $map->execute([$orgId]);
        $mapped = (int)($map->fetchColumn() ?: 0);

        $tree = $this->loadAccounts($pdo, $orgId);
        $byId = $tree['byId']; $children = $tree['children'];

        if ($mapped > 0) {
            return $this->collectDescendants($mapped, $children);
        }

        $ids = [];
        foreach ($byId as $acc) {
            $t = (string)($acc['type'] ?? '');
            $nm = strtolower((string)($acc['name'] ?? ''));
            if ($t === 'income' || $t === 'revenue' || str_contains($nm, 'sales')) {
                $ids[] = (int)$acc['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /** All COGS account ids (mapped parent + descendants OR fallback by name) */
    private function cogsAccountIds(PDO $pdo, int $orgId): array
    {
        $map = $pdo->prepare("SELECT account_id FROM dms_account_map WHERE org_id=? AND map_key='cogs' LIMIT 1");
        $map->execute([$orgId]);
        $mapped = (int)($map->fetchColumn() ?: 0);

        $tree = $this->loadAccounts($pdo, $orgId);
        $byId = $tree['byId']; $children = $tree['children'];

        if ($mapped > 0) {
            return $this->collectDescendants($mapped, $children);
        }

        $ids = [];
        foreach ($byId as $acc) {
            $nm = strtolower((string)($acc['name'] ?? ''));
            if (str_contains($nm, 'cogs') || str_contains($nm, 'cost of goods')) {
                $ids[] = (int)$acc['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /* =========================================================================
     *                LOW-LEVEL GL QUERIES (SINGLE & MULTI-ACCOUNT)
     * =========================================================================
     * NOTE: We use DATE(j.posted_at) as the time axis (authoritative in DMS).
     */

    private function sumBetween(PDO $pdo, int $orgId, int $accountId, string $from, string $to): float
    {
        if ($accountId <= 0) return 0.0;
        $q = $pdo->prepare("
            SELECT COALESCE(SUM(e.dr - e.cr),0)
            FROM dms_gl_entries e
            JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
            WHERE e.org_id=? AND e.account_id=?
              AND DATE(j.posted_at) BETWEEN ? AND ?
        ");
        $q->execute([$orgId, $accountId, $from, $to]);
        return (float)$q->fetchColumn();
    }

    private function balTo(PDO $pdo, int $orgId, int $accountId, string $to): float
    {
        if ($accountId <= 0) return 0.0;
        $q = $pdo->prepare("
            SELECT COALESCE(SUM(e.dr - e.cr),0)
            FROM dms_gl_entries e
            JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
            WHERE e.org_id=? AND e.account_id=?
              AND DATE(j.posted_at) <= ?
        ");
        $q->execute([$orgId, $accountId, $to]);
        return (float)$q->fetchColumn();
    }

    private function sumBetweenMany(PDO $pdo, int $orgId, array $accountIds, string $from, string $to): float
    {
        $ids = array_values(array_filter(array_map('intval', $accountIds), fn($x)=>$x>0));
        if (!$ids) return 0.0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
            SELECT COALESCE(SUM(e.dr - e.cr),0)
            FROM dms_gl_entries e
            JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
            WHERE e.org_id=?
              AND DATE(j.posted_at) BETWEEN ? AND ?
              AND e.account_id IN ($in)
        ";
        $q = $pdo->prepare($sql);
        $q->execute(array_merge([$orgId, $from, $to], $ids));
        return (float)$q->fetchColumn();
    }

    private function balToMany(PDO $pdo, int $orgId, array $accountIds, string $to): float
    {
        $ids = array_values(array_filter(array_map('intval', $accountIds), fn($x)=>$x>0));
        if (!$ids) return 0.0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
            SELECT COALESCE(SUM(e.dr - e.cr),0)
            FROM dms_gl_entries e
            JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
            WHERE e.org_id=?
              AND DATE(j.posted_at) <= ?
              AND e.account_id IN ($in)
        ";
        $q = $pdo->prepare($sql);
        $q->execute(array_merge([$orgId, $to], $ids));
        return (float)$q->fetchColumn();
    }

    private function seriesMany(PDO $pdo, int $orgId, array $accountIds, string $from, string $to): array
    {
        $ids = array_values(array_filter(array_map('intval', $accountIds), fn($x)=>$x>0));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "
            SELECT DATE(j.posted_at) AS d, ROUND(COLESCE(SUM(e.dr - e.cr),0),2) AS delta
            FROM dms_gl_entries e
            JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
            WHERE e.org_id=?
              AND DATE(j.posted_at) BETWEEN ? AND ?
              AND e.account_id IN ($in)
            GROUP BY DATE(j.posted_at)
            ORDER BY DATE(j.posted_at)
        ";
        // Fix COALESCE typo (Percona is strict)
        $sql = str_replace('COLESCE', 'COALESCE', $sql);

        $q = $pdo->prepare($sql);
        $q->execute(array_merge([$orgId, $from, $to], $ids));
        $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $res = [];
        foreach ($rows as $r) $res[$r['d']] = (float)$r['delta'];

        $out=[]; $d=new DateTimeImmutable($from); $end=new DateTimeImmutable($to);
        while($d <= $end){ $k=$d->format('Y-m-d'); $out[$k]=$res[$k]??0.0; $d=$d->modify('+1 day'); }
        return $out;
    }

    /* =========================================================================
     *                                DASHBOARD
     * ========================================================================= */

    public function index(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);
        $this->ensureOwnerOrSuperAdmin($c);

        try {
            $pdo   = $this->pdo();
            $orgId = $this->orgIdFromCtx($c);
            if ($orgId <= 0) { $this->abort500('No org id in context.'); return; }

            // Required tables
            foreach (['dms_gl_entries','dms_gl_journals','dms_gl_accounts'] as $t) {
                if (!$this->hasTable($pdo, $t)) {
                    $this->view('accounts/dashboard', [
                        'title'=>'Accounting Dashboard',
                        'kpi'=>[],
                        'trend'=>['labels'=>[],'sales'=>[],'receipts'=>[]],
                        'active'=>'accounts','subactive'=>'accounts.dashboard'
                    ], $c);
                    return;
                }
            }

            // Date anchors (local DB date)
            $today  = (new DateTimeImmutable('today'))->format('Y-m-d');
            $from14 = (new DateTimeImmutable('today -13 days'))->format('Y-m-d');
            $mStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');

            // Resolve accounts
            $keys     = $this->resolveKeyAccounts($pdo, $orgId);
            $cashLike = array_values(array_filter([$keys['cash'],$keys['bank'],$keys['mobile']], fn($x)=>(int)$x>0));

            // Collect “many” ids for Revenue & COGS
            $revIds  = $this->revenueAccountIds($pdo, $orgId);
            $cogsIds = $this->cogsAccountIds($pdo, $orgId);
            // Fallback to single mapped COGS if no set found
            if (!$cogsIds && !empty($keys['cogs'])) $cogsIds = [$keys['cogs']];

            // KPIs (Revenue is credit-nature → flip sign)
            $salesMTD   = $revIds  ? -1 * $this->sumBetweenMany($pdo, $orgId, $revIds,  $mStart, $today) : 0.0;
            $cogsMTD    = $cogsIds ?       $this->sumBetweenMany($pdo, $orgId, $cogsIds, $mStart, $today) : 0.0;
            $gpMTD      = $salesMTD - $cogsMTD;

            $cashBal    = $cashLike ? $this->balToMany($pdo, $orgId, $cashLike, $today) : 0.0;
            $arBal      = $keys['ar'] ? $this->balTo($pdo, $orgId, $keys['ar'], $today) : 0.0;
            $apRaw      = $keys['ap'] ? $this->balTo($pdo, $orgId, $keys['ap'], $today) : 0.0;
            $apBal      = $apRaw < 0 ? abs($apRaw) : 0.0;

            $salesToday = $revIds  ? -1 * $this->sumBetweenMany($pdo, $orgId, $revIds,  $today, $today) : 0.0;
            $cogsToday  = $cogsIds ?       $this->sumBetweenMany($pdo, $orgId, $cogsIds, $today, $today) : 0.0;

            // Trends (last 14 days)
            $trendSales    = $revIds   ? $this->seriesMany($pdo, $orgId, $revIds,   $from14, $today) : [];
            $trendReceipts = $cashLike ? $this->seriesMany($pdo, $orgId, $cashLike, $from14, $today) : [];

            // Labels: prefer sales series, else receipts, else generate day list
            $labels = array_keys($trendSales ?: $trendReceipts);
            if (!$labels) {
                $d=new DateTimeImmutable($from14); $end=new DateTimeImmutable($today);
                while($d <= $end){ $labels[]=$d->format('Y-m-d'); $d=$d->modify('+1 day'); }
            }

            $this->view('accounts/dashboard', [
                'title'         => 'Accounting Dashboard',
                'module_base'   => $this->moduleBase($c) ?? '',
                'head_includes' => [''],
                'kpi'           => [
                    'sales_mtd'   => $salesMTD,
                    'cogs_mtd'    => $cogsMTD,
                    'gp_mtd'      => $gpMTD,
                    'cash_bal'    => $cashBal,
                    'ar_bal'      => $arBal,
                    'ap_bal'      => $apBal,
                    'sales_today' => $salesToday,
                    'cogs_today'  => $cogsToday,
                ],
                'trend'         => [
                    'labels'   => $labels,
                    'sales'    => array_values($trendSales ?: array_fill_keys($labels, 0.0)),
                    'receipts' => array_values($trendReceipts ?: array_fill_keys($labels, 0.0)),
                ],
                'active'        => 'accounts',
                'subactive'     => 'accounts.dashboard',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Accounting dashboard error: '.$e->getMessage());
        }
    }

    /* =========================================================================
     *                            BOOKS (GL-NATIVE)
     * ========================================================================= */

    public function cashBook(?array $ctx = null): void
    {
        $this->ensureOwnerOrSuperAdmin($this->ctx($ctx));
        $this->renderBookGL($ctx, 'cash', 'Cash Book', 'accounts/cash-book', $this->singleMappedAccountList($ctx, 'cash'));
    }

    public function bankBook(?array $ctx = null): void
    {
        $this->ensureOwnerOrSuperAdmin($this->ctx($ctx));
        $list = $this->bankAccountsList($ctx);
        if (!$list) $list = $this->singleMappedAccountList($ctx, 'bank');
        $this->renderBookGL($ctx, 'bank', 'Bank Book', 'accounts/bank-book', $list);
    }

    public function mobileBankBook(?array $ctx = null): void
    {
        $this->ensureOwnerOrSuperAdmin($this->ctx($ctx));
        $list = $this->mobileAccountsList($ctx);
        if (!$list) $list = $this->singleMappedAccountList($ctx, 'mobile');
        $this->renderBookGL($ctx, 'mobile', 'Mobile Bank Book', 'accounts/mobile-bank-book', $list);
    }

    private function renderBookGL(?array $ctx, string $mapKey, string $title, string $view, array $accountList): void
    {
        $c = $this->ctx($ctx);
        try {
            $pdo   = $this->pdo();
            $orgId = $this->orgIdFromCtx($c);
            if ($orgId <= 0) { $this->abort500('No org id in context.'); return; }

            $from = $_GET['from'] ?? date('Y-m-01');
            $to   = $_GET['to']   ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

            $selected = (int)($_GET['account_id'] ?? 0);
            if ($selected <= 0 && $accountList) $selected = (int)$accountList[0]['id'];

            // Opening balance (to day before from)
            $opening = 0.0;
            if ($selected > 0) {
                $fromMinus1 = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
                $opening = $this->balTo($pdo, $orgId, $selected, $fromMinus1);
            }

            // Rows
            $rows = [];
            if ($selected > 0) {
                $q = $pdo->prepare("
                    SELECT 
                      DATE(j.posted_at) AS jdate,
                      j.jno,
                      j.jtype,
                      COALESCE(e.memo, j.memo) AS memo,
                      ROUND(e.dr,2) AS dr, ROUND(e.cr,2) AS cr,
                      (e.dr - e.cr) AS delta,
                      e.id AS entry_id
                    FROM dms_gl_entries e
                    JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
                    WHERE e.org_id=? AND e.account_id=? AND DATE(j.posted_at) BETWEEN ? AND ?
                    ORDER BY j.posted_at, j.id, e.id
                ");
                $q->execute([$orgId, $selected, $from, $to]);
                $tmp = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $run = $opening;
                foreach ($tmp as $r) {
                    $run += (float)$r['delta'];
                    $r['running'] = round($run, 2);
                    $rows[] = $r;
                }
            }

            $this->view($view, [
                'title'         => $title,
                'head_includes' => [''],
                'from'          => $from,
                'to'            => $to,
                'opening'       => round($opening, 2),
                'rows'          => $rows,
                'accountList'   => $accountList,
                'selectedAcc'   => $selected,
                'active'        => 'accounts',
                'subactive'     => 'accounts.dashboard',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Accounting book error: '.$e->getMessage());
        }
    }

    /* =========================================================================
     *                            ACCOUNT PICKERS
     * ========================================================================= */

    private function singleMappedAccountList(?array $ctx, string $mapKey): array
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgIdFromCtx($this->ctx($ctx));
        try {
            $s = $pdo->prepare("
                SELECT m.account_id, a.name
                FROM dms_account_map m
                JOIN dms_gl_accounts a ON a.id=m.account_id AND a.org_id=m.org_id
                WHERE m.org_id=? AND m.map_key=? LIMIT 1
            ");
            $s->execute([$orgId, $mapKey]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row ? [['id'=>(int)$row['account_id'], 'label'=>(string)$row['name']]] : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function bankAccountsList(?array $ctx): array
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgIdFromCtx($this->ctx($ctx));
        if (!$this->hasTable($pdo, 'dms_bank_accounts')) return [];
        try {
            $s = $pdo->prepare("
                SELECT DISTINCT gl_account_id AS id,
                       TRIM(CONCAT(COALESCE(bank_name,''),' ',COALESCE(account_name,''))) AS label
                FROM dms_bank_accounts
                WHERE org_id=? AND COALESCE(gl_account_id,0) > 0
                ORDER BY label
            ");
            $s->execute([$orgId]);
            $out=[];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $id=(int)$r['id']; if ($id>0) $out[]=['id'=>$id,'label'=>(string)$r['label']];
            }
            return $out;
        } catch (\Throwable) { return []; }
    }

    private function mobileAccountsList(?array $ctx): array
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgIdFromCtx($this->ctx($ctx));
        if (!$this->hasTable($pdo, 'dms_mobile_accounts')) return [];
        try {
            $s = $pdo->prepare("
                SELECT DISTINCT gl_account_id AS id, name AS label
                FROM dms_mobile_accounts
                WHERE org_id=? AND COALESCE(gl_account_id,0) > 0
                ORDER BY name
            ");
            $s->execute([$orgId]);
            $out=[];
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $id=(int)$r['id']; if ($id>0) $out[]=['id'=>$id,'label'=>(string)$r['label']];
            }
            return $out;
        } catch (\Throwable) { return []; }
    }

    /* =========================================================================
     *                               ROLE GUARD
     * ========================================================================= */

    private function ensureOwnerOrSuperAdmin(array $ctx): void
    {
        $role = '';
        if (isset($ctx['user']['role']))                 $role = (string)$ctx['user']['role'];
        elseif (isset($_SESSION['tenant_user']['role'])) $role = (string)$_SESSION['tenant_user']['role'];

        $role = strtolower($role);
        if (!in_array($role, ['owner', 'super_admin'], true)) {
            http_response_code(403);
            echo 'Forbidden: accounting dashboard is restricted to owner or super admin.';
            exit;
        }
    }
}