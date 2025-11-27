<?php
declare(strict_types=1);

namespace Modules\DMS\Support;

use PDO;
use Throwable;

/**
 * InventoryPoster
 * - Multi-tenant safe (requires org_id on every call)
 * - Transactional (SELECT ... FOR UPDATE on balances)
 * - Weighted moving-average cost on receipts
 * - Auto-detects ledger table: dms_stock_ledger | dms_stock_moves | dms_inventory_moves
 * - Works with or without warehouses (wh_id nullable)
 * - Prevents negative stock unless allowNegative=true
 */
final class InventoryPoster
{
    public function __construct(private PDO $pdo) {}

    /* ----------------------------- Helpers ----------------------------- */

    private function tableExists(string $t): bool {
        $q = $this->pdo->prepare("
            SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1
        ");
        $q->execute([$t]);
        return (bool)$q->fetchColumn();
    }

    private function colExists(string $t, string $c): bool {
        $q = $this->pdo->prepare("
            SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1
        ");
        $q->execute([$t, $c]);
        return (bool)$q->fetchColumn();
    }

    private function pickLedgerTable(): ?string {
        foreach (['dms_stock_ledger','dms_stock_moves','dms_inventory_moves'] as $t) {
            if ($this->tableExists($t)) return $t;
        }
        return null;
    }

    private function ensureBalanceRow(int $orgId, int $productId): array
    {
        // Try to get for update
        $sel = $this->pdo->prepare("
            SELECT qty_on_hand, avg_cost, last_cost
              FROM dms_stock_balances
             WHERE org_id = ? AND product_id = ?
             FOR UPDATE
        ");
        $sel->execute([$orgId, $productId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'qty'  => (float)$row['qty_on_hand'],
                'avg'  => (float)($row['avg_cost'] ?? 0),
                'last' => (float)($row['last_cost'] ?? 0),
            ];
        }

        // Create minimal row if table exists
        if (!$this->tableExists('dms_stock_balances')) {
            throw new \RuntimeException("Missing table dms_stock_balances");
        }
        $ins = $this->pdo->prepare("
            INSERT INTO dms_stock_balances (org_id, product_id, qty_on_hand, avg_cost, last_cost)
            VALUES (?, ?, 0, 0, 0)
        ");
        $ins->execute([$orgId, $productId]);

        return ['qty'=>0.0,'avg'=>0.0,'last'=>0.0];
    }

    private function updateBalance(int $orgId, int $productId, float $newQty, float $newAvg, float $lastCost): void
    {
        $upd = $this->pdo->prepare("
            UPDATE dms_stock_balances
               SET qty_on_hand = ?, avg_cost = ?, last_cost = ?, updated_at = NOW()
             WHERE org_id = ? AND product_id = ?
             LIMIT 1
        ");
        $upd->execute([$newQty, $newAvg, $lastCost, $orgId, $productId]);
    }

    private function writeLedger(
        string $ledger, int $orgId, ?int $whId, int $productId,
        string $txnType, float $qtyIn, float $qtyOut, float $unitCost,
        string $refTable, ?int $refId, ?int $refLineId, string $memo = ''
    ): void {
        if ($ledger === 'dms_stock_ledger') {
            $sql = "
                INSERT INTO dms_stock_ledger
                  (org_id, product_id, wh_id, txn_type, ref_table, ref_id, ref_line_id,
                   qty_in, qty_out, unit_cost, cost_total, memo, created_at, posted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (? * (? - ?)), ?, NOW(), NOW())
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([
                $orgId, $productId, $whId, $txnType, $refTable, $refId, $refLineId,
                $qtyIn, $qtyOut, $unitCost, $unitCost, $qtyIn, $qtyOut, $memo
            ]);
            return;
        }

        if ($ledger === 'dms_stock_moves') {
            $sql = "
                INSERT INTO dms_stock_moves
                  (org_id, product_id, wh_id, qty_in, qty_out, unit_cost, ref_table, ref_id, ref_line_id, memo, move_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([$orgId,$productId,$whId,$qtyIn,$qtyOut,$unitCost,$refTable,$refId,$refLineId,$memo]);
            return;
        }

        // dms_inventory_moves (generic)
        if ($ledger === 'dms_inventory_moves') {
            $sql = "
                INSERT INTO dms_inventory_moves
                  (org_id, product_id, move_type, in_qty, out_qty, unit_cost, note, ref_table, ref_id, ref_line_id,
                   created_at, updated_at, posted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
            ";
            $st = $this->pdo->prepare($sql);
            $st->execute([$orgId,$productId,$txnType,$qtyIn,$qtyOut,$unitCost,$memo,$refTable,$refId,$refLineId]);
            return;
        }
    }

    /* ----------------------------- Core ops ----------------------------- */

    /**
     * Post a receipt (qty increases, avg cost recalculated).
     * Lines: [{product_id, qty, unit_cost, wh_id?, ref_line_id?, memo?}, ...]
     */
    public function receive(
        int $orgId,
        string $refTable,
        int|string|null $refId,
        array $lines,
        array $opts = []
    ): array {
        $allowZeroCost = (bool)($opts['allowZeroCost'] ?? false);
        $whIdDefault   = isset($opts['wh_id']) ? (int)$opts['wh_id'] : null;
        $ledger        = $this->pickLedgerTable();

        $this->pdo->beginTransaction();
        try {
            $posted = [];
            foreach ($lines as $i => $ln) {
                $pid  = (int)($ln['product_id'] ?? 0);
                $qty  = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
                $cost = (float)($ln['unit_cost'] ?? $ln['cost'] ?? $ln['unit_price'] ?? $ln['price'] ?? 0);
                $whId = isset($ln['wh_id']) ? (int)$ln['wh_id'] : $whIdDefault;
                $rln  = isset($ln['ref_line_id']) ? (int)$ln['ref_line_id'] : null;
                $memo = trim((string)($ln['memo'] ?? ''));

                if ($pid <= 0 || $qty <= 0) continue;
                if (!$allowZeroCost && $cost <= 0) {
                    throw new \InvalidArgumentException("Line #".($i+1)." has zero cost.");
                }

                // Lock + read balance
                $bal = $this->ensureBalanceRow($orgId, $pid);
                $oldQty = $bal['qty'];
                $oldAvg = $bal['avg'];

                $newQty = $oldQty + $qty;
                // moving-average only on receipts
                $newAvg = ($oldQty <= 0)
                    ? $cost
                    : round((($oldQty * $oldAvg) + ($qty * $cost)) / max($newQty, 1e-9), 6);

                $this->updateBalance($orgId, $pid, $newQty, $newAvg, $cost);

                if ($ledger) {
                    $this->writeLedger(
                        $ledger, $orgId, $whId, $pid,
                        'receipt', $qty, 0.0, $cost,
                        $refTable, is_numeric($refId)?(int)$refId:null, $rln, $memo
                    );
                }

                $posted[] = ['product_id'=>$pid, 'qty_in'=>$qty, 'unit_cost'=>$cost, 'new_qty'=>$newQty, 'new_avg'=>$newAvg];
            }
            $this->pdo->commit();
            return ['ok'=>true, 'count'=>count($posted), 'lines'=>$posted];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }
    }

    /**
     * Post an issue (qty decreases, cost taken from current avg unless provided).
     * Lines: [{product_id, qty, unit_cost?, wh_id?, ref_line_id?, memo?}, ...]
     */
    public function issue(
        int $orgId,
        string $refTable,
        int|string|null $refId,
        array $lines,
        array $opts = []
    ): array {
        $allowNegative = (bool)($opts['allowNegative'] ?? false);
        $useProvidedCost = (bool)($opts['useProvidedCost'] ?? false);
        $whIdDefault   = isset($opts['wh_id']) ? (int)$opts['wh_id'] : null;
        $ledger        = $this->pickLedgerTable();

        $this->pdo->beginTransaction();
        try {
            $posted = [];
            foreach ($lines as $i => $ln) {
                $pid  = (int)($ln['product_id'] ?? 0);
                $qty  = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
                if ($pid <= 0 || $qty <= 0) continue;

                $whId = isset($ln['wh_id']) ? (int)$ln['wh_id'] : $whIdDefault;
                $rln  = isset($ln['ref_line_id']) ? (int)$ln['ref_line_id'] : null;
                $memo = trim((string)($ln['memo'] ?? ''));

                $bal = $this->ensureBalanceRow($orgId, $pid);
                $oldQty = $bal['qty'];
                $avg    = $bal['avg'];

                $cost = $useProvidedCost
                    ? (float)($ln['unit_cost'] ?? $ln['cost'] ?? $avg)
                    : $avg;

                $newQty = $oldQty - $qty;
                if (!$allowNegative && $newQty < -0.000001) {
                    throw new \RuntimeException("Insufficient stock for PID={$pid}. On hand={$oldQty}, issue={$qty}");
                }
                // avg cost stays same on issue
                $this->updateBalance($orgId, $pid, $newQty, $avg, $bal['last']);

                if ($ledger) {
                    $this->writeLedger(
                        $ledger, $orgId, $whId, $pid,
                        'issue', 0.0, $qty, $cost,
                        $refTable, is_numeric($refId)?(int)$refId:null, $rln, $memo
                    );
                }

                $posted[] = ['product_id'=>$pid, 'qty_out'=>$qty, 'unit_cost'=>$cost, 'new_qty'=>$newQty, 'avg_cost'=>$avg];
            }
            $this->pdo->commit();
            return ['ok'=>true, 'count'=>count($posted), 'lines'=>$posted];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }
    }

    /**
     * Adjustment: positive delta = gain (receipt), negative delta = loss (issue).
     * Each line: {product_id, delta, unit_cost? (for +ve), memo?, wh_id?, ref_line_id?}
     */
    public function adjust(
        int $orgId,
        string $refTable,
        int|string|null $refId,
        array $lines,
        array $opts = []
    ): array {
        $allowNegative = (bool)($opts['allowNegative'] ?? false);
        $whIdDefault   = isset($opts['wh_id']) ? (int)$opts['wh_id'] : null;
        $ledger        = $this->pickLedgerTable();

        $this->pdo->beginTransaction();
        try {
            $posted = [];
            foreach ($lines as $ln) {
                $pid   = (int)($ln['product_id'] ?? 0);
                $delta = (float)($ln['delta'] ?? 0);
                if ($pid <= 0 || abs($delta) <= 0) continue;

                $whId = isset($ln['wh_id']) ? (int)$ln['wh_id'] : $whIdDefault;
                $rln  = isset($ln['ref_line_id']) ? (int)$ln['ref_line_id'] : null;
                $memo = trim((string)($ln['memo'] ?? ''));

                $bal = $this->ensureBalanceRow($orgId, $pid);
                $oldQty = $bal['qty'];
                $avg    = $bal['avg'];

                if ($delta > 0) { // gain → like receipt with provided cost (else use avg)
                    $cost = (float)($ln['unit_cost'] ?? $avg);
                    $newQty = $oldQty + $delta;
                    $newAvg = ($oldQty <= 0)
                        ? $cost
                        : round((($oldQty*$avg) + ($delta*$cost)) / max($newQty, 1e-9), 6);

                    $this->updateBalance($orgId, $pid, $newQty, $newAvg, $cost);
                    if ($ledger) $this->writeLedger($ledger,$orgId,$whId,$pid,'adjust_gain',$delta,0.0,$cost,$refTable,is_numeric($refId)?(int)$refId:null,$rln,$memo);

                    $posted[] = ['product_id'=>$pid,'qty_in'=>$delta,'unit_cost'=>$cost,'new_qty'=>$newQty,'new_avg'=>$newAvg];
                } else {         // loss → like issue at avg
                    $qty = abs($delta);
                    $newQty = $oldQty - $qty;
                    if (!$allowNegative && $newQty < -0.000001) {
                        throw new \RuntimeException("Adjustment makes stock negative for PID={$pid}");
                    }
                    $this->updateBalance($orgId, $pid, $newQty, $avg, $bal['last']);
                    if ($ledger) $this->writeLedger($ledger,$orgId,$whId,$pid,'adjust_loss',0.0,$qty,$avg,$refTable,is_numeric($refId)?(int)$refId:null,$rln,$memo);

                    $posted[] = ['product_id'=>$pid,'qty_out'=>$qty,'unit_cost'=>$avg,'new_qty'=>$newQty,'avg_cost'=>$avg];
                }
            }
            $this->pdo->commit();
            return ['ok'=>true, 'count'=>count($posted), 'lines'=>$posted];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }
    }

    /**
     * Transfer between warehouses (from_wh → to_wh). Doesn’t change avg cost overall.
     */
    public function transfer(
        int $orgId, int $productId, float $qty, int $fromWh, int $toWh,
        array $opts = [], string $refTable='transfer', int|string|null $refId=null
    ): array {
        if ($qty <= 0) return ['ok'=>true, 'count'=>0, 'lines'=>[]];
        $ledger = $this->pickLedgerTable();
        $allowNegative = (bool)($opts['allowNegative'] ?? false);

        $this->pdo->beginTransaction();
        try {
            // lock once
            $bal = $this->ensureBalanceRow($orgId, $productId);
            $avg = $bal['avg'];

            // from (issue)
            $res1 = $this->issue($orgId, $refTable, $refId, [
                ['product_id'=>$productId,'qty'=>$qty,'wh_id'=>$fromWh,'memo'=>'transfer-out']
            ], ['allowNegative'=>$allowNegative,'useProvidedCost'=>false]);

            if (!$res1['ok']) throw new \RuntimeException($res1['error'] ?? 'transfer out failed');

            // to (receipt) at current avg
            $res2 = $this->receive($orgId, $refTable, $refId, [
                ['product_id'=>$productId,'qty'=>$qty,'unit_cost'=>$avg,'wh_id'=>$toWh,'memo'=>'transfer-in']
            ], ['allowZeroCost'=>true]);

            if (!$res2['ok']) throw new \RuntimeException($res2['error'] ?? 'transfer in failed');

            $this->pdo->commit();
            return ['ok'=>true, 'count'=>1, 'lines'=>[['product_id'=>$productId,'qty'=>$qty,'avg_cost'=>$avg]]];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }
    }
}