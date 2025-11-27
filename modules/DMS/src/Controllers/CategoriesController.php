<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class CategoriesController extends BaseController
{
    /* ------------------------ helpers ------------------------ */
    private function org(array $ctx): int
    {
        return (int)$this->orgId($ctx);
    }

    private function base(array $ctx): string
    {
        return $this->moduleBase($ctx);
    }

    /** Auto code: CAT-YYYY-00001 per org/year */
    private function nextCode(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = "CAT-$year-";
        $st = $pdo->prepare("
            SELECT code
            FROM dms_categories
            WHERE org_id = ? AND code LIKE CONCAT(?, '%')
            ORDER BY code DESC
            LIMIT 1
        ");
        $st->execute([$orgId, $prefix]);
        $last = (string)($st->fetchColumn() ?: '');
        $n = 0;
        if ($last && preg_match('/^CAT-' . $year . '-(\d{1,})$/', $last, $m)) {
            $n = (int)$m[1];
        }
        return $prefix . str_pad((string)($n + 1), 5, '0', STR_PAD_LEFT);
    }

    /* ------------------------ INDEX ------------------------ */
    /** GET /categories */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));

        $where = ["c.org_id = ?"];
        $args  = [$orgId];

        if ($q !== '') {
            $where[] = "(c.name LIKE ? OR c.code LIKE ?)";
            $like = "%{$q}%";
            $args[] = $like;
            $args[] = $like;
        }

        // Category-level product + supplier counts
        $sql = "
            SELECT
                c.id, c.code, c.name, c.is_active, c.created_at,
                COALESCE(pc.product_count, 0)  AS product_count,
                COALESCE(sc.supplier_count, 0) AS supplier_count
            FROM dms_categories c
            LEFT JOIN (
                SELECT org_id, category_id, COUNT(*) AS product_count
                FROM dms_products
                WHERE org_id = ? AND category_id IS NOT NULL
                GROUP BY org_id, category_id
            ) pc ON pc.org_id = c.org_id AND pc.category_id = c.id
            LEFT JOIN (
                SELECT p.org_id, p.category_id, COUNT(DISTINCT pu.supplier_id) AS supplier_count
                FROM dms_purchase_items pi
                JOIN dms_purchases pu
                    ON pu.org_id = pi.org_id AND pu.id = pi.purchase_id
                JOIN dms_products p
                    ON p.org_id = pi.org_id AND p.id = pi.product_id
                WHERE pi.org_id = ? AND p.category_id IS NOT NULL AND COALESCE(pu.supplier_id,0) > 0
                GROUP BY p.org_id, p.category_id
            ) sc ON sc.org_id = c.org_id AND sc.category_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.name ASC, c.id DESC
            LIMIT 200
        ";

        array_unshift($args, $orgId, $orgId);

        $st = $pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('categories/index', [
            'title'       => 'Product Categories',
            'rows'        => $rows,
            'q'           => $q,
            'module_base' => $this->base($ctx),
            'active'      => 'purchase',
            'subactive'   => 'categories.index',
        ], $ctx);
    }

    /* ------------------------ CREATE ------------------------ */
    /** GET /categories/create */
    public function create(array $ctx): void
    {
        $this->view('categories/create', [
            'title'       => 'Create Category',
            'module_base' => $this->base($ctx),
            'active'      => 'purchase',
            'subactive'   => 'categories.create',
        ], $ctx);
    }

    /* ------------------------ STORE ------------------------ */
    /** POST /categories */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);

        $code     = trim((string)($_POST['code'] ?? ''));
        $name     = trim((string)($_POST['name'] ?? ''));
        $isActive = (int)($_POST['is_active'] ?? 1) ? 1 : 0;

        if ($name === '') {
            $this->abort400('Name is required.');
        }
        if ($code === '') {
            $code = $this->nextCode($pdo, $orgId);
        }

        $dup = $pdo->prepare("SELECT 1 FROM dms_categories WHERE org_id=? AND code=? LIMIT 1");
        $dup->execute([$orgId, $code]);
        if ($dup->fetchColumn()) {
            $this->abort400('Duplicate category code for this organization.');
        }

        $ins = $pdo->prepare("
            INSERT INTO dms_categories (org_id, name, code, is_active, created_at)
            VALUES (?,?,?,?, NOW())
        ");
        $ins->execute([$orgId, $name, $code, $isActive]);

        $id = (int)$pdo->lastInsertId();
        $this->redirect($this->base($ctx) . '/categories/' . $id . '/edit');
    }

    /* ------------------------ EDIT ------------------------ */
    /** GET /categories/{id}/edit */
    public function edit(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);

        $st = $pdo->prepare("SELECT * FROM dms_categories WHERE org_id=? AND id=?");
        $st->execute([$orgId, $id]);
        $cat = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cat) {
            $this->abort404('Category not found.');
        }

        $this->view('categories/create', [
            'title'       => 'Edit Category',
            'cat'         => $cat,
            'module_base' => $this->base($ctx),
            'active'      => 'purchase',
            'subactive'   => 'categories.edit',
        ], $ctx);
    }

    /* ------------------------ UPDATE ------------------------ */
    /** POST /categories/{id} */
    public function update(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);

        $code     = trim((string)($_POST['code'] ?? ''));
        $name     = trim((string)($_POST['name'] ?? ''));
        $isActive = (int)($_POST['is_active'] ?? 1) ? 1 : 0;

        if ($name === '') {
            $this->abort400('Name is required.');
        }

        if ($code !== '') {
            $uq = $pdo->prepare("
                SELECT 1 FROM dms_categories
                WHERE org_id=? AND code=? AND id<>?
                LIMIT 1
            ");
            $uq->execute([$orgId, $code, $id]);
            if ($uq->fetchColumn()) {
                $this->abort400('Another category already uses this code.');
            }
        }

        $upd = $pdo->prepare("
            UPDATE dms_categories
               SET name=?, code=?, is_active=?, updated_at=NOW()
             WHERE org_id=? AND id=? LIMIT 1
        ");
        $upd->execute([$name, ($code ?: null), $isActive, $orgId, $id]);

        $this->redirect($this->base($ctx) . '/categories');
    }

    /* ------------------------ SHOW ------------------------ */
    /** GET /categories/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);

        $st = $pdo->prepare("SELECT * FROM dms_categories WHERE org_id=? AND id=?");
        $st->execute([$orgId, $id]);
        $cat = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cat) {
            $this->abort404('Category not found.');
        }

        // Product count
        $pc = $pdo->prepare("SELECT COUNT(*) FROM dms_products WHERE org_id=? AND category_id=?");
        $pc->execute([$orgId, $id]);
        $productCount = (int)$pc->fetchColumn();

        // Supplier count (through purchases)
        $sc = $pdo->prepare("
            SELECT COUNT(DISTINCT pu.supplier_id)
            FROM dms_purchase_items pi
            JOIN dms_purchases pu
              ON pu.org_id = pi.org_id AND pu.id = pi.purchase_id
            JOIN dms_products p
              ON p.org_id = pi.org_id AND p.id = pi.product_id
            WHERE pi.org_id=? AND p.category_id=? AND COALESCE(pu.supplier_id,0) > 0
        ");
        $sc->execute([$orgId, $id]);
        $supplierCount = (int)$sc->fetchColumn();

        // Recent products (safe columns only)
        $rp = $pdo->prepare("
            SELECT id, code, name, brand, model, barcode, status
            FROM dms_products
            WHERE org_id=? AND category_id=?
            ORDER BY id DESC
            LIMIT 12
        ");
        $rp->execute([$orgId, $id]);
        $recent = $rp->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('categories/show', [
            'title'         => 'Category: ' . ($cat['name'] ?? ('#' . $id)),
            'cat'           => $cat,
            'productCount'  => $productCount,
            'supplierCount' => $supplierCount,
            'recent'        => $recent,
            'module_base'   => $this->base($ctx),
            'active'        => 'purchase',
            'subactive'     => 'categories.index',
        ], $ctx);
    }

    /* ------------------------ API (Typeahead) ------------------------ */
    /** GET /api/categories?q=... → {items:[{id,code,name}]} */
    public function api(array $ctx): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));

        if ($q === '') {
            $st = $pdo->prepare("
                SELECT id, code, name
                FROM dms_categories
                WHERE org_id=?
                ORDER BY name ASC
                LIMIT 50
            ");
            $st->execute([$orgId]);
        } else {
            $like = "%{$q}%";
            $st = $pdo->prepare("
                SELECT id, code, name
                FROM dms_categories
                WHERE org_id=? AND (name LIKE ? OR code LIKE ?)
                ORDER BY name ASC
                LIMIT 50
            ");
            $st->execute([$orgId, $like, $like]);
        }

        echo json_encode(['items' => $st->fetchAll(PDO::FETCH_ASSOC) ?: []], JSON_UNESCAPED_UNICODE);
        exit;
    }
}