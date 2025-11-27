<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class DashboardController extends BaseController
{
    /** GET /apps/pos/dashboard (aliases: /posdashboard, /maindash) */
    public function app(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $dir   = rtrim((string)($c['module_dir'] ?? __DIR__ . '/../../'), '/');
        $base  = (string)($c['module_base'] ?? '/apps/pos');
        $title = 'POS — Transactional Dashboard';

        $shell = $dir . '/Views/shared/layouts/shell.php';
        $body  = $dir . '/Views/posdashboard/index.php';
        if (!is_file($body)) {
            $alt = $dir . '/Views/posdashboard/app.php';
            if (is_file($alt)) {
                $body = $alt;
            }
        }

        // ---- range from query: 14d (default), 1m, 3m ----
        $range = strtolower((string)($_GET['range'] ?? '14d'));
        if (!in_array($range, ['14d', '1m', '3m'], true)) {
            $range = '14d';
        }

        // ---- gather data (soft if org missing) ----
        $kpis         = ['today' => 0, 'month' => 0, 'totalSales' => 0, 'totalOrders' => 0];
        $chart        = ['labels' => [], 'values' => []];
        $topProducts  = [];
        $recentOrders = [];

        try {
            $orgId    = (int)($c['org_id'] ?? 0);
            $branchId = $this->currentBranchId(); // 0 = all branches

            if ($orgId > 0) {
                [$kpis, $chart, $topProducts, $recentOrders] = $this->loadData(
                    $this->pdo(),
                    $orgId,
                    $branchId,
                    $range
                );
            }
        } catch (Throwable $e) {
            error_log('[POS] dashboard data load failed: ' . $e->getMessage());
        }

        // ---- render ----
        try {
            if (!is_file($shell)) {
                throw new \RuntimeException("Shell missing: {$shell}");
            }
            if (!is_file($body)) {
                throw new \RuntimeException("Dashboard view missing: {$body}");
            }

            // Try $slot layout
            try {
                $slot = (static function (array $__vars, string $__view) {
                    extract($__vars, EXTR_SKIP);
                    ob_start();
                    require $__view;
                    return ob_get_clean();
                })([
                    'title'        => $title,
                    'brandColor'   => '#228B22',
                    'base'         => $base,
                    'ctx'          => $c,
                    'kpis'         => $kpis,
                    'chart'        => $chart,
                    'topProducts'  => $topProducts,
                    'recentOrders' => $recentOrders,
                    'range'        => $range,
                ], $body);

                (static function (array $__vars, string $__shell, string $__slot) {
                    extract($__vars, EXTR_SKIP);
                    $slot          = $__slot;
                    $moduleSidenav = null;
                    $_sidenav      = '';
                    $_content      = '';
                    require $__shell;
                })([
                    'title'      => $title,
                    'brandColor' => '#228B22',
                    'base'       => $base,
                    'ctx'        => $c,
                ], $shell, $slot);

                return;
            } catch (Throwable $ignored) {
                // fallback below
            }

            // Fallback: legacy $_content shell
            (static function (array $__vars, string $__shell, string $__content) {
                extract($__vars, EXTR_SKIP);
                $moduleSidenav = null;
                $_sidenav      = '';
                $_content      = $__content;
                require $__shell;
            })([
                'title'        => $title,
                'brandColor'   => '#228B22',
                'base'         => $base,
                'ctx'          => $c,
                'kpis'         => $kpis,
                'chart'        => $chart,
                'topProducts'  => $topProducts,
                'recentOrders' => $recentOrders,
                'range'        => $range,
            ], $shell, $body);
        } catch (Throwable $e) {
            $this->oops('POS Dashboard render failed', $e);
        }
    }

    /**
     * Load KPIs + chart + top products + recent orders.
     * Branch-aware (if branch_id column exists and $branchId > 0).
     * Range: 14d | 1m | 3m
     */
    protected function loadData(PDO $pdo, int $orgId, int $branchId = 0, string $range = '14d'): array
    {
        // use local helper tryPickTable() – does NOT override BaseController::pickTable()
        $salesTbl     = $this->tryPickTable($pdo, ['pos_sales', 'sales', 'dms_sales']);
        $saleItemsTbl = $this->tryPickTable($pdo, ['pos_sale_items', 'sale_items', 'dms_sale_items']);
        $productsTbl  = $this->tryPickTable($pdo, ['pos_products', 'products', 'dms_products']);
        $customersTbl = $this->tryPickTable($pdo, ['pos_customers', 'customers', 'dms_customers']);

        if (!$salesTbl) {
            return [
                ['today' => 0, 'month' => 0, 'totalSales' => 0, 'totalOrders' => 0],
                ['labels' => [], 'values' => []],
                [],
                [],
            ];
        }

        $colDate   = $this->pickColumn($pdo, $salesTbl, ['sale_date', 'date', 'created_at', 'posted_at', 'createdOn']);
        $colTotal  = $this->pickColumn($pdo, $salesTbl, ['total_amount', 'grand_total', 'net_total', 'amount', 'total']);
        $colId     = $this->pickColumn($pdo, $salesTbl, ['id', 'sale_id', 'doc_no', 'code']);
        $colCustId = $this->pickColumn($pdo, $salesTbl, ['customer_id', 'customerId', 'cust_id']);

        // ---- base WHERE (org + optional branch) ----
        $whereBase = 'org_id = :o';
        $bindBase  = [':o' => $orgId];

        $hasBranchCol = $this->hasCol($pdo, $salesTbl, 'branch_id');
        if ($branchId > 0 && $hasBranchCol) {
            $whereBase .= ' AND branch_id = :b';
            $bindBase[':b'] = $branchId;
        }

        // ---- KPIs ----
        $kToday = $this->valSafe(
            $pdo,
            "SELECT COALESCE(SUM($colTotal),0)
             FROM $salesTbl
             WHERE $whereBase AND DATE($colDate) = CURRENT_DATE()",
            $bindBase
        );

        $kMonth = $this->valSafe(
            $pdo,
            "SELECT COALESCE(SUM($colTotal),0)
             FROM $salesTbl
             WHERE $whereBase
               AND YEAR($colDate) = YEAR(CURRENT_DATE())
               AND MONTH($colDate) = MONTH(CURRENT_DATE())",
            $bindBase
        );

        $kTotal = $this->valSafe(
            $pdo,
            "SELECT COALESCE(SUM($colTotal),0)
             FROM $salesTbl
             WHERE $whereBase",
            $bindBase
        );

        $kOrders = (int)$this->valSafe(
            $pdo,
            "SELECT COUNT(*)
             FROM $salesTbl
             WHERE $whereBase",
            $bindBase
        );

        // ---- Range → dateFrom ----
        $days = match ($range) {
            '1m' => 29,
            '3m' => 89,
            default => 13, // 14d
        };
        $dateFrom = (new \DateTimeImmutable("-{$days} day"))->format('Y-m-d');

        // ---- Chart data (group by date) ----
        $chart = ['labels' => [], 'values' => []];

        try {
            $bind          = $bindBase;
            $bind[':from'] = $dateFrom;

            $sql = "SELECT DATE($colDate) d, COALESCE(SUM($colTotal),0) t
                    FROM $salesTbl
                    WHERE $whereBase AND DATE($colDate) >= :from
                    GROUP BY DATE($colDate)
                    ORDER BY d ASC";

            $st   = $pdo->prepare($sql);
            $st->execute($bind);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $map = [];
            foreach ($rows as $r) {
                $map[$r['d']] = (float)$r['t'];
            }

            for ($i = $days; $i >= 0; $i--) {
                $d = (new \DateTimeImmutable("-{$i} day"))->format('Y-m-d');
                $chart['labels'][] = $d;
                $chart['values'][] = (float)($map[$d] ?? 0);
            }
        } catch (Throwable $e) {
            // silent
        }

        // ---- Top products ----
        $topProducts = [];
        if ($saleItemsTbl) {
            $itSaleId = $this->pickColumn($pdo, $saleItemsTbl, ['sale_id', 'sales_id', 'pos_sale_id', 'doc_id', 'saleId']);
            $itProdId = $this->pickColumn($pdo, $saleItemsTbl, ['product_id', 'prod_id', 'item_id']);
            $itQty    = $this->pickColumn($pdo, $saleItemsTbl, ['qty', 'quantity', 'qty_sold', 'sold_qty']);
            $prodName = $productsTbl
                ? $this->pickColumn($pdo, $productsTbl, ['name', 'title', 'product_name'])
                : null;

            try {
                $whereJoin = 's.org_id = :o';
                $bindTP    = [':o' => $orgId];
                if ($branchId > 0 && $hasBranchCol) {
                    $whereJoin .= ' AND s.branch_id = :b';
                    $bindTP[':b'] = $branchId;
                }

                if ($productsTbl && $prodName) {
                    $sql = "
                        SELECT p.$prodName AS name, SUM(i.$itQty) qty
                        FROM $saleItemsTbl i
                        JOIN $salesTbl s
                          ON s.$colId = i.$itSaleId
                         AND $whereJoin
                        JOIN $productsTbl p
                          ON p.id = i.$itProdId
                        GROUP BY p.$prodName
                        ORDER BY qty DESC
                        LIMIT 4";
                } else {
                    $sql = "
                        SELECT i.$itProdId AS name, SUM(i.$itQty) qty
                        FROM $saleItemsTbl i
                        JOIN $salesTbl s
                          ON s.$colId = i.$itSaleId
                         AND $whereJoin
                        GROUP BY i.$itProdId
                        ORDER BY qty DESC
                        LIMIT 4";
                }

                $st   = $pdo->prepare($sql);
                $st->execute($bindTP);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $topProducts = array_map(
                    static fn(array $r): array => [
                        'name' => (string)$r['name'],
                        'qty'  => (float)$r['qty'],
                    ],
                    $rows
                );
            } catch (Throwable $e) {
                // ignore
            }
        }

        // ---- Recent orders ----
        $recentOrders = [];
        try {
            $custNameCol = $customersTbl
                ? $this->pickColumn($pdo, $customersTbl, ['name', 'full_name', 'customer_name'])
                : null;

            $whereJoin = 's.org_id = :o';
            $bindRO    = [':o' => $orgId];
            if ($branchId > 0 && $hasBranchCol) {
                $whereJoin .= ' AND s.branch_id = :b';
                $bindRO[':b'] = $branchId;
            }

            if ($customersTbl && $colCustId && $custNameCol) {
                $sql = "
                    SELECT s.$colId no,
                           DATE(s.$colDate) d,
                           COALESCE(c.$custNameCol,'') AS customer,
                           COALESCE(s.$colTotal,0) AS total
                    FROM $salesTbl s
                    LEFT JOIN $customersTbl c
                      ON c.id = s.$colCustId
                    WHERE $whereJoin
                    ORDER BY s.$colDate DESC
                    LIMIT 8";
            } else {
                $sql = "
                    SELECT s.$colId no,
                           DATE(s.$colDate) d,
                           '' AS customer,
                           COALESCE(s.$colTotal,0) AS total
                    FROM $salesTbl s
                    WHERE $whereJoin
                    ORDER BY s.$colDate DESC
                    LIMIT 8";
            }

            $st   = $pdo->prepare($sql);
            $st->execute($bindRO);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $recentOrders = array_map(
                static fn(array $r): array => [
                    'no'       => $r['no'],
                    'date'     => $r['d'],
                    'customer' => $r['customer'],
                    'total'    => (float)$r['total'],
                ],
                $rows
            );
        } catch (Throwable $e) {
            // ignore
        }

        return [
            [
                'today'       => (float)$kToday,
                'month'       => (float)$kMonth,
                'totalSales'  => (float)$kTotal,
                'totalOrders' => $kOrders,
            ],
            $chart,
            $topProducts,
            $recentOrders,
        ];
    }

    /* ---------- local helpers (NO name clash with BaseController) ---------- */

    /** Soft table resolver – returns first existing table or null. */
    protected function tryPickTable(PDO $pdo, array $names): ?string
    {
        foreach ($names as $t) {
            if ($this->hasTable($pdo, $t)) {
                return $t;
            }
        }
        return null;
    }

    /** Try candidate columns on a table and return the first that works. */
    protected function pickColumn(PDO $pdo, string $table, array $candidates): string
    {
        foreach ($candidates as $c) {
            try {
                $pdo->query("SELECT `$c` FROM `$table` LIMIT 1");
                return $c;
            } catch (Throwable $e) {
                // keep trying
            }
        }
        return $candidates[0];
    }

    /** Value helper that never throws. */
    protected function valSafe(PDO $pdo, string $sql, array $bind = [])
    {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($bind);
            $r = $st->fetch(PDO::FETCH_NUM);
            return $r === false ? 0 : $r[0];
        } catch (Throwable $e) {
            return 0;
        }
    }
}