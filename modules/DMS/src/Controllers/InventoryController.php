<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class InventoryController extends BaseController
{
    /* ============================================================
     * SEGMENT 0: Schema helpers (table/column detection)
     * ============================================================ */

    private array $schemaCache = [];

    protected function hasTable(PDO $pdo, string $table): bool
    {
        $k = "t:$table";
        if (!array_key_exists($k, $this->schemaCache)) {
            $st = $pdo->prepare("
                SELECT 1
                  FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                 LIMIT 1
            ");
            $st->execute([$table]);
            $this->schemaCache[$k] = (bool)$st->fetchColumn();
        }
        return $this->schemaCache[$k];
    }

    protected function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $k = "c:$table.$column";
        if (!array_key_exists($k, $this->schemaCache)) {
            $st = $pdo->prepare("
                SELECT 1
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND COLUMN_NAME  = ?
                 LIMIT 1
            ");
            $st->execute([$table, $column]);
            $this->schemaCache[$k] = (bool)$st->fetchColumn();
        }
        return $this->schemaCache[$k];
    }

    private function skuExpr(PDO $pdo): string
    {
        if ($this->hasColumn($pdo, 'dms_products', 'code'))    return 'p.code';
        if ($this->hasColumn($pdo, 'dms_products', 'barcode')) return 'p.barcode';
        return 'NULL';
    }

    /** Helper used by index/aging for stock expression. */
    private function stockSql(PDO $pdo): array
    {
        if ($this->hasTable($pdo, 'dms_stock_balances')
            && $this->hasColumn($pdo, 'dms_stock_balances', 'qty_on_hand')) {
            return [
                'expr'   => 'COALESCE(SUM(sb.qty_on_hand),0)',
                'join'   => 'LEFT JOIN dms_stock_balances sb ON sb.org_id=p.org_id AND sb.product_id=p.id',
                'group'  => true,
                'source' => 'balances',
            ];
        }

        if ($this->hasTable($pdo, 'dms_stock_moves')
            && $this->hasColumn($pdo, 'dms_stock_moves', 'qty_in')
            && $this->hasColumn($pdo, 'dms_stock_moves', 'qty_out')) {
            return [
                'expr'   => 'COALESCE(SUM(m.qty_in - m.qty_out),0)',
                'join'   => 'LEFT JOIN dms_stock_moves m ON m.org_id=p.org_id AND m.product_id=p.id',
                'group'  => true,
                'source' => 'moves',
            ];
        }

        if ($this->hasColumn($pdo, 'dms_products', 'initial_qty')) {
            return [
                'expr'   => 'COALESCE(p.initial_qty,0)',
                'join'   => '',
                'group'  => false,
                'source' => 'products.initial_qty',
            ];
        }

        return ['expr' => '0', 'join' => '', 'group' => false, 'source' => 'zero'];
    }

    /** Compute stock for a single product id. */
    private function stockFor(PDO $pdo, int $orgId, int $productId): float
    {
        if ($this->hasTable($pdo, 'dms_stock_balances')
            && $this->hasColumn($pdo, 'dms_stock_balances', 'qty_on_hand')) {
            $q = $pdo->prepare("SELECT COALESCE(SUM(qty_on_hand),0) FROM dms_stock_balances WHERE org_id=? AND product_id=?");
            $q->execute([$orgId, $productId]);
            return (float)($q->fetchColumn() ?: 0);
        }

        if ($this->hasTable($pdo, 'dms_stock_moves')
            && $this->hasColumn($pdo, 'dms_stock_moves', 'qty_in')
            && $this->hasColumn($pdo, 'dms_stock_moves', 'qty_out')) {
            $q = $pdo->prepare("SELECT COALESCE(SUM(qty_in - qty_out),0) FROM dms_stock_moves WHERE org_id=? AND product_id=?");
            $q->execute([$orgId, $productId]);
            return (float)($q->fetchColumn() ?: 0);
        }

        if ($this->hasColumn($pdo, 'dms_products', 'initial_qty')) {
            $q = $pdo->prepare("SELECT COALESCE(initial_qty,0) FROM dms_products WHERE org_id=? AND id=?");
            $q->execute([$orgId, $productId]);
            return (float)($q->fetchColumn() ?: 0);
        }

        return 0.0;
    }

    /* ============================================================
     * SEGMENT 1: Balance + move helpers (NO forced updated_at)
     * ============================================================ */

    /**
     * Apply a quantity delta to dms_stock_balances.
     */
    private function applyBalanceDelta(
        PDO $pdo,
        int $orgId,
        int $productId,
        float $deltaQty,
        ?float $unitCost
    ): void {
        if (!$this->hasTable($pdo, 'dms_stock_balances')) {
            return; // no table → nothing to do
        }

        $cost = $unitCost ?? 0.0;

        // Existing row?
        $st = $pdo->prepare("SELECT id FROM dms_stock_balances WHERE org_id=? AND product_id=? LIMIT 1");
        $st->execute([$orgId, $productId]);
        $existingId = $st->fetchColumn();

        if ($existingId !== false) {
            if ($unitCost !== null) {
                $sql = "
                    UPDATE dms_stock_balances
                       SET qty_on_hand = qty_on_hand + ?,
                           last_cost  = ?
                     WHERE org_id = ? AND product_id = ?
                     LIMIT 1
                ";
                $u = $pdo->prepare($sql);
                $u->execute([$deltaQty, $cost, $orgId, $productId]);
            } else {
                $sql = "
                    UPDATE dms_stock_balances
                       SET qty_on_hand = qty_on_hand + ?
                     WHERE org_id = ? AND product_id = ?
                     LIMIT 1
                ";
                $u = $pdo->prepare($sql);
                $u->execute([$deltaQty, $orgId, $productId]);
            }
            return;
        }

        // Insert minimal row
        if ($unitCost !== null) {
            $sql = "
                INSERT INTO dms_stock_balances (org_id, product_id, qty_on_hand, last_cost)
                VALUES (?, ?, ?, ?)
            ";
            $ins = $pdo->prepare($sql);
            $ins->execute([$orgId, $productId, $deltaQty, $cost]);
        } else {
            $sql = "
                INSERT INTO dms_stock_balances (org_id, product_id, qty_on_hand)
                VALUES (?, ?, ?)
            ";
            $ins = $pdo->prepare($sql);
            $ins->execute([$orgId, $productId, $deltaQty]);
        }
    }

    /**
     * Create a stock move row (damage / adjust / free posting)
     * - Tries dms_stock_moves first, then dms_inventory_moves
     * - NEVER references updated_at unless the column exists
     */
    private function createStockMove(
        PDO $pdo,
        int $orgId,
        int $productId,
        float $qtyIn,
        float $qtyOut,
        string $refTable,
        string $memo,
        string $date
    ): void {
        // Normalise date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $moveAt = $date . ' 00:00:00';
        $memo   = $memo !== '' ? $memo : 'Damage';

        /* ---------- Path 1: dms_stock_moves (legacy) ---------- */
        if ($this->hasTable($pdo, 'dms_stock_moves')) {
            $cols = ['org_id', 'product_id'];
            $vals = ['?',      '?'        ];
            $params = [$orgId, $productId];

            // Optional wh_id
            if ($this->hasColumn($pdo, 'dms_stock_moves', 'wh_id')) {
                $cols[] = 'wh_id';
                $vals[] = 'NULL'; // no warehouse context for now
            }

            // Movement basics
            $cols = array_merge($cols, ['qty_in', 'qty_out', 'move_at']);
            $vals = array_merge($vals, ['?',      '?',       '?']);
            $params = array_merge($params, [$qtyIn, $qtyOut, $moveAt]);

            // Optional reference fields
            if ($this->hasColumn($pdo, 'dms_stock_moves', 'ref_table')) {
                $cols[] = 'ref_table';
                $vals[] = '?';
                $params[] = $refTable;
            }
            if ($this->hasColumn($pdo, 'dms_stock_moves', 'ref_id')) {
                $cols[] = 'ref_id';
                $vals[] = 'NULL';
            }
            if ($this->hasColumn($pdo, 'dms_stock_moves', 'ref_no')) {
                $cols[] = 'ref_no';
                $vals[] = 'NULL';
            }
            if ($this->hasColumn($pdo, 'dms_stock_moves', 'memo')) {
                $cols[] = 'memo';
                $vals[] = '?';
                $params[] = $memo;
            } elseif ($this->hasColumn($pdo, 'dms_stock_moves', 'note')) {
                $cols[] = 'note';
                $vals[] = '?';
                $params[] = $memo;
            }

            // Timestamps (optional)
            if ($this->hasColumn($pdo, 'dms_stock_moves', 'created_at')) {
                $cols[] = 'created_at';
                $vals[] = 'NOW()';
            }
            if ($this->hasColumn($pdo, 'dms_stock_moves', 'updated_at')) {
                $cols[] = 'updated_at';
                $vals[] = 'NOW()';
            }

            $sql = "INSERT INTO dms_stock_moves (" . implode(',', $cols) . ")
                    VALUES (" . implode(',', $vals) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        /* ---------- Path 2: dms_inventory_moves (unified) ---------- */
        if ($this->hasTable($pdo, 'dms_inventory_moves')) {
            $cols   = ['org_id', 'product_id', 'move_type', 'in_qty', 'out_qty', 'unit_cost', 'note'];
            $vals   = ['?',      '?',          '?',         '?',      '?',       '0',         '?'];
            $params = [$orgId,   $productId,   $refTable,   $qtyIn,   $qtyOut,                $memo];

            // Optional ref_no / ref / reference / voucher_no
            if ($this->hasColumn($pdo, 'dms_inventory_moves', 'ref_no')) {
                $cols[] = 'ref_no';
                $vals[] = 'NULL';
            } elseif ($this->hasColumn($pdo, 'dms_inventory_moves', 'ref')) {
                $cols[] = 'ref';
                $vals[] = 'NULL';
            } elseif ($this->hasColumn($pdo, 'dms_inventory_moves', 'reference')) {
                $cols[] = 'reference';
                $vals[] = 'NULL';
            }

            // created_at / updated_at optional
            if ($this->hasColumn($pdo, 'dms_inventory_moves', 'created_at')) {
                $cols[] = 'created_at';
                $vals[] = 'NOW()';
            }
            if ($this->hasColumn($pdo, 'dms_inventory_moves', 'updated_at')) {
                $cols[] = 'updated_at';
                $vals[] = 'NOW()';
            }

            $sql = "INSERT INTO dms_inventory_moves (" . implode(',', $cols) . ")
                    VALUES (" . implode(',', $vals) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        // If neither table exists, silently no-op (or throw if you want).
    }

    /* ============================================================
     * SEGMENT 2: Inventory dashboard (index)
     * ============================================================ */
    public function index(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo   = $this->pdo();
            $orgId = $this->orgId($c);
            $base  = $this->moduleBase($c);

            $skuExpr = $this->hasColumn($pdo, 'dms_products', 'code')
                ? 'p.code'
                : ($this->hasColumn($pdo, 'dms_products', 'barcode') ? 'p.barcode' : 'NULL');

            $q   = trim((string)($_GET['q'] ?? ''));
            $low = (int)($_GET['low'] ?? 0) === 1;

            /* ---------- Preferred: dms_stock_balances path ---------- */
            if ($this->hasTable($pdo, 'dms_stock_balances')
                && $this->hasColumn($pdo, 'dms_stock_balances', 'qty_on_hand')) {

                $w    = ["p.org_id = ?"];
                $args = [$orgId];

                if ($q !== '') {
                    if ($skuExpr !== 'NULL') {
                        $w[]   = "(p.name LIKE ? OR $skuExpr LIKE ?)";
                        $args[] = "%$q%";
                        $args[] = "%$q%";
                    } else {
                        $w[]   = "p.name LIKE ?";
                        $args[] = "%$q%";
                    }
                }

                $sql = "
                    SELECT
                        p.id,
                        p.name,
                        " . ($skuExpr !== 'NULL' ? $skuExpr : "NULL") . " AS sku,
                        p.supplier_id,
                        p.unit_price,

                        COALESCE(SUM(COALESCE(sb.qty_on_hand,0)), 0) AS on_hand,

                        COALESCE(SUM(COALESCE(sb.qty_on_hand,0) *
                               COALESCE(NULLIF(sb.avg_cost,0), sb.last_cost, 0)), 0) AS stock_value,

                        CASE WHEN COALESCE(SUM(COALESCE(sb.qty_on_hand,0)),0) > 0
                             THEN COALESCE(SUM(COALESCE(sb.qty_on_hand,0) *
                                       COALESCE(NULLIF(sb.avg_cost,0), sb.last_cost, 0)), 0)
                                  / COALESCE(SUM(COALESCE(sb.qty_on_hand,0)),0)
                             ELSE 0 END AS avg_cost,

                        COALESCE(MAX(COALESCE(sb.last_cost,0)), 0) AS last_cost

                    FROM dms_products p
                    LEFT JOIN dms_stock_balances sb
                      ON sb.org_id  = p.org_id
                     AND sb.product_id = p.id
                    WHERE " . implode(' AND ', $w) . "
                    GROUP BY p.id, p.name, p.supplier_id, p.unit_price"
                        . ($skuExpr !== 'NULL' ? ", $skuExpr" : "") . "
                    ORDER BY p.name
                    LIMIT 1000
                ";

                $st = $pdo->prepare($sql);
                $st->execute($args);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

                if ($low) {
                    $rows = array_values(array_filter(
                        $rows,
                        fn($r) => (float)$r['on_hand'] <= 0.00001
                    ));
                }

                $distinct = count($rows);
                $totalQty = 0.0;
                $lowCount = 0;
                $invValue = 0.0;

                foreach ($rows as &$r) {
                    $r['on_hand']     = (float)$r['on_hand'];
                    $r['avg_cost']    = (float)$r['avg_cost'];
                    $r['last_cost']   = (float)$r['last_cost'];
                    $r['stock_value'] = (float)$r['stock_value'];

                    $totalQty += $r['on_hand'];
                    $invValue += $r['stock_value'];
                    if ($r['on_hand'] <= 0.00001) $lowCount++;
                }
                unset($r);

                $this->view('inventory/index', [
                    'title'        => 'Inventory',
                    'rows'         => $rows,
                    'kpi'          => [
                        'distinct' => $distinct,
                        'total'    => $totalQty,
                        'low'      => $lowCount,
                        'value'    => $invValue,
                    ],
                    'filters'      => ['q' => $q, 'low' => $low ? 1 : 0],
                    'module_base'  => $base,
                    'stock_source' => 'balances',
                    'active'       => 'inventory',
                    'subactive'    => 'inventory.index',
                ], $c);
                return;
            }

            /* ---------- Fallback: moves / initial_qty ---------- */
            $stk   = $this->stockSql($pdo);
            $w     = ["p.org_id = ?"];
            $args  = [$orgId];

            if ($q !== '') {
                if ($skuExpr !== 'NULL') {
                    $w[] = "(p.name LIKE ? OR $skuExpr LIKE ?)";
                    $args[] = "%$q%";
                    $args[] = "%$q%";
                } else {
                    $w[] = "p.name LIKE ?";
                    $args[] = "%$q%";
                }
            }

            $group = $stk['group']
                ? "GROUP BY p.id, p.name, p.supplier_id, p.unit_price, $skuExpr"
                : "";

            $sql = "
                SELECT
                    p.id,
                    p.name,
                    $skuExpr AS sku,
                    p.supplier_id,
                    p.unit_price,
                    {$stk['expr']} AS on_hand
                FROM dms_products p
                {$stk['join']}
                WHERE " . implode(' AND ', $w) . "
                $group
                ORDER BY p.name
                LIMIT 1000
            ";

            $st = $pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($low) {
                $rows = array_values(array_filter(
                    $rows,
                    fn($r) => (float)($r['on_hand'] ?? 0) <= 0.00001
                ));
            }

            $distinct = count($rows);
            $totalQty = 0.0;
            $lowCount = 0;

            foreach ($rows as &$r) {
                $r['on_hand']     = (float)($r['on_hand'] ?? 0);
                $r['avg_cost']    = 0.0;
                $r['last_cost']   = 0.0;
                $r['stock_value'] = 0.0;

                $totalQty += $r['on_hand'];
                if ($r['on_hand'] <= 0.00001) $lowCount++;
            }
            unset($r);

            $this->view('inventory/index', [
                'title'        => 'Inventory',
                'rows'         => $rows,
                'kpi'          => [
                    'distinct' => $distinct,
                    'total'    => $totalQty,
                    'low'      => $lowCount,
                    'value'    => 0.0,
                ],
                'filters'      => ['q' => $q, 'low' => $low ? 1 : 0],
                'module_base'  => $base,
                'stock_source' => $stk['source'],
                'active'       => 'inventory',
                'subactive'    => 'inventory.index',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Inventory index error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * SEGMENT 3: Movements viewer
     * ============================================================ */
    public function moves(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);
            $pid = (int)($_GET['product_id'] ?? 0);

            if ($pid <= 0) {
                $this->abort400('Missing product_id');
                return;
            }

            $p = $pdo->prepare("SELECT id, name FROM dms_products WHERE org_id=? AND id=?");
            $p->execute([$org, $pid]);
            $prod = $p->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                $this->abort404('Product not found');
                return;
            }

            $rows = [];
            if ($this->hasTable($pdo, 'dms_stock_moves')) {
                $st = $pdo->prepare("
                    SELECT id, move_at, ref_table, ref_id, ref_no, memo, qty_in, qty_out, created_at
                      FROM dms_stock_moves
                     WHERE org_id=? AND product_id=?
                  ORDER BY id DESC
                     LIMIT 200
                ");
                $st->execute([$org, $pid]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $this->view('inventory/moves', [
                'title'      => 'Stock Movements',
                'product'    => $prod,
                'rows'       => $rows,
                'on_hand'    => $this->stockFor($pdo, $org, $pid),
                'module_base'=> $this->moduleBase($c),
                'active'     => 'inventory',
                'subactive'  => 'inventory.moves',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Inventory moves error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * SEGMENT 4: Aging
     * ============================================================ */
    public function aging(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo   = $this->pdo();
            $orgId = $this->orgId($c);
            $sku   = $this->skuExpr($pdo);
            $stk   = $this->stockSql($pdo);

            $lifeJoin = '';
            $lifeSel  = ', NULL AS arrival_date, NULL AS expiry_date';
            $grpLife  = '';

            if ($this->hasTable($pdo, 'dms_product_lifecycle')) {
                $lifeJoin = 'LEFT JOIN dms_product_lifecycle lc ON lc.org_id=p.org_id AND lc.product_id=p.id';
                $lifeSel  = ', lc.arrival_date, lc.expiry_date';
                $grpLife  = ', lc.arrival_date, lc.expiry_date';
            }

            $group = $stk['group'] ? "GROUP BY p.id, p.name, $sku $grpLife" : '';

            $q = $pdo->prepare("
                SELECT p.id,
                       p.name,
                       $sku AS sku,
                       {$stk['expr']} AS on_hand
                       $lifeSel
                  FROM dms_products p
                  {$stk['join']}
                  $lifeJoin
                 WHERE p.org_id=?
                 $group
              ORDER BY p.name
            ");
            $q->execute([$orgId]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view('inventory/aging', [
                'title'       => 'Inventory Aging',
                'rows'        => $rows,
                'module_base' => $this->moduleBase($c),
                'active'      => 'inventory',
                'subactive'   => 'inventory.aging',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Inventory aging error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * SEGMENT 5: Damage (entry + listing)
     * ============================================================ */

    public function damage(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo  = $this->pdo();
            $org  = $this->orgId($c);
            $base = $this->moduleBase($c);
            $sku  = $this->skuExpr($pdo);

            // Products dropdown
            $ps = $pdo->prepare("
                SELECT p.id, p.name, {$sku} AS sku
                  FROM dms_products p
                 WHERE p.org_id = ?
              ORDER BY p.name
            ");
            $ps->execute([$org]);
            $products = $ps->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Recent damage rows
            $rows = [];
            if ($this->hasTable($pdo, 'dms_stock_moves')) {
                $st = $pdo->prepare("
                    SELECT m.id,
                           m.move_at,
                           m.memo,
                           m.qty_in,
                           m.qty_out,
                           p.name AS product_name,
                           {$sku} AS sku
                      FROM dms_stock_moves m
                      JOIN dms_products p
                        ON p.org_id = m.org_id AND p.id = m.product_id
                     WHERE m.org_id = ? AND m.ref_table = 'damage'
                  ORDER BY m.id DESC
                     LIMIT 200
                ");
                $st->execute([$org]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) {
                    $r['qty'] = (float)($r['qty_out'] ?? 0);
                }
                unset($r);
            } elseif ($this->hasTable($pdo, 'dms_inventory_moves')) {
                $st = $pdo->prepare("
                    SELECT m.id,
                           m.created_at AS move_at,
                           m.note       AS memo,
                           m.in_qty,
                           m.out_qty,
                           p.name AS product_name,
                           {$sku} AS sku
                      FROM dms_inventory_moves m
                      JOIN dms_products p
                        ON p.org_id = m.org_id AND p.id = m.product_id
                     WHERE m.org_id = ? AND m.move_type = 'damage'
                  ORDER BY m.id DESC
                     LIMIT 200
                ");
                $st->execute([$org]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) {
                    $r['qty'] = (float)($r['out_qty'] ?? 0);
                }
                unset($r);
            }

            $this->view('inventory/damage', [
                'title'       => 'Damage Entry',
                'products'    => $products,
                'rows'        => $rows,
                'module_base' => $base,
                'active'      => 'inventory',
                'subactive'   => 'inventory.damage',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Inventory damage error: ' . $e->getMessage());
        }
    }

    public function storeDamage(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);

            $pid  = (int)($_POST['product_id'] ?? 0);
            $qty  = (float)($_POST['qty'] ?? 0);
            $note = trim((string)($_POST['note'] ?? 'Damage'));
            $date = (string)($_POST['date'] ?? date('Y-m-d'));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            if ($pid <= 0 || $qty <= 0) {
                $this->abort400('Select product and positive quantity.');
                return;
            }

            $pdo->beginTransaction();

            // Record move (qty_out for damage)
            $this->createStockMove($pdo, $org, $pid, 0.0, $qty, 'damage', $note, $date);

            // Negative balance delta
            $this->applyBalanceDelta($pdo, $org, $pid, -$qty, null);

            $pdo->commit();

            $this->redirect($this->moduleBase($c) . '/inventory/damage');

        } catch (\Throwable $e) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->abort500('Could not save damage entry: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * SEGMENT 6: Adjustments (± qty)
     * ============================================================ */

    public function adjust(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);

            $ps = $pdo->prepare("SELECT id, name FROM dms_products WHERE org_id=? ORDER BY name");
            $ps->execute([$org]);
            $products = $ps->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view('inventory/adjust', [
                'title'       => 'Stock Adjustment',
                'products'    => $products,
                'today'       => date('Y-m-d'),
                'module_base' => $this->moduleBase($c),
                'active'      => 'inventory',
                'subactive'   => 'inventory.adjust',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Inventory adjust error: ' . $e->getMessage());
        }
    }

    public function storeAdjust(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);

            $pid  = (int)($_POST['product_id'] ?? 0);
            $qty  = (float)($_POST['qty'] ?? 0);
            $note = trim((string)($_POST['note'] ?? 'Adjustment'));
            $date = (string)($_POST['date'] ?? date('Y-m-d'));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            if ($pid <= 0 || abs($qty) <= 0) {
                $this->abort400('Select product and non-zero quantity.');
                return;
            }

            $pdo->beginTransaction();

            if ($qty > 0) {
                // Increase stock
                $this->createStockMove($pdo, $org, $pid, $qty, 0, 'adjust', $note, $date);
            } else {
                // Decrease stock
                $this->createStockMove($pdo, $org, $pid, 0, -$qty, 'adjust', $note, $date);
            }

            $this->applyBalanceDelta($pdo, $org, $pid, $qty, null);
            $pdo->commit();

            $this->redirect($this->moduleBase($c) . '/inventory');

        } catch (\Throwable $e) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->abort500('Inventory storeAdjust error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * SEGMENT 7: Free stock helpers (model detection)
     * ============================================================ */

    /** Detect the available free-stock model. */
    private function freeModel(PDO $pdo): array
    {
        // Preferred: dms_free_stock_moves (qty_in / qty_out)
        if ($this->hasTable($pdo, 'dms_free_stock_moves')
            && $this->hasColumn($pdo, 'dms_free_stock_moves', 'qty_in')
            && $this->hasColumn($pdo, 'dms_free_stock_moves', 'qty_out')) {
            return ['table' => 'dms_free_stock_moves', 'mode' => 'moves'];
        }

        // Fallback: dms_free_stock with qty + direction/type
        if ($this->hasTable($pdo, 'dms_free_stock')
            && $this->hasColumn($pdo, 'dms_free_stock', 'qty')) {
            $hasDir = $this->hasColumn($pdo, 'dms_free_stock', 'direction');
            $hasTyp = $this->hasColumn($pdo, 'dms_free_stock', 'type');
            return [
                'table' => 'dms_free_stock',
                'mode'  => $hasDir ? 'direction' : ($hasTyp ? 'type' : 'qty_only'),
            ];
        }

        return ['table' => null, 'mode' => null];
    }

    /* ============================================================
     * SEGMENT 8: Free stock — Receive
     * ============================================================ */

    /** GET /free/receive */
    public function freeReceive(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);
            $sku = $this->skuExpr($pdo);

            $ps = $pdo->prepare("SELECT p.id, p.name, $sku AS sku FROM dms_products p WHERE p.org_id=? ORDER BY p.name");
            $ps->execute([$org]);
            $products = $ps->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $rows = [];
            $fm   = $this->freeModel($pdo);

            if ($fm['table'] === 'dms_free_stock_moves') {
                $st = $pdo->prepare("
                    SELECT f.id,
                           f.moved_at,
                           f.ref_no,
                           f.note,
                           f.qty_in,
                           p.name AS product_name,
                           $sku AS sku
                      FROM dms_free_stock_moves f
                      JOIN dms_products p ON p.org_id=f.org_id AND p.id=f.product_id
                     WHERE f.org_id=? AND COALESCE(f.qty_in,0)>0
                  ORDER BY f.id DESC
                     LIMIT 200
                ");
                $st->execute([$org]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) $r['qty'] = (float)($r['qty_in'] ?? 0);
                unset($r);

            } elseif ($fm['table'] === 'dms_free_stock') {
                $col = $fm['mode'] === 'direction'
                    ? "direction='in'"
                    : ($fm['mode'] === 'type' ? "type='receive'" : "qty>0");

                $st = $pdo->prepare("
                    SELECT f.id,
                           f.moved_at,
                           f.ref_no,
                           f.note,
                           f.qty,
                           p.name AS product_name,
                           $sku AS sku
                      FROM dms_free_stock f
                      JOIN dms_products p ON p.org_id=f.org_id AND p.id=f.product_id
                     WHERE f.org_id=? AND $col
                  ORDER BY f.id DESC
                     LIMIT 200
                ");
                $st->execute([$org]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) $r['qty'] = (float)($r['qty'] ?? 0);
                unset($r);
            }

            $this->view('inventory/free-receive', [
                'title'       => 'Free Stock — Receive',
                'products'    => $products,
                'rows'        => $rows,
                'today'       => date('Y-m-d'),
                'module_base' => $this->moduleBase($c),
                'active'      => 'inventory',
                'subactive'   => 'inventory.free.receive',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Free receive error: ' . $e->getMessage());
        }
    }

    /** POST /free/receive */
    public function storeFreeReceive(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);

            $pid     = (int)($_POST['product_id'] ?? 0);
            $qty     = (float)($_POST['qty'] ?? 0);
            $note    = trim((string)($_POST['note'] ?? 'Free receive'));
            $date    = (string)($_POST['date'] ?? date('Y-m-d'));
            $postInv = (int)($_POST['post_inventory'] ?? 0) === 1;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            if ($pid <= 0 || $qty <= 0) {
                $this->abort400('Select product and positive quantity.');
                return;
            }

            $pdo->beginTransaction();

            $fm = $this->freeModel($pdo);
            $movedAt = $date . ' 00:00:00';

            if ($fm['table'] === 'dms_free_stock_moves') {
                // dynamic created_at / updated_at
                $cols   = ['org_id', 'product_id', 'qty_in', 'qty_out', 'ref_no', 'note', 'moved_at'];
                $vals   = ['?',      '?',          '?',      '0',       'NULL',  '?',    '?'];
                $params = [$org,     $pid,         $qty,               $note,   $movedAt];

                if ($this->hasColumn($pdo, 'dms_free_stock_moves', 'created_at')) {
                    $cols[] = 'created_at';
                    $vals[] = 'NOW()';
                }
                if ($this->hasColumn($pdo, 'dms_free_stock_moves', 'updated_at')) {
                    $cols[] = 'updated_at';
                    $vals[] = 'NOW()';
                }

                $sql = "INSERT INTO dms_free_stock_moves (" . implode(',', $cols) . ")
                        VALUES (" . implode(',', $vals) . ")";
                $ins = $pdo->prepare($sql);
                $ins->execute($params);

            } elseif ($fm['table'] === 'dms_free_stock') {
                $cols   = ['org_id', 'product_id', 'qty', 'ref_no', 'note', 'moved_at'];
                $vals   = ['?',      '?',          '?',   'NULL',   '?',    '?'];
                $params = [$org,     $pid,         $qty,          $note,   $movedAt];

                if ($fm['mode'] === 'direction') {
                    $cols[] = 'direction';
                    $vals[] = "'in'";
                } elseif ($fm['mode'] === 'type') {
                    $cols[] = 'type';
                    $vals[] = "'receive'";
                }

                if ($this->hasColumn($pdo, 'dms_free_stock', 'created_at')) {
                    $cols[] = 'created_at';
                    $vals[] = 'NOW()';
                }
                if ($this->hasColumn($pdo, 'dms_free_stock', 'updated_at')) {
                    $cols[] = 'updated_at';
                    $vals[] = 'NOW()';
                }

                $sql = "INSERT INTO dms_free_stock (" . implode(',', $cols) . ")
                        VALUES (" . implode(',', $vals) . ")";
                $ins = $pdo->prepare($sql);
                $ins->execute($params);
            }

            // Optional posting to main inventory
            if ($postInv) {
                $this->createStockMove($pdo, $org, $pid, $qty, 0, 'free_receive', $note, $date);
                $this->applyBalanceDelta($pdo, $org, $pid, $qty, null);
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($c) . '/free/receive');

        } catch (\Throwable $e) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->abort500('Free receive save error: ' . $e->getMessage());
        }
    }

    /* ============================================================
     * SEGMENT 9: Free stock — Issue
     * ============================================================ */

    /** GET /free/issue */
    public function freeIssue(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);
            $sku = $this->skuExpr($pdo);

            $ps = $pdo->prepare("SELECT p.id, p.name, $sku AS sku FROM dms_products p WHERE p.org_id=? ORDER BY p.name");
            $ps->execute([$org]);
            $products = $ps->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $rows = [];
            $fm   = $this->freeModel($pdo);

            if ($fm['table'] === 'dms_free_stock_moves') {
                $st = $pdo->prepare("
                    SELECT f.id,
                           f.moved_at,
                           f.ref_no,
                           f.note,
                           f.qty_out,
                           p.name AS product_name,
                           $sku AS sku
                      FROM dms_free_stock_moves f
                      JOIN dms_products p ON p.org_id=f.org_id AND p.id=f.product_id
                     WHERE f.org_id=? AND COALESCE(f.qty_out,0)>0
                  ORDER BY f.id DESC
                     LIMIT 200
                ");
                $st->execute([$org]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) $r['qty'] = (float)($r['qty_out'] ?? 0);
                unset($r);

            } elseif ($fm['table'] === 'dms_free_stock') {
                $col = $fm['mode'] === 'direction'
                    ? "direction='out'"
                    : ($fm['mode'] === 'type' ? "type='issue'" : "qty<0");

                $st = $pdo->prepare("
                    SELECT f.id,
                           f.moved_at,
                           f.ref_no,
                           f.note,
                           f.qty,
                           p.name AS product_name,
                           $sku AS sku
                      FROM dms_free_stock f
                      JOIN dms_products p ON p.org_id=f.org_id AND p.id=f.product_id
                     WHERE f.org_id=? AND $col
                  ORDER BY f.id DESC
                     LIMIT 200
                ");
                $st->execute([$org]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) $r['qty'] = abs((float)($r['qty'] ?? 0));
                unset($r);
            }

            $this->view('inventory/free-issue', [
                'title'       => 'Free Stock — Issue',
                'products'    => $products,
                'rows'        => $rows,
                'today'       => date('Y-m-d'),
                'module_base' => $this->moduleBase($c),
                'active'      => 'inventory',
                'subactive'   => 'inventory.free.issue',
            ], $c);

        } catch (\Throwable $e) {
            $this->abort500('Free issue error: ' . $e->getMessage());
        }
    }

    /** POST /free/issue */
    public function storeFreeIssue(?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        try {
            $pdo = $this->pdo();
            $org = $this->orgId($c);

            $pid     = (int)($_POST['product_id'] ?? 0);
            $qty     = (float)($_POST['qty'] ?? 0);
            $note    = trim((string)($_POST['note'] ?? 'Free issue'));
            $date    = (string)($_POST['date'] ?? date('Y-m-d'));
            $postInv = (int)($_POST['post_inventory'] ?? 0) === 1;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            if ($pid <= 0 || $qty <= 0) {
                $this->abort400('Select product and positive quantity.');
                return;
            }

            $pdo->beginTransaction();

            $fm      = $this->freeModel($pdo);
            $movedAt = $date . ' 00:00:00';

            if ($fm['table'] === 'dms_free_stock_moves') {
                $cols   = ['org_id', 'product_id', 'qty_in', 'qty_out', 'ref_no', 'note', 'moved_at'];
                $vals   = ['?',      '?',          '0',      '?',       'NULL',  '?',    '?'];
                $params = [$org,     $pid,                   $qty,      $note,   $movedAt];

                if ($this->hasColumn($pdo, 'dms_free_stock_moves', 'created_at')) {
                    $cols[] = 'created_at';
                    $vals[] = 'NOW()';
                }
                if ($this->hasColumn($pdo, 'dms_free_stock_moves', 'updated_at')) {
                    $cols[] = 'updated_at';
                    $vals[] = 'NOW()';
                }

                $sql = "INSERT INTO dms_free_stock_moves (" . implode(',', $cols) . ")
                        VALUES (" . implode(',', $vals) . ")";
                $ins = $pdo->prepare($sql);
                $ins->execute($params);

            } elseif ($fm['table'] === 'dms_free_stock') {
                $cols   = ['org_id', 'product_id', 'qty', 'ref_no', 'note', 'moved_at'];
                $vals   = ['?',      '?',          '?',   'NULL',   '?',    '?'];
                $params = [$org,     $pid,         -$qty,         $note,   $movedAt];

                if ($fm['mode'] === 'direction') {
                    $cols[] = 'direction';
                    $vals[] = "'out'";
                } elseif ($fm['mode'] === 'type') {
                    $cols[] = 'type';
                    $vals[] = "'issue'";
                }

                if ($this->hasColumn($pdo, 'dms_free_stock', 'created_at')) {
                    $cols[] = 'created_at';
                    $vals[] = 'NOW()';
                }
                if ($this->hasColumn($pdo, 'dms_free_stock', 'updated_at')) {
                    $cols[] = 'updated_at';
                    $vals[] = 'NOW()';
                }

                $sql = "INSERT INTO dms_free_stock (" . implode(',', $cols) . ")
                        VALUES (" . implode(',', $vals) . ")";
                $ins = $pdo->prepare($sql);
                $ins->execute($params);
            }

            if ($postInv) {
                $this->createStockMove($pdo, $org, $pid, 0, $qty, 'free_issue', $note, $date);
                $this->applyBalanceDelta($pdo, $org, $pid, -$qty, null);
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($c) . '/free/issue');

        } catch (\Throwable $e) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->abort500('Free issue save error: ' . $e->getMessage());
        }
    }
}