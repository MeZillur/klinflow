<?php
declare(strict_types=1);

namespace Modules\BhataFlow\Controllers;

use PDO;

final class PricesController extends \Modules\DMS\Controllers\BaseController
{
    public function index(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $st=$pdo->prepare("SELECT * FROM bf_prices WHERE org_id=? ORDER BY eff_date DESC, grade ASC");
        $st->execute([$orgId]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('prices/index', ['title'=>'Brick Prices','rows'=>$rows,'active'=>'bhata.prices'], $ctx);
    }

    public function store(array $ctx): void
    {
        $pdo=$this->pdo(); $orgId=$this->orgId($ctx);
        $dt=(string)($_POST['eff_date'] ?? date('Y-m-d'));
        $gr=in_array(($_POST['grade'] ?? ''), ['1st','2nd','3rd','batta'], true) ? $_POST['grade'] : '1st';
        $rt=(float)($_POST['price_per_1000'] ?? 0);
        if ($rt<=0) $this->abort400('Price required.');
        $i=$pdo->prepare("INSERT INTO bf_prices (org_id,eff_date,grade,price_per_1000,created_at) VALUES (?,?,?,?,NOW())");
        $i->execute([$orgId,$dt,$gr,$rt]);
        $this->redirect($this->moduleBase($ctx).'/bhata/prices');
    }
}