<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use Shared\DB;
use DateTimeImmutable;
use PDO;

final class DashboardController extends BaseController
{
    public function index(array $ctx = []): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = DB::pdo();
        $orgId = (int)($c['org_id'] ?? 0);

        $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $monthEnd   = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');

        /* ---------------- helpers: schema-safe ---------------- */

        $tableExists = function(string $t) use ($pdo): bool {
            try {
                $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
                $q->execute([$t]); return (bool)$q->fetchColumn();
            } catch (\Throwable $e) { return false; }
        };
        $colExists = function(string $t,string $c) use ($pdo): bool {
            try {
                $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
                $q->execute([$t,$c]); return (bool)$q->fetchColumn();
            } catch (\Throwable $e) { return false; }
        };
        $scalar = function(string $sql,array $p=[], $fallback=0) use ($pdo) {
            try { $st=$pdo->prepare($sql); $st->execute($p); $v=$st->fetchColumn(); return is_numeric($v)?+$v:$fallback; }
            catch (\Throwable $e) { return $fallback; }
        };
        $rows = function(string $sql,array $p=[]) use ($pdo): array {
            try { $st=$pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; }
            catch (\Throwable $e) { return []; }
        };
        $count = function(string $t) use ($tableExists,$scalar,$orgId): int {
            if (!$tableExists($t)) return 0;
            return (int)$scalar("SELECT COUNT(*) FROM {$t} WHERE org_id=?",[$orgId],0);
        };

        /* ---------------- suppliers counter (no dealers) ---------------- */

        $countSuppliers = function() use ($tableExists,$colExists,$scalar,$orgId): int {
            // Preferred: dms_suppliers
            if ($tableExists('dms_suppliers')) {
                return (int)$scalar("SELECT COUNT(*) FROM dms_suppliers WHERE org_id=?",[$orgId],0);
            }
            // Alternative: dms_vendors
            if ($tableExists('dms_vendors')) {
                return (int)$scalar("SELECT COUNT(*) FROM dms_vendors WHERE org_id=?",[$orgId],0);
            }
            // Legacy catch-all: dms_stakeholders filtered by role/name
            if ($tableExists('dms_stakeholders')) {
                $roleCol = $colExists('dms_stakeholders','role') ? 'role' : null;
                $typeCol = $colExists('dms_stakeholders','type') ? 'type' : null;
                if ($roleCol || $typeCol) {
                    $col = $roleCol ?: $typeCol;
                    return (int)$scalar(
                        "SELECT COUNT(*) FROM dms_stakeholders WHERE org_id=? AND LOWER($col) IN ('supplier','suppliers','vendor','vendors')",
                        [$orgId],0
                    );
                }
                // if no role/type columns, just return 0 (can’t infer)
            }
            return 0;
        };

        /* --------------- pick date/number columns per table --------------- */

        $pickDateCol = function(string $t, array $cands) use ($colExists) {
            foreach ($cands as $c) { if ($colExists($t,$c)) return $c; }
            return null;
        };
        $pickAmtCol = function(string $t, array $cands) use ($colExists) {
            foreach ($cands as $c) { if ($colExists($t,$c)) return $c; }
            return null;
        };

        $salesDate   = $pickDateCol('dms_sales',     ['jdate','invoice_date','date']);
        $salesTotal  = $pickAmtCol ('dms_sales',     ['grand_total','total','net_total']);
        $purchDate   = $pickDateCol('dms_purchases', ['jdate','bill_date','date']);
        $purchTotal  = $pickAmtCol ('dms_purchases', ['grand_total','total','net_total']);
        $expDate     = $pickDateCol('dms_expenses',  ['expense_date','jdate','date']);
        $expAmount   = $pickAmtCol ('dms_expenses',  ['amount','total']);

        /* ------------------ cards: counts + MTD amounts ------------------ */

        $counts = [
            'products'   => $count('dms_products'),
            'customers'  => $count('dms_customers'),
            'suppliers'  => $countSuppliers(),
            'sales'      => 0,
            'purchases'  => 0,
        ];

        $totals = ['sales_value'=>0.0,'purchase_value'=>0.0,'expenses'=>0.0];

