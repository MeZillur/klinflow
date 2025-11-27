<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

final class ReportsController extends BaseController
{
    /**
     * GET /customers/statement?customer_id=...&from=YYYY-MM-DD&to=YYYY-MM-DD
     * No schema changes. Uses dms_sales + dms_receipts + dms_receipt_items.
     */
    public function customerStatement(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $customerId = (int)($_GET['customer_id'] ?? 0);
        $dateFrom   = (string)($_GET['from'] ?? '');
        $dateTo     = (string)($_GET['to']   ?? '');

        // sensible defaults: current month if not provided
        if ($dateFrom === '' || $dateTo === '') {
            $dateFrom = date('Y-m-01');
            $dateTo   = date('Y-m-t');
        }

        // Load customer (for header)
        $cust = null;
        if ($customerId > 0) {
            $c = $pdo->prepare("SELECT id, name, phone, email, address FROM dms_customers WHERE org_id=? AND id=?");
            $c->execute([$orgId, $customerId]);
            $cust = $c->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        // ---------- OPENING BALANCE (before :from) ----------
        // Debits = invoices
        $qDeb = $pdo->prepare("
            SELECT COALESCE(SUM(s.grand_total),0) AS amt
            FROM dms_sales s
            WHERE s.org_id=? AND s.customer_id<=>? AND s.sale_date < ?
        ");
        $qDeb->execute([$orgId, $customerId ?: null, $dateFrom]);
        $openingDeb = (float)$qDeb->fetchColumn();

        // Credits = receipts applied to any sale for this customer, OR on-account receipts (receipt with no items but same customer)
        $qCred = $pdo->prepare("
            SELECT
              COALESCE((
                SELECT SUM(ri.amount_applied)
                FROM dms_receipt_items ri
                JOIN dms_receipts r ON r.id=ri.receipt_id AND r.org_id=ri.org_id
                WHERE ri.org_id=? AND r.customer_id<=>? AND r.receipt_date < ?
              ),0)
              +
              COALESCE((
                SELECT SUM(r.total_amount)
                FROM dms_receipts r
                LEFT JOIN dms_receipt_items ri ON ri.org_id=r.org_id AND ri.receipt_id=r.id
                WHERE r.org_id=? AND r.customer_id<=>? AND r.receipt_date < ? AND ri.id IS NULL
              ),0) AS amt
        ");
        $qCred->execute([$orgId,$customerId?:null,$dateFrom,$orgId,$customerId?:null,$dateFrom]);
        $openingCred = (float)$qCred->fetchColumn();

        $openingBalance = $openingDeb - $openingCred; // positive = customer owes

        // ---------- PERIOD ROWS (between :from and :to inclusive) ----------
        // Invoices as debits
        $inv = $pdo->prepare("
            SELECT s.id AS ref_id, s.sale_no AS ref_no, s.sale_date AS txn_date,
                   s.grand_total AS debit, 0.00 AS credit, 'invoice' AS kind
            FROM dms_sales s
            WHERE s.org_id=? AND s.customer_id<=>? AND s.sale_date BETWEEN ? AND ?
            ORDER BY s.sale_date, s.id
        ");
        $inv->execute([$orgId, $customerId?:null, $dateFrom, $dateTo]);
        $invoices = $inv->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Receipts (applied)
        $rc1 = $pdo->prepare("
            SELECT r.id AS ref_id, r.receipt_no AS ref_no, r.receipt_date AS txn_date,
                   0.00 AS debit, ri.amount_applied AS credit, 'receipt' AS kind
            FROM dms_receipt_items ri
            JOIN dms_receipts r ON r.id=ri.receipt_id AND r.org_id=ri.org_id
            WHERE ri.org_id=? AND r.customer_id<=>? AND r.receipt_date BETWEEN ? AND ?
            ORDER BY r.receipt_date, r.id, ri.id
        ");
        $rc1->execute([$orgId, $customerId?:null, $dateFrom, $dateTo]);
        $rcApplied = $rc1->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Receipts (on-account) = receipts with no items
        $rc2 = $pdo->prepare("
            SELECT r.id AS ref_id, r.receipt_no AS ref_no, r.receipt_date AS txn_date,
                   0.00 AS debit, r.total_amount AS credit, 'receipt_on_account' AS kind
            FROM dms_receipts r
            LEFT JOIN dms_receipt_items ri ON ri.org_id=r.org_id AND ri.receipt_id=r.id
            WHERE r.org_id=? AND r.customer_id<=>? AND r.receipt_date BETWEEN ? AND ? AND ri.id IS NULL
            ORDER BY r.receipt_date, r.id
        ");
        $rc2->execute([$orgId, $customerId?:null, $dateFrom, $dateTo]);
        $rcOnAcct = $rc2->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Merge & sort by date then id
        $rows = array_merge($invoices, $rcApplied, $rcOnAcct);
        usort($rows, static function($a,$b){
            if ($a['txn_date'] === $b['txn_date']) return $a['ref_id'] <=> $b['ref_id'];
            return strcmp($a['txn_date'], $b['txn_date']);
        });

        // Running balance
        $running = $openingBalance;
        foreach ($rows as &$r) {
            $running += (float)$r['debit'] - (float)$r['credit'];
            $r['balance'] = $running;
        }
        unset($r);

        $this->view('reports/customer_statement', [
            'title'           => 'Customer Statement',
            'customer'        => $cust,
            'customer_id'     => $customerId,
            'date_from'       => $dateFrom,
            'date_to'         => $dateTo,
            'opening_balance' => $openingBalance,
            'rows'            => $rows,
            'closing_balance' => $running,
        ], $ctx);
    }

    /**
     * Optional: print-friendly (PDF/print) view — reuses same query but different template.
     */
    public function customerStatementPdf(array $ctx): void
    {
        $_GET['print'] = '1';
        $this->customerStatement($ctx);
    }
}