<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;
use Throwable;

final class ExpensesController extends BaseController
{
    /** LIST: GET /expenses */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $base  = $this->moduleBase($ctx);

        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to   = (string)($_GET['to']   ?? date('Y-m-d'));
        $q    = trim((string)($_GET['q'] ?? ''));
        $bank = (int)($_GET['bank_account_id'] ?? 0);

        $sql = "
          SELECT
            e.id, e.expense_no, e.expense_date, e.amount, e.payee, e.memo,
            a.code AS acc_code, a.name AS acc_name,
            b.account_name AS bank_name
          FROM dms_expenses e
          LEFT JOIN dms_gl_accounts a ON a.id=e.category_id AND a.org_id=e.org_id
          LEFT JOIN dms_bank_accounts b ON b.id=e.bank_account_id AND b.org_id=e.org_id
          WHERE e.org_id=? AND e.expense_date BETWEEN ? AND ?
        ";
        $params = [$orgId, $from, $to];
        if ($bank > 0) { $sql .= " AND e.bank_account_id=?"; $params[] = $bank; }
        if ($q !== '') {
            $like = "%$q%";
            $sql .= " AND (e.payee LIKE ? OR e.memo LIKE ? OR a.name LIKE ? OR a.code LIKE ?)";
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY e.expense_date DESC, e.id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $banks = $this->banksForOrg($pdo, $orgId);

        $this->view('expenses/index', [
            'title'       => 'Expenses',
            'rows'        => $rows,
            'banks'       => $banks,
            'from'        => $from,
            'to'          => $to,
            'q'           => $q,
            'module_base' => $base,
            'active'      => 'accounts',
            'subactive'   => 'accounts.bank',
        ], $ctx);
    }

    /** FORM: GET /expenses/create */
/** FORM: GET /expenses/create */
public function create(array $ctx): void
{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);
    $base  = $this->moduleBase($ctx);

    // (optional) make sure there are expense accounts to pick
    if (method_exists($this, 'ensureExpenseCoaSeed')) {
        $this->ensureExpenseCoaSeed($pdo, $orgId);
    }
    // (optional) gently auto-link if there’s an obvious single bank-like GL
    if (method_exists($this, 'maybeAutoLinkBankGl')) {
        $this->maybeAutoLinkBankGl($pdo, $orgId);
    }

    $accounts      = $this->banksForOrg($pdo, $orgId);   // id, account_name, account_no, gl_account_id, is_master
    $coaExpense    = $this->expenseCoa($pdo, $orgId);    // expense-like only
    $bankGlOptions = $this->bankLikeCoa($pdo, $orgId);   // asset/cash/bank/current asset

    // If no bank-like GL found, still show an empty select — user can fix COA later.
    // (If you prefer: provide a broader fallback list.)

    $this->view('expenses/create', [
        'title'           => 'Record Expense',
        'accounts'        => $accounts,
        'coa'             => $coaExpense,

        // match the view’s variable name
        'bankGlChoices'   => $bankGlOptions,
        // keep snake_case too in case the view changes later
        'bank_gl_options' => $bankGlOptions,

        'today'           => date('Y-m-d'),
        'module_base'     => $base,
        'active'          => 'accounts',
        'subactive'       => 'accounts.bank',
    ], $ctx);
}