        if ($tableExists('dms_sales') && $salesDate) {
            $counts['sales'] = (int)$scalar(
                "SELECT COUNT(*) FROM dms_sales WHERE org_id=? AND {$salesDate} BETWEEN ? AND ?",
                [$orgId,$monthStart,$monthEnd],0
            );
            if ($salesTotal) {
                $totals['sales_value'] = (float)$scalar(
                    "SELECT COALESCE(SUM({$salesTotal}),0) FROM dms_sales WHERE org_id=? AND {$salesDate} BETWEEN ? AND ?",
                    [$orgId,$monthStart,$monthEnd],0.0
                );
            }
        }

        if ($tableExists('dms_purchases') && $purchDate) {
            $counts['purchases'] = (int)$scalar(
                "SELECT COUNT(*) FROM dms_purchases WHERE org_id=? AND {$purchDate} BETWEEN ? AND ?",
                [$orgId,$monthStart,$monthEnd],0
            );
            if ($purchTotal) {
                $totals['purchase_value'] = (float)$scalar(
                    "SELECT COALESCE(SUM({$purchTotal}),0) FROM dms_purchases WHERE org_id=? AND {$purchDate} BETWEEN ? AND ?",
                    [$orgId,$monthStart,$monthEnd],0.0
                );
            }
        }

        if ($tableExists('dms_expenses') && $expDate && $expAmount) {
            $totals['expenses'] = (float)$scalar(
                "SELECT COALESCE(SUM({$expAmount}),0) FROM dms_expenses WHERE org_id=? AND {$expDate} BETWEEN ? AND ?",
                [$orgId,$monthStart,$monthEnd],0.0
            );
        }

        /* ----------------------- top customers (MTD) ---------------------- */

        $topCustomers=[];
        if ($tableExists('dms_sales') && $tableExists('dms_customers') && $salesDate && $salesTotal && $colExists('dms_sales','customer_id')) {
            $topCustomers = $rows(
                "SELECT c.name, SUM(s.{$salesTotal}) AS total
                   FROM dms_sales s
                   JOIN dms_customers c ON c.id=s.customer_id AND c.org_id=s.org_id
                  WHERE s.org_id=? AND s.{$salesDate} BETWEEN ? AND ?
                  GROUP BY c.id,c.name
                  ORDER BY total DESC
                  LIMIT 5",
                [$orgId,$monthStart,$monthEnd]
            );
        }

        /* ----------------------- low stock (live) ------------------------- */

        $lowStock=[];
        if ($tableExists('dms_products')) {
            $qtyCol = $colExists('dms_products','quantity')    ? 'quantity'
                    : ($colExists('dms_products','qty_on_hand') ? 'qty_on_hand'
                    : ($colExists('dms_products','stock_qty')   ? 'stock_qty' : null));
            $reoCol = $colExists('dms_products','reorder_level') ? 'reorder_level'
                    : ($colExists('dms_products','reorder')       ? 'reorder' : null);
            if ($qtyCol && $reoCol) {
                $lowStock = $rows(
                    "SELECT IFNULL(sku,'') AS sku, name, {$qtyCol} AS qty, {$reoCol} AS reorder_level
                       FROM dms_products
                      WHERE org_id=? AND {$qtyCol} <= {$reoCol}
                      ORDER BY {$qtyCol} ASC
                      LIMIT 10",
                    [$orgId]
                );
            }
        }

        /* -------------------- liquidity & balances ------------------------ */

        $financials = ['cash'=>0.0,'bank'=>0.0,'ar'=>0.0,'ap'=>0.0];
        $glOk = $tableExists('dms_gl_entries') && $tableExists('dms_gl_journals') && $tableExists('dms_gl_accounts');

