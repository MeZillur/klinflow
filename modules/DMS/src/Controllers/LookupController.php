<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

/**
 * DMS LookupController (smart lookup for KF stack)
 *
 * - Standard JSON: { items: [ { id, label, name?, code?, price?, unit_price?, ... } ], meta: {...} }
 * - Uses app/Support/api_helpers.php (if present) for respond_lookup() / format_lookup_item()
 * - Escapes LIKE safely and bounds limits
 * - Swallows errors to keep UI responsive (logs to error_log)
 *
 * Entities:
 *   /api/lookup/suppliers
 *   /api/lookup/categories
 *   /api/lookup/products
 *   /api/lookup/customers
 *   /api/lookup/stakeholders
 *   /api/lookup/users      (alias of stakeholders)
 *   /api/lookup/orders
 *   /api/lookup/invoices
 */
final class LookupController extends BaseController
{
    private const MAX_LIMIT     = 50;
    private const DEFAULT_LIMIT = 30;

    public function __construct()
    {
        // Attempt to load project-level API helpers if available (non-fatal)
        $helpers = dirname(__DIR__, 3) . '/app/Support/api_helpers.php';
        if (is_file($helpers)) {
            /** @noinspection PhpIncludeInspection */
            require_once $helpers;
        }
    }

    /** GET {base}/api/lookup → discovery */
    public function index(array $ctx): void
    {
        $this->json([
            'entities' => [
                'suppliers',
                'categories',
                'products',
                'customers',
                'stakeholders',
                'users',     // alias
                'orders',
                'invoices',
            ],
            'hint' => 'Use /api/lookup/{entity}?q=term&limit=30',
        ]);
    }

    /** GET {base}/api/lookup/{entity}?q=... */
    /** GET {base}/api/lookup/{entity}?q=... */
public function handle(array $ctx, string $entity): void
{
    $entity = strtolower(trim($entity));

    switch ($entity) {
        /* ----------------- SUPPLIERS ----------------- */
        case 'suppliers':
        case 'supplier':
            $this->suppliers($ctx);
            return;

        /* ----------------- CATEGORIES ---------------- */
        case 'categories':
        case 'category':
            $this->categories($ctx);
            return;

        /* ----------------- PRODUCTS ------------------ */
        case 'products':
        case 'product':
        case 'items':
        case 'item':
            $this->products($ctx);
            return;

        /* ----------------- CUSTOMERS ----------------- */
        case 'customers':
        case 'customer':
            $this->customers($ctx);
            return;

        /* ----------------- STAKEHOLDERS / USERS ------ */
        case 'stakeholders':
        case 'stakeholder':
        case 'users':
        case 'user':
            $this->stakeholders($ctx);
            return;

        /* ----------------- ORDERS -------------------- */
        case 'orders':
        case 'order':
            $this->orders($ctx);
            return;

        /* ----------------- INVOICES ------------------ */
        case 'invoices':
        case 'invoice':
            $this->invoices($ctx);
            return;
    }

    $this->json(['items' => [], 'error' => 'unknown entity'], 404);
}

    /* ============================================================
     * Shared helpers
     * ========================================================== */

    private function limitFromRequest(): int
    {
        $lim = (int)($_GET['limit'] ?? self::DEFAULT_LIMIT);
        return max(1, min(self::MAX_LIMIT, $lim));
    }

    private function qFromRequest(): string
    {
        return trim((string)($_GET['q'] ?? ''));
    }

    /**
     * Escape a string for use in LIKE with explicit backslash escape.
     */
    private function escapeLike(string $s): string
    {
        // escape backslash first, then % and _
        return strtr($s, [
            '\\' => '\\\\',
            '%'  => '\\%',
            '_'  => '\\_',
        ]);
    }

    /**
     * Optionally run items through a global formatter (format_lookup_item)
     * so other modules / global helpers can tweak the shape.
     */
    private function formatItem(array $item): array
    {
        if (function_exists('format_lookup_item')) {
            $out = format_lookup_item($item);
            if (is_array($out)) {
                return $out;
            }
        }
        return $item;
    }

    /**
     * Attempt to respond using app-level helper if available; otherwise fall back to $this->json
     */
    private function respond(array $items, array $meta = []): void
    {
        if (function_exists('respond_lookup')) {
            respond_lookup($items, $meta);
            return;
        }

        $this->json([
            'items' => $items,
            'meta'  => $meta,
        ]);
    }

