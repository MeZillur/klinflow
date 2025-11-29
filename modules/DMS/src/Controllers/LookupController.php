<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;
use PDOStatement;
use Throwable;

/**
 * DMS LookupController (Tom Select / KF stack)
 *
 * Base URL (per tenant):
 *   /t/{slug}/apps/dms/api/lookup/{entity}?q=term&limit=30
 *
 * Supported entities:
 *   - products, customers, suppliers, orders, users (and stakeholders)
 *   - invoices  (new: lookup orders for invoice creation)
 *
 * Standard JSON shape:
 *   {
 *     "items": [...],
 *     "meta":  { q, limit, org_id, count, ... }
 *   }
 */
final class LookupController extends BaseController
{
    private const MAX_LIMIT     = 50;
    private const DEFAULT_LIMIT = 30;

    private array $schemaCache = [];
    private int $paramCounter = 0;
    private ?array $lastSqlDebug = null;

    public function __construct()
    {
    }

    private function isDebug(): bool
    {
        return (int)($_GET['debug'] ?? 0) === 1;
    }

    private function limitFromRequest(): int
    {
        $lim = (int)($_GET['limit'] ?? self::DEFAULT_LIMIT);
        return max(1, min(self::MAX_LIMIT, $lim));
    }

    private function qFromRequest(): string
    {
        return trim((string)($_GET['q'] ?? ''));
    }

    private function baseMeta(string $q, int $limit, int $orgId, int $count = 0): array
    {
        return [
            'q'      => $q,
            'limit'  => $limit,
            'org_id' => $orgId,
            'count'  => $count,
        ];
    }

    private function respond(array $items, array $meta = []): void
    {
        if (!array_key_exists('count', $meta)) {
            $meta['count'] = count($items);
        }
        $this->json([
            'items' => $items,
            'meta'  => $meta,
        ]);
    }

    private function respondError(string $error, array $metaExtras = [], int $httpCode = 500): void
    {
        http_response_code($httpCode);
        $q   = $this->qFromRequest();
        $lim = $this->limitFromRequest();
        $org = $this->resolveOrgId([]);
        $meta = $this->baseMeta($q, $lim, $org, 0);
        $meta['error'] = $error;
        $meta = array_merge($meta, $metaExtras);
        $this->respond([], $meta);
    }

    private function escapeLike(string $s): string
    {
        return strtr($s, [
            '\\' => '\\\\',
            '%'  => '\\%',
            '_'  => '\\_',
        ]);
    }

    private function newPlaceholder(string $prefix = 'q'): string
    {
        $this->paramCounter++;
        return ':' . $prefix . $this->paramCounter;
    }

