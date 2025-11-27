<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;
use Throwable;

final class DmsChallanController extends BaseController
{
    /* ============================================================
     * Shared helpers (copied from SalesController style)
     * ========================================================== */

    /** Ensure slug + module_base are set in ctx */
    private function ensureBase(array &$ctx): void
    {
        $slug = (string)($ctx['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));
        if ($slug === '' || $slug === '_') {
            if (!empty($_SERVER['REQUEST_URI']) &&
                preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
                $slug = $m[1];
            }
        }
        $ctx['slug'] = $slug;

        $mb = (string)($ctx['module_base'] ?? '');
        if ($mb === '' || !preg_match('#^/t/[^/]+/apps/dms$#', $mb)) {
            $ctx['module_base'] = ($slug !== '' && $slug !== '_')
                ? '/t/' . rawurlencode($slug) . '/apps/dms'
                : '/apps/dms';
        }
    }

    /** Resolve org_id like SalesController::resolveOrgId */
    private function resolveOrgId(array &$ctx, PDO $pdo): int
    {
        $id = (int)($ctx['org']['id'] ?? 0);
        if ($id > 0) return $id;

        $id = (int)($_SESSION['tenant_org']['id'] ?? 0);
        if ($id > 0) { $ctx['org']['id'] = $id; return $id; }

        $slug = (string)($ctx['slug'] ?? '');
        if ($slug === '' || $slug === '_') {
            if (!empty($_SERVER['REQUEST_URI']) &&
                preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
                $slug = $m[1];
                $ctx['slug'] = $slug;
            }
        }
        if ($slug !== '' && $this->hasColumn($pdo, 'cp_organizations', 'slug')) {
            $st = $pdo->prepare("SELECT id FROM cp_organizations WHERE slug=? LIMIT 1");
            $st->execute([$slug]);
            $id = (int)($st->fetchColumn() ?: 0);
            if ($id > 0) { $ctx['org']['id'] = $id; return $id; }
        }

        $st = $pdo->query("SELECT id FROM cp_organizations ORDER BY id LIMIT 1");
        $id = (int)($st?->fetchColumn() ?: 0);
        if ($id > 0) { $ctx['org']['id'] = $id; return $id; }

        throw new \RuntimeException('No tenant organization in context (org_id empty).');
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    }

  /**
 * Detect challan items table once and cache.
 * Supports both singular/plural variants so schema rename won't break print().
 */
/**
 * Detect challan items table once and cache.
 * Supports both singular/plural variants so schema rename won't break print().
 */
private function challanItemsTable(PDO $pdo): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // Adjust this list to whatever you actually have
    $candidates = [
        'dms_challans_items',  // new plural name
        'dms_challan_items',   // old singular (if still exists anywhere)
        'dms_challan_lines',   // optional fallback
    ];

    foreach ($candidates as $t) {
        // NOTE: no placeholders here – SHOW TABLES cannot use parameter markers
        $sql = "SHOW TABLES LIKE " . $pdo->quote($t);
        $st  = $pdo->query($sql);
        if ($st && $st->fetchColumn()) {
            $cache = $t;
            return $cache;
        }
    }

    throw new \RuntimeException(
        'No challan items table found (tried dms_challans_items, dms_challan_items, dms_challan_lines).'
    );
}
  
    /* -------------------------------------------------------------
 * Org logo + identity helpers (DMS, org-based storage)
 *
 * Logos (per org):
 *   modules/DMS/storage/uploads/logo/org_{ORG_ID}/logo.(png|jpg|jpeg|webp|svg)
 *
 * Identity JSON (optional):
 *   modules/DMS/storage/settings/org_{ORG_ID}/identity.json
 *
 * View contract:
 *   $logo['url']      → best <img src> (data: URL or fallback PNG)
 *   $logo['data_url'] → data: URL if available, else null
 *   $identity[...]    → name, address, phone, email
 * ----------------------------------------------------------- */

/** Base dir where per-tenant logos live for DMS. */
private function logoBaseDir(): string
{
    // DMS module root: modules/DMS
    // Final path example: modules/DMS/storage/uploads/logo/org_18/logo.png
    return dirname(__DIR__, 2) . '/storage/uploads/logo';
}

/** Base dir where per-tenant identity JSON lives for DMS. */
private function identityBaseDir(): string
{
    // Example file: modules/DMS/storage/settings/org_18/identity.json
    return dirname(__DIR__, 2) . '/storage/settings';
}

/** Ensure a directory exists (used by logo + identity). */
private function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
}

/**
 * Find current logo for this org.
 *
 * Returns:
 * [
 *   'dir'      => filesystem dir,
 *   'path'     => full filesystem path to logo.* or null,
 *   'url'      => best <img src> (data: URL or global fallback),
 *   'data_url' => base64 data: URL for inline use (print/PDF), or null,
 *   'exists'   => bool
 * ]
 */
private function currentLogoInfoForOrg(int $orgId): array
{
    $baseDir = $this->logoBaseDir();
    $orgKey  = 'org_' . $orgId;
    $dir     = $baseDir . '/' . $orgKey;

    // Make sure org-specific logo directory exists so future uploads work
    $this->ensureDir($dir);

    $candidates = ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.svg'];

    $filePath = null;
    $dataUrl  = null;

    foreach ($candidates as $file) {
        $p = $dir . '/' . $file;
        if (!is_file($p)) {
            continue;
        }

        $filePath = $p;

        $ext  = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        $mime = 'image/png';
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } elseif ($ext === 'svg') {
            $mime = 'image/svg+xml';
        }

        $raw = @file_get_contents($p);
        if ($raw !== false) {
            $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
        }

        // First matching logo.* wins
        break;
    }

    // Best src for <img>: prefer data URL (works even if /modules is not public),
    // otherwise fall back to the global KlinFlow logo.
    $bestSrc = $dataUrl ?: '/assets/brand/logo.png';

    return [
        'dir'      => $dir,
        'path'     => $filePath,
        'url'      => $bestSrc,
        'data_url' => $dataUrl,
        'exists'   => $filePath !== null,
    ];
}

/**
 * Load identity (name, address, phone, email) for this org from JSON,
 * falling back to cp_organizations values if JSON is missing / partial.
 *
 * JSON file example:
 *   {
 *     "name": "DependCore Ltd.",
 *     "address": "...",
 *     "phone": "...",
 *     "email": "..."
 *   }
 */
