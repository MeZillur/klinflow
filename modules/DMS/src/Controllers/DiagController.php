<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class DiagController extends BaseController
{
    public function ping(array $ctx): void
    {
        header('Content-Type:text/plain; charset=utf-8');

        // 1) basic route/controller reachability
        echo "[OK] DiagController::ping reached\n";

        // 2) org + module_base
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $mbase = $this->moduleBase($ctx);
            echo "org_id={$orgId}\nmodule_base={$mbase}\n";
        } catch (\Throwable $e) {
            echo "[ERR] ctx/org/module_base: ".$e->getMessage()."\n";
        }

        // 3) shell + sidenav file presence (NO include, only checks)
        try {
            $root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 5);
            $s1 = $root . '/modules/DMS/Views/shared/layouts/shell.php';
            $sn = $root . '/modules/DMS/Views/shared/sidenav.php';
            echo "shell_exists=".(is_file($s1)?'1':'0')."  path={$s1}\n";
            echo "sidenav_exists=".(is_file($sn)?'1':'0')." path={$sn}\n";
        } catch (\Throwable $e) {
            echo "[ERR] fs: ".$e->getMessage()."\n";
        }

        // 4) sales list query (no rendering)
        try {
            $pdo   = $this->pdo();
            $orgId = (int)$this->orgId($ctx);
            $has = fn($c)=>$this->hasColumn($pdo,'dms_sales',$c);
            $cols = ['id'];
            if ($has('sale_no')) $cols[]='sale_no'; elseif ($has('invoice_no')) $cols[]='invoice_no AS sale_no';
            if ($has('sale_date')) $cols[]='sale_date';
            if ($has('customer_name')) $cols[]='customer_name';
            if ($has('grand_total')) $cols[]='grand_total'; elseif ($has('total')) $cols[]='total AS grand_total';
            $sql = "SELECT ".implode(',', $cols)." FROM dms_sales WHERE org_id=? ORDER BY id DESC LIMIT 3";
            $st  = $pdo->prepare($sql); $st->execute([$orgId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            echo "rows=".count($rows)."\n";
        } catch (\Throwable $e) {
            echo "[ERR] sales query: ".$e->getMessage()."\n";
        }
    }
}