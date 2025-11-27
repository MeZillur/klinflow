<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use PDO;
use Throwable;

final class BillingController extends BaseController
{
    /* ------------- schema helpers ------------- */

    private function colExists(PDO $pdo, string $table, string $column): bool {
        try {
            $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");
            $st->execute([':t'=>$table, ':c'=>$column]);
            return (bool)$st->fetchColumn();
        } catch (Throwable) { return false; }
    }
    private function tableExists(PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=:t LIMIT 1");
            $st->execute([':t'=>$table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable) { return false; }
    }
    private function tryFetchAll(PDO $pdo, string $sql, array $b=[]): array {
        try { $s=$pdo->prepare($sql); $s->execute($b); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable) { return []; }
    }
    private function tryFetchOne(PDO $pdo, string $sql, array $b=[]): ?array {
        try { $s=$pdo->prepare($sql); $s->execute($b); $row=$s->fetch(PDO::FETCH_ASSOC); return $row?:null; }
        catch (Throwable) { return null; }
    }
    
    /* ---------- org brand path helpers ---------- */
private function orgBrandWebDir(int $orgId): string {
    // Web path used in <img src="...">
    return "/public/uploads/hotelflow/brand/org/{$orgId}";
}
private function orgBrandAbsDir(int $orgId): string {
    // Absolute filesystem dir (DOCUMENT_ROOT fallback-safe)
    $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? getcwd(), '/');
    return $root . $this->orgBrandWebDir($orgId);
}
private function ensureDir(string $absDir): void {
    if (!is_dir($absDir)) @mkdir($absDir, 0775, true);
}