private function currentIdentityValuesForOrg(int $orgId, array $org): array
{
    $baseDir = $this->identityBaseDir();
    $orgKey  = 'org_' . $orgId;
    $dir     = $baseDir . '/' . $orgKey;

    // Ensure org-specific settings directory exists
    $this->ensureDir($dir);

    $file = $dir . '/identity.json';

    // Start with cp_organizations values as defaults
    $values = [
        'name'    => trim((string)($org['name'] ?? '')),
        'address' => trim((string)($org['address'] ?? '')),
        'phone'   => trim((string)($org['phone'] ?? '')),
        'email'   => trim((string)($org['email'] ?? '')),
    ];

    if (is_file($file)) {
        $raw  = @file_get_contents($file);
        $data = json_decode((string)$raw, true);

        if (is_array($data)) {
            foreach (['name', 'address', 'phone', 'email'] as $k) {
                if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') {
                    $values[$k] = trim($data[$k]);
                }
            }
        }
    }

    return $values;
}
   

    /* ============================================================
     * Prepare challan from invoice
     * ========================================================== */

    /** GET /challan/prepare?invoice_id=# */
    public function prepare(array $ctx): void
    {
        $this->ensureBase($ctx);

        try {
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            $invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
            if ($invoiceId <= 0) {
                $this->abort404('Missing invoice_id.');
            }

            // ---- Load invoice header ----
            $h = $pdo->prepare("SELECT * FROM dms_sales WHERE org_id=? AND id=? LIMIT 1");
            $h->execute([$orgId, $invoiceId]);
            $invoice = $h->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$invoice) {
                $this->abort404('Invoice not found.');
            }

            // ---- Load invoice items ----
            $lines = [];
            if ($this->hasTable($pdo, 'dms_sale_items')) {
                $q = $pdo->prepare(
                    "SELECT *
                       FROM dms_sale_items
                      WHERE org_id=? AND sale_id=?
                      ORDER BY id"
                );
                $q->execute([$orgId, $invoiceId]);
                $lines = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $this->view('challan/prepare', [
                'title'       => 'Prepare Delivery Challan',
                'invoice'     => $invoice,
                'lines'       => $lines,
                'active'      => 'challan',
                'subactive'   => 'challan.prepare',
            ], $ctx);

        } catch (Throwable $e) {
            if (isset($_GET['_debug'])) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "Challan prepare error: " . $e->getMessage() . "\n";
                exit;
            }
            $this->abort500('Challan prepare error: '.$e->getMessage());
        }
    }

    /* ============================================================
     * Index
     * ========================================================== */

 
