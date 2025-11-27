<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;
use Dompdf\Dompdf;
use Dompdf\Options;

final class AutoPoController
{
    /* ============================================================
     * 0) SMALL CACHES
     * ============================================================ */
    private PDO $pdoCache;
    private array $schemaCache = []; // table/column existence memo

    /* ============================================================
     * 1) CORE HELPERS
     * ============================================================ */
    private function pdo(): PDO {
        if (isset($this->pdoCache)) return $this->pdoCache;
        if (!class_exists('\Shared\DB')) throw new \RuntimeException('DB layer not available');
        $pdo = \Shared\DB::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $this->pdoCache = $pdo;
    }

    private function currentOrg(): array {
        if (\PHP_SESSION_ACTIVE !== \session_status()) @\session_start();
        $org = $_SESSION['tenant_org'] ?? [];
        if (!is_array($org) || empty($org)) { header('Location: /tenant/login', true, 302); exit; }
        return $org;
    }

    private function orgIdFrom(array $org): int {
        return (int)($org['id'] ?? $org['org_id'] ?? 0);
    }

    private function moduleBase(?array $org = null): string {
        $slug = is_array($org) ? (string)($org['slug'] ?? '') : '';
        if ($slug === '' && !empty($_SERVER['REQUEST_URI']) &&
            preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
            $slug = $m[1];
        }
        return $slug !== '' ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
    }

    private function ident(string $col): string {
        return '`'.str_replace('`', '``', $col).'`';
    }

    /* ============================================================
     * 2) VIEW RESOLUTION (CONTENT-ONLY)
     * ============================================================ */
    private function viewPath(string $name): ?string {
    $rel  = str_ends_with($name, '.php') ? $name : ($name.'.php');
    $root = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);

