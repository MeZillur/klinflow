<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class ARController extends BaseController
{
    /** GET {base}/ar  — overall AR summary + latest open invoices */
    public function index(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->orgId($c);

        // Customer filter (optional)
        $cid = (int)($_GET['customer_id'] ?? 0);

        // Totals (from views you already created)
        $tot = [
            'invoiced'     => 0.0,
            'collected'    => 0.0,
            'due'          => 0.0,
            'open_count'   => 0,
        ];

        // Customer rollup
        $sqlCust = "SELECT org_id, customer_id,
                           COALESCE(invoiced,0) AS invoiced,
                           COALESCE(collected,0) AS collected,
                           COALESCE(due_amount,0) AS due_amount,
                           COALESCE(open_invoices,0) AS open_invoices
                    FROM v_dms_ar_customer_due
                    WHERE org_id = ?
                    ".($cid>0 ? "AND customer_id = ?" : "")."
                    ORDER BY due_amount DESC";
        $st = $pdo->prepare($sqlCust);
        $st->execute($cid>0 ? [$orgId, $cid] : [$orgId]);
        $customers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($customers as $r) {
            $tot['invoiced']   += (float)$r['invoiced'];
            $tot['collected']  += (float)$r['collected'];
            $tot['due']        += (float)$r['due_amount'];
            $tot['open_count'] += (int)$r['open_invoices'];
        }

        // Invoice-wise breakdown (open / recent first)
        $sqlInv = "SELECT i.org_id, i.invoice_id, i.customer_id,
                          COALESCE(i.invoice_amount,0) AS invoice_amount,
                          COALESCE(i.paid_amount,0)    AS paid_amount,
                          COALESCE(i.due_amount,0)     AS due_amount
                   FROM v_dms_ar_invoice_due i
                   WHERE i.org_id = ?
                   ".($cid>0 ? "AND i.customer_id = ?" : "")."
                   ORDER BY i.due_amount DESC, i.invoice_id DESC
                   LIMIT 500";
        $si = $pdo->prepare($sqlInv);
        $si->execute($cid>0 ? [$orgId, $cid] : [$orgId]);
        $invoices = $si->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Customer names (best-effort)
        $names = [];
        if ($customers) {
            $ids = array_values(array_unique(array_map(fn($r)=>(int)$r['customer_id'], $customers)));
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $qc = $pdo->prepare("SELECT id, name FROM dms_customers WHERE org_id=? AND id IN ($in)");
                $qc->execute(array_merge([$orgId], $ids));
                foreach ($qc->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $names[(int)$r['id']] = (string)$r['name'];
                }
            }
        }

        $this->view('accounts/ar/index', [
            'title'       => 'Accounts Receivable',
            'tot'         => $tot,
            'customers'   => $customers,
            'invoices'    => $invoices,
            'names'       => $names,
            'customer_id' => $cid,
            'module_base' => $this->moduleBase($c),
            'active'      => 'ar',
            'subactive'   => 'ar.index',
        ], $c);
    }
}