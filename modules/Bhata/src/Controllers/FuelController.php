<?php
declare(strict_types=1);

namespace Modules\BhataFlow\Controllers;

use PDO;

final class FuelController extends \Modules\DMS\Controllers\BaseController
{
    public function index(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $st=$pdo->prepare("SELECT * FROM bf_fuel_logs WHERE org_id=? ORDER BY log_date DESC, id DESC LIMIT 200");
        $st->execute([$orgId]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('fuel/index', ['title'=>'Fuel Logs','rows'=>$rows,'active'=>'bhata.fuel'], $ctx);
    }
    public function store(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $d=(string)($_POST['log_date'] ?? date('Y-m-d'));
        $t=in_array(($_POST['fuel_type'] ?? 'coal'), ['coal','mixed','other'], true) ? $_POST['fuel_type'] : 'coal';
        $q=(float)($_POST['qty_kg'] ?? 0);
        $c=(int)($_POST['for_cycle_id'] ?? 0);
        $n=trim((string)($_POST['notes'] ?? ''));
        if ($q<=0) $this->abort400('Quantity required.');

        $st=$pdo->prepare("INSERT INTO bf_fuel_logs (org_id,log_date,fuel_type,qty_kg,for_cycle_id,notes,created_at)
                           VALUES (?,?,?,?,?,?,NOW())");
        $st->execute([$orgId,$d,$t,$q,$c?:null,$n?:null]);
        $this->redirect($this->moduleBase($ctx).'/bhata/fuel');
    }
}