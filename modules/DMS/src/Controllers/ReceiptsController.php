<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class ReceiptsController extends BaseController
{
    /** GET /receipts */
    public function index(array $ctx): void
    {
        $pdo = $this->pdo();
        $org = $this->orgId($ctx);

        $st = $pdo->prepare("
            SELECT id, receipt_no, receipt_date, customer_name, total_amount
            FROM dms_receipts
            WHERE org_id=?
            ORDER BY id DESC
            LIMIT 100
        ");
        $st->execute([$org]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('receipts/index', [
            'title' => 'Receipts',
            'rows'  => $rows,
        ], $ctx);
    }

    /** GET /receipts/create */
    public function create(array $ctx): void
    {
        $this->view('receipts/create', [
            'title' => 'Record Receipt',
        ], $ctx);
    }

    /** POST /receipts */
    public function store(array $ctx): void
    {
        $pdo = $this->pdo();
        $org = $this->orgId($ctx);

        $receiptNo   = trim((string)($_POST['receipt_no'] ?? ''));
        $receiptDate = (string)($_POST['receipt_date'] ?? date('Y-m-d'));
        $customerId  = (int)($_POST['customer_id'] ?? 0);
        $customerNm  = trim((string)($_POST['customer_name'] ?? ''));
        $payMethod   = trim((string)($_POST['pay_method'] ?? 'cash'));
        $notes       = (string)($_POST['notes'] ?? '');

        // Lines: apply to invoices (optional)
        $lines = is_array($_POST['apply'] ?? null) ? $_POST['apply'] : []; // each: ['sale_id'=>.., 'amount'=>..]

        $total = 0.0;
        $clean = [];
        foreach ($lines as $ln) {
            $sid = (int)($ln['sale_id'] ?? 0);
            $amt = (float)($ln['amount'] ?? 0);
            if ($sid > 0 && $amt > 0) {
                $total += $amt;
                $clean[] = ['sale_id'=>$sid, 'amount'=>$amt];
            }
        }

        // If no lines provided, treat as on-account receipt
        if (!$clean && isset($_POST['on_account_amount'])) {
            $total = max(0.0, (float)$_POST['on_account_amount']);
        }

        if ($receiptNo === '' || $total <= 0) {
            $this->view('receipts/create', [
                'title' => 'Record Receipt',
                'error' => 'Receipt No and a positive amount are required.',
                'old'   => $_POST,
            ], $ctx);
            return;
        }

        $pdo->beginTransaction();
        try {
            $h = $pdo->prepare("
                INSERT INTO dms_receipts
                  (org_id, receipt_no, receipt_date, customer_id, customer_name, pay_method, total_amount, notes)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            $h->execute([$org, $receiptNo, $receiptDate, $customerId ?: null, $customerNm ?: null, $payMethod ?: null, $total, $notes ?: null]);
            $rid = (int)$pdo->lastInsertId();

            if ($clean) {
                $i = $pdo->prepare("
                    INSERT INTO dms_receipt_items (org_id, receipt_id, sale_id, amount_applied)
                    VALUES (?,?,?,?)
                ");
                foreach ($clean as $ln) {
                    $i->execute([$org, $rid, $ln['sale_id'], $ln['amount']]);
                }
            }

            // Post to A/R (credits)
            $pdo->prepare("CALL dms_post_receipt(?, ?)")->execute([$org, $rid]);

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx).'/receipts');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->view('receipts/create', [
                'title' => 'Record Receipt',
                'error' => 'Save failed: '.$e->getMessage(),
                'old'   => $_POST,
            ], $ctx);
        }
    }

    /** GET /receipts/print/{id}?autoprint=1 */
    public function print(array $ctx, int $id): void
    {
        $pdo = $this->pdo();
        $org = $this->orgId($ctx);

        $h = $pdo->prepare("SELECT * FROM dms_receipts WHERE org_id=? AND id=?");
        $h->execute([$org, $id]);
        $rec = $h->fetch(PDO::FETCH_ASSOC);
        if (!$rec) $this->abort404('Receipt not found.');

        $i = $pdo->prepare("
            SELECT ri.*, s.sale_no
            FROM dms_receipt_items ri
            LEFT JOIN dms_sales s ON s.id=ri.sale_id
            WHERE ri.org_id=? AND ri.receipt_id=?
            ORDER BY ri.id
        ");
        $i->execute([$org, $id]);
        $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('receipts/print', [
            'title'   => 'Print Receipt',
            'receipt' => $rec,
            'items'   => $items,
        ], $ctx);
    }
}