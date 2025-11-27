<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class DeliveryReturnsController extends BaseController
{
    /** List recent delivery returns */
    public function index(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);

        $st = $pdo->prepare("
            SELECT dr.id, dr.return_no, dr.return_date, dr.customer_id, dr.customer_name,
                   dr.posted_at,
                   COALESCE(SUM(rl.qty),0) AS total_qty,
                   COUNT(rl.id) AS items
            FROM dms_delivery_returns dr
            LEFT JOIN dms_delivery_return_lines rl
                   ON rl.delivery_return_id = dr.id
            WHERE dr.org_id = ?
            GROUP BY dr.id, dr.return_no, dr.return_date, dr.customer_id, dr.customer_name, dr.posted_at
            ORDER BY dr.id DESC
            LIMIT 200
        ");
        $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('returns/index', [
            'title'       => 'Delivery Returns',
            'rows'        => $rows,
            'module_base' => $this->moduleBase($c),
            'active'      => 'inventory',
            'subactive'   => 'returns.index',
        ], $c);
    }

    /** Show a single return + lines */
    public function show(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $id    = (int)($_GET['id'] ?? 0);

        if ($id <= 0) { $this->abort400('Missing id'); return; }

        $h = $pdo->prepare("SELECT * FROM dms_delivery_returns WHERE org_id=? AND id=?");
        $h->execute([$orgId, $id]);
        $hdr = $h->fetch(PDO::FETCH_ASSOC);
        if (!$hdr) { $this->abort404('Return not found'); return; }

        $l = $pdo->prepare("
            SELECT id, product_id, sku, product_name, qty, note
            FROM dms_delivery_return_lines
            WHERE delivery_return_id=?
            ORDER BY id
        ");
        $l->execute([$id]);
        $lines = $l->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('returns/show', [
            'title'       => 'Return #'.($hdr['return_no'] ?? $hdr['id']),
            'hdr'         => $hdr,
            'lines'       => $lines,
            'module_base' => $this->moduleBase($c),
            'active'      => 'inventory',
            'subactive'   => 'returns.index',
        ], $c);
    }

    /** POST /returns/post  -> calls stored proc dms_post_delivery_return */
    public function post(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $id    = (int)($_POST['return_id'] ?? 0);

        if ($id <= 0) { $this->abort400('Missing return_id'); return; }

        try {
            $pdo->beginTransaction();
            // Optional: validate org ownership before calling proc
            $chk = $pdo->prepare("SELECT org_id FROM dms_delivery_returns WHERE id=? FOR UPDATE");
            $chk->execute([$id]);
            $own = (int)($chk->fetchColumn() ?: 0);
            if ($own !== $orgId) { $pdo->rollBack(); $this->abort403('Forbidden'); return; }

            $pdo->exec("CALL dms_post_delivery_return($id)");
            $pdo->commit();
            $this->redirect(($this->moduleBase($c))."/returns/show?id=".$id);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->abort500('Post failed: '.$e->getMessage());
        }
    }

    /** POST /returns/unpost -> calls stored proc dms_unpost_delivery_return */
    public function unpost(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);
        $id    = (int)($_POST['return_id'] ?? 0);

        if ($id <= 0) { $this->abort400('Missing return_id'); return; }

        try {
            $pdo->beginTransaction();
            $chk = $pdo->prepare("SELECT org_id FROM dms_delivery_returns WHERE id=? FOR UPDATE");
            $chk->execute([$id]);
            $own = (int)($chk->fetchColumn() ?: 0);
            if ($own !== $orgId) { $pdo->rollBack(); $this->abort403('Forbidden'); return; }

            $pdo->exec("CALL dms_unpost_delivery_return($id)");
            $pdo->commit();
            $this->redirect(($this->moduleBase($c))."/returns/show?id=".$id);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->abort500('Unpost failed: '.$e->getMessage());
        }
    }
}