    $candidates = [
        $root.'/modules/DMS/Views/'.$rel,
        $root.'/modules/DMS/Views/auto_po/'.$rel,
        $root.'/modules/dms/Views/'.$rel,
        $root.'/modules/dms/Views/auto_po/'.$rel,
        // also allow “bare file under auto_po” even if caller passed “auto_po/…”
        $root.'/modules/DMS/Views/auto_po/'.basename($rel),
        $root.'/modules/dms/Views/auto_po/'.basename($rel),
    ];
    foreach ($candidates as $p) if (is_file($p)) return $p;
    return null;
}
    private function renderContent(string $viewName, array $vars): void {
        $vp = $this->viewPath($viewName);
        if (!$vp) { http_response_code(500); echo 'DMS view missing: '.htmlspecialchars($viewName, ENT_QUOTES, 'UTF-8'); return; }
        extract($vars, EXTR_SKIP);
        include $vp; // CONTENT ONLY — your front wraps with shell+sidenav
    }

    /* ============================================================
     * 3) SCHEMA UTILITIES
     * ============================================================ */
    private function tableExists(PDO $pdo, string $t): bool {
        $k='t:'.strtolower($t);
        if (array_key_exists($k,$this->schemaCache)) return $this->schemaCache[$k];
        $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
        $q->execute([$t]); return $this->schemaCache[$k]=(bool)$q->fetchColumn();
    }
    private function hasCol(PDO $pdo, string $t, string $c): bool {
        $k='c:'.strtolower($t).'.'.strtolower($c);
        if (array_key_exists($k,$this->schemaCache)) return $this->schemaCache[$k];
        $q=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
        $q->execute([$t,$c]); return $this->schemaCache[$k]=(bool)$q->fetchColumn();
    }
    private function coalesceNotEmpty(PDO $pdo, string $table, string $alias, array $cands, string $fallback='NULL'): string {
        $parts=[];
        foreach ($cands as $col) if ($this->hasCol($pdo,$table,$col)) $parts[]="NULLIF($alias.".$this->ident($col).", '')";
        if (!$parts) return $fallback;
        return 'COALESCE('.implode(', ',$parts).($fallback!==''? ', '.$fallback:'').')';
    }
    private function pickTable(PDO $pdo, array $cands): ?string {
        foreach ($cands as $t) if ($this->tableExists($pdo,$t)) return $t; return null;
    }

    private function purchaseCols(PDO $pdo): array {
        $cols=[]; $st=$pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dms_purchases'");
        foreach (($st->fetchAll(PDO::FETCH_COLUMN)?:[]) as $c) $cols[strtolower($c)]=$c;
        $first = static function(array $names, array $have, string $fallback){
            foreach ($names as $n){ $lc=strtolower($n); if(isset($have[$lc])) return $have[$lc]; }
            return $fallback;
        };
        return [
            'no'          => $first(['bill_no','purchase_no','ref_no','invoice_no','doc_no','number','code'],$cols,$cols['id']??'id'),
            'date'        => $first(['bill_date','purchase_date','date','doc_date','created_at'],$cols,$cols['created_at']??'created_at'),
            'supplier_id' => $first(['supplier_id','vendor_id','party_id'],$cols,'supplier_id'),
            'supplier'    => $first(['supplier_name','vendor_name','party_name','name'],$cols,'supplier_name'),
            'grand'       => $first(['grand_total','total','amount','net_total','payable'],$cols,'grand_total'),
            'status'      => $first(['status','state'],$cols,'status'),
            'notes'       => $cols['notes'] ?? null,
        ];
    }
    private function nextPurchaseNo(PDO $pdo, int $orgId, string $noCol): string {
        $ym=date('Y-m'); $prefix="PUR-{$ym}-"; $like=$prefix.'%';
        $noColQ=preg_replace('/[^A-Za-z0-9_]/','',$noCol);
        $st=$pdo->prepare("SELECT MAX(CAST(SUBSTRING($noColQ, LENGTH(?) + 1) AS UNSIGNED)) AS mx
                           FROM dms_purchases WHERE org_id=? AND $noColQ LIKE ?");
        $st->execute([$prefix,$orgId,$like]); $mx=(int)($st->fetchColumn()?:0);
        return $prefix.str_pad((string)($mx+1),4,'0',STR_PAD_LEFT);
    }

    /* ============================================================
     * 4) CSRF + RESPONSE HELPERS
     * ============================================================ */
    private function csrfVerifyPostTenant(): bool {
        $tok = (string)($_POST['_csrf'] ?? $_POST['_token'] ?? '');
        if ($tok==='') return false;
        if (class_exists('\Shared\Csrf')) {
            try { return \Shared\Csrf::verify($tok,'tenant'); } catch (\Throwable) { return false; }
        }
        if (\PHP_SESSION_ACTIVE !== \session_status()) @\session_start();
        $sess = $_SESSION;
        foreach (['csrf_token_tenant','csrf_tenant','tenant_csrf','_csrf','csrf','XSRF-TOKEN'] as $k) {
            if (!empty($sess[$k]) && hash_equals((string)$sess[$k], $tok)) return true;
        }
        return false;
    }
    private function wantsJson(): bool {
        $accept=strtolower($_SERVER['HTTP_ACCEPT']??'');
        $xhr=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'');
        $fmt=strtolower((string)($_GET['format']??''));
        $pretty=isset($_GET['pretty']) && $_GET['pretty']!=='0';
        return $pretty || $fmt==='json' || str_contains($accept,'application/json') || $xhr==='xmlhttprequest';
    }
    private function seeOther(string $url): void {
        if (!headers_sent()) header('Location: '.$url, true, 303);
        exit;
    }
    private function json(mixed $payload, int $status=200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
  
  /* ============================================================
     * 4) DomPDF Section
     * ============================================================ */
  
  
  	private function runFetch(int $orgId, int $id): array {
    $pdo = $this->pdo();
    $h = $pdo->prepare("SELECT * FROM dms_auto_po_runs WHERE org_id=? AND id=?");
    $h->execute([$orgId,$id]);
    $run = $h->fetch(PDO::FETCH_ASSOC);
    if (!$run) throw new \RuntimeException('Run not found');

    $i = $pdo->prepare("SELECT * FROM dms_auto_po_items WHERE org_id=? AND run_id=? ORDER BY id");
    $i->execute([$orgId,$id]);
    $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [$run, $items];
}

/** Build HTML for PDF (brand-ready, printable, email-friendly) */
private function runHtml(array $org, array $run, array $items): string {
    $h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $brand = '#228B22';
    $id = (int)($run['id'] ?? 0);
    $when = $run['created_at'] ?? date('Y-m-d H:i');
    $orgName = (string)($org['name'] ?? 'Organization');
    $orgAddr = (string)($org['address'] ?? $org['company_address'] ?? '');
    $orgPhone= (string)($org['phone'] ?? '');
    $orgEmail= (string)($org['email'] ?? '');
    $logo    = (string)($org['logo_url'] ?? '');

    ob_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= $h("Auto-PO Run #$id") ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --brand: <?= $brand ?>; --ink:#0f172a; --muted:#64748b; }
  * { box-sizing:border-box; }
  body { margin:0; background:#fff; color:var(--ink); font:12px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; }
  .sheet { margin: 16px auto; padding: 16px; max-width: 900px; }
  header { display:flex; gap:16px; align-items:center; border-bottom:2px solid var(--brand); padding-bottom:12px; }
  .logo { width:70px; height:70px; object-fit:contain; }
  .org h1 { margin:0; font-size:20px; }
  .org .meta { margin-top:2px; color:var(--muted); font-size:12px; }
  .titlebar { display:flex; justify-content:space-between; align-items:flex-end; margin-top:14px; }
  .titlebar h2 { margin:0; font-size:18px; letter-spacing:.2px; }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; color:#fff; background:var(--brand); }

  table { width:100%; border-collapse:collapse; margin-top:14px; }
  thead th { text-align:left; font-weight:600; background:#f8fafc; border-bottom:1px solid #e5e7eb; padding:8px; }
  tbody td { border-bottom:1px solid #f1f5f9; padding:8px; vertical-align:top; }
  .tr { text-align:right; }

  .signers { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; margin-top:16px; }
  .sig { border:1px dashed #e5e7eb; border-radius:10px; padding:10px; height:90px; display:flex; flex-direction:column; justify-content:flex-end; }
  .sig .line { border-top:1px solid #cbd5e1; margin-top:auto; }
  .sig .who { font-size:11px; color:var(--muted); margin-top:6px; }

  footer { margin-top:14px; display:flex; justify-content:space-between; align-items:center; color:var(--muted); font-size:11px; }
</style>
</head>
<body>
  <div class="sheet">
    <header>
      <?php if ($logo !== ''): ?>
        <img class="logo" src="<?= $h($logo) ?>" alt="Logo">
      <?php else: ?>
        <div class="logo" style="border:1px solid #e5e7eb; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;">LOGO</div>
      <?php endif; ?>
      <div class="org">
        <h1><?= $h($orgName) ?></h1>
        <div class="meta">
          <?= $h($orgAddr) ?>
          <?= $orgPhone ? ' · '.$h($orgPhone) : '' ?>
          <?= $orgEmail ? ' · '.$h($orgEmail) : '' ?>
        </div>
      </div>
    </header>

    <div class="titlebar">
      <h2>Auto-PO Suggestions</h2>
      <div style="text-align:right">
        <div style="font-weight:600; font-size:14px;">Run #<?= $h($id) ?></div>
        <div class="badge" style="margin-top:4px;"><?= $h($when) ?></div>
      </div>
    </div>

    <table aria-label="Suggested items">
      <thead>
        <tr>
          <th style="width:40%;">Product</th>
          <th class="tr" style="width:12%;">PID</th>
          <th style="width:20%;">Supplier</th>
          <th class="tr" style="width:10%;">On-hand</th>
          <th class="tr" style="width:10%;">Suggest</th>
          <th class="tr" style="width:8%;">Price</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= $h((string)($it['name'] ?? '')) ?></td>
            <td class="tr"><?= (int)($it['product_id'] ?? 0) ?></td>
            <td><?= $h((string)($it['supplier_name'] ?? $it['supplier_id'] ?? '')) ?></td>
            <td class="tr"><?= (float)($it['onhand'] ?? 0) ?></td>
            <td class="tr"><?= (float)($it['suggested_qty'] ?? 0) ?></td>
            <td class="tr"><?= number_format((float)($it['unit_price'] ?? 0), 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="signers">
      <div class="sig"><div class="line"></div><div class="who">Prepared By</div></div>
      <div class="sig"><div class="line"></div><div class="who">Authorised By</div></div>
      <div class="sig"><div class="line"></div><div class="who">Approved By</div></div>
    </div>

    <footer>
      <div>Thank you. This is a system-generated document.</div>
      <div>Printed: <?= $h(date('Y-m-d H:i')) ?></div>
    </footer>
  </div>
</body>
</html>
<?php
    return (string)ob_get_clean();
}


    /* ============================================================
     * 5) ROUTES
     * ============================================================ */

    /** GET /auto-po */
    public function index(array $ctx=[]): void {
        $org  = $this->currentOrg();
        $base = $this->moduleBase($org);
        $endpoints = [
            'runs'            => $base.'/auto-po/runs',
            'preview'         => $base.'/auto-po/run',
            'saveRun'         => $base.'/auto-po/run',
            'lookupSuppliers' => $base.'/api/lookup/suppliers',
        ];
        $this->renderContent('auto_po/auto_po_index', [
            'title'=>'Auto Purchase Orders', 'module_base'=>$base, 'org'=>$org, 'endpoints'=>$endpoints,
        ]);
    }

    /**
     * GET/POST /auto-po/run
     * - GET with ?pretty=1 → JSON preview
     * - POST with commit/save → snapshot to dms_auto_po_runs/items (then redirect unless JSON)
     */
    public function run(array $ctx=[]): void {
        $method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $wantsSave = ($method==='POST') && (isset($_POST['commit']) || isset($_POST['save']));

        try {
            $org   = $this->currentOrg(); $orgId=$this->orgIdFrom($org);
            $pdo   = $this->pdo();

            if ($wantsSave && !$this->csrfVerifyPostTenant()) $this->json(['ok'=>false,'error'=>'CSRF token mismatch.'], 419);

            $in           = ($method==='POST') ? $_POST : $_GET;
            $limit        = max(1, min(500, (int)($in['limit'] ?? 100)));
            $minShortage  = max(0, (int)($in['min_shortage'] ?? 1));
            $pretty       = isset($in['pretty']) && (string)$in['pretty']!=='0';
            $preferSuppId = (int)($in['prefer_supplier_id'] ?? 0);

            // Data sources
            $productsT = 'dms_products';
            $bestPriceV = $this->tableExists($pdo,'v_best_product_price_today') ? 'v_best_product_price_today' : null;
            $stockT = $this->pickTable($pdo, ['dms_current_stock','v_stock_summary','v_dms_stock_onhand','pos_v_current_stock','stock_onhand','v_item_onhand']);

            $pName = $this->coalesceNotEmpty($pdo,$productsT,'p',['name','product_name','title','code','product_code'],"'Unnamed'");
            $pSku  = $this->coalesceNotEmpty($pdo,$productsT,'p',['sku','product_sku','code','product_code','pid'],"''");
            $pUnit = $this->coalesceNotEmpty($pdo,$productsT,'p',['unit','uom_name','uom'],"'PCS'");
            $pSupp = $this->coalesceNotEmpty($pdo,$productsT,'p',['supplier_id','vendor_id','party_id'],'NULL');

            $pReorder   = $this->coalesceNotEmpty($pdo,$productsT,'p',['reorder_point','min_stock','min_qty','reorder_level'],'10');
            $pTarget    = $this->coalesceNotEmpty($pdo,$productsT,'p',['target_level','max_stock','target_qty'],'('.$pReorder.' * 2)');
            $pBasePrice = 'NULLIF('.$this->coalesceNotEmpty($pdo,$productsT,'p',['cost_price','purchase_price','unit_cost','unit_price','price'],'0').',0)';

            $stockJoin=''; $sQtyExpr='0';
            if ($stockT) {
                $pidCol=null; foreach (['product_id','pid','item_id','product','prod_id','id'] as $c) if ($this->hasCol($pdo,$stockT,$c)) {$pidCol=$c; break;}
                $qtyCol=null; foreach (['onhand','qty','quantity','stock_qty','available_qty','bal_qty'] as $c) if ($this->hasCol($pdo,$stockT,$c)) {$qtyCol=$c; break;}
                if ($pidCol && $qtyCol) {
                    $sQtyExpr="COALESCE(s.".$this->ident($qtyCol).",0)";
                    $stockJoin="LEFT JOIN {$stockT} s ON s.org_id=p.org_id AND s.".$this->ident($pidCol)."=p.id";
                }
            }
            $bpJoin=''; $bpPrice="COALESCE(bp.unit_price, {$pBasePrice}, 0)"; $bpSuppId="COALESCE(bp.supplier_id, {$pSupp})";
            if ($bestPriceV) $bpJoin="LEFT JOIN {$bestPriceV} bp ON bp.org_id=p.org_id AND bp.product_id=p.id";

            $suggestExpr = "GREATEST(0, ({$pTarget}) - ({$sQtyExpr}))";
            $where = "p.org_id = :orgId AND ({$sQtyExpr}) <= ({$pReorder}) AND {$suggestExpr} >= :minShortage";
            if ($preferSuppId > 0) $where .= " AND COALESCE(bp.supplier_id, {$pSupp}) = :pref";

            $sql = "
                SELECT
                    p.id               AS product_id,
                    {$pName}           AS name,
                    {$pSku}            AS sku,
                    {$pUnit}           AS unit,
                    {$sQtyExpr}        AS onhand,
                    {$pReorder}        AS reorder_point,
                    {$pTarget}         AS target_level,
                    {$suggestExpr}     AS suggested_qty,
                    {$bpPrice}         AS unit_price,
                    {$bpSuppId}        AS supplier_id
                FROM {$productsT} p
                {$stockJoin}
                {$bpJoin}
                WHERE {$where}
                ORDER BY suggested_qty DESC, name ASC
                LIMIT {$limit}";
            $st=$pdo->prepare($sql);
            $st->bindValue(':orgId',$orgId,PDO::PARAM_INT);
            $st->bindValue(':minShortage',$minShortage,PDO::PARAM_INT);
            if ($preferSuppId>0) $st->bindValue(':pref',$preferSuppId,PDO::PARAM_INT);
            $st->execute();
            $rows=$st->fetchAll(PDO::FETCH_ASSOC)?:[];

            // Snapshot if requested
            $runId=null;
            if ($wantsSave) {
                $hasCreated = $this->hasCol($pdo,'dms_auto_po_runs','created_at');
                $hasUpdated = $this->hasCol($pdo,'dms_auto_po_runs','updated_at');

                $fields="org_id, created_by, params_json, status";
                $ph    ="?, ?, ?, ?";
                if ($hasCreated) { $fields.=", created_at"; $ph.=", NOW()"; }
                if ($hasUpdated) { $fields.=", updated_at"; $ph.=", NOW()"; }

                $insRun=$pdo->prepare("INSERT INTO dms_auto_po_runs ($fields) VALUES ($ph)");
                $params=json_encode(['limit'=>$limit,'min_shortage'=>$minShortage,'prefer_supplier_id'=>$preferSuppId], JSON_UNESCAPED_UNICODE);
                $userId=(int)($_SESSION['tenant_user']['id'] ?? 0);
                $insRun->execute([$orgId, ($userId?:null), $params, 'draft']);
                $runId=(int)$pdo->lastInsertId();

                if ($rows) {
                    $hasCreatedI = $this->hasCol($pdo,'dms_auto_po_items','created_at');
                    $ins = $pdo->prepare("
                        INSERT INTO dms_auto_po_items
                          (org_id, run_id, product_id, name, sku, unit, onhand, reorder_point, target_level, suggested_qty, unit_price, supplier_id".($hasCreatedI?", created_at":"").")
                        VALUES (?,?,?,?,?,?,?,?,?,?,?, ?".($hasCreatedI?", NOW()":"").")
                    ");
                    foreach ($rows as $r) {
                        $ins->execute([
                            $orgId, $runId, (int)$r['product_id'], (string)$r['name'], (string)($r['sku']??''),
                            (string)($r['unit']??'PCS'), (float)$r['onhand'], (float)$r['reorder_point'],
                            (float)$r['target_level'], (float)$r['suggested_qty'], (float)$r['unit_price'],
                            isset($r['supplier_id'])?(int)$r['supplier_id']:null,
                        ]);
                    }
                }

                // Browser? → redirect to run page
                if (!$this->wantsJson()) $this->seeOther($this->moduleBase($org).'/auto-po/run/'.$runId);
            }

            // JSON response (preview or API)
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'=>true, 'org_id'=>$orgId, 'count'=>count($rows), 'generated'=>date('c'),
                'items'=>array_map(static fn($r)=>[
                    'product_id'=>(int)$r['product_id'], 'name'=>(string)$r['name'],
                    'sku'=>(string)($r['sku']??''), 'unit'=>(string)($r['unit']??'PCS'),
                    'onhand'=>(float)$r['onhand'], 'reorder_point'=>(float)$r['reorder_point'],
                    'target_level'=>(float)$r['target_level'], 'suggested_qty'=>(float)$r['suggested_qty'],
                    'unit_price'=>(float)$r['unit_price'], 'supplier_id'=>isset($r['supplier_id'])?(int)$r['supplier_id']:null,
                ], $rows),
                'run_id'=>$runId,
            ], JSON_UNESCAPED_UNICODE | ($pretty?JSON_PRETTY_PRINT:0));
        } catch (\Throwable $e) {
            $this->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    /** GET /auto-po/runs[.json] */
    public function runs(array $ctx=[]): void {
        $org=$this->currentOrg(); $orgId=$this->orgIdFrom($org); $pdo=$this->pdo();

        if ($this->tableExists($pdo,'v_dms_auto_po_runs')) {
            $st=$pdo->prepare("SELECT * FROM v_dms_auto_po_runs WHERE org_id=? ORDER BY id DESC LIMIT 200");
        } else {
            $st=$pdo->prepare("
                SELECT r.*, COUNT(i.id) items_count,
                       COALESCE(SUM(i.suggested_qty),0) total_qty,
                       COALESCE(SUM(i.suggested_qty*i.unit_price),0) est_total
                  FROM dms_auto_po_runs r
                  LEFT JOIN dms_auto_po_items i ON i.org_id=r.org_id AND i.run_id=r.id
                 WHERE r.org_id=? GROUP BY r.id ORDER BY r.id DESC LIMIT 200");
        }
        $st->execute([$orgId]); $rows=$st->fetchAll(PDO::FETCH_ASSOC)?:[];

        $json = (isset($_GET['format']) && $_GET['format']==='json') || str_ends_with(($_SERVER['REQUEST_URI']??''),'.json');
        if ($json) { $this->json(['ok'=>true,'rows'=>$rows]); return; }

        $this->renderContent('auto_po/auto_po_runs', [
            'title'=>'Auto-PO · Saved runs','org'=>$org,'rows'=>$rows,'module_base'=>$this->moduleBase($org),
        ]);
    }
  
  
  		/** GET /auto-po/run/{id}/pdf — stream a PDF */
	public function pdfRun(array $ctx, int $id): void
	{
    try {
        $org = $this->currentOrg();
        $orgId = (int)$org['id'];
        [$run, $items] = $this->runFetch($orgId, $id);

        // Optional: supplier_name join for nicer PDF (if you have dms_suppliers)
        $pdo = $this->pdo();
        if ($this->tableExists($pdo, 'dms_suppliers') && $items) {
            $map = [];
            $in = array_unique(array_map(fn($r)=>(int)($r['supplier_id']??0), $items));
            $in = array_values(array_filter($in));
            if ($in) {
                $ph = implode(',', array_fill(0, count($in), '?'));
                $st = $pdo->prepare("SELECT id,name FROM dms_suppliers WHERE org_id=? AND id IN ($ph)");
                $st->execute(array_merge([$orgId], $in));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) $map[(int)$s['id']] = $s['name'];
                foreach ($items as &$r) if (!empty($r['supplier_id']) && isset($map[(int)$r['supplier_id']])) $r['supplier_name']=$map[(int)$r['supplier_id']];
            }
        }

        $html = $this->runHtml($org, $run, $items);

        $opts = new Options();
        $opts->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fname = 'auto-po-run-'.$id.'.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.$fname.'"');
        echo $dompdf->output();
    } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Failed to render PDF: '.$e->getMessage();
    }
}

/** POST /auto-po/run/{id}/email — email PDF as attachment */
public function emailRun(array $ctx, int $id): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405); echo 'POST only'; return;
    }
    // CSRF
    if (!$this->csrfVerifyPostTenant()) { http_response_code(419); echo 'CSRF token mismatch.'; return; }

    try {
        $org = $this->currentOrg();
        $orgId = (int)$org['id'];
        [$run, $items] = $this->runFetch($orgId, $id);
        $to = trim((string)($_POST['to'] ?? ''));
        if ($to === '') { http_response_code(422); echo 'Recipient required'; return; }

        // Build PDF in-memory
        $html = $this->runHtml($org, $run, $items);
        $opts = new Options(); $opts->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml($html, 'UTF-8'); $dompdf->setPaper('A4'); $dompdf->render();
        $pdf = $dompdf->output();

        // Minimal mail() with attachment (use your mailer if available)
        $from = (string)($org['email'] ?? 'no-reply@'.($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $subj = 'Auto-PO Suggestions — Run #'.$id;
        $boundary = '=_KF_'.bin2hex(random_bytes(8));
        $headers  = "From: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= "Please find attached the Auto-PO suggestions (Run #{$id}).\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/pdf; name=\"auto-po-run-{$id}.pdf\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"auto-po-run-{$id}.pdf\"\r\n\r\n";
        $body .= chunk_split(base64_encode($pdf))."\r\n";
        $body .= "--{$boundary}--";

        $ok = @mail($to, $subj, $body, $headers);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>$ok ? true : false]);
    } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
}
  	/** GET /auto-po/run/{id}/csv — download CSV of run items */
	public function csvRun(array $ctx, int $id): void
	{
    try {
        $org   = $this->currentOrg();
        $orgId = (int)$org['id'];
        [$run, $items] = $this->runFetch($orgId, $id);

        // Optional: join supplier names if available
        $pdo = $this->pdo();
        if ($this->tableExists($pdo, 'dms_suppliers') && $items) {
            $map = [];
            $in = array_unique(array_map(fn($r)=>(int)($r['supplier_id']??0), $items));
            $in = array_values(array_filter($in));
            if ($in) {
                $ph = implode(',', array_fill(0, count($in), '?'));
                $st = $pdo->prepare("SELECT id,name FROM dms_suppliers WHERE org_id=? AND id IN ($ph)");
                $st->execute(array_merge([$orgId], $in));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) $map[(int)$s['id']] = $s['name'];
                foreach ($items as &$r)
                    if (!empty($r['supplier_id']) && isset($map[(int)$r['supplier_id']]))
                        $r['supplier_name'] = $map[(int)$r['supplier_id']];
            }
        }

        // Prepare CSV headers
        $fname = 'auto-po-run-'.$id.'.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Product ID', 'Product Name', 'Supplier', 'On-hand',
            'Reorder Point', 'Target Level', 'Suggested Qty',
            'Unit Price', 'Line Total'
        ]);

        foreach ($items as $it) {
            $pid   = (int)($it['product_id'] ?? 0);
            $name  = (string)($it['name'] ?? '');
            $supp  = (string)($it['supplier_name'] ?? $it['supplier_id'] ?? '');
            $onh   = (float)($it['onhand'] ?? 0);
            $reord = (float)($it['reorder_point'] ?? 0);
            $target= (float)($it['target_level'] ?? 0);
            $sugg  = (float)($it['suggested_qty'] ?? 0);
            $price = (float)($it['unit_price'] ?? 0);
            $line  = round($sugg * $price, 2);
            fputcsv($out, [$pid,$name,$supp,$onh,$reord,$target,$sugg,$price,$line]);
        }

        fclose($out);
    } catch (\Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Failed to export CSV: '.$e->getMessage();
    }
}
  

    /** GET /auto-po/run/{id}[.json] — with supplier names */
    public function showRun(array $ctx, int $id): void {
        $org=$this->currentOrg(); $orgId=$this->orgIdFrom($org); $pdo=$this->pdo();

        $h=$pdo->prepare("SELECT * FROM dms_auto_po_runs WHERE org_id=? AND id=?");
        $h->execute([$orgId,$id]); $run=$h->fetch(PDO::FETCH_ASSOC);
        if(!$run){ http_response_code(404); echo 'Run not found'; return; }

        $i=$pdo->prepare("SELECT * FROM dms_auto_po_items WHERE org_id=? AND run_id=? ORDER BY id");
        $i->execute([$orgId,$id]); $items=$i->fetchAll(PDO::FETCH_ASSOC)?:[];

        // supplier names (if table exists)
        $supNames=[];
        if ($this->tableExists($pdo,'dms_suppliers') && $items) {
            $ids=array_values(array_unique(array_filter(array_map(fn($r)=> (int)($r['supplier_id']??0), $items))));
            if ($ids){
                $inList=implode(',', array_fill(0,count($ids),'?'));
                $s=$pdo->prepare("SELECT id,name FROM dms_suppliers WHERE org_id=? AND id IN ($inList)");
                $s->execute(array_merge([$orgId], $ids));
                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $supNames[(int)$r['id']] = (string)$r['name'];
            }
        }

        $json=(isset($_GET['format']) && $_GET['format']==='json') || str_ends_with(($_SERVER['REQUEST_URI']??''),'.json');
        if ($json){ $this->json(['ok'=>true,'run'=>$run,'items'=>$items,'suppliers'=>$supNames]); return; }

        $this->renderContent('auto_po/auto_po_run_show', [
            'title'=>'Auto-PO Run #'.$id,'org'=>$org,'run'=>$run,'items'=>$items,
            'suppliers'=>$supNames,'module_base'=>$this->moduleBase($org),
        ]);
    }

    /** POST /auto-po/run/{id}/commit — create actual POs grouped by supplier */
    public function commitRun(array $ctx, int $id): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo 'POST only'; return; }
        if (!$this->csrfVerifyPostTenant()) { http_response_code(419); echo 'CSRF token mismatch.'; return; }

        $org=$this->currentOrg(); $orgId=$this->orgIdFrom($org); $pdo=$this->pdo();

        $h=$pdo->prepare("SELECT * FROM dms_auto_po_runs WHERE org_id=? AND id=?");
        $h->execute([$orgId,$id]); $run=$h->fetch(PDO::FETCH_ASSOC);
        if(!$run){ http_response_code(404); echo 'Run not found'; return; }

        $i=$pdo->prepare("SELECT * FROM dms_auto_po_items WHERE org_id=? AND run_id=? ORDER BY id");
        $i->execute([$orgId,$id]); $items=$i->fetchAll(PDO::FETCH_ASSOC)?:[];
        if(!$items){ http_response_code(400); echo 'No items in run'; return; }

        $pc=$this->purchaseCols($pdo);
        $bySupplier=[]; foreach ($items as $r){ $sid=isset($r['supplier_id'])?(int)$r['supplier_id']:0; $bySupplier[$sid][]=$r; }

        // dms_purchases timestamps existing?
        $hasPHCreated = $this->hasCol($pdo,'dms_purchases','created_at');
        $hasPHUpdated = $this->hasCol($pdo,'dms_purchases','updated_at');

        $created=[]; $pdo->beginTransaction();
        try {
            foreach ($bySupplier as $supplierId=>$rows) {
                $grand=0.0; $lines=[];
                foreach ($rows as $r) {
                    $qty=(float)($r['suggested_qty'] ?? 0);
                    $price=(float)($r['unit_price'] ?? 0);
                    $pid=(int)($r['product_id'] ?? 0);

                    // Optional pack/moq soft handling
                    $pack=0.0; $moq=0.0;
                    if ($this->hasCol($pdo,'dms_products','pack_size') || $this->hasCol($pdo,'dms_products','moq')) {
                        $pr=$pdo->prepare("SELECT ".
                            ($this->hasCol($pdo,'dms_products','pack_size')?'pack_size':'NULL')." AS pack_size, ".
                            ($this->hasCol($pdo,'dms_products','moq')?'moq':'NULL')." AS moq
                            FROM dms_products WHERE org_id=? AND id=? LIMIT 1");
                        $pr->execute([$orgId,$pid]); if ($x=$pr->fetch(PDO::FETCH_ASSOC)) { $pack=(float)($x['pack_size']??0); $moq=(float)($x['moq']??0); }
                    }
                    $qFinal=max(0.0,$qty);
                    if ($pack>0){ $snap=floor($qFinal/$pack)*$pack; if($snap>0)$qFinal=$snap; }
                    if ($moq>0 && $qFinal<$moq) $qFinal=$moq;

                    $line=round($qFinal*$price,2); $grand+=$line;
                    $lines[]=['product_id'=>$pid,'product_name'=>(string)($r['name']??''),'qty'=>$qFinal,'unit_price'=>$price,'line_total'=>$line];
                }

                // Header insert (timestamps optional)
                $noCol=$pc['no']; $billNo=$this->nextPurchaseNo($pdo,$orgId,$noCol); $pDate=date('Y-m-d');
                $fields="org_id, {$pc['supplier_id']}, {$pc['supplier']}, {$pc['no']}, {$pc['date']}, {$pc['grand']}, {$pc['status']}";
                $ph="?, ?, ?, ?, ?, ?, ?"; $args=[$orgId,($supplierId?:null),null,$billNo,$pDate,$grand,'draft'];
                if (!empty($pc['notes']) && $this->hasCol($pdo,'dms_purchases',$pc['notes'])) { $fields.=", {$pc['notes']}"; $ph.=", ?"; $args[]='Auto-PO run #'.$id; }
                if ($hasPHCreated){ $fields.=", created_at"; $ph.=", NOW()"; }
                if ($hasPHUpdated){ $fields.=", updated_at"; $ph.=", NOW()"; }

                $insH=$pdo->prepare("INSERT INTO dms_purchases ($fields) VALUES ($ph)");
                $insH->execute($args); $purchaseId=(int)$pdo->lastInsertId(); $created[]=$purchaseId;

                // Lines
                $hasPLCreated = $this->hasCol($pdo,'dms_purchase_items','created_at');
                $insL=$pdo->prepare("
                    INSERT INTO dms_purchase_items
                      (org_id, purchase_id, product_id, product_name, qty, unit_price, line_total".($hasPLCreated?", created_at":"").")
                    VALUES (?,?,?,?,?,?,?".($hasPLCreated?", NOW()":"").")
                ");
                foreach ($lines as $ln) {
                    $insL->execute([$orgId,$purchaseId,$ln['product_id'],$ln['product_name'],$ln['qty'],$ln['unit_price'],$ln['line_total']]);
                }
            }

            // mark run
            if ($this->hasCol($pdo,'dms_auto_po_runs','status')) {
                $sql="UPDATE dms_auto_po_runs SET status='committed'";
                if ($this->hasCol($pdo,'dms_auto_po_runs','updated_at')) $sql.=", updated_at=NOW()";
                $sql.=" WHERE org_id=? AND id=?";
                $pdo->prepare($sql)->execute([$orgId,$id]);
            }

            $pdo->commit();

            if ($this->wantsJson()) {
                $this->json(['ok'=>true,'run_id'=>$id,'created_purchase_ids'=>$created]);
            } else {
                $this->seeOther($this->moduleBase($org).'/purchases?created='.rawurlencode(implode(',',$created)));
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($this->wantsJson()) $this->json(['ok'=>false,'error'=>$e->getMessage()], 500);
            http_response_code(500); echo 'Failed to commit Auto-PO run: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');
        }
    }
}