<?php
declare(strict_types=1);

namespace Modules\BhataFlow\Controllers;

use PDO;

final class ProductionController extends \Modules\DMS\Controllers\BaseController
{
    public function index(array $ctx): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);

        $green = $pdo->prepare("SELECT * FROM bf_green_batches WHERE org_id=? ORDER BY id DESC LIMIT 30");
        $green->execute([$orgId]);
        $greens = $green->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cycles = $pdo->prepare("SELECT * FROM bf_kiln_cycles WHERE org_id=? ORDER BY id DESC LIMIT 30");
        $cycles->execute([$orgId]);
        $cy = $cycles->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('production/index', [
            'title'  => 'Production',
            'greens' => $greens,
            'cycles' => $cy,
            'active' => 'bhata.production',
        ], $ctx);
    }

    public function createGreen(array $ctx): void
    {
        $this->view('production/create_green', [
            'title'  => 'New Green Batch',
            'active' => 'bhata.production',
        ], $ctx);
    }

    public function storeGreen(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);

        $batch_no    = trim((string)($_POST['batch_no'] ?? ''));
        $batch_date  = (string)($_POST['batch_date'] ?? date('Y-m-d'));
        $location    = trim((string)($_POST['location'] ?? ''));
        $prepared_by = trim((string)($_POST['prepared_by'] ?? ''));
        $qty_pcs     = (int)($_POST['qty_pcs'] ?? 0);
        $moisture    = (float)($_POST['moisture_pct'] ?? 0);
        $notes       = trim((string)($_POST['notes'] ?? ''));

        if ($batch_no === '' || $qty_pcs <= 0) $this->abort400('Batch no & quantity required.');

        $st=$pdo->prepare("
          INSERT INTO bf_green_batches
          (org_id,batch_no,batch_date,location,prepared_by,qty_pcs,moisture_pct,notes,created_at)
          VALUES (?,?,?,?,?,?,?,?,NOW())
        ");
        $st->execute([$orgId,$batch_no,$batch_date,$location?:null,$prepared_by?:null,$qty_pcs,$moisture?:null,$notes?:null]);

        $this->redirect($this->moduleBase($ctx).'/bhata/production');
    }

    public function cycles(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $st=$pdo->prepare("SELECT * FROM bf_kiln_cycles WHERE org_id=? ORDER BY id DESC LIMIT 50");
        $st->execute([$orgId]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('production/cycles', [
            'title'=>'Kiln Cycles',
            'rows'=>$rows,
            'active'=>'bhata.production',
        ], $ctx);
    }

    public function storeCycle(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);

        $action = (string)($_POST['action'] ?? 'open');

        if ($action === 'open') {
            $cycle_no = trim((string)($_POST['cycle_no'] ?? ''));
            $start    = (string)($_POST['start_date'] ?? date('Y-m-d'));
            $ktype    = in_array(($_POST['kiln_type'] ?? 'zigzag'), ['zigzag','hoffman','clamp','other'], true) ? $_POST['kiln_type'] : 'zigzag';
            if ($cycle_no==='') $this->abort400('Cycle no required.');
            $i=$pdo->prepare("
              INSERT INTO bf_kiln_cycles (org_id,cycle_no,kiln_type,start_date,status,created_at)
              VALUES (?,?,?,?, 'open', NOW())
            ");
            $i->execute([$orgId,$cycle_no,$ktype,$start]);
        } else if ($action === 'close') {
            $id   = (int)($_POST['cycle_id'] ?? 0);
            $end  = (string)($_POST['end_date'] ?? date('Y-m-d'));
            $fuel = (float)($_POST['fuel_qty_kg'] ?? 0);
            $g = [
              'fired_1st_pcs'  => (int)($_POST['fired_1st_pcs']  ?? 0),
              'fired_2nd_pcs'  => (int)($_POST['fired_2nd_pcs']  ?? 0),
              'fired_3rd_pcs'  => (int)($_POST['fired_3rd_pcs']  ?? 0),
              'fired_batta_pcs'=> (int)($_POST['fired_batta_pcs']?? 0),
              'breakage_pcs'   => (int)($_POST['breakage_pcs']   ?? 0),
            ];
            if ($id<=0) $this->abort400('Cycle id required.');

            $pdo->beginTransaction();
            try {
                $u=$pdo->prepare("
                  UPDATE bf_kiln_cycles
                  SET end_date=?, fuel_qty_kg=?, fired_1st_pcs=?, fired_2nd_pcs=?,
                      fired_3rd_pcs=?, fired_batta_pcs=?, breakage_pcs=?, status='closed'
                  WHERE org_id=? AND id=? AND status='open'
                ");
                $u->execute([$end,$fuel,$g['fired_1st_pcs'],$g['fired_2nd_pcs'],$g['fired_3rd_pcs'],$g['fired_batta_pcs'],$g['breakage_pcs'],$orgId,$id]);

                // write stock (+) by grade
                $ins = $pdo->prepare("
                  INSERT INTO bf_fired_stock (org_id, txn_date, ref_type, ref_id, grade, qty_pcs, notes, created_at)
                  VALUES (?,?,?,?,?,?,?,NOW())
                ");
                $date = $end ?: date('Y-m-d');
                foreach (['1st','2nd','3rd','batta'] as $gr) {
                    $qty = (int)$g['fired_'.($gr==='batta'?'batta':$gr).'_pcs'];
                    if ($qty>0) $ins->execute([$orgId,$date,'cycle_close',$id,$gr,$qty,null]);
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack(); $this->abort500($e);
            }
        }

        $this->redirect($this->moduleBase($ctx).'/bhata/production/cycles');
    }
}