    /* ------------- branding settings (per org) ------------- */
    private function loadBranding(PDO $pdo, int $orgId): array
{
    // Preferred: from settings table
    if ($this->tableExists($pdo, 'hms_billing_settings')) {
        $row = $this->tryFetchOne($pdo,
            "SELECT logo_path, org_name, org_address, org_phone, org_web, org_email, invoice_footer, print_size
             FROM hms_billing_settings WHERE org_id=:o LIMIT 1", [':o'=>$orgId]);
        if ($row) {
            $row['print_size'] = in_array(($row['print_size']??'A4'), ['A4','A5','POS'], true) ? $row['print_size'] : 'A4';

            // If path empty but a file exists in the org brand folder, prefer that
            if (empty($row['logo_path'])) {
                $guess = $this->orgBrandWebDir($orgId) . '/logo.png';
                $abs   = $this->orgBrandAbsDir($orgId) . '/logo.png';
                if (is_file($abs)) $row['logo_path'] = $guess;
            }
            return $row;
        }
    }

    // Fallbacks
    $guess = $this->orgBrandWebDir($orgId) . '/logo.png';
    $abs   = $this->orgBrandAbsDir($orgId) . '/logo.png';
    return [
        'logo_path'      => (is_file($abs) ? $guess : '/public/assets/brand/logo.png'),
        'org_name'       => (string)(($_SESSION['tenant_org']['name'] ?? 'Your Hotel')),
        'org_address'    => (string)(($_SESSION['tenant_org']['address'] ?? '')),
        'org_phone'      => (string)(($_SESSION['tenant_org']['phone'] ?? '')),
        'org_web'        => (string)(($_SESSION['tenant_org']['website'] ?? '')),
        'org_email'      => (string)(($_SESSION['tenant_org']['email'] ?? '')),
        'invoice_footer' => 'Thank you for your business.',
        'print_size'     => 'A4',
    ];
}

    private function saveBranding(PDO $pdo, int $orgId, array $data): void
    {
        if (!$this->tableExists($pdo, 'hms_billing_settings')) {
            // no table: silently no-op (schema-safe); you can create it later
            return;
        }
        // upsert (MySQL)
        $sql = "INSERT INTO hms_billing_settings
                    (org_id, logo_path, org_name, org_address, org_phone, org_web, org_email, invoice_footer, print_size)
                VALUES (:o,:logo,:name,:addr,:phone,:web,:email,:foot,:size)
                ON DUPLICATE KEY UPDATE
                    logo_path=VALUES(logo_path), org_name=VALUES(org_name), org_address=VALUES(org_address),
                    org_phone=VALUES(org_phone), org_web=VALUES(org_web), org_email=VALUES(org_email),
                    invoice_footer=VALUES(invoice_footer), print_size=VALUES(print_size)";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':o'=>$orgId,
            ':logo'=>(string)$data['logo_path'],
            ':name'=>(string)$data['org_name'],
            ':addr'=>(string)$data['org_address'],
            ':phone'=>(string)$data['org_phone'],
            ':web'=>(string)$data['org_web'],
            ':email'=>(string)$data['org_email'],
            ':foot'=>(string)$data['invoice_footer'],
            ':size'=>in_array($data['print_size'], ['A4','A5','POS'], true) ? $data['print_size'] : 'A4',
        ]);
    }

    /* ------------- Pages ------------- */

    public function folios(array $ctx): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];

        // Accept either hms_folios or hms_reservation_folios
        $rows = [];
        if ($this->tableExists($pdo,'hms_folios')) {
            $guestExpr = $this->colExists($pdo,'hms_folios','guest_name') ? 'f.guest_name' :
                         ($this->colExists($pdo,'hms_folios','guest_id') && $this->tableExists($pdo,'hms_guests') && $this->colExists($pdo,'hms_guests','name')
                            ? 'COALESCE(g.name, "")' : "''");
            $joinG = (strpos($guestExpr,'g.')!==false) ? "LEFT JOIN hms_guests g ON g.id=f.guest_id" : "";
            $rows = $this->tryFetchAll($pdo, "
                SELECT f.id,
                       ".($this->colExists($pdo,'hms_folios','folio_no')?'f.folio_no':'f.id')." AS folio_no,
                       {$guestExpr} AS guest_name,
                       ".($this->colExists($pdo,'hms_folios','status')?'f.status':"'open'")." AS status,
                       ".($this->colExists($pdo,'hms_folios','balance')?'f.balance':'0')." AS balance,
                       ".($this->colExists($pdo,'hms_folios','currency')?'f.currency':"'USD'")." AS currency
                FROM hms_folios f
                {$joinG}
                WHERE f.org_id=:o
                ORDER BY f.id DESC
                LIMIT 200", [':o'=>$orgId]);
        } elseif ($this->tableExists($pdo,'hms_reservation_folios')) {
            $rows = $this->tryFetchAll($pdo, "
                SELECT rf.id, rf.reservation_id AS folio_no,
                       ".($this->colExists($pdo,'hms_reservation_folios','status')?'rf.status':"'open'")." AS status,
                       ".($this->colExists($pdo,'hms_reservation_folios','balance')?'rf.balance':'0')." AS balance,
                       'USD' AS currency
                FROM hms_reservation_folios rf
                WHERE rf.org_id=:o
                ORDER BY rf.id DESC
                LIMIT 200", [':o'=>$orgId]);
        }

        $this->view('billing/folios/index', [
            'title'=>'Folios','rows'=>$rows,
        ], $c);
    }

    public function folioShow(array $ctx, int $id): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        $folio=null; $lines=[];

        if ($this->tableExists($pdo,'hms_folios')) {
            $folio = $this->tryFetchOne($pdo,"
                SELECT f.* FROM hms_folios f WHERE f.org_id=:o AND f.id=:id LIMIT 1",
                [':o'=>$orgId, ':id'=>$id]);
            $lines = $this->tryFetchAll($pdo,"
                SELECT l.id,
                       ".($this->colExists($pdo,'hms_folio_lines','post_date')?'l.post_date':'NULL')." AS post_date,
                       ".($this->colExists($pdo,'hms_folio_lines','code')?'l.code':"''")." AS code,
                       ".($this->colExists($pdo,'hms_folio_lines','description')?'l.description':"''")." AS description,
                       ".($this->colExists($pdo,'hms_folio_lines','amount')?'l.amount':'0')." AS amount,
                       ".($this->colExists($pdo,'hms_folio_lines','tax_amount')?'l.tax_amount':'0')." AS tax_amount
                FROM hms_folio_lines l
                WHERE l.org_id=:o AND l.folio_id=:id
                ORDER BY l.id ASC", [':o'=>$orgId, ':id'=>$id]);
        } elseif ($this->tableExists($pdo,'hms_reservation_folios')) {
            $folio = $this->tryFetchOne($pdo,"
                SELECT rf.* FROM hms_reservation_folios rf WHERE rf.org_id=:o AND rf.id=:id LIMIT 1",
                [':o'=>$orgId, ':id'=>$id]);
            $lines = $this->tryFetchAll($pdo,"
                SELECT id, NULL AS post_date, description, amount, 0 AS tax_amount, '' AS code
                FROM hms_reservation_folio_lines
                WHERE org_id=:o AND folio_id=:id ORDER BY id ASC", [':o'=>$orgId, ':id'=>$id]);
        }

        if (!$folio) { $this->notFound('Folio not found.'); return; }

        $branding = $this->loadBranding($pdo, $orgId);

        $this->view('billing/folios/show', [
            'title'=>'Folio #'.(int)$id,
            'folio'=>$folio,
            'lines'=>$lines,
            'branding'=>$branding,
        ], $c);
    }

    public function payments(array $ctx): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        // Pick any available payments table
        if ($this->tableExists($pdo,'hms_payments')) {
            $rows = $this->tryFetchAll($pdo,"
                SELECT id,
                       ".($this->colExists($pdo,'hms_payments','created_at')?'created_at':'NULL')." AS created_at,
                       ".($this->colExists($pdo,'hms_payments','amount')?'amount':'0')." AS amount,
                       ".($this->colExists($pdo,'hms_payments','currency')?'currency':"'USD'")." AS currency,
                       ".($this->colExists($pdo,'hms_payments','method_name')?'method_name':"''")." AS method_name,
                       ".($this->colExists($pdo,'hms_payments','note')?'note':"''")." AS note
                FROM hms_payments WHERE org_id=:o ORDER BY id DESC LIMIT 200", [':o'=>$orgId]);
        } else {
            $rows = $this->tryFetchAll($pdo,"
                SELECT id, created_at, amount, currency, method_id AS method_name, note
                FROM hms_reservation_payments WHERE org_id=:o ORDER BY id DESC LIMIT 200", [':o'=>$orgId]);
        }

        $this->view('billing/payments/index', ['title'=>'Payments','rows'=>$rows], $c);
    }

    public function invoices(array $ctx): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];

        // Try generic hms_invoices; fallback to charges grouped per reservation as a pseudo-invoice
        if ($this->tableExists($pdo,'hms_invoices')) {
            $rows = $this->tryFetchAll($pdo,"
                SELECT i.id,
                       ".($this->colExists($pdo,'hms_invoices','invoice_no')?'i.invoice_no':'i.id')." AS invoice_no,
                       ".($this->colExists($pdo,'hms_invoices','issued_at')?'i.issued_at':'NULL')." AS issued_at,
                       ".($this->colExists($pdo,'hms_invoices','total')?'i.total':'0')." AS total,
                       ".($this->colExists($pdo,'hms_invoices','currency')?'i.currency':"'USD'")." AS currency,
                       ".($this->colExists($pdo,'hms_invoices','status')?'i.status':"'open'")." AS status
                FROM hms_invoices i WHERE i.org_id=:o
                ORDER BY i.id DESC LIMIT 200", [':o'=>$orgId]);
        } else {
            $rows = $this->tryFetchAll($pdo,"
                SELECT r.id AS id, CONCAT('R-',r.id) AS invoice_no, NULL AS issued_at,
                       COALESCE(SUM(c.amount),0) AS total, 'USD' AS currency, 'open' AS status
                FROM hms_reservations r
                LEFT JOIN hms_reservation_charges c ON c.reservation_id=r.id AND c.org_id=r.org_id
                WHERE r.org_id=:o
                GROUP BY r.id
                ORDER BY r.id DESC LIMIT 200", [':o'=>$orgId]);
        }
        $this->view('billing/invoices/index', ['title'=>'Invoices','rows'=>$rows], $c);
    }

    public function invoiceShow(array $ctx, int $id): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        $branding = $this->loadBranding($pdo, $orgId);

        if ($this->tableExists($pdo,'hms_invoices')) {
            $inv = $this->tryFetchOne($pdo,"SELECT * FROM hms_invoices WHERE org_id=:o AND id=:id LIMIT 1",
                                      [':o'=>$orgId, ':id'=>$id]);
            if (!$inv) { $this->notFound('Invoice not found.'); return; }
            $lines = $this->tryFetchAll($pdo,"
                SELECT id,
                       ".($this->colExists($pdo,'hms_invoice_lines','description')?'description':"''")." AS description,
                       ".($this->colExists($pdo,'hms_invoice_lines','qty')?'qty':'1')." AS qty,
                       ".($this->colExists($pdo,'hms_invoice_lines','unit_price')?'unit_price':'0')." AS unit_price,
                       ".($this->colExists($pdo,'hms_invoice_lines','tax_amount')?'tax_amount':'0')." AS tax_amount
                FROM hms_invoice_lines WHERE org_id=:o AND invoice_id=:id ORDER BY id ASC",
                [':o'=>$orgId, ':id'=>$id]);
            $this->view('billing/invoices/show', ['title'=>'Invoice #'.$id,'invoice'=>$inv,'lines'=>$lines,'branding'=>$branding], $c);
            return;
        }

        // fallback: synthesize invoice from reservation charges
        $res = $this->tryFetchOne($pdo,"SELECT * FROM hms_reservations WHERE org_id=:o AND id=:id LIMIT 1",
                                  [':o'=>$orgId, ':id'=>$id]);
        if (!$res) { $this->notFound('Invoice not found.'); return; }
        $lines = $this->tryFetchAll($pdo,"
            SELECT id, ".($this->colExists($pdo,'hms_reservation_charges','description')?'description':"''")." AS description,
                   1 AS qty,
                   ".($this->colExists($pdo,'hms_reservation_charges','amount')?'amount':'0')." AS unit_price,
                   ".($this->colExists($pdo,'hms_reservation_charges','tax_amount')?'tax_amount':'0')." AS tax_amount
            FROM hms_reservation_charges WHERE org_id=:o AND reservation_id=:id ORDER BY id ASC",
            [':o'=>$orgId, ':id'=>$id]);
        $invoice = [
            'id'=>$id,
            'invoice_no'=>'R-'.$id,
            'issued_at'=>null,
            'total'=>array_sum(array_map(fn($x)=>(float)$x['unit_price']+(float)$x['tax_amount'], $lines)),
            'currency'=>'USD',
            'status'=>'open',
            'bill_to'=>$res['guest_name'] ?? '',
        ];
        $this->view('billing/invoices/show', ['title'=>'Invoice #'.$id,'invoice'=>$invoice,'lines'=>$lines,'branding'=>$branding], $c);
    }

    public function invoicePrint(array $ctx, int $id): void
    {
        // Simply reuse invoiceShow view but with print skin (size from branding)
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        $branding = $this->loadBranding($pdo, $orgId);

        // Try to read invoice via the same logic, then pass to dedicated print view
        $inv = null; $lines=[];
        if ($this->tableExists($pdo,'hms_invoices')) {
            $inv = $this->tryFetchOne($pdo,"SELECT * FROM hms_invoices WHERE org_id=:o AND id=:id LIMIT 1",
                                      [':o'=>$orgId, ':id'=>$id]);
            $lines = $this->tryFetchAll($pdo,"
                SELECT id, description, qty, unit_price, tax_amount
                FROM hms_invoice_lines WHERE org_id=:o AND invoice_id=:id ORDER BY id ASC",
                [':o'=>$orgId, ':id'=>$id]);
        } else {
            $res = $this->tryFetchOne($pdo,"SELECT * FROM hms_reservations WHERE org_id=:o AND id=:id LIMIT 1",
                                      [':o'=>$orgId, ':id'=>$id]);
            if ($res) {
                $inv = [
                    'id'=>$id,'invoice_no'=>'R-'.$id,'issued_at'=>null,'total'=>0,'currency'=>'USD','status'=>'open',
                    'bill_to'=>$res['guest_name'] ?? ''
                ];
                $lines = $this->tryFetchAll($pdo,"
                    SELECT id, ".($this->colExists($pdo,'hms_reservation_charges','description')?'description':"''")." AS description,
                           1 AS qty,
                           ".($this->colExists($pdo,'hms_reservation_charges','amount')?'amount':'0')." AS unit_price,
                           ".($this->colExists($pdo,'hms_reservation_charges','tax_amount')?'tax_amount':'0')." AS tax_amount
                    FROM hms_reservation_charges WHERE org_id=:o AND reservation_id=:id ORDER BY id ASC",
                    [':o'=>$orgId, ':id'=>$id]);
            }
        }
        if (!$inv) { $this->notFound('Invoice not found.'); return; }

        $this->view('billing/invoices/print', [
            'title'=>'Invoice Print',
            'invoice'=>$inv,
            'lines'=>$lines,
            'branding'=>$branding, // contains print_size
        ], $c);
    }

    public function creditNotes(array $ctx): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        if ($this->tableExists($pdo,'hms_credit_notes')) {
            $rows = $this->tryFetchAll($pdo,"
                SELECT id,
                       ".($this->colExists($pdo,'hms_credit_notes','note_no')?'note_no':'id')." AS note_no,
                       ".($this->colExists($pdo,'hms_credit_notes','issued_at')?'issued_at':'NULL')." AS issued_at,
                       ".($this->colExists($pdo,'hms_credit_notes','amount')?'amount':'0')." AS amount,
                       ".($this->colExists($pdo,'hms_credit_notes','currency')?'currency':"'USD'")." AS currency,
                       ".($this->colExists($pdo,'hms_credit_notes','reason')?'reason':"''")." AS reason
                FROM hms_credit_notes WHERE org_id=:o ORDER BY id DESC LIMIT 200", [':o'=>$orgId]);
        } else {
            $rows = [];
        }
        $this->view('billing/credit-notes/index', ['title'=>'Credit Notes','rows'=>$rows], $c);
    }

    public function cityLedger(array $ctx): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];

        // Either a dedicated city ledger table, or AR balances per company
        if ($this->tableExists($pdo,'hms_city_ledger')) {
            $rows = $this->tryFetchAll($pdo,"
                SELECT id,
                       ".($this->colExists($pdo,'hms_city_ledger','company_name')?'company_name':"CONCAT('Company #',id)")." AS company_name,
                       ".($this->colExists($pdo,'hms_city_ledger','balance')?'balance':'0')." AS balance,
                       ".($this->colExists($pdo,'hms_city_ledger','currency')?'currency':"'USD'")." AS currency
                FROM hms_city_ledger WHERE org_id=:o ORDER BY id DESC LIMIT 200", [':o'=>$orgId]);
        } else {
            $rows = $this->tryFetchAll($pdo,"
                SELECT c.id, c.name AS company_name, COALESCE(SUM(i.total - i.paid_amount),0) AS balance, 'USD' AS currency
                FROM hms_companies c
                LEFT JOIN hms_invoices i ON i.company_id=c.id AND i.org_id=c.org_id
                WHERE c.org_id=:o
                GROUP BY c.id
                ORDER BY balance DESC
                LIMIT 200", [':o'=>$orgId]);
        }

        $this->view('billing/city-ledger/index', ['title'=>'City Ledger','rows'=>$rows], $c);
    }

    /* -------- Settings (branding + print size) -------- */
    public function settings(array $ctx): void
    {
        $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];
        $branding = $this->loadBranding($pdo, $orgId);
        $this->view('billing/settings', ['title'=>'Billing Settings','branding'=>$branding], $c);
    }

    public function settingsSave(array $ctx): void
{
    $c=$this->ctx($ctx); $pdo=$this->pdo(); $orgId=(int)$c['org_id'];

    // Current path (hidden field) can persist if no upload
    $logoPath = trim((string)($_POST['logo_path'] ?? ''));

    // Normalize/prepare org directory
    $webDir = $this->orgBrandWebDir($orgId);
    $absDir = $this->orgBrandAbsDir($orgId);
    $this->ensureDir($absDir);

    // Handle optional logo upload into /public/uploads/hotelflow/brand/org/{orgId}/
    if (!empty($_FILES['logo_file']['tmp_name']) && (int)($_FILES['logo_file']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'] ?? 'png', PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','svg'], true)) {
            // Use a deterministic filename to avoid clutter; keep old filenames if you prefer
            $fname = 'logo.' . $ext; // e.g. logo.png / logo.svg
            $dest  = $absDir . '/' . $fname;

            // Remove previous logo.* to avoid stale files
            foreach (glob($absDir.'/logo.*') ?: [] as $old) { @unlink($old); }

            if (@move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                // Public web path
                $logoPath = $webDir . '/' . $fname;
            }
        }
    }

    // If nothing set yet and a default file exists, point to it
    if ($logoPath === '') {
        foreach (['png','jpg','jpeg','svg'] as $e) {
            $p = $absDir.'/logo.'.$e;
            if (is_file($p)) { $logoPath = $webDir.'/logo.'.$e; break; }
        }
    }
    if ($logoPath === '') {
        $logoPath = '/public/assets/brand/logo.png';
    }

    $data = [
        'logo_path'      => $logoPath,
        'org_name'       => (string)($_POST['org_name'] ?? ''),
        'org_address'    => (string)($_POST['org_address'] ?? ''),
        'org_phone'      => (string)($_POST['org_phone'] ?? ''),
        'org_web'        => (string)($_POST['org_web'] ?? ''),
        'org_email'      => (string)($_POST['org_email'] ?? ''),
        'invoice_footer' => (string)($_POST['invoice_footer'] ?? ''),
        'print_size'     => (string)($_POST['print_size'] ?? 'A4'),
    ];

    try {
        $this->saveBranding($pdo, $orgId, $data);
        $this->redirect($c['module_base'].'/billing/settings', $c);
    } catch (\Throwable $e) {
        $this->abort500('Failed to save settings: '.$e->getMessage());
    }
}
}