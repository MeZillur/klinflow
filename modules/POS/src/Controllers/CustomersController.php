<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

final class CustomersController extends BaseController
{
    /* --------------------------- context helper ---------------------------- */

    private function ctxOk(array $ctx): array
    {
        $c   = $this->ctx($ctx);
        $org = $c['org'] ?? ($_SESSION['tenant_org'] ?? []);

        if (!isset($c['org']) && $org) {
            $c['org'] = $org;
        }

        if (empty($c['org_id']) && isset($org['id'])) {
            $c['org_id'] = (int)$org['id'];
        }

        if (empty($c['slug']) && isset($org['slug'])) {
            $c['slug'] = (string)$org['slug'];
        }

        if (empty($c['module_base'])) {
            $slug = (string)($c['slug'] ?? '');
            $c['module_base'] = $slug !== ''
                ? '/t/'.rawurlencode($slug).'/apps/pos'
                : '/apps/pos';
        }

        if (empty($c['module_dir'])) {
            // modules/POS
            $c['module_dir'] = dirname(__DIR__, 2);
        }

        return $c;
    }

    /* --------------------------- code generator --------------------------- */

    /**
     * Generate next code like CUS-2025-00001 per org & year.
     */
    private function nextCode(int $orgId): string
    {
        $year   = date('Y');
        $prefix = "CUS-{$year}-";

        // last code for this org+year
        $row = $this->row(
            "SELECT code
               FROM pos_customers
              WHERE org_id = :o AND code LIKE :p
              ORDER BY code DESC
              LIMIT 1",
            [
                ':o' => $orgId,
                ':p' => $prefix.'%',   // safe because year & prefix are fixed
            ]
        );

        $n = 1;
        if ($row && isset($row['code']) &&
            preg_match('/^CUS-\d{4}-(\d{5})$/', (string)$row['code'], $m)
        ) {
            $n = (int)$m[1] + 1;
        }

        return sprintf('%s%05d', $prefix, $n);
    }

    /* ------------------------------- index -------------------------------- */

    /** GET /customers */
    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $q    = trim((string)($_GET['q'] ?? ''));
            $page = max(1, (int)($_GET['page'] ?? 1));
            $per  = 24;
            $off  = ($page - 1) * $per;

            $where = 'org_id = :org';
            $bind  = [':org' => $orgId];

            if ($q !== '') {
                $where .= ' AND (name LIKE :q OR phone LIKE :q OR email LIKE :q OR code LIKE :q)';
                $bind[':q'] = "%{$q}%";
            }

            $total = 0;
            $pages = 1;
            $rows  = [];

            if ($orgId > 0) {
                $row   = $this->row("SELECT COUNT(*) c FROM pos_customers WHERE {$where}", $bind);
                $total = (int)($row['c'] ?? 0);
                $pages = max(1, (int)ceil($total / $per));

                $rows = $this->rows(
                    "SELECT
                        id,
                        code,
                        name,
                        phone,
                        email,
                        city,
                        country,
                        COALESCE(is_active,1) AS is_active,
                        created_at
                     FROM pos_customers
                     WHERE {$where}
                     ORDER BY name ASC
                     LIMIT {$per} OFFSET {$off}",
                    $bind
                );
            }

