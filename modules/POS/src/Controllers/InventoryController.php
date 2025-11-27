<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class InventoryController extends BaseController
{
    /* ================================================================
 * A) Small schema helpers  (must be protected, never private!)
 * ================================================================ */

 /** information_schema — always use lower-case table/column names */
protected function hasTable(PDO $pdo, string $table): bool
{
    $t = strtolower($table);
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND LOWER(table_name) = ?"
    );
    $st->execute([$t]);
    return (int)$st->fetchColumn() > 0;
}

protected function hasCol(PDO $pdo, string $table, string $col): bool
{
    $t = strtolower($table);
    $c = strtolower($col);
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND LOWER(table_name)  = ?
           AND LOWER(column_name) = ?"
    );
    $st->execute([$t, $c]);
    return (int)$st->fetchColumn() > 0;
}

/** First existing candidate column. */
protected function pickCol(PDO $pdo, string $table, array $candidates, bool $required = true): ?string
{
    foreach ($candidates as $c) {
        if ($this->hasCol($pdo, $table, $c)) return $c;
    }
    if ($required) {
        throw new \RuntimeException(
            "Required column missing in {$table}: ".implode('|',$candidates)
        );
    }
    return null;
}

/** Add a column if it doesn't exist. */
protected function ensureCol(PDO $pdo, string $table, string $col, string $definition): void
{
    if ($this->hasCol($pdo, $table, $col)) return;
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
}

/** Create minimal tables if missing; add commonly used columns if absent. */
protected function ensureSchema(PDO $pdo): void
{
    /* ----------------- pos_products ----------------- */
    if (!$this->hasTable($pdo, 'pos_products')) {
        $pdo->exec("
            CREATE TABLE `pos_products` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `org_id` BIGINT UNSIGNED NOT NULL,
              `sku` VARCHAR(64) NOT NULL,
              `barcode` VARCHAR(64) NULL,
              `name` VARCHAR(255) NOT NULL,
              `category_id` BIGINT UNSIGNED NULL,
              `sale_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
              `cost_price` DECIMAL(14,2) NULL,
              `stock_on_hand` DECIMAL(18,3) NOT NULL DEFAULT 0,
              `low_stock_threshold` INT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `track_stock` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` DATETIME NULL,
              `updated_at` DATETIME NULL,
              PRIMARY KEY (`id`),
              KEY `ix_org` (`org_id`),
              KEY `ix_org_sku` (`org_id`,`sku`),
              KEY `ix_org_cat` (`org_id`,`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        $this->ensureCol($pdo, 'pos_products', 'org_id',             "BIGINT UNSIGNED NOT NULL");
        $this->ensureCol($pdo, 'pos_products', 'sku',                "VARCHAR(64) NOT NULL");
        $this->ensureCol($pdo, 'pos_products', 'name',               "VARCHAR(255) NOT NULL");
        $this->ensureCol($pdo, 'pos_products', 'stock_on_hand',      "DECIMAL(18,3) NOT NULL DEFAULT 0");
        $this->ensureCol($pdo, 'pos_products', 'low_stock_threshold',"INT NULL");
        $this->ensureCol($pdo, 'pos_products', 'is_active',          "TINYINT(1) NOT NULL DEFAULT 1");
        $this->ensureCol($pdo, 'pos_products', 'track_stock',        "TINYINT(1) NOT NULL DEFAULT 1");

        // ensure some price column exists
        $hasAnyPrice =
            $this->hasCol($pdo,'pos_products','sale_price')    ||
            $this->hasCol($pdo,'pos_products','selling_price') ||
            $this->hasCol($pdo,'pos_products','retail_price')  ||
            $this->hasCol($pdo,'pos_products','unit_price')    ||
            $this->hasCol($pdo,'pos_products','price')         ||
            $this->hasCol($pdo,'pos_products','mrp');

        if (!$hasAnyPrice) {
            $this->ensureCol($pdo,'pos_products','sale_price',
                "DECIMAL(14,2) NOT NULL DEFAULT 0.00");
        }
    }

    /* ----------------- pos_categories ----------------- */
    if (!$this->hasTable($pdo, 'pos_categories')) {
        $pdo->exec("
            CREATE TABLE `pos_categories` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `org_id` BIGINT UNSIGNED NOT NULL,
              `name` VARCHAR(120) NOT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (`id`),
              KEY `ix_org` (`org_id`),
              KEY `ix_org_active` (`org_id`,`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /* ----------------- pos_stock_moves ----------------- */
    if (!$this->hasTable($pdo, 'pos_stock_moves')) {
        $pdo->exec("
            CREATE TABLE `pos_stock_moves` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `org_id` BIGINT UNSIGNED NOT NULL,
              `product_id` BIGINT UNSIGNED NOT NULL,
              `direction` ENUM('in','out') NOT NULL,
              `qty` DECIMAL(18,3) NOT NULL,
              `reason` VARCHAR(40) NOT NULL DEFAULT 'adjustment',
              `sale_id` BIGINT UNSIGNED NULL,
              `note` VARCHAR(255) NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `ix_org` (`org_id`),
              KEY `ix_org_prod` (`org_id`,`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

    /* ================================================================
     * B) CSRF helpers (compatible with Shared\Csrf when present)
     * ================================================================ */
    private function csrfToken(): string
    {
        if (class_exists('\\Shared\\Csrf')) {
            try { return (string)\Shared\Csrf::token(); } catch (Throwable) {}
        }
        return '';
    }
    private function csrfValid(string $t): bool
    {
        if (class_exists('\\Shared\\Csrf')) {
            try { return \Shared\Csrf::verify($t); } catch (Throwable) { return false; }
        }
        return true;
    }

    /* ================================================================
     * C) Pages
     * ================================================================ */

   /** GET /inventory */
public function index(array $ctx = []): void
{
    try {
        $c = $this->ctx($ctx);
        $orgId = (int)($c['org_id'] ?? ($c['org']['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0)));
        if ($orgId <= 0) {
            throw new \RuntimeException('Tenant (org_id) missing in context');
        }

        $pdo = $this->pdo();
        $this->ensureSchema($pdo); // auto-create missing tables/columns

        $tbl = 'pos_products';

        // Columns we *might* have
        $stockCol  = $this->pickCol($pdo, $tbl, ['stock_on_hand','stock','qty_on_hand'], true);
        $lowCol    = $this->pickCol($pdo, $tbl, ['low_stock_threshold','min_stock'], false);
        $activeCol = $this->pickCol($pdo, $tbl, ['is_active','active'], false);
        $trackCol  = $this->pickCol($pdo, $tbl, ['track_stock','manage_stock','track_inventory'], false);

        // Build a COALESCE() price expression from whatever exists
        $priceCandidates = ['sale_price','selling_price','retail_price','unit_price','price','mrp'];
        $priceExprParts = [];
        foreach ($priceCandidates as $pc) {
            if ($this->hasCol($pdo, $tbl, $pc)) {
                $priceExprParts[] = "p.`{$pc}`";
            }
        }
        $priceExpr = $priceExprParts
            ? ('COALESCE('.implode(',', $priceExprParts).',0.00)')
            : '0.00';

        // Filters/paging
        $q    = trim((string)($_GET['q'] ?? ''));
        $cat  = trim((string)($_GET['category'] ?? ''));
        $stat = trim((string)($_GET['stat'] ?? ''));     // out|low|ok|''
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = max(10, min(100, (int)($_GET['per'] ?? 20)));
        $off  = ($page - 1) * $per;

        $cats = $this->rows(
            "SELECT id,name FROM pos_categories WHERE org_id=:o ORDER BY name",
            [':o'=>$orgId]
        );

        $bind  = [':o'=>$orgId];
        $where = "WHERE p.org_id = :o";
        if ($activeCol) {
            $where .= " AND p.`{$activeCol}` = 1";
        }
        if ($q !== '') {
            $where .= " AND (p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q)";
            $bind[':q'] = "%{$q}%";
        }
        if ($cat !== '') {
            $where .= " AND p.category_id = :c";
            $bind[':c'] = $cat;
        }
        if ($stat === 'out') {
            $where .= " AND p.`{$stockCol}` <= 0";
        } elseif ($stat === 'low' && $lowCol) {
            $where .= " AND p.`{$stockCol}` < COALESCE(p.`{$lowCol}`,0)";
        } elseif ($stat === 'ok') {
            $where .= $lowCol
                ? " AND p.`{$stockCol}` > 0 AND p.`{$stockCol}` >= COALESCE(p.`{$lowCol}`,0)"
                : " AND p.`{$stockCol}` > 0";
        }

        $total = (int)$this->val("SELECT COUNT(*) FROM {$tbl} p {$where}", $bind);
        $pages = max(1, (int)ceil($total / $per));

        // 🔥 IMPORTANT: also select low_stock_threshold_like so the view shows it
        $lowSelect = $lowCol
            ? "p.`{$lowCol}` AS low_stock_threshold_like,"
            : "0 AS low_stock_threshold_like,";

        $rows = $this->rows("
            SELECT
              p.id,
              p.sku,
              p.barcode,
              p.name,
              p.category_id,
              {$priceExpr} AS price_like,
              p.`{$stockCol}` AS stock_on_hand_like,
              {$lowSelect}
              c.name AS category_name,
              CASE
                WHEN p.`{$stockCol}` <= 0 THEN 'out'
                ".($lowCol ? "WHEN p.`{$stockCol}` < COALESCE(p.`{$lowCol}`,0) THEN 'low'" : "")."
                ELSE 'ok'
              END AS stock_status
            FROM {$tbl} p
            LEFT JOIN pos_categories c
              ON c.id = p.category_id AND c.org_id = p.org_id
            {$where}
            ORDER BY p.name ASC
            LIMIT {$per} OFFSET {$off}
        ", $bind);

        $this->view(
            $c['module_dir'].'/Views/inventory/index.php',
            [
                'title'    => 'Inventory',
                'base'     => $c['module_base'],
                'slug'     => $c['slug'] ?? '',
                'rows'     => $rows,
                'q'        => $q,
                'category' => $cat,
                'cats'     => $cats,
                'stat'     => $stat,
                'page'     => $page,
                'per'      => $per,
                'total'    => $total,
                'pages'    => $pages,

                // ✅ pass CSRF token so adjust() no longer returns "CSRF"
                'csrf'     => $this->csrfToken(),

                '_resolved' => [
                    'stock'  => $stockCol,
                    'low'    => $lowCol,
                    'active' => $activeCol,
                    'track'  => $trackCol,
                ],
            ],
            'shell'
        );
    } catch (Throwable $e) {
        $this->oops('Inventory index failed', $e);
    }
}
  
  
      /** GET /inventory/adjust */
    public function adjustForm(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSchema($pdo);
            $orgId = (int)$this->requireOrg();

            $productId = (int)($_GET['product_id'] ?? 0);

            $product = null;
            if ($productId > 0) {
                $product = $this->row(
                    "SELECT id, sku, name, stock_on_hand AS stock
                     FROM pos_products
                     WHERE org_id=:o AND id=:id",
                    [':o' => $orgId, ':id' => $productId]
                );
            }

            // If not coming from a row link, just give them a dropdown of products
            if (!$product) {
                $products = $this->rows(
                    "SELECT id, sku, name
                     FROM pos_products
                     WHERE org_id=:o
                     ORDER BY name ASC
                     LIMIT 200",
                    [':o' => $orgId]
                );
            } else {
                $products = [];
            }

            $this->view(
                $c['module_dir'] . '/Views/inventory/adjust.php',
                [
                    'title'    => 'Adjust Inventory',
                    'base'     => $c['module_base'],
                    'slug'     => $c['slug'],
                    'csrf'     => $this->csrfToken(),
                    'product'  => $product,
                    'products' => $products,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Inventory adjust form failed', $e);
        }
    }

    /** POST /inventory/adjust */
        /** POST /inventory/adjust */
    public function adjust(array $ctx = []): void
    {
        try {
            if (!$this->csrfValid((string)($_POST['_csrf'] ?? ''))) {
                http_response_code(419);
                echo 'CSRF';
                return;
            }

            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $this->ensureSchema($pdo);

            $orgId = (int)$this->requireOrg();
            $pid    = (int)($_POST['product_id'] ?? 0);
            $delta  = (float)($_POST['delta'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? 'Manual adjustment'));
            if ($pid <= 0 || $delta == 0.0) {
                http_response_code(422);
                echo 'Invalid payload';
                return;
            }

            $tbl      = 'pos_products';
            $stockCol = $this->pickCol($pdo, $tbl, ['stock_on_hand','stock','qty_on_hand'], true);
            $trackCol = $this->pickCol($pdo, $tbl, ['track_stock','manage_stock','track_inventory'], false);

            $this->begin();
            $row = $this->row(
                "SELECT ".($trackCol?"`{$trackCol}`,":"")."`{$stockCol}` AS stock
                 FROM {$tbl} WHERE org_id=:o AND id=:i FOR UPDATE",
                [':o'=>$orgId, ':i'=>$pid]
            );
            if (!$row) throw new \RuntimeException('Product not found');

            $track   = $trackCol ? ((int)$row[$trackCol] === 1) : true;
            $current = (float)$row['stock'];
            if ($track && $delta < 0 && ($current + $delta) < 0) {
                throw new \RuntimeException('Insufficient stock for decrement');
            }

            $this->exec(
                "UPDATE {$tbl} SET `{$stockCol}` = `{$stockCol}` + :d, updated_at=NOW()
                 WHERE org_id=:o AND id=:i",
                [':d'=>$delta, ':o'=>$orgId, ':i'=>$pid]
            );

            $this->exec(
                "INSERT INTO pos_stock_moves (org_id, product_id, direction, qty, reason, sale_id, note, created_at)
                 VALUES (:o,:p,:dir,:q,'adjustment',NULL,:note,NOW())",
                [':o'=>$orgId, ':p'=>$pid, ':dir'=>($delta>=0?'in':'out'), ':q'=>abs($delta), ':note'=>$reason]
            );

            $this->commit();
            $this->redirect(rtrim($c['module_base'],'/').'/inventory');
        } catch (Throwable $e) {
            $this->rollBack();
            $this->oops('Inventory adjust failed', $e);
        }
    }

    /** GET /inventory/adjustments */
    public function adjustments(array $ctx = []): void
    {
        try {
            $c     = $this->ctx($ctx);
            $pdo   = $this->pdo();
            $this->ensureSchema($pdo);
            $orgId = (int)$this->requireOrg();

            $page = max(1, (int)($_GET['page'] ?? 1));
            $per  = 25; $off = ($page - 1) * $per;

            $total = (int)$this->val(
                "SELECT COUNT(*) FROM pos_stock_moves WHERE org_id=:o AND reason='adjustment'",
                [':o'=>$orgId]
            );

            $rows = $this->rows(
                "SELECT m.id,m.product_id,m.direction,m.qty,m.reason,m.note,m.created_at,
                        p.sku,p.name
                 FROM pos_stock_moves m
                 JOIN pos_products p ON p.id=m.product_id AND p.org_id=m.org_id
                 WHERE m.org_id=:o AND m.reason='adjustment'
                 ORDER BY m.created_at DESC, m.id DESC
                 LIMIT {$per} OFFSET {$off}",
                [':o'=>$orgId]
            );

            $this->view($c['module_dir'].'/Views/inventory/adjustments.php', [
                'title'=>'Adjustments','base'=>$c['module_base'],'slug'=>$c['slug'],
                'csrf'=>$this->csrfToken(),'rows'=>$rows,'page'=>$page,'per'=>$per,
                'total'=>$total,'pages'=>max(1,(int)ceil($total/max(1,$per))),
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Inventory adjustments failed', $e);
        }
    }

    /** GET /inventory/transfers (stub UI) */
    public function transfers(array $ctx = []): void
    {
        $c = $this->ctx($ctx);
        $this->view($c['module_dir'].'/Views/inventory/transfers.php', [
            'title'=>'Transfers','base'=>$c['module_base'],'slug'=>$c['slug'],'csrf'=>$this->csrfToken(),
        ], 'shell');
    }

    /** GET /inventory/aging (stub UI) */
    public function aging(array $ctx = []): void
    {
        $c = $this->ctx($ctx);
        $this->view($c['module_dir'].'/Views/inventory/aging.php', [
            'title'=>'Inventory Aging','base'=>$c['module_base'],'slug'=>$c['slug'],'rows'=>[],
        ], 'shell');
    }

    /** GET /inventory/low-stock */
public function lowStock(array $ctx = []): void
{
    try {
        $c   = $this->ctx($ctx);
        $pdo = $this->pdo();
        $this->ensureSchema($pdo);
        $orgId = (int)$this->requireOrg();

        $tbl       = 'pos_products';
        $stockCol  = $this->pickCol($pdo, $tbl, ['stock_on_hand','stock','qty_on_hand'], true);
        $lowCol    = $this->pickCol($pdo, $tbl, ['low_stock_threshold','min_stock'], false);
        $activeCol = $this->pickCol($pdo, $tbl, ['is_active','active'], false);

        $bind  = [':o'=>$orgId];
        $where = "WHERE p.org_id=:o".($activeCol ? " AND p.`{$activeCol}`=1" : "");
        $where .= $lowCol
            ? " AND p.`{$stockCol}` < COALESCE(p.`{$lowCol}`,0)"
            : " AND p.`{$stockCol}` <= 0";

        $total = (int)$this->val("SELECT COUNT(*) FROM {$tbl} p {$where}", $bind);

        $rows = $this->rows("
            SELECT p.id,p.sku,p.name,p.category_id,
                   p.`{$stockCol}` AS stock_on_hand_like,
                   ".($lowCol
                        ? "p.`{$lowCol}` AS low_stock_threshold_like,"
                        : "0 AS low_stock_threshold_like,")."
                   c.name AS category_name,
                   CASE
                     WHEN p.`{$stockCol}` <= 0 THEN 'out'
                     ".($lowCol ? "WHEN p.`{$stockCol}` < COALESCE(p.`{$lowCol}`,0) THEN 'low'" : "")."
                     ELSE 'ok'
                   END AS stock_status
            FROM {$tbl} p
            LEFT JOIN pos_categories c
              ON c.id=p.category_id AND c.org_id=p.org_id
            {$where}
            ORDER BY p.name ASC
            LIMIT 100 OFFSET 0
        ", $bind);

        $this->view(
            $c['module_dir'].'/Views/inventory/low-stock.php',
            [
                'title' => 'Low Stock',
                'base'  => $c['module_base'],
                'slug'  => $c['slug'] ?? '',
                'rows'  => $rows,
                'total' => $total,

                // ✅ pass CSRF token so the +1 button works
                'csrf'  => $this->csrfToken(),
            ],
            'shell'
        );
    } catch (Throwable $e) {
        $this->oops('Inventory low-stock failed', $e);
    }
}

    /** GET /inventory/movements */
    public function movements(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureSchema($pdo);
            $orgId = (int)$this->requireOrg();

            $page = max(1, (int)($_GET['page'] ?? 1));
            $per  = 25; $off = ($page - 1) * $per;

            $total = (int)$this->val(
                "SELECT COUNT(*) FROM pos_stock_moves WHERE org_id=:o",
                [':o'=>$orgId]
            );

            $rows = $this->rows(
                "SELECT m.id,m.product_id,m.direction,m.qty,m.reason,m.note,m.created_at,
                        p.sku,p.name
                 FROM pos_stock_moves m
                 JOIN pos_products p ON p.id=m.product_id AND p.org_id=m.org_id
                 WHERE m.org_id=:o
                 ORDER BY m.created_at DESC, m.id DESC
                 LIMIT {$per} OFFSET {$off}",
                [':o'=>$orgId]
            );

            $this->view($c['module_dir'].'/Views/inventory/movements.php', [
                'title'=>'Stock Movements','base'=>$c['module_base'],'slug'=>$c['slug'],
                'rows'=>$rows,'page'=>$page,'per'=>$per,'total'=>$total,
                'pages'=>max(1,(int)ceil($total/max(1,$per))),
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Inventory movements failed', $e);
        }
    }
}