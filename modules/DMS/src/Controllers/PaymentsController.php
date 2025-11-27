<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class PaymentsController extends BaseController
{
    /** GET /payments */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));

        if ($q !== '') {
            $st = $pdo->prepare("
                SELECT id, pay_no, pay_date, counterparty, method, amount, bank_account_id
                FROM dms_payments
                WHERE org_id = ? AND (pay_no LIKE ?)
                ORDER BY id DESC
                LIMIT 200
            ");
            $st->execute([$orgId, "%{$q}%"]);
        } else {
            $st = $pdo->prepare("
                SELECT id, pay_no, pay_date, counterparty, method, amount, bank_account_id
                FROM dms_payments
                WHERE org_id = ?
                ORDER BY id DESC
                LIMIT 200
            ");
            $st->execute([$orgId]);
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('payments/index', [
            'title'     => 'Payments',
            'rows'      => $rows,
            'q'         => $q,
            'active'    => 'accounts',
            'subactive' => 'payments.index',
        ], $ctx);
    }

    /** GET /payments/create */
    public function create(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $st = $pdo->prepare("
            SELECT id, bank_name, account_no, is_master, status
            FROM dms_bank_accounts
            WHERE org_id=? AND (status IS NULL OR status='active')
            ORDER BY is_master DESC, bank_name ASC
        ");
        $st->execute([$orgId]);
        $bankAccounts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('payments/create', [
            'title'          => 'Record Payment',
            'bank_accounts'  => $bankAccounts,
            'active'         => 'accounts',
            'subactive'      => 'accounts.cash',
        ], $ctx);
    }

    /** POST /payments */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $pay_no       = trim((string)($_POST['pay_no'] ?? ''));
        $pay_date     = (string)($_POST['pay_date'] ?? date('Y-m-d'));
        $counterparty = (string)($_POST['counterparty'] ?? 'customer'); // 'customer'|'supplier'|'other'
        $customer_id  = (int)($_POST['customer_id'] ?? 0) ?: null;
        $supplier_id  = (int)($_POST['supplier_id'] ?? 0) ?: null;      // dealer removed
        $method       = (string)($_POST['method'] ?? 'cash');
        $bank_id      = (int)($_POST['bank_account_id'] ?? 0) ?: null;
        $amount       = (float)($_POST['amount'] ?? 0);
        $notes        = trim((string)($_POST['notes'] ?? ''));

        if ($pay_no === '') {
            $year = date('Y'); $prefix = "PMT-$year-";
            $st = $pdo->prepare("SELECT pay_no FROM dms_payments WHERE org_id=? AND pay_no LIKE ? ORDER BY id DESC LIMIT 1");
            $st->execute([$orgId, "$prefix%"]);
            $last = (string)$st->fetchColumn();
            $seq  = 0;
            if ($last && preg_match('/^PMT-'.$year.'-(\d+)$/', $last, $m)) $seq = (int)$m[1];
            $pay_no = $prefix . str_pad((string)($seq+1), 5, '0', STR_PAD_LEFT);
        }

        $pdo->beginTransaction();
        try {
            // Insert payment (supplier-only)
            $h = $pdo->prepare("
                INSERT INTO dms_payments
                  (org_id, pay_no, pay_date, counterparty, customer_id, supplier_id, method, bank_account_id, amount, notes, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?, NOW())
            ");
            $h->execute([$orgId,$pay_no,$pay_date,$counterparty,$customer_id,$supplier_id,$method,$bank_id,$amount,$notes]);

            // Resolve GLs + auto-link bank gl if missing
            [$bankGL, $cpGL] = $this->resolvePaymentGlAndAutoLink($pdo, $orgId, $bank_id, $counterparty, $customer_id, $supplier_id);

            // Create journal (DR bank / CR counterparty)
            if ($bankGL && $amount > 0) {
                $jno  = $this->nextJournalNo($pdo, $orgId);
                $memo = trim("Payment $pay_no" . ($notes !== '' ? " — $notes" : ''));

                $pdo->prepare("INSERT INTO dms_gl_journals (org_id, jno, jdate, jtype, memo, created_at) VALUES (?,?,?,?,?,NOW())")
                    ->execute([$orgId,$jno,$pay_date,'payment',$memo]);
                $jid = (int)$pdo->lastInsertId();

                $elin = $pdo->prepare("INSERT INTO dms_gl_entries (org_id, journal_id, account_id, dr, cr, memo) VALUES (?,?,?,?,?,?)");
                $elin->execute([$orgId,$jid,$bankGL,$amount,0,$memo]); // DR bank
                $elin->execute([$orgId,$jid,$cpGL,0,$amount,$memo]);   // CR cp (will be Suspense if no cp picked)
            }

            // Update bank current balance
            if ($bank_id && $amount > 0 && $this->hasColumnSafe($pdo, 'dms_bank_accounts', 'current_balance')) {
                $pdo->prepare("UPDATE dms_bank_accounts SET current_balance = COALESCE(current_balance,0) + ? WHERE org_id=? AND id=?")
                    ->execute([$amount, $orgId, $bank_id]);
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx).'/payments');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->abort500($e);
        }
    }

    /** GET /payments/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $s = $pdo->prepare("SELECT * FROM dms_payments WHERE org_id=? AND id=?");
        $s->execute([$orgId, $id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) $this->abort404('Payment not found.');

        $alloc = [];
        if ($this->hasTable($pdo, 'dms_payment_allocations')) {
            $a = $pdo->prepare("
                SELECT pa.*, i.invoice_no
                FROM dms_payment_allocations pa
                LEFT JOIN dms_invoices i
                  ON i.org_id=pa.org_id AND i.id=pa.invoice_id
                WHERE pa.org_id=? AND pa.payment_id=?
                ORDER BY pa.id
            ");
            $a->execute([$orgId, $id]);
            $alloc = $a->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $this->view('payments/show', [
            'title'     => 'Payment '.$row['pay_no'],
            'p'         => $row,
            'alloc'     => $alloc,
            'active'    => 'accounts',
            'subactive' => 'payments.index',
        ], $ctx);
    }

    /** GET /payments/{id}/edit */
    public function edit(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $s = $pdo->prepare("SELECT * FROM dms_payments WHERE org_id=? AND id=?");
        $s->execute([$orgId, $id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) $this->abort404('Payment not found.');

        $ba = [];
        if ($this->hasTable($pdo, 'dms_bank_accounts')) {
            $q = $pdo->prepare("SELECT id, bank_name, account_no, is_master FROM dms_bank_accounts WHERE org_id=? ORDER BY is_master DESC, bank_name");
            $q->execute([$orgId]);
            $ba = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $this->view('payments/edit', [
            'title'         => 'Edit Payment',
            'p'             => $row,
            'bank_accounts' => $ba,
            'active'        => 'accounts',
            'subactive'     => 'payments.index',
        ], $ctx);
    }

    /** POST /payments/{id} */
    public function update(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        // Load old row
        $ps = $pdo->prepare("SELECT * FROM dms_payments WHERE org_id=? AND id=?");
        $ps->execute([$orgId, $id]);
        $prev = $ps->fetch(PDO::FETCH_ASSOC);
        if (!$prev) $this->abort404('Payment not found.');

        $pay_no       = trim((string)($_POST['pay_no'] ?? (string)$prev['pay_no']));
        $pay_date     = (string)($_POST['pay_date'] ?? (string)$prev['pay_date']);
        $counterparty = (string)($_POST['counterparty'] ?? (string)$prev['counterparty']);
        $customer_id  = (int)($_POST['customer_id'] ?? (int)($prev['customer_id'] ?? 0)) ?: null;
        $supplier_id  = (int)($_POST['supplier_id'] ?? (int)($prev['supplier_id'] ?? 0)) ?: null;
        $method       = (string)($_POST['method'] ?? (string)$prev['method']);
        $bank_id      = (int)($_POST['bank_account_id'] ?? (int)($prev['bank_account_id'] ?? 0)) ?: null;
        $amount       = (float)($_POST['amount'] ?? (float)$prev['amount']);
        $notes        = trim((string)($_POST['notes'] ?? (string)($prev['notes'] ?? '')));

        // Update row
        $u = $pdo->prepare("
            UPDATE dms_payments
            SET pay_no=?, pay_date=?, counterparty=?, customer_id=?, supplier_id=?,
                method=?, bank_account_id=?, amount=?, notes=?, updated_at=NOW()
            WHERE org_id=? AND id=?
        ");
        $u->execute([$pay_no,$pay_date,$counterparty,$customer_id,$supplier_id,$method,$bank_id,$amount,$notes,$orgId,$id]);

        // Resolve GLs + auto-link bank gl if missing
        [$bankGL, $cpGL] = $this->resolvePaymentGlAndAutoLink($pdo, $orgId, $bank_id, $counterparty, $customer_id, $supplier_id);

        // Find journal by memo prefix
        $jid = (int)($pdo->prepare("SELECT id FROM dms_gl_journals WHERE org_id=? AND jtype='payment' AND memo LIKE ? ORDER BY id DESC LIMIT 1")
            ->execute([$orgId, "Payment ".$pay_no."%"]) ?: 0);
        $jst = $pdo->prepare("SELECT id FROM dms_gl_journals WHERE org_id=? AND jtype='payment' AND memo LIKE ? ORDER BY id DESC LIMIT 1");
        $jst->execute([$orgId, "Payment ".$pay_no."%"]);
        $jid = (int)($jst->fetchColumn() ?: 0);

        // Compute delta for bank balance
        $oldBank = (int)($prev['bank_account_id'] ?? 0) ?: null;
        $oldAmt  = (float)($prev['amount'] ?? 0);

        $pdo->beginTransaction();
        try {
            if ($jid) {
                // Rebuild entries + update header
                $pdo->prepare("DELETE FROM dms_gl_entries WHERE org_id=? AND journal_id=?")->execute([$orgId,$jid]);
                $pdo->prepare("UPDATE dms_gl_journals SET jdate=?, memo=?, updated_at=NOW() WHERE org_id=? AND id=?")
                    ->execute([$pay_date, trim("Payment $pay_no" . ($notes !== '' ? " — $notes" : '')), $orgId, $jid]);
            } else {
                // Create new header if missing
                $jno = $this->nextJournalNo($pdo, $orgId);
                $pdo->prepare("INSERT INTO dms_gl_journals (org_id, jno, jdate, jtype, memo, created_at) VALUES (?,?,?,?,?,NOW())")
                    ->execute([$orgId,$jno,$pay_date,'payment',trim("Payment $pay_no" . ($notes !== '' ? " — $notes" : ''))]);
                $jid = (int)$pdo->lastInsertId();
            }

            // Recreate entries (never skip; cpGL falls back to Suspense)
            if ($bankGL && $amount > 0) {
                $elin = $pdo->prepare("INSERT INTO dms_gl_entries (org_id, journal_id, account_id, dr, cr, memo) VALUES (?,?,?,?,?,?)");
                $memo = "Payment $pay_no";
                $elin->execute([$orgId,$jid,$bankGL,$amount,0,$memo]); // DR bank
                $elin->execute([$orgId,$jid,$cpGL,0,$amount,$memo]);   // CR cp
            }

            // Adjust bank balances if the bank or amount changed
            if ($this->hasColumnSafe($pdo, 'dms_bank_accounts', 'current_balance')) {
                if ($oldBank && $oldAmt > 0) {
                    $pdo->prepare("UPDATE dms_bank_accounts SET current_balance = COALESCE(current_balance,0) - ? WHERE org_id=? AND id=?")
                        ->execute([$oldAmt, $orgId, $oldBank]);
                }
                if ($bank_id && $amount > 0) {
                    $pdo->prepare("UPDATE dms_bank_accounts SET current_balance = COALESCE(current_balance,0) + ? WHERE org_id=? AND id=?")
                        ->execute([$amount, $orgId, $bank_id]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->abort500($e);
        }

        $this->redirect($this->moduleBase($ctx).'/payments/'.$id);
    }

    /* ===================== helpers ===================== */

    private function hasTable(PDO $pdo, string $t): bool
    {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
        $st->execute([$t]);
        return ((int)$st->fetchColumn()) > 0;
    }

    private function hasColumnSafe(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $st->execute([$table, $column]);
        return ((int)$st->fetchColumn()) > 0;
    }

    private function nextJournalNo(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = "J-$year-";
        $st = $pdo->prepare("SELECT jno FROM dms_gl_journals WHERE org_id=? AND jno LIKE ? ORDER BY id DESC LIMIT 1");
        $st->execute([$orgId, "$prefix%"]);
        $last = (string)($st->fetchColumn() ?: '');
        $seq  = 0;
        if ($last && preg_match('/^J-'.$year.'-(\d{5})$/', $last, $m)) $seq = (int)$m[1];
        return $prefix . str_pad((string)($seq+1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Returns [bankGL, cpGL]; auto-links the bank's gl_account_id if missing.
     * Counterparty GL is resolved by explicit mapping, then by type/name, then Suspense.
     */
    private function resolvePaymentGlAndAutoLink(PDO $pdo, int $orgId, ?int $bankId, string $counterparty, ?int $customerId, ?int $supplierId): array
    {
        // BANK GL (prefer bank.gl_account_id)
        $bankGL = 0;
        if ($bankId) {
            $g = $pdo->prepare("SELECT gl_account_id FROM dms_bank_accounts WHERE org_id=? AND id=?");
            $g->execute([$orgId, $bankId]);
            $bankGL = (int)$g->fetchColumn();
        }
        if (!$bankGL) {
            // try by type first, then by name
            $q = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(type) IN ('bank','cash at bank','bank account') ORDER BY code LIMIT 1");
            $q->execute([$orgId]); $bankGL = (int)($q->fetchColumn() ?: 0);
            if (!$bankGL) {
                $q = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(name) REGEXP '(bank|cash)' ORDER BY code LIMIT 1");
                $q->execute([$orgId]); $bankGL = (int)($q->fetchColumn() ?: 0);
            }
            // persist mapping so Bank Account screens can join GL correctly
            if ($bankId && $bankGL && $this->hasColumnSafe($pdo,'dms_bank_accounts','gl_account_id')) {
                $pdo->prepare("UPDATE dms_bank_accounts SET gl_account_id=? WHERE org_id=? AND id=? AND (gl_account_id IS NULL OR gl_account_id=0)")
                    ->execute([$bankGL,$orgId,$bankId]);
            }
        }

        // COUNTERPARTY GL
        $cpGL = 0;
        if ($counterparty === 'customer' && $customerId) {
            $s = $pdo->prepare("SELECT gl_account_id FROM dms_customers WHERE org_id=? AND id=?");
            $s->execute([$orgId, $customerId]);
            $cpGL = (int)$s->fetchColumn();
        } elseif ($counterparty === 'supplier' && $supplierId) {
            $s = $pdo->prepare("SELECT gl_account_id FROM dms_suppliers WHERE org_id=? AND id=?");
            $s->execute([$orgId, $supplierId]);
            $cpGL = (int)$s->fetchColumn();
        }

        if (!$cpGL) {
            if ($counterparty === 'customer') {
                $s = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(type) LIKE 'accounts receivable%' ORDER BY code LIMIT 1");
                $s->execute([$orgId]); $cpGL = (int)($s->fetchColumn() ?: 0);
                if (!$cpGL) {
                    $s = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(name) REGEXP '(receivable|debtors)' ORDER BY code LIMIT 1");
                    $s->execute([$orgId]); $cpGL = (int)($s->fetchColumn() ?: 0);
                }
            } elseif ($counterparty === 'supplier') {
                $s = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(type) LIKE 'accounts payable%' ORDER BY code LIMIT 1");
                $s->execute([$orgId]); $cpGL = (int)($s->fetchColumn() ?: 0);
                if (!$cpGL) {
                    $s = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(name) REGEXP '(payable|creditors)' ORDER BY code LIMIT 1");
                    $s->execute([$orgId]); $cpGL = (int)($s->fetchColumn() ?: 0);
                }
            }
        }
        if (!$cpGL) {
            $cpGL = $this->ensureSuspenseGl($pdo, $orgId); // final fallback so we never skip posting
        }

        return [$bankGL, $cpGL];
    }

    /** Ensure a clearing/suspense GL exists as a fallback. */
    private function ensureSuspenseGl(PDO $pdo, int $orgId): int
    {
        $q = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(type) IN ('clearing','suspense','other current liability') ORDER BY code LIMIT 1");
        $q->execute([$orgId]); $id = (int)($q->fetchColumn() ?: 0);
        if ($id) return $id;

        $q = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(name) REGEXP '(suspense|clearing)' ORDER BY code LIMIT 1");
        $q->execute([$orgId]); $id = (int)($q->fetchColumn() ?: 0);
        if ($id) return $id;

        $q = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(type) IN ('liability','equity','other current liability') ORDER BY code LIMIT 1");
        $q->execute([$orgId]); return (int)($q->fetchColumn() ?: 0);
    }
}