            $this->view(
                $c['module_dir'].'/Views/customers/index.php',
                [
                    'title' => 'Customers',
                    'ctx'   => $c,
                    'base'  => $c['module_base'],
                    'q'     => $q,
                    'rows'  => $rows,
                    'page'  => $page,
                    'pages' => $pages,
                    'total' => $total,
                ],
                'shell'
            );
        } catch (\Throwable $e) {
            $this->oops('Customers index failed', $e);
        }
    }

    /* ------------------------------ create -------------------------------- */

    /** GET /customers/create */
    public function create(array $ctx = []): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $nextCode = $orgId > 0 ? $this->nextCode($orgId) : '';

            $this->view(
                $c['module_dir'].'/Views/customers/create.php',
                [
                    'title'    => 'New Customer',
                    'ctx'      => $c,
                    'base'     => $c['module_base'],
                    'nextCode' => $nextCode,
                ],
                'shell'
            );
        } catch (\Throwable $e) {
            $this->oops('Customers create failed', $e);
        }
    }

    /** POST /customers */
    public function store(array $ctx = []): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $in = fn(string $k, string $d = ''): string =>
                trim((string)($_POST[$k] ?? $d));

            $code    = $in('code');
            $name    = $in('name');
            $phone   = $in('phone');
            $email   = $in('email');
            $addr    = $in('address');         // maps to address_line1
            $city    = $in('city');
            $country = $in('country');
            $notes   = $in('notes');
            $active  = isset($_POST['is_active']) ? 1 : 0;

            $errors = [];

            if ($name === '')    $errors['name']    = 'Name is required';
            if ($phone === '')   $errors['phone']   = 'Phone is required';
            if ($city === '')    $errors['city']    = 'City is required';
            if ($country === '') $errors['country'] = 'Country is required';

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email address';
            }

            if ($errors) {
                $_SESSION['pos_errors'] = $errors;
                $_SESSION['pos_old']    = $_POST;
                header('Location: '.$c['module_base'].'/customers/create');
                return;
            }

            if ($orgId > 0) {
                // if user left it blank, generate automatically
                if ($code === '') {
                    $code = $this->nextCode($orgId);
                }

                // ensure code unique for org
                $exists = $this->row(
                    "SELECT id FROM pos_customers
                      WHERE org_id = :o AND code = :x
                      LIMIT 1",
                    [':o' => $orgId, ':x' => $code]
                );
                if ($exists) {
                    $_SESSION['pos_errors'] = ['code' => 'Code already exists'];
                    $_SESSION['pos_old']    = $_POST;
                    header('Location: '.$c['module_base'].'/customers/create');
                    return;
                }

                $this->pdo()->prepare(
                    "INSERT INTO pos_customers (
                        org_id,
                        code,
                        name,
                        phone,
                        email,
                        address_line1,
                        city,
                        country,
                        -- address_line2, state, postal_code left NULL for now
                        is_active,
                        created_at
                     ) VALUES (
                        :o,
                        :code,
                        :name,
                        :phone,
                        :email,
                        :addr1,
                        :city,
                        :country,
                        :a,
                        NOW()
                     )"
                )->execute([
                    ':o'       => $orgId,
                    ':code'    => $code,
                    ':name'    => $name,
                    ':phone'   => $phone ?: null,
                    ':email'   => $email ?: null,
                    ':addr1'   => $addr  ?: null,
                    ':city'    => $city ?: null,
                    ':country' => $country ?: null,
                    ':a'       => $active,
                ]);

                // optionally you can store notes into a separate table later
            }

            header('Location: '.$c['module_base'].'/customers');
        } catch (\Throwable $e) {
            $this->oops('Customers store failed', $e);
        }
    }

    /* ------------------------------- edit --------------------------------- */

    /** GET /customers/{id}/edit */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $cust = $orgId > 0
                ? $this->row(
                    "SELECT *
                       FROM pos_customers
                      WHERE org_id = :o AND id = :i",
                    [':o' => $orgId, ':i' => (int)$id]
                )
                : null;

            if (!$cust) {
                http_response_code(404);
                echo 'Not found';
                return;
            }

            $this->view(
                $c['module_dir'].'/Views/customers/edit.php',
                [
                    'title' => 'Edit Customer',
                    'ctx'   => $c,
                    'base'  => $c['module_base'],
                    'cust'  => $cust,
                ],
                'shell'
            );
        } catch (\Throwable $e) {
            $this->oops('Customers edit failed', $e);
        }
    }

    /** POST /customers/{id} */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $in = fn(string $k, string $d = ''): string =>
                trim((string)($_POST[$k] ?? $d));

            $code    = $in('code');
            $name    = $in('name');
            $phone   = $in('phone');
            $email   = $in('email');
            $addr    = $in('address');
            $city    = $in('city');
            $country = $in('country');
            $notes   = $in('notes');
            $active  = isset($_POST['is_active']) ? 1 : 0;

            $errors = [];
            if ($name === '')    $errors['name']    = 'Name is required';
            if ($phone === '')   $errors['phone']   = 'Phone is required';
            if ($city === '')    $errors['city']    = 'City is required';
            if ($country === '') $errors['country'] = 'Country is required';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email address';
            }

            if ($errors) {
                $_SESSION['pos_errors'] = $errors;
                $_SESSION['pos_old']    = $_POST;
                header('Location: '.$c['module_base'].'/customers/'.(int)$id.'/edit');
                return;
            }

            if ($orgId > 0) {
                if ($code === '') {
                    $code = $this->nextCode($orgId);
                }

                // enforce unique code (excluding self)
                $exists = $this->row(
                    "SELECT id
                       FROM pos_customers
                      WHERE org_id = :o AND code = :x AND id <> :i
                      LIMIT 1",
                    [
                        ':o' => $orgId,
                        ':x' => $code,
                        ':i' => (int)$id,
                    ]
                );
                if ($exists) {
                    $_SESSION['pos_errors'] = ['code' => 'Code already exists'];
                    $_SESSION['pos_old']    = $_POST;
                    header('Location: '.$c['module_base'].'/customers/'.(int)$id.'/edit');
                    return;
                }

                $this->pdo()->prepare(
                    "UPDATE pos_customers SET
                        code          = :code,
                        name          = :name,
                        phone         = :phone,
                        email         = :email,
                        address_line1 = :addr1,
                        city          = :city,
                        country       = :country,
                        is_active     = :a,
                        updated_at    = NOW()
                     WHERE org_id = :o AND id = :i"
                )->execute([
                    ':code'    => $code,
                    ':name'    => $name,
                    ':phone'   => $phone ?: null,
                    ':email'   => $email ?: null,
                    ':addr1'   => $addr  ?: null,
                    ':city'    => $city ?: null,
                    ':country' => $country ?: null,
                    ':a'       => $active,
                    ':o'       => $orgId,
                    ':i'       => (int)$id,
                ]);

                // (notes could be handled via a separate table later)
            }

            header('Location: '.$c['module_base'].'/customers');
        } catch (\Throwable $e) {
            $this->oops('Customers update failed', $e);
        }
    }

    /* -------------------------------- show -------------------------------- */

    /** GET /customers/{id} */
    public function show(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ctxOk($ctx);
            $orgId = (int)($c['org_id'] ?? 0);

            $cust = $orgId > 0
                ? $this->row(
                    "SELECT *
                       FROM pos_customers
                      WHERE org_id = :o AND id = :i",
                    [':o' => $orgId, ':i' => (int)$id]
                )
                : null;

            if (!$cust) {
                http_response_code(404);
                echo 'Not found';
                return;
            }

            // Optional stats: orders, spend, rewards etc.
            $stats = [
                'orders'   => 0,
                'spent'    => 0.0,
                'rewards'  => 0.0,
            ];

            try {
                // orders + spend from pos_sales if table exists
                $o = $this->row(
                    "SELECT
                        COUNT(*)                       AS n,
                        COALESCE(SUM(grand_total),0)  AS t
                     FROM pos_sales
                     WHERE org_id = :o AND customer_id = :i",
                    [':o' => $orgId, ':i' => (int)$id]
                );
                if ($o) {
                    $stats['orders'] = (int)($o['n'] ?? 0);
                    $stats['spent']  = (float)($o['t'] ?? 0);
                }

                // rewards balance (if ledger table present)
                $r = $this->row(
                    "SELECT COALESCE(SUM(points),0) p
                       FROM pos_customer_rewards_ledger
                      WHERE org_id = :o AND customer_id = :i",
                    [':o' => $orgId, ':i' => (int)$id]
                );
                if ($r) {
                    $stats['rewards'] = (float)($r['p'] ?? 0);
                }
            } catch (\Throwable $ignore) {
                // If any of these tables/cols are missing, we just skip stats.
            }

            $this->view(
                $c['module_dir'].'/Views/customers/show.php',
                [
                    'title' => 'Customer Details',
                    'ctx'   => $c,
                    'base'  => $c['module_base'],
                    'cust'  => $cust,
                    'stats' => $stats,
                ],
                'shell'
            );
        } catch (\Throwable $e) {
            $this->oops('Customers show failed', $e);
        }
    }
}