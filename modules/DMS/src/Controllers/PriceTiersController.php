<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class PriceTiersController extends BaseController
{
    private function org(array $ctx): int { return (int)$this->orgId($ctx); }

    // GET /products/{productId}/tiers  (JSON list)
    public function index(array $ctx, int $productId): void
    {
        $pdo   = $this->pdo(); $orgId = $this->org($ctx);

        // Validate product exists for this org
        $chk = $pdo->prepare("SELECT id FROM dms_products WHERE org_id=? AND id=? LIMIT 1");
        $chk->execute([$orgId, $productId]);
        if (!$chk->fetchColumn()) {
            $this->json(['ok'=>false, 'error'=>'Invalid product'], 404); return;
        }

        $sql = "SELECT id, state, currency, channel, customer_segment,
                       effective_from, effective_to, min_qty, max_qty,
                       base_price, discount_pct, discount_abs, commission_pct, commission_abs,
                       tax_included, priority, created_at, updated_at
                  FROM dms_price_tiers
                 WHERE org_id=? AND product_id=?
                 ORDER BY effective_from DESC, priority DESC, id DESC";
        $st  = $pdo->prepare($sql); $st->execute([$orgId, $productId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->json(['ok'=>true, 'tiers'=>$rows]);
    }
  
  // modules/DMS/src/Controllers/PriceTiersController.php
public function page(array $ctx, int $productId): void
{
    $pdo = $this->pdo(); $orgId = (int)$this->orgId($ctx);

    $st = $pdo->prepare("SELECT id, name_canonical FROM dms_products WHERE org_id=? AND id=? LIMIT 1");
    $st->execute([$orgId, $productId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) { $this->abort404('Product not found.'); return; }

    $this->view('products/tiers', [
        'title'       => 'Price Tiers',
        'product'     => $p,
        'module_base' => $this->moduleBase($ctx),
        'active'      => 'products',
        'subactive'   => 'products.tiers',
    ], $ctx);
}
  

    // POST /products/{productId}/tiers  (create draft)
    public function store(array $ctx, int $productId): void
    {
        $pdo   = $this->pdo(); $orgId = $this->org($ctx);

        $chk = $pdo->prepare("SELECT id FROM dms_products WHERE org_id=? AND id=? LIMIT 1");
        $chk->execute([$orgId, $productId]);
        if (!$chk->fetchColumn()) { $this->json(['ok'=>false,'error'=>'Invalid product'], 404); return; }

        $currency         = 'BDT';
        $channel          = trim((string)($_POST['channel'] ?? 'default')) ?: 'default';
        $customerSegment  = trim((string)($_POST['customer_segment'] ?? 'default')) ?: 'default';
        $effectiveFrom    = trim((string)($_POST['effective_from'] ?? '')) ?: date('Y-m-d H:i:s');
        $minQty           = (int)($_POST['min_qty'] ?? 1); if ($minQty < 1) $minQty = 1;
        $maxQty           = $_POST['max_qty'] === '' ? null : (int)$_POST['max_qty'];

        $basePrice        = (float)($_POST['base_price'] ?? 0);
        $discountPct      = $_POST['discount_pct'] === '' ? null : (float)$_POST['discount_pct'];
        $discountAbs      = $_POST['discount_abs'] === '' ? null : (float)$_POST['discount_abs'];
        $commissionPct    = $_POST['commission_pct'] === '' ? null : (float)$_POST['commission_pct'];
        $commissionAbs    = $_POST['commission_abs'] === '' ? null : (float)$_POST['commission_abs'];
        $taxIncluded      = (int)($_POST['tax_included'] ?? 0) ? 1 : 0;
        $priority         = (int)($_POST['priority'] ?? 10);

        $sql = "INSERT INTO dms_price_tiers
                   (org_id, product_id, currency, channel, customer_segment,
                    effective_from, effective_to, min_qty, max_qty,
                    base_price, discount_pct, discount_abs, commission_pct, commission_abs,
                    tax_included, priority, state, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft', NOW(), NOW())";
        $pdo->prepare($sql)->execute([
            $orgId, $productId, $currency, $channel, $customerSegment,
            $effectiveFrom, null, $minQty, $maxQty,
            $basePrice, $discountPct, $discountAbs, $commissionPct, $commissionAbs,
            $taxIncluded, $priority, // state is fixed to 'draft' here
        ]);

        $this->json(['ok'=>true, 'tier_id'=>(int)$pdo->lastInsertId()]);
    }

    // POST /tiers/{tierId}/publish  (auto-bound overlaps)
    public function publish(array $ctx, int $tierId): void
    {
        $pdo=$this->pdo(); $orgId=$this->org($ctx);
        $pdo->beginTransaction();
        try {
            $g = $pdo->prepare("SELECT product_id, channel, customer_segment, currency, effective_from
                                  FROM dms_price_tiers WHERE org_id=? AND id=? FOR UPDATE");
            $g->execute([$orgId,$tierId]);
            $t = $g->fetch(PDO::FETCH_ASSOC);
            if(!$t){ $pdo->rollBack(); $this->json(['ok'=>false,'error'=>'Tier not found'],404); return; }

            // retire overlapping published tiers
            $retire = $pdo->prepare("
                UPDATE dms_price_tiers
                   SET effective_to = LEAST(COALESCE(effective_to, '9999-12-31 23:59:59'), :from),
                       updated_at = NOW()
                 WHERE org_id = :org
                   AND product_id = :pid
                   AND channel = :ch
                   AND customer_segment = :seg
                   AND currency = :cur
                   AND state = 'published'
                   AND (effective_to IS NULL OR effective_to > :from)
            ");
            $retire->execute([
                ':from'=>$t['effective_from'], ':org'=>$orgId, ':pid'=>$t['product_id'],
                ':ch'=>$t['channel'], ':seg'=>$t['customer_segment'], ':cur'=>$t['currency']
            ]);

            // set this tier to published
            $pdo->prepare("UPDATE dms_price_tiers SET state='published', updated_at=NOW() WHERE org_id=? AND id=?")
                ->execute([$orgId,$tierId]);

            $pdo->commit();
            $this->json(['ok'=>true]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->json(['ok'=>false,'error'=>$e->getMessage()],500);
        }
    }

    // POST /tiers/{tierId}/retire
    public function retire(array $ctx, int $tierId): void
    {
        $pdo=$this->pdo(); $orgId=$this->org($ctx);
        $pdo->prepare("UPDATE dms_price_tiers SET state='retired', updated_at=NOW() WHERE org_id=? AND id=?")
            ->execute([$orgId,$tierId]);
        $this->json(['ok'=>true]);
    }

    // POST /tiers/{tierId}/delete
    public function destroy(array $ctx, int $tierId): void
    {
        $pdo=$this->pdo(); $orgId=$this->org($ctx);
        $pdo->prepare("DELETE FROM dms_price_tiers WHERE org_id=? AND id=? LIMIT 1")->execute([$orgId,$tierId]);
        $this->json(['ok'=>true]);
    }

    // GET /products/{productId}/quote(.json)?qty=1&as_of=...
    public function quote(array $ctx, int $productId): void
    {
        $pdo=$this->pdo(); $orgId=$this->org($ctx);
        $qty  = (int)($_GET['qty'] ?? 1); if ($qty < 1) $qty = 1;
        $asOf = trim((string)($_GET['as_of'] ?? '')) ?: date('Y-m-d H:i:s');

        $q = $pdo->prepare("
            SELECT
              dms_final_price(base_price, discount_pct, discount_abs, commission_pct, commission_abs) AS final_price,
              id AS source_tier_id
            FROM dms_price_tiers
            WHERE org_id=? AND product_id=? AND state='published'
              AND currency='BDT' AND channel='default' AND customer_segment='default'
              AND min_qty <= ? AND (max_qty IS NULL OR max_qty >= ?)
              AND effective_from <= ? AND (effective_to IS NULL OR effective_to > ?)
            ORDER BY priority DESC, effective_from DESC, id DESC
            LIMIT 1
        ");
        $q->execute([$orgId,$productId,$qty,$qty,$asOf,$asOf]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        $this->json(['ok'=> (bool)$row, 'quote'=>$row ?: null]);
    }
}