<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use Dompdf\Dompdf;
use Dompdf\Options;


/**
 * BizFlow — Invoices
 *
 * Routes (see routes.php):
 *   GET  /invoices            → index()
 *   GET  /invoices/{id}       → show()
 *   GET  /invoices/create     → create()  (manual POS-style form)
 *
 * Extra flow (this file):
 *   GET /awards/{id}/invoice  → createFromAward()
 *     - One-click: copies biz_awards + biz_award_lines
 *       into biz_invoices + biz_invoice_items
 *       and redirects to the invoice show page.
 */

final class InvoicesController extends BaseController
{
    /* -------------------------------------------------------------
     * Shared helper: ctx + org + PDO + module base
     * ----------------------------------------------------------- */
    private function base(?array $ctx = null): array
    {
        $c          = $this->ctx($ctx ?? []);
        $orgId      = $this->requireOrg();
        $pdo        = $this->pdo();
        $moduleBase = $c['module_base'] ?? '/apps/bizflow';

        return [$c, $orgId, $pdo, $moduleBase];
    }

    /* -------------------------------------------------------------
 * Helper: invoice number generator (INV-YYYY-00001)
 * ----------------------------------------------------------- */
private function generateInvoiceNo(PDO $pdo, int $orgId, string $date): string
{
    $year = substr($date, 0, 4);
    if (!ctype_digit($year)) {
        $year = date('Y');
    }

    $prefix = 'INV-' . $year . '-';

    $last = $this->val(
        "SELECT invoice_no
           FROM biz_invoices
          WHERE org_id = ? AND invoice_no LIKE ?
          ORDER BY invoice_no DESC
          LIMIT 1",
        [$orgId, $prefix . '%']
    );

    $next = 1;
    if ($last && preg_match('/^' . preg_quote($prefix, '/') . '(\d{5})$/', (string)$last, $m)) {
        $next = (int)$m[1] + 1;
    }

    return $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}
	/* -------------------------------------------------------------
     * Shared helpers (logo + identity) reused from Settings/Quotes
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
            if (!is_file($p)) {
                continue;
            }

            $filePath = $p;
            $fileUrl  = '/modules/bizflow/Assets/brand/logo/' . $orgKey . '/' . $file;

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
  
  
  
    /* ============================================================
 	 * 1) Index — list invoices with filters and metrics
     * ========================================================== */
		
  	public function index(?array $ctx = null): void
      	{
    	try {
        [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

        $search      = trim((string)($_GET['q']         ?? ''));
        $status      = trim((string)($_GET['status']    ?? ''));
        $dateFrom    = trim((string)($_GET['date_from'] ?? ''));
        $dateTo      = trim((string)($_GET['date_to']   ?? ''));
        $overdueRaw  = (string)($_GET['overdue'] ?? '');
        $overdueOnly = $overdueRaw === '1';

        $today   = date('Y-m-d');
        $metrics = [
            'total'   => 0,
            'unpaid'  => 0,
            'overdue' => 0,
            'today'   => $today,
        ];

        /* ------------------------------
         * Base query
         * ---------------------------- */
        $sql = "
            SELECT
                i.*,
                c.name AS customer_name,
                c.code AS customer_code,
                (COALESCE(i.grand_total,0) - COALESCE(i.paid_total,0)) AS balance
            FROM biz_invoices i
            LEFT JOIN biz_customers c
                   ON c.org_id = i.org_id
                  AND c.id     = i.customer_id
            WHERE i.org_id = :org_id
        ";
        $params = ['org_id' => $orgId];

        /* ------------------------------
         * Filters
         * ---------------------------- */
        if ($search !== '') {
            $sql .= " AND (
                i.invoice_no      LIKE :q
                OR i.external_ref LIKE :q
                OR c.name         LIKE :q
                OR c.code         LIKE :q
            )";
            $params['q'] = '%'.$search.'%';
        }

        if ($status !== '') {
            $sql .= " AND i.status = :status";
            $params['status'] = $status;
        }

        if ($dateFrom !== '') {
            $sql .= " AND i.date >= :date_from";
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND i.date <= :date_to";
            $params['date_to'] = $dateTo;
        }

        if ($overdueOnly) {
            $sql .= "
                AND i.due_date IS NOT NULL
                AND i.due_date < :today
                AND (COALESCE(i.grand_total,0) - COALESCE(i.paid_total,0)) > 0
                AND i.status NOT IN ('void','draft')
            ";
            $params['today'] = $today;
        }

        $sql .= "
            ORDER BY
                i.due_date IS NULL,
                i.due_date ASC,
                i.id DESC
            LIMIT 500
        ";

        $invoices = $this->rows($sql, $params);

        /* ------------------------------
         * Metrics from current result set
         * ---------------------------- */
        $metrics['total'] = count($invoices);
        foreach ($invoices as $row) {
            $bal = (float)($row['balance'] ?? 0.0);
            $st  = strtolower((string)($row['status'] ?? ''));
            $due = (string)($row['due_date'] ?? '');

            if ($bal > 0 && !in_array($st, ['void','draft'], true)) {
                $metrics['unpaid']++;
                if ($due !== '' && $due < $today) {
                    $metrics['overdue']++;
                }
            }
        }

        $this->view('invoices/index', [
            'title'        => 'Invoices',
            'org'          => $c['org'] ?? [],
            'module_base'  => $moduleBase,
            'invoices'     => $invoices,
            'metrics'      => $metrics,
            'search'       => $search,
            'status'       => $status,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'overdue_only' => $overdueOnly,
        ], 'shell');

    } catch (Throwable $e) {
        $this->oops('Invoices index failed', $e);
    }
}

/* =============================================================
 * SHOW
 *   GET /apps/bizflow/invoices/{id}
 *   GET /t/{slug}/apps/bizflow/invoices/{id}
 *   - Works for:
 *       • direct invoices
 *       • invoices from orders
 *       • invoices from awards
 * =========================================================== */

