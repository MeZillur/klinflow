<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

final class OrdersController extends BaseController
{
    /**
     * GET /apps/bizflow/orders
     * GET /t/{slug}/apps/bizflow/orders
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c      = $this->ctx($ctx ?? []);
            $orgId  = $this->requireOrg();
            $pdo    = $this->pdo();

            $moduleBase = $c['module_base'] ?? '/apps/bizflow';
            $org        = $c['org'] ?? [];

            $q        = trim((string)($_GET['q']       ?? ''));
            $status   = trim((string)($_GET['status']  ?? ''));
            $from     = trim((string)($_GET['from']    ?? ''));
            $to       = trim((string)($_GET['to']      ?? ''));
            $onlyOpen = !empty($_GET['only_open']);

            $orders  = [];
            $metrics = [
                'order_count'      => 0,
                'open_count'       => 0,
                'draft_count'      => 0,
                'cancelled_count'  => 0,
                'total_value'      => 0.0,
                'open_value'       => 0.0,
            ];

            if ($this->hasTable($pdo, 'biz_orders')) {

                // Handle missing expected_ship_date safely
                $expectedExpr = $this->hasCol($pdo, 'biz_orders', 'expected_ship_date')
                    ? 'o.expected_ship_date'
                    : 'NULL';

                $sql  = "
                    SELECT
                        o.id,
                        o.org_id,
                        o.order_no,
                        o.external_ref,
                        o.status,
                        o.date,
                        {$expectedExpr} AS expected_ship_date,
                        o.grand_total,
                        o.currency,
                        o.created_at,
                        c.name AS customer_name
                    FROM biz_orders o
                    LEFT JOIN biz_customers c
                           ON c.id = o.customer_id
                          AND c.org_id = o.org_id
                    WHERE o.org_id = :org_id
                ";
                $bind = ['org_id' => $orgId];

                if ($q !== '') {
                    $sql .= "
                      AND (
                            o.order_no    LIKE :q
                         OR o.external_ref LIKE :q
                         OR c.name        LIKE :q
                      )
                    ";
                    $bind['q'] = '%'.$q.'%';
                }

                if ($status !== '') {
                    $sql .= " AND o.status = :status ";
                    $bind['status'] = $status;
                }

                if ($from !== '') {
                    $sql .= " AND o.date >= :from ";
                    $bind['from'] = $from;
                }

                if ($to !== '') {
                    $sql .= " AND o.date <= :to ";
                    $bind['to'] = $to;
                }

                if ($onlyOpen) {
                    // “Open” = anything not completed / cancelled / closed
                    $sql .= " AND o.status NOT IN ('cancelled','completed','closed') ";
                }

                $sql .= " ORDER BY o.date DESC, o.id DESC LIMIT 500";

                $orders = $this->rows($sql, $bind);

                // Metrics from the result set
                foreach ($orders as $row) {
                    $metrics['order_count']++;
                    $total = (float)($row['grand_total'] ?? 0);

                    $metrics['total_value'] += $total;

                    $st = (string)($row['status'] ?? '');
                    if (in_array($st, ['draft'], true)) {
                        $metrics['draft_count']++;
                    }
                    if (in_array($st, ['pending','confirmed','partially_shipped'], true)) {
                        $metrics['open_count']++;
                        $metrics['open_value'] += $total;
                    }
                    if ($st === 'cancelled') {
                        $metrics['cancelled_count']++;
                    }
                }
            }

            $this->view('orders/index', [
                'title'        => 'Orders',
                'org'          => $org,
                'module_base'  => $moduleBase,
                'orders'       => $orders,
                'metrics'      => $metrics,
                'search'       => $q,
                'filterStatus' => $status,
                'from'         => $from,
                'to'           => $to,
                'only_open'    => $onlyOpen,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Orders index failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/orders/{id}
     * GET /t/{slug}/apps/bizflow/orders/{id}
     */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c      = $this->ctx($ctx ?? []);
            $orgId  = $this->requireOrg();
            $pdo    = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_orders')) {
                http_response_code(404);
                echo 'Order not found.';
                return;
            }

            $order = $this->row("
                SELECT
                    o.*,
                    c.name          AS customer_name,
                    c.code          AS customer_code,
                    c.company_name  AS customer_company,
                    c.city          AS customer_city,
                    c.district      AS customer_district,
                    c.country       AS customer_country
                FROM biz_orders o
                LEFT JOIN biz_customers c
                       ON c.id = o.customer_id
                      AND c.org_id = o.org_id
                WHERE o.org_id = ? AND o.id = ?
                LIMIT 1
            ", [$orgId, $id]);

            if (!$order) {
                http_response_code(404);
                echo 'Order not found.';
                return;
            }

            // Order items (if table exists)
            $items = [];
            if ($this->hasTable($pdo, 'biz_order_items')) {
                $items = $this->rows("
                    SELECT
                        i.*,
                        p.name AS item_name,
                        p.code AS item_code,
                        p.unit AS item_unit
                    FROM biz_order_items i
                    LEFT JOIN biz_items p
                           ON p.id = i.item_id
                          AND p.org_id = i.org_id
                    WHERE i.org_id = ? AND i.order_id = ?
                    ORDER BY i.line_no, i.id
                ", [$orgId, $id]);
            }

            // Related docs (soft)
            $invoices = [];
            if ($this->hasTable($pdo, 'biz_invoices')) {
                $invoices = $this->rows("
                    SELECT id, invoice_no, date, status, grand_total
                      FROM biz_invoices
                     WHERE org_id = ? AND order_id = ?
                     ORDER BY date DESC, id DESC
                ", [$orgId, $id]);
            }

            $payments = [];
            if ($this->hasTable($pdo, 'biz_payments')) {
                $payments = $this->rows("
                    SELECT id, date, method, reference, amount
                      FROM biz_payments
                     WHERE org_id = ? AND customer_id = ?
                     ORDER BY date DESC, id DESC
                     LIMIT 50
                ", [$orgId, (int)($order['customer_id'] ?? 0)]);
            }

            $this->view('orders/show', [
                'title'       => 'Order details',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'order'       => $order,
                'items'       => $items,
                'invoices'    => $invoices,
                'payments'    => $payments,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Order show failed', $e);
        }
    }

    /* ------------------------------------------------------------------
     * Placeholders to keep routes happy (no DB writes yet)
     * ------------------------------------------------------------------ */

    public function create(?array $ctx = null): void
    {
        try {
            $c = $this->ctx($ctx ?? []);
            $this->view('orders/create', [
                'title'       => 'New Order',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Order create screen failed', $e);
        }
    }

    public function edit(?array $ctx, int $id): void
    {
        try {
            $c = $this->ctx($ctx ?? []);
            $this->view('orders/edit', [
                'title'       => 'Edit Order',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'order_id'    => $id,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Order edit screen failed', $e);
        }
    }

    public function store(?array $ctx = null): void
    {
        $this->postOnly();
        http_response_code(501);
        echo 'Order save (store) not implemented yet.';
    }

    public function update(?array $ctx, int $id): void
    {
        $this->postOnly();
        http_response_code(501);
        echo 'Order update not implemented yet.';
    }

    /* -------------------------------------------------------------
     * Local helpers
     * ----------------------------------------------------------- */

    private function hasTable(PDO $pdo, string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $st = $pdo->prepare(
            "SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?"
        );
        $st->execute([$table]);
        $cache[$table] = (bool)$st->fetchColumn();

        return $cache[$table];
    }

    private function hasCol(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $st = $pdo->prepare(
            "SELECT COUNT(*)
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?"
        );
        $st->execute([$table, $column]);
        $cache[$key] = (bool)$st->fetchColumn();

        return $cache[$key];
    }
}