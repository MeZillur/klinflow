<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;

final class QuotesController extends BaseController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */

    private function flash(string $msg): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['bizflow_quote_flash'] = $msg;
    }

    /** Next quote number for this org: Q-YYYY-00001 */
    private function nextQuoteNo(PDO $pdo, int $orgId): string
    {
        $year = (new DateTimeImmutable('now'))->format('Y');

        $st = $pdo->prepare(
            "SELECT COALESCE(MAX(id), 0) + 1 AS seq
             FROM biz_quotes
             WHERE org_id = ?"
        );
        $st->execute([$orgId]);
        $seq = (int)$st->fetchColumn();

        return sprintf('Q-%s-%05d', $year, $seq);
    }

    /** Current tenant user id for owner_id */
    private function currentUserId(): ?int
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        $u = $_SESSION['tenant_user'] ?? null;
        if (!is_array($u)) {
            return null;
        }

        $id = $u['id'] ?? null;
        return $id !== null ? (int)$id : null;
    }

    /** Small JSON helper so store/update/email can respond via AJAX */
    protected function json($data, int $code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data);
        exit;
    }

    /** Check if a table exists in the current DB (safe guard) */
    private function hasTable(PDO $pdo, string $table): bool
    {
        $sql = "SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute(['t' => $table]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Build quote line items from meta_json->rows when DB lines are empty.
     *
     * @param string|null $metaJson
     * @return array<int,array<string,mixed>>
     */
    private function buildLinesFromMetaJson(?string $metaJson): array
    {
        if ($metaJson === null || $metaJson === '') {
            return [];
        }

        $decoded = json_decode((string)$metaJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = $decoded['rows'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $lines = [];

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }

            $lines[] = [
                'line_no'      => $row['line_no']      ?? ($i + 1),
                'kind'         => $row['kind']         ?? 'item',
                'name'         => $row['name']         ?? '',
                'description'  => $row['description']  ?? '',
                'qty'          => (float)($row['qty'] ?? 0),
                'unit'         => (string)($row['unit'] ?? 'pcs'),
                'unit_price'   => (float)($row['unit_price'] ?? 0),
                'discount_pct' => (float)($row['discount_pct'] ?? 0),
                // frontend may use "total" or "line_total"
                'line_total'   => (float)($row['line_total'] ?? ($row['total'] ?? 0)),
            ];
        }

        return $lines;
    }

    /**
     * Simple helper to render a raw PHP view file (no shell) to string.
     */
    private function renderViewToString(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }

    /* -------------------------------------------------------------
     * Shared helpers (logo + identity) reused from SettingsController
     * ----------------------------------------------------------- */

    /** Base dir where all logos live: modules/bizflow/Assets/brand/logo */
    private function logoBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/brand/logo';
    }

    /** Base dir where per-tenant identity JSON lives */
    private function identityBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/settings';
    }

    /** Ensure a directory exists (used by logo + identity) */
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
     *   'url'      => web URL (/modules/…/logo.ext) or null,
     *   'data_url' => base64 data: URL for inline use (print/PDF), or null,
     *   'exists'   => bool
     * ]
     */
    private function currentLogoInfoForOrg(int $orgId): array
    {
        $baseDir = $this->logoBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $candidates = ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.svg'];

        $filePath = null;
        $fileUrl  = null;
        $dataUrl  = null;

        foreach ($candidates as $file) {
            $p = $dir . '/' . $file;
            if (is_file($p)) {
                $filePath = $p;

                // HTTP path (if /modules is web-reachable)
                $fileUrl  = '/modules/bizflow/Assets/brand/logo/' . $orgKey . '/' . $file;

                // Also build a data: URL so print/PDF works even if /modules is not public
                $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
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

                break;
            }
        }

        return [
            'dir'      => $dir,
            'path'     => $filePath,
            'url'      => $fileUrl,
            'data_url' => $dataUrl,
            'exists'   => $filePath !== null,
        ];
    }

    /**
     * Load identity (name, address, phone, email) for this org from JSON,
     * falling back to cp_organizations values if JSON is missing/partial.
     */
    private function currentIdentityValuesForOrg(int $orgId, array $org): array
    {
        $baseDir = $this->identityBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $file   = $dir . '/identity.json';
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
                        $values[$k] = $data[$k];
                    }
                }
            }
        }

        return $values;
    }

    /* =============================================================
     * GET /quotes  → index list
     * =========================================================== */
    public function index(?array $ctx = null): void
    {
        try {
            $c    = $this->ctx($ctx ?? []);
            $pdo  = $this->pdo();
            $org  = $c['org'] ?? [];
            $base = $c['module_base'] ?? '/apps/bizflow';

            $orgId  = $this->requireOrg();
            $quotes = [];

            if ($this->hasTable($pdo, 'biz_quotes')) {
                $sql = "
                    SELECT
                        q.*,
                        c.name AS customer_name
                    FROM biz_quotes q
                    LEFT JOIN biz_customers c
                      ON c.id = q.customer_id
                     AND c.org_id = q.org_id
                    WHERE q.org_id = ?
                    ORDER BY q.id DESC
                    LIMIT 200
                ";
                $st = $pdo->prepare($sql);
                $st->execute([$orgId]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // Decode meta_json for UI fields
                foreach ($rows as &$row) {
                    $meta = [];
                    if (!empty($row['meta_json'])) {
                        $tmp = json_decode((string)$row['meta_json'], true);
                        if (is_array($tmp)) {
                            $meta = $tmp;
                        }
                    }
                    if (isset($meta['customer_contact'])) {
                        $row['customer_contact'] = $meta['customer_contact'];
                    }
                    if (isset($meta['customer_reference'])) {
                        $row['customer_reference'] = $meta['customer_reference'];
                    }
                    if (isset($meta['ui_type'])) {
                        $row['quote_type'] = $meta['ui_type'];
                    }
                }
                unset($row);

                $quotes = $rows;
            }

            $this->view('quotes/index', [
                'title'       => 'Quotes',
                'org'         => $org,
                'module_base' => $base,
                'quotes'      => $quotes,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Quotes index failed', $e);
        }
    }

    /* =============================================================
     * GET /quotes/create
     * =========================================================== */
    public function create(?array $ctx = null): void
    {
        try {
            $c      = $this->ctx($ctx ?? []);
            $org    = $c['org'] ?? [];
            $base   = $c['module_base'] ?? '/apps/bizflow';
            $pdo    = $this->pdo();
            $orgId  = $this->requireOrg();

            $today      = (new DateTimeImmutable('now'))->format('Y-m-d');
            $validUntil = (new DateTimeImmutable('now +7 days'))->format('Y-m-d');

            $customers = [];

            // Preload stock items for dropdown
            $items = [];
            if ($this->hasTable($pdo, 'biz_items')) {
                $sql = "
                    SELECT
                        id,
                        code,
                        name,
                        unit,
                        sale_price,
                        description
                    FROM biz_items
                    WHERE org_id    = :org
                      AND item_type = 'stock'
                      AND (is_active = 1 OR is_active IS NULL)
                    ORDER BY name ASC
                    LIMIT 500
                ";
                $st = $pdo->prepare($sql);
                $st->execute(['org' => $orgId]);
                $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $this->view('quotes/create', [
                'title'       => 'New quote',
                'org'         => $org,
                'module_base' => $base,
                'customers'   => $customers,
                'items'       => $items,
                'today'       => $today,
                'valid_until' => $validUntil,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Quote create failed', $e);
        }
    }

    /* =============================================================
 * POST /quotes  — header + line items
 * =========================================================== */
public function store(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c      = $this->ctx($ctx ?? []);
            $base   = rtrim((string)($c['module_base'] ?? '/apps/bizflow'), '/');
            $orgId  = $this->requireOrg();
            $pdo    = $this->pdo();

            $ownerId = $this->currentUserId();

            // ---------- Core form fields ----------
            $mode     = (string)($_POST['_mode'] ?? 'draft');   // 'draft' | 'send'
            $status   = $mode === 'send' ? 'sent' : 'draft';

            $custId   = (int)($_POST['customer_id'] ?? 0);
            $date     = (string)($_POST['date'] ?? '');
            $valid    = (string)($_POST['valid_until'] ?? '');
            $extRef   = trim((string)($_POST['external_ref'] ?? ''));

            $uiType    = (string)($_POST['quote_type'] ?? 'mixed');
            $quoteType = 'standard';

            $vatPercent       = (float)($_POST['vat_percent'] ?? 0);
            $customerContact  = trim((string)($_POST['customer_contact'] ?? ''));
            $customerRef      = trim((string)($_POST['customer_reference'] ?? ''));
            $paymentTerms     = trim((string)($_POST['payment_terms'] ?? ''));
            $deliveryTerms    = trim((string)($_POST['delivery_terms'] ?? ''));

            if ($custId <= 0) {
                $this->json(['ok' => false, 'error' => 'Customer is required.'], 422);
                return;
            }

            // ---------- Normalise line items ----------
            $rawLines      = $_POST['lines'] ?? [];
            $cleanLines    = [];
            $subtotal      = 0.0;
            $discountTotal = 0.0;

            if (is_array($rawLines)) {
                $lineNo        = 0;
                $itemNameCache = [];

                foreach ($rawLines as $ln) {
                    $qty       = isset($ln['qty']) ? (float)$ln['qty'] : 0.0;
                    $unitPrice = isset($ln['unit_price']) ? (float)$ln['unit_price'] : 0.0;
                    $discPct   = isset($ln['discount_pct']) ? (float)$ln['discount_pct'] : 0.0;

                    $productId   = isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
                    $serviceName = trim((string)($ln['service_name'] ?? ''));
                    $description = trim((string)($ln['description'] ?? ''));
                    $unit        = trim((string)($ln['unit'] ?? 'pcs'));
                    if ($unit === '') {
                        $unit = 'pcs';
                    }

                    $hasProduct = $productId > 0;
                    $hasService = $serviceName !== '';

                    if ($qty <= 0 || (!$hasProduct && !$hasService)) {
                        continue;
                    }

                    $lineNo++;
                    $kind = $hasProduct ? 'item' : 'service';

                    // Try to build a meaningful item name
                    $name = '';
                    if (!empty($ln['name'])) {
                        $name = trim((string)$ln['name']);
                    } elseif ($hasService) {
                        $name = $serviceName;
                    } elseif ($hasProduct) {
                        // Fallback to product name from biz_items
                        if (!isset($itemNameCache[$productId])) {
                            $stItem = $pdo->prepare(
                                "SELECT name FROM biz_items
                                 WHERE org_id = ? AND id = ?
                                 LIMIT 1"
                            );
                            $stItem->execute([$orgId, $productId]);
                            $itemNameCache[$productId] = (string)($stItem->fetchColumn() ?: '');
                        }
                        $name = $itemNameCache[$productId] !== ''
                            ? $itemNameCache[$productId]
                            : ('Line ' . $lineNo);
                    } else {
                        $name = 'Line ' . $lineNo;
                    }

                    // Compute totals
                    $gross   = $qty * $unitPrice;
                    $discAmt = $gross * ($discPct / 100.0);
                    $lineTot = $gross - $discAmt;

                    $subtotal      += $gross;
                    $discountTotal += $discAmt;

                    $cleanLines[] = [
                        'line_no'      => $lineNo,
                        'product_id'   => $hasProduct ? $productId : null,
                        'kind'         => $kind,
                        'name'         => $name,
                        'description'  => $description !== '' ? $description : null,
                        'qty'          => $qty,
                        'unit'         => $unit,
                        'unit_price'   => $unitPrice,
                        'discount_pct' => $discPct,
                        'line_total'   => $lineTot,
                    ];
                }
            }

            $subtotal      = round($subtotal, 2);
            $discountTotal = round($discountTotal, 2);
            $net           = max($subtotal - $discountTotal, 0.0);

            $vatAmount = ($net > 0 && $vatPercent > 0)
                ? round($net * $vatPercent / 100.0, 2)
                : 0.0;

            $taxTotal      = 0.00;
            $shippingTotal = 0.00;
            $grandTotal    = round($net + $vatAmount + $shippingTotal, 2);

            // ---------- Notes / meta ----------
            $notesInternal = null;
            $notesExternal = null;

            if ($paymentTerms !== '' || $deliveryTerms !== '') {
                $chunks = [];
                if ($paymentTerms !== '') {
                    $chunks[] = "Payment terms:\n" . $paymentTerms;
                }
                if ($deliveryTerms !== '') {
                    $chunks[] = "Delivery terms:\n" . $deliveryTerms;
                }
                $notesExternal = implode("\n\n", $chunks);
            }

            $meta = [
                'ui_type'            => $uiType,
                'vat_percent'        => $vatPercent,
                'customer_contact'   => $customerContact,
                'customer_reference' => $customerRef,
                'payment_terms'      => $paymentTerms,
                'delivery_terms'     => $deliveryTerms,
            ];
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null;

            // ---------- DB: header + lines ----------
            $pdo->beginTransaction();

            $quoteNo = $this->nextQuoteNo($pdo, $orgId);

            $sql = "INSERT INTO biz_quotes (
                        org_id,
                        customer_id,
                        owner_id,
                        quote_no,
                        external_ref,
                        quote_type,
                        status,
                        date,
                        valid_until,
                        currency,
                        exchange_rate,
                        subtotal,
                        discount_total,
                        tax_total,
                        vat_total,
                        shipping_total,
                        grand_total,
                        notes_internal,
                        notes_external,
                        meta_json
                    ) VALUES (
                        :org_id,
                        :customer_id,
                        :owner_id,
                        :quote_no,
                        :external_ref,
                        :quote_type,
                        :status,
                        :date,
                        :valid_until,
                        :currency,
                        :exchange_rate,
                        :subtotal,
                        :discount_total,
                        :tax_total,
                        :vat_total,
                        :shipping_total,
                        :grand_total,
                        :notes_internal,
                        :notes_external,
                        :meta_json
                    )";

            $st = $pdo->prepare($sql);
            $ok = $st->execute([
                'org_id'         => $orgId,
                'customer_id'    => $custId,
                'owner_id'       => $ownerId,
                'quote_no'       => $quoteNo,
                'external_ref'   => $extRef !== '' ? $extRef : null,
                'quote_type'     => $quoteType,
                'status'         => $status,
                'date'           => $date !== '' ? $date : (new DateTimeImmutable('now'))->format('Y-m-d'),
                'valid_until'    => $valid !== '' ? $valid : null,
                'currency'       => 'BDT',
                'exchange_rate'  => 1.000000,
                'subtotal'       => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total'      => $taxTotal,
                'vat_total'      => $vatAmount,
                'shipping_total' => $shippingTotal,
                'grand_total'    => $grandTotal,
                'notes_internal' => $notesInternal,
                'notes_external' => $notesExternal,
                'meta_json'      => $metaJson,
            ]);

            if (!$ok) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $this->json(['ok' => false, 'error' => 'Insert failed'], 500);
                return;
            }

            $quoteId = (int)$pdo->lastInsertId();

            // Insert line items if table exists
            if ($this->hasTable($pdo, 'biz_quote_lines') && $cleanLines) {
                $lineSql = "
                    INSERT INTO biz_quote_lines (
                        org_id,
                        quote_id,
                        line_no,
                        product_id,
                        kind,
                        name,
                        description,
                        qty,
                        unit,
                        unit_price,
                        discount_pct,
                        line_total,
                        meta_json
                    ) VALUES (
                        :org_id,
                        :quote_id,
                        :line_no,
                        :product_id,
                        :kind,
                        :name,
                        :description,
                        :qty,
                        :unit,
                        :unit_price,
                        :discount_pct,
                        :line_total,
                        :meta_json
                    )";
                $ls = $pdo->prepare($lineSql);

                foreach ($cleanLines as $ln) {
                    $ls->execute([
                        'org_id'       => $orgId,
                        'quote_id'     => $quoteId,
                        'line_no'      => $ln['line_no'],
                        'product_id'   => $ln['product_id'],
                        'kind'         => $ln['kind'],
                        'name'         => $ln['name'],
                        'description'  => $ln['description'],
                        'qty'          => $ln['qty'],
                        'unit'         => $ln['unit'],
                        'unit_price'   => $ln['unit_price'],
                        'discount_pct' => $ln['discount_pct'],
                        'line_total'   => $ln['line_total'],
                        'meta_json'    => null,
                    ]);
                }
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            $this->flash('Quote saved successfully.');
            $redirectUrl = $base . '/quotes/' . $quoteId;

            $this->json([
                'ok'       => true,
                'id'       => $quoteId,
                'quote_no' => $quoteNo,
                'redirect' => $redirectUrl,
            ]);

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->oops('Quote store failed', $e);
            $this->json(['ok' => false, 'error' => 'Quote store failed'], 500);
        }
    }

    /* ============================================================
     * GET /quotes/{id}/pdf — Download PDF (A4, no shell)
     * ========================================================== */
    public function downloadPdf(?array $ctx, int $id): void
    {
        try {
            // Make sure Dompdf is really available
            if (!class_exists(Dompdf::class) || !class_exists(Options::class)) {
                http_response_code(500);
                echo 'PDF engine not configured. Make sure "dompdf/dompdf" is installed via Composer and vendor/autoload.php is required in public/index.php.';
                return;
            }

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $org = (array)($c['org'] ?? []);

            $storageReady = $this->hasTable($pdo, 'biz_quotes');
            $quote        = null;
            $lines        = [];

            if ($storageReady) {
                // Same header load as show/printView
                $quote = $this->row(
                    "SELECT
                        q.*,
                        c.name AS customer_name
                     FROM biz_quotes q
                     LEFT JOIN biz_customers c
                       ON c.id = q.customer_id
                      AND c.org_id = q.org_id
                     WHERE q.org_id = ? AND q.id = ?
                     LIMIT 1",
                    [$orgId, $id]
                );

                if ($quote && $this->hasTable($pdo, 'biz_quote_lines')) {
                    $st = $pdo->prepare(
                        "SELECT
                            l.*,
                            i.name AS product_name,
                            i.code AS product_code
                         FROM biz_quote_lines l
                         LEFT JOIN biz_items i
                           ON i.id = l.product_id
                          AND i.org_id = l.org_id
                         WHERE l.org_id = ?
                           AND l.quote_id = ?
                         ORDER BY l.line_no, l.id"
                    );
                    $st->execute([$orgId, $id]);
                    $lines = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                // Fallback for very early quotes: build lines from meta_json->rows
                if ($quote && !$lines) {
                    $metaJson = $quote['meta_json'] ?? null;
                    $lines    = $this->buildLinesFromMetaJson(
                        is_string($metaJson) ? $metaJson : null
                    );
                }
            }

            if (!$quote) {
                http_response_code(404);
                echo 'Quote not found.';
                return;
            }

            /* ---------------------------------------------------------
             * Identity + logo (same pattern as printView)
             * ------------------------------------------------------- */
            $identityValues = $this->currentIdentityValuesForOrg($orgId, $org);
            $identity = [
                'name'    => $identityValues['name']    ?? '',
                'address' => $identityValues['address'] ?? '',
                'phone'   => $identityValues['phone']   ?? '',
                'email'   => $identityValues['email']   ?? '',
            ];

            $logoInfo = $this->currentLogoInfoForOrg($orgId);

            $logoUrl = '/assets/brand/logo.png'; // safe fallback
            if (!empty($logoInfo['path']) && is_file($logoInfo['path'])) {
                $fsPath = (string)$logoInfo['path'];
                $raw    = @file_get_contents($fsPath);

                if ($raw !== false) {
                    $ext  = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
                    $mime = 'image/png';

                    if ($ext === 'jpg' || $ext === 'jpeg') {
                        $mime = 'image/jpeg';
                    } elseif ($ext === 'webp') {
                        $mime = 'image/webp';
                    } elseif ($ext === 'svg') {
                        $mime = 'image/svg+xml';
                    }

                    $logoUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
                }
            }

            $logo = ['url' => $logoUrl];

            /* ---------------------------------------------------------
             * Render the PDF layout as HTML from Views/quotes/pdf.php
             * (no module shell; raw PHP view only).
             * ------------------------------------------------------- */
            $viewFile = dirname(__DIR__, 2) . '/Views/quotes/pdf.php';
            if (!is_file($viewFile)) {
                http_response_code(500);
                echo 'Quote PDF template (pdf.php) not found.';
                return;
            }

            $viewData = [
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'quote'       => $quote,
                'lines'       => $lines,
                'identity'    => $identity,
                'logo'        => $logo,
            ];

            $html = $this->renderViewToString($viewFile, $viewData);

            /* ---------------------------------------------------------
             * Dompdf: A4 PDF, portrait, inline in browser
             * ------------------------------------------------------- */
            $options = new Options();
            $options->set('isRemoteEnabled', true);      // allow /assets/*
            $options->set('isHtml5ParserEnabled', true); // better layout

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

           $filename = ($quote['quote_no'] ?? ('quote-' . $id)) . '.pdf';

			if (!headers_sent()) {
    			header('Content-Type: application/pdf');
    			header('Content-Disposition: attachment; filename="' . $filename . '"');
    			header('Content-Transfer-Encoding: binary');
    			header('Cache-Control: private, must-revalidate');
    			header('Pragma: public');
			}

			echo $dompdf->output();
			exit;

        } catch (Throwable $e) {
            $this->oops('Quote PDF download failed', $e);
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo 'Quote PDF download failed';
        }
    }

    /* =============================================================
     * GET /quotes/{id} — details
     * =========================================================== */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_quotes');
            $quote        = null;
            $lines        = [];

            if ($storageReady) {
                $quote = $this->row(
                    "SELECT
                        q.*,
                        c.name AS customer_name
                     FROM biz_quotes q
                     LEFT JOIN biz_customers c
                       ON c.id = q.customer_id
                      AND c.org_id = q.org_id
                     WHERE q.org_id = ? AND q.id = ?
                     LIMIT 1",
                    [$orgId, $id]
                );

                if ($quote && $this->hasTable($pdo, 'biz_quote_lines')) {
                    $st = $pdo->prepare(
                        "SELECT
                            l.*,
                            i.name AS product_name,
                            i.code AS product_code
                         FROM biz_quote_lines l
                         LEFT JOIN biz_items i
                           ON i.id = l.product_id
                          AND i.org_id = l.org_id
                         WHERE l.org_id = ?
                           AND l.quote_id = ?
                         ORDER BY l.line_no, l.id"
                    );
                    $st->execute([$orgId, $id]);
                    $lines = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                // Fallback: if no DB lines yet, build them from meta_json->rows
                if ($quote && !$lines) {
                    $metaJson = isset($quote['meta_json']) && is_string($quote['meta_json'])
                        ? $quote['meta_json']
                        : null;
                    $lines = $this->buildLinesFromMetaJson($metaJson);
                }
            }

            if (!$quote) {
                http_response_code(404);
                echo 'Quote not found.';
                return;
            }

            $this->view('quotes/show', [
                'title'         => 'Quote details',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'quote'         => $quote,
                'lines'         => $lines,
                'storage_ready' => $storageReady,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Quote details failed', $e);
        }
    }

    /* =============================================================
     * GET /quotes/{id}/edit — Edit form
     * =========================================================== */
    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $quote = $this->row(
                "SELECT
                    q.*,
                    c.name AS customer_name
                 FROM biz_quotes q
                 LEFT JOIN biz_customers c
                   ON c.id = q.customer_id
                  AND c.org_id = q.org_id
                 WHERE q.org_id = ? AND q.id = ?
                 LIMIT 1",
                [$orgId, $id]
            );

            $lines = [];
            if ($quote && $this->hasTable($pdo, 'biz_quote_lines')) {
                $st = $pdo->prepare(
                    "SELECT
                        l.*,
                        i.name AS product_name,
                        i.code AS product_code
                     FROM biz_quote_lines l
                     LEFT JOIN biz_items i
                       ON i.id = l.product_id
                      AND i.org_id = l.org_id
                     WHERE l.org_id = ?
                       AND l.quote_id = ?
                     ORDER BY l.line_no, l.id"
                );
                $st->execute([$orgId, $id]);
                $lines = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            if (!$quote) {
                http_response_code(404);
                echo 'Quote not found.';
                return;
            }

            $this->view('quotes/edit', [
                'title'       => 'Edit quote',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'quote'       => $quote,
                'lines'       => $lines,
                'mode'        => 'edit',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Quote edit failed', $e);
        }
    }
  
   /* ============================================================
     * CREATE AWARD
     * ========================================================== */

  	public function createAward(array $ctx, int $id): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        return;
    }

    $orgId = (int)($ctx['org_id'] ?? 0);
    $slug  = (string)($ctx['slug'] ?? '');
    $moduleBase = $slug !== '' ? "/t/{$slug}/apps/bizflow" : "/apps/bizflow";

    if ($orgId <= 0) {
        http_response_code(500);
        echo 'Missing org context.';
        return;
    }

    $pdo = \Shared\DB::pdo();

    try {
        $pdo->beginTransaction();

        // 1) Load quote
        $stmt = $pdo->prepare("SELECT * FROM biz_quotes WHERE org_id = ? AND id = ? LIMIT 1");
        $stmt->execute([$orgId, $id]);
        $quote = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$quote) {
            $pdo->rollBack();
            http_response_code(404);
            echo 'Quote not found.';
            return;
        }

        // 1b) If award already exists for this quote, just go back to quote details
        $chk = $pdo->prepare("SELECT id FROM biz_awards WHERE org_id = ? AND quote_id = ? LIMIT 1");
        $chk->execute([$orgId, $id]);
        $existingAwardId = $chk->fetchColumn();
        if ($existingAwardId) {
            $pdo->rollBack();
            header('Location: ' . $moduleBase . '/quotes/' . $id, true, 302);
            return;
        }

        // 2) Generate award_no via biz_counters (doc_type = 'award')
        $counterStmt = $pdo->prepare("
            SELECT id, prefix, next_no
            FROM biz_counters
            WHERE org_id = ? AND doc_type = 'award'
            FOR UPDATE
        ");
        $counterStmt->execute([$orgId]);
        $row = $counterStmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $next = (int)$row['next_no'];
            if ($next <= 0) {
                $next = 1;
            }
            $prefix    = (string)($row['prefix'] ?? 'AWD-');
            $counterId = (int)$row['id'];

            $upd = $pdo->prepare("UPDATE biz_counters SET next_no = ? WHERE id = ?");
            $upd->execute([$next + 1, $counterId]);
        } else {
            $prefix = 'AWD-';
            $next   = 1;
            $insC = $pdo->prepare("
                INSERT INTO biz_counters (org_id, doc_type, prefix, next_no)
                VALUES (?, 'award', ?, ?)
            ");
            // next_no = 2 → we use 1 now, next run will use 2
            $insC->execute([$orgId, $prefix, 2]);
        }

        $awardNo = $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);

        // 3) Prepare header fields from quote
        $currency   = (string)($quote['currency'] ?? 'BDT');
        $date       = (string)($quote['date'] ?? date('Y-m-d'));
        $custName   = trim((string)($quote['customer_name'] ?? ''));
        if ($custName === '') {
            $custName = 'Customer';
        }

        $externalRef = (string)($quote['external_ref'] ?? '');
        $subtotal    = (float)($quote['subtotal'] ?? 0);
        $discountTot = (float)($quote['discount_total'] ?? 0);
        $taxTot      = (float)($quote['tax_total'] ?? 0);
        $shipTot     = (float)($quote['shipping_total'] ?? 0);
        $grandTot    = (float)($quote['grand_total'] ?? 0);
        $metaJson    = $quote['meta_json'] ?? null;

        // Contact + customer ref live in meta_json on the quote
        $meta = [];
        if (!empty($metaJson)) {
            $tmp = json_decode((string)$metaJson, true);
            if (is_array($tmp)) {
                $meta = $tmp;
            }
        }
        $custContact = trim((string)($meta['customer_contact'] ?? ''));
        $custRef     = trim((string)($meta['customer_reference'] ?? ''));
        if ($custRef === '' && $externalRef !== '') {
            $custRef = $externalRef;
        }

        // 4) Insert award header
        $insert = $pdo->prepare("
            INSERT INTO biz_awards (
                org_id, quote_id,
                customer_name, customer_contact, customer_ref,
                award_no, external_ref,
                date, status, currency,
                subtotal, discount_total, tax_total, shipping_total, grand_total,
                meta_json
            ) VALUES (
                :org_id, :quote_id,
                :customer_name, :customer_contact, :customer_ref,
                :award_no, :external_ref,
                :date, :status, :currency,
                :subtotal, :discount_total, :tax_total, :shipping_total, :grand_total,
                :meta_json
            )
        ");
        $insert->execute([
            ':org_id'          => $orgId,
            ':quote_id'        => $id,
            ':customer_name'   => $custName,
            ':customer_contact'=> $custContact !== '' ? $custContact : null,
            ':customer_ref'    => $custRef !== '' ? $custRef : null,
            ':award_no'        => $awardNo,
            ':external_ref'    => $externalRef !== '' ? $externalRef : null,
            ':date'            => $date,
            ':status'          => 'approved',             // initial award status
            ':currency'        => $currency,
            ':subtotal'        => $subtotal,
            ':discount_total'  => $discountTot,
            ':tax_total'       => $taxTot,
            ':shipping_total'  => $shipTot,
            ':grand_total'     => $grandTot,
            ':meta_json'       => $metaJson,
        ]);

        $awardId = (int)$pdo->lastInsertId();

        // 5) Copy line items from biz_quote_lines → biz_award_lines
        $copy = $pdo->prepare("
            INSERT INTO biz_award_lines (
                org_id, award_id,
                line_no, kind,
                product_name, product_code,
                description,
                qty, unit,
                unit_price, discount_pct, line_total
            )
            SELECT
                org_id, :award_id,
                line_no, kind,
                product_name, product_code,
                description,
                qty, unit,
                unit_price, discount_pct, line_total
            FROM biz_quote_lines
            WHERE org_id = :org_id AND quote_id = :quote_id
            ORDER BY line_no, id
        ");
        $copy->execute([
            ':award_id' => $awardId,
            ':org_id'   => $orgId,
            ':quote_id' => $id,
        ]);

        // 6) Mark quote as approved (matches your new status model)
        $upq = $pdo->prepare("UPDATE biz_quotes SET status = 'approved' WHERE org_id = ? AND id = ?");
        $upq->execute([$orgId, $id]);

        $pdo->commit();

        // For now, go back to quote details; after Awards pages exist
        // we can redirect to /awards/{id}
        header('Location: ' . $moduleBase . '/quotes/' . $id, true, 302);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1') {
            http_response_code(500);
            echo 'Failed to create award: ' .
                htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        } else {
            http_response_code(500);
            echo 'Failed to create award.';
        }
    }
}
  
  
  
    /* ============================================================
     * POST /quotes/{id}/status — inline status change from index
     * ========================================================== */
    public function updateStatus(array $ctx, int $id): void
{
    // POST only
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        return;
    }

    // Read status from POST
    $status = strtolower(trim($_POST['status'] ?? ''));

    // Only allow these 3 from the index pills
    $allowed = ['approved', 'accepted', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'Invalid status value',
            'value' => $status,
        ]);
        return;
    }

    // Resolve org_id from ctx (depends on how you store it)
    $orgId = 0;
    if (isset($ctx['org_id'])) {
        $orgId = (int)$ctx['org_id'];
    } elseif (isset($ctx['org']['id'])) {
        $orgId = (int)$ctx['org']['id'];
    }

    if ($orgId <= 0) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Missing organisation context']);
        return;
    }

    // Get tenant PDO (or fallback to shared)
    $pdo = method_exists(\Shared\DB::class, 'tenant')
        ? \Shared\DB::tenant()
        : \Shared\DB::pdo();

    try {
        // TODO: if your table name is different, change `bf_quotes` here
        $sql = "UPDATE biz_quotes
           		SET status = :status, updated_at = NOW()
         		WHERE org_id = :org_id AND id = :id
         		LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':status' => $status,
            ':org_id' => $orgId,
            ':id'     => $id,
        ]);

        if ($st->rowCount() < 1) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => false,
                'error' => 'Quote not found for this organisation',
            ]);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'     => true,
            'status' => $status,
            'id'     => $id,
        ]);
    } catch (\Throwable $e) {
        // If APP_DEBUG is on you’ll also see the exception in your global handler
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'Failed to update status',
            'code'  => $e->getCode(),
        ]);
    }
}

    /* ============================================================
     * GET /quotes/{id}/print — A4 print / PDF-ready view
     * ========================================================== */
    public function printView(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $org = (array)($c['org'] ?? []);

            $storageReady = $this->hasTable($pdo, 'biz_quotes');
            $quote        = null;
            $lines        = [];

            if ($storageReady) {
                $quote = $this->row(
                    "SELECT q.*, c.name AS customer_name
                     FROM biz_quotes q
                     LEFT JOIN biz_customers c
                       ON c.id = q.customer_id
                      AND c.org_id = q.org_id
                     WHERE q.org_id = ? AND q.id = ?
                     LIMIT 1",
                    [$orgId, $id]
                );

                if ($quote && $this->hasTable($pdo, 'biz_quote_lines')) {
                    $st = $pdo->prepare(
                        "SELECT
                            l.*,
                            i.name AS product_name,
                            i.code AS product_code
                         FROM biz_quote_lines l
                         LEFT JOIN biz_items i
                           ON i.id = l.product_id
                          AND i.org_id = l.org_id
                         WHERE l.org_id = ?
                           AND l.quote_id = ?
                         ORDER BY l.line_no, l.id"
                    );
                    $st->execute([$orgId, $id]);
                    $lines = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                // Fallback: build from meta_json if we still have no lines
                if ($quote && !$lines) {
                    $metaJson = isset($quote['meta_json']) && is_string($quote['meta_json'])
                        ? $quote['meta_json']
                        : null;
                    $lines = $this->buildLinesFromMetaJson($metaJson);
                }
            }

            if (!$quote) {
                http_response_code(404);
                echo 'Quote not found.';
                return;
            }

            // 1) Identity values from JSON (or org fallback)
            $identityValues = $this->currentIdentityValuesForOrg($orgId, $org);
            $identity = [
                'name'    => $identityValues['name']    ?? '',
                'address' => $identityValues['address'] ?? '',
                'phone'   => $identityValues['phone']   ?? '',
                'email'   => $identityValues['email']   ?? '',
            ];

            // 2) Logo: data URL if possible, otherwise KlinFlow default
            $logoInfo = $this->currentLogoInfoForOrg($orgId);

            $logoUrl = '/assets/brand/logo.png'; // safe fallback

            if (!empty($logoInfo['path']) && is_file($logoInfo['path'])) {
                $fsPath = (string)$logoInfo['path'];
                $raw    = @file_get_contents($fsPath);

                if ($raw !== false) {
                    $ext  = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
                    $mime = 'image/png';

                    if ($ext === 'jpg' || $ext === 'jpeg') {
                        $mime = 'image/jpeg';
                    } elseif ($ext === 'webp') {
                        $mime = 'image/webp';
                    } elseif ($ext === 'svg') {
                        $mime = 'image/svg+xml';
                    }

                    $logoUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
                }
            }

            $logo = ['url' => $logoUrl];

            $this->view('quotes/print', [
                'title'         => 'Quote print',
                'org'           => $org,
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'quote'         => $quote,
                'lines'         => $lines,
                'storage_ready' => $storageReady,
                'identity'      => $identity,
                'logo'          => $logo,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Quote print failed', $e);
        }
    }

    /* ============================================================
     * POST /quotes/{id}/email — Email quote (preview)
     * ========================================================== */
    public function sendEmail(?array $ctx, int $id): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c     = $this->ctx($ctx ?? []);
            $base  = $c['module_base'] ?? '/apps/bizflow';
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $storageReady = $this->hasTable($pdo, 'biz_quotes');
            $quote        = null;

            if ($storageReady) {
                $quote = $this->row(
                    "SELECT *
                     FROM biz_quotes
                     WHERE org_id = ? AND id = ?
                     LIMIT 1",
                    [$orgId, $id]
                );
            }

            if (!$quote) {
                http_response_code(404);
                echo 'Quote not found.';
                return;
            }

            // For now just flash + redirect; later wire Dompdf + mailer.
            $this->flash(
                'Email send requested for this quote (preview only). Mail/PDF engine will be wired in the next phase.'
            );

            $redirect = rtrim($base, '/') . '/quotes/' . $id;

            $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
            if (strpos($accept, 'application/json') !== false) {
                $this->json(['ok' => true, 'redirect' => $redirect]);
                return;
            }

            if (!headers_sent()) {
                header('Location: ' . $redirect);
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Quote email failed', $e);
            $this->json(['ok' => false, 'error' => 'Quote email failed'], 500);
        }
    }
}