/** CREATE: POST /expenses */
public function store(array $ctx): void
{
    $pdo   = $this->pdo();
    $orgId = $this->orgId($ctx);
    $base  = $this->moduleBase($ctx);

    $expenseNo  = trim((string)($_POST['ref_no']       ?? ''));
    $expenseDt  = trim((string)($_POST['trans_date']   ?? ''));
    $bankId     = (int)($_POST['bank_account_id']      ?? 0);
    $catId      = (int)($_POST['category_id']          ?? 0); // EXPENSE category
    $amount     = (float)($_POST['amount']             ?? 0);
    $payee      = trim((string)($_POST['payee']        ?? ''));
    $memo       = trim((string)($_POST['note']         ?? ''));

    // Accept both possible field names from the view
    $linkGlId   = (int)($_POST['link_gl_account_id']   ?? ($_POST['fix_bank_gl_id'] ?? 0));

    // If user selected a GL to link, persist it first so validation passes
    if ($bankId > 0 && $linkGlId > 0 && method_exists($this, 'linkBankGl')) {
        $this->linkBankGl($pdo, $orgId, $bankId, $linkGlId);
    }

    // Validate
    $errors = [];
    if (!$this->isYmd($expenseDt)) $errors[] = 'Invalid date.';
    if ($bankId <= 0)              $errors[] = 'Select a bank account.';
    if ($catId  <= 0)              $errors[] = 'Select an expense category (COA).';
    if ($amount <= 0)              $errors[] = 'Amount must be greater than zero.';

    // Re-check the bank GL link after any auto-link above
    $bankGL = $this->bankGlId($pdo, $orgId, $bankId);
    if ($bankGL <= 0) {
        $errors[] = 'Selected bank is not linked to a GL account. Choose a GL in “Link GL Account” or edit the bank.';
    }

    if ($errors) {
        $_SESSION['flash_errors'] = $errors;
        $_SESSION['form_old']     = $_POST;
        header('Location: '.$base.'/expenses/create');
        return;
    }

    // Auto-number (EXP-YYYY-00001)
    if ($expenseNo === '') {
        $y = date('Y', strtotime($expenseDt));
        $s = $pdo->prepare("SELECT expense_no FROM dms_expenses WHERE org_id=? AND expense_no LIKE ? ORDER BY id DESC LIMIT 1");
        $s->execute([$orgId, "EXP-$y-%"]);
        $last = (string)($s->fetchColumn() ?: '');
        $seq  = 0;
        if (preg_match('/^EXP-\d{4}-(\d{5})$/', $last, $m)) $seq = (int)$m[1];
        $expenseNo = sprintf('EXP-%s-%05d', $y, $seq+1);
    }

    try {
        $pdo->beginTransaction();

        // 1) expense row
        $ins = $pdo->prepare("
            INSERT INTO dms_expenses
              (org_id, expense_no, expense_date, category_id, amount, payee, memo, bank_account_id, created_at)
            VALUES (?,?,?,?,?,?,?,?, NOW())
        ");
        $ins->execute([
            $orgId, $expenseNo, $expenseDt, $catId, $amount,
            ($payee !== '' ? $payee : null),
            ($memo  !== '' ? $memo  : null),
            $bankId
        ]);
        $expId = (int)$pdo->lastInsertId();

        // 2) journal header
        $jno  = $this->nextJournalNo($pdo, $orgId);
        $jins = $pdo->prepare("
            INSERT INTO dms_gl_journals (org_id, jno, jdate, jtype, memo, ref_table, ref_id)
            VALUES (?,?,?,?,?,?,?)
        ");
        $jins->execute([$orgId, $jno, $expenseDt, 'GENERAL', 'Expense: '.$expenseNo, 'dms_expenses', $expId]);
        $jid = (int)$pdo->lastInsertId();

        // 3) journal lines: DR expense / CR bank
        $elin = $pdo->prepare("
            INSERT INTO dms_gl_entries (org_id, journal_id, account_id, dr, cr, memo)
            VALUES (?,?,?,?,?,?)
        ");
        $elin->execute([$orgId, $jid, $catId,  $amount, 0, $memo ?: '']); // DR expense
        $elin->execute([$orgId, $jid, $bankGL, 0, $amount, $memo ?: '']); // CR bank

        // 4) update running bank balance (optional)
        if ($this->hasColumn($pdo, 'dms_bank_accounts', 'current_balance')) {
            $pdo->prepare("
                UPDATE dms_bank_accounts
                   SET current_balance = COALESCE(current_balance,0) - ?
                 WHERE org_id=? AND id=?
            ")->execute([$amount, $orgId, $bankId]);
        }

        $pdo->commit();
        $_SESSION['flash_success'] = "Expense saved ($expenseNo).";
        header('Location: '.$base.'/expenses');
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_errors'] = ['Failed to save expense: '.$e->getMessage()];
        $_SESSION['form_old']     = $_POST;
        header('Location: '.$base.'/expenses/create');
    }
}
    
        /* ===================== helpers ===================== */

/** Bank accounts for the org (minimal columns for forms/filters) */
private function banksForOrg(PDO $pdo, int $orgId): array
{
    $s = $pdo->prepare("
        SELECT id, account_name, account_no, gl_account_id, is_master
        FROM dms_bank_accounts
        WHERE org_id=?
        ORDER BY is_master DESC, account_name
    ");
    $s->execute([$orgId]);
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** GL accounts suitable to link to a bank (cash/bank/asset-ish) */
private function bankLikeCoa(PDO $pdo, int $orgId): array
{
    $s = $pdo->prepare("
        SELECT id, code, name, type
        FROM dms_gl_accounts
        WHERE org_id=?
          AND (
                LOWER(type) IN ('asset','assets','current asset','current assets','cash','bank','cash at bank','cash & bank')
             OR LOWER(name) LIKE '%bank%'
             OR LOWER(name) LIKE '%cash%'
          )
        ORDER BY code, name
    ");
    $s->execute([$orgId]);
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Expense-like COA (debit side). Includes COGS as a safety net. */
private function expenseCoa(PDO $pdo, int $orgId): array
{
    $s = $pdo->prepare("
        SELECT id, code, name, type
        FROM dms_gl_accounts
        WHERE org_id=? AND LOWER(type) IN (
            'expense','expenses','operating expense','operating expenses',
            'cogs','cost','cost of sales'
        )
        ORDER BY code, name
    ");
    $s->execute([$orgId]);
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Seed a minimal set of expense accounts if none exist (optional) */
private function ensureExpenseCoaSeed(PDO $pdo, int $orgId): void
{
    $chk = $pdo->prepare("
        SELECT COUNT(*) FROM dms_gl_accounts
        WHERE org_id=? AND LOWER(type) IN (
            'expense','expenses','operating expense','operating expenses','cogs','cost','cost of sales'
        )
    ");
    $chk->execute([$orgId]);
    if ((int)$chk->fetchColumn() > 0) return;

    $ins = $pdo->prepare("
        INSERT INTO dms_gl_accounts(org_id, code, name, type, created_at)
        VALUES (?,?,?,?, NOW())
    ");
    $seed = [
        ['6100','Office Supplies','expense'],
        ['6200','Utilities','expense'],
        ['6300','Rent Expense','expense'],
        ['6400','Travel & Meals','expense'],
        ['6500','Marketing','expense'],
        ['6600','Maintenance','expense'],
        ['6700','Bank Charges','expense'],
        ['7000','Cost of Sales','cogs'],
    ];
    foreach ($seed as [$code,$name,$type]) {
        $ins->execute([$orgId,$code,$name,$type]);
    }
}

/**
 * Try to auto-link bank -> GL if gl_account_id is empty:
 *  - If exactly one bank-like GL exists, use it for all unlinked banks.
 *  - Else attempt a soft name match (non-destructive).
 */
private function maybeAutoLinkBankGl(PDO $pdo, int $orgId): void
{
    // find unlinked banks
    $b = $pdo->prepare("
        SELECT id, account_name
        FROM dms_bank_accounts
        WHERE org_id=? AND COALESCE(gl_account_id,0)=0
    ");
    $b->execute([$orgId]);
    $banks = $b->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$banks) return;

    // candidate GLs
    $ga = $this->bankLikeCoa($pdo, $orgId);
    if (!$ga) return;

    if (count($ga) === 1) {
        $gid = (int)$ga[0]['id'];
        $u = $pdo->prepare("
            UPDATE dms_bank_accounts
            SET gl_account_id=?
            WHERE org_id=? AND COALESCE(gl_account_id,0)=0
        ");
        $u->execute([$gid, $orgId]);
        return;
    }

    // fuzzy one-by-one
    $u = $pdo->prepare("
        UPDATE dms_bank_accounts
        SET gl_account_id=?
        WHERE org_id=? AND id=? AND COALESCE(gl_account_id,0)=0
    ");
    foreach ($banks as $bank) {
        $bn = strtolower((string)$bank['account_name']);
        $matchId = 0;
        foreach ($ga as $g) {
            $gn = strtolower((string)($g['name'].' '.$g['code']));
            if ($bn !== '' && (str_contains($gn,$bn) || str_contains($bn,strtolower((string)$g['name'])))) {
                $matchId = (int)$g['id'];
                break;
            }
        }
        if ($matchId > 0) $u->execute([$matchId,$orgId,(int)$bank['id']]);
    }
}

/** Persist a GL link to a specific bank (used when user picks “Link GL account”) */
private function linkBankGl(PDO $pdo, int $orgId, int $bankId, int $glId): void
{
    if (!$this->hasColumn($pdo,'dms_bank_accounts','gl_account_id')) return;
    $u = $pdo->prepare("UPDATE dms_bank_accounts SET gl_account_id=? WHERE org_id=? AND id=?");
    $u->execute([$glId,$orgId,$bankId]);
}

/** Resolve the GL account id linked to a bank account */
private function bankGlId(PDO $pdo, int $orgId, int $bankId): int
{
    $s = $pdo->prepare("SELECT COALESCE(gl_account_id,0) FROM dms_bank_accounts WHERE org_id=? AND id=?");
    $s->execute([$orgId,$bankId]);
    return (int)($s->fetchColumn() ?: 0);
}

/** Map GL ids -> account codes (for optional legacy mirrors) */
private function codesByIds(PDO $pdo, int $orgId, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval',$ids)));
    if (!$ids) return [];
    $in     = implode(',', array_fill(0,count($ids),'?'));
    $params = array_merge([$orgId], $ids);
    $s = $pdo->prepare("SELECT id, code FROM dms_gl_accounts WHERE org_id=? AND id IN ($in)");
    $s->execute($params);
    $out = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
        $out[(int)$r['id']] = (string)$r['code'];
    }
    return $out;
}

/** Generate J-YYYY-xxxxx journal number */
private function nextJournalNo(PDO $pdo, int $orgId): string
{
    $y = date('Y');
    $s = $pdo->prepare("
        SELECT jno FROM dms_gl_journals
        WHERE org_id=? AND jno LIKE CONCAT('J-', ?, '-%')
        ORDER BY id DESC LIMIT 1
    ");
    $s->execute([$orgId,$y]);
    $last = (string)($s->fetchColumn() ?: '');
    $seq  = 0;
    if (preg_match('/^J-\d{4}-(\d{5})$/',$last,$m)) $seq = (int)$m[1];
    return sprintf('J-%s-%05d',$y,$seq+1);
}

/** Schema helpers */
private function hasTable(PDO $pdo, string $t): bool
{
    $q = $pdo->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? LIMIT 1
    ");
    $q->execute([$t]);
    return (bool)$q->fetchColumn();
}

private function hasColumn(PDO $pdo, string $table, string $col): bool
{
    $q = $pdo->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1
    ");
    $q->execute([$table,$col]);
    return (bool)$q->fetchColumn();
}

/** Strict YYYY-MM-DD validator */
private function isYmd(string $d): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) return false;
    [$y,$m,$day] = array_map('intval', explode('-',$d));
    return checkdate($m,$day,$y);
}
}
    