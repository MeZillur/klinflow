<?php
declare(strict_types=1);

namespace Modules\POS\Services;

use PDO;

final class StockService
{
    /**
     * Post stock IN for a purchase.
     * Idempotent via uq_move_ref on (org_id, ref_table, ref_id, product_id).
     */
    public static function postPurchase(PDO $pdo, int $purchaseId): void
    {
        $pdo->beginTransaction();
        try {
            // Lock header row (so concurrent calls don't race)
            $h = $pdo->prepare("SELECT id, org_id, status FROM pos_purchases WHERE id=? FOR UPDATE");
            $h->execute([$purchaseId]);
            $header = $h->fetch(PDO::FETCH_ASSOC);
            if (!$header) {
                throw new \RuntimeException("Purchase not found.");
            }
            $orgId = (int)$header['org_id'];

            // Pull items
            $q = $pdo->prepare("
                SELECT i.product_id, i.qty, i.unit_cost
                FROM pos_purchase_items i
                WHERE i.purchase_id = ?
            ");
            $q->execute([$purchaseId]);
            $items = $q->fetchAll(PDO::FETCH_ASSOC);

            if (!$items) {
                // no lines? still mark as received to avoid stuck status
                $u = $pdo->prepare("UPDATE pos_purchases SET status='received', received_at=NOW() WHERE id=? LIMIT 1");
                $u->execute([$purchaseId]);
                $pdo->commit();
                return;
            }

            // Insert moves; ignore duplicates thanks to unique key
            $ins = $pdo->prepare("
                INSERT INTO pos_stock_moves
                    (org_id, product_id, qty_in, qty_out, unit_price, move_type, ref_table, ref_id, moved_at, created_at)
                VALUES
                    (?, ?, ?, 0, ?, 'purchase_in', 'pos_purchases', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    qty_in = VALUES(qty_in), unit_price = VALUES(unit_price), moved_at = VALUES(moved_at)
            ");

            foreach ($items as $it) {
                $prodId = (int)$it['product_id'];
                $qty    = (float)$it['qty'];
                $price  = (float)$it['unit_cost'];
                if ($prodId <= 0 || $qty <= 0) continue;
                $ins->execute([$orgId, $prodId, $qty, $price, $purchaseId]);
            }

            // Mark header as received
            $u = $pdo->prepare("UPDATE pos_purchases SET status='received', received_at=NOW() WHERE id=? LIMIT 1");
            $u->execute([$purchaseId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}