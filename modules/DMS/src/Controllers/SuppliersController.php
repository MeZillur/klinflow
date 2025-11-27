<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class SuppliersController extends BaseController
{
    /* ───────────────────────── Schema helpers (safe) ───────────────────────── */

    private function dbName(PDO $pdo): string {
        try { return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); }
        catch (\Throwable $e) { return ''; }
    }

    private function hasTable(PDO $pdo, string $table): bool {
        try {
            $db = $this->dbName($pdo);
            if ($db !== '') {
                $s = $pdo->prepare(
                    "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1"
                );
                $s->execute([$db, $table]);
                return (bool)$s->fetchColumn();
            }
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Throwable $e) { return false; }
    }

    private function hasCol(PDO $pdo, string $table, string $col): bool {
        try {
            $db = $this->dbName($pdo);
            if ($db !== '') {
                $s = $pdo->prepare(
                    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1"
                );
                $s->execute([$db, $table, $col]);
                return (bool)$s->fetchColumn();
            }
            $s = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $s->execute([$col]);
            return (bool)$s->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return false; }
    }

    private function hasIndex(PDO $pdo, string $table, string $index): bool {
        try {
            $db = $this->dbName($pdo);
            if ($db !== '') {
                $s = $pdo->prepare(
                    "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1"
                );
                $s->execute([$db, $table, $index]);
                return (bool)$s->fetchColumn();
            }
            $s = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name=?");
            $s->execute([$index]);
            return (bool)$s->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return false; }
    }

    /** ALTER ADD COLUMN only if missing; ignore 1060 duplicate-column races */
    private function addColumnIfMissing(PDO $pdo, string $table, string $col, string $ddl): void {
        if ($this->hasCol($pdo, $table, $col)) return;
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1060) return; // duplicate column
            throw $e;
        }
    }

    /** ADD UNIQUE only if missing; ignore 1061 duplicate key name races */
    private function addUniqueIfMissing(PDO $pdo, string $table, string $keyName, string $cols): void {
        if ($this->hasIndex($pdo, $table, $keyName)) return;
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$keyName}` ({$cols})");
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1061) return; // duplicate key name
            throw $e;
        }
    }

    /** Ensure base table + optional columns/indexes exist (idempotent) */
    private function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `dms_suppliers` (
              `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `org_id` INT(11) NOT NULL,
              `name` VARCHAR(150) NOT NULL,
              `phone` VARCHAR(50) DEFAULT NULL,
              `email` VARCHAR(120) DEFAULT NULL,
              `address` TEXT DEFAULT NULL,
              `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
              `status` VARCHAR(20) DEFAULT 'active',
              `created_at` DATETIME DEFAULT NULL,
              `updated_at` DATETIME DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `ix_sup_org` (`org_id`),
              KEY `ix_sup_org_name` (`org_id`,`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Optional/newer columns (safe if already exist)
        $this->addColumnIfMissing($pdo, 'dms_suppliers', 'code',       "VARCHAR(32) DEFAULT NULL AFTER `id`");
        $this->addColumnIfMissing($pdo, 'dms_suppliers', 'tax_reg_no', "VARCHAR(80) DEFAULT NULL");

        // Uniqueness
        $this->addUniqueIfMissing($pdo, 'dms_suppliers', 'uq_sup_org_name', '`org_id`,`name`');
        $this->addUniqueIfMissing($pdo, 'dms_suppliers', 'uq_sup_org_code', '`org_id`,`code`');
    }

    private function find(PDO $pdo, int $orgId, int $id): ?array
    {
        $this->ensureTable($pdo);
        $st = $pdo->prepare("SELECT * FROM dms_suppliers WHERE org_id=? AND id=? LIMIT 1");
        $st->execute([$orgId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Generate SUP-YYYY-0001 sequence per org per year */
    private function nextCode(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = "SUP-{$year}-";
        // (Length depends on prefix)  e.g., SUP-2025-0001 → tail starts at pos 10
        $st = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(code, 10) AS UNSIGNED)) AS mx
            FROM dms_suppliers
            WHERE org_id=? AND code LIKE ?
        ");
        $st->execute([$orgId, $prefix.'%']);
        $mx = (int)($st->fetchColumn() ?: 0);
        return $prefix . str_pad((string)($mx+1), 4, '0', STR_PAD_LEFT);
    }

    /* ───────────────────────────── Pages ───────────────────────────── */

    /** GET {base}/suppliers */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $this->ensureTable($pdo);

        $q = $pdo->prepare("
            SELECT id, code, name, phone, email, status, opening_balance
            FROM dms_suppliers
            WHERE org_id=?
            ORDER BY name
        ");
        $q->execute([$orgId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('suppliers/index', [
            'title'       => 'Suppliers',
            'rows'        => $rows,
            'module_base' => $this->moduleBase($ctx),
        ], $ctx);
    }

    /** GET {base}/suppliers/create */
    public function create(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $this->ensureTable($pdo);

        $prefill = ['code' => $this->nextCode($pdo, $orgId)];

        $this->view('suppliers/form', [
            'title'       => 'New Supplier',
            'row'         => $prefill,
            'module_base' => $this->moduleBase($ctx),
        ], $ctx);
    }

    /** POST {base}/suppliers */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $base  = $this->moduleBase($ctx);
        $this->ensureTable($pdo);

        $name   = trim((string)($_POST['name'] ?? ''));
        $phone  = trim((string)($_POST['phone'] ?? ''));
        $email  = trim((string)($_POST['email'] ?? ''));
        $addr   = trim((string)($_POST['address'] ?? ''));
        $tax    = trim((string)($_POST['tax_reg_no'] ?? ''));
        $ob     = is_numeric($_POST['opening_balance'] ?? null) ? (float)$_POST['opening_balance'] : 0.00;
        $status = strtolower((string)($_POST['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';

        if ($name === '') {
            $_SESSION['flash_err'] = 'Supplier name is required.';
            $this->redirect($base.'/suppliers/create');
        }

        // Always generate on server (avoid code collisions)
        $code = $this->nextCode($pdo, $orgId);

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("
                INSERT INTO dms_suppliers
                (org_id, code, name, phone, email, address, tax_reg_no, opening_balance, status, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())
            ");
            $ins->execute([$orgId, $code, $name, $phone, $email, $addr, $tax, $ob, $status]);
            $pdo->commit();
            $_SESSION['flash_ok'] = 'Supplier created.';
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                try { $pdo->rollBack(); } catch (\Throwable $__) {}
                $pdo->beginTransaction();
                $code = $this->nextCode($pdo, $orgId);
                $ins = $pdo->prepare("
                    INSERT INTO dms_suppliers
                    (org_id, code, name, phone, email, address, tax_reg_no, opening_balance, status, created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())
                ");
                $ins->execute([$orgId, $code, $name, $phone, $email, $addr, $tax, $ob, $status]);
                $pdo->commit();
                $_SESSION['flash_ok'] = 'Supplier created.';
            } else {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash_err'] = 'Failed to create supplier.';
            }
        }

        $this->redirect($base.'/suppliers');
    }

    /** GET {base}/suppliers/{id}/edit */
    public function edit(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $row   = $this->find($pdo, $orgId, $id);
        if (!$row) { $this->redirect($this->moduleBase($ctx).'/suppliers'); }

        $this->view('suppliers/form', [
            'title'       => 'Edit Supplier',
            'row'         => $row,
            'module_base' => $this->moduleBase($ctx),
        ], $ctx);
    }

    /** POST {base}/suppliers/{id} */
    public function update(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $base  = $this->moduleBase($ctx);
        if (!$this->find($pdo, $orgId, $id)) {
            $this->redirect($base.'/suppliers');
        }

        $name   = trim((string)($_POST['name'] ?? ''));
        $phone  = trim((string)($_POST['phone'] ?? ''));
        $email  = trim((string)($_POST['email'] ?? ''));
        $addr   = trim((string)($_POST['address'] ?? ''));
        $tax    = trim((string)($_POST['tax_reg_no'] ?? ''));
        $ob     = is_numeric($_POST['opening_balance'] ?? null) ? (float)$_POST['opening_balance'] : 0.00;
        $status = strtolower((string)($_POST['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';

        if ($name === '') {
            $_SESSION['flash_err'] = 'Supplier name is required.';
            $this->redirect($base."/suppliers/{$id}/edit");
        }

        try {
            $st = $pdo->prepare("
                UPDATE dms_suppliers
                SET name=?, phone=?, email=?, address=?, tax_reg_no=?, opening_balance=?, status=?, updated_at=NOW()
                WHERE org_id=? AND id=? LIMIT 1
            ");
            $st->execute([$name, $phone, $email, $addr, $tax, $ob, $status, $orgId, $id]);
            $_SESSION['flash_ok'] = 'Supplier updated.';
        } catch (\PDOException $e) {
            $_SESSION['flash_err'] = 'Update failed.';
        }

        $this->redirect($base.'/suppliers');
    }

    /** POST {base}/suppliers/{id}/delete (soft → mark inactive) */
    public function destroy(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $this->ensureTable($pdo);

        $st = $pdo->prepare("UPDATE dms_suppliers SET status='inactive', updated_at=NOW() WHERE org_id=? AND id=? LIMIT 1");
        $st->execute([$orgId, $id]);
        $_SESSION['flash_ok'] = 'Supplier disabled.';
        $this->redirect($this->moduleBase($ctx).'/suppliers');
    }

    /* ───────────────────────────── Show / Drilldown ───────────────────────────── */

    /** GET {base}/suppliers/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $supplier = $this->find($pdo, $orgId, $id);
        if (!$supplier) {
            $this->view('suppliers/show', [
                'title'        => 'Supplier',
                'supplier'     => null,
                'stats'        => [],
                'recent_bills' => [],
                'recentPurchases' => [],
                'products'     => [],
                'recentProducts' => [],
                'module_base'  => $this->moduleBase($ctx),
            ], $ctx);
            return;
        }

        // helpers bound to current connection
        $hasTable = fn(string $t): bool => $this->hasTable($pdo, $t);
        $hasCol   = fn(string $t, string $c): bool => $this->hasCol($pdo, $t, $c);
        $pick = function(string $table, array $cands, ?string $fallback = null) use ($hasCol): ?string {
            foreach ($cands as $c) if ($c && $hasCol($table,$c)) return $c;
            return $fallback;
        };

        $purchT = 'dms_purchases';
        $itemsT = 'dms_purchase_items';

        $stats = [
            'purchases_count' => 0,
            'purchases_total' => 0.0,
            'products_count'  => 0,
            'last_purchase_at'=> null,
        ];
        $recent = [];
        $products = [];

        if ($hasTable($purchT)) {
            $billCol   = $pick($purchT, ['bill_no','invoice_no','ref_no','number','code'], 'id');
            $dateCol   = $pick($purchT, ['bill_date','purchase_date','date','created_at'], 'created_at');
            $statusCol = $pick($purchT, ['status','state','bill_status'], null);

            // Header-preferred amount
            $hdr = $pick($purchT, ['grand_total','total','net_total','total_amount','amount','payable','bill_total'], null);
            if ($hdr) {
                $headerAmountExpr = "COALESCE(pu.`{$hdr}`,0)";
            } else {
                $bits = [];
                foreach (['subtotal','tax_total','vat_total','other_charges'] as $c) {
                    if ($hasCol($purchT,$c)) $bits[] = "COALESCE(pu.`{$c}`,0)";
                }
                $headerAmountExpr = $bits ? implode(' + ', $bits) : '0';
                if ($hasCol($purchT,'discount')) $headerAmountExpr .= ' - COALESCE(pu.`discount`,0)';
            }

            // Item line expr (fallback)
            $qtyCol   = $pick($itemsT, ['qty','quantity','qty_in','qty_buy','qty_purchased'], null);
            $priceCol = $pick($itemsT, ['unit_price','price','rate','purchase_price'], null);
            $lineCol  = $pick($itemsT, ['line_total','total','amount','net_total','extended_total','subtotal'], null);
            if ($lineCol) {
                $itemLineExpr = "COALESCE(i.`{$lineCol}`,0)";
            } else {
                $itemLineExpr = ($qtyCol && $priceCol)
                    ? "COALESCE(i.`{$priceCol}`,0) * COALESCE(i.`{$qtyCol}`,0)"
                    : "0";
            }

            // Subquery of item sums per purchase
            $itemSumSql = "
                SELECT i.org_id, i.purchase_id, SUM({$itemLineExpr}) AS item_sum
                FROM `{$itemsT}` i
                GROUP BY i.org_id, i.purchase_id
            ";

            // Stats (count, sum of COALESCE(header,item_sum), last date)
            $agg = $pdo->prepare("
                SELECT
                    COUNT(*) AS cnt,
                    COALESCE(SUM(COALESCE(hdr_amt, item_sum, 0)),0) AS total_amt,
                    MAX(`{$dateCol}`) AS last_at
                FROM (
                    SELECT
                        pu.id,
                        pu.`{$dateCol}`,
                        {$headerAmountExpr} AS hdr_amt,
                        it.item_sum
                    FROM `{$purchT}` pu
                    LEFT JOIN ({$itemSumSql}) it
                      ON it.org_id = pu.org_id AND it.purchase_id = pu.id
                    WHERE pu.org_id=? AND pu.supplier_id=?
                ) x
            ");
            $agg->execute([$orgId, $id]);
            $aggr = $agg->fetch(PDO::FETCH_ASSOC) ?: ['cnt'=>0,'total_amt'=>0,'last_at'=>null];
            $stats['purchases_count'] = (int)($aggr['cnt'] ?? 0);
            $stats['purchases_total'] = (float)($aggr['total_amt'] ?? 0);
            $stats['last_purchase_at']= $aggr['last_at'] ?? null;

            // Recent purchases (with fallback amount per row)
            $statusExpr = $statusCol ? "pu.`{$statusCol}`" : "'pending'";
            $recentSql = "
                SELECT
                    pu.id,
                    pu.`{$billCol}` AS bill_no,
                    pu.`{$dateCol}` AS bill_date,
                    {$statusExpr}   AS status,
                    COALESCE({$headerAmountExpr}, it.item_sum, 0) AS amount
                FROM `{$purchT}` pu
                LEFT JOIN ({$itemSumSql}) it
                  ON it.org_id = pu.org_id AND it.purchase_id = pu.id
                WHERE pu.org_id=? AND pu.supplier_id=?
                ORDER BY pu.`{$dateCol}` DESC, pu.id DESC
                LIMIT 20
            ";
            $st = $pdo->prepare($recentSql);
            $st->execute([$orgId, $id]);
            $recent = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // Products supplied (qty+amount per product)
        if ($hasTable($purchT) && $hasTable($itemsT)) {
            $qtyCol   = $pick($itemsT, ['qty','quantity','qty_in','qty_buy','qty_purchased'], null);
            $priceCol = $pick($itemsT, ['unit_price','price','rate','purchase_price'], null);
            $lineCol  = $pick($itemsT, ['line_total','total','amount','net_total','extended_total','subtotal'], null);

            $qtyExpr  = $qtyCol ? "SUM(COALESCE(i.`{$qtyCol}`,0))" : "COUNT(*)";
            $lineExpr = $lineCol
                ? "SUM(COALESCE(i.`{$lineCol}`,0))"
                : (($priceCol && $qtyCol) ? "SUM(COALESCE(i.`{$priceCol}`,0) * COALESCE(i.`{$qtyCol}`,0))" : "0");

            $productNameExpr = "COALESCE(p.name, i.product_name)";

            $pi = $pdo->prepare("
                SELECT 
                    i.product_id,
                    {$productNameExpr} AS product_name,
                    {$qtyExpr}  AS total_qty,
                    {$lineExpr} AS total_amount
                FROM `{$itemsT}` i
                JOIN `{$purchT}` pu
                  ON pu.org_id=i.org_id AND pu.id=i.purchase_id
                LEFT JOIN dms_products p
                  ON p.org_id=i.org_id AND p.id=i.product_id
                WHERE i.org_id=? AND pu.supplier_id=?
                GROUP BY i.product_id, {$productNameExpr}
                ORDER BY total_amount DESC, product_name
                LIMIT 50
            ");
            $pi->execute([$orgId, $id]);
            $products = $pi->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // products_count KPI
            $cnt = $pdo->prepare("
                SELECT COUNT(DISTINCT i.product_id) AS c
                FROM `{$itemsT}` i
                JOIN `{$purchT}` pu ON pu.org_id=i.org_id AND pu.id=i.purchase_id
                WHERE i.org_id=? AND pu.supplier_id=?
            ");
            $cnt->execute([$orgId, $id]);
            $stats['products_count'] = (int)($cnt->fetchColumn() ?: 0);
        }

        $this->view('suppliers/show', [
            'title'            => 'Supplier',
            'supplier'         => $supplier,
            'stats'            => $stats,
            // legacy + new names (your view covers both)
            'recent_bills'     => $recent,
            'recentPurchases'  => $recent,
            'products'         => $products,
            'recentProducts'   => $products,
            'module_base'      => $this->moduleBase($ctx),
        ], $ctx);
    }

    /* ───────────────────────────── Typeahead JSON ───────────────────────────── */

    /** GET {base}/suppliers.lookup.json?q=... → {items:[{id,code,name,phone,email}]} */
    public function lookup(array $ctx): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $this->ensureTable($pdo);

        $q = trim((string)($_GET['q'] ?? ''));
        $sql  = "SELECT id, code, name, phone, email FROM dms_suppliers WHERE org_id=?";
        $args = [$orgId];
        if ($q !== '') {
            $like = "%{$q}%";
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR code LIKE ?)";
            array_push($args, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY name LIMIT 50";
        $st = $pdo->prepare($sql);
        $st->execute($args);
        echo json_encode(['items' => ($st->fetchAll(PDO::FETCH_ASSOC) ?: [])], JSON_UNESCAPED_UNICODE);
        exit;
    }
}