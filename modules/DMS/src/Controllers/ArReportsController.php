<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class ArReportsController extends BaseController
{
    /* ---------------- small helpers ---------------- */

    private function tableExists(PDO $pdo, string $table): bool
    {
        $q = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ");
        $q->execute([$table]);
        return (bool)$q->fetchColumn();
    }

    private function colExists(PDO $pdo, string $table, string $col): bool
    {
        $q = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $q->execute([$table, $col]);
        return (bool)$q->fetchColumn();
    }

    private function kv(PDO $pdo, string $sql, array $args = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_NUM) ?: [];
        $out = [];
        foreach ($rows as $r) { $out[(int)$r[0]] = (string)$r[1]; }
        return $out;
    }

    /**
     * GET /reports/ar-statement
     * Query:
     *   - type = ar|ap   (default: ar)
     *   - party_id | customer_id | supplier_id
     *   - party_name | customer_name | supplier_name
     *   - from (Y-m-d), to (Y-m-d)
     */
    public function statement(array $ctx): void
    {
        $pdo         = $this->pdo();
        $orgId       = $this->orgId($ctx);
        $module_base = $this->moduleBase($ctx);

        /* ------------ filters ------------ */
        $type = strtolower(trim((string)($_GET['type'] ?? 'ar')));
        if (!in_array($type, ['ar','ap'], true)) $type = 'ar';
        $isAR = ($type === 'ar');

        $partyId   = (int)($_GET['party_id'] ?? $_GET['customer_id'] ?? $_GET['supplier_id'] ?? 0);
        $partyName = trim((string)($_GET['party_name'] ?? $_GET['customer_name'] ?? $_GET['supplier_name'] ?? ''));
        $from      = (string)($_GET['from'] ?? date('Y-m-01'));
        $to        = (string)($_GET['to']   ?? date('Y-m-d'));
        if ($from > $to) { [$from, $to] = [$to, $from]; }

        /* ------------ tables/columns ------------ */
        $ledger     = $isAR ? 'dms_ar_ledger' : 'dms_ap_ledger';
        $partyCol   = $isAR ? 'customer_id'   : 'supplier_id';
        $partyTable = $isAR ? 'dms_customers' : 'dms_stakeholders';
        $partyExtra = $isAR ? ''              : "AND type='supplier'";

        /* ------------ fail fast if no ledger ------------ */
        if (!$this->tableExists($pdo, $ledger)) {
            $this->view('reports/ar_statement', [
                'title'         => ($isAR ? 'Customer' : 'Supplier').' Statement',
                'type'          => $type,
                'module_base'   => $module_base,
                'customer_id'   => $partyId,
                'customer_name' => $partyName ?: ($isAR ? 'All Customers' : 'All Suppliers'),
                'from'          => $from,
                'to'            => $to,
                'opening'       => 0.0,
                'closing'       => 0.0,
                'total_debit'   => 0.0,
                'total_credit'  => 0.0,
                'lines'         => [],
            ], $ctx);
            return;
        }

        /* ------------ party name lookup (optional table) ------------ */
        if ($partyId > 0 && $partyName === '' && $this->tableExists($pdo, $partyTable)) {
            try {
                $st = $pdo->prepare("SELECT name FROM {$partyTable} WHERE org_id=? AND id=? {$partyExtra}");
                $st->execute([$orgId, $partyId]);
                $nm = $st->fetchColumn();
                if ($nm) $partyName = (string)$nm;
            } catch (\Throwable $e) { /* ignore */ }
        }

        $dateCol = $this->colExists($pdo, $ledger, 'txn_date')
            ? 'txn_date'
            : ($this->colExists($pdo, $ledger, 'date') ? 'date' : 'txn_date');

        /* ------------ opening ------------ */
        $opening = 0.0;
        try {
            $sqlOpen  = "SELECT COALESCE(SUM(debit - credit),0)
                         FROM {$ledger}
                         WHERE org_id=? AND {$dateCol} < ?";
            $argsOpen = [$orgId, $from];
            if ($partyId > 0) { $sqlOpen .= " AND {$partyCol}=?"; $argsOpen[] = $partyId; }
            $st = $pdo->prepare($sqlOpen);
            $st->execute($argsOpen);
            $opening = (float)$st->fetchColumn();
        } catch (\Throwable $e) {
            $opening = 0.0;
        }

        /* ------------ movements ------------ */
        $rows = [];
        try {
            $sql  = "SELECT id, {$dateCol} AS txn_date, source_type, source_id,
                            COALESCE(debit,0)  AS debit,
                            COALESCE(credit,0) AS credit,
                            COALESCE(note,'')  AS note,
                            COALESCE(sale_id, purchase_id, 0) AS ref_id
                     FROM {$ledger}
                     WHERE org_id=? AND {$dateCol} BETWEEN ? AND ?";
            $args = [$orgId, $from, $to];
            if ($partyId > 0) { $sql .= " AND {$partyCol}=?"; $args[] = $partyId; }
            $sql .= " ORDER BY {$dateCol}, id";

            $st = $pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        /* ------------ optional document numbers (safe, prepared) ------------ */
        $mapDoc   = [];   // invoices/bills
        $mapPay   = [];   // receipts/payments

        try {
            if ($isAR) {
                if ($this->tableExists($pdo, 'dms_sales') && $this->colExists($pdo, 'dms_sales','sale_no')) {
                    $mapDoc = $this->kv($pdo, "SELECT id, sale_no FROM dms_sales WHERE org_id=?", [$orgId]);
                }
                if ($this->tableExists($pdo, 'dms_receipts') && $this->colExists($pdo, 'dms_receipts','receipt_no')) {
                    $mapPay = $this->kv($pdo, "SELECT id, receipt_no FROM dms_receipts WHERE org_id=?", [$orgId]);
                }
            } else { // AP
                if ($this->tableExists($pdo, 'dms_purchases') && $this->colExists($pdo, 'dms_purchases','purchase_no')) {
                    $mapDoc = $this->kv($pdo, "SELECT id, purchase_no FROM dms_purchases WHERE org_id=?", [$orgId]);
                }
                if ($this->tableExists($pdo, 'dms_payments') && $this->colExists($pdo, 'dms_payments','payment_no')) {
                    $mapPay = $this->kv($pdo, "SELECT id, payment_no FROM dms_payments WHERE org_id=?", [$orgId]);
                }
            }
        } catch (\Throwable $e) { /* optional */ }

        /* ------------ build lines + running balance ------------ */
        $running = $opening;
        $lines   = [];

        foreach ($rows as $r) {
            $debit  = (float)$r['debit'];
            $credit = (float)$r['credit'];
            $running += ($debit - $credit);

            $src  = strtolower((string)$r['source_type']);
            $memo = trim((string)$r['note']);

            if ($memo === '') {
                if ($isAR && $src === 'sale') {
                    $memo = 'Invoice ' . ($mapDoc[(int)$r['ref_id']] ?? '#'.$r['ref_id']);
                } elseif (!$isAR && $src === 'purchase') {
                    $memo = 'Bill ' . ($mapDoc[(int)$r['ref_id']] ?? '#'.$r['ref_id']);
                } elseif (($isAR && $src === 'receipt') || (!$isAR && $src === 'payment')) {
                    $memo = ucfirst($src) . ' ' . ($mapPay[(int)$r['source_id']] ?? '#'.$r['source_id']);
                } elseif ($src === 'opening') {
                    $memo = 'Opening adjustment';
                } else {
                    $memo = ucfirst($src ?: 'Entry') . ' #' . (string)$r['id'];
                }
            }

            $lines[] = [
                'date'    => substr((string)$r['txn_date'], 0, 10),
                'memo'    => $memo,
                'debit'   => $debit,
                'credit'  => $credit,
                'balance' => $running,
            ];
        }

        $totalDebit  = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));
        $closing     = $opening + $totalDebit - $totalCredit;

        /* ------------ render ------------ */
        $this->view('reports/ar_statement', [
            'title'         => ($isAR ? 'Customer' : 'Supplier').' Statement',
            'type'          => $type,
            'module_base'   => $module_base,

            // keep legacy names so your existing view params work
            'customer_id'   => $partyId,
            'customer_name' => ($partyName !== '' ? $partyName : ($isAR ? 'All Customers' : 'All Suppliers')),

            'from'          => $from,
            'to'            => $to,
            'opening'       => $opening,
            'closing'       => $closing,
            'total_debit'   => $totalDebit,
            'total_credit'  => $totalCredit,
            'lines'         => $lines,
        ], $ctx);
    }

    /** GET /reports/ar-statement/print */
    public function statementPrint(array $ctx): void
    {
        // Reuse statement data; your print view is the same HTML with print CSS
        $_GET['print'] = '1';
        $this->statement($ctx);
    }
}