        if ($glOk) {
            $idFor = function(string $key) use ($rows,$orgId): int {
                $map = $rows("SELECT account_id FROM dms_account_map WHERE org_id=? AND map_key=?",[$orgId,$key]);
                return (int)($map[0]['account_id'] ?? 0);
            };
            $accId = [
                'cash' => $idFor('cash'),
                'bank' => $idFor('bank'),
                'ar'   => $idFor('ar'),
                'ap'   => $idFor('ap'),
            ];
            // infer if missing
            if (!$accId['cash'] || !$accId['bank'] || !$accId['ar'] || !$accId['ap']) {
                $list = $rows("SELECT id,LOWER(type) AS t,LOWER(name) AS n FROM dms_gl_accounts WHERE org_id=?",[$orgId]);
                foreach ($list as $a) {
                    if (!$accId['cash'] && (str_contains($a['t'],'cash') || str_contains($a['n'],'cash')))   $accId['cash']=(int)$a['id'];
                    if (!$accId['bank'] && (str_contains($a['t'],'bank') || str_contains($a['n'],'bank')))   $accId['bank']=(int)$a['id'];
                    if (!$accId['ar']   && (str_contains($a['t'],'receivable') || str_contains($a['n'],'receivable'))) $accId['ar']=(int)$a['id'];
                    if (!$accId['ap']   && (str_contains($a['t'],'payable')    || str_contains($a['n'],'payable')))    $accId['ap']=(int)$a['id'];
                }
            }
            $balance = function(int $accId) use ($scalar,$orgId): float {
                if ($accId<=0) return 0.0;
                return (float)$scalar(
                    "SELECT COALESCE(SUM(e.dr - e.cr),0)
                       FROM dms_gl_entries e
                       JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
                      WHERE e.org_id=? AND e.account_id=?",
                    [$orgId,$accId],0.0
                );
            };
            $financials = [
                'cash'=>$balance($accId['cash']),
                'bank'=>$balance($accId['bank']),
                'ar'  =>$balance($accId['ar']),
                'ap'  =>$balance($accId['ap']),
            ];
        } elseif ($tableExists('dms_bank_accounts') && $colExists('dms_bank_accounts','current_balance')) {
            $financials['bank'] = (float)$scalar(
                "SELECT COALESCE(SUM(current_balance),0) FROM dms_bank_accounts WHERE org_id=?",
                [$orgId],0.0
            );
        }

        /* ----------------------- recent activity -------------------------- */

        $recent = [];
        if ($tableExists('dms_sales') && $salesDate && $salesTotal) {
            $recent = array_merge($recent, $rows(
                "SELECT 'Sale' AS type, IFNULL(invoice_no,'') AS ref, {$salesTotal} AS amount, {$salesDate} AS date
                   FROM dms_sales WHERE org_id=? ORDER BY {$salesDate} DESC LIMIT 10",
                [$orgId]
            ));
        }
        if ($tableExists('dms_purchases') && $purchDate && $purchTotal) {
            $recent = array_merge($recent, $rows(
                "SELECT 'Purchase' AS type, IFNULL(invoice_no,'') AS ref, {$purchTotal} AS amount, {$purchDate} AS date
                   FROM dms_purchases WHERE org_id=? ORDER BY {$purchDate} DESC LIMIT 10",
                [$orgId]
            ));
        }
        if ($tableExists('dms_expenses') && $expDate && $expAmount) {
            $refCol = $colExists('dms_expenses','expense_no') ? 'expense_no'
                   : ($colExists('dms_expenses','ref_no')     ? 'ref_no' : "''");
            $recent = array_merge($recent, $rows(
                "SELECT 'Expense' AS type, {$refCol} AS ref, {$expAmount} AS amount, {$expDate} AS date
                   FROM dms_expenses WHERE org_id=? ORDER BY {$expDate} DESC LIMIT 10",
                [$orgId]
            ));
        }
        usort($recent, fn($a,$b)=>strcmp((string)$b['date'], (string)$a['date']));
        if (count($recent)>10) $recent=array_slice($recent,0,10);

        /* --------------------------- render ------------------------------- */

        $this->view('dashboard/index', [
            'title'        => 'DMS Dashboard',
            'counts'       => $counts,          // products, customers, suppliers, sales, purchases
            'totals'       => $totals,          // MTD values
            'financials'   => $financials,      // cash/bank/ar/ap
            'topCustomers' => $topCustomers,
            'lowStock'     => $lowStock,
            'recent'       => $recent,
            'org'          => $c['org'],
            'slug'         => $c['slug'],
            'module_base'  => $c['module_base'],
            'period'       => ['from'=>$monthStart,'to'=>$monthEnd],
        ], $c);
    }
}