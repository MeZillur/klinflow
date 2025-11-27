<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

final class LookupController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    /** Resolve org_id from tenant session */
    private function orgId(): int
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        $org = $_SESSION['tenant_org'] ?? null;
        $id  = (int)($org['id'] ?? 0);

        if ($id <= 0) {
            throw new \RuntimeException('Org not resolved in LookupController');
        }
        return $id;
    }

    /* -------------------------------------------------------------
     * GET /api/lookup — discovery
     * ----------------------------------------------------------- */
    public function index(array $ctx = []): void
    {
        $this->json([
            'entities' => [
                // Items / products
                'item',
                'items',
                'product',
                'products',

                // Customers
                'customer',
                'customers',

                // Suppliers
                'supplier',
                'suppliers',
            ],
            'hint' => 'Use /api/lookup/{entity}?q=term&limit=30',
        ]);
    }

    /* -------------------------------------------------------------
     * GET /api/lookup/{entity}?q=...
     * ----------------------------------------------------------- */
    public function handle(array $ctx, string $entity): void
    {
        try {
            $entity = strtolower(trim($entity));

            switch ($entity) {
                // Items / products
                case 'item':
                case 'items':
                case 'product':
                case 'products':
                    $this->items();
                    return;

                // Customers
                case 'customer':
                case 'customers':
                    $this->customers();
                    return;

                // Suppliers
                case 'supplier':
                case 'suppliers':
                    $this->suppliers();
                    return;

                default:
                    $this->json(['items' => [], 'error' => 'unknown entity'], 404);
                    return;
            }
        } catch (Throwable $e) {
            $this->oops('Lookup failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Items → biz_items
     *   Used by: data-kf-lookup="items" (or "item"/"products")
     *   Returns: { items:[{id, code, name, unit, unit_price, label, sublabel,...}] }
     * ----------------------------------------------------------- */
    private function items(): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId();
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        // Build WHERE dynamically
        $where = "i.org_id = :o AND i.item_type = 'stock'";
        if ($q !== '') {
            $where .= " AND (i.name LIKE :like OR i.code LIKE :like OR i.barcode LIKE :like)";
        }

        $sql = "
            SELECT
                i.id,
                i.code,
                i.name,
                i.unit,
                i.barcode,
                i.sale_price,
                i.description
            FROM biz_items i
            WHERE {$where}
            ORDER BY i.name ASC
            LIMIT :lim
        ";

        $st = $pdo->prepare($sql);

        // Required params
        $st->bindValue(':o',   $orgId, PDO::PARAM_INT);
        $st->bindValue(':lim', $lim,   PDO::PARAM_INT);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $st->bindValue(':like', $like, PDO::PARAM_STR);
        }

        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(static function (array $r): array {
            $id      = (int)($r['id'] ?? 0);
            $code    = (string)($r['code'] ?? '');
            $name    = (string)($r['name'] ?? '');
            $unit    = (string)($r['unit'] ?? '');
            $price   = (float)($r['sale_price'] ?? 0);
            $barcode = (string)($r['barcode'] ?? '');
            $desc    = (string)($r['description'] ?? '');

            $label = $name !== '' ? $name : $code;
            if ($name !== '' && $code !== '') {
                $label = "{$name} ({$code})";
            }

            $subParts = [];
            if ($barcode !== '') $subParts[] = $barcode;
            if ($unit    !== '') $subParts[] = $unit;
            $sublabel = implode(' • ', $subParts);

            return [
                'id'          => $id,
                'code'        => $code,
                'name'        => $name,
                'unit'        => $unit,
                'unit_price'  => $price,
                'price'       => $price,
                'barcode'     => $barcode,
                'description' => $desc,
                'label'       => $label,
                'sublabel'    => $sublabel,
            ];
        }, $rows);

        $this->json(['items' => $items]);
    }

    /* -------------------------------------------------------------
     * Customers → biz_customers
     *   Used by: data-kf-lookup="customers" / "customer"
     * ----------------------------------------------------------- */
    private function customers(): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId();
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        $sql = "
            SELECT id, name
            FROM biz_customers
            WHERE org_id = :o
              AND (:q = '' OR name LIKE :like)
            ORDER BY name ASC
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

        $items = array_map(static function (array $r): array {
            $name = (string)($r['name'] ?? '');
            return [
                'id'    => (int)$r['id'],
                'name'  => $name,
                'label' => $name,
            ];
        }, $rows);

        $this->json(['items' => $items]);
    }

    /* -------------------------------------------------------------
     * Suppliers → biz_suppliers
     *   Used by: data-kf-lookup="suppliers" / "supplier"
     *   Returns: { items:[{id, code, name, phone, email, label, sublabel}] }
     * ----------------------------------------------------------- */
    private function suppliers(): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId();
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = max(1, min(50, (int)($_GET['limit'] ?? 30)));

        $where = "s.org_id = :o";
        if ($q !== '') {
            $where .= " AND (s.name LIKE :like OR s.code LIKE :like OR s.phone LIKE :like OR s.email LIKE :like)";
        }

        $sql = "
            SELECT
                s.id,
                s.code,
                s.name,
                s.phone,
                s.email
            FROM biz_suppliers s
            WHERE {$where}
            ORDER BY s.name ASC
            LIMIT :lim
        ";

        $st = $pdo->prepare($sql);
        $st->bindValue(':o',   $orgId, PDO::PARAM_INT);
        $st->bindValue(':lim', $lim,   PDO::PARAM_INT);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $st->bindValue(':like', $like, PDO::PARAM_STR);
        }

        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(static function (array $r): array {
            $name  = (string)($r['name'] ?? '');
            $code  = (string)($r['code'] ?? '');
            $phone = (string)($r['phone'] ?? '');
            $email = (string)($r['email'] ?? '');

            $label = $name;
            if ($name !== '' && $code !== '') {
                $label = "{$name} ({$code})";
            } elseif ($label === '') {
                $label = $code;
            }

            $sub = trim($phone.' '.$email);

            return [
                'id'       => (int)$r['id'],
                'code'     => $code,
                'name'     => $name,
                'phone'    => $phone,
                'email'    => $email,
                'label'    => $label,
                'sublabel' => $sub,
            ];
        }, $rows);

        $this->json(['items' => $items]);
    }
}