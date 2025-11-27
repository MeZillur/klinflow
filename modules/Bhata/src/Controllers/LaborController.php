<?php
declare(strict_types=1);

namespace Modules\BhataFlow\Controllers;

use PDO;

final class LaborController extends \Modules\DMS\Controllers\BaseController
{
    public function index(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);

        $rates=$pdo->prepare("SELECT * FROM bf_labor_piece_rate WHERE org_id=? ORDER BY eff_date DESC, id DESC LIMIT 50");
        $rates->execute([$orgId]);
        $rateRows=$rates->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $logs=$pdo->prepare("SELECT * FROM bf_labor_logs WHERE org_id=? ORDER BY log_date DESC, id DESC LIMIT 200");
        $logs->execute([$orgId]);
        $logRows=$logs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('labor/index', [
            'title'=>'Labor Piece-Rate & Logs',
            'rates'=>$rateRows,
            'logs'=>$logRows,
            'active'=>'bhata.labor',
        ], $ctx);
    }

    public function store(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);

        if (($_POST['kind'] ?? '') === 'rate') {
            $wt = in_array(($_POST['work_type'] ?? ''), ['molding','carrying','stacking','unloading','other'], true) ? $_POST['work_type'] : 'other';
            $dt = (string)($_POST['eff_date'] ?? date('Y-m-d'));
            $rt = (float)($_POST['rate_per_1000'] ?? 0);
            if ($rt<=0) $this->abort400('Rate required.');
            $i=$pdo->prepare("INSERT INTO bf_labor_piece_rate (org_id,work_type,eff_date,rate_per_1000,created_at) VALUES (?,?,?,?,NOW())");
            $i->execute([$orgId,$wt,$dt,$rt]);
        } else {
            $dt   = (string)($_POST['log_date'] ?? date('Y-m-d'));
            $name = trim((string)($_POST['worker_name'] ?? ''));
            $wt   = in_array(($_POST['work_type'] ?? ''), ['molding','carrying','stacking','unloading','other'], true) ? $_POST['work_type'] : 'other';
            $qty  = (int)($_POST['qty_pcs'] ?? 0);
            $rate = (float)($_POST['rate_per_1000'] ?? 0);
            if ($name==='' || $qty<=0 || $rate<=0) $this->abort400('Name, qty & rate required.');
            $amt  = ($qty/1000.0)*$rate;
            $i=$pdo->prepare("
              INSERT INTO bf_labor_logs (org_id,log_date,worker_name,work_type,qty_pcs,rate_per_1000,amount,notes,created_at)
              VALUES (?,?,?,?,?,?,?, ?, NOW())
            ");
            $i->execute([$orgId,$dt,$name,$wt,$qty,$rate,$amt, trim((string)($_POST['notes'] ?? '')) ?: null]);
        }

        $this->redirect($this->moduleBase($ctx).'/bhata/labor');
    }
}