  public function show(?array $ctx = null, int $id = 0): void
{
    try {
        if ($id <= 0) {
            http_response_code(400);
            echo 'Invalid invoice id.';
            return;
        }

        [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

        // 1) Header + customer
        $invoice = $this->row(
            "SELECT
                 i.*,
                 c.name AS customer_name,
                 c.code AS customer_code
             FROM biz_invoices i
             LEFT JOIN biz_customers c
                    ON c.org_id = i.org_id
                   AND c.id     = i.customer_id
            WHERE i.org_id = ?
              AND i.id     = ?
            LIMIT 1",
            [$orgId, $id]
        );

        if (!$invoice) {
            http_response_code(404);
            echo 'Invoice not found.';
            return;
        }

        // 2) Line items
        $items = $this->rows(
            "SELECT *
               FROM biz_invoice_items
              WHERE org_id     = ?
                AND invoice_id = ?
              ORDER BY COALESCE(line_no, id), id",
            [$orgId, $id]
        );

        // Fallback: if org_id was not stored on items in early data
        if (!$items) {
            $items = $this->rows(
                "SELECT *
                   FROM biz_invoice_items
                  WHERE invoice_id = ?
                  ORDER BY COALESCE(line_no, id), id",
                [$id]
            );
        }

        // Final safety: if invoice came from an award and still has no items,
        // we try to reconstruct from award lines (same logic as before).
        if (!$items) {
            $items = $this->backfillInvoiceItemsFromAward($pdo, $orgId, $invoice);
        }

        $title = 'Invoice ' . ($invoice['invoice_no'] ?? ('#' . $id));

        $this->view('invoices/show', [
            'title'       => $title,
            'org'         => $c['org'] ?? [],
            'module_base' => $moduleBase,
            'invoice'     => $invoice,
            'items'       => $items,
        ], 'shell');

    } catch (Throwable $e) {
        $this->oops('Invoice show failed', $e);
    }
}

    /* ============================================================
     * 3) Create / Edit — FRONTEND-FIRST (manual POS-style form)
     * ========================================================== */

    public function create(?array $ctx = null): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            // Optional small customer list
            $customers = [];
            if ($this->hasTable($pdo, 'biz_customers')) {
                $customers = $this->rows(
                    "SELECT id, name, code
                       FROM biz_customers
                      WHERE org_id = ?
                      ORDER BY name
                      LIMIT 200",
                    [$orgId]
                );
            }

            $today = (new \DateTimeImmutable('now'))->format('Y-m-d');
            $due   = (new \DateTimeImmutable('now +7 days'))->format('Y-m-d');

            // We can optionally pre-generate a soft invoice number placeholder
            $invoiceNo = $this->generateInvoiceNo($pdo, $orgId, $today);

            $this->view('invoices/create', [
                'title'       => 'New invoice',
                'org'         => $c['org'] ?? [],
                'module_base' => $moduleBase,
                'customers'   => $customers,
                'today'       => $today,
                'due'         => $due,
                'invoice_no'  => $invoiceNo,
                'csrf'        => $c['csrf'] ?? '',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Invoice create failed', $e);
        }
    }
  
  	
	// EDIT SECTION
  
    public function edit(?array $ctx, int $id): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            if (!$this->hasTable($pdo, 'biz_invoices')) {
                http_response_code(500);
                echo 'Invoices storage not ready yet (biz_invoices table missing).';
                return;
            }

            // Base select same style as show(), so edit + show stay in sync
            $invoice = $this->row(
                "SELECT
                     i.*,
                     c.name         AS customer_name,
                     c.code         AS customer_code,
                     c.company_name AS customer_company,
                     u.name         AS owner_name,
                     (COALESCE(i.grand_total,0) - COALESCE(i.paid_total,0)) AS balance
                 FROM biz_invoices i
            LEFT JOIN biz_customers c
                   ON c.id     = i.customer_id
                  AND c.org_id = i.org_id
            LEFT JOIN cp_users u
                   ON u.id = i.owner_id
                WHERE i.org_id = ?
                  AND i.id     = ?
                LIMIT 1",
                [$orgId, $id]
            );

            if (!$invoice) {
                http_response_code(404);
                echo 'Invoice not found.';
                return;
            }

            // Items (optional)
            $items = [];
            if ($this->hasTable($pdo, 'biz_invoice_items')) {
                $items = $this->rows(
                    "SELECT it.*
                       FROM biz_invoice_items it
                      WHERE it.org_id     = ?
                        AND it.invoice_id = ?
                      ORDER BY it.id",
                    [$orgId, $id]
                );
            }

            // Customers for dropdown (optional)
            $customers = [];
            if ($this->hasTable($pdo, 'biz_customers')) {
                $customers = $this->rows(
                    "SELECT id, name, code
                       FROM biz_customers
                      WHERE org_id = ?
                      ORDER BY name
                      LIMIT 200",
                    [$orgId]
                );
            }

            $this->view('invoices/edit', [
                'title'       => 'Edit invoice',
                'org'         => $c['org'] ?? [],
                'module_base' => $moduleBase,
                'invoice'     => $invoice,
                'items'       => $items,
                'customers'   => $customers,
                'csrf'        => $c['csrf'] ?? '',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Invoice edit failed', $e);
        }
    }

    /* ============================================================
     * 4) One-click: create invoice from award (no manual form)
     * ========================================================== */

    /**
     * GET /apps/bizflow/awards/{id}/invoice
     * GET /t/{slug}/apps/bizflow/awards/{id}/invoice
     *
     * - If an invoice already exists for this award, redirect to it.
     * - Otherwise:
     *     • copy biz_awards → biz_invoices
     *     • copy biz_award_lines → biz_invoice_items
     *   Then redirect to the new invoice show page.
     */
    public function createFromAward(?array $ctx = null, int $awardId = 0): void
    {
        $pdo = null;

        try {
            if ($awardId <= 0) {
                http_response_code(400);
                echo 'Invalid award id.';
                return;
            }

            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            if (!$this->hasTable($pdo, 'biz_awards') ||
                !$this->hasTable($pdo, 'biz_award_lines') ||
                !$this->hasTable($pdo, 'biz_invoices') ||
                !$this->hasTable($pdo, 'biz_invoice_items')) {
                http_response_code(500);
                echo 'Required award / invoice tables are not ready yet.';
                return;
            }

            // If invoice already exists for this award, just go there.
            $existingId = $this->val(
                "SELECT id
                   FROM biz_invoices
                  WHERE org_id = ? AND award_id = ?
                  LIMIT 1",
                [$orgId, $awardId]
            );
            if ($existingId) {
                $this->redirect(rtrim($moduleBase, '/') . '/invoices/' . (int)$existingId);
                return;
            }

            // Load award
            $award = $this->row(
                "SELECT *
                   FROM biz_awards
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $awardId]
            );

            if (!$award) {
                http_response_code(404);
                echo 'Award not found.';
                return;
            }

            // Load award lines
            $lines = $this->rows(
                "SELECT *
                   FROM biz_award_lines
                  WHERE org_id = ? AND award_id = ?
                  ORDER BY line_no ASC, id ASC",
                [$orgId, $awardId]
            );

            if (!$lines) {
                http_response_code(400);
                echo 'Award has no lines to invoice.';
                return;
            }

            $pdo->beginTransaction();

            // Try to resolve customer_id from award or quote
            $customerId = (int)($award['customer_id'] ?? 0);
            if ($customerId <= 0 && !empty($award['quote_id'])) {
                $cid = $this->val(
                    "SELECT customer_id
                       FROM biz_quotes
                      WHERE org_id = ? AND id = ?
                      LIMIT 1",
                    [$orgId, (int)$award['quote_id']]
                );
                if ($cid) {
                    $customerId = (int)$cid;
                }
            }
            if ($customerId <= 0) {
                $customerId = null;
            }

            $customerName = trim((string)($award['customer_name'] ?? 'Customer'));
            if ($customerName === '') {
                $customerName = 'Customer';
            }

            $customerRef = trim((string)($award['customer_ref'] ?? ''));
            $externalRef = trim((string)($award['external_ref'] ?? ''));

            $currency = (string)($award['currency'] ?? 'BDT');

            $rawDate = (string)($award['award_date'] ?? $award['date'] ?? date('Y-m-d'));
            try {
                $dt = new \DateTimeImmutable($rawDate ?: 'now');
            } catch (\Exception $e) {
                $dt = new \DateTimeImmutable('now');
            }
            $date = $dt->format('Y-m-d');
            $due  = $dt->modify('+7 days')->format('Y-m-d');

            $subtotal       = (float)($award['subtotal']       ?? 0);
            $discountTotal  = (float)($award['discount_total'] ?? 0);
            $taxTotal       = (float)($award['tax_total']      ?? 0);
            $shippingTotal  = (float)($award['shipping_total'] ?? 0);
            $grandTotal     = (float)($award['grand_total']    ?? 0);
            $metaJson       = $award['meta_json'] ?? null;
            $notes          = $award['notes']     ?? null;

            // Fallback: compute subtotal & grand_total from lines if empty
            if ($subtotal <= 0) {
                $sum = 0.0;
                foreach ($lines as $ln) {
                    $sum += (float)($ln['line_total'] ?? 0);
                }
                $subtotal = $sum;
            }

            if ($grandTotal <= 0) {
                $grandTotal = $subtotal - $discountTotal + $taxTotal + $shippingTotal;
            }

            $invoiceNo = $this->generateInvoiceNo($pdo, $orgId, $date);

            /* -----------------------------------------------------
             * Insert invoice header
             * --------------------------------------------------- */
            $this->exec(
                "INSERT INTO biz_invoices (
                     org_id,
                     award_id,
                     quote_id,
                     customer_id,
                     customer_name,
                     customer_ref,
                     external_ref,
                     invoice_no,
                     currency,
                     date,
                     due_date,
                     status,
                     subtotal,
                     discount_total,
                     tax_total,
                     shipping_total,
                     grand_total,
                     paid_total,
                     notes,
                     meta_json
                 ) VALUES (
                     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                 )",
                [
                    $orgId,
                    $awardId,
                    $award['quote_id'] ?? null,
                    $customerId,
                    $customerName,
                    $customerRef !== '' ? $customerRef : null,
                    $externalRef !== '' ? $externalRef : null,
                    $invoiceNo,
                    $currency,
                    $date,
                    $due,
                    'approved',      // award is already confirmed
                    $subtotal,
                    $discountTotal,
                    $taxTotal,
                    $shippingTotal,
                    $grandTotal,
                    0.0,             // paid_total
                    $notes,
                    $metaJson,
                ]
            );

            $invoiceId = (int)$pdo->lastInsertId();

            /* -----------------------------------------------------
             * Insert invoice items from award lines
             * --------------------------------------------------- */
            $sqlLine = "
                INSERT INTO biz_invoice_items (
                    org_id,
                    invoice_id,
                    award_line_id,
                    product_id,
                    description,
                    qty,
                    unit,
                    unit_price,
                    discount_pct,
                    line_total,
                    meta_json
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";

            foreach ($lines as $ln) {
                $awardLineId = (int)$ln['id'];

                $productId = $ln['item_id']   ?? $ln['product_id']   ?? null;
                $desc      = $ln['description'] ?? $ln['item_name'] ?? $ln['product_name'] ?? ('Award line '.$awardLineId);

                $qty       = (float)($ln['qty']          ?? 0);
                $unit      = (string)($ln['unit']        ?? 'pcs');
                $unitPrice = (float)($ln['unit_price']   ?? 0);
                $discPct   = (float)($ln['discount_pct'] ?? 0);
                $lineTotal = (float)($ln['line_total']   ?? 0);

                if ($lineTotal <= 0) {
                    $lineBase  = $qty * $unitPrice;
                    $lineTotal = $lineBase - ($lineBase * $discPct / 100);
                }

                $this->exec($sqlLine, [
                    $orgId,
                    $invoiceId,
                    $awardLineId,
                    $productId,
                    $desc,
                    $qty,
                    $unit,
                    $unitPrice,
                    $discPct,
                    $lineTotal,
                    $ln['meta_json'] ?? null,
                ]);
            }

            $pdo->commit();

            // Finally, go straight to invoice show page
            $this->redirect(rtrim($moduleBase, '/') . '/invoices/' . $invoiceId);

        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Create invoice from award failed', $e);
        }
    }
  
  
/* =============================================================
 * PRINT (HTML) — open in new window, auto window.print()
 *   GET /apps/bizflow/invoices/{id}/print
 *   GET /t/{slug}/apps/bizflow/invoices/{id}/print
 * -----------------------------------------------------------
 * SEGMENT OVERVIEW:
 *   1) Guard + base context
 *   2) Load invoice header + items
 *   3) Resolve org identity + logo (same as Quotes)
 *   4) Include raw print template (no shell)
 * ========================================================== */