public function index(array $ctx): void
{
    $this->ensureBase($ctx);

    try {
        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx, $pdo);

        // Challans + aggregated items/qty from dms_challans_items
        $sql = "
            SELECT c.*,
                   COALESCE(x.total_items, 0) AS total_items,
                   COALESCE(x.total_qty,   0) AS total_qty
              FROM dms_challans AS c
              LEFT JOIN (
                    SELECT org_id,
                           challan_id,
                           COUNT(*)              AS total_items,
                           SUM(COALESCE(qty, 0)) AS total_qty
                      FROM dms_challans_items
                     GROUP BY org_id, challan_id
              ) AS x
                ON x.org_id     = c.org_id
               AND x.challan_id = c.id
             WHERE c.org_id = ?
             ORDER BY c.id DESC
             LIMIT 100
        ";

        $st = $pdo->prepare($sql);
        $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Header stats
        $stats = [
            'total'      => count($rows),
            'waiting'    => 0,
            'dispatched' => 0,
            'cancelled'  => 0,
        ];
        foreach ($rows as $r) {
            $stName = strtolower((string)($r['status'] ?? ''));
            if ($stName === 'dispatched') {
                $stats['dispatched']++;
            } elseif ($stName === 'cancelled') {
                $stats['cancelled']++;
            } else {
                $stats['waiting']++;
            }
        }

        $this->view('challan/index', [
            'title'       => 'Delivery Challans',
            'rows'        => $rows,
            'stats'       => $stats,
            'active'      => 'challan',
            'subactive'   => 'challan.index',
            'org'         => $ctx['org'] ?? ($_SESSION['tenant_org'] ?? []),
            'module_base' => $ctx['module_base'] ?? null,
        ], $ctx);

    } catch (Throwable $e) {
        $this->abort500('Challan index failed: ' . $e->getMessage());
    }
}

    /* ============================================================
     * Store single challan (from invoice)
     * ========================================================== */

    /** POST /challan */
    public function store(array $ctx): void
    {
        if (method_exists($this, 'ensureBase')) {
            $this->ensureBase($ctx);
        }

        try {
            if (!method_exists($this, 'pdo')) {
                throw new \RuntimeException('DmsChallanController::pdo() not available');
            }

            /** @var PDO $pdo */
            $pdo = $this->pdo();

            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }

            $orgId = (int)($ctx['org']['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0));
            if ($orgId <= 0) {
                $slug = (string)($ctx['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));

                if ($slug === '' || $slug === '_') {
                    if (!empty($_SERVER['REQUEST_URI']) &&
                        preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
                        $slug = $m[1];
                        $ctx['slug'] = $slug;
                    }
                }

                if ($slug !== '') {
                    $canCheck = method_exists($this, 'hasColumn');
                    $hasSlug  = true;
                    if ($canCheck) {
                        try {
                            $hasSlug = $this->hasColumn($pdo, 'cp_organizations', 'slug');
                        } catch (\Throwable $e) {
                            $hasSlug = true;
                        }
                    }

                    if ($hasSlug) {
                        $st = $pdo->prepare("SELECT id FROM cp_organizations WHERE slug = ? LIMIT 1");
                        $st->execute([$slug]);
                        $orgId = (int)($st->fetchColumn() ?: 0);
                        if ($orgId > 0) {
                            $ctx['org']['id'] = $orgId;
                        }
                    }
                }

                if ($orgId <= 0) {
                    $st = $pdo->query("SELECT id FROM cp_organizations ORDER BY id LIMIT 1");
                    $orgId = (int)($st?->fetchColumn() ?: 0);
                    if ($orgId > 0) {
                        $ctx['org']['id'] = $orgId;
                    }
                }
            }

            if ($orgId <= 0) {
                throw new \RuntimeException('No tenant organization in context (org_id empty).');
            }

            if (method_exists($this, 'moduleBase')) {
                $base = $this->moduleBase($ctx);
            } else {
                $slug = (string)($ctx['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));
                if ($slug === '' || $slug === '_') {
                    if (!empty($_SERVER['REQUEST_URI']) &&
                        preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
                        $slug = $m[1];
                        $ctx['slug'] = $slug;
                    }
                }
                $base = ($slug !== '' && $slug !== '_')
                    ? '/t/' . rawurlencode($slug) . '/apps/dms'
                    : '/apps/dms';
            }
            $base = rtrim($base, '/');

            // ---------------------------------------------------------
            // 2) Read POST payload
            // ---------------------------------------------------------
            $invoiceId = (int)($_POST['invoice_id'] ?? 0);
            if ($invoiceId <= 0) {
                $this->redirect($base . '/challan');
                return;
            }

            $linesPost = $_POST['lines'] ?? [];
            if (!is_array($linesPost) || !$linesPost) {
                $this->redirect($base . '/challan/prepare?invoice_id=' . $invoiceId);
                return;
            }

            $shipToName = trim((string)($_POST['ship_to_name'] ?? ''));
            $shipToAddr = trim((string)($_POST['ship_to_addr'] ?? ''));
            $vehicleNo  = trim((string)($_POST['vehicle_no'] ?? ''));
            $driverName = trim((string)($_POST['driver_name'] ?? ''));
            $remarks    = trim((string)($_POST['remarks'] ?? ''));

            $paymentReceived = (float)($_POST['payment_received'] ?? 0);

            // ---------------------------------------------------------
            // 3) Load invoice header
            // ---------------------------------------------------------
            $st = $pdo->prepare("SELECT * FROM dms_sales WHERE org_id = ? AND id = ? LIMIT 1");
            $st->execute([$orgId, $invoiceId]);
            $sale = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$sale) {
                throw new \RuntimeException('Invoice not found for challan.');
            }

            $customerId   = (int)($sale['customer_id'] ?? 0) ?: null;
            $customerName = (string)($sale['customer_name'] ?? '');

            if ($shipToName === '') {
                $shipToName = $customerName !== '' ? $customerName : 'Customer';
            }

            // ---------------------------------------------------------
            // 4) Normalize selected lines
            // ---------------------------------------------------------
            $items       = [];
            $amountTotal = 0.0;

            foreach ($linesPost as $saleItemId => $ln) {
                $saleItemId = (int)$saleItemId;
                if ($saleItemId <= 0) continue;

                $qty = (float)($ln['qty'] ?? 0);
                $up  = (float)($ln['unit_price'] ?? 0);
                $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : null;
                $nm  = trim((string)($ln['product_name'] ?? ''));

                if ($qty <= 0) continue;
                if ($nm === '') $nm = 'Item #' . $saleItemId;

                $lineAmt      = $qty * $up;
                $amountTotal += $lineAmt;

                $items[] = [
                    'sale_item_id' => $saleItemId,
                    'product_id'   => $pid,
                    'product_name' => $nm,
                    'qty'          => $qty,
                    'unit_price'   => $up,
                ];
            }

            if (!$items) {
                $this->redirect($base . '/challan/prepare?invoice_id=' . $invoiceId);
            }

            // ---------------------------------------------------------
            // 5) Generate challan_no via dms_counters
            // ---------------------------------------------------------
            $challanNo = null;
            $year      = (int)date('Y');

            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS dms_counters (
                        org_id INT UNSIGNED NOT NULL,
                        name   VARCHAR(50) NOT NULL,
                        y      INT NOT NULL,
                        seq    INT NOT NULL DEFAULT 0,
                        PRIMARY KEY (org_id, name, y)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $cst = $pdo->prepare("
                    INSERT INTO dms_counters (org_id, name, y, seq)
                    VALUES (?, 'challan', ?, 0)
                    ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)
                ");
                $cst->execute([$orgId, $year]);
                $seq = (int)$pdo->lastInsertId();

                if ($seq > 0) {
                    $challanNo = sprintf('CH-%d-%05d', $year, $seq);
                }
            } catch (\Throwable $e) {
                // ignore, fallback below
            }

            if (!$challanNo) {
                $challanNo = sprintf('CH-%d-%s', $year, strtoupper(bin2hex(random_bytes(3))));
            }

            $today = date('Y-m-d');

            // ---------------------------------------------------------
            // 6) Ensure challan tables exist (PLURAL ONLY)
            // ---------------------------------------------------------
            $pdo->exec("CREATE TABLE IF NOT EXISTS dms_challans(
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL,
                sale_id BIGINT UNSIGNED NOT NULL,
                challan_no VARCHAR(64) NOT NULL,
                challan_date DATE NULL,
                customer_id BIGINT UNSIGNED NULL,
                customer_name VARCHAR(190) NULL,
                status ENUM('draft','ready','dispatched','delivered','cancelled') NOT NULL DEFAULT 'ready',
                vehicle_no VARCHAR(64) NULL,
                driver_name VARCHAR(128) NULL,
                dispatch_at DATETIME NULL,
                notes TEXT NULL,
                ship_to_name VARCHAR(190) NULL,
                ship_to_addr TEXT NULL,
                created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                UNIQUE KEY uq_org_no (org_id, challan_no),
                KEY idx_org_sale (org_id, sale_id),
                KEY idx_org_status (org_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS dms_challans_items(
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                org_id BIGINT UNSIGNED NOT NULL,
                challan_id BIGINT UNSIGNED NOT NULL,
                sale_item_id BIGINT UNSIGNED NULL,
                product_id BIGINT UNSIGNED NULL,
                product_name VARCHAR(255) NULL,
                qty DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
                unit_price DECIMAL(16,4) NULL,
                created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY idx_head (org_id, challan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // ---------------------------------------------------------
            // 7) Insert challan header + lines
            // ---------------------------------------------------------
            $pdo->beginTransaction();

            $insH = $pdo->prepare("
                INSERT INTO dms_challans (
                    org_id,
                    sale_id,
                    challan_no,
                    challan_date,
                    customer_id,
                    customer_name,
                    status,
                    vehicle_no,
                    driver_name,
                    dispatch_at,
                    notes,
                    ship_to_name,
                    ship_to_addr,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW()
                )
            ");

            $insH->execute([
                $orgId,
                $invoiceId,
                $challanNo,
                $today,
                $customerId,
                $customerName,
                'ready',
                $vehicleNo !== '' ? $vehicleNo : null,
                $driverName !== '' ? $driverName : null,
                $remarks !== '' ? $remarks : null,
                $shipToName,
                $shipToAddr !== '' ? $shipToAddr : null,
            ]);

            $challanId = (int)$pdo->lastInsertId();

            $insI = $pdo->prepare("
                INSERT INTO dms_challans_items (
                    org_id,
                    challan_id,
                    sale_item_id,
                    product_id,
                    product_name,
                    qty,
                    unit_price,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            foreach ($items as $ln) {
                $insI->execute([
                    $orgId,
                    $challanId,
                    $ln['sale_item_id'],
                    $ln['product_id'],
                    $ln['product_name'],
                    $ln['qty'],
                    $ln['unit_price'],
                ]);
            }

            $pdo->commit();

            // ---------------------------------------------------------
            // 8) Optional: challan payment (PLURAL TABLE)
            // ---------------------------------------------------------
            if ($paymentReceived > 0.00001) {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS dms_challans_payments(
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        org_id BIGINT UNSIGNED NOT NULL,
                        challan_id BIGINT UNSIGNED NOT NULL,
                        invoice_id BIGINT UNSIGNED NULL,
                        amount DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
                        method VARCHAR(64) NULL,
                        reference VARCHAR(190) NULL,
                        paid_at DATETIME NULL,
                        created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY(id),
                        KEY idx_org_challan (org_id, challan_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                    $pmt = $pdo->prepare("
                        INSERT INTO dms_challans_payments (
                            org_id,
                            challan_id,
                            invoice_id,
                            amount,
                            method,
                            reference,
                            paid_at,
                            created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, NOW(), NOW()
                        )
                    ");

                    $pmt->execute([
                        $orgId,
                        $challanId,
                        $invoiceId,
                        $paymentReceived,
                        null,
                        null,
                    ]);
                } catch (\Throwable $e) {
                    // ignore payment failure, challan is already created
                }
            }

            // ---------------------------------------------------------
            // 9) Redirect to challan show
            // ---------------------------------------------------------
            $this->redirect($base . '/challan/' . $challanId);

        } catch (\Throwable $e) {
            if (method_exists($this, 'abort500')) {
                $this->abort500('Challan store failed: ' . $e->getMessage());
            } else {
                http_response_code(500);
                echo 'Challan store failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }

  
         /* ============================================================
     * Edit delivery (GET)  /challan/{id}/edit
     * ========================================================== */
    public function edit(array $ctx, int $id): void
    {
        $id = (int)$id;

        try {
            $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            // ---- load challan header ----
            $st = $pdo->prepare("
                SELECT *
                  FROM dms_challans
                 WHERE org_id = ?
                   AND id     = ?
                 LIMIT 1
            ");
            $st->execute([$orgId, $id]);
            $challan = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$challan) {
                $this->abort404('Challan not found.');
                return;
            }

            // ---- load challan items + product info ----
            $sql = "
                SELECT
                    ci.*,
                    COALESCE(ci.product_name, p.name) AS product_name,
                    p.code AS product_code
                  FROM dms_challans_items ci
             LEFT JOIN dms_products p
                    ON p.id     = ci.product_id
                   AND p.org_id = ci.org_id
                 WHERE ci.org_id     = ?
                   AND ci.challan_id = ?
                 ORDER BY ci.id
            ";
            $qi = $pdo->prepare($sql);
            $qi->execute([$orgId, $id]);
            $items = $qi->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // normalise qty fields for the view
            foreach ($items as &$ln) {
                if (!isset($ln['qty_ordered']) && isset($ln['qty'])) {
                    $ln['qty_ordered'] = (float)$ln['qty'];
                }
                if (!isset($ln['qty_delivered'])) {
                    $ln['qty_delivered'] = (float)($ln['qty_delivered'] ?? 0);
                }
                if (!isset($ln['qty_returned'])) {
                    $ln['qty_returned'] = (float)($ln['qty_returned'] ?? 0);
                }
            }
            unset($ln);

            $org = $ctx['org'] ?? ($_SESSION['tenant_org'] ?? []);
            if (!is_array($org)) {
                $org = [];
            }

            $csrf = method_exists($this, 'csrfTokenTenant')
                ? $this->csrfTokenTenant()
                : '';

            $this->view('challan/edit_delivery', [
                'title'       => 'Update Delivery — ' . (string)($challan['challan_no'] ?? ('#'.$id)),
                'challan'     => $challan,
                'items'       => $items,
                'org'         => $org,
                'module_base' => $ctx['module_base'] ?? null,
                'csrf'        => $csrf,
            ], $ctx);

        } catch (Throwable $e) {
            $this->abort500('Challan edit failed: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * Update delivery (POST) /challan/{id}/update-delivery
     * ========================================================== */
    public function updateDelivery(array $ctx, int $id): void
    {
        $id      = (int)$id;
        $isDebug = isset($_GET['_debug']);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        try {
            $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            // CSRF
            if (method_exists($this, 'csrfVerifyPostTenant')) {
                if (!$this->csrfVerifyPostTenant()) {
                    http_response_code(419);
                    echo 'CSRF token mismatch.';
                    return;
                }
            }

            // POST payload
            $itemsPost = $_POST['items'] ?? [];
            if (!is_array($itemsPost)) {
                $itemsPost = [];
            }

            if (!$itemsPost) {
                if (\PHP_SESSION_ACTIVE !== \session_status()) {
                    @\session_start();
                }
                $_SESSION['flash_error'] = 'Nothing to update.';
                $base = $this->moduleBase($ctx);
                $this->redirect(rtrim($base, '/') . '/challan/' . $id);
                return;
            }

            // figure out which columns actually exist to avoid unknown column errors
            $hasDelivered = $this->hasColumn($pdo, 'dms_challans_items', 'qty_delivered');
            $hasReturned  = $this->hasColumn($pdo, 'dms_challans_items', 'qty_returned');
            $hasReason    = $this->hasColumn($pdo, 'dms_challans_items', 'return_reason');

            $setParts = [];
            if ($hasDelivered) $setParts[] = 'qty_delivered = :delivered';
            if ($hasReturned)  $setParts[] = 'qty_returned  = :returned';
            if ($hasReason)    $setParts[] = 'return_reason = :reason';

            if (!$setParts) {
                // nothing to persist – schema doesn’t support delivery tracking
                if (\PHP_SESSION_ACTIVE !== \session_status()) {
                    @\session_start();
                }
                $_SESSION['flash_error'] = 'Delivery tracking columns missing on dms_challans_items.';
                $base = $this->moduleBase($ctx);
                $this->redirect(rtrim($base, '/') . '/challan/' . $id);
                return;
            }

            $sql = "
                UPDATE dms_challans_items
                   SET " . implode(', ', $setParts) . "
                 WHERE org_id     = :org_id
                   AND challan_id = :challan_id
                   AND id         = :id
            ";
            $upd = $pdo->prepare($sql);

            $pdo->beginTransaction();

            foreach ($itemsPost as $rowId => $payload) {
                $rowId = (int)$rowId;
                if ($rowId <= 0) {
                    continue;
                }

                $delivered = (float)($payload['delivered'] ?? 0);
                $returned  = (float)($payload['returned']  ?? 0);
                $reason    = trim((string)($payload['reason'] ?? ''));

                $params = [
                    ':org_id'     => $orgId,
                    ':challan_id' => $id,
                    ':id'         => $rowId,
                ];
                if ($hasDelivered) $params[':delivered'] = $delivered;
                if ($hasReturned)  $params[':returned']  = $returned;
                if ($hasReason)    $params[':reason']    = ($reason !== '' ? $reason : null);

                $upd->execute($params);
            }

            // touch header updated_at
            $ht = $pdo->prepare("
                UPDATE dms_challans
                   SET updated_at = NOW()
                 WHERE org_id = ? AND id = ?
            ");
            $ht->execute([$orgId, $id]);

            $pdo->commit();

            if ($isDebug) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "updateDelivery(): OK for challan {$id}\n";
                return;
            }

            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $_SESSION['flash_success'] = 'Delivery quantities updated successfully.';

            $base = $this->moduleBase($ctx);
            $this->redirect(rtrim($base, '/') . '/challan/' . $id);
            return;

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($isDebug) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "updateDelivery() exception: " . $e->getMessage() . "\n";
                echo $e->getFile() . ':' . $e->getLine() . "\n";
                return;
            }

            $this->abort500('Delivery update failed: ' . $e->getMessage());
        }
    }
  
  
    /* ============================================================
     * Show single challan
     * ========================================================== */

    public function show(array $ctx, int $id): void
    {
        $id = (int)$id;

        try {
            $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            $debug = isset($_GET['__debug']);
            $log   = [];

            if ($id <= 0) {
                if ($debug) {
                    header('Content-Type: text/plain; charset=utf-8');
                    echo "Invalid challan id: {$id}\n";
                    return;
                }
                $this->abort404('Invalid challan id.');
                return;
            }

            // 1) Load challan header (PLURAL ONLY)
            $st = $pdo->prepare("
                SELECT *
                  FROM dms_challans
                 WHERE org_id = ?
                   AND id = ?
                 LIMIT 1
            ");
            $st->execute([$orgId, $id]);
            $challan = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($debug) {
                $log[] = "SELECT * FROM dms_challans WHERE org_id={$orgId} AND id={$id}";
            }

            if (!$challan) {
                if ($debug) {
                    header('Content-Type: text/plain; charset=utf-8');
                    echo "Challan not found for id = {$id}\n";
                    echo "Log:\n";
                    foreach ($log as $line) echo " - {$line}\n";
                    return;
                }
                $this->abort404('Challan not found.');
                return;
            }

            // 2) Load items (PLURAL ONLY)
            $qi = $pdo->prepare("
                SELECT *
                  FROM dms_challans_items
                 WHERE org_id = ?
                   AND challan_id = ?
                 ORDER BY id
            ");
            $qi->execute([$orgId, $id]);
            $items = $qi->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($debug) {
                $log[] = "Items: ".count($items)." rows from dms_challans_items";

                header('Content-Type: text/plain; charset=utf-8');
                echo "Challan found in table: dms_challans\n";
                echo "ID: {$id}\n";
                echo "Status: " . ($challan['status'] ?? '') . "\n";
                echo "Items count: " . count($items) . "\n\n";
                echo "Log:\n";
                foreach ($log as $line) {
                    echo " - {$line}\n";
                }
                return;
            }

            if (empty($ctx['module_base'])) {
                $ctx['module_base'] = $this->moduleBase($ctx);
            }

            $title = 'Delivery Challan ' . (string)($challan['challan_no'] ?? ('#'.$id));

            $this->view('challan/show', [
                'title'   => $title,
                'challan' => $challan,
                'items'   => $items,
            ], $ctx);

        } catch (\Throwable $e) {
            $this->abort500('Challan show failed: '.$e->getMessage());
        }
    }
  
  //export Section
  
  public function exportCsv(array $ctx): void
{
    $this->ensureBase($ctx);

    try {
        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx, $pdo);

        // Filters from query string (same as index)
        $q      = trim((string)($_GET['q']      ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $where  = 'c.org_id = ?';
        $params = [$orgId];

        if ($status !== '') {
            if ($status === 'waiting') {
                // "waiting" = anything not dispatched/cancelled
                $where .= " AND c.status NOT IN ('dispatched','cancelled')";
            } else {
                $where   .= " AND c.status = ?";
                $params[] = $status;
            }
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where   .= " AND (c.challan_no LIKE ? OR c.invoice_no LIKE ? OR c.customer_name LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "
            SELECT c.*,
                   COALESCE(x.total_items, 0) AS total_items,
                   COALESCE(x.total_qty,   0) AS total_qty
              FROM dms_challans AS c
         LEFT JOIN (
                    SELECT org_id,
                           challan_id,
                           COUNT(*)              AS total_items,
                           SUM(COALESCE(qty, 0)) AS total_qty
                      FROM dms_challans_items
                     GROUP BY org_id, challan_id
              ) AS x
                ON x.org_id     = c.org_id
               AND x.challan_id = c.id
             WHERE {$where}
             ORDER BY c.id DESC
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ---------- CSV response ----------
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header(
                'Content-Disposition: attachment; filename="challans-' .
                date('Ymd-His') .
                '.csv"'
            );
        }

        $out = fopen('php://output', 'w');

        // header row
        fputcsv($out, [
            'ID',
            'Challan No',
            'Date',
            'Invoice No',
            'Customer',
            'Status',
            'Items',
            'Qty',
            'Vehicle',
            'Driver',
            'Dispatch At',
            'Notes',
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id']            ?? '',
                $r['challan_no']    ?? '',
                $r['challan_date']  ?? '',
                $r['invoice_no']    ?? '',
                $r['customer_name'] ?? '',
                $r['status']        ?? '',
                $r['total_items']   ?? 0,
                $r['total_qty']     ?? 0,
                $r['vehicle_no']    ?? '',
                $r['driver_name']   ?? '',
                $r['dispatch_at']   ?? '',
                $r['notes']         ?? '',
            ]);
        }

        fclose($out);
        return;

    } catch (\Throwable $e) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo 'Challan export failed: ' . $e->getMessage();
    }
}

  //Dispatch Section
  
  public function markDispatched(array $ctx): void
{
    $this->ensureBase($ctx);
    $isDebug = isset($_GET['_debug']);

    try {
        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx, $pdo);

        // Resolve module_base for redirect
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $slug = (string)($ctx['slug']
            ?? ($_SESSION['tenant_org']['slug'] ?? '')
        );
        $moduleBase = rtrim((string)($ctx['module_base'] ?? ''), '/');
        if ($moduleBase === '') {
            $moduleBase = $slug !== ''
                ? '/t/' . rawurlencode($slug) . '/apps/dms'
                : '/apps/dms';
        }

        // Read ids from POST ("1,2,3")
        $rawIds = (string)($_POST['ids'] ?? '');
        $ids    = [];

        foreach (preg_split('/[,\s]+/', $rawIds) as $token) {
            $token = trim($token);
            if ($token !== '' && ctype_digit($token)) {
                $ids[] = (int)$token;
            }
        }
        $ids = array_values(array_unique($ids));

        if (!$ids) {
            if ($isDebug) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "markDispatched(): no challan ids provided.\n";
                return;
            }
            $_SESSION['flash_error'] = 'Please select at least one challan.';
            header('Location: ' . $moduleBase . '/challan', true, 302);
            return;
        }

        // Build UPDATE ... WHERE org_id = ? AND id IN (?,?,?)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params       = array_merge([$orgId], $ids);

        $sql = "
            UPDATE dms_challans
               SET status      = 'dispatched',
                   dispatch_at = COALESCE(dispatch_at, NOW()),
                   updated_at  = NOW()
             WHERE org_id = ?
               AND id IN ($placeholders)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $affected = $stmt->rowCount();

        if ($isDebug) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "markDispatched(): updated {$affected} row(s) for ids = "
                 . implode(',', $ids) . "\n";
            return;
        }

        $_SESSION['flash_success'] =
            $affected . ' challan(s) marked as dispatched.';
        header('Location: ' . $moduleBase . '/challan', true, 302);
        return;

    } catch (\Throwable $e) {
        if ($isDebug) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "markDispatched() exception: " . $e->getMessage() . "\n";
            echo $e->getFile() . ':' . $e->getLine() . "\n";
            return;
        }
        $this->abort500('Mark dispatched failed: ' . $e->getMessage());
    }
}
  
  //Print method section
  
public function print(array $ctx, int $id): void
{
    $this->ensureBase($ctx);

    try {
        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx, $pdo);

        // ---- load challan header (with sale + customer info) ----------
        $sql = "
            SELECT
                c.*,
                s.customer_id,
                s.customer_name
              FROM dms_challans c
         LEFT JOIN dms_sales s
                ON s.id     = c.sale_id
               AND s.org_id = c.org_id
             WHERE c.org_id = ?
               AND c.id     = ?
             LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$orgId, $id]);
        $challan = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$challan) {
            $this->abort404('Challan not found.');
            return;
        }

        // safe invoice_no fallback so view can rely on it
        if (!array_key_exists('invoice_no', $challan)) {
            $challan['invoice_no'] = !empty($challan['sale_id'])
                ? ('SALE-' . $challan['sale_id'])
                : null;
        }

        // ---- load challan line items -----------------
        $sql = "
            SELECT
                ci.*,
                COALESCE(ci.product_name, p.name) AS product_name,
                p.code AS product_code
              FROM dms_challans_items ci
         LEFT JOIN dms_products p
                ON p.id     = ci.product_id
               AND p.org_id = ci.org_id
             WHERE ci.org_id     = ?
               AND ci.challan_id = ?
             ORDER BY ci.id
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$orgId, $id]);
        $lines = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        foreach ($lines as &$ln) {
            if (!isset($ln['qty_ordered']) && isset($ln['qty'])) {
                $ln['qty_ordered'] = (float)$ln['qty'];
            }
            if (!isset($ln['unit_price'])) {
                $ln['unit_price'] = (float)($ln['price'] ?? 0);
            }
        }
        unset($ln);

        $org = $ctx['org'] ?? ($_SESSION['tenant_org'] ?? []);
        if (!is_array($org)) $org = [];

        // IMPORTANT: this must match the file name DMS/Views/challan/print.php
        $this->view('challan/print', [
            'org'     => $org,
            'challan' => $challan,
            'lines'   => $lines,
            'title'   => 'Dispatch Challan #'.($challan['challan_no'] ?? $id),
        ], []); // full HTML, no shell
    } catch (\Throwable $e) {
        $this->abort500('Challan print failed: '.$e->getMessage());
    }
}
  
// ======================================================================
// PDF stub
//   GET /challan/{id}/pdf
//   For now just re-use print view; later you can plug real PDF engine.
// ======================================================================
public function pdf(array $ctx, int $id): void
{
    // Very simple: for now, just call print()
    // Later you can render to a PDF library instead.
    $this->print($ctx, $id);
}
  
/* ============================================================
 * Master challan from selected challans (list → master view)
 *  - GET  /challan/master-from-challan?ids=1,2,3
 *  - POST /challan/master-from-challan  (ids="1,2,3")
 *  Uses:
 *    - dms_challans
 *    - dms_challans_items   (plural)
 * ========================================================== */
public function masterFromChallan(array $ctx): void
{
    $isDebug = isset($_GET['_debug']);

    try {
        // 1) Normalise base + org
        $this->ensureBase($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx, $pdo);

        $moduleBase = rtrim((string)($ctx['module_base'] ?? ''), '/');
        if ($moduleBase === '') {
            $moduleBase = '/apps/dms';
        }

        /* -----------------------------------------------------
         * 2) Collect challan IDs
         * --------------------------------------------------- */
        $tokens = [];

        // POST ids="1,2,3"
        if (isset($_POST['ids']) && !is_array($_POST['ids'])) {
            $raw    = (string)$_POST['ids'];
            $tokens = preg_split('/[,\s]+/', $raw) ?: [];
        }

        // POST ids[]=1&ids[]=2
        if (empty($tokens) && isset($_POST['ids']) && is_array($_POST['ids'])) {
            $tokens = $_POST['ids'];
        }

        // GET ?ids=1,2,3
        if (empty($tokens) && isset($_GET['ids'])) {
            $raw    = (string)$_GET['ids'];
            $tokens = preg_split('/[,\s]+/', $raw) ?: [];
        }

        // Normalise to int IDs
        $ids = [];
        foreach ($tokens as $t) {
            $t = trim((string)$t);
            if ($t === '' || !ctype_digit($t)) {
                continue;
            }
            $ids[] = (int)$t;
        }
        $ids = array_values(array_unique($ids));

        if (!$ids) {
            if ($isDebug) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "masterFromChallan(): no ids provided\n";
                echo "POST: " . print_r($_POST, true) . "\n";
                echo "GET : " . print_r($_GET, true) . "\n";
                return;
            }

            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $_SESSION['flash_error'] = 'Please select at least one challan.';
            header('Location: '.$moduleBase.'/challan', true, 302);
            return;
        }

        /* -----------------------------------------------------
         * 3) Load challan headers from dms_challans
         * --------------------------------------------------- */
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params       = array_merge([$orgId], $ids);

        $sql = "
            SELECT *
              FROM dms_challans
             WHERE org_id = ?
               AND id IN ($placeholders)
             ORDER BY challan_date, id
        ";

        $stmt     = $pdo->prepare($sql);
        $ok       = $stmt->execute($params);
        $challans = $ok ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        if (!$challans) {
            if ($isDebug) {
                header('Content-Type: text/plain; charset=utf-8');
                echo "masterFromChallan(): no rows in dms_challans for ids = ".implode(',', $ids)."\n";
                return;
            }

            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $_SESSION['flash_error'] = 'Selected challans not found for this organisation.';
            header('Location: '.$moduleBase.'/challan', true, 302);
            return;
        }

        /* -----------------------------------------------------
         * 3b) Load ALL items from dms_challans_items (plural)
         *      and group them by challan_id
         * --------------------------------------------------- */
        $headerIds = array_column($challans, 'id');
        $itemMap   = [];

        if ($headerIds) {
            $phItems  = implode(',', array_fill(0, count($headerIds), '?'));
            $params2  = array_merge([$orgId], $headerIds);

            $sqlItems = "
                SELECT
                    ci.*,
                    p.name AS _product_name,
                    p.code AS _product_code
                  FROM dms_challans_items ci
             LEFT JOIN dms_products p
                    ON p.id     = ci.product_id
                   AND p.org_id = ci.org_id
                 WHERE ci.org_id     = ?
                   AND ci.challan_id IN ($phItems)
                 ORDER BY ci.challan_id, ci.id
            ";

            $st2   = $pdo->prepare($sqlItems);
            $ok2   = $st2->execute($params2);
            $rows2 = $ok2 ? $st2->fetchAll(PDO::FETCH_ASSOC) : [];

            foreach ($rows2 as $row) {
                // normalise product label/code without assuming columns exist
                if (!isset($row['product_name']) && isset($row['_product_name'])) {
                    $row['product_name'] = $row['_product_name'];
                }
                if (!isset($row['product_code']) && isset($row['_product_code'])) {
                    $row['product_code'] = $row['_product_code'];
                }
                unset($row['_product_name'], $row['_product_code']);

                $cid = (int)($row['challan_id'] ?? 0);
                if (!$cid) {
                    continue;
                }
                $itemMap[$cid][] = $row;
            }
        }

        /* -----------------------------------------------------
         * 3c) Reshape into [ 'header' => ..., 'items' => [...] ]
         *     so the view can show item-wise qty
         * --------------------------------------------------- */
        $challansWithItems = [];
        foreach ($challans as $c) {
            $cid = (int)($c['id'] ?? 0);
            $challansWithItems[] = [
                'header' => $c,
                'items'  => $itemMap[$cid] ?? [],
            ];
        }

        /* -----------------------------------------------------
         * 4) Render master challan view
         *    View path: modules/DMS/Views/challan/master_from_challan.php
         * --------------------------------------------------- */
        $this->view('challan/master_from_challan', [
            'title'       => 'Master Dispatch Challan',
            'org'         => $ctx['org'] ?? ($_SESSION['tenant_org'] ?? []),
            'module_base' => $moduleBase,
            'challans'    => $challansWithItems,  // <<< now header+items
            'ids'         => $ids,
            'active'      => 'challan',
            'subactive'   => 'challan.master',
        ], $ctx);

    } catch (\Throwable $e) {
        if ($isDebug) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "masterFromChallan() exception: ".$e->getMessage()."\n";
            echo $e->getFile().':'.$e->getLine()."\n";
            return;
        }

        $this->abort500('Master challan creation failed: '.$e->getMessage());
    }
}
  
 // ======================================================================
// POST /challan/master-from-challan/store
//   - stores a row in dms_master_challans
//   - expects: ids, mode, deliveryPerson, vehicleNo, routeText,
//              dispatchAt, notes, subtotal, return_amount, net_total,
//              totalReceived
//   - redirects to /challan/master/{id} with flash message
// ======================================================================
public function storeMasterChallan(array $ctx): void
{
    $isDebug = isset($_GET['_debug']);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        return;
    }

    try {
        $this->ensureBase($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx, $pdo);

        // CSRF (if helper exists)
        if (method_exists($this, 'csrfVerifyPostTenant')) {
            if (!$this->csrfVerifyPostTenant()) {
                http_response_code(419);
                echo 'CSRF token mismatch.';
                return;
            }
        }

        /* ---------------------------------------------------------
         * 1) Collect challan IDs from POST (same pattern as preview)
         * ------------------------------------------------------- */
        $tokens = [];

        if (isset($_POST['ids']) && !is_array($_POST['ids'])) {
            $raw    = (string)$_POST['ids'];
            $tokens = preg_split('/[,\s]+/', $raw) ?: [];
        }
        if (empty($tokens) && isset($_POST['ids']) && is_array($_POST['ids'])) {
            $tokens = $_POST['ids'];
        }

        $ids = [];
        foreach ($tokens as $t) {
            $t = trim((string)$t);
            if ($t === '' || !ctype_digit($t)) {
                continue;
            }
            $ids[] = (int)$t;
        }
        $ids = array_values(array_unique($ids));

        if (!$ids) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $_SESSION['flash_error'] = 'No challans selected for master challan.';
            $base = rtrim((string)($ctx['module_base'] ?? '/apps/dms'), '/');
            header('Location: '.$base.'/challan', true, 302);
            return;
        }

        /* ---------------------------------------------------------
         * 2) Load challan HEADERS (only real columns)
         * ------------------------------------------------------- */
        $ph     = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$orgId], $ids);

        $sql = "
            SELECT id,
                   challan_no,
                   challan_date,
                   customer_id,
                   customer_name
              FROM dms_challans
             WHERE org_id = ?
               AND id IN ($ph)
             ORDER BY challan_date, id
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        if (!$rows) {
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $_SESSION['flash_error'] = 'Selected challans not found for this organisation.';
            $base = rtrim((string)($ctx['module_base'] ?? '/apps/dms'), '/');
            header('Location: '.$base.'/challan', true, 302);
            return;
        }

        /* ---------------------------------------------------------
         * 3) Aggregate quantities from dms_challans_items
         *    (we only use ci.qty – NO line_total assumptions)
         * ------------------------------------------------------- */
        $aggByChallan = []; // challan_id => ['line_count'=>.., 'total_qty'=>..]

        $phItems = implode(',', array_fill(0, count($ids), '?'));
        $params2 = array_merge([$orgId], $ids);

        $sqlItems = "
            SELECT ci.challan_id,
                   COUNT(*)                AS line_count,
                   COALESCE(SUM(ci.qty),0) AS total_qty
              FROM dms_challans_items ci
             WHERE ci.org_id     = ?
               AND ci.challan_id IN ($phItems)
             GROUP BY ci.challan_id
        ";

        $si = $pdo->prepare($sqlItems);
        $si->execute($params2);
        while ($r = $si->fetch(\PDO::FETCH_ASSOC)) {
            $cid = (int)$r['challan_id'];
            $aggByChallan[$cid] = [
                'line_count' => (int)($r['line_count'] ?? 0),
                'total_qty'  => (float)($r['total_qty'] ?? 0),
            ];
        }

        /* ---------------------------------------------------------
         * 4) Delivery + basic totals from POST
         * ------------------------------------------------------- */
        $mode            = (string)($_POST['mode'] ?? 'draft');   // 'draft' | 'final'
        $deliveryPerson  = trim((string)($_POST['delivery_person'] ?? ''));
        $deliveryContact = trim((string)($_POST['delivery_contact'] ?? ''));
        $vehicleNo       = trim((string)($_POST['vehicle_no'] ?? ''));
        $routeText       = trim((string)($_POST['route_text'] ?? ''));
        $dispatchAtRaw   = trim((string)($_POST['dispatch_at'] ?? ''));
        $notes           = trim((string)($_POST['notes'] ?? ''));
        $totalReceived   = (float)($_POST['total_received'] ?? 0);

        $dispatchAt = null;
        if ($dispatchAtRaw !== '') {
            $ts = \strtotime($dispatchAtRaw);
            if ($ts !== false) {
                $dispatchAt = \date('Y-m-d H:i:s', $ts);
            }
        }

        // Aggregates
        $totalChallans = count($rows);
        $totalLines    = 0;
        $totalQty      = 0.0;

        foreach ($rows as $r) {
            $cid = (int)$r['id'];
            if (isset($aggByChallan[$cid])) {
                $totalLines += $aggByChallan[$cid]['line_count'];
                $totalQty   += $aggByChallan[$cid]['total_qty'];
            }
        }

        // Money fields – keep 0.00 for now (safe with your schema)
        $subtotal      = 0.00;
        $returnAmount  = 0.00;
        $netTotal      = 0.00;

        $masterDate = \date('Y-m-d');
        $status     = ($mode === 'final') ? 'locked' : 'draft';

        $createdBy = null;
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        if (!empty($_SESSION['tenant_user']['id'])) {
            $createdBy = (int)$_SESSION['tenant_user']['id'];
        }

        /* ---------------------------------------------------------
         * 5) Next master_no for this org (MCH-YYYY-00001)
         * ------------------------------------------------------- */
        $year   = \date('Y');
        $prefix = "MCH-$year-";

        $st = $pdo->prepare("
            SELECT master_no
              FROM dms_master_challans
             WHERE org_id = ?
               AND master_no LIKE ?
             ORDER BY master_no DESC
             LIMIT 1
        ");
        $st->execute([$orgId, $prefix.'%']);
        $last = $st->fetch(\PDO::FETCH_ASSOC);

        $nextSeq = 1;
        if ($last && !empty($last['master_no'])) {
            $parts = explode('-', $last['master_no']);
            $seq   = (int)end($parts);
            if ($seq > 0) {
                $nextSeq = $seq + 1;
            }
        }
        $masterNo = $prefix . str_pad((string)$nextSeq, 5, '0', STR_PAD_LEFT);

        /* ---------------------------------------------------------
         * 6) Insert into dms_master_challans + dms_master_challans_items
         * ------------------------------------------------------- */
        $pdo->beginTransaction();

        // header
        $insMaster = $pdo->prepare("
            INSERT INTO dms_master_challans (
                org_id,
                master_no,
                master_date,
                status,
                total_challans,
                total_lines,
                total_qty,
                subtotal,
                return_amount,
                net_total,
                total_received,
                delivery_person,
                delivery_contact,
                vehicle_no,
                route_text,
                dispatch_at,
                notes,
                created_by
            ) VALUES (
                :org_id,
                :master_no,
                :master_date,
                :status,
                :total_challans,
                :total_lines,
                :total_qty,
                :subtotal,
                :return_amount,
                :net_total,
                :total_received,
                :delivery_person,
                :delivery_contact,
                :vehicle_no,
                :route_text,
                :dispatch_at,
                :notes,
                :created_by
            )
        ");

        $insMaster->execute([
            ':org_id'          => $orgId,
            ':master_no'       => $masterNo,
            ':master_date'     => $masterDate,
            ':status'          => $status,
            ':total_challans'  => $totalChallans,
            ':total_lines'     => $totalLines,
            ':total_qty'       => $totalQty,
            ':subtotal'        => $subtotal,
            ':return_amount'   => $returnAmount,
            ':net_total'       => $netTotal,
            ':total_received'  => $totalReceived,
            ':delivery_person' => $deliveryPerson !== '' ? $deliveryPerson : null,
            ':delivery_contact'=> $deliveryContact !== '' ? $deliveryContact : null,
            ':vehicle_no'      => $vehicleNo !== '' ? $vehicleNo : null,
            ':route_text'      => $routeText !== '' ? $routeText : null,
            ':dispatch_at'     => $dispatchAt,
            ':notes'           => $notes !== '' ? $notes : null,
            ':created_by'      => $createdBy,
        ]);

        $masterId = (int)$pdo->lastInsertId();

        // items: one row per challan
        $insItem = $pdo->prepare("
            INSERT INTO dms_master_challans_items (
                org_id,
                master_id,
                challan_id,
                challan_no,
                challan_date,
                customer_id,
                customer_name,
                total_qty,
                line_total,
                sort_order
            ) VALUES (
                :org_id,
                :master_id,
                :challan_id,
                :challan_no,
                :challan_date,
                :customer_id,
                :customer_name,
                :total_qty,
                :line_total,
                :sort_order
            )
        ");

        $sort = 1;
        foreach ($rows as $r) {
            $cid = (int)$r['id'];
            $agg = $aggByChallan[$cid] ?? ['total_qty' => 0.0];

            $insItem->execute([
                ':org_id'       => $orgId,
                ':master_id'    => $masterId,
                ':challan_id'   => $cid,
                ':challan_no'   => (string)($r['challan_no'] ?? ''),
                ':challan_date' => $r['challan_date'] ?? null,
                ':customer_id'  => !empty($r['customer_id']) ? (int)$r['customer_id'] : null,
                ':customer_name'=> (string)($r['customer_name'] ?? ''),
                ':total_qty'    => (float)$agg['total_qty'],
                ':line_total'   => 0.00,  // safe default – no ci.line_total assumption
                ':sort_order'   => $sort++,
            ]);
        }

        $pdo->commit();

        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['flash_success'] = 'Master challan '.$masterNo.' saved successfully.';

        $base = rtrim((string)($ctx['module_base'] ?? '/apps/dms'), '/');
        header('Location: '.$base.'/challan', true, 302);
        return;

    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($isDebug) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "storeMasterChallan() exception: ".$e->getMessage()."\n";
            echo $e->getFile().':'.$e->getLine()."\n";
            return;
        }

        $this->abort500('Master challan save failed: '.$e->getMessage());
    }
}

// ===============================================================
// SHOW stored master challan (read-only, reuse master_from_challan view)
//  GET /challan/master/{id}
// ===============================================================
public function masterShow(array $ctx, int $id): void
{
    $this->ensureBase($ctx);
    $pdo   = $this->pdo();
    $orgId = $this->resolveOrgId($ctx, $pdo);

    // Header
    $st = $pdo->prepare("
        SELECT *
          FROM dms_master_challans
         WHERE org_id = ?
           AND id     = ?
         LIMIT 1
    ");
    $st->execute([$orgId, $id]);
    $master = $st->fetch(\PDO::FETCH_ASSOC);
    if (!$master) {
        $this->abort404('Master challan not found.');
        return;
    }

    // Decode challan ids
    $ids = json_decode($master['challan_ids_json'] ?? '[]', true) ?: [];
    $ids = array_values(array_filter($ids, fn($v) => is_int($v) || ctype_digit((string)$v)));

    $challans = [];
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params       = array_merge([$orgId], $ids);

        $sql = "
            SELECT *
              FROM dms_challans
             WHERE org_id = ?
               AND id IN ($placeholders)
             ORDER BY challan_date, id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $challans = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    $moduleBase = rtrim((string)($ctx['module_base'] ?? ''), '/');
    if ($moduleBase === '') {
        $moduleBase = '/apps/dms';
    }

    $this->view('challan/master_from_challan', [
        'title'       => 'Master Dispatch Challan '.$master['master_no'],
        'org'         => $ctx['org'] ?? ($_SESSION['tenant_org'] ?? []),
        'module_base' => $moduleBase,
        'challans'    => $challans,
        'ids'         => $ids,
        'master'      => $master,
        'read_only'   => true,
        'active'      => 'challan',
        'subactive'   => 'challan.master',
    ], $ctx);
}



}