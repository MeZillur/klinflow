<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class CustomersController extends BaseController
{
    /** GET /customers */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));

        if ($q !== '') {
            $st = $pdo->prepare("
                SELECT id, code, name, phone, email, address, balance
                FROM dms_customers
                WHERE org_id = ? AND (name LIKE ? OR phone LIKE ? OR code LIKE ?)
                ORDER BY name LIMIT 200
            ");
            $like = "%{$q}%";
            $st->execute([$orgId, $like, $like, $like]);
        } else {
            $st = $pdo->prepare("
                SELECT id, code, name, phone, email, address, balance
                FROM dms_customers
                WHERE org_id = ?
                ORDER BY id DESC LIMIT 200
            ");
            $st->execute([$orgId]);
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('customers/index', [
            'title'     => 'Customers',
            'rows'      => $rows,
            'q'         => $q,
            'active'    => 'clients',
            'subactive' => 'clients.index',
        ], $ctx);
    }

    /** GET /customers/create */
    public function create(array $ctx): void
    {
        $this->view('customers/create', [
            'title'     => 'Create Customer',
            'active'    => 'clients',
            'subactive' => 'clients.create',
        ], $ctx);
    }

    /** POST /customers */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $code    = trim((string)($_POST['code'] ?? ''));
        $name    = trim((string)($_POST['name'] ?? ''));
        $phone   = trim((string)($_POST['phone'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($name === '') $this->abort400('Name is required.');

        // Auto-generate code if empty: CID-YYYY-00001
        if ($code === '') {
            $year   = date('Y');
            $prefix = "CID-$year-";
            $st = $pdo->prepare("
                SELECT code FROM dms_customers
                WHERE org_id = ? AND code LIKE ?
                ORDER BY id DESC LIMIT 1
            ");
            $st->execute([$orgId, "$prefix%"]);
            $last = (string)$st->fetchColumn();

            $seq = 0;
            if ($last && preg_match('/^CID-' . $year . '-(\d{1,})$/', $last, $m)) {
                $seq = (int)$m[1];
            }
            $code = $prefix . str_pad((string)($seq + 1), 5, '0', STR_PAD_LEFT);
        }

        $st = $pdo->prepare("
            INSERT INTO dms_customers (org_id, code, name, phone, email, address, balance, created_at)
            VALUES (?,?,?,?,?,?,0.00, NOW())
        ");
        $st->execute([$orgId, $code, $name, $phone ?: null, $email ?: null, $address ?: null]);

        $id = (int)$pdo->lastInsertId();
        $this->redirect($this->moduleBase($ctx).'/customers/'.$id);
    }

    /** GET /customers/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $st = $pdo->prepare("SELECT * FROM dms_customers WHERE org_id=? AND id=?");
        $st->execute([$orgId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) $this->abort404('Customer not found.');

        // Optional summary numbers (if ledger table exists)
        $summary = ['orders'=>0, 'invoices'=>0, 'receivable'=>0.00];
        if ($pdo->query("SHOW TABLES LIKE 'dms_customer_ledger'")->rowCount() > 0) {
            $ls = $pdo->prepare("
                SELECT 
                  COALESCE(SUM(CASE WHEN type='invoice'    THEN amount END),0) as invoices,
                  COALESCE(SUM(CASE WHEN type='payment'    THEN amount END),0) as payments,
                  COALESCE(SUM(CASE WHEN type='adjustment' THEN amount END),0) as adjustments
                FROM dms_customer_ledger
                WHERE org_id=? AND customer_id=?
            ");
            $ls->execute([$orgId, $id]);
            $t = $ls->fetch(PDO::FETCH_ASSOC) ?: [];
            $summary['invoices']    = (float)($t['invoices'] ?? 0);
            $summary['receivable']  = max(0, $summary['invoices'] - (float)($t['payments'] ?? 0) - (float)($t['adjustments'] ?? 0));
        }

        $this->view('customers/show', [
            'title'     => 'Customer: '.($row['name'] ?? ('#'.$id)),
            'customer'  => $row,          // <-- IMPORTANT: match the view
            'summary'   => $summary,
            'active'    => 'clients',
            'subactive' => 'clients.index',
        ], $ctx);
    }

    /** GET /customers/{id}/edit */
    public function edit(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $st = $pdo->prepare("SELECT * FROM dms_customers WHERE org_id=? AND id=?");
        $st->execute([$orgId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) $this->abort404('Customer not found.');

        $this->view('customers/edit', [
            'title'     => 'Edit Customer',
            'customer'  => $row,          // <-- match the form view
            'active'    => 'clients',
            'subactive' => 'clients.index',
        ], $ctx);
    }

    /** POST /customers/{id} */
    public function update(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $code    = trim((string)($_POST['code'] ?? ''));
        $name    = trim((string)($_POST['name'] ?? ''));
        $phone   = trim((string)($_POST['phone'] ?? ''));
        $email   = trim((string)($_POST['email'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($name === '') $this->abort400('Name is required.');

        $st = $pdo->prepare("
            UPDATE dms_customers
            SET code=?, name=?, phone=?, email=?, address=?, updated_at=NOW()
            WHERE org_id=? AND id=?
        ");
        $st->execute([$code ?: null, $name, $phone ?: null, $email ?: null, $address ?: null, $orgId, $id]);

        $this->redirect($this->moduleBase($ctx).'/customers/'.$id);
    }

    /** GET /customers/credit-summary */
    public function creditSummary(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $rows = [];
        if ($pdo->query("SHOW TABLES LIKE 'dms_customer_ledger'")->rowCount() > 0) {
            $st = $pdo->prepare("
                SELECT c.id, c.name,
                       COALESCE(SUM(CASE WHEN l.type='invoice'    THEN l.amount ELSE 0 END),0) AS invoices,
                       COALESCE(SUM(CASE WHEN l.type='payment'    THEN l.amount ELSE 0 END),0) AS payments,
                       COALESCE(SUM(CASE WHEN l.type='adjustment' THEN l.amount ELSE 0 END),0) AS adjustments
                FROM dms_customers c
                LEFT JOIN dms_customer_ledger l
                  ON l.org_id=c.org_id AND l.customer_id=c.id
                WHERE c.org_id=?
                GROUP BY c.id, c.name
                ORDER BY c.name
            ");
            $st->execute([$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $st = $pdo->prepare("SELECT id, name, balance FROM dms_customers WHERE org_id=? ORDER BY name");
            $st->execute([$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $this->view('customers/credit-summary', [
            'title'     => 'Customer Credit Summary',
            'rows'      => $rows,
            'active'    => 'clients',
            'subactive' => 'clients.credit',
        ], $ctx);
    }
}