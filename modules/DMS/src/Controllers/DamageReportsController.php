<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class DamageReportsController extends BaseController
{
    /** GET /reports/damage  (filters: q, from, to) */
    public function index(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);

        $q    = trim((string)($_GET['q'] ?? ''));
        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to   = (string)($_GET['to']   ?? date('Y-m-d'));

        // basic date sanity
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

        $w = ["sm.org_id = ?", "sm.ref_table = 'damage'", "DATE(sm.move_at) BETWEEN ? AND ?"];
        $args = [$orgId, $from, $to];

        if ($q !== '') {
            $w[] = "(sm.sku LIKE ? OR sm.memo LIKE ? OR p.name LIKE ?)";
            $args[] = "%$q%"; $args[] = "%$q%"; $args[] = "%$q%";
        }

        $sql = "
            SELECT sm.id, sm.move_at, sm.sku, sm.qty_out, sm.memo,
                   p.name AS product_name
            FROM dms_stock_moves sm
            LEFT JOIN dms_products p
                   ON p.org_id = sm.org_id AND p.id = sm.product_id
            WHERE ".implode(' AND ', $w)."
            ORDER BY sm.move_at DESC, sm.id DESC
            LIMIT 500
        ";
        $st = $pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // quick totals
        $tot_qty = 0.0;
        foreach ($rows as $r) $tot_qty += (float)($r['qty_out'] ?? 0);

        $this->view('reports/damage', [
            'title'       => 'Damage Reports',
            'rows'        => $rows,
            'tot_qty'     => $tot_qty,
            'filters'     => ['q'=>$q, 'from'=>$from, 'to'=>$to],
            'module_base' => $this->moduleBase($c),
            'active'      => 'inventory',
            'subactive'   => 'reports.damage',
        ], $c);
    }
}