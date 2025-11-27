<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow ReportsController
 * - 2035-style overview dashboard
 * - All queries are guarded with hasTable() so it is safe
 *   even when BizFlow tables are not yet created.
 */
final class ReportsController extends BaseController
{
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $metrics = [
                // Sales / revenue
                'quotes_count'        => null,
                'orders_count'        => null,
                'invoices_count'      => null,
                'invoices_total'      => null,
                'payments_total'      => null,

                // Purchases / supply
                'purchases_count'     => null,
                'purchases_total'     => null,

                // Inventory
                'inventory_skus'      => null,
                'inventory_moves_in'  => null,
                'inventory_moves_out' => null,

                // Data readiness flags
                'has_quotes'          => false,
                'has_orders'          => false,
                'has_invoices'        => false,
                'has_payments'        => false,
                'has_purchases'       => false,
                'has_inventory'       => false,
            ];

            $dataReady = false;

            /* ---------------- Sales: quotes ---------------- */
            if ($this->hasTable($pdo, 'biz_quotes')) {
                $dataReady = $metrics['has_quotes'] = true;

                $row = $this->row(
                    "SELECT COUNT(*) AS c
                       FROM biz_quotes
                      WHERE org_id = ?",
                    [$orgId]
                );
                $metrics['quotes_count'] = (int)($row['c'] ?? 0);
            }

            /* ---------------- Sales: orders ---------------- */
            if ($this->hasTable($pdo, 'biz_orders')) {
                $dataReady = $metrics['has_orders'] = true;

                $row = $this->row(
                    "SELECT COUNT(*) AS c
                       FROM biz_orders
                      WHERE org_id = ?",
                    [$orgId]
                );
                $metrics['orders_count'] = (int)($row['c'] ?? 0);
            }

            /* ---------------- Sales: invoices ---------------- */
            if ($this->hasTable($pdo, 'biz_invoices')) {
                $dataReady = $metrics['has_invoices'] = true;

                $row = $this->row(
                    "SELECT COUNT(*) AS c,
                            COALESCE(SUM(grand_total), 0) AS total
                       FROM biz_invoices
                      WHERE org_id = ?",
                    [$orgId]
                );
                $metrics['invoices_count'] = (int)($row['c'] ?? 0);
                $metrics['invoices_total'] = (float)($row['total'] ?? 0.0);
            }

            /* ---------------- Cash: payments ---------------- */
            if ($this->hasTable($pdo, 'biz_payments')) {
                $dataReady = $metrics['has_payments'] = true;

                $row = $this->row(
                    "SELECT COALESCE(SUM(amount), 0) AS total
                       FROM biz_payments
                      WHERE org_id = ?",
                    [$orgId]
                );
                $metrics['payments_total'] = (float)($row['total'] ?? 0.0);
            }

            /* ---------------- Purchases ---------------- */
            if ($this->hasTable($pdo, 'biz_purchases')) {
                $dataReady = $metrics['has_purchases'] = true;

                $row = $this->row(
                    "SELECT COUNT(*) AS c,
                            COALESCE(SUM(grand_total), 0) AS total
                       FROM biz_purchases
                      WHERE org_id = ?",
                    [$orgId]
                );
                $metrics['purchases_count'] = (int)($row['c'] ?? 0);
                $metrics['purchases_total'] = (float)($row['total'] ?? 0.0);
            }

            /* ---------------- Inventory ---------------- */
            if ($this->hasTable($pdo, 'biz_items')) {
                $dataReady = $metrics['has_inventory'] = true;

                $row = $this->row(
                    "SELECT COUNT(*) AS c
                       FROM biz_items
                      WHERE org_id = ?",
                    [$orgId]
                );
                $metrics['inventory_skus'] = (int)($row['c'] ?? 0);
            }

            if ($this->hasTable($pdo, 'biz_inventory_moves')) {
                $dataReady = $metrics['has_inventory'] = true;

                $rowIn = $this->row(
                    "SELECT COALESCE(SUM(qty), 0) AS qty
                       FROM biz_inventory_moves
                      WHERE org_id = ?
                        AND direction = 'in'",
                    [$orgId]
                );
                $rowOut = $this->row(
                    "SELECT COALESCE(SUM(qty), 0) AS qty
                       FROM biz_inventory_moves
                      WHERE org_id = ?
                        AND direction = 'out'",
                    [$orgId]
                );

                $metrics['inventory_moves_in']  = (float)($rowIn['qty']  ?? 0.0);
                $metrics['inventory_moves_out'] = (float)($rowOut['qty'] ?? 0.0);
            }

            $this->view('reports/index', [
                'title'       => 'Reports & Analytics',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'metrics'     => $metrics,
                'data_ready'  => $dataReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Reports index failed', $e);
        }
    }
}