<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class FreeProductsController extends BaseController
{
    /* ============================ List & CRUD ============================ */

    /** GET /free-products */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $st = $pdo->prepare("
            SELECT p.id, p.code, p.name, p.unit, COALESCE(SUM(m.qty),0) AS stock
            FROM dms_free_products p
            LEFT JOIN dms_free_stock_moves m
              ON m.org_id=p.org_id AND m.product_id=p.id
            WHERE p.org_id=?
            GROUP BY p.id, p.code, p.name, p.unit
            ORDER BY p.id DESC
            LIMIT 200
        ");
        $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('free-products/index', [
            'title'     => 'Free Products',
            'rows'      => $rows,
            'active'    => 'purchase',
            'subactive' => 'free-products.index',
        ], $ctx);
    }

    /** GET /free-products/create */
    public function create(array $ctx): void
    {
        $this->view('free-products/create', [
            'title'     => 'Create Free Product',
            'active'    => 'purchase',
            'subactive' => 'free-products.create',
        ], $ctx);
    }

    /** POST /free-products */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $unit = trim((string)($_POST['unit'] ?? 'pcs'));
        if ($name === '') $this->abort400('Name is required.');

        // Auto-code if blank
        if ($code === '') {
            $prefix = 'FP-'.date('Y').'-';
            $st = $pdo->prepare("SELECT code FROM dms_free_products WHERE org_id=? AND code LIKE ? ORDER BY id DESC LIMIT 1");
            $st->execute([$orgId, $prefix.'%']);
            $last = (string)$st->fetchColumn();
            $seq  = 0;
            if ($last && preg_match('/^'.preg_quote($prefix,'/').'(\d+)$/', $last, $m)) $seq = (int)$m[1];
            $code = $prefix . str_pad((string)($seq+1), 5, '0', STR_PAD_LEFT);
        }

        $ins = $pdo->prepare("
            INSERT INTO dms_free_products (org_id, code, name, unit, created_at, updated_at)
            VALUES (?,?,?,?,NOW(),NOW())
        ");
        $ins->execute([$orgId, $code, $name, $unit]);

        $id = (int)$pdo->lastInsertId();
        $this->redirect($this->moduleBase($ctx).'/free-products/'.$id);
    }

    /** GET /free-products/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $h = $pdo->prepare("SELECT * FROM dms_free_products WHERE org_id=? AND id=?");
        $h->execute([$orgId, $id]);
        $p = $h->fetch(PDO::FETCH_ASSOC);
        if (!$p) $this->abort404('Free product not found.');

        $stock = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM dms_free_stock_moves WHERE org_id=? AND product_id=?");
        $stock->execute([$orgId, $id]);
        $qty = (float)$stock->fetchColumn();

        $this->view('free-products/show', [
            'title'     => 'Free Product: '.($p['name'] ?? ('#'.$id)),
            'p'         => $p,
            'stock'     => $qty,
            'active'    => 'purchase',
            'subactive' => 'free-products.index',
        ], $ctx);
    }

    // In Modules\DMS\Controllers\FreeProductsController.php

public function inventory(array $ctx): void
{
    // just reuse the index view/data for now
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);

    $st = $pdo->prepare("
        SELECT p.id, p.code, p.name, p.unit, COALESCE(SUM(m.qty),0) AS stock
        FROM dms_free_products p
        LEFT JOIN dms_free_stock_moves m
          ON m.org_id=p.org_id AND m.product_id=p.id
        WHERE p.org_id=?
        GROUP BY p.id, p.code, p.name, p.unit
        ORDER BY p.id DESC
        LIMIT 200
    ");
    $st->execute([$orgId]);
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $this->view('free-products/index', [
        'title'     => 'Free Products Inventory',
        'rows'      => $rows,
        'active'    => 'purchase',
        'subactive' => 'free-products.inventory',
    ], $ctx);
}


    /** GET /free-products/{id}/movements */
    public function movementsAll(array $ctx): void
{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);

    $st = $pdo->prepare("
        SELECT m.id, m.product_id, m.type, m.qty, m.ref_no, m.note, m.created_at,
               p.name AS product_name, p.code AS product_code, p.unit
        FROM dms_free_stock_moves m
        LEFT JOIN dms_free_products p
               ON p.id = m.product_id AND p.org_id = m.org_id
        WHERE m.org_id = ?
        ORDER BY m.id DESC
        LIMIT 200
    ");
    $st->execute([$orgId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Reuse the same movements view but label as "All Products"
    $this->view('free-products/movements', [
        'title'     => 'All Free Product Movements',
        'p'         => ['id' => 0, 'name' => 'All Products'],
        'rows'      => $rows,
        'active'    => 'purchase',
        'subactive' => 'free-products.index',
    ], $ctx);
}

    /* ======================== Receive (match routes) ======================== */

    /**
     * GET/POST /free-products/receive
     * Your routes call ->receive(), so handle both verbs here.
     */
    public function receive(array $ctx): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') { $this->receivePost($ctx); return; }

        // GET: show form with a small product list (id, code, name)
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $products = $this->listProducts($pdo, $orgId);

        $this->view('free-products/receive', [
            'title'     => 'Receive Free Product',
            'products'  => $products,
            'active'    => 'purchase',
            'subactive' => 'free-products.receive',
        ], $ctx);
    }

    /** Internal: handle POST for receive */
    private function receivePost(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $pid  = (int)($_POST['product_id'] ?? 0);
        $qty  = (float)($_POST['qty'] ?? 0);
        $ref  = trim((string)($_POST['ref_no'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($pid <= 0 || $qty <= 0) $this->abort400('Product and positive qty are required.');

        $ins = $pdo->prepare("
            INSERT INTO dms_free_stock_moves (org_id, product_id, type, qty, ref_no, note, created_at, updated_at)
            VALUES (?,?,?,?,?,?,NOW(),NOW())
        ");
        $ins->execute([$orgId, $pid, 'receive', $qty, $ref ?: null, $note ?: null]);

        $this->redirect($this->moduleBase($ctx).'/free-products/'.$pid.'/movements');
    }

    /* ========================= Issue (match routes) ========================= */

    /**
     * GET/POST /free-products/issue
     * If your routes call ->issue(), support both verbs here.
     */
    public function issue(array $ctx): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') { $this->issuePost($ctx); return; }

        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $products = $this->listProducts($pdo, $orgId);

        $this->view('free-products/issue', [
            'title'     => 'Issue Free Product',
            'products'  => $products,
            'active'    => 'purchase',
            'subactive' => 'free-products.issue',
        ], $ctx);
    }

    /** Internal: handle POST for issue */
    private function issuePost(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $pid  = (int)($_POST['product_id'] ?? 0);
        $qty  = (float)($_POST['qty'] ?? 0);
        $ref  = trim((string)($_POST['ref_no'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($pid <= 0 || $qty <= 0) $this->abort400('Product and positive qty are required.');

        // store issue as negative qty (keeps stock = SUM(qty))
        $ins = $pdo->prepare("
            INSERT INTO dms_free_stock_moves (org_id, product_id, type, qty, ref_no, note, created_at, updated_at)
            VALUES (?,?,?,?,?,?,NOW(),NOW())
        ");
        $ins->execute([$orgId, $pid, 'issue', -abs($qty), $ref ?: null, $note ?: null]);

        $this->redirect($this->moduleBase($ctx).'/free-products/'.$pid.'/movements');
    }

    /* ============================== Utilities ============================== */

    /** Small product list for selects */
    private function listProducts(PDO $pdo, int $orgId): array
    {
        try {
            $q = $pdo->prepare("SELECT id, code, name, unit FROM dms_free_products WHERE org_id=? ORDER BY id DESC LIMIT 200");
            $q->execute([$orgId]);
            return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}