    private function buildLikeClause(array $columns, string $q, array &$params, bool $useCollate = true): string
    {
        if ($q === '') return '';
        $like = '%' . $this->escapeLike($q) . '%';
        $parts = [];
        foreach ($columns as $col) {
            $ph = $this->newPlaceholder('q');
            $parts[] = $col . ($useCollate ? " COLLATE utf8mb4_unicode_ci" : '') . " LIKE {$ph}";
            $params[$ph] = $like;
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    private function bindAll(PDOStatement $st, array $params): void
    {
        foreach ($params as $k => $v) {
            $name = (string)$k;
            if ($name === '') continue;
            if ($name[0] !== ':') $name = ':' . $name;
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $st->bindValue($name, $v, $type);
        }
    }

    private function normalizeScientificBarcode(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        if (stripos($s, 'e') === false) return $s;

        $parts = preg_split('/e/i', $s);
        if (!isset($parts[0]) || !isset($parts[1])) return $s;

        $mantissa = $parts[0];
        $exp = (int)$parts[1];
        $neg = false;
        if ($mantissa !== '' && ($mantissa[0] === '-' || $mantissa[0] === '+')) {
            if ($mantissa[0] === '-') $neg = true;
            $mantissa = substr($mantissa, 1);
        }

        if (strpos($mantissa, '.') !== false) {
            [$intPart, $fracPart] = explode('.', $mantissa, 2);
        } else {
            $intPart = $mantissa;
            $fracPart = '';
        }

        $intPart = preg_replace('/\D+/', '', $intPart);
        $fracPart = preg_replace('/\D+/', '', $fracPart);
        $mantNoDot = $intPart . $fracPart;
        $decCount = strlen($fracPart);
        $shift = $exp - $decCount;
        if ($shift < 0) {
            $fallback = @rtrim(sprintf('%.0f', (float)$s), '.');
            return $fallback !== '' ? $fallback : $s;
        }
        $result = $mantNoDot . str_repeat('0', $shift);
        $result = ltrim($result, '0');
        if ($result === '') $result = '0';
        if ($neg) $result = '-' . $result;
        return $result;
    }

    private function resolveOrgId(array $ctx): int
    {
        try {
            if (method_exists($this, 'orgId')) {
                $id = (int)$this->orgId($ctx);
                if ($id > 0) return $id;
            }
        } catch (Throwable $e) {
            error_log('[DMS LookupController::resolveOrgId] ' . $e->getMessage());
        }
        try {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $org = $_SESSION['tenant_org'] ?? null;
            $id  = (int)($org['id'] ?? 0);
            try { @\session_write_close(); } catch (Throwable $e) {}
            return $id;
        } catch (Throwable $e) {
            error_log('[DMS LookupController::resolveOrgId(session)] ' . $e->getMessage());
        }
        return 0;
    }

    private function formatItem(array $item): array
    {
        return $item;
    }

    public function index(array $ctx = []): void
    {
        $this->json([
            'entities' => [
                'products',
                'customers',
                'suppliers',
                'orders',
                'invoices',
                'users',
                'stakeholders',
            ],
            'hint' => 'Use /api/lookup/{entity}?q=term&limit=30',
        ]);
    }

    public function handle(array $ctx, string $entity): void
    {
        $entity = strtolower(trim($entity));
        $requestMeta = [
            'entity' => $entity,
            'q' => $this->qFromRequest(),
            'limit' => $this->limitFromRequest(),
            'debug' => $this->isDebug() ? 1 : 0,
        ];
        try {
            $requestMeta['org_id'] = $this->resolveOrgId($ctx);
        } catch (Throwable $ignored) {
            $requestMeta['org_id'] = 0;
        }

        try {
            switch ($entity) {
                case 'product': case 'products': case 'item': case 'items':
                    $this->products($ctx); return;
                case 'customer': case 'customers':
                    $this->customers($ctx); return;
                case 'supplier': case 'suppliers':
                    $this->suppliers($ctx); return;
                case 'order': case 'orders':
                    $this->orders($ctx); return;
                case 'invoice': case 'invoices':
                    $this->invoices($ctx); return;
                case 'user': case 'users':
                    $this->users($ctx); return;
                case 'stakeholder': case 'stakeholders':
                    $this->users($ctx); return;
                default:
                    $meta = $this->baseMeta($this->qFromRequest(), $this->limitFromRequest(), $requestMeta['org_id'], 0);
                    $meta['error'] = 'unknown_entity';
                    $meta['entity'] = $entity;
                    $this->respond([], $meta);
                    return;
            }
        } catch (Throwable $e) {
            try {
                $errId = bin2hex(random_bytes(12));
            } catch (Throwable $t) {
                $errId = substr(sha1((string)microtime(true) . $e->getMessage()), 0, 24);
            }
            $logEntry = sprintf(
                "[DMS LookupController::handle][%s] %s in %s:%d\nStack trace:\n%s\nRequest: %s\n",
                $errId, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString(),
                json_encode($requestMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            $prev = $e->getPrevious();
            if ($prev !== null) $logEntry .= "Previous: " . (string)$prev . "\n";
            if ($this->lastSqlDebug) $logEntry .= "SQL debug: " . json_encode($this->lastSqlDebug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            error_log($logEntry);

            http_response_code(500);
            $meta = $this->baseMeta($this->qFromRequest(), $this->limitFromRequest(), $requestMeta['org_id'], 0);
            $meta['error'] = 'unexpected_error';
            $meta['id'] = $errId;
            $meta['hint'] = 'Something went wrong. Provide this ID to support.';
            if ($this->isDebug()) {
                $meta['debug'] = ['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()];
            }
            $this->respond([], $meta);
        }
    }

    /* PRODUCTS (as before) */
    private function products(array $ctx): void
    {
        $this->paramCounter = 0;
        $this->lastSqlDebug = null;

        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx);
        $q     = $this->qFromRequest();
        $lim   = $this->limitFromRequest();
        $debug = $this->isDebug();

        if ($orgId <= 0) {
            $this->respondError('org_missing', ['org_id' => $orgId], 400);
            return;
        }

        try {
            $where  = 'p.org_id = :o';
            $params = [':o' => $orgId];

            if ($q !== '') {
                $likeClause = $this->buildLikeClause(['p.name','p.code','CAST(p.barcode AS CHAR)'],$q,$params,true);
                if ($likeClause !== '') $where .= " AND {$likeClause}";
            }

            $orderClause = ($q === '')
                ? "ORDER BY (CASE WHEN COALESCE(p.name,'') = '' THEN 1 ELSE 0 END), p.name ASC, p.id DESC"
                : "ORDER BY p.name ASC";

            $sql = "
                SELECT p.id, COALESCE(p.code,'') AS code, COALESCE(p.name,'') AS name,
                       p.unit_price, p.category_id, p.uom_name, CAST(p.barcode AS CHAR) AS barcode
                FROM dms_products p
                WHERE {$where}
                {$orderClause}
                LIMIT :lim
            ";

            $st = $pdo->prepare($sql);
            $this->bindAll($st,$params);
            $st->bindValue(':lim',$lim,PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $usedSql = $sql; $usedParams = $params;
            if ($debug) $this->lastSqlDebug = ['sql'=>$usedSql,'params'=>$usedParams];

            if (!$rows && $q !== '') {
                $this->paramCounter = 0;
                $params2 = [':o'=>$orgId];
                $fallbackOrder = "ORDER BY (CASE WHEN COALESCE(p.name,'') = '' THEN 1 ELSE 0 END), p.name ASC, p.id DESC";
                $sql2 = "
                    SELECT p.id, COALESCE(p.code,'') AS code, COALESCE(p.name,'') AS name,
                           p.unit_price, p.category_id, p.uom_name, CAST(p.barcode AS CHAR) AS barcode
                    FROM dms_products p
                    WHERE p.org_id = :o
                    {$fallbackOrder}
                    LIMIT :lim
                ";
                $st2 = $pdo->prepare($sql2);
                $this->bindAll($st2,$params2);
                $st2->bindValue(':lim',$lim,PDO::PARAM_INT);
                $st2->execute();
                $rows = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $usedSql = $sql2; $usedParams = $params2;
                if ($debug) $this->lastSqlDebug = ['sql'=>$usedSql,'params'=>$usedParams];
            }

            $items = [];
            foreach ($rows as $r) {
                $id = (int)$r['id'];
                $name = (string)($r['name'] ?? '');
                $code = (string)($r['code'] ?? '');
                $barcodeRaw = (string)($r['barcode'] ?? '');
                $barcode = $this->normalizeScientificBarcode($barcodeRaw);
                $price = isset($r['unit_price']) && $r['unit_price'] !== null ? (float)$r['unit_price'] : null;
                if ($name !== '') {
                    $label = trim($name . ($code !== '' ? " ({$code})" : ''));
                } elseif ($code !== '') {
                    $label = $code;
                } elseif ($barcode !== '') {
                    $label = $barcode;
                } else {
                    $label = '#' . $id;
                }
                $items[] = $this->formatItem([
                    'id'=>$id,'name'=>$name,'code'=>$code,'label'=>$label,'sublabel'=>$barcode,
                    'unit_price'=>$price,'price'=>$price,
                    'category_id'=>isset($r['category_id'])? (int)$r['category_id'] : null,
                    'uom_name'=>(string)($r['uom_name'] ?? ''),'barcode'=>$barcode
                ]);
            }

            $meta = $this->baseMeta($q,$lim,$orgId,count($items));
            if ($debug) $meta['debug']=['sql'=>$usedSql,'params'=>$usedParams];
            $this->respond($items,$meta);
        } catch (Throwable $e) {
            error_log('[DMS LookupController::products] ' . $e->getMessage());
            if ($this->isDebug()) {
                $this->respond([], array_merge($this->baseMeta($q,$lim,$orgId,0), ['error'=>'products_lookup_failed','debug'=>$e->getMessage()]));
            } else {
                $this->respondError('products_lookup_failed',['org_id'=>$orgId],500);
            }
        }
    }

    /* CUSTOMERS */
    private function customers(array $ctx): void
    {
        $this->paramCounter = 0; $this->lastSqlDebug = null;
        $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx);
        $q = $this->qFromRequest(); $lim = $this->limitFromRequest(); $debug = $this->isDebug();
        if ($orgId <= 0) { $this->respondError('org_missing',['org_id'=>$orgId],400); return; }

        try {
            $where = 'c.org_id = :o'; $params = [':o'=>$orgId];
            if ($q !== '') {
                $likeClause = $this->buildLikeClause(['c.name','c.code','c.phone','c.email'],$q,$params,true);
                if ($likeClause !== '') $where .= " AND {$likeClause}";
            }
            $sql = "
                SELECT c.id, COALESCE(c.code,'') AS code, COALESCE(c.name,'') AS name,
                       COALESCE(c.phone,'') AS phone, COALESCE(c.email,'') AS email,
                       COALESCE(c.address,'') AS address
                FROM dms_customers c
                WHERE {$where}
                ORDER BY c.name ASC
                LIMIT :lim
            ";
            $st = $pdo->prepare($sql);
            $this->bindAll($st,$params);
            $st->bindValue(':lim',$lim,PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $usedSql=$sql; $usedParams=$params;
            if ($debug) $this->lastSqlDebug=['sql'=>$usedSql,'params'=>$usedParams];

            if (!$rows && $q !== '') {
                $this->paramCounter = 0; $params2=[':o'=>$orgId];
                $sql2 = "
                    SELECT c.id, COALESCE(c.code,'') AS code, COALESCE(c.name,'') AS name,
                           COALESCE(c.phone,'') AS phone, COALESCE(c.email,'') AS email,
                           COALESCE(c.address,'') AS address
                    FROM dms_customers c
                    WHERE c.org_id = :o
                    ORDER BY c.name ASC
                    LIMIT :lim
                ";
                $st2 = $pdo->prepare($sql2);
                $this->bindAll($st2,$params2);
                $st2->bindValue(':lim',$lim,PDO::PARAM_INT);
                $st2->execute();
                $rows = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $usedSql=$sql2; $usedParams=$params2;
                if ($debug) $this->lastSqlDebug=['sql'=>$usedSql,'params'=>$usedParams];
            }

            $items = [];
            foreach($rows as $r) {
                $id=(int)$r['id']; $name=(string)($r['name']??''); $code=(string)($r['code']??'');
                $phone=(string)($r['phone']??''); $email=(string)($r['email']??''); $address=(string)($r['address']??'');
                if ($name !== '') { $label = ($code !== '' ? "[{$code}] " : '') . $name; }
                elseif ($code !== '') { $label = $code; }
                elseif ($phone !== '') { $label = $phone; }
                elseif ($email !== '') { $label = $email; }
                else { $label = '#' . $id; }
                $items[] = $this->formatItem([
                    'id'=>$id,'name'=>$name,'code'=>$code,'phone'=>$phone,'email'=>$email,'address'=>$address,
                    'label'=>$label,'sublabel'=>$phone !== '' ? $phone : $email
                ]);
            }
            $meta=$this->baseMeta($q,$lim,$orgId,count($items));
            if ($debug) $meta['debug']=['sql'=>$usedSql,'params'=>$usedParams];
            $this->respond($items,$meta);
        } catch (Throwable $e) {
            error_log('[DMS LookupController::customers] ' . $e->getMessage());
            if ($this->isDebug()) {
                $this->respond([], array_merge($this->baseMeta($q,$lim,$orgId,0), ['error'=>'customers_lookup_failed','debug'=>$e->getMessage()]));
            } else {
                $this->respondError('customers_lookup_failed',['org_id'=>$orgId],500);
            }
        }
    }

    /* SUPPLIERS */
    private function suppliers(array $ctx): void
    {
        $this->paramCounter = 0; $this->lastSqlDebug = null;
        $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx);
        $q = $this->qFromRequest(); $lim = $this->limitFromRequest(); $debug = $this->isDebug();
        if ($orgId <= 0) { $this->respondError('org_missing',['org_id'=>$orgId],400); return; }

        try {
            $where='s.org_id = :o'; $params=[':o'=>$orgId];
            if ($q !== '') {
                $likeClause = $this->buildLikeClause(['s.name','s.code','s.phone','s.email'],$q,$params,true);
                if ($likeClause !== '') $where .= " AND {$likeClause}";
            }
            $sql = "
                SELECT s.id, COALESCE(s.code,'') AS code, COALESCE(s.name,'') AS name,
                       COALESCE(s.phone,'') AS phone, COALESCE(s.email,'') AS email
                FROM dms_suppliers s
                WHERE {$where}
                ORDER BY s.name ASC
                LIMIT :lim
            ";
            $st = $pdo->prepare($sql);
            $this->bindAll($st,$params);
            $st->bindValue(':lim',$lim,PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $usedSql=$sql; $usedParams=$params;
            if ($debug) $this->lastSqlDebug=['sql'=>$usedSql,'params'=>$usedParams];

            if (!$rows && $q !== '') {
                $this->paramCounter = 0; $params2=[':o'=>$orgId];
                $sql2 = "
                    SELECT s.id, COALESCE(s.code,'') AS code, COALESCE(s.name,'') AS name,
                           COALESCE(s.phone,'') AS phone, COALESCE(s.email,'') AS email
                    FROM dms_suppliers s
                    WHERE s.org_id = :o
                    ORDER BY s.name ASC
                    LIMIT :lim
                ";
                $st2 = $pdo->prepare($sql2);
                $this->bindAll($st2,$params2);
                $st2->bindValue(':lim',$lim,PDO::PARAM_INT);
                $st2->execute();
                $rows = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $usedSql=$sql2; $usedParams=$params2;
                if ($debug) $this->lastSqlDebug=['sql'=>$usedSql,'params'=>$usedParams];
            }

            $items=[];
            foreach($rows as $r) {
                $id=(int)$r['id']; $name=(string)($r['name']??''); $code=(string)($r['code']??'');
                $phone=(string)($r['phone']??''); $email=(string)($r['email']??'');
                $sub = trim($phone . ' ' . $email);
                $label = $name !== '' ? trim($name . ($code !== '' ? " ({$code})" : '')) : ($code !== '' ? $code : ($phone !== '' ? $phone : '#' . $id));
                $items[] = $this->formatItem(['id'=>$id,'name'=>$name,'code'=>$code,'phone'=>$phone,'email'=>$email,'label'=>$label,'sublabel'=>$sub]);
            }
            $meta=$this->baseMeta($q,$lim,$orgId,count($items));
            if ($debug) $meta['debug']=['sql'=>$usedSql,'params'=>$usedParams];
            $this->respond($items,$meta);
        } catch (Throwable $e) {
            error_log('[DMS LookupController::suppliers] ' . $e->getMessage());
            if ($this->isDebug()) {
                $this->respond([], array_merge($this->baseMeta($q,$lim,$orgId,0), ['error'=>'suppliers_failed','debug'=>$e->getMessage()]));
            } else {
                $this->respondError('suppliers_failed',['org_id'=>$orgId],500);
            }
        }
    }

    /* ORDERS */
    private function orders(array $ctx): void
    {
        // Basic order lookup used elsewhere; invoices() is specialized for invoicing flow.
        $this->paramCounter = 0; $this->lastSqlDebug = null;
        $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx);
        $q = $this->qFromRequest(); $lim = $this->limitFromRequest(); $debug = $this->isDebug();
        if ($orgId <= 0) { $this->respondError('org_missing',['org_id'=>$orgId],400); return; }

        try {
            $where='o.org_id = :o'; $params=[':o'=>$orgId];
            if ($q !== '') {
                if (ctype_digit($q)) {
                    $params[':qid'] = (int)$q;
                    $likePart = $this->buildLikeClause(['o.order_no','o.reference'],$q,$params,true);
                    $where .= " AND (o.id = :qid" . ($likePart !== '' ? " OR {$likePart}" : '') . ")";
                } else {
                    $where .= " AND " . $this->buildLikeClause(['o.order_no','o.reference'],$q,$params,true);
                }
            }
            $sql = "
                SELECT o.id, COALESCE(o.order_no,'') AS order_no, COALESCE(o.reference,'') AS reference,
                       o.customer_id, COALESCE(o.customer_name,'') AS customer_name
                FROM dms_orders o
                WHERE {$where}
                ORDER BY o.id DESC
                LIMIT :lim
            ";
            $st = $pdo->prepare($sql);
            $this->bindAll($st,$params);
            $st->bindValue(':lim',$lim,PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $usedSql=$sql; $usedParams=$params;
            if ($debug) $this->lastSqlDebug=['sql'=>$usedSql,'params'=>$usedParams];

            $items=[];
            foreach($rows as $r) {
                $id=(int)$r['id']; $no=(string)($r['order_no']??''); $ref=(string)($r['reference']??''); $cust=(string)($r['customer_name']??'');
                $label = $no !== '' ? $no : ('#' . $id);
                $sublabel = $cust !== '' ? $cust : $ref;
                $items[] = $this->formatItem(['id'=>$id,'order_no'=>$no,'reference'=>$ref,'customer_id'=>isset($r['customer_id'])?(int)$r['customer_id']:null,'customer_name'=>$cust,'label'=>$label,'sublabel'=>$sublabel]);
            }
            $meta = $this->baseMeta($q,$lim,$orgId,count($items));
            if ($debug) $meta['debug']=['sql'=>$usedSql,'params'=>$usedParams];
            $this->respond($items,$meta);
        } catch (Throwable $e) {
            error_log('[DMS LookupController::orders] ' . $e->getMessage());
            if ($this->isDebug()) {
                $this->respond([], array_merge($this->baseMeta($q,$lim,$orgId,0), ['error'=>'orders_lookup_failed','debug'=>$e->getMessage()]));
            } else {
                $this->respondError('orders_lookup_failed',['org_id'=>$orgId],500);
            }
        }
    }

    /* INVOICES (order picker for invoice creation) */
    private function invoices(array $ctx): void
    {
        $this->paramCounter = 0; $this->lastSqlDebug = null;
        $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx);
        $q = $this->qFromRequest(); $lim = $this->limitFromRequest(); $debug = $this->isDebug();
        if ($orgId <= 0) { $this->respondError('org_missing',['org_id'=>$orgId],400); return; }

        // Optional flag: only return orders that are not invoiced (invoice_id IS NULL)
        $uninvoiced = (int)($_GET['uninvoiced'] ?? 0) === 1;

        try {
            $where = 'o.org_id = :o';
            $params = [':o' => $orgId];

            if ($uninvoiced) {
                // Adapt if your schema uses another column for invoices (invoice_id, is_invoiced, status etc.)
                $where .= " AND o.invoice_id IS NULL";
            }

            if ($q !== '') {
                if (ctype_digit($q)) {
                    $params[':qid'] = (int)$q;
                    $likePart = $this->buildLikeClause(['o.order_no','o.reference','COALESCE(o.customer_name,\'\')'],$q,$params,true);
                    $where .= " AND (o.id = :qid" . ($likePart !== '' ? " OR {$likePart}" : '') . ")";
                } else {
                    $where .= " AND " . $this->buildLikeClause(['o.order_no','o.reference','COALESCE(o.customer_name,\'\')'],$q,$params,true);
                }
            }

            $sql = "
                SELECT o.id, COALESCE(o.order_no,'') AS order_no, COALESCE(o.reference,'') AS reference,
                       o.customer_id, COALESCE(o.customer_name,'') AS customer_name, o.total_amount
                FROM dms_orders o
                WHERE {$where}
                ORDER BY o.id DESC
                LIMIT :lim
            ";
            $st = $pdo->prepare($sql);
            $this->bindAll($st,$params);
            $st->bindValue(':lim',$lim,PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $usedSql=$sql; $usedParams=$params;
            if ($debug) $this->lastSqlDebug=['sql'=>$usedSql,'params'=>$usedParams];

            $items=[];
            foreach($rows as $r) {
                $id=(int)$r['id'];
                $no=(string)($r['order_no']??'');
                $ref=(string)($r['reference']??'');
                $cust=(string)($r['customer_name']??'');
                $total = isset($r['total_amount']) ? (float)$r['total_amount'] : null;
                $label = $no !== '' ? $no : ('#' . $id);
                $sublabel = $cust !== '' ? $cust : $ref;
                $items[] = $this->formatItem([
                    'id'=>$id,
                    'order_no'=>$no,
                    'reference'=>$ref,
                    'customer_id'=> isset($r['customer_id']) ? (int)$r['customer_id'] : null,
                    'customer_name'=>$cust,
                    'total_amount'=>$total,
                    'label'=>$label,
                    'sublabel'=>$sublabel,
                ]);
            }

            $meta = $this->baseMeta($q,$lim,$orgId,count($items));
            if ($debug) $meta['debug']=['sql'=>$usedSql,'params'=>$usedParams];
            $this->respond($items,$meta);
        } catch (Throwable $e) {
            error_log('[DMS LookupController::invoices] ' . $e->getMessage());
            if ($this->isDebug()) {
                $this->respond([], array_merge($this->baseMeta($q,$lim,$orgId,0), ['error'=>'invoices_lookup_failed','debug'=>$e->getMessage()]));
            } else {
                $this->respondError('invoices_lookup_failed',['org_id'=>$orgId],500);
            }
        }
    }

    /* USERS */
    private function users(array $ctx): void
    {
        $this->paramCounter = 0; $this->lastSqlDebug = null;
        $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx);
        $q = $this->qFromRequest(); $lim = $this->limitFromRequest(); $debug = $this->isDebug();
        if ($orgId <= 0) { $this->respondError('org_missing',['org_id'=>$orgId],400); return; }

        try {
            $where='u.org_id = :o'; $params=[':o'=>$orgId];
            if ($q !== '') {
                $likeClause = $this->buildLikeClause(['u.name','u.email'],$q,$params,true);
                if ($likeClause !== '') $where .= " AND {$likeClause}";
            }
            $sql = "
                SELECT u.id, COALESCE(u.name,'') AS name, COALESCE(u.email,'') AS email
                FROM cp_users u
                WHERE {$where}
                ORDER BY u.name ASC
                LIMIT :lim
            ";
            $st = $pdo->prepare($sql);
            $this->bindAll($st,$params);
            $st->bindValue(':lim',$lim,PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $usedSql=$sql; $usedParams=$params;
            if ($debug) $this->lastSqlDebug=['sql'=>$usedSql,'params'=>$usedParams];

            $items=[];
            foreach($rows as $r) {
                $id=(int)$r['id']; $name=(string)($r['name']??''); $email=(string)($r['email']??'');
                $label = $name !== '' ? $name : ($email !== '' ? $email : '#' . $id);
                $sub = ($email !== '' && $name !== '') ? $email : '';
                $items[] = $this->formatItem(['id'=>$id,'name'=>$name,'email'=>$email,'label'=>$label,'sublabel'=>$sub]);
            }
            $meta=$this->baseMeta($q,$lim,$orgId,count($items));
            if ($debug) $meta['debug']=['sql'=>$usedSql,'params'=>$usedParams];
            $this->respond($items,$meta);
        } catch (Throwable $e) {
            error_log('[DMS LookupController::users] ' . $e->getMessage());
            if ($this->isDebug()) {
                $this->respond([], array_merge($this->baseMeta($q,$lim,$orgId,0), ['error'=>'users_lookup_failed','debug'=>$e->getMessage()]));
            } else {
                $this->respondError('users_lookup_failed',['org_id'=>$orgId],500);
            }
        }
    }
}
