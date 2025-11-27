<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow CustomersController
 * - Index: 2035-style list + filters
 * - Show: customer profile + historical docs
 * - Create/Store/Update: real DB write with defensive column checks
 */
final class CustomersController extends BaseController
{
    /**
     * GET /apps/bizflow/customers
     * GET /t/{slug}/apps/bizflow/customers
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $search      = trim((string)($_GET['q']    ?? ''));
            $segment     = trim((string)($_GET['seg']  ?? ''));
            $only_active = isset($_GET['active']) && $_GET['active'] === '1';

            $customers = [];

            if ($this->hasTable($pdo, 'biz_customers')) {
                $where = ['org_id = ?'];
                $bind  = [$orgId];

                if ($search !== '') {
                    $where[] = "(name LIKE ? OR code LIKE ? OR email LIKE ? OR phone LIKE ? OR company_name LIKE ?)";
                    $like    = '%' . $search . '%';
                    $bind[]  = $like;
                    $bind[]  = $like;
                    $bind[]  = $like;
                    $bind[]  = $like;
                    $bind[]  = $like;
                }

                if ($segment !== '') {
                    $where[] = "segment = ?";
                    $bind[]  = $segment;
                }

                if ($only_active) {
                    $where[] = "(is_active = 1)";
                }

                // SELECT * so schema tweaks don’t break this
                $sql = "
                    SELECT *
                      FROM biz_customers
                     WHERE " . implode(' AND ', $where) . "
                     ORDER BY name ASC, id ASC
                     LIMIT 1000
                ";

                $customers = $this->rows($sql, $bind);
            }

            $this->view('customers/index', [
                'title'        => 'Customers',
                'org'          => $c['org'] ?? [],
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'customers'    => $customers,
                'search'       => $search,
                'segment'      => $segment,
                'only_active'  => $only_active,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Customers index failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/customers/create
     * GET /t/{slug}/apps/bizflow/customers/create
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c = $this->ctx($ctx ?? []);
            $this->requireOrg();

            $this->view('customers/create', [
                'title'       => 'New Customer',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                // allow store() to re-use this view with validation errors
                'errors'      => [],
                'old'         => [],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Customers create failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/customers
     */
    public function store(?array $ctx = null): void
    {
        try {
            $this->postOnly();
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_customers')) {
                http_response_code(500);
                echo 'Customer table (biz_customers) missing.';
                return;
            }

            // Optional CSRF guard if available
            if (method_exists($this, 'csrfVerifyPostTenant')
                && !$this->csrfVerifyPostTenant()) {
                http_response_code(419);
                echo 'CSRF token mismatch.';
                return;
            }

            // ---------- Gather posted fields ----------
            $name          = trim((string)($_POST['name'] ?? ''));
            $codeInput     = trim((string)($_POST['code'] ?? ''));
            $email         = trim((string)($_POST['email'] ?? ''));
            $phone         = trim((string)($_POST['phone'] ?? ''));
            $companyName   = trim((string)($_POST['company_name'] ?? ''));
            $segment       = trim((string)($_POST['segment'] ?? ''));
            $taxNumber     = trim((string)($_POST['tax_number'] ?? ''));
            $billingAddr   = trim((string)($_POST['billing_address'] ?? ''));
            $shippingAddr  = trim((string)($_POST['shipping_address'] ?? ''));
            $city          = trim((string)($_POST['city'] ?? ''));
            $country       = trim((string)($_POST['country'] ?? ''));
            $notes         = trim((string)($_POST['notes'] ?? ''));
            $isActive      = isset($_POST['is_active']) ? 1 : 0;

            $errors = [];

            if ($name === '') {
                $errors['name'] = 'Name is required.';
            }

            // ---------- Generate / validate code ----------
            $code = $codeInput;

            if ($this->hasCol($pdo, 'biz_customers', 'code')) {
                if ($code === '') {
                    // Simple auto-code: ACME-00001 style from company/name
                    $source = $companyName !== '' ? $companyName : $name;
                    $upper  = strtoupper($source);
                    $letters = preg_replace('/[^A-Z]/', '', $upper) ?: 'CUST';
                    $base   = substr($letters, 0, 4);
                    $base   = str_pad($base, 4, 'X');

                    $prefix = $base . '-';

                    $last = $this->val(
                        "SELECT code
                           FROM biz_customers
                          WHERE org_id = ?
                            AND code LIKE ?
                          ORDER BY id DESC
                          LIMIT 1",
                        [$orgId, $prefix . '%']
                    );

                    $next = 1;
                    if (is_string($last) && preg_match('/^' . preg_quote($prefix, '/') . '(\d{5})$/', $last, $m)) {
                        $next = (int)$m[1] + 1;
                    }

                    $code = sprintf('%s%05d', $prefix, $next);
                }

                // Uniqueness check on (org_id, code)
                $exists = $this->val(
                    "SELECT id
                       FROM biz_customers
                      WHERE org_id = ? AND code = ?
                      LIMIT 1",
                    [$orgId, $code]
                );
                if ($exists) {
                    $errors['code'] = 'This customer code already exists for this organisation.';
                }
            }

            // Basic email format check (optional)
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email address.';
            }

            // If errors, re-render create page with previous input
            if ($errors) {
                $this->view('customers/create', [
                    'title'       => 'New Customer',
                    'org'         => $c['org'] ?? [],
                    'module_base' => $c['module_base'] ?? '/apps/bizflow',
                    'errors'      => $errors,
                    'old'         => $_POST,
                ], 'shell');
                return;
            }

            // ---------- Build INSERT dynamically based on existing columns ----------
            $cols   = ['org_id'];
            $vals   = [$orgId];

            $maybeCol = function(string $col) use (&$cols, &$vals, $pdo) {
                // will be filled later via closure binding
            };

            // We’ll manually check/append each possible column, using hasCol.
            $addCol = function(string $col, $value) use (&$cols, &$vals) {
                $cols[] = $col;
                $vals[] = $value;
            };

            if ($this->hasCol($pdo, 'biz_customers', 'name')) {
                $addCol('name', $name);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'code')) {
                $addCol('code', $code);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'email')) {
                $addCol('email', $email !== '' ? $email : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'phone')) {
                $addCol('phone', $phone !== '' ? $phone : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'company_name')) {
                $addCol('company_name', $companyName !== '' ? $companyName : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'segment')) {
                $addCol('segment', $segment !== '' ? $segment : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'tax_number')) {
                $addCol('tax_number', $taxNumber !== '' ? $taxNumber : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'billing_address')) {
                $addCol('billing_address', $billingAddr !== '' ? $billingAddr : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'shipping_address')) {
                $addCol('shipping_address', $shippingAddr !== '' ? $shippingAddr : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'city')) {
                $addCol('city', $city !== '' ? $city : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'country')) {
                $addCol('country', $country !== '' ? $country : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'notes')) {
                $addCol('notes', $notes !== '' ? $notes : null);
            }
            if ($this->hasCol($pdo, 'biz_customers', 'is_active')) {
                $addCol('is_active', $isActive);
            }

            // created_at / updated_at will use DB defaults if they exist

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO biz_customers (" . implode(',', $cols) . ")
                    VALUES ({$placeholders})";

            $this->exec($sql, $vals);

            // ---------- Redirect back to customers list ----------
            $base = $c['module_base'] ?? '/apps/bizflow';
            if (!headers_sent()) {
                header('Location: ' . rtrim($base, '/') . '/customers');
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('Customers store failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/customers/{id}
     * GET /t/{slug}/apps/bizflow/customers/{id}
     */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_customers')) {
                http_response_code(404);
                echo 'Customer table missing.';
                return;
            }

            // Core customer record
            $customer = $this->row(
                "SELECT *
                   FROM biz_customers
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$customer) {
                http_response_code(404);
                echo 'Customer not found.';
                return;
            }

            // Historical documents (guarded)
            $quotes   = [];
            $orders   = [];
            $invoices = [];
            $payments = [];

            if ($this->hasTable($pdo, 'biz_quotes')) {
                $quotes = $this->rows(
                    "SELECT *
                       FROM biz_quotes
                      WHERE org_id = ? AND customer_id = ?
                      ORDER BY date DESC, id DESC
                      LIMIT 50",
                    [$orgId, $id]
                );
            }

            if ($this->hasTable($pdo, 'biz_orders')) {
                $orders = $this->rows(
                    "SELECT *
                       FROM biz_orders
                      WHERE org_id = ? AND customer_id = ?
                      ORDER BY date DESC, id DESC
                      LIMIT 50",
                    [$orgId, $id]
                );
            }

            if ($this->hasTable($pdo, 'biz_invoices')) {
                $invoices = $this->rows(
                    "SELECT *
                       FROM biz_invoices
                      WHERE org_id = ? AND customer_id = ?
                      ORDER BY date DESC, id DESC
                      LIMIT 50",
                    [$orgId, $id]
                );
            }

            if ($this->hasTable($pdo, 'biz_payments')) {
                $payments = $this->rows(
                    "SELECT *
                       FROM biz_payments
                      WHERE org_id = ? AND customer_id = ?
                      ORDER BY date DESC, id DESC
                      LIMIT 50",
                    [$orgId, $id]
                );
            }

            // Metrics snapshot
            $metrics = [
                'open_quote_count'    => count($quotes),
                'open_order_count'    => count($orders),
                'invoice_count'       => count($invoices),
                'payment_count'       => count($payments),
                'lifetime_invoiced'   => null,
                'lifetime_paid'       => null,
                'outstanding_balance' => null,
                'last_activity_at'    => null,
            ];

            if ($this->hasTable($pdo, 'biz_invoices') &&
                $this->hasCol($pdo, 'biz_invoices', 'grand_total')) {
                $metrics['lifetime_invoiced'] = (float)($this->val(
                    "SELECT COALESCE(SUM(grand_total), 0)
                       FROM biz_invoices
                      WHERE org_id = ? AND customer_id = ?",
                    [$orgId, $id]
                ) ?? 0.0);
            }

            if ($this->hasTable($pdo, 'biz_payments') &&
                $this->hasCol($pdo, 'biz_payments', 'amount')) {
                $metrics['lifetime_paid'] = (float)($this->val(
                    "SELECT COALESCE(SUM(amount), 0)
                       FROM biz_payments
                      WHERE org_id = ? AND customer_id = ?",
                    [$orgId, $id]
                ) ?? 0.0);
            }

            if ($this->hasCol($pdo, 'biz_customers', 'ar_balance')) {
                $metrics['outstanding_balance'] = (float)($customer['ar_balance'] ?? 0.0);
            } elseif ($metrics['lifetime_invoiced'] !== null && $metrics['lifetime_paid'] !== null) {
                $metrics['outstanding_balance']
                    = (float)$metrics['lifetime_invoiced'] - (float)$metrics['lifetime_paid'];
            }

            $dates = [];
            foreach ([$quotes, $orders, $invoices, $payments] as $group) {
                foreach ($group as $row) {
                    foreach (['date', 'created_at', 'posted_at'] as $dk) {
                        if (!empty($row[$dk])) {
                            $dates[] = $row[$dk];
                            break;
                        }
                    }
                }
            }
            if ($dates) {
                rsort($dates);
                $metrics['last_activity_at'] = $dates[0];
            }

            $this->view('customers/show', [
                'title'       => 'Customer — ' . ($customer['name'] ?? 'Details'),
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'customer'    => $customer,
                'metrics'     => $metrics,
                'quotes'      => $quotes,
                'orders'      => $orders,
                'invoices'    => $invoices,
                'payments'    => $payments,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Customers show failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/customers/{id}/edit
     * (still a safe stub)
     */
    public function edit(?array $ctx, int $id): void
    {
        try {
            http_response_code(501);
            echo 'Customer edit not implemented yet.';
        } catch (Throwable $e) {
            $this->oops('Customers edit failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/customers/{id}
     * (safe stub for now)
     */
    public function update(?array $ctx, int $id): void
    {
        try {
            $this->postOnly();
            http_response_code(501);
            echo 'Customer update not implemented yet.';
        } catch (Throwable $e) {
            $this->oops('Customers update failed', $e);
        }
    }

    /* -------------------------------------------------------------
     * Local helpers so index/show/store don’t explode
     * ----------------------------------------------------------- */

    private function hasTable(PDO $pdo, string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $st = $pdo->prepare(
            "SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?"
        );
        $st->execute([$table]);
        $cache[$table] = (bool)$st->fetchColumn();

        return $cache[$table];
    }

    private function hasCol(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $st = $pdo->prepare(
            "SELECT COUNT(*)
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?"
        );
        $st->execute([$table, $column]);
        $cache[$key] = (bool)$st->fetchColumn();

        return $cache[$key];
    }
}