    /* ============================================================
     * Suppliers
     * ========================================================== */

    protected function suppliers(array $ctx): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $q     = $this->qFromRequest();
            $lim   = $this->limitFromRequest();

            $where  = "s.org_id = :o";
            $params = [':o' => $orgId, ':lim' => $lim];

            if ($q !== '') {
                $like   = '%' . $this->escapeLike($q) . '%';
                $where .= " AND (
                    s.name COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR s.code COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                )";
                $params[':q'] = $like;
            }

            $sql = "
                SELECT s.id, s.code, s.name
                FROM dms_suppliers s
                WHERE {$where}
                ORDER BY s.name ASC
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $lim, PDO::PARAM_INT);
            $st->execute();

            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $n = (string)($r['name'] ?? '');
                $c = (string)($r['code'] ?? '');

                $items[] = $this->formatItem([
                    'id'       => (int)$r['id'],
                    'name'     => $n,
                    'code'     => $c,
                    'label'    => trim($n . ($c !== '' ? " — {$c}" : '')),
                    'sublabel' => $c,
                    'meta'     => [],
                ]);
            }

            $this->respond($items, [
                'q'     => $q,
                'limit' => $lim,
            ]);
        } catch (\Throwable $e) {
            error_log('Lookup suppliers failed: ' . $e->getMessage());
            $this->respond([], []);
        }
    }

    /* ============================================================
     * Categories
     * ========================================================== */

    protected function categories(array $ctx): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $q     = $this->qFromRequest();
            $lim   = $this->limitFromRequest();

            $table = 'dms_categories';
            try {
                $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            } catch (\Throwable $ex) {
                $table = 'dms_product_categories';
            }

            $where  = "c.org_id = :o";
            $params = [':o' => $orgId, ':lim' => $lim];

            if ($q !== '') {
                $like   = '%' . $this->escapeLike($q) . '%';
                $where .= " AND (
                    c.name COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR c.code COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                )";
                $params[':q'] = $like;
            }

            $sql = "
                SELECT c.id, c.code, c.name
                FROM {$table} c
                WHERE {$where}
                ORDER BY c.name ASC
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $lim, PDO::PARAM_INT);
            $st->execute();

            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $name = (string)($r['name'] ?? '');
                $code = (string)($r['code'] ?? '');

                $items[] = $this->formatItem([
                    'id'       => (int)$r['id'],
                    'name'     => $name,
                    'code'     => $code,
                    'label'    => ($name !== '' ? $name : $code),
                    'sublabel' => $code !== '' ? $code : '',
                    'meta'     => [],
                ]);
            }

            $this->respond($items, [
                'q'     => $q,
                'limit' => $lim,
            ]);
        } catch (\Throwable $e) {
            error_log('Lookup categories failed: ' . $e->getMessage());
            $this->respond([], []);
        }
    }

    /* ============================================================
     * Invoices
     * ========================================================== */

    protected function invoices(array $ctx): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            if ($orgId <= 0) {
                $orgId = (int)($_SESSION['tenant_org']['id'] ?? 0);
            }

            $q   = $this->qFromRequest();
            $lim = $this->limitFromRequest();

            // FAST PATH: exact id lookup if ?id=NN provided or q is purely numeric
            $exactId = (int)($_GET['id'] ?? 0);
            if ($exactId <= 0 && $q !== '' && preg_match('/^\d+$/', $q)) {
                $exactId = (int)$q;
            }

            if ($exactId > 0) {
                $sql = "
                    SELECT
                        s.id,
                        COALESCE(s.invoice_no, s.sale_no, CONCAT('INV-', s.id)) AS code,
                        NULLIF(TRIM(COALESCE(c.name, s.customer_name, '')), '') AS customer_name,
                        COALESCE(s.customer_id, 0) AS customer_id,
                        COALESCE(s.order_no, '') AS order_no,
                        COALESCE(s.order_id, 0) AS order_id,
                        COALESCE(s.delivery_user_id, s.sale_user_id, 0) AS sr_id
                    FROM dms_sales s
                    LEFT JOIN dms_customers c ON c.id = s.customer_id AND c.org_id = s.org_id
                    WHERE s.id = :id AND s.org_id = :o
                    LIMIT 1
                ";

                $st = $pdo->prepare($sql);
                $st->bindValue(':id', $exactId, PDO::PARAM_INT);
                $st->bindValue(':o',  $orgId,   PDO::PARAM_INT);
                $st->execute();

                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $items = $this->buildInvoiceItemsWithSrNames($pdo, [$row]);

                    $this->respond($items, [
                        'q'       => $q,
                        'limit'   => $lim,
                        'exactId' => $exactId,
                    ]);
                    return;
                }
                // else fall through to text search
            }

            // Build WHERE safely (search invoice_no, sale_no, customer name, id)
            $where  = "s.org_id = :o";
            $params = [':o' => $orgId, ':lim' => $lim];

            if ($q !== '') {
                $like = '%' . $this->escapeLike($q) . '%';
                $where .= " AND (
                    s.invoice_no COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR s.sale_no COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR COALESCE(c.name, s.customer_name, '') COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR CAST(s.id AS CHAR) LIKE :qid
                )";
                $params[':q']   = $like;
                $params[':qid'] = '%' . $q . '%';
            }

            $sql = "
                SELECT
                    s.id,
                    COALESCE(s.invoice_no, s.sale_no, CONCAT('INV-', s.id)) AS code,
                    NULLIF(TRIM(COALESCE(c.name, s.customer_name, '')), '') AS customer_name,
                    COALESCE(s.customer_id, 0) AS customer_id,
                    COALESCE(s.order_no, '') AS order_no,
                    COALESCE(s.order_id, 0) AS order_id,
                    COALESCE(s.delivery_user_id, s.sale_user_id, 0) AS sr_id
                FROM dms_sales s
                LEFT JOIN dms_customers c ON c.id = s.customer_id AND c.org_id = s.org_id
                WHERE {$where}
                ORDER BY s.id DESC
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $lim, PDO::PARAM_INT);
            $st->execute();

            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = $this->buildInvoiceItemsWithSrNames($pdo, $rows);

            $this->respond($items, [
                'q'     => $q,
                'limit' => $lim,
            ]);
        } catch (\Throwable $e) {
            error_log('Lookup invoices failed: ' . $e->getMessage());
            $this->respond([], []);
        }
    }

    /**
     * Build invoice items and resolve SR names (dms_stakeholders → cp_users fallback).
     *
     * @param PDO   $pdo
     * @param array $rows raw rows from dms_sales + customers join
     * @return array formatted items
     */
    private function buildInvoiceItemsWithSrNames(PDO $pdo, array $rows): array
    {
        if (!$rows) {
            return [];
        }

        // Collect SR IDs
        $srIds = [];
        foreach ($rows as $r) {
            $sr = (int)($r['sr_id'] ?? 0);
            if ($sr > 0) {
                $srIds[] = $sr;
            }
        }

        $srIds = array_values(array_unique($srIds));
        $srMap = [];

        if ($srIds) {
            // try dms_stakeholders first
            if ($this->hasTable($pdo, 'dms_stakeholders')) {
                $in = implode(',', array_fill(0, count($srIds), '?'));
                $q  = $pdo->prepare("SELECT id, name FROM dms_stakeholders WHERE id IN ($in)");
                $q->execute($srIds);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $srMap[(int)$r['id']] = (string)$r['name'];
                }
            }

            // fallback to cp_users for any missing
            $missing = array_diff($srIds, array_keys($srMap));
            if ($missing && $this->hasTable($pdo, 'cp_users')) {
                $in = implode(',', array_fill(0, count($missing), '?'));
                $q  = $pdo->prepare("SELECT id, COALESCE(name, email, '') AS name FROM cp_users WHERE id IN ($in)");
                $q->execute(array_values($missing));
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $srMap[(int)$r['id']] = (string)$r['name'];
                }
            }
        }

        // Build final items
        $items = [];
        foreach ($rows as $r) {
            $id   = (int)$r['id'];
            $code = (string)($r['code'] ?? ('INV-' . $id));
            $cust = (string)($r['customer_name'] ?? '');
            $srid = (int)($r['sr_id'] ?? 0);

            $items[] = $this->formatItem([
                'id'            => $id,
                'label'         => "[#{$id}] {$code}",
                'code'          => $code,
                'name'          => $code,
                'customer_name' => $cust,
                'customer_id'   => (int)($r['customer_id'] ?? 0),
                'order_no'      => (string)($r['order_no'] ?? ''),
                'order_id'      => (int)($r['order_id'] ?? 0),
                'sr_id'         => $srid,
                'sr_name'       => $srMap[$srid] ?? '',
                'meta'          => [
                    'customer_id' => (int)($r['customer_id'] ?? 0),
                    'sr_id'       => $srid,
                    'order_id'    => (int)($r['order_id'] ?? 0),
                ],
            ]);
        }

        return $items;
    }

    /* ============================================================
     * Products
     * ========================================================== */

    protected function products(array $ctx): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $q     = $this->qFromRequest();
            $lim   = $this->limitFromRequest();

            $where  = "p.org_id = :o";
            $params = [':o' => $orgId, ':lim' => $lim];

            if ($q !== '') {
                $like   = '%' . $this->escapeLike($q) . '%';
                $where .= " AND (
                    p.name COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR p.code COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR p.barcode LIKE :q
                )";
                $params[':q'] = $like;
            }

            $sql = "
                SELECT
                    p.id,
                    p.code,
                    p.name,
                    p.unit_price,
                    p.category_id,
                    p.uom_name,
                    p.barcode
                FROM dms_products p
                WHERE {$where}
                ORDER BY p.name ASC
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $lim, PDO::PARAM_INT);
            $st->execute();

            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $name  = (string)($r['name'] ?? '');
                $code  = (string)($r['code'] ?? '');
                $price = isset($r['unit_price']) ? (float)$r['unit_price'] : null;

                $items[] = $this->formatItem([
                    'id'          => (int)$r['id'],
                    'name'        => $name,
                    'code'        => $code,
                    'label'       => trim($name . ($code !== '' ? " ({$code})" : '')),
                    'sublabel'    => (string)($r['barcode'] ?? ''),
                    'unit_price'  => $price,
                    'price'       => $price,
                    'category_id' => isset($r['category_id']) ? (int)$r['category_id'] : null,
                    'uom_name'    => (string)($r['uom_name'] ?? ''),
                    'barcode'     => (string)($r['barcode'] ?? ''),
                    'meta'        => [],
                ]);
            }

            $this->respond($items, [
                'q'     => $q,
                'limit' => $lim,
            ]);
        } catch (\Throwable $e) {
            error_log('Lookup products failed: ' . $e->getMessage());
            $this->respond([], []);
        }
    }

    /* ============================================================
     * Customers
     * ========================================================== */

    protected function customers(array $ctx): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $q     = $this->qFromRequest();
            $lim   = $this->limitFromRequest();

            $where  = "c.org_id = :o";
            $params = [':o' => $orgId, ':lim' => $lim];

            if ($q !== '') {
                $like   = '%' . $this->escapeLike($q) . '%';
                $where .= " AND (
                    c.name  COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR c.code  COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR c.phone LIKE :q
                    OR c.email LIKE :q
                )";
                $params[':q'] = $like;
            }

            $sql = "
                SELECT
                    c.id,
                    c.code,
                    c.name,
                    c.phone,
                    c.email,
                    c.address
                FROM dms_customers c
                WHERE {$where}
                ORDER BY c.name ASC
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $lim, PDO::PARAM_INT);
            $st->execute();

            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $name  = (string)($r['name'] ?? '');
                $code  = (string)($r['code'] ?? '');
                $phone = (string)($r['phone'] ?? '');

                $label = ($code !== '' ? "[{$code}] " : '') . $name;

                $items[] = $this->formatItem([
                    'id'       => (int)$r['id'],
                    'name'     => $name,
                    'code'     => $code,
                    'phone'    => $phone,
                    'email'    => (string)($r['email'] ?? ''),
                    'address'  => (string)($r['address'] ?? ''),
                    'label'    => $label,
                    'sublabel' => $phone,
                    'meta'     => [],
                ]);
            }

            $this->respond($items, [
                'q'     => $q,
                'limit' => $lim,
            ]);
        } catch (\Throwable $e) {
            error_log('Lookup customers failed: ' . $e->getMessage());
            $this->respond([], []);
        }
    }

    /* ============================================================
     * Stakeholders / Users
     * ========================================================== */

    protected function stakeholders(array $ctx): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $q     = $this->qFromRequest();
            $role  = strtolower(trim((string)($_GET['role'] ?? '')));
            $lim   = $this->limitFromRequest();

            $table = 'dms_stakeholders';
            try {
                $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            } catch (\Throwable) {
                $table = 'cp_users';
            }

            $where  = "t.org_id = :o";
            $params = [':o' => $orgId, ':lim' => $lim];

            if ($q !== '') {
                $like   = '%' . $this->escapeLike($q) . '%';
                $where .= " AND (
                    t.name  COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    OR t.email LIKE :q
                    OR t.phone LIKE :q
                )";
                $params[':q'] = $like;
            }

            if ($role !== '' && $table === 'dms_stakeholders') {
                $where         .= " AND (t.role = :r)";
                $params[':r']   = $role;
            }

            $sql = "
                SELECT
                    t.id,
                    t.name,
                    COALESCE(t.email,'') AS email,
                    COALESCE(t.phone,'') AS phone,
                    COALESCE(t.role,'')  AS role
                FROM {$table} t
                WHERE {$where}
                ORDER BY t.name ASC
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $lim, PDO::PARAM_INT);
            $st->execute();

            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $label = (string)($r['name'] ?? '');
                $meta  = trim(
                    (string)($r['email'] ?? '') . ' ' .
                    (string)($r['phone'] ?? '')
                );

                $items[] = $this->formatItem([
                    'id'       => (int)$r['id'],
                    'name'     => (string)($r['name'] ?? ''),
                    'email'    => (string)($r['email'] ?? ''),
                    'phone'    => (string)($r['phone'] ?? ''),
                    'role'     => (string)($r['role'] ?? ''),
                    'label'    => $label,
                    'sublabel' => $meta,
                    'meta'     => [],
                ]);
            }

            $this->respond($items, [
                'q'     => $q,
                'limit' => $lim,
                'role'  => $role,
            ]);
        } catch (\Throwable $e) {
            error_log('Lookup stakeholders failed: ' . $e->getMessage());
            $this->respond([], []);
        }
    }

    /* ============================================================
     * Orders (lookup only)
     * ========================================================== */

    protected function orders(array $ctx): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $q     = $this->qFromRequest();
            $lim   = $this->limitFromRequest();

            $where  = "o.org_id = :o";
            $params = [':o' => $orgId, ':lim' => $lim];

            if ($q !== '') {
                if (ctype_digit($q)) {
                    $where .= " AND (
                        o.id = :qid
                        OR o.order_no LIKE :q
                        OR o.reference LIKE :q
                    )";
                    $params[':qid'] = (int)$q;
                    $params[':q']   = '%' . $this->escapeLike($q) . '%';
                } else {
                    $where .= " AND (
                        o.order_no  COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                        OR o.reference COLLATE utf8mb4_unicode_ci LIKE :q ESCAPE '\\\\'
                    )";
                    $params[':q'] = '%' . $this->escapeLike($q) . '%';
                }
            }

            $sql = "
                SELECT
                    o.id,
                    o.order_no,
                    o.reference,
                    o.customer_id,
                    COALESCE(o.customer_name,'') AS customer_name
                FROM dms_orders o
                WHERE {$where}
                ORDER BY o.id DESC
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->bindValue(':lim', $lim, PDO::PARAM_INT);
            $st->execute();

            $rows  = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];

            foreach ($rows as $r) {
                $no   = (string)($r['order_no'] ?? '');
                $ref  = (string)($r['reference'] ?? '');
                $cust = (string)($r['customer_name'] ?? '');

                $items[] = $this->formatItem([
                    'id'            => (int)$r['id'],
                    'order_no'      => $no,
                    'reference'     => $ref,
                    'customer_id'   => isset($r['customer_id']) ? (int)$r['customer_id'] : null,
                    'customer_name' => $cust,
                    'label'         => ($no !== '' ? $no : ('#' . $r['id'])),
                    'sublabel'      => $cust !== '' ? $cust : $ref,
                    'meta'          => [],
                ]);
            }

            $this->respond($items, [
                'q'     => $q,
                'limit' => $lim,
            ]);
        } catch (\Throwable $e) {
            error_log('Lookup orders failed: ' . $e->getMessage());
            $this->respond([], []);
        }
    }
}