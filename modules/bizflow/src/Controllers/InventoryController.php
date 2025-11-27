<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

/**
 * BizFlow InventoryController
 *
 * Phase 1:
 *  - Inventory dashboard, product-centric
 *  - Stock movements table (biz_inventory_moves)
 *  - Stock transfers (create + store)
 *  - Stock adjustments (create + store)
 *
 * All monetary values in BDT. Moving-average costing can be plugged later.
 */
final class InventoryController extends BaseController
{
    /* ============================================================
     * SCHEMA BOOTSTRAP
     * ========================================================== */

    /**
     * Ensure core inventory table exists.
     *
     * Canonical schema (matches InventoryService::postGrn):
     *
     * biz_inventory_moves (
     *   id, org_id, item_id, warehouse_id, grn_item_id, invoice_item_id,
     *   kind ENUM('opening','grn','sale','adjustment','transfer','correction'),
     *   move_date, move_time,
     *   direction ENUM('in','out'),
     *   qty, unit, unit_cost, total_cost,
     *   batch_no, expiry_date, meta_json,
     *   created_at, updated_at
     * )
     */
    private function ensureInventorySchema(PDO $pdo): void
    {
        if ($this->hasTable($pdo, 'biz_inventory_moves')) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `biz_inventory_moves` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `org_id`         INT UNSIGNED    NOT NULL,
    `item_id`        BIGINT UNSIGNED NOT NULL,
    `warehouse_id`   BIGINT UNSIGNED NOT NULL,
    `grn_item_id`        BIGINT UNSIGNED DEFAULT NULL,
    `invoice_item_id`    BIGINT UNSIGNED DEFAULT NULL,
    `kind`           ENUM('opening','grn','sale','adjustment','transfer','correction') NOT NULL,
    `move_date`      DATE NOT NULL,
    `move_time`      TIME NOT NULL DEFAULT '00:00:00',
    `direction`      ENUM('in','out') NOT NULL,
    `qty`            DECIMAL(18,4) NOT NULL,
    `unit`           VARCHAR(32)   DEFAULT NULL,
    `unit_cost`      DECIMAL(18,4) DEFAULT NULL,
    `total_cost`     DECIMAL(18,4) DEFAULT NULL,
    `batch_no`       VARCHAR(64)   DEFAULT NULL,
    `expiry_date`    DATE          DEFAULT NULL,
    `meta_json`      JSON          DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bim_org_item_date`   (`org_id`,`item_id`,`move_date`),
    KEY `idx_bim_org_kind`        (`org_id`,`kind`),
    KEY `idx_bim_org_wh`          (`org_id`,`warehouse_id`),
    KEY `idx_bim_org_grn_item`    (`org_id`,`grn_item_id`),
    KEY `idx_bim_org_invoice_itm` (`org_id`,`invoice_item_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
SQL;

        $pdo->exec($sql);
    }

    /* ============================================================
     * INVENTORY DASHBOARD
     * ========================================================== */

