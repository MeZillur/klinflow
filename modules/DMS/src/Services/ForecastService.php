<?php
declare(strict_types=1);

namespace Modules\DMS\Services;

use PDO;

final class ForecastService
{
    /** ensure cache table */
    private static function ensureTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dms_demand_cache (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              sku_id INT UNSIGNED NOT NULL,
              sku_code VARCHAR(120) DEFAULT NULL,
              product_name VARCHAR(255) DEFAULT NULL,
              supplier_id INT UNSIGNED DEFAULT NULL,
              stock_on_hand DECIMAL(18,6) DEFAULT 0,
              on_order DECIMAL(18,6) DEFAULT 0,
              avg_daily DECIMAL(18,6) DEFAULT 0,
              lead_time_days INT DEFAULT 7,
              safety_days INT DEFAULT 3,
              rop DECIMAL(18,6) DEFAULT 0,
              suggested_qty DECIMAL(18,6) DEFAULT 0,
              computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY u1 (org_id, sku_id),
              KEY idx_org (org_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /** public: read cached forecast */
    public static function readCache(PDO $pdo, int $orgId): array {
        self::ensureTable($pdo);
        $q = $pdo->prepare("SELECT * FROM dms_demand_cache WHERE org_id=? ORDER BY product_name");
        $q->execute([$orgId]);
        return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** public: recompute & replace cache */
    public static function rebuild(PDO $pdo, int $orgId, array $opts): void {
        self::ensureTable($pdo);

        $lookback = max(14, (int)($opts['lookback_days'] ?? 90));
        $lead     = max(0,  (int)($opts['lead_time_days'] ?? 7));
        $safety   = max(0,  (int)($opts['safety_days'] ?? 3));
        $MOQ      = max(1,  (int)($opts['min_order_qty'] ?? 1));

        // --- Gather sales usage (defensive: table names may vary in your DB) ---
        // Expect: sales_items(si) join sales(s) with si.qty, si.product_id, s.org_id, s.status='issued/paid' and s.sale_date
        $sales = [];
        try {
            $st = $pdo->prepare("
                SELECT si.product_id AS sku_id,
                       SUM(si.quantity) / ? AS avg_daily
                  FROM sales_items si
                  JOIN sales s ON s.id = si.sale_id
                 WHERE s.org_id = ? AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                   AND s.status IN ('issued','paid','delivered','completed')
                 GROUP BY si.product_id
            ");
            $st->execute([$lookback, $orgId, $lookback]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $sales[(int)$r['sku_id']] = max(0.0, (float)$r['avg_daily']);
            }
        } catch (\Throwable) {
            // If tables not present, leave empty; UI will show none.
        }

        // --- Product master + supplier + stock (schema-neutral best effort) ---
        $products = [];
        try {
            $p = $pdo->prepare("
                SELECT p.id AS sku_id, p.code AS sku_code, p.name AS product_name,
                       p.supplier_id,
                       COALESCE(p.lead_time_days, ?) AS lead_time_days
                  FROM products p
                 WHERE p.org_id = ?
            ");
            $p->execute([$lead, $orgId]);
            foreach ($p->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $products[(int)$r['sku_id']] = $r;
            }
        } catch (\Throwable) {}

        $stockNow = [];
        try {
            // Try a simple stock table
            $s = $pdo->prepare("SELECT product_id AS sku_id, SUM(qty) AS on_hand FROM stock_levels WHERE org_id=? GROUP BY product_id");
            $s->execute([$orgId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $stockNow[(int)$r['sku_id']] = (float)$r['on_hand'];
        } catch (\Throwable) {
            // fallback 0 if stock_levels table not found
        }

        $onOrder = [];
        try {
            $s = $pdo->prepare("
                SELECT pi.product_id AS sku_id, SUM(pi.quantity - pi.received_qty) AS open_qty
                  FROM purchase_items pi
                  JOIN purchases p ON p.id = pi.purchase_id
                 WHERE p.org_id=? AND p.status IN ('approved','ordered')
                 GROUP BY pi.product_id
            ");
            $s->execute([$orgId]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $onOrder[(int)$r['sku_id']] = max(0.0, (float)$r['open_qty']);
        } catch (\Throwable) {}

        // --- Write cache (replace) ---
        $pdo->prepare("DELETE FROM dms_demand_cache WHERE org_id=?")->execute([$orgId]);

        $ins = $pdo->prepare("
          INSERT INTO dms_demand_cache
            (org_id, sku_id, sku_code, product_name, supplier_id,
             stock_on_hand, on_order, avg_daily, lead_time_days, safety_days, rop, suggested_qty, computed_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");

        foreach ($products as $skuId => $p) {
            $avg    = (float)($sales[$skuId] ?? 0.0);
            $lead2  = (int)($p['lead_time_days'] ?? $lead);
            $sfty   = $safety;
            $soh    = (float)($stockNow[$skuId] ?? 0.0);
            $oo     = (float)($onOrder[$skuId] ?? 0.0);

            $rop    = max(0.0, $avg * ($lead2 + $sfty));
            $need   = max(0.0, $rop - ($soh + $oo));
            $sugg   = $need > 0 ? max($MOQ, ceil($need)) : 0;

            $ins->execute([
                $orgId,
                $skuId,
                (string)($p['sku_code'] ?? ''),
                (string)($p['product_name'] ?? ''),
                isset($p['supplier_id']) ? (int)$p['supplier_id'] : null,
                $soh, $oo, $avg, $lead2, $sfty, $rop, $sugg
            ]);
        }
    }
}