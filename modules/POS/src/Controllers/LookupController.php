<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

/**
 * Smart lookup API for POS module
 * Mirrors DMS LookupController but points to pos_* tables.
 * Routes:
 *   GET /api/lookup              → discovery
 *   GET /api/lookup/{entity}?q=  → search entities
 */
final class LookupController extends BaseController
{
    /** GET /api/lookup */
    public function index(array $ctx): void
    {
        $this->json([
            'entities' => [
                'suppliers', 'categories', 'products',
                'customers', 'sales', 'users'
            ],
            'hint' => 'Use /api/lookup/{entity}?q=term&limit=30'
        ]);
    }

    /** GET /api/lookup/{entity}?q=... */
    public function handle(array $ctx, string $entity): void
    {
        $entity = strtolower(trim($entity));
        switch ($entity) {
            case 'suppliers':  return $this->suppliers($ctx);
            case 'categories': return $this->categories($ctx);
            case 'products':   return $this->products($ctx);
            case 'customers':  return $this->customers($ctx);
            case 'sales':      return $this->sales($ctx);
            case 'users':      return $this->users($ctx);
        }
        $this->json(['items' => [], 'error' => 'unknown entity'], 404);
    }

    /* -----------------------------------------------------------------
     * Suppliers
     * ----------------------------------------------------------------- */
    protected function suppliers(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = (int)$this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        $where = "s.org_id = :o";
        if ($q !== '') {
            $where .= " AND (s.name LIKE :q OR s.code LIKE :q)";
        }

        $sql = "
            SELECT s.id, s.code, s.name
            FROM pos_suppliers s
            WHERE $where
            ORDER BY s.name ASC
            LIMIT :lim
        ";

        $st = $pdo->prepare($sql);
        $st->bindValue(':o', $orgId, PDO::PARAM_INT);
        $st->bindValue(':lim', $lim, PDO::PARAM_INT);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $st->bindValue(':q', $like, PDO::PARAM_STR);
        }

        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'code' => $r['code'],
            'name' => $r['name'],
            'label' => "{$r['name']} ({$r['code']})"
        ], $rows ?: []);

        $this->json(['items' => $items]);
    }

    /* -----------------------------------------------------------------
     * Categories
     * ----------------------------------------------------------------- */
    protected function categories(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = (int)$this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        $sql = "
            SELECT id, code, name
            FROM pos_categories
            WHERE org_id = :o
              AND (:q = '' OR name LIKE :like OR code LIKE :like)
            ORDER BY name ASC
            LIMIT :lim
        ";

        $st = $pdo->prepare($sql);
        $like = '%'.$q.'%';
        $st->execute([
            ':o' => $orgId,
            ':q' => $q,
            ':like' => $like,
            ':lim' => $lim
        ]);

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'code' => $r['code'],
            'name' => $r['name'],
            'label' => $r['name'],
            'sublabel' => $r['code']
        ], $rows ?: []);

        $this->json(['items' => $items]);
    }

    /* -----------------------------------------------------------------
 * Products  (for /api/lookup/products)
 * ----------------------------------------------------------------- */
protected function products(array $ctx): void
{
    $pdo   = $this->pdo();
    $orgId = (int)$this->orgId($ctx);
    $q     = trim((string)($_GET['q'] ?? ''));
    $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

    $sql = "
        SELECT
          p.id,
          p.sku,
          p.name,
          p.unit       AS unit_name,
          p.sale_price,
          p.barcode
        FROM pos_products p
        WHERE p.org_id = :o
          AND p.is_active = 1
          AND (:q = '' OR p.name LIKE :like OR p.sku LIKE :like OR p.barcode LIKE :like)
        ORDER BY p.name ASC
        LIMIT :lim
    ";

    $st   = $pdo->prepare($sql);
    $like = '%'.$q.'%';
    $st->bindValue(':o',    $orgId, PDO::PARAM_INT);
    $st->bindValue(':q',    $q,     PDO::PARAM_STR);
    $st->bindValue(':like', $like,  PDO::PARAM_STR);
    $st->bindValue(':lim',  $lim,   PDO::PARAM_INT);
    $st->execute();

    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = array_map(static function(array $r): array {
        $price = (float)($r['sale_price'] ?? 0);
        return [
            'id'       => (int)$r['id'],
            'code'     => (string)($r['sku'] ?? ''),
            'name'     => (string)($r['name'] ?? ''),
            'price'    => $price,
            'unit'     => (string)($r['unit_name'] ?? ''),
            'barcode'  => (string)($r['barcode'] ?? ''),
            'label'    => sprintf('%s (%s)', $r['name'], $r['sku'] ?? ''),
            'sublabel' => (string)($r['barcode'] ?? ''),
        ];
    }, $rows);

    $this->json(['items' => $items]);
}

    /* -----------------------------------------------------------------
     * Customers
     * ----------------------------------------------------------------- */
    protected function customers(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = (int)$this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        $sql = "
            SELECT id, code, name, phone, email
            FROM pos_customers
            WHERE org_id = :o
              AND (:q = '' OR name LIKE :like OR code LIKE :like OR phone LIKE :like OR email LIKE :like)
            ORDER BY name ASC
            LIMIT :lim
        ";

        $st = $pdo->prepare($sql);
        $like = '%'.$q.'%';
        $st->execute([
            ':o' => $orgId,
            ':q' => $q,
            ':like' => $like,
            ':lim' => $lim
        ]);

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'code' => $r['code'],
            'name' => $r['name'],
            'phone' => $r['phone'],
            'label' => "{$r['name']} ({$r['phone']})",
            'sublabel' => $r['email']
        ], $rows ?: []);

        $this->json(['items' => $items]);
    }

    /* -----------------------------------------------------------------
     * Sales
     * ----------------------------------------------------------------- */
    protected function sales(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = (int)$this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        $sql = "
            SELECT id, sale_no, customer_name, grand_total
            FROM pos_sales
            WHERE org_id = :o
              AND (:q = '' OR sale_no LIKE :like OR customer_name LIKE :like)
            ORDER BY id DESC
            LIMIT :lim
        ";

        $st = $pdo->prepare($sql);
        $like = '%'.$q.'%';
        $st->execute([
            ':o' => $orgId,
            ':q' => $q,
            ':like' => $like,
            ':lim' => $lim
        ]);

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'label' => "[#{$r['id']}] {$r['sale_no']}",
            'sublabel' => $r['customer_name'],
            'amount' => (float)$r['grand_total']
        ], $rows ?: []);

        $this->json(['items' => $items]);
    }

    /* -----------------------------------------------------------------
     * Users (fallback: cp_users)
     * ----------------------------------------------------------------- */
    protected function users(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = (int)$this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        $sql = "
            SELECT id, name, email, phone
            FROM cp_users
            WHERE org_id = :o
              AND (:q = '' OR name LIKE :like OR email LIKE :like OR phone LIKE :like)
            ORDER BY name ASC
            LIMIT :lim
        ";

        $st = $pdo->prepare($sql);
        $like = '%'.$q.'%';
        $st->execute([
            ':o' => $orgId,
            ':q' => $q,
            ':like' => $like,
            ':lim' => $lim
        ]);

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'label' => $r['name'],
            'sublabel' => "{$r['email']} {$r['phone']}"
        ], $rows ?: []);

        $this->json(['items' => $items]);
    }
}