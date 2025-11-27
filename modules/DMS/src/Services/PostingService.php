<?php
declare(strict_types=1);

namespace Modules\DMS\Services;

use PDO;
use DateTimeImmutable;
use Throwable;

final class PostingService
{
    public function __construct(private PDO $pdo) {}

    /* ---------- Helpers ---------- */

    private function map(PDO $pdo, int $orgId, string $key): int
    {
        $st = $pdo->prepare("SELECT account_id FROM dms_account_map WHERE org_id=? AND map_key=? LIMIT 1");
        $st->execute([$orgId, $key]);
        return (int)($st->fetchColumn() ?: 0);
    }

    private function movingAverageCost(PDO $pdo, int $orgId, int $productId, string $asOfYmd): float
    {
        // Average cost = total value in / net qty on hand up to asOf (IN-OUT valued @ move cost)
        $sql = "
            SELECT
              COALESCE(SUM(qty_in * cost_price) - SUM(qty_out * cost_price_out), 0)  AS value_on_hand,
              COALESCE(SUM(qty_in) - SUM(qty_out), 0)                               AS qty_on_hand
            FROM (
              SELECT moved_at, qty_in, 0 AS qty_out, cost_price, 0 cost_price_out
              FROM dms_inventory_moves
              WHERE org_id=? AND product_id=? AND moved_at <= ?
              UNION ALL
              SELECT moved_at, 0, qty_out, 0, cost_price
              FROM dms_inventory_moves
              WHERE org_id=? AND product_id=? AND moved_at <= ?
            ) t
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$orgId,$productId,$asOfYmd,$orgId,$productId,$asOfYmd]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['value_on_hand'=>0,'qty_on_hand'=>0];
        $qty = (float)$row['qty_on_hand'];
        if ($qty <= 0) {
            // Fallback safety: use product.cost_price or 0
            $p = $pdo->prepare("SELECT COALESCE(cost_price,0) FROM dms_products WHERE org_id=? AND id=?");
            $p->execute([$orgId,$productId]);
            return (float)($p->fetchColumn() ?: 0.0);
        }
        return round((float)$row['value_on_hand'] / $qty, 6);
    }

    private function clearFootprints(PDO $pdo, int $orgId, string $refType, int $refId): void
    {
        $pdo->prepare("DELETE FROM dms_inventory_moves WHERE org_id=? AND ref_type=? AND ref_id=?")
            ->execute([$orgId,$refType,$refId]);

        $st = $pdo->prepare("SELECT id FROM dms_gl_journals WHERE org_id=? AND ref_type=? AND ref_id=?");
        $st->execute([$orgId,$refType,$refId]);
        $ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id'));
        if ($ids) {
            $in = implode(',', array_fill(0,count($ids),'?'));
            $pdo->prepare("DELETE FROM dms_gl_entries WHERE org_id=? AND journal_id IN ($in)")
                ->execute(array_merge([$orgId], $ids));
            $pdo->prepare("DELETE FROM dms_gl_journals WHERE org_id=? AND id IN ($in)")
                ->execute(array_merge([$orgId], $ids));
        }
    }

    private function createJournal(PDO $pdo, int $orgId, string $jdate, string $jtype, string $memo, string $refType, int $refId): int
    {
        $st = $pdo->prepare("
            INSERT INTO dms_gl_journals (org_id, jno, jdate, jtype, memo, posted_by, posted_at, ref_type, ref_id)
            VALUES (?, '', ?, ?, ?, NULL, NOW(), ?, ?)
        ");
        $st->execute([$orgId, $jdate, $jtype, $memo, $refType, $refId]);
        return (int)$pdo->lastInsertId();
    }

    private function addEntry(PDO $pdo, int $orgId, int $journalId, int $accountId, float $dr, float $cr, string $memo=''): void
    {
        if ($accountId <= 0) return; // safe no-op
        $pdo->prepare("
            INSERT INTO dms_gl_entries (org_id, journal_id, line_no, account_id, dr, cr, memo)
            VALUES (?, ?, 0, ?, ROUND(?,2), ROUND(?,2), ?)
        ")->execute([$orgId, $journalId, $accountId, $dr, $cr, $memo]);
    }

    /* ---------- Public: post sales invoice (from order or standalone) ---------- */

    public function postSalesInvoice(int $orgId, int $saleId): void
    {
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            // Lock header
            $h = $pdo->prepare("SELECT id, status, invoice_no, date AS jdate FROM dms_sales WHERE org_id=? AND id=? FOR UPDATE");
            $h->execute([$orgId,$saleId]);
            $sale = $h->fetch(PDO::FETCH_ASSOC);
            if (!$sale) throw new \RuntimeException("Sale not found");
            if ((string)$sale['status'] !== 'draft') throw new \RuntimeException("Sale already posted/voided");
            $jdate = (string)($sale['jdate'] ?? date('Y-m-d'));

            // Validate map keys
            $accRevenue   = $this->map($pdo,$orgId,'revenue');
            $accCOGS      = $this->map($pdo,$orgId,'cogs');
            $accInventory = $this->map($pdo,$orgId,'inventory');
            $accAR        = $this->map($pdo,$orgId,'ar');
            if (!$accRevenue || !$accCOGS || !$accInventory || !$accAR) {
                throw new \RuntimeException("Account map incomplete (revenue/cogs/inventory/ar).");
            }

            // Lines
            $L = $pdo->prepare("
                SELECT product_id, qty, price, COALESCE(discount,0) AS discount
                FROM dms_sale_lines
                WHERE org_id=? AND sale_id=?
            ");
            $L->execute([$orgId,$saleId]);
            $lines = $L->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!$lines) throw new \RuntimeException("No sale lines.");

            // Idempotency
            $this->clearFootprints($pdo, $orgId, 'sale', $saleId);

            // Check stock availability (simple, by product)
            foreach ($lines as $ln) {
                $pid = (int)$ln['product_id'];
                $qty = (float)$ln['qty'];

                $on = $pdo->prepare("
                    SELECT COALESCE(SUM(qty_in) - SUM(qty_out),0)
                    FROM dms_inventory_moves
                    WHERE org_id=? AND product_id=?
                ");
                $on->execute([$orgId,$pid]);
                $onHand = (float)$on->fetchColumn();

                if ($onHand < $qty) {
                    throw new \RuntimeException("Insufficient stock for product_id {$pid}. On hand: {$onHand}, need: {$qty}");
                }
            }

            // Inventory moves + cost accumulation
            $sumCost = 0.0; $sumNet = 0.0;

            $insMove = $pdo->prepare("
                INSERT INTO dms_inventory_moves
                (org_id, product_id, ref_type, ref_id, qty_in, qty_out, cost_price, moved_at)
                VALUES (?, ?, 'sale', ?, 0, ?, ?, ?)
            ");

            foreach ($lines as $ln) {
                $pid = (int)$ln['product_id'];
                $qty = (float)$ln['qty'];
                $price = (float)$ln['price'];
                $disc  = (float)$ln['discount'];

                $avgCost = $this->movingAverageCost($pdo, $orgId, $pid, $jdate);
                $insMove->execute([$orgId, $pid, $saleId, $qty, $avgCost, $jdate]);

                $sumCost += $avgCost * $qty;
                $sumNet  += ($qty * $price) - $disc;
            }

            // Journal
            $jno = $this->createJournal($pdo, $orgId, $jdate, 'SALE', 'Sales Invoice #'.$sale['invoice_no'], 'sale', $saleId);

            // Revenue (credit-nature) → AR debit, Revenue credit
            $this->addEntry($pdo, $orgId, $jno, $accAR,        $sumNet, 0.0, 'AR');
            $this->addEntry($pdo, $orgId, $jno, $accRevenue,   0.0,     $sumNet, 'Revenue');

            // COGS (debit), Inventory (credit)
            $this->addEntry($pdo, $orgId, $jno, $accCOGS,      $sumCost, 0.0, 'COGS');
            $this->addEntry($pdo, $orgId, $jno, $accInventory, 0.0,      $sumCost, 'Inventory');

            // Finalize header
            $pdo->prepare("UPDATE dms_sales SET status='posted', posted_at=NOW() WHERE org_id=? AND id=?")
                ->execute([$orgId,$saleId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}