  public function print(?array $ctx = null, int $id = 0): void
	{
    try {
        /* SEGMENT 1: Guard + base context */
        if ($id <= 0) {
            http_response_code(400);
            echo 'Invalid invoice id.';
            return;
        }

        // base() = ctx + orgId + PDO + module base URL (same helper as other methods)
        [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

        /* SEGMENT 2: Load invoice header + customer (same logic as show) */
        $invoice = $this->row(
            "SELECT
                 i.*,
                 c.name AS customer_name,
                 c.code AS customer_code
             FROM biz_invoices i
        LEFT JOIN biz_customers c
               ON c.org_id = i.org_id
              AND c.id     = i.customer_id
            WHERE i.org_id = ?
              AND i.id     = ?
            LIMIT 1",
            [$orgId, $id]
        );

        if (!$invoice) {
            http_response_code(404);
            echo 'Invoice not found.';
            return;
        }

        // Items from invoice table; fallback to legacy rows; fallback from award if needed
        $items = $this->rows(
            "SELECT *
               FROM biz_invoice_items
              WHERE org_id    = ?
                AND invoice_id = ?
              ORDER BY COALESCE(line_no, id), id",
            [$orgId, $id]
        );

        if (!$items) {
            // very early rows that forgot org_id
            $items = $this->rows(
                "SELECT *
                   FROM biz_invoice_items
                  WHERE invoice_id = ?
                  ORDER BY COALESCE(line_no, id), id",
                [$id]
            );
        }

        if (!$items) {
            // last fallback: reconstruct from award lines if present
            $items = $this->backfillInvoiceItemsFromAward($pdo, $orgId, $invoice);
        }

        $org         = (array)($c['org'] ?? []);
        $module_base = $moduleBase;

        /* SEGMENT 3: Identity + logo (reused pattern from QuotesController)
         * These helpers must exist in this controller:
         *   - currentIdentityValuesForOrg(int $orgId, array $org): array
         *   - currentLogoInfoForOrg(int $orgId): array
         */
        $identityValues = $this->currentIdentityValuesForOrg($orgId, $org);
        $identity = [
            'name'    => $identityValues['name']    ?? '',
            'address' => $identityValues['address'] ?? '',
            'phone'   => $identityValues['phone']   ?? '',
            'email'   => $identityValues['email']   ?? '',
        ];

        $logoInfo = $this->currentLogoInfoForOrg($orgId);

        // Prefer data: URL for safety (works even if /modules is not web-served)
        $logoUrl = '/assets/brand/logo.png';
        if (!empty($logoInfo['data_url'])) {
            $logoUrl = (string)$logoInfo['data_url'];
        } elseif (!empty($logoInfo['url'])) {
            $logoUrl = (string)$logoInfo['url'];
        }

        $logo = [
            'url'      => $logoUrl,
            'data_url' => $logoInfo['data_url'] ?? null,
        ];

        /* SEGMENT 4: Render raw print template (no shell) */
        $viewFile = dirname(__DIR__, 2) . '/Views/invoices/print.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Invoice print template missing.';
            return;
        }

        $title = 'Invoice ' . ($invoice['invoice_no'] ?? ('#' . $id));

        /** @var array  $invoice */
        /** @var array  $items */
        /** @var array  $org */
        /** @var array  $identity */
        /** @var array  $logo */
        /** @var string $module_base */
        include $viewFile;

    } catch (Throwable $e) {
        $this->oops('Invoice print failed', $e);
    }
}
  
/* ============================================================
 * PDF — /invoices/{id}/pdf
 * ------------------------------------------------------------
 * SEGMENT OVERVIEW:
 *   1) Guard + base context
 *   2) Load invoice header + items (same rules as print)
 *   3) Resolve org identity + logo (data: URL for Dompdf)
 *   4) Render invoices/pdf.php to HTML (no shell)
 *   5) Dompdf: generate + stream PDF
 * ========================================================== */

