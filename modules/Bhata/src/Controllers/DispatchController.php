<?php
declare(strict_types=1);

namespace Modules\BhataFlow\Controllers;

use PDO;

final class DispatchController extends \Modules\DMS\Controllers\BaseController
{
    public function index(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);

        $st=$pdo->prepare("
          SELECT d.id, d.challan_no, d.challan_date, d.customer_name,
                 SUM(i.qty_pcs) as qty, SUM((i.qty_pcs/1000.0)*i.rate_per_1000) as amt
          FROM bf_dispatch d
          LEFT JOIN bf_dispatch_items i ON i.org_id=d.org_id AND i.dispatch_id=d.id
          WHERE d.org_id=? GROUP BY d.id ORDER BY d.id DESC LIMIT 100
        ");
        $st->execute([$orgId]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('dispatch/index', [
            'title'=>'Dispatch/Challan',
            'rows'=>$rows,
            'active'=>'bhata.dispatch',
        ], $ctx);
    }

    public function create(array $ctx): void
    {
        $this->view('dispatch/create', [
            'title'=>'Create Challan',
            'active'=>'bhata.dispatch',
        ], $ctx);
    }

    public function store(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);

        $no    = trim((string)($_POST['challan_no'] ?? ''));
        $date  = (string)($_POST['challan_date'] ?? date('Y-m-d'));
        $cust  = trim((string)($_POST['customer_name'] ?? ''));
        if ($no==='' || $cust==='') $this->abort400('Challan no & customer required.');

        $pdo->beginTransaction();
        try {
            $h=$pdo->prepare("INSERT INTO bf_dispatch (org_id,challan_no,challan_date,customer_name,site_address,truck_no,driver_name,remarks,created_at)
                              VALUES (?,?,?,?,?,?,?, ?, NOW())");
            $h->execute([$orgId,$no,$date,$cust,
                         trim((string)($_POST['site_address'] ?? '')) ?: null,
                         trim((string)($_POST['truck_no'] ?? '')) ?: null,
                         trim((string)($_POST['driver_name'] ?? '')) ?: null,
                         trim((string)($_POST['remarks'] ?? '')) ?: null]);
            $id=(int)$pdo->lastInsertId();

            $items = $_POST['items'] ?? [];
            $i=$pdo->prepare("INSERT INTO bf_dispatch_items (org_id,dispatch_id,grade,qty_pcs,rate_per_1000) VALUES (?,?,?,?,?)");
            $stock=$pdo->prepare("
              INSERT INTO bf_fired_stock (org_id,txn_date,ref_type,ref_id,grade,qty_pcs,notes,created_at)
              VALUES (?,?,?,?,?,?,'dispatch',NOW())
            ");
            foreach ($items as $row) {
                $gr = in_array(($row['grade'] ?? ''), ['1st','2nd','3rd','batta'], true) ? $row['grade'] : null;
                $qty= (int)($row['qty_pcs'] ?? 0);
                $rt = (float)($row['rate_per_1000'] ?? 0);
                if (!$gr || $qty<=0) continue;
                $i->execute([$orgId,$id,$gr,$qty,$rt]);

                // Stock out (negative qty)
                $stock->execute([$orgId,$date,'dispatch',$id,$gr,-$qty]);
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx).'/bhata/dispatch/'.$id.'/print?autoprint=1');
        } catch (\Throwable $e) {
            $pdo->rollBack(); $this->abort500($e);
        }
    }

    public function print(array $ctx, int $id): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);

        $h=$pdo->prepare("SELECT * FROM bf_dispatch WHERE org_id=? AND id=?");
        $h->execute([$orgId,$id]);
        $d=$h->fetch(PDO::FETCH_ASSOC); if(!$d) $this->abort404('Challan not found.');

        $i=$pdo->prepare("SELECT * FROM bf_dispatch_items WHERE org_id=? AND dispatch_id=? ORDER BY id");
        $i->execute([$orgId,$id]);
        $items=$i->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('dispatch/challan_print', [
            'title'=>'Dispatch Print',
            'challan'=>$d,
            'items'=>$items,
            'active'=>'bhata.dispatch',
        ], $ctx);
    }
}