    public function index(?array $ctx = null): void
    {
        try {
            $c      = $this->ctx($ctx ?? []);
            $org    = $c['org'] ?? [];
            $orgId  = $this->requireOrg();
            $pdo    = $this->pdo();

            $moduleBase = $c['module_base'] ?? '/apps/bizflow';

            // Make sure inventory table exists on fresh tenants
            $this->ensureInventorySchema($pdo);

            // ---------- Filters from query string ----------
            $search       = trim((string)($_GET['q']      ?? ''));
            $warehouseId  = (string)($_GET['wh']          ?? '');
            $categoryId   = (string)($_GET['cat']         ?? ''); // reserved for future
            $onlyProblems = (string)($_GET['problems']    ?? '') === '1';

            $asOf = trim((string)($_GET['as_of'] ?? ''));
            if ($asOf === '') {
                $asOf = (new DateTimeImmutable('now'))->format('Y-m-d');
            }

            // ---------- Warehouses dropdown ----------
            $warehouses = [];
            try {
                if ($this->hasTable($pdo, 'biz_warehouses')) {
                    $stmt = $pdo->prepare("
                        SELECT id, code, name, is_default
                          FROM biz_warehouses
                         WHERE org_id = ?
                         ORDER BY is_default DESC, name ASC
                    ");
                    $stmt->execute([$orgId]);
                    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            } catch (Throwable $ignore) {
                $warehouses = [];
            }

            // Categories – we’ll wire once schema is final
            $categories = [];

            // ---------- 1) Load items (safe columns only) ----------
            $sqlItems = "
                SELECT
                    i.id   AS item_id,
                    i.code AS item_code,
                    i.name AS item_name
                FROM biz_items i
                WHERE i.org_id = :org_id
            ";
            $itemParams = ['org_id' => $orgId];

            if ($search !== '') {
                $sqlItems .= " AND (i.code LIKE :q OR i.name LIKE :q)";
                $itemParams['q'] = '%'.$search.'%';
            }

            // (Warehouse / category filters don’t change item list yet)
            $sqlItems .= " ORDER BY i.name ASC, i.code ASC LIMIT 2000";

            $itemRows = $this->rows($sqlItems, $itemParams);

            // ---------- 2) Build stock index from biz_inventory_moves ----------
            $stockIndex    = [];   // item_id => ['on_hand' => .., 'value' => ..]
            $totalValue    = 0.0;
            $negativeCount = 0;

            $movesTableExists = $this->hasTable($pdo, 'biz_inventory_moves');

            if ($movesTableExists) {
                // Inspect columns so we can adapt to direction / total_cost presence
                $cols       = [];
                $hasDir     = false;
                $hasTotCost = false;

                try {
                    $qCols = $pdo->query("SHOW COLUMNS FROM biz_inventory_moves");
                    foreach ($qCols->fetchAll(PDO::FETCH_ASSOC) as $col) {
                        $name = (string)($col['Field'] ?? '');
                        if ($name === '') continue;
                        $cols[$name] = true;
                    }
                    $hasDir     = isset($cols['direction']);
                    $hasTotCost = isset($cols['total_cost']);
                } catch (Throwable $ignore) {
                    $cols       = [];
                    $hasDir     = false;
                    $hasTotCost = false;
                }

                $selectCols = "item_id, ";
                if ($hasDir) {
                    // Use direction to decide sign
                    $selectCols .= "
                        SUM(
                            CASE WHEN direction = 'in'
                                 THEN qty
                                 ELSE -qty
                            END
                        ) AS on_hand_qty
                    ";
                } else {
                    // Fallback if legacy schema without direction
                    $selectCols .= "SUM(qty) AS on_hand_qty";
                }

                if ($hasTotCost) {
                    if ($hasDir) {
                        $selectCols .= ",
                        SUM(
                            CASE WHEN direction = 'in'
                                 THEN COALESCE(total_cost,0)
                                 ELSE -COALESCE(total_cost,0)
                            END
                        ) AS stock_value
                        ";
                    } else {
                        $selectCols .= ",
                        SUM(COALESCE(total_cost,0)) AS stock_value
                        ";
                    }
                }

                $sqlStock = "
                    SELECT {$selectCols}
                      FROM biz_inventory_moves
                     WHERE org_id = :org_id
                       AND item_id IS NOT NULL
                       AND move_date <= :as_of
                ";
                $stockParams = [
                    'org_id' => $orgId,
                    'as_of'  => $asOf,
                ];

                if ($warehouseId !== '') {
                    $sqlStock            .= " AND warehouse_id = :wh_id";
                    $stockParams['wh_id'] = (int)$warehouseId;
                }

                $sqlStock .= " GROUP BY item_id";

                try {
                    $stockRows = $this->rows($sqlStock, $stockParams);
                    foreach ($stockRows as $sr) {
                        $itemId  = (int)($sr['item_id']      ?? 0);
                        $onHand  = (float)($sr['on_hand_qty'] ?? 0);
                        $value   = $hasTotCost
                            ? (float)($sr['stock_value'] ?? 0)
                            : 0.0;

                        if ($itemId <= 0) {
                            continue;
                        }

                        $stockIndex[$itemId] = [
                            'on_hand' => $onHand,
                            'value'   => $value,
                        ];

                        $totalValue += $value;
                        if ($onHand < 0) {
                            $negativeCount++;
                        }
                    }
                } catch (Throwable $ignore) {
                    // If anything goes wrong here, we just fall back to zeros.
                    $stockIndex    = [];
                    $totalValue    = 0.0;
                    $negativeCount = 0;
                }
            }

            // ---------- 3) Build inventory rows for the view ----------
            $inventory = [];

            foreach ($itemRows as $r) {
                $itemId = (int)($r['item_id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }

                $agg   = $stockIndex[$itemId] ?? ['on_hand' => 0.0, 'value' => 0.0];
                $onHand = (float)$agg['on_hand'];
                $value  = (float)$agg['value'];

                // For now reserved / incoming / reorder not wired – 0 is safe.
                $reserved   = 0.0;
                $incoming   = 0.0;
                $reorderLvl = 0.0;

                $available = $onHand - $reserved;

                // "Problem" rows (used by the view highlighting and counters)
                $isProblem = ($onHand < 0) || ($available < $reorderLvl);

                if ($onlyProblems && !$isProblem) {
                    continue;
                }

                $inventory[] = [
                    'item_id'          => $itemId,
                    'item_code'        => (string)($r['item_code'] ?? ''),
                    'item_name'        => (string)($r['item_name'] ?? ''),
                    'sku'              => '',  // optional later
                    'unit'             => '',
                    'warehouse_name'   => $warehouseId !== '' ? 'Selected warehouse' : 'All warehouses',
                    'warehouse_code'   => '',
                    'on_hand_qty'      => $onHand,
                    'reserved_qty'     => $reserved,
                    'available_qty'    => $available,
                    'incoming_qty'     => $incoming,
                    'reorder_level'    => $reorderLvl,
                    'stock_value'      => $value,
                    'last_movement_at' => '',   // we’ll wire later from moves
                ];
            }

            // ---------- 4) Summary KPIs ----------
            $summary = [
                'items'         => count($inventory),
                'stock_value'   => $totalValue,
                'below_reorder' => 0,                // reorder not wired yet
                'negative'      => $negativeCount,
            ];

            // ---------- 5) Render view ----------
            $this->view('inventory/index', [
                'title'       => 'Inventory',
                'org'         => $org,
                'module_base' => $moduleBase,
                'summary'     => $summary,
                'warehouses'  => $warehouses,
                'categories'  => $categories,
                'inventory'   => $inventory,
                // If you want later: pass filters/as_of to keep form sticky
                // 'filters'   => [...],
                // 'as_of'     => $asOf,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Inventory index failed', $e);
        }
    }

    /* ============================================================
     * TRANSFERS
     * ========================================================== */

    public function createTransfer(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $this->ensureInventorySchema($pdo);

            $warehouses = [];
            if ($this->hasTable($pdo, 'biz_warehouses')) {
                $warehouses = $this->rows(
                    "SELECT id, code, name, is_default
                       FROM biz_warehouses
                      WHERE org_id = ?
                      ORDER BY is_default DESC, name",
                    [$orgId]
                );
            }

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');
            $next  = ''; // later: biz_doc_counters

            $this->view('inventory/transfers/create', [
                'title'       => 'Transfer stock',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'warehouses'  => $warehouses,
                'today'       => $today,
                'next_no'     => $next,
                'endpoints'   => [
                    'items' => ($c['module_base'] ?? '/apps/bizflow') . '/items.lookup.json',
                ],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Transfer create screen failed', $e);
        }
    }

    public function storeTransfer(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'POST only';
                return;
            }

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $this->ensureInventorySchema($pdo);

            $moduleBase = $c['module_base'] ?? '/apps/bizflow';

            $transferNo = trim((string)($_POST['transfer_no'] ?? ''));
            $date       = (string)($_POST['date'] ?? date('Y-m-d'));
            $reason     = trim((string)($_POST['reason'] ?? ''));
            $reference  = trim((string)($_POST['reference'] ?? ''));
            $notes      = trim((string)($_POST['notes'] ?? ''));

            $fromWh = (int)($_POST['from_warehouse_id'] ?? 0);
            $toWh   = (int)($_POST['to_warehouse_id']   ?? 0);

            $items = $_POST['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            if ($fromWh <= 0 || $toWh <= 0 || $fromWh === $toWh) {
                http_response_code(422);
                echo 'Invalid warehouses';
                return;
            }

            $pdo->beginTransaction();

            $ins = $pdo->prepare(
                "INSERT INTO biz_inventory_moves
                 (org_id, item_id, warehouse_id, kind,
                  move_date, move_time, direction, qty,
                  unit, unit_cost, total_cost,
                  batch_no, expiry_date, meta_json,
                  created_at, updated_at)
                 VALUES
                 (:org_id, :item_id, :warehouse_id, 'transfer',
                  :move_date, '00:00:00', :direction, :qty,
                  NULL, NULL, NULL,
                  NULL, NULL, :meta_json,
                  NOW(), NOW())"
            );

            $userId = $this->currentUserId();

            foreach ($items as $row) {
                $itemId   = (int)($row['item_id'] ?? 0);
                $qty      = (float)($row['qty']      ?? 0);
                $lineNote = trim((string)($row['note'] ?? ''));

                if ($itemId <= 0 || $qty == 0.0) {
                    continue;
                }

                $baseMeta = [
                    'transfer_no' => $transferNo ?: null,
                    'reason'      => $reason ?: null,
                    'reference'   => $reference ?: null,
                    'notes'       => $notes ?: null,
                    'line_note'   => $lineNote ?: null,
                    'user_id'     => $userId,
                ];
                $metaJson = json_encode($baseMeta, JSON_UNESCAPED_UNICODE);

                $absQty = abs($qty);

                // OUT from source warehouse
                $ins->execute([
                    ':org_id'       => $orgId,
                    ':item_id'      => $itemId,
                    ':warehouse_id' => $fromWh,
                    ':move_date'    => $date,
                    ':direction'    => 'out',
                    ':qty'          => $absQty,
                    ':meta_json'    => $metaJson,
                ]);

                // IN to destination warehouse
                $ins->execute([
                    ':org_id'       => $orgId,
                    ':item_id'      => $itemId,
                    ':warehouse_id' => $toWh,
                    ':move_date'    => $date,
                    ':direction'    => 'in',
                    ':qty'          => $absQty,
                    ':meta_json'    => $metaJson,
                ]);
            }

            $pdo->commit();

            if (!headers_sent()) {
                header('Location: ' . $moduleBase . '/inventory', true, 302);
            }
            return;

        } catch (Throwable $e) {
            try {
                $this->pdo()->rollBack();
            } catch (Throwable $e2) {
                // ignore
            }
            $this->oops('Transfer store failed', $e);
        }
    }

    /* ============================================================
     * ADJUSTMENTS
     * ========================================================== */

    public function createAdjustment(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $this->ensureInventorySchema($pdo);

            $warehouses = [];
            if ($this->hasTable($pdo, 'biz_warehouses')) {
                $warehouses = $this->rows(
                    "SELECT id, code, name, is_default
                       FROM biz_warehouses
                      WHERE org_id = ?
                      ORDER BY is_default DESC, name",
                    [$orgId]
                );
            }

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');
            $next  = '';

            $this->view('inventory/adjustments/create', [
                'title'       => 'New stock adjustment',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'warehouses'  => $warehouses,
                'today'       => $today,
                'next_no'     => $next,
                'endpoints'   => [
                    'items' => ($c['module_base'] ?? '/apps/bizflow') . '/items.lookup.json',
                ],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Adjustment create screen failed', $e);
        }
    }

    public function storeAdjustment(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'POST only';
                return;
            }

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $this->ensureInventorySchema($pdo);

            $moduleBase = $c['module_base'] ?? '/apps/bizflow';

            $adjNo   = trim((string)($_POST['adjustment_no'] ?? ''));
            $date    = (string)($_POST['date'] ?? date('Y-m-d'));
            $reason  = trim((string)($_POST['reason'] ?? ''));
            $ref     = trim((string)($_POST['reference'] ?? ''));
            $notes   = trim((string)($_POST['notes'] ?? ''));
            $mode    = (string)($_POST['mode'] ?? 'delta'); // 'delta' or 'recount'
            $whId    = (int)($_POST['warehouse_id'] ?? 0);

            $items = $_POST['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            if ($whId <= 0) {
                http_response_code(422);
                echo 'Invalid warehouse';
                return;
            }

            $pdo->beginTransaction();

            $ins = $pdo->prepare(
                "INSERT INTO biz_inventory_moves
                 (org_id, item_id, warehouse_id, kind,
                  move_date, move_time, direction, qty,
                  unit, unit_cost, total_cost,
                  batch_no, expiry_date, meta_json,
                  created_at, updated_at)
                 VALUES
                 (:org_id, :item_id, :warehouse_id, 'adjustment',
                  :move_date, '00:00:00', :direction, :qty,
                  NULL, NULL, NULL,
                  NULL, NULL, :meta_json,
                  NOW(), NOW())"
            );

            $userId = $this->currentUserId();

            foreach ($items as $row) {
                $itemId = (int)($row['item_id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }

                // For now both modes use delta_qty; you can extend recount later.
                $deltaQty = (float)($row['delta_qty'] ?? 0);
                if ($deltaQty == 0.0) {
                    continue;
                }

                $direction = $deltaQty >= 0 ? 'in' : 'out';
                $absQty    = abs($deltaQty);

                $lineReason = trim((string)($row['reason'] ?? ''));

                $meta = [
                    'adjustment_no' => $adjNo ?: null,
                    'reason'        => $reason ?: null,
                    'reference'     => $ref ?: null,
                    'notes'         => $notes ?: null,
                    'line_reason'   => $lineReason ?: null,
                    'mode'          => $mode,
                    'user_id'       => $userId,
                ];
                $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);

                $ins->execute([
                    ':org_id'       => $orgId,
                    ':item_id'      => $itemId,
                    ':warehouse_id' => $whId,
                    ':move_date'    => $date,
                    ':direction'    => $direction,
                    ':qty'          => $absQty,
                    ':meta_json'    => $metaJson,
                ]);
            }

            $pdo->commit();

            if (!headers_sent()) {
                header('Location: ' . $moduleBase . '/inventory', true, 302);
            }
            return;

        } catch (Throwable $e) {
            try {
                $this->pdo()->rollBack();
            } catch (Throwable $e2) {
                // ignore
            }
            $this->oops('Adjustment store failed', $e);
        }
    }

    /* ============================================================
     * LOCAL HELPERS
     * ========================================================== */

    /**
     * Local hasTable() so we don't depend on BaseController implementation.
     */
    private function hasTable(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = ?
              LIMIT 1"
        );
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private function currentUserId(): ?int
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $u = $_SESSION['tenant_user'] ?? null;
        if (is_array($u) && isset($u['id'])) {
            return (int)$u['id'];
        }
        return null;
    }
}