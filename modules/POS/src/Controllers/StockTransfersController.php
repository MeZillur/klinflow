<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class StockTransfersController extends BaseController
{
    /* ============================================================
       Helpers
    ============================================================ */

    /** Optional inventory table (per-branch stock). */
    private function inventoryTable(PDO $pdo): ?string
    {
        foreach (['pos_inventory', 'pos_stock', 'inventory', 'stocks'] as $t) {
            if ($this->hasTable($pdo, $t)) return $t;
        }
        return null;
    }

    /** Optional transfer header table. */
    private function transfersTable(PDO $pdo): ?string
    {
        foreach (['pos_stock_transfers', 'stock_transfers'] as $t) {
            if ($this->hasTable($pdo, $t)) return $t;
        }
        return null;
    }

    /** Normalize ctx same way as SalesController. */
    private function ensureBase(array $ctx = []): array
    {
        return $this->ctx($ctx);
    }

    /* ============================================================
       INDEX – list recent transfers
       GET /stock-transfers
    ============================================================ */
    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();
            $tbl   = $this->transfersTable($pdo);

            $rows = [];
            if ($tbl) {
                // Build column list depending on what exists
                $cols = ['id'];

                foreach (['transfer_date','date','created_at','from_branch_id',
                          'to_branch_id','reference','ref_no','total_items'] as $col) {
                    if ($this->hasCol($pdo, $tbl, $col)) {
                        $cols[] = $col;
                    }
                }

                $sql  = 'SELECT '.implode(',', $cols).' FROM '.$tbl.' WHERE 1=1';
                $bind = [];

                if ($this->hasCol($pdo, $tbl, 'org_id')) {
                    $sql  .= ' AND org_id = ?';
                    $bind[] = $orgId;
                }

                $sql .= ' ORDER BY id DESC LIMIT 100';

                $st   = $pdo->prepare($sql);
                $st->execute($bind);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $branches = $this->branches($pdo, $orgId);

            $this->view($c['module_dir'].'/Views/stock_transfers/index.php', [
                'title'    => 'Stock Transfers',
                'rows'     => $rows,
                'branches' => $branches,
                'base'     => $c['module_base'],
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Stock transfer list failed', $e);
        }
    }

    /* ============================================================
       CREATE FORM
       GET /stock-transfers/create
    ============================================================ */
    public function create(array $ctx = []): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();

            $branches = $this->branches($pdo, $orgId);

            $this->view($c['module_dir'].'/Views/stock_transfers/create.php', [
                'title'    => 'New Stock Transfer',
                'branches' => $branches,
                'base'     => $c['module_base'],
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Stock transfer create failed', $e);
        }
    }

    /* ============================================================
       STORE
       POST /stock-transfers
    ============================================================ */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();

            $pdo      = $this->pdo();
            $orgId    = $this->requireOrg();
            $invTbl   = $this->inventoryTable($pdo);

            if ($invTbl === null) {
                throw new \RuntimeException('Inventory table not configured');
            }

            // Quantity column
            $qtyCol = null;
            if ($this->hasCol($pdo, $invTbl, 'qty_on_hand')) {
                $qtyCol = 'qty_on_hand';
            } elseif ($this->hasCol($pdo, $invTbl, 'qty')) {
                $qtyCol = 'qty';
            }
            if ($qtyCol === null) {
                throw new \RuntimeException('Inventory quantity column not found');
            }

            $fromBranch = (int)($_POST['from_branch_id'] ?? 0);
            $toBranch   = (int)($_POST['to_branch_id']   ?? 0);
            $date       = trim((string)($_POST['transfer_date'] ?? date('Y-m-d')));
            $ref        = trim((string)($_POST['reference'] ?? ''));
            $notes      = trim((string)($_POST['notes'] ?? ''));

            if ($fromBranch <= 0 || $toBranch <= 0) {
                throw new \RuntimeException('Both source and destination branches are required');
            }
            if ($fromBranch === $toBranch) {
                throw new \RuntimeException('Source and destination branch cannot be the same');
            }

            $productIds = $_POST['product_id'] ?? [];
            $qtys       = $_POST['qty']        ?? [];

            $lines = [];
            $qtyByProduct = [];

            foreach ((array)$productIds as $idx => $pidRaw) {
                $pid = (int)$pidRaw;
                $qty = (float)($qtys[$idx] ?? 0);
                if ($pid <= 0 || $qty <= 0) continue;

                $lines[] = ['product_id' => $pid, 'qty' => $qty];

                if (!isset($qtyByProduct[$pid])) {
                    $qtyByProduct[$pid] = 0.0;
                }
                $qtyByProduct[$pid] += $qty;
            }

            if (empty($lines)) {
                throw new \RuntimeException('No products on the transfer');
            }

            // Optional header logging
            $transTbl = $this->transfersTable($pdo);

            $this->begin();

            /* ---------- 1) Check source branch stock (FOR UPDATE) ---------- */
            $pids = array_keys($qtyByProduct);
            $inPlace = implode(',', array_fill(0, count($pids), '?'));

            $sqlInv = "SELECT product_id, {$qtyCol} AS qty
                       FROM {$invTbl}
                       WHERE org_id = ?
                         AND branch_id = ?
                         AND product_id IN ({$inPlace})
                       FOR UPDATE";

            $argsInv = array_merge([$orgId, $fromBranch], $pids);
            $stInv   = $pdo->prepare($sqlInv);
            $stInv->execute($argsInv);
            $rowsInv = $stInv->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stockByPid = [];
            foreach ($rowsInv as $r) {
                $stockByPid[(int)$r['product_id']] = (float)$r['qty'];
            }

            foreach ($qtyByProduct as $pid => $needQty) {
                $have = $stockByPid[$pid] ?? 0.0;
                if ($have < $needQty) {
                    throw new \RuntimeException(
                        "Insufficient stock for product #{$pid} in source branch"
                    );
                }
            }

            /* ---------- 2) Optional header insert ---------- */
            $transferId = null;
            if ($transTbl) {
                $cols = ['org_id','from_branch_id','to_branch_id'];
                $qs   = ['?','?','?'];
                $vals = [$orgId,$fromBranch,$toBranch];

                if ($this->hasCol($pdo, $transTbl, 'transfer_date')) {
                    $cols[] = 'transfer_date';
                    $qs[]   = '?';
                    $vals[] = $date;
                } elseif ($this->hasCol($pdo, $transTbl, 'date')) {
                    $cols[] = 'date';
                    $qs[]   = '?';
                    $vals[] = $date;
                }

                if ($this->hasCol($pdo, $transTbl, 'reference')) {
                    $cols[] = 'reference';
                    $qs[]   = '?';
                    $vals[] = $ref;
                } elseif ($this->hasCol($pdo, $transTbl, 'ref_no')) {
                    $cols[] = 'ref_no';
                    $qs[]   = '?';
                    $vals[] = $ref;
                }

                if ($this->hasCol($pdo, $transTbl, 'notes')) {
                    $cols[] = 'notes';
                    $qs[]   = '?';
                    $vals[] = $notes;
                }

                if ($this->hasCol($pdo, $transTbl, 'total_items')) {
                    $cols[] = 'total_items';
                    $qs[]   = '?';
                    $vals[] = count($lines);
                }

                if ($this->hasCol($pdo, $transTbl, 'created_at')) {
                    $cols[] = 'created_at';
                    $qs[]   = 'NOW()';
                }
                if ($this->hasCol($pdo, $transTbl, 'updated_at')) {
                    $cols[] = 'updated_at';
                    $qs[]   = 'NOW()';
                }

                $sqlHdr = "INSERT INTO {$transTbl} (".implode(',', $cols).")
                           VALUES (".implode(',', $qs).")";
                $pdo->prepare($sqlHdr)->execute($vals);
                $transferId = (int)$pdo->lastInsertId();
            }

            /* ---------- 3) Update inventory (from – / to +) ---------- */

            // Prepare UPDATE for source
            $updSrc = $pdo->prepare("
                UPDATE {$invTbl}
                SET {$qtyCol} = {$qtyCol} - :qty,
                    updated_at = NOW()
                WHERE org_id    = :org_id
                  AND branch_id = :branch_id
                  AND product_id = :product_id
                LIMIT 1
            ");

            // Prepare UPDATE for destination
            $updDst = $pdo->prepare("
                UPDATE {$invTbl}
                SET {$qtyCol} = {$qtyCol} + :qty,
                    updated_at = NOW()
                WHERE org_id    = :org_id
                  AND branch_id = :branch_id
                  AND product_id = :product_id
                LIMIT 1
            ");

            // Prepare INSERT for destination if row missing
            $insCols = ['org_id','branch_id','product_id',$qtyCol];
            $insQs   = ['?','?','?','?'];
            if ($this->hasCol($pdo, $invTbl, 'created_at')) {
                $insCols[] = 'created_at';
                $insQs[]   = 'NOW()';
            }
            if ($this->hasCol($pdo, $invTbl, 'updated_at')) {
                $insCols[] = 'updated_at';
                $insQs[]   = 'NOW()';
            }
            if ($this->hasCol($pdo, $invTbl, 'is_active')) {
                $insCols[] = 'is_active';
                $insQs[]   = '1';
            }
            $sqlIns = "INSERT INTO {$invTbl} (".implode(',', $insCols).")
                       VALUES (".implode(',', $insQs).")";
            $insDst = $pdo->prepare($sqlIns);

            foreach ($lines as $line) {
                $pid = $line['product_id'];
                $qty = $line['qty'];

                // 3a) decrement source
                $updSrc->execute([
                    ':qty'        => $qty,
                    ':org_id'     => $orgId,
                    ':branch_id'  => $fromBranch,
                    ':product_id' => $pid,
                ]);

                // 3b) increment dest; if no row, insert
                $updDst->execute([
                    ':qty'        => $qty,
                    ':org_id'     => $orgId,
                    ':branch_id'  => $toBranch,
                    ':product_id' => $pid,
                ]);

                if ($updDst->rowCount() === 0) {
                    $insVals = [$orgId, $toBranch, $pid, $qty];
                    $insDst->execute($insVals);
                }
            }

            $this->commit();

            // back to index
            $base = $this->ctx($ctx)['module_base'] ?? '/apps/pos';
            $this->redirect($base.'/stock-transfers');

        } catch (Throwable $e) {
            $this->rollBack();
            $this->oops('Stock transfer failed', $e);
        }
    }
}