  public function pdf(?array $ctx = null, int $id = 0): void
	{
    /* SEGMENT 1: Guard + base context */
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid invoice id.';
        return;
    }

    try {
        [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);
        $org = (array)($c['org'] ?? []);

        /* SEGMENT 2: Load invoice + customer */
        $invoice = $this->row(
            "SELECT
                 i.*,
                 c.name AS customer_name
             FROM biz_invoices i
        LEFT JOIN biz_customers c
               ON c.org_id = i.org_id
              AND c.id     = i.customer_id
            WHERE i.org_id = ?
              AND i.id     = ?
            LIMIT 1",
            [$orgId, $id]
        );

        if (!$invoice) {
            http_response_code(404);
            echo 'Invoice not found.';
            return;
        }

        // Load items (same fallback behaviour as print())
        $items = $this->rows(
            "SELECT *
               FROM biz_invoice_items
              WHERE org_id = ?
                AND invoice_id = ?
              ORDER BY COALESCE(line_no,id), id",
            [$orgId, $id]
        );

        if (!$items) {
            $items = $this->rows(
                "SELECT *
                   FROM biz_invoice_items
                  WHERE invoice_id = ?
                  ORDER BY COALESCE(line_no,id), id",
                [$id]
            );
        }

        if (!$items) {
            $items = $this->backfillInvoiceItemsFromAward($pdo, $orgId, $invoice);
        }

        /* SEGMENT 3: Identity + logo (same helpers as Quotes PDF) */
        $identityValues = $this->currentIdentityValuesForOrg($orgId, $org);
        $identity = [
            'name'    => $identityValues['name']    ?? '',
            'address' => $identityValues['address'] ?? '',
            'phone'   => $identityValues['phone']   ?? '',
            'email'   => $identityValues['email']   ?? '',
        ];

        $logoInfo = $this->currentLogoInfoForOrg($orgId);

        // For Dompdf, data: URL is safest (embeds image directly in HTML)
        $logoUrl = '/assets/brand/logo.png';
        if (!empty($logoInfo['data_url'])) {
            $logoUrl = (string)$logoInfo['data_url'];
        } elseif (!empty($logoInfo['url'])) {
            $logoUrl = (string)$logoInfo['url'];
        }

        $logo = [
            'url'      => $logoUrl,
            'data_url' => $logoInfo['data_url'] ?? null,
        ];

        /* SEGMENT 4: Render invoices/pdf.php into HTML string (no shell) */
        $viewFile = dirname(__DIR__, 2) . '/Views/invoices/pdf.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Invoice PDF template (Views/invoices/pdf.php) not found.';
            return;
        }

        ob_start();
        $module_base = $moduleBase; // if view needs it

        /** @var array $org */
        /** @var array $invoice */
        /** @var array $items */
        /** @var array $identity */
        /** @var array $logo */
        require $viewFile;

        $html = (string)ob_get_clean();

        /* SEGMENT 5: Dompdf — build and stream PDF */
        $options = new Options();
        $options->set('isRemoteEnabled', true);      // allow /assets/* if needed
        $options->set('isHtml5ParserEnabled', true); // better layout

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $fileName = ($invoice['invoice_no'] ?? ('invoice-' . $id)) . '.pdf';

        // ?download=1 → force download; otherwise open inline in browser
        $download = isset($_GET['download']) && $_GET['download'] === '1';
        $dompdf->stream($fileName, ['Attachment' => $download ? 1 : 0]);

    } catch (Throwable $e) {
        $this->oops('Invoice PDF generation failed', $e);
    }
}
  
  
  
    
/* =============================================================
 * POST /invoices  — Direct invoice create (AJAX JSON)
 *   - Consistent with QuotesController::store()
 *   - Works for direct invoices (no quote/award)
 * ========================================================== */
public function store(?array $ctx = null): void
{
    try {
        // ------------------------------
        // SEGMENT A: Guard + context
        // ------------------------------
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            return;
        }

        // If you have a CSRF helper, uncomment:
        // if (!$this->csrfVerifyPostTenant()) {
        //     $this->json(['ok' => false, 'error' => 'CSRF token mismatch.'], 419);
        //     return;
        // }

        $c            = $this->ctx($ctx ?? []);
        $moduleBase   = rtrim((string)($c['module_base'] ?? '/apps/bizflow'), '/');
        $orgId        = $this->requireOrg();
        $pdo          = $this->pdo();

        // ------------------------------
        // SEGMENT B: Read + validate header fields
        // ------------------------------
        $errors = [];

        $customerId   = (int)($_POST['customer_id'] ?? 0);
        $date         = trim((string)($_POST['date'] ?? ''));
        $dueDate      = trim((string)($_POST['due_date'] ?? ''));
        $externalRef  = trim((string)($_POST['external_ref'] ?? ''));

        // Status / mode – keep simple: draft vs issued
        $mode   = (string)($_POST['_mode'] ?? 'draft');   // 'draft' | 'issue'
        $status = $mode === 'issue' ? 'issued' : 'draft';

        if ($customerId <= 0) {
            $errors[] = 'Customer is required.';
        }

        if ($date === '') {
            $date = (new \DateTimeImmutable('now'))->format('Y-m-d');
        }

        // ------------------------------
        // SEGMENT C: Normalise line items
        // ------------------------------
        $rawLines   = $_POST['lines'] ?? [];
        $cleanLines = [];
        $subtotal   = 0.0;
        $discount   = 0.0;

        if (!is_array($rawLines) || count($rawLines) === 0) {
            $errors[] = 'At least one invoice line is required.';
        } else {
            $lineNo = 0;

            foreach ($rawLines as $ln) {
                if (!is_array($ln)) {
                    continue;
                }

                $qty       = isset($ln['qty']) ? (float)$ln['qty'] : 0.0;
                $unitPrice = isset($ln['unit_price']) ? (float)$ln['unit_price'] : 0.0;
                $discPct   = isset($ln['discount_pct']) ? (float)$ln['discount_pct'] : 0.0;

                $name        = trim((string)($ln['item_name'] ?? $ln['name'] ?? ''));
                $description = trim((string)($ln['description'] ?? ''));
                $unit        = trim((string)($ln['unit'] ?? 'pcs'));

                if ($unit === '') {
                    $unit = 'pcs';
                }

                // skip empty rows
                if ($qty <= 0 || $name === '' || $unitPrice < 0) {
                    continue;
                }

                $lineNo++;

                $gross   = $qty * $unitPrice;
                $discAmt = $gross * ($discPct / 100.0);
                $lineTot = $gross - $discAmt;

                $subtotal += $gross;
                $discount += $discAmt;

                $cleanLines[] = [
                    'line_no'      => $lineNo,
                    'item_name'    => $name,
                    'description'  => $description !== '' ? $description : null,
                    'qty'          => $qty,
                    'unit'         => $unit,
                    'unit_price'   => $unitPrice,
                    'discount_pct' => $discPct,
                    'line_total'   => $lineTot,
                ];
            }

            if (empty($cleanLines)) {
                $errors[] = 'At least one invoice line is required.';
            }
        }

        // If we collected any validation errors, stop here with ONE response
        if (!empty($errors)) {
            $this->json([
                'ok'     => false,
                'error'  => implode(' ', $errors),
                'errors' => $errors,
            ], 422);
            return;
        }

        // ------------------------------
        // SEGMENT D: Totals
        // ------------------------------
        $subtotal      = round($subtotal, 2);
        $discount      = round($discount, 2);
        $net           = max($subtotal - $discount, 0.0);

        // For now we keep tax/shipping 0 like quotes; can extend later
        $taxTotal      = 0.00;
        $shippingTotal = 0.00;
        $grandTotal    = round($net + $taxTotal + $shippingTotal, 2);

        // Any extra meta we want to remember (you can extend later)
        $meta = [
            'source' => 'direct',   // distinguish from quote/award derived invoices
        ];
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null;

        // ------------------------------
        // SEGMENT E: DB transaction — header + lines
        // ------------------------------
        $pdo->beginTransaction();

        // Use your existing helper to generate invoice number
        $invoiceNo = $this->generateInvoiceNo($pdo, $orgId, $date);

        $sql = "
            INSERT INTO biz_invoices (
                org_id,
                customer_id,
                quote_id,
                award_id,
                invoice_no,
                external_ref,
                status,
                date,
                due_date,
                currency,
                exchange_rate,
                subtotal,
                discount_total,
                tax_total,
                shipping_total,
                grand_total,
                meta_json
            ) VALUES (
                :org_id,
                :customer_id,
                :quote_id,
                :award_id,
                :invoice_no,
                :external_ref,
                :status,
                :date,
                :due_date,
                :currency,
                :exchange_rate,
                :subtotal,
                :discount_total,
                :tax_total,
                :shipping_total,
                :grand_total,
                :meta_json
            )
        ";

        $st = $pdo->prepare($sql);
        $ok = $st->execute([
            'org_id'         => $orgId,
            'customer_id'    => $customerId,
            'quote_id'       => null,           // direct invoice → no quote
            'award_id'       => null,           // direct invoice → no award
            'invoice_no'     => $invoiceNo,
            'external_ref'   => $externalRef !== '' ? $externalRef : null,
            'status'         => $status,
            'date'           => $date,
            'due_date'       => $dueDate !== '' ? $dueDate : null,
            'currency'       => 'BDT',
            'exchange_rate'  => 1.000000,
            'subtotal'       => $subtotal,
            'discount_total' => $discount,
            'tax_total'      => $taxTotal,
            'shipping_total' => $shippingTotal,
            'grand_total'    => $grandTotal,
            'meta_json'      => $metaJson,
        ]);

        if (!$ok) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->json(['ok' => false, 'error' => 'Failed to insert invoice header.'], 500);
            return;
        }

        $invoiceId = (int)$pdo->lastInsertId();

        // Insert items
        $lineSql = "
            INSERT INTO biz_invoice_items (
                org_id,
                invoice_id,
                line_no,
                item_name,
                description,
                qty,
                unit,
                unit_price,
                discount_pct,
                line_total,
                meta_json
            ) VALUES (
                :org_id,
                :invoice_id,
                :line_no,
                :item_name,
                :description,
                :qty,
                :unit,
                :unit_price,
                :discount_pct,
                :line_total,
                :meta_json
            )
        ";
        $li = $pdo->prepare($lineSql);

        foreach ($cleanLines as $ln) {
            $li->execute([
                'org_id'       => $orgId,
                'invoice_id'   => $invoiceId,
                'line_no'      => $ln['line_no'],
                'item_name'    => $ln['item_name'],
                'description'  => $ln['description'],
                'qty'          => $ln['qty'],
                'unit'         => $ln['unit'],
                'unit_price'   => $ln['unit_price'],
                'discount_pct' => $ln['discount_pct'],
                'line_total'   => $ln['line_total'],
                'meta_json'    => null,
            ]);
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        // ------------------------------
        // SEGMENT F: JSON success response
        // ------------------------------
        $this->json([
            'ok'         => true,
            'id'         => $invoiceId,
            'invoice_no' => $invoiceNo,
            'redirect'   => $moduleBase . '/invoices/' . $invoiceId,
        ]);

    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Your global error handler will log details
        $this->oops('Direct invoice store failed', $e);

        // Single clean JSON error back to frontend
        if (!headers_sent()) {
            $this->json([
                'ok'    => false,
                'error' => 'Unexpected error while saving invoice.',
            ], 500);
        }
    }
}

    public function update(?array $ctx, int $id): void
    {
        http_response_code(501);
        echo 'Invoice update is not implemented yet. Schema/posting flow will define this.';
    }

/**
 * If this invoice is linked to an award but has no invoice_items yet,
 * rebuild them from biz_award_lines and return the freshly inserted rows.
 */

  private function backfillInvoiceItemsFromAward(PDO $pdo, int $orgId, array $invoice): array
	{
    $invoiceId = (int)($invoice['id'] ?? 0);
    $awardId   = (int)($invoice['award_id'] ?? 0);

    if ($invoiceId <= 0 || $awardId <= 0) {
        return [];
    }

    // Load award lines
    $lines = $this->rows(
        "SELECT *
           FROM biz_award_lines
          WHERE org_id = ?
            AND award_id = ?
          ORDER BY line_no ASC, id ASC",
        [$orgId, $awardId]
    );

    if (!$lines) {
        return [];
    }

    try {
        $pdo->beginTransaction();

        $sqlItem = "
            INSERT INTO biz_invoice_items (
                org_id,
                invoice_id,
                award_line_id,
                line_no,
                kind,
                item_id,
                item_name,
                item_code,
                description,
                qty,
                unit,
                unit_price,
                discount_pct,
                tax_pct,
                line_subtotal,
                discount_amount,
                tax_amount,
                line_total,
                meta_json
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?
            )
        ";

        foreach ($lines as $ln) {
            $awardLineId = (int)$ln['id'];
            $lineNo      = (int)($ln['line_no'] ?? $awardLineId);

            $itemId   = $ln['item_id']   ?? $ln['product_id']   ?? null;
            $itemName = $ln['item_name'] ?? $ln['product_name'] ?? '';
            if ($itemName === '') {
                $itemName = (string)($ln['description'] ?? ('Line ' . $lineNo));
            }
            $itemCode = $ln['item_code'] ?? $ln['product_code'] ?? null;

            $qty       = (float)($ln['qty']          ?? 0);
            $unit      = (string)($ln['unit']        ?? 'pcs');
            $unitPrice = (float)($ln['unit_price']   ?? 0);
            $discPct   = (float)($ln['discount_pct'] ?? 0);
            $taxPct    = 0.0;

            $lineSubtotal = $qty * $unitPrice;
            $discAmount   = $discPct !== 0.0 ? round($lineSubtotal * $discPct / 100, 2) : 0.0;
            $taxAmount    = 0.0;
            $lineTotal    = $lineSubtotal - $discAmount + $taxAmount;

            $this->exec($sqlItem, [
                $orgId,
                $invoiceId,
                $awardLineId,
                $lineNo,
                $ln['kind'] ?? 'item',
                $itemId,
                $itemName,
                $itemCode,
                $ln['description'] ?? null,
                $qty,
                $unit,
                $unitPrice,
                $discPct,
                $taxPct,
                $lineSubtotal,
                $discAmount,
                $taxAmount,
                $lineTotal,
                $ln['meta_json'] ?? null,
            ]);
        }

        $pdo->commit();

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // If anything goes wrong, just return empty - page will still load.
        return [];
    }

    // Reload and return the items we just inserted
    return $this->rows(
        "SELECT *
           FROM biz_invoice_items
          WHERE org_id    = ?
            AND invoice_id = ?
          ORDER BY COALESCE(line_no, id), id",
        [$orgId, $invoiceId]
    );
}
  
  
    /* ============================================================
     * Local helper: check if table exists
     * ========================================================== */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}