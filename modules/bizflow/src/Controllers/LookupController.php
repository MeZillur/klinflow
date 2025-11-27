<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

final class LookupController extends BaseController
{
  
  // Temporary debug method: call via /api/lookup/debug
public function debug(array $ctx = []): void
{
    try {
        if (\PHP_SESSION_ACTIVE !== session_status()) @session_start();
        $sess = $_SESSION ?? null;
        $sid = session_id();

        // try resolve org id using your existing helper but avoid throwing
        $orgId = 0;
        try { $orgId = $this->orgId(); } catch (\Throwable $e) { /* ignore */ }

        $pdo = $this->pdo();

        // supplier count for this org (safe query)
        $st = $pdo->prepare("SELECT COUNT(*) AS c FROM biz_suppliers WHERE org_id = :o");
        $st->bindValue(':o', (int)$orgId, \PDO::PARAM_INT);
        $st->execute();
        $count = (int)($st->fetchColumn() ?: 0);

        // sample suppliers (max 5)
        $st2 = $pdo->prepare("SELECT id, name, code, phone, email FROM biz_suppliers WHERE org_id = :o ORDER BY name LIMIT 5");
        $st2->bindValue(':o', (int)$orgId, \PDO::PARAM_INT);
        $st2->execute();
        $rows = $st2->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->json([
            'session_id' => $sid,
            'session_tenant_org' => $sess['tenant_org'] ?? null,
            'resolved_org_id' => $orgId,
            'supplier_count' => $count,
            'sample_suppliers' => $rows,
        ]);
    } catch (\Throwable $e) {
        error_log('[LookupController::debug] ' . $e->getMessage());
        $this->json(['error' => 'debug failed'], 500);
    }
}
  
  
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    /**
     * Resolve org_id from tenant session and immediately close the session
     * to avoid PHP session file locking for subsequent concurrent AJAX calls.
     *
     * Throws RuntimeException if org not resolved.
     */
    private function orgId(): int
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        // Read tenant_org from session then close the session quickly
        $org = $_SESSION['tenant_org'] ?? null;
        $id  = (int)($org['id'] ?? 0);

        // Avoid holding the session lock for the rest of the request:
        // we already read what we need. This prevents AJAX calls queuing.
        try {
            @\session_write_close();
        } catch (\Throwable $e) {
            // ignore; we tried our best to release the lock
        }

        if ($id <= 0) {
            // Prefer throwing so callers catch and return JSON error consistently.
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
                'item', 'items', 'product', 'products',
                'customer', 'customers',
                'supplier', 'suppliers',
            ],
            'hint' => 'Use /api/lookup/{entity}?q=term&limit=30',
        ]);
    }

    /* -------------------------------------------------------------
     * GET /api/lookup/{entity}?q=...
     * Central dispatcher — returns JSON in all cases (including errors).
     * ----------------------------------------------------------- */
    public function handle(array $ctx, string $entity): void
    {
        try {
            $entity = strtolower(trim($entity));

            switch ($entity) {
                case 'item':
                case 'items':
                case 'product':
                case 'products':
                    $this->items();
                    return;

                case 'customer':
                case 'customers':
                    $this->customers();
                    return;

                case 'supplier':
                case 'suppliers':
                    $this->suppliers();
                    return;

                default:
                    $this->json(['items' => [], 'error' => 'unknown entity'], 404);
                    return;
            }
        } catch (Throwable $e) {
            // Ensure we always return JSON to the client (no HTML error pages)
            error_log('[LookupController::handle] ' . $e->getMessage());
            $this->json(['items' => [], 'error' => 'Lookup failed'], 500);
        }
    }

    /* -------------------------------------------------------------
     * Items → biz_items
     * Used by: data-kf-lookup="items" (or "product/s")
     * Returns: { items:[{id, code, name, unit, unit_price, label, sublabel,...}] }
     *
     * Improvements applied:
     * - early session_write_close in orgId()
     * - LIMIT interpolated as sanitized integer to avoid PDO native-prep issues
     * - prefix search (q%) for indexability; fallback to infix can be enabled
     * - try/catch with logged errors and JSON-safe response
     * ----------------------------------------------------------- */
    
    private function items(): void
   {
    try {
        $pdo   = $this->pdo();
        $orgId = $this->orgId();
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = (int)($_GET['limit'] ?? 30);
        $lim   = max(1, min(50, $lim));

        // Use prefix search by default (faster). Change to infix only if you've indexed for it.
        $useInfix = false;
        $likePattern = $q !== '' ? ($useInfix ? "%{$q}%" : "{$q}%") : null;

        $where = "i.org_id = :o AND i.item_type = 'stock'";
        if ($q !== '') {
            // Use distinct placeholders (PDO may not allow reusing a named placeholder multiple times)
            $where .= " AND (i.name LIKE :like1 OR i.code LIKE :like2 OR i.barcode LIKE :like3)";
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
            LIMIT {$lim}
        ";

        $st = $pdo->prepare($sql);
        $st->bindValue(':o', $orgId, PDO::PARAM_INT);

        if ($q !== '') {
            // Bind each placeholder separately to the same pattern
            $st->bindValue(':like1', (string)$likePattern, PDO::PARAM_STR);
            $st->bindValue(':like2', (string)$likePattern, PDO::PARAM_STR);
            $st->bindValue(':like3', (string)$likePattern, PDO::PARAM_STR);
        }

        if ($st->execute() === false) {
            $err = $st->errorInfo();
            error_log('[LookupController::items] PDO execute failed: ' . json_encode($err));
            throw new \RuntimeException('DB execute failed');
        }

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
    } catch (Throwable $e) {
        error_log('[LookupController::items] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $this->json(['items' => [], 'error' => 'items lookup failed'], 500);
    }
}
    /* -------------------------------------------------------------
     * Customers → biz_customers
     * Used by: data-kf-lookup="customers" / "customer"
     *
     * Notes:
     * - This query was working for you; pattern kept but hardened.
     * - Use prefix search for performance; infix can be enabled if needed.
     * ----------------------------------------------------------- */
    private function customers(): void
    {
        try {
            $pdo   = $this->pdo();
            $orgId = $this->orgId();
            $q     = trim((string)($_GET['q'] ?? ''));
            $lim   = (int)($_GET['limit'] ?? 30);
            $lim   = max(1, min(50, $lim));

            $useInfix = false;
            $like = $q !== '' ? ($useInfix ? "%{$q}%" : "{$q}%") : null;

            if ($q === '') {
                $sql = "
                    SELECT id, name
                    FROM biz_customers
                    WHERE org_id = :o
                    ORDER BY name ASC
                    LIMIT {$lim}
                ";
                $st = $pdo->prepare($sql);
                $st->bindValue(':o', $orgId, PDO::PARAM_INT);
            } else {
                $sql = "
                    SELECT id, name
                    FROM biz_customers
                    WHERE org_id = :o
                      AND name LIKE :like
                    ORDER BY name ASC
                    LIMIT {$lim}
                ";
                $st = $pdo->prepare($sql);
                $st->bindValue(':o', $orgId, PDO::PARAM_INT);
                $st->bindValue(':like', (string)$like, PDO::PARAM_STR);
            }

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
        } catch (Throwable $e) {
            error_log('[LookupController::customers] ' . $e->getMessage());
            $this->json(['items' => [], 'error' => 'customers lookup failed'], 500);
        }
    }

    /* -------------------------------------------------------------
     * Suppliers → biz_suppliers
     * Used by: data-kf-lookup="suppliers" / "supplier"
     * Returns: { items:[{id, code, name, phone, email, label, sublabel}] }
     * ----------------------------------------------------------- */
    // Replace suppliers() with this
private function suppliers(): void
{
    try {
        $pdo   = $this->pdo();
        $orgId = $this->orgId();
        $q     = trim((string)($_GET['q'] ?? ''));
        $lim   = (int)($_GET['limit'] ?? 30);
        $lim   = max(1, min(100, $lim)); // modest upper bound

        // Choose prefix vs infix search:
        // - prefix (q%) is fast and index-friendly
        // - infix (%q%) is slower; enable for longer queries only
        $useInfix = strlen($q) >= 3; // infix only if q length >= 3
        $like = $q !== '' ? ($useInfix ? "%{$q}%" : "{$q}%") : null;

        $where = "s.org_id = :o";
        if ($q !== '') {
            // distinct placeholders to avoid PDO named-parameter reuse issues
            $where .= " AND (s.name LIKE :like1 OR s.code LIKE :like2 OR s.phone LIKE :like3 OR s.email LIKE :like4)";
        }

        // Interpolate LIMIT as integer (safer across PDO drivers)
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
            LIMIT {$lim}
        ";

        $st = $pdo->prepare($sql);
        $st->bindValue(':o', $orgId, PDO::PARAM_INT);

        if ($q !== '') {
            $st->bindValue(':like1', (string)$like, PDO::PARAM_STR);
            $st->bindValue(':like2', (string)$like, PDO::PARAM_STR);
            $st->bindValue(':like3', (string)$like, PDO::PARAM_STR);
            $st->bindValue(':like4', (string)$like, PDO::PARAM_STR);
        }

        if ($st->execute() === false) {
            $err = $st->errorInfo();
            error_log('[LookupController::suppliers] PDO execute failed: ' . json_encode($err));
            $this->json(['items' => []]);
            return;
        }

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(static function (array $r): array {
            $name = trim((string)($r['name'] ?? ''));
            $code = trim((string)($r['code'] ?? ''));
            $label = $name !== '' ? $name : $code;
            if ($name !== '' && $code !== '') $label = "{$name} ({$code})";
            $sub = trim(($r['phone'] ?? '') . ' ' . ($r['email'] ?? ''));

            return [
                'id'       => (int)($r['id'] ?? 0),
                'code'     => $code,
                'name'     => $name,
                'phone'    => (string)($r['phone'] ?? ''),
                'email'    => (string)($r['email'] ?? ''),
                'label'    => $label,
                'sublabel' => $sub,
            ];
        }, $rows);

        $this->json(['items' => $items]);
    } catch (Throwable $e) {
        error_log('[LookupController::suppliers] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $this->json(['items' => []]);
    }
}
}