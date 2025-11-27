<?php
declare(strict_types=1);

namespace Modules\BizFlow\Services;

use PDO;
use Throwable;
use Shared\DB;

/**
 * BizFlow InventoryService
 *
 * Responsibility:
 *   - Take a GRN (header + items)
 *   - Write inventory moves into biz_inventory_moves
 *
 * Tables:
 *   biz_grn
 *   biz_grn_items
 *   biz_inventory_moves
 */
final class InventoryService
{
    /* -------------------------------------------------------------
     * Low-level helpers
     * ----------------------------------------------------------- */

    private function pdo(): PDO
    {
        return DB::pdo();
    }

    /** Fetch single row or null */
    private function row(string $sql, array $params = []): ?array
    {
        $pdo  = $this->pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /** Fetch all rows (empty array if none) */
    private function all(string $sql, array $params = []): array
    {
        $pdo  = $this->pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* -------------------------------------------------------------
     * Core: post GRN → biz_inventory_moves
     * ----------------------------------------------------------- */

    /**
     * Post a GRN into inventory.
     *
     * - Reads biz_grn + biz_grn_items
     * - Deletes previous GRN moves (idempotent)
     * - Inserts fresh rows into biz_inventory_moves (kind='grn', direction='in')
     * - Marks GRN as posted
     */
    public function postGrn(int $orgId, int $grnId): void
    {
        $pdo = $this->pdo();

        try {
            $pdo->beginTransaction();

            // Lock GRN row so concurrent posts can't race
            $grn = $this->row(
                "SELECT *
                   FROM biz_grn
                  WHERE org_id = :org_id
                    AND id     = :id
                  FOR UPDATE",
                [
                    'org_id' => $orgId,
                    'id'     => $grnId,
                ]
            );

            if (!$grn) {
                throw new \RuntimeException("GRN not found (org_id={$orgId}, id={$grnId})");
            }

            $status      = strtolower((string)($grn['status'] ?? 'draft'));
            $warehouseId = (int)($grn['warehouse_id'] ?? 0);
            $moveDate    = (string)($grn['grn_date'] ?? date('Y-m-d'));

            // Load GRN items (we will also use ids for delete)
            $items = $this->all(
                "SELECT *
                   FROM biz_grn_items
                  WHERE org_id = :org_id
                    AND grn_id = :grn_id
               ORDER BY id",
                [
                    'org_id' => $orgId,
                    'grn_id' => $grnId,
                ]
            );

            // If nothing to post → clear any old moves for these items & reset state
            if (empty($items)) {
                $pdo->prepare(
                    "UPDATE biz_grn
                        SET status    = 'draft',
                            posted_at = NULL
                      WHERE org_id = :org_id
                        AND id     = :id"
                )->execute([
                    'org_id' => $orgId,
                    'id'     => $grnId,
                ]);

                $pdo->commit();
                return;
            }

            // Build list of GRN item ids for this GRN to clean old moves
            $grnItemIds   = array_column($items, 'id');
            $placeholders = implode(',', array_fill(0, count($grnItemIds), '?'));

            // Delete previous inventory moves for these GRN items (idempotent)
            $del = $pdo->prepare(
                "DELETE FROM biz_inventory_moves
                  WHERE org_id     = ?
                    AND kind       = 'grn'
                    AND grn_item_id IN ({$placeholders})"
            );
            $del->execute(array_merge([$orgId], $grnItemIds));

            // If GRN is cancelled → do not insert fresh moves, only clear / reset
            if ($status === 'cancelled') {
                $pdo->prepare(
                    "UPDATE biz_grn
                        SET posted_at = NULL
                      WHERE org_id = :org_id
                        AND id     = :id"
                )->execute([
                    'org_id' => $orgId,
                    'id'     => $grnId,
                ]);

                $pdo->commit();
                return;
            }

            // Insert fresh moves for each line
            $ins = $pdo->prepare(
                "INSERT INTO biz_inventory_moves
                     (org_id,
                      item_id,
                      warehouse_id,
                      grn_item_id,
                      kind,
                      move_date,
                      move_time,
                      direction,
                      qty,
                      unit,
                      unit_cost,
                      total_cost,
                      batch_no,
                      expiry_date,
                      meta_json,
                      created_at,
                      updated_at)
              VALUES (:org_id,
                      :item_id,
                      :warehouse_id,
                      :grn_item_id,
                      'grn',
                      :move_date,
                      :move_time,
                      'in',
                      :qty,
                      :unit,
                      :unit_cost,
                      :total_cost,
                      :batch_no,
                      :expiry_date,
                      NULL,
                      NOW(),
                      NOW())"
            );

            foreach ($items as $row) {
                $itemId   = (int)($row['item_id'] ?? 0);
                $qty      = (float)($row['qty'] ?? 0);
                $unitCost = (float)($row['unit_cost'] ?? 0.0);
                $unit     = (string)($row['unit'] ?? '');
                $batchNo  = (string)($row['batch_no'] ?? '');
                $expiry   = $row['expiry_date'] ?? null;

                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                $totalCost = $qty * $unitCost;

                $ins->execute([
                    'org_id'       => $orgId,
                    'item_id'      => $itemId,
                    'warehouse_id' => $warehouseId,
                    'grn_item_id'  => (int)$row['id'],
                    'move_date'    => $moveDate,
                    'move_time'    => '00:00:00',
                    'qty'          => $qty,
                    'unit'         => $unit !== '' ? $unit : null,
                    'unit_cost'    => $unitCost,
                    'total_cost'   => $totalCost,
                    'batch_no'     => $batchNo !== '' ? $batchNo : null,
                    'expiry_date'  => $expiry !== '' ? $expiry : null,
                ]);
            }

            // Mark GRN as posted
            $pdo->prepare(
                "UPDATE biz_grn
                    SET status    = 'posted',
                        posted_at = NOW()
                  WHERE org_id = :org_id
                    AND id     = :id"
            )->execute([
                'org_id' => $orgId,
                'id'     => $grnId,
            ]);

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Bubble up to controller
            throw $e;
        }
    }

    /* -------------------------------------------------------------
     * Optional helper: unpost GRN
     * ----------------------------------------------------------- */

    public function cancelGrn(int $orgId, int $grnId): void
    {
        $pdo = $this->pdo();

        try {
            $pdo->beginTransaction();

            // Get all GRN item ids for this GRN
            $items = $this->all(
                "SELECT id
                   FROM biz_grn_items
                  WHERE org_id = :org_id
                    AND grn_id = :grn_id",
                [
                    'org_id' => $orgId,
                    'grn_id' => $grnId,
                ]
            );
            $ids = array_column($items, 'id');

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $del = $pdo->prepare(
                    "DELETE FROM biz_inventory_moves
                      WHERE org_id     = ?
                        AND kind       = 'grn'
                        AND grn_item_id IN ({$placeholders})"
                );
                $del->execute(array_merge([$orgId], $ids));
            }

            $pdo->prepare(
                "UPDATE biz_grn
                    SET status    = 'cancelled',
                        posted_at = NULL
                  WHERE org_id = :org_id
                    AND id     = :id"
            )->execute([
                'org_id' => $orgId,
                'id'     => $grnId,
            ]);

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}