<?php
declare(strict_types=1);

namespace Modules\POS\Controllers\Api;

use Shared\DB;

final class ProductsApiController
{
    private function pdo() { return method_exists(DB::class,'tenant') ? DB::tenant() : DB::pdo(); }
    private function orgId(array $p): int { return (int)($p['org_id'] ?? 0); }
    private function json($data, int $code=200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /t/{org}/apps/pos/api/products/search?q=
     * Returns: [{id, name, price, stock, sku, barcode}]
     * - Matches by name, SKU or barcode
     */
    public function search(array $ctx): void
    {
        $orgId = $this->orgId($ctx);
        if ($orgId <= 0) return $this->json([], 403);

        $q   = trim((string)($_GET['q'] ?? ''));
        $pdo = $this->pdo();

        // Unified table/column handling (pos_products vs products; stock vs stock_qty)
        $tbl = DB::fetch("SHOW TABLES LIKE 'pos_products'") ? 'pos_products' : 'products';
        $stockCol = DB::fetch("SHOW COLUMNS FROM {$tbl} LIKE 'stock'") ? 'stock'
                   : (DB::fetch("SHOW COLUMNS FROM {$tbl} LIKE 'stock_qty'") ? 'stock_qty' : '0');

        $sql = "
            SELECT id, name,
                   COALESCE(price,0) AS price,
                   COALESCE({$stockCol},0) AS stock,
                   COALESCE(sku,'') AS sku,
                   COALESCE(barcode,'') AS barcode
              FROM {$tbl}
             WHERE ".($tbl==='pos_products' ? "org_id = ? AND " : "")."(name LIKE ? OR sku LIKE ? OR barcode LIKE ?)
             ORDER BY name ASC
             LIMIT 25
        ";
        $bind = ($tbl==='pos_products')
            ? [$orgId, "%{$q}%", "%{$q}%", "%{$q}%"]
            : ["%{$q}%", "%{$q}%", "%{$q}%"];

        $st = $pdo->prepare($sql);
        $st->execute($bind);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->json($rows);
    }

    /**
     * GET /t/{org}/apps/pos/api/products/by-barcode?code=XXXXXXXXXXXX
     * Returns: {id,name,price,stock,sku,barcode} or 404
     */
    public function byBarcode(array $ctx): void
    {
        $orgId = $this->orgId($ctx);
        if ($orgId <= 0) return $this->json(['ok'=>false,'message'=>'No tenant'], 403);

        $code = trim((string)($_GET['code'] ?? ($_GET['q'] ?? ''))); // support q= for wedges
        if ($code === '') return $this->json(['ok'=>false,'message'=>'Empty barcode'], 422);

        $pdo = $this->pdo();
        $tbl = DB::fetch("SHOW TABLES LIKE 'pos_products'") ? 'pos_products' : 'products';
        $stockCol = DB::fetch("SHOW COLUMNS FROM {$tbl} LIKE 'stock'") ? 'stock'
                   : (DB::fetch("SHOW COLUMNS FROM {$tbl} LIKE 'stock_qty'") ? 'stock_qty' : '0');

        $sql = "
            SELECT id, name,
                   COALESCE(price,0) AS price,
                   COALESCE({$stockCol},0) AS stock,
                   COALESCE(sku,'') AS sku,
                   COALESCE(barcode,'') AS barcode
              FROM {$tbl}
             WHERE ".($tbl==='pos_products' ? "org_id = ? AND " : "")." barcode = ?
             LIMIT 1
        ";
        $bind = ($tbl==='pos_products') ? [$orgId, $code] : [$code];

        $st = $pdo->prepare($sql);
        $st->execute($bind);
        $p = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$p) return $this->json(['ok'=>false,'message'=>'Not found'], 404);
        $this->json($p);
    }
}