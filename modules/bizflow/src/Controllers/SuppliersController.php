<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow SuppliersController
 * - Index: list + filters (matches suppliers/index.php)
 * - Create: form shell
 * - Store: insert into biz_suppliers + redirect to show
 * - Show: basic supplier profile
 * - Update: stub for now
 */
final class SuppliersController extends BaseController
{
    /**
     * GET /apps/bizflow/suppliers
     * GET /t/{slug}/apps/bizflow/suppliers
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $search      = (string)($_GET['q']    ?? '');
            $filter_type = (string)($_GET['type'] ?? '');
            $only_active = isset($_GET['active']) && $_GET['active'] === '1';

            $where = ['org_id = ?'];
            $bind  = [$orgId];

            // Simple search across a few safe columns
            if ($search !== '') {
                $like = '%' . $search . '%';
                $where[] = '('
                    . 'name LIKE ?'
                    . ' OR code LIKE ?'
                    . ' OR phone LIKE ?'
                    . ' OR email LIKE ?'
                    . ' OR city LIKE ?'
                    . ' OR district LIKE ?'
                    . ' OR country LIKE ?'
                    . ')';
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
                $bind[] = $like;
            }

            // Type filter (varchar(32) in your schema)
            if ($filter_type !== '') {
                $where[] = 'type = ?';
                $bind[]  = $filter_type;
            }

            // Only active
            if ($only_active) {
                $where[] = 'is_active = 1';
            }

            $sql = "
                SELECT *
                FROM biz_suppliers
                WHERE " . implode(' AND ', $where) . "
                ORDER BY name ASC, id ASC
            ";

            $suppliers = $this->rows($sql, $bind);

            $this->view('suppliers/index', [
                'title'       => 'Suppliers',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'suppliers'   => $suppliers,
                'search'      => $search,
                'filter_type' => $filter_type,
                'only_active' => $only_active,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Suppliers index failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/suppliers/create
     * GET /t/{slug}/apps/bizflow/suppliers/create
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c = $this->ctx($ctx ?? []);
            $this->requireOrg(); // ensure tenant context

            $this->view('suppliers/create', [
                'title'       => 'New Supplier',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'mode'        => 'create',
                'supplier'    => null,
                'errors'      => [],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Suppliers create failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/suppliers
     */
    public function store(?array $ctx = null): void
    {
        try {
            $this->postOnly();

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $name   = trim((string)($_POST['name'] ?? ''));
            $code   = strtoupper(trim((string)($_POST['code'] ?? '')));
            $type   = trim((string)($_POST['type'] ?? 'local'));

            $contact_name = trim((string)($_POST['contact_name'] ?? ''));
            $phone        = trim((string)($_POST['phone'] ?? ''));
            $alt_phone    = trim((string)($_POST['alt_phone'] ?? ''));
            $email        = trim((string)($_POST['email'] ?? ''));
            $website      = trim((string)($_POST['website'] ?? ''));

            $city         = trim((string)($_POST['city'] ?? ''));
            $district     = trim((string)($_POST['district'] ?? ''));
            $country      = trim((string)($_POST['country'] ?? 'Bangladesh'));
            $postal       = trim((string)($_POST['postal_code'] ?? ''));

            $addr1        = trim((string)($_POST['address_line1'] ?? ''));
            $addr2        = trim((string)($_POST['address_line2'] ?? ''));

            $payment      = trim((string)($_POST['payment_terms'] ?? ''));
            $credit_raw   = trim((string)($_POST['credit_limit'] ?? ''));
            $credit_limit = ($credit_raw === '' ? null : (float)$credit_raw);

            $tax_reg_no   = trim((string)($_POST['tax_reg_no'] ?? ''));
            $currency     = trim((string)($_POST['preferred_currency'] ?? ''));
            $is_active    = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
            $notes        = trim((string)($_POST['notes'] ?? ''));

            $errors = [];

            if ($name === '') {
                $errors[] = 'Name is required.';
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email address is not valid.';
            }

            // Normalise type
            if ($type === '') $type = 'local';

            if (!in_array($type, ['local', 'international', 'other'], true)) {
                $type = 'local';
            }

            // If any validation errors, redisplay the form
            if (!empty($errors)) {
                if (!headers_sent()) {
                    http_response_code(422);
                }
                $this->view('suppliers/create', [
                    'title'       => 'New Supplier',
                    'org'         => $c['org'] ?? [],
                    'module_base' => $c['module_base'] ?? '/apps/bizflow',
                    'mode'        => 'create',
                    'supplier'    => $_POST,
                    'errors'      => $errors,
                ], 'shell');
                return;
            }

            // Auto-generate code if empty
            if ($code === '') {
                $code = $this->generateSupplierCode($pdo, $orgId, $name);
            }

            // Insert supplier
            $sql = "
                INSERT INTO biz_suppliers (
                    org_id, code, name, type,
                    contact_name, phone, alt_phone, email, website,
                    city, district, country, postal_code,
                    address_line1, address_line2,
                    payment_terms, credit_limit,
                    tax_reg_no, preferred_currency,
                    is_active, notes
                ) VALUES (
                    :org_id, :code, :name, :type,
                    :contact_name, :phone, :alt_phone, :email, :website,
                    :city, :district, :country, :postal_code,
                    :address_line1, :address_line2,
                    :payment_terms, :credit_limit,
                    :tax_reg_no, :preferred_currency,
                    :is_active, :notes
                )
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':org_id'            => $orgId,
                ':code'              => $code,
                ':name'              => $name,
                ':type'              => $type,
                ':contact_name'      => $contact_name,
                ':phone'             => $phone,
                ':alt_phone'         => $alt_phone,
                ':email'             => $email,
                ':website'           => $website,
                ':city'              => $city,
                ':district'          => $district,
                ':country'           => $country,
                ':postal_code'       => $postal,
                ':address_line1'     => $addr1,
                ':address_line2'     => $addr2,
                ':payment_terms'     => $payment,
                ':credit_limit'      => $credit_limit,
                ':tax_reg_no'        => $tax_reg_no,
                ':preferred_currency'=> $currency,
                ':is_active'         => $is_active,
                ':notes'             => $notes,
            ]);

            $newId = (int)$pdo->lastInsertId();

            $moduleBase = $c['module_base'] ?? '/apps/bizflow';
            if (!headers_sent()) {
                header('Location: '.$moduleBase.'/suppliers/'.$newId, true, 302);
            }
            return;

        } catch (Throwable $e) {
            $this->oops('Suppliers store failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/suppliers/{id}
     * GET /t/{slug}/apps/bizflow/suppliers/{id}
     */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $supplier = $this->row(
                "SELECT *
                 FROM biz_suppliers
                 WHERE org_id = ? AND id = ?
                 LIMIT 1",
                [$orgId, $id]
            );

            if (!$supplier) {
                if (!headers_sent()) {
                    http_response_code(404);
                }
                echo 'Supplier not found.';
                return;
            }

            $this->view('suppliers/show', [
                'title'       => 'Supplier — ' . ($supplier['name'] ?? 'Detail'),
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'supplier'    => $supplier,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Suppliers show failed', $e);
        }
    }

    /**
     * POST/PATCH /apps/bizflow/suppliers/{id}
     * Still stubbed – safe no-op.
     */
    public function update(?array $ctx, int $id): void
    {
        try {
            $this->postOnly();
            if (!headers_sent()) {
                http_response_code(501);
            }
            echo 'Supplier update not implemented yet.';
        } catch (Throwable $e) {
            $this->oops('Suppliers update failed', $e);
        }
    }

    /* ============================================================
     * Local helper: generate supplier code per org
     * ========================================================== */

    /**
     * Generate a code like ABC001, ABC002 per org_id from supplier name.
     */
    private function generateSupplierCode(PDO $pdo, int $orgId, string $name): string
    {
        $base = preg_replace('/[^A-Z0-9]/', '', strtoupper($name));
        if ($base === '') {
            $base = 'SUP';
        }
        $base = substr($base, 0, 3);

        $stmt = $pdo->prepare("
            SELECT code
            FROM biz_suppliers
            WHERE org_id = ? AND code LIKE ?
            ORDER BY code DESC
            LIMIT 1
        ");
        $stmt->execute([$orgId, $base.'%']);
        $last = (string)($stmt->fetchColumn() ?: '');

        $nextNum = 1;
        if ($last !== '' && preg_match('/^'.preg_quote($base, '/').'(\d+)$/', $last, $m)) {
            $nextNum = (int)$m[1] + 1;
        }

        return sprintf('%s%03d', $base, $nextNum);
    }
}