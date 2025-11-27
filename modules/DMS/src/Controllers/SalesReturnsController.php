<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class SalesReturnsController extends BaseController
{
    /* ---------- tiny helpers ---------- */
    private function hasTable(PDO $pdo, string $t): bool {
        try {
            $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
            $q->execute([$t]);
            return (bool)$q->fetchColumn();
        } catch (\Throwable $e) { return false; }
    }
    private function nextDocNo(PDO $pdo, int $orgId, string $name='sales_return', string $prefix='RET'): string {
        $y = (int)date('Y');
        $st = $pdo->prepare("
            INSERT INTO dms_counters (org_id, name, y, seq)
            VALUES (?,?,?,0)
            ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)
        ");
        $st->execute([$orgId, $name, $y]);
        $seq = (int)$pdo->lastInsertId();
        return sprintf('%s-%d-%05d', strtoupper($prefix), $y, $seq);
    }

    /* ---------- screens ---------- */

    /** GET /returns */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $rows = [];
        if ($pdo instanceof PDO && $this->hasTable($pdo, 'dms_sales_returns')) {
            $st = $pdo->prepare("
                SELECT id, return_no, return_date, customer_id, grand_total, status
                FROM dms_sales_returns
                WHERE org_id=?
                ORDER BY id DESC
                LIMIT 200
            ");
            $st->execute([$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $this->view('returns/index', [
            'title' => 'Sales Returns',
            'rows'  => $rows,
        ], $ctx);
    }

    /** GET /returns/create */
    public function create(array $ctx): void
    {
        // Minimal page — the view can handle the UI; no DB dependency required here
        $this->view('returns/create', [
            'title' => 'Create Return',
            'today' => date('Y-m-d'),
        ], $ctx);
    }

    /** POST /returns (minimal safe store for testing routes) */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $base  = $this->moduleBase($ctx);

        // If the table exists, do a tiny insert so you can see it in the index.
        // If not, just bounce back to index so your routes are proven.
        if ($pdo instanceof PDO && $this->hasTable($pdo, 'dms_sales_returns')) {
            try {
                $pdo->beginTransaction();

                $retNo   = trim((string)($_POST['return_no'] ?? ''));
                $retDate = trim((string)($_POST['return_date'] ?? date('Y-m-d')));
                $cid     = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                $gt      = (float)($_POST['grand_total'] ?? 0);
                $status  = (string)($_POST['status'] ?? 'confirmed');

                if ($retNo === '') $retNo = $this->nextDocNo($pdo, $orgId, 'sales_return', 'RET');

                $h = $pdo->prepare("
                    INSERT INTO dms_sales_returns
                      (org_id, sale_id, customer_id, return_no, return_date, reason,
                       subtotal, discount_type, discount_value, grand_total, status, notes,
                       created_at, updated_at)
                    VALUES
                      (?,?,?,?,?,'Manual test', ?, 'amount', 0, ?, ?, NULL, NOW(), NOW())
                ");

                // For a minimal test we’ll set subtotal = grand_total
                $h->execute([
                    $orgId, null, ($cid ?: null), $retNo, $retDate,
                    $gt, $gt, $status
                ]);

                $retId = (int)$pdo->lastInsertId();

                // Lines are optional; skip for now to keep it minimal.

                $pdo->commit();
                $this->redirect($base.'/returns/'.$retId);
                return;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                // Fall through to index (still demonstrates routing works)
            }
        }

        // If we can’t store (no table / error), still prove the route works:
        $this->redirect($base.'/returns');
    }

    /** GET /returns/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $ret = null; $items = [];
        if ($pdo instanceof PDO && $this->hasTable($pdo, 'dms_sales_returns')) {
            $h = $pdo->prepare("SELECT * FROM dms_sales_returns WHERE org_id=? AND id=?");
            $h->execute([$orgId, $id]);
            $ret = $h->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($ret && $this->hasTable($pdo, 'dms_sales_return_items')) {
                $i = $pdo->prepare("SELECT * FROM dms_sales_return_items WHERE org_id=? AND return_id=? ORDER BY id");
                $i->execute([$orgId, $id]);
                $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        // Even if not found, render the view (your template can show “not found” nicely)
        $this->view('returns/show', [
            'title' => $ret ? ('Return '.$ret['return_no']) : 'Return',
            'ret'   => $ret,
            'items' => $items,
            'id'    => $id,
        ], $ctx);
    }

    /** GET /returns/{id}/print */
    public function print(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $ret = null; $items = [];
        if ($pdo instanceof PDO && $this->hasTable($pdo, 'dms_sales_returns')) {
            $h = $pdo->prepare("SELECT * FROM dms_sales_returns WHERE org_id=? AND id=?");
            $h->execute([$orgId, $id]);
            $ret = $h->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($ret && $this->hasTable($pdo, 'dms_sales_return_items')) {
                $i = $pdo->prepare("SELECT * FROM dms_sales_return_items WHERE org_id=? AND return_id=? ORDER BY id");
                $i->execute([$orgId, $id]);
                $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        $this->view('returns/print', [
            'title'     => 'Return · Print',
            'ret'       => $ret,
            'items'     => $items,
            'id'        => $id,
            'autoprint' => isset($_GET['autoprint']),
        ], $ctx);
    }
}