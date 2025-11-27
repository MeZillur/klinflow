<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;
use DateTimeImmutable;
use Shared\DB;
use Shared\Csrf;

final class DemandForecastController
{
    /* -------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------- */
    private function ensureTenant(): array {
        if (\PHP_SESSION_ACTIVE !== \session_status()) @\session_start();
        $u = $_SESSION['tenant_user'] ?? null;
        $o = $_SESSION['tenant_org']  ?? null;
        if (!$u || !$o) { if (!headers_sent()) header('Location: /tenant/login', true, 302); exit; }
        return [$u, $o];
    }

    private function pdo(): PDO { return DB::pdo(); }

    private function moduleBase(array $org): string {
        $slug = (string)($org['slug'] ?? '');
        return $slug !== '' ? "/t/{$slug}/apps/dms" : "/apps/dms";
    }

    private function sidenavPath(): ?string {
        $root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 5);
        foreach ([
            $root.'/modules/DMS/Views/shared/partials/sidenav.php',
            $root.'/modules/DMS/Views/shared/sidenav.php',
        ] as $p) if (is_file($p)) return $p;
        return null;
    }

    private function view(string $name, array $locals = []): void {
        $root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 5);
        $candidates = [
            $root.'/modules/DMS/Views/forecast/'.$name.'.php',          // canonical
            __DIR__.'/../Views/forecast/'.$name.'.php',                 // lowercase fallback
        ];
        foreach ($candidates as $f) {
            if (is_file($f)) { extract($locals, EXTR_SKIP); include $f; return; }
        }
        http_response_code(500);
        echo 'DMS view missing: '.$name;
    }

    private function wantsJson(): bool {
        $h = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return str_contains($h, 'application/json')
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || (isset($_GET['format']) && $_GET['format'] === 'json');
    }

    private function hasTable(PDO $pdo, string $t): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $q->execute([$t]); return (bool)$q->fetchColumn();
    }
    private function col(PDO $pdo, string $t, string $c): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $q->execute([$t,$c]); return (bool)$q->fetchColumn();
    }

    /* ---------- tiny tables (non-intrusive) ---------- */
    private function createRunsAndResults(PDO $pdo): void {
        // varchar status (avoids ENUM/strict surprises)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dms_forecast_runs (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              status VARCHAR(20) NOT NULL DEFAULT 'queued',
              progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
              message VARCHAR(255) NULL,
              created_at DATETIME NOT NULL,
              started_at DATETIME NULL,
              finished_at DATETIME NULL,
              params JSON NULL,
              KEY (org_id), KEY (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dms_forecast_results (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              org_id INT UNSIGNED NOT NULL,
              run_id BIGINT UNSIGNED NOT NULL,
              product_id BIGINT UNSIGNED NOT NULL,
              sku VARCHAR(64) NULL,
              name VARCHAR(255) NOT NULL,
              stock_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
              sales_hist_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
              forecast_per_day DECIMAL(18,6) NOT NULL DEFAULT 0,
              horizon_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
              suggested_po DECIMAL(18,4) NOT NULL DEFAULT 0,
              UNIQUE KEY uk_run_product (run_id, product_id),
              KEY (org_id), KEY (run_id), KEY (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /* -------------------------------------------------------------
     * Actions
     * ----------------------------------------------------------- */

    /** GET /.../forecast */
    public function index(array $ctx = []): void {
        [, $org] = $this->ensureTenant();
        $this->view('forecast_index', [
            'title'         => 'Demand Forecasting',
            'org'           => $org,
            'module_base'   => $this->moduleBase($org),
            'moduleSidenav' => $this->sidenavPath(),
        ]);
    }

    /** POST /.../forecast/run */
    public function run(array $ctx = []): void {
        [, $org] = $this->ensureTenant();

        $token = (string)($_POST['_csrf'] ?? $_POST['_token'] ?? '');
        if (class_exists(Csrf::class)) {
            $ok = Csrf::verify($token, 'tenant') || Csrf::verify($token);
            if (!$ok) { http_response_code(419); echo 'CSRF token mismatch.'; return; }
        }

        $pdo   = $this->pdo();
        $orgId = (int)($org['id'] ?? 0);

        $algo   = (string)($_POST['algo'] ?? 'moving-average');
        $hist   = max(7, (int)($_POST['history_days'] ?? 90));
        $horiz  = max(7, (int)($_POST['horizon_days'] ?? 30));
        $catId  = (int)($_POST['category_id'] ?? 0);
        $supId  = (int)($_POST['supplier_id'] ?? 0);
        $prodId = (int)($_POST['product_id']  ?? 0);
        $params = compact('algo','hist','horiz','catId','supId','prodId');

        $runId = 0; // guard for catch

        try {
            $this->createRunsAndResults($pdo);
            $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

            $st = $pdo->prepare("
               INSERT INTO dms_forecast_runs (org_id,status,progress,message,created_at,params)
               VALUES (:o,'queued',5,'Forecast queued',:now,:p)
            ");
            $st->execute([':o'=>$orgId, ':now'=>$now, ':p'=>json_encode($params, JSON_UNESCAPED_UNICODE)]);
            $runId = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE dms_forecast_runs SET status='running',progress=15,started_at=NOW(),message='Computing...' WHERE id=? AND org_id=?")
                ->execute([$runId,$orgId]);

            $this->computeForecast($pdo, $orgId, $runId, $params);

            $pdo->prepare("UPDATE dms_forecast_runs SET status='done',progress=100,finished_at=NOW(),message='Done' WHERE id=? AND org_id=?")
                ->execute([$runId,$orgId]);

            $base = $this->moduleBase($org);
            if (!headers_sent()) header('Location: '.$base.'/forecast/status?id='.$runId, true, 303);
            exit;
        } catch (\Throwable $e) {
            error_log('[forecast.run] '.$e->getMessage());
            if ($runId > 0) {
                try {
                    $pdo->prepare("UPDATE dms_forecast_runs SET status='error',progress=0,message=:m WHERE id=:id AND org_id=:o")
                        ->execute([':m'=>substr($e->getMessage(),0,240), ':id'=>$runId, ':o'=>$orgId]);
                } catch (\Throwable) {}
            }
            if (isset($_GET['debug']) && $_GET['debug']==='1') {
                http_response_code(500); echo 'Unexpected error: '.$e->getMessage(); return;
            }
            http_response_code(500); echo 'Unexpected error running forecast.'; return;
        }
    }

    /** GET /.../forecast/status?id=# */
    public function status(array $ctx = []): void {
        [, $org] = $this->ensureTenant();
        $pdo     = $this->pdo();
        $orgId   = (int)($org['id'] ?? 0);
        $id      = (int)($_GET['id'] ?? 0);

        $this->createRunsAndResults($pdo);

        $run = null; $items = [];
        if ($id > 0) {
            $st=$pdo->prepare("SELECT * FROM dms_forecast_runs WHERE org_id=:o AND id=:id");
            $st->execute([':o'=>$orgId, ':id'=>$id]);
            $run = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            $rs=$pdo->prepare("SELECT * FROM dms_forecast_results WHERE org_id=:o AND run_id=:id ORDER BY suggested_po DESC, horizon_qty DESC, name ASC");
            $rs->execute([':o'=>$orgId, ':id'=>$id]);
            $items = $rs->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $state    = 'unknown';
        $progress = (int)($run['progress'] ?? 0);
        $message  = (string)($run['message']  ?? '—');
        $s = strtolower((string)($run['status'] ?? ''));
        if ($s==='queued')  { $state='queued';  $progress = max($progress,5);  $message = $message ?: 'Queued'; }
        if ($s==='running') { $state='running'; $progress = max($progress,50); $message = $message ?: 'Computing...'; }
        if ($s==='done')    { $state='done';    $progress = 100;               $message = $message ?: 'Done'; }
        if ($s==='error')   { $state='failed';  $progress = 0;                 $message = $message ?: 'Failed'; }

        $payload = ['ok'=>true,'job_id'=>$id,'state'=>$state,'message'=>$message,'progress'=>$progress,'run'=>$run,'items'=>$items];

        if ($this->wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            return;
        }

        $this->view('forecast_status', [
            'title'         => 'Forecast Status',
            'org'           => $org,
            'module_base'   => $this->moduleBase($org),
            'moduleSidenav' => $this->sidenavPath(),
            'run'           => $run,
            'items'         => $items,
        ]);
    }

    /** GET /.../forecast/download?id=#  (CSV) */
    public function download(array $ctx = []): void {
        [, $org] = $this->ensureTenant();
        $pdo     = $this->pdo();
        $orgId   = (int)($org['id'] ?? 0);
        $id      = (int)($_GET['id'] ?? 0);

        $this->createRunsAndResults($pdo);
        $st=$pdo->prepare("SELECT * FROM dms_forecast_results WHERE org_id=:o AND run_id=:id ORDER BY suggested_po DESC, name ASC");
        $st->execute([':o'=>$orgId, ':id'=>$id]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="forecast-'.$id.'.csv"');
        $out=fopen('php://output','w');
        fputcsv($out, ['Product ID','SKU','Name','Stock','Sales (History)','Forecast/Day','Horizon Qty','Suggested PO']);
        foreach($rows as $r){
            fputcsv($out, [
                $r['product_id'],$r['sku'],$r['name'],
                $r['stock_qty'],$r['sales_hist_qty'],$r['forecast_per_day'],
                $r['horizon_qty'],$r['suggested_po'],
            ]);
        }
        fclose($out);
    }

    /* -------------------------------------------------------------
     * Core compute (moving-average placeholder; tolerant SQL)
     * ----------------------------------------------------------- */
    private function computeForecast(PDO $pdo, int $orgId, int $runId, array $p): void {
        // tolerant columns for your mixed schemas
        $sku = "COALESCE(p.sku,p.product_sku,p.code,p.product_code,p.pid)";
        $nm  = "COALESCE(p.name,p.product_name)";
        $cat = "COALESCE(p.category_id,p.cat_id,0)";
        $sup = "COALESCE(p.supplier_id,p.vendor_id,0)";

        $where = "p.org_id = :o"; $bind = [':o'=>$orgId];
        if (($p['catId'] ?? 0) > 0) { $where .= " AND {$cat} = :cat"; $bind[':cat'] = (int)$p['catId']; }
        if (($p['supId'] ?? 0) > 0) { $where .= " AND {$sup} = :sup"; $bind[':sup'] = (int)$p['supId']; }
        if (($p['prodId']?? 0) > 0) { $where .= " AND p.id = :pid";   $bind[':pid'] = (int)$p['prodId']; }

        $ps = $pdo->prepare("SELECT p.id, {$sku} AS sku, {$nm} AS name FROM dms_products p WHERE {$where} ORDER BY p.id DESC LIMIT 1000");
        $ps->execute($bind);
        $products = $ps->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $histDays = max(1, (int)($p['hist'] ?? 90));
        $horizon  = max(1, (int)($p['horiz'] ?? 30));
        $fromDate = (new DateTimeImmutable("-{$histDays} days"))->format('Y-m-d');

        // sales table variants
        $salesItemsTbl = $this->hasTable($pdo,'dms_sale_items') ? 'dms_sale_items'
                         : ($this->hasTable($pdo,'dms_sales_items') ? 'dms_sales_items' : null);

        $qSales = null; $qStock1 = null; $qStock2 = null;

        if ($salesItemsTbl) {
            $qSales = $pdo->prepare("
                SELECT COALESCE(SUM(it.qty),0) AS q
                FROM {$salesItemsTbl} it
                JOIN dms_sales s ON s.id=it.sale_id AND s.org_id=it.org_id
                WHERE it.org_id=:o AND it.product_id=:pid
                  AND DATE(COALESCE(s.posted_at,s.created_at,s.sale_date)) >= :d
            ");
        }
        if ($this->hasTable($pdo,'dms_inventory_balances')) {
            $qStock1 = $pdo->prepare("SELECT COALESCE(SUM(qty_on_hand),0) FROM dms_inventory_balances WHERE org_id=:o AND product_id=:pid");
        }
        if ($this->hasTable($pdo,'dms_inventory_moves')) {
            $qStock2 = $pdo->prepare("
                SELECT COALESCE(
                    SUM(CASE WHEN type IN('in','purchase','adj_in','return_in') THEN qty ELSE 0 END)
                  - SUM(CASE WHEN type IN('out','sale','adj_out','return_out') THEN qty ELSE 0 END),0)
                FROM dms_inventory_moves
                WHERE org_id=:o AND product_id=:pid
            ");
        }

        $ins = $pdo->prepare("
            INSERT INTO dms_forecast_results
            (org_id,run_id,product_id,sku,name,stock_qty,sales_hist_qty,forecast_per_day,horizon_qty,suggested_po)
            VALUES (:o,:r,:pid,:sku,:name,:stock,:sales,:fpd,:hq,:sugg)
            ON DUPLICATE KEY UPDATE stock_qty=VALUES(stock_qty), sales_hist_qty=VALUES(sales_hist_qty),
                forecast_per_day=VALUES(forecast_per_day), horizon_qty=VALUES(horizon_qty), suggested_po=VALUES(suggested_po)
        ");

        $i=0; $total=max(1,count($products));
        foreach ($products as $pRow) {
            $pid   = (int)$pRow['id'];
            $sales = 0.0; $stock = 0.0;

            if ($qSales)  { $qSales->execute([':o'=>$orgId,':pid'=>$pid,':d'=>$fromDate]); $sales = (float)$qSales->fetchColumn(); }
            if ($qStock1) { $qStock1->execute([':o'=>$orgId,':pid'=>$pid]); $stock = (float)$qStock1->fetchColumn(); }
            if ($stock === 0.0 && $qStock2) { $qStock2->execute([':o'=>$orgId,':pid'=>$pid]); $stock = (float)$qStock2->fetchColumn(); }

            $fpd  = $sales / max(1,$histDays);
            $hQty = $fpd * $horizon;
            $sugg = max(0.0, $hQty - $stock);

            $ins->execute([
                ':o'=>$orgId, ':r'=>$runId, ':pid'=>$pid,
                ':sku'=>(string)($pRow['sku'] ?? ''), ':name'=>(string)($pRow['name'] ?? ''),
                ':stock'=>$stock, ':sales'=>$sales, ':fpd'=>$fpd, ':hq'=>$hQty, ':sugg'=>$sugg,
            ]);

            // soft progress update
            $i++;
            if ($i % 50 === 0) {
                $pct = 15 + (int)floor(($i/$total) * 80);
                $pdo->prepare("UPDATE dms_forecast_runs SET progress=:p WHERE id=:id AND org_id=:o")
                    ->execute([':p'=>$pct, ':id'=>$runId, ':o'=>$orgId]);
            }
        }
    }
}