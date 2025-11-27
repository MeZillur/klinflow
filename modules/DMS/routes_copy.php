<?php
declare(strict_types=1);

/* =============================================================================
 * SEGMENT 0: Hard includes (avoid autoload races for landing controller)
 * =========================================================================== */
$ctlPath = __DIR__ . '/src/Controllers/LandingController.php';
if (is_file($ctlPath)) { require_once $ctlPath; }

/* =============================================================================
 * SEGMENT 1: Debug bootstrap (minimal, tenant-safe)
 * =========================================================================== */
(function () {
    $ehFqn = '\\Shared\\Debug\\ErrorHandler';
    if (!class_exists($ehFqn, false)) {
        $here = __DIR__;
        $root = dirname($here, 2);
        $doc  = $_SERVER['DOCUMENT_ROOT'] ?? '';
        foreach ([
            $root . '/shared/Debug/ErrorHandler.php',
            dirname($here) . '/shared/Debug/ErrorHandler.php',
            $doc . '/shared/Debug/ErrorHandler.php',
        ] as $cand) { if ($cand && is_file($cand)) { require_once $cand; break; } }
    }

    $debug = false;
    $env = getenv('APP_DEBUG');
    if ($env === '1' || strcasecmp((string)$env, 'true') === 0) $debug = true;
    if (isset($_GET['_debug']) && $_GET['_debug'] === '1')      $debug = true;

    $logFile = '/tmp/klinflow-dms.log';

    if (class_exists($ehFqn)) {
        $ehFqn::boot(['debug'=>$debug, 'log_file'=>$logFile]);
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
        set_error_handler(function ($sev, $msg, $file, $line) {
            if (!(error_reporting() & $sev)) return false;
            throw new \ErrorException($msg, 0, $sev, $file, $line);
        });
        register_shutdown_function(function () use ($debug) {
            $e = error_get_last();
            if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
            http_response_code(500);
            echo $debug
                ? "<pre>FATAL: {$e['message']}\n{$e['file']}:{$e['line']}</pre>"
                : "<h1>Unexpected error</h1>";
        });
    }

    if (!headers_sent()) header('X-Request-ID: ' . bin2hex(random_bytes(6)));
})();

/* =============================================================================
 * SEGMENT 2: Context boot
 * =========================================================================== */
use Shared\View;

$mod         = (array)($__KF_MODULE__ ?? []);
$slug        = (string)($mod['slug'] ?? '');
$tail        = trim((string)($mod['tail'] ?? ''), '/');
$moduleDir   = (string)($mod['module_dir'] ?? __DIR__);
$method      = strtoupper((string)($mod['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
$manifest    = (array)($mod['manifest'] ?? (is_file($moduleDir.'/manifest.php') ? require $moduleDir.'/manifest.php' : []));
$manifestLay = (string)($manifest['layout'] ?? 'Views/shared/layouts/shell.php');

if ($slug === '' && isset($_SERVER['REQUEST_URI']) && preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
    $slug = $m[1];
}
if (!is_dir($moduleDir)) $moduleDir = realpath(dirname(__DIR__));

/* Resolve org: session → DB by slug (soft; non-fatal on failure) */
$ctxOrg = (array)($mod['org'] ?? []);
if (empty($ctxOrg)) {
    if (!empty($_SESSION['tenant_org']) && is_array($_SESSION['tenant_org'])) {
        $ctxOrg = $_SESSION['tenant_org'];
    }
    if ((int)($ctxOrg['id'] ?? 0) <= 0 && $slug !== '') {
        try {
            if (function_exists('\\App\\DB\\pdo')) {
                $pdo = \App\DB\pdo();
            } elseif (class_exists('\\App\\DB')) {
                $pdo = \App\DB::pdo();
            } else {
                $pdo = null;
            }
            if ($pdo instanceof \PDO) {
                $st = $pdo->prepare("SELECT id, slug, name FROM cp_organizations WHERE slug = :s LIMIT 1");
                $st->execute([':s' => $slug]);
                $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                if ($row) $ctxOrg = array_merge((array)$ctxOrg, $row);
            }
        } catch (\Throwable $e) { /* ignore */ }
    }
}
$ctxOrg['id']   = (int)($ctxOrg['id'] ?? 0);
$ctxOrg['slug'] = (string)($ctxOrg['slug'] ?? $slug);

$effectiveSlug = $ctxOrg['slug'] ?: $slug;
$module_base   = $effectiveSlug ? '/t/' . rawurlencode($effectiveSlug) . '/apps/dms' : '/apps/dms';

/* Redirect plain /apps/dms → tenant path if slug known */
if ($effectiveSlug && isset($_SERVER['REQUEST_URI'])
    && preg_match('#^/apps/dms(?:/|$)#', (string)$_SERVER['REQUEST_URI'])) {
    if (!headers_sent()) {
        header('Location: /t/' . rawurlencode($effectiveSlug) . '/apps/dms/' . $tail, true, 302);
    }
    exit;
}

/* Optional layout from manifest */
$layoutFile = is_file($moduleDir.'/'.$manifestLay) ? $moduleDir.'/'.$manifestLay : null;

/* Canonical context */
$ctx = [
  'slug'        => $effectiveSlug,
  'org'         => $ctxOrg,
  'module_base' => $module_base,
  'module_dir'  => $moduleDir,
  'layout'      => $layoutFile,
  'scope'       => 'tenant',
];

/* =============================================================================
 * SEGMENT 3: Render helpers
 * =========================================================================== */
$render = function (string $view, array $data = []) use ($ctx, $moduleDir): void {
    $viewPath = "$moduleDir/Views/$view.php";
    if (!is_file($viewPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "View not found: $view";
        exit;
    }
    // canonical sidenav path (no partials, no double shell)
    $side = "$moduleDir/Views/shared/sidenav.php";
    $vars = array_merge($ctx, [
        'moduleSidenav' => is_file($side) ? $side : null,
        'shell' => 'module',
    ], $data);
    View::render($viewPath, $vars, $ctx['layout']);
};

$notFound = function (string $m = 'Not found') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404: $m";
    exit;
};
$has = fn($fqn) => class_exists($fqn);

/* =============================================================================
 * SEGMENT 4: URL segments
 * =========================================================================== */
$parts = $tail ? array_values(array_filter(explode('/', $tail))) : [];
$seg   = fn(int $i) => $parts[$i] ?? null;
[$first, $second, $third] = array_map('strval', [$seg(0), $seg(1), $seg(2)]);

/* =============================================================================
 * SEGMENT 5: Controller imports
 * =========================================================================== */
use Modules\DMS\Controllers\{
  DashboardController,
  OrdersController,
  PaymentsController,
  ProductsController,
  CategoriesController,
  PurchasesController,
  InventoryController,
  BankAccountsController,
  ExpensesController,
  CustomersController,
  SuppliersController,
  StakeholdersController,
  FreeProductsController,
  AccountingDashboardController,
  SalesController,
  SalesReturnsController,
  PriceTiersController,
  ArReportsController,
  ReportsController,
  LookupController,
  DmsChallanController,
  DmsForecastController,
  ApiController,
  LandingController,
  DmsAutoPoController,
  DemandForecastController,
  SettingsController as DmsSettingsController
};
use Modules\DMS\Controllers\Accounts\{
  CoaController, JournalsController, LedgerController,
  TrialBalanceController, ProfitLossController, BalanceSheetController
};
use App\Controllers\Tenant\UsersController;

/* =============================================================================
 * SEGMENT 6: Landing & Dashboard
 *   - "" or "home" -> LandingController::home
 *   - "dashboard"  -> DashboardController::index (or view fallback)
 * =========================================================================== */
if ($tail === '' || $first === 'home') {
    if ($has(LandingController::class)) { (new LandingController())->home($ctx); exit; }
    $render('dashboard/index', ['title' => 'Dashboard']); exit;
}

if ($first === 'dashboard') {
    if ($has(DashboardController::class)) { (new DashboardController())->index($ctx); exit; }
    $render('dashboard/index', ['title' => 'Dashboard']); exit;
}

/* =============================================================================
 * SEGMENT 7: Accounts / GL Hub
 * =========================================================================== */
if ($first === 'accounts') {
    $acct = new AccountingDashboardController();

    if ($second === '' && $method==='GET')           { $acct->index($ctx);       exit; }
    if ($second === 'cash-book')                     { $acct->cashBook($ctx);    exit; }
    if ($second === 'bank-book')                     { $acct->bankBook($ctx);    exit; }
    if ($second === 'mobile-bank-book')              { $acct->mobileBankBook($ctx); exit; }

    if ($second === 'journals') {
        $ctl = new JournalsController();
        if ($third === '' && $method==='GET')        { $ctl->index($ctx); exit; }
        if (ctype_digit($third))                     { $ctl->show($ctx,(int)$third); exit; }
    }

    if ($second === 'ledger')                        { (new LedgerController())->index($ctx);        exit; }
    if ($second === 'trial-balance')                 { (new TrialBalanceController())->index($ctx);  exit; }
    if ($second === 'profit-and-loss')               { (new ProfitLossController())->index($ctx);    exit; }
    if ($second === 'balance-sheet')                 { (new BalanceSheetController())->index($ctx);  exit; }

    if ($second === 'coa') {
        $coa = new CoaController();
        if ($third === '' && $method==='GET')        { $coa->index($ctx);   exit; }
        if ($third === 'create')                     { $coa->create($ctx);  exit; }
        if ($third === 'update')                     { $coa->update($ctx,(int)$third); exit; } // if you need id, change path pattern
        if ($third === 'delete')                     { $coa->destroy($ctx,(int)$third); exit; }
    }

    // Optional AR dashboard if present
    if ($second === 'ar' && class_exists('\\Modules\\DMS\\Controllers\\ARController')) {
        (new \Modules\DMS\Controllers\ARController())->index($ctx); exit;
    }

    $notFound('/accounts route not found');
}

/* =============================================================================
 * SEGMENT 8: Reports
 * =========================================================================== */
if ($first === 'reports') {
    if ($second === 'health') {
        $h = new \Modules\DMS\Controllers\HealthController();
        if ($third === '' || $third === null) { $h->index($ctx); exit; }
        if ($third === 'gl')                  { $h->gl($ctx);    exit; }
        if ($third === 'stock')               { $h->stock($ctx); exit; }
        if ($third === 'map-keys')            { $h->mapKeys($ctx); exit; }
        $h->index($ctx); exit;
    }

    if ($second === 'ar-statement') {
        $r = new ArReportsController();
        if ($third === '' || $third === null) { $r->statement($ctx);       exit; }
        if ($third === 'print')               { $r->statementPrint($ctx);  exit; }
        $r->statement($ctx); exit;
    }

    if ($second === 'customer-statement') {
        $r = new ReportsController();
        if ($third === '' || $third === null) { $r->customerStatement($ctx);    exit; }
        if ($third === 'print')               { $r->customerStatementPdf($ctx); exit; }
        $r->customerStatement($ctx); exit;
    }

    $render('reports/index', ['title' => 'Reports']); exit;
}

/* =============================================================================
 * SEGMENT 9: Payments
 * =========================================================================== */
if ($first === 'payments') {
    $p = new PaymentsController();
    if ($second === '' && $method==='GET')             { $p->index($ctx);  exit; }
    if ($second === 'create')                          { $p->create($ctx); exit; }
    if (ctype_digit($second) && $third==='edit')       { $p->edit($ctx,(int)$second); exit; }
    if (ctype_digit($second)) {
        $id=(int)$second;
        if     ($method==='GET')  $p->show($ctx,$id);
        elseif ($method==='POST') $p->update($ctx,$id);
        exit;
    }
}

/* =============================================================================
 * SEGMENT 10: Sales (+ Returns under Sales)
 * Routes supported:
 *   /sales                      GET index, POST store
 *   /sales/create               GET create
 *   /sales/{id}                 GET show
 *   /sales/{id}/print           GET print
 *   /sales/{id}/pay             POST pay (mark paid)
 *   /sales/{id}/deliver         POST deliver (optional)
 *   /sales/dispatch             GET challan list
 *   /sales/{id}/create-challan  POST create challan from invoice
 *
 *   /returns                    same pattern but for returns
 *   /sales/returns/...          alias of /returns/...
 * =========================================================================== */
if ($first === 'sales' || $first === 'returns') {

    // ---------- Returns branch: /returns/*  OR  /sales/returns/* ----------
    if ($first === 'returns' || ($first === 'sales' && $second === 'returns')) {
        if (!class_exists(\Modules\DMS\Controllers\SalesReturnsController::class)) {
            http_response_code(501);
            exit('SalesReturnsController not available');
        }

        $rSeg1 = ($first === 'returns') ? rawurldecode((string)($seg(1) ?? ''))
                                        : rawurldecode((string)($seg(2) ?? ''));
        $rSeg2 = ($first === 'returns') ? rawurldecode((string)($seg(2) ?? ''))
                                        : rawurldecode((string)($seg(3) ?? ''));

        $ret = new \Modules\DMS\Controllers\SalesReturnsController();

        // Collection
        if ($rSeg1 === ''         && $method === 'GET')  { $ret->index($ctx);  exit; }
        if ($rSeg1 === ''         && $method === 'POST') { $ret->store($ctx);  exit; }
        if ($rSeg1 === 'create'   && $method === 'GET')  { $ret->create($ctx); exit; }

         // Item
    if (ctype_digit($sSeg1)) {
        $id = (int)$sSeg1;
        if ($sSeg2 === ''       && $method === 'GET')  { $sales->show($ctx, $id);   exit; }
        if ($sSeg2 === 'edit'   && $method === 'GET')  { $sales->edit($ctx, $id);   exit; }
        if ($sSeg2 === ''       && $method === 'POST') { $sales->update($ctx, $id); exit; }
        if ($sSeg2 === 'print'  && $method === 'GET')  { $sales->print($ctx, $id);  exit; }
        if ($sSeg2 === 'delete' && in_array($method, ['POST','DELETE'], true) && method_exists($sales,'destroy')) {
            $sales->destroy($ctx, $id); exit;
        }
    }


        http_response_code(404);
        exit('Unknown /returns route');
    }

    // --------------------------- Sales branch ---------------------------
    if (!class_exists(\Modules\DMS\Controllers\SalesController::class)) {
        http_response_code(501);
        exit('SalesController not available');
    }

    // NOTE: Do NOT use lowercase namespace fallback on Linux/PSR-4 — it can trigger autoloader fatals.
    $challanCtrlFqn = '\Modules\DMS\Controllers\DmsChallanController';

    $sSeg1 = rawurldecode((string)($seg(1) ?? ''));
    $sSeg2 = rawurldecode((string)($seg(2) ?? ''));
    $sales = new \Modules\DMS\Controllers\SalesController();

    // /sales/dispatch → challan list
    if ($sSeg1 === 'dispatch') {
        if ($method !== 'GET') { http_response_code(405); exit('Method Not Allowed'); }
        if (!class_exists($challanCtrlFqn)) { http_response_code(501); exit('DmsChallanController not available'); }
        (new $challanCtrlFqn())->index(); exit;
    }

    // Collection
    if ($sSeg1 === '') {
        if     ($method === 'GET')  { $sales->index($ctx);  exit; }
        elseif ($method === 'POST') { $sales->store($ctx);  exit; }
        http_response_code(405); exit('Method Not Allowed');
    }

    if ($sSeg1 === 'create' && $method === 'GET') { $sales->create($ctx); exit; }

    // Item
    if ($sSeg1 !== '' && ctype_digit($sSeg1)) {
        $id = (int)$sSeg1;

        if ($sSeg2 === '') {
            if     ($method === 'GET')  { $sales->show($ctx, $id);   exit; }
            elseif ($method === 'POST') { http_response_code(405); exit('Method Not Allowed'); }
        }
        if ($sSeg2 === 'print'   && $method === 'GET')  { $sales->print($ctx, $id);  exit; }
        if ($sSeg2 === 'pay'     && $method === 'POST') { $sales->pay($ctx, $id);    exit; }
        if ($sSeg2 === 'deliver' && $method === 'POST') { $sales->deliver($ctx, $id); exit; }

        if ($sSeg2 === 'create-challan' && $method === 'POST') {
            if (!class_exists($challanCtrlFqn)) { http_response_code(501); exit('DmsChallanController not available'); }
            // pass invoice id as expected by controller
            $_POST['invoice_id'] = $id;
            (new $challanCtrlFqn())->createFromInvoice();
            exit;
        }

        http_response_code(404);
        exit('Unknown /sales item route');
    }

    http_response_code(404);
    exit('Unknown /sales route');
}

/* ═══════════════════════════ 10.1) Returns (Sales Returns) ═══════════════════════════ */
if ($first === 'returns' && class_exists(SalesReturnsController::class)) {
    $r = new SalesReturnsController();

    // List + create
    if ($second === '' && $method === 'GET')  { $r->index($ctx);  exit; }
    if ($second === 'create' && $method === 'GET') { $r->create($ctx); exit; }
    if ($second === '' && $method === 'POST') { $r->store($ctx);  exit; }

    // Single
    if (ctype_digit($second)) {
        $id = (int)$second;
        if ($third === ''      && $method === 'GET')  { $r->show($ctx,$id);    exit; }
        if ($third === 'edit'  && $method === 'GET')  { $r->edit($ctx,$id);    exit; }
        if ($third === ''      && $method === 'POST') { $r->update($ctx,$id);  exit; }
        if ($third === 'print' && $method === 'GET')  { $r->print($ctx,$id);   exit; }
        if ($third === 'delete'&& in_array($method,['POST','DELETE'], true) && method_exists($r,'destroy')) {
            $r->destroy($ctx,$id); exit;
        }
    }

    // Unknown route
    http_response_code(404);
    exit('Unknown /returns route');
}

/* =============================================================================
 * SEGMENT 11: I18N (en/bn)
 * =========================================================================== */
if ($first === 'i18n') {
    $json = static function(array $data, int $code=200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    };

    $i18nFile = rtrim($moduleDir,'/').'/i18n.php';
    if (is_file($i18nFile)) {
        require_once $i18nFile;
        if (function_exists('\Modules\DMS\i18n_boot')) {
            \Modules\DMS\i18n_boot($ctx, $moduleDir);
        }
    }

    if ($second === 'set') {
        $loc = strtolower((string)($_GET['locale'] ?? 'en'));
        if (!in_array($loc, ['en','bn'], true)) { http_response_code(400); exit('Invalid locale'); }
        if (class_exists('\Modules\DMS\I18n')) {
            \Modules\DMS\I18n::setLocale($ctx, $loc, true);
        } else {
            $_SESSION['dms_locale'] = $loc;
            setcookie('dms_locale', $loc, time()+86400*365, '/', '', isset($_SERVER['HTTPS']), true);
        }
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/json') || isset($_GET['json'])) {
            $json(['ok'=>true,'locale'=>$loc]);
        }
        http_response_code(204); exit;
    }

    if ($second === 'strings') {
        $strings = class_exists('\Modules\DMS\I18n') ? \Modules\DMS\I18n::strings() : [];
        $json(['locale'=>(string)($_SESSION['dms_locale'] ?? 'en'),'strings'=>$strings]);
    }

    if ($second === 'locale') {
        $json(['locale'=>(string)($_SESSION['dms_locale'] ?? 'en')]);
    }

    $notFound('Unknown /i18n route.');
}

/* =============================================================================
 * SEGMENT 12: Tenant Users (universal)
 * =========================================================================== */
if ($first === 't' && class_exists(UsersController::class)) {
    $tenantSlug = (string)($second ?? '');
    if ($tenantSlug === '') { $notFound('Tenant slug missing'); }
    $u   = new UsersController();
    $pctx = $ctx; $pctx['slug'] = $tenantSlug;
    $s3  = (string)($seg(2) ?? '');
    $s4  = (string)($seg(3) ?? '');

    if ($s3 === 'users' && $s4 === '' && $method==='GET')  { $u->index($pctx);       exit; }
    if ($s3 === 'users' && $s4 === 'invite') {
        if ($method==='GET')  { $u->inviteForm($pctx);      exit; }
        if ($method==='POST') { $u->sendInvite($pctx);      exit; }
    }
    if ($s3 === 'users' && $s4 === 'me') {
        if ($method==='GET')  { $u->profile($pctx);         exit; }
        if ($method==='POST') { $u->updateProfile($pctx);   exit; }
    }
}
if ($first === 'tenant' && $second==='invite' && $third==='accept' && class_exists(UsersController::class)) {
    $u = new UsersController();
    if     ($method==='GET')  { $u->acceptForm(); exit; }
    elseif ($method==='POST') { $u->accept();     exit; }
    $notFound('Unsupported /tenant/invite/accept');
}

/* =============================================================================
 * SEGMENT 13: Orders
 * =========================================================================== */
if ($first === 'orders' && class_exists(OrdersController::class)) {
    $o = new OrdersController();

    // --- Create (collection/new) ---
    if ($second === 'create' && $method === 'GET')  { $o->create($ctx); exit; }
    if ($second === 'create' && $method === 'POST') { $o->store($ctx);  exit; }

    // --- Collection ---
    if ($second === '' && $method === 'GET')  { $o->index($ctx); exit; }
    if ($second === '' && $method === 'POST') { $o->store($ctx); exit; }
  
  
    // --- Debug endpoints ---
if ($second === 'smoke-insert' && $method === 'GET') { $o->smokeInsert($ctx); exit; }

    // --- Member ---
    if (ctype_digit($second)) {
        $id = (int)$second;
        if ($third === ''      && $method==='GET')   { $o->show($ctx,$id);   exit; }
        if ($third === 'edit'  && $method==='GET')   { $o->edit($ctx,$id);   exit; }
        if ($third === ''      && $method==='POST')  { $o->update($ctx,$id); exit; }
        if ($third === 'issue-invoice')              { $o->issueInvoice($ctx,$id); exit; }
        if ($third === 'print' && $method==='GET')   { $o->print($ctx,$id);  exit; }
        if ($third === 'share' && $method==='GET') {
            $chan = (string)($seg(3) ?? '');
            if ($chan === 'whatsapp' && method_exists($o,'shareWhatsApp')) { $o->shareWhatsApp($ctx,$id); exit; }
            if ($chan === 'email'    && method_exists($o,'shareEmail'))    { $o->shareEmail($ctx,$id);    exit; }
        }
    }
}

/* =============================================================================
 * SEGMENT 14: Purchases
 * =========================================================================== */
if ($first === 'purchases' && class_exists(PurchasesController::class)) {
    $pc = new PurchasesController();
    if ($second === '' && $method==='GET')                { $pc->index($ctx);  exit; }
    if ($second === '' && $method==='POST')               { $pc->store($ctx);  exit; }
    if ($second === 'create' && $method==='GET')          { $pc->create($ctx); exit; }

    // Lookups scoped to purchase forms (legacy)
    if ($second === 'suppliers.lookup.json' && $method==='GET') { $pc->suppliersLookup($ctx); exit; }
    if ($second === 'products.lookup.json'  && $method==='GET') { $pc->productsLookup($ctx);  exit; }

    if (ctype_digit($second)) {
        $id = (int)$second;
        if ($third === ''       && $method==='GET')  { $pc->show($ctx,$id);    exit; }
        if ($third === 'edit'   && $method==='GET')  { $pc->edit($ctx,$id);    exit; }
        if ($third === 'update' && $method==='POST') { $pc->update($ctx,$id);  exit; }
        if ($third === 'delete' && $method==='POST') { $pc->destroy($ctx,$id); exit; }
    }
}
if ($first === 'purchase' && class_exists(PurchasesController::class)) {
    (new PurchasesController())->create($ctx); exit; // legacy alias
}

/* Small helper to resolve controller class with either module case */
$__resolveCtrl = static function(array $candidates): ?string {
    foreach ($candidates as $fqcn) {
        if (class_exists($fqcn)) return $fqcn;
    }
    return null;
};

/* =============================================================================
 * SEGMENT: 14-A Demand Forecasting
 *   /forecast                 GET index
 *   /forecast/run             POST run
 *   /products/forecast        GET index (alias)
 * =========================================================================== */
if ($first === 'forecast' || ($first === 'products' && $second === 'forecast')) {
    $DemandCtrl = $__resolveCtrl([
        \Modules\dms\Controllers\DemandForecastController::class,
        \Modules\DMS\Controllers\DemandForecastController::class,
    ]);
    if (!$DemandCtrl) { http_response_code(501); exit('DemandForecastController not available'); }

    // when called as /products/forecast, shift segments by 1
    $fSeg1 = ($first === 'forecast') ? (string)($seg(1) ?? '') : (string)($seg(2) ?? '');

    /** @var object $fc */
    $fc = new $DemandCtrl();

    if ($fSeg1 === ''          && $method === 'GET')  { $fc->index($ctx);    exit; }
    if ($fSeg1 === 'run'       && $method === 'POST') { $fc->run($ctx);      exit; }
    if ($fSeg1 === 'download'  && $method === 'GET' && method_exists($fc,'download')) {
        $fc->download($ctx); exit;
    }

    http_response_code(404);
    exit('Unknown /forecast route');
}

/* =============================================================================
 * SEGMENT: Auto Purchase Orders (Auto-PO)
 *   /auto-po                    GET  index
 *   /auto-po/run                GET  preview JSON | POST snapshot (save)
 *   /auto-po/runs               GET  runs list (HTML/JSON)
 *   /auto-po/run/{id}           GET  show single run | POST commit (fallback)
 *   /auto-po/run/{id}/commit    POST commit run -> create POs
 *   /auto-po/run/{id}/pdf       GET  download/stream PDF
 *   /auto-po/run/{id}/csv       GET  download CSV
 *   /auto-po/run/{id}/email     POST send PDF via email
 *   (alias) /products/auto-po   GET  index (same handlers with +1 offset)
 * =========================================================================== */
if ($first === 'auto-po' || ($first === 'products' && $second === 'auto-po')) {
    // Resolve controller (case-insensitive)
    $AutoPoCtrl = $__resolveCtrl([
        \Modules\DMS\Controllers\AutoPoController::class,
        \Modules\dms\Controllers\AutoPoController::class,
    ]);
    if (!$AutoPoCtrl) { http_response_code(501); exit('AutoPoController not available'); }

    // If routed as /products/auto-po/..., shift segment indexes by +1
    $offset = ($first === 'auto-po') ? 0 : 1;

    $s1 = (string)($seg(1 + $offset) ?? ''); // '', 'run', 'runs'
    $s2 = (string)($seg(2 + $offset) ?? ''); // '', '{id}'
    $s3 = (string)($seg(3 + $offset) ?? ''); // '', 'commit' | 'pdf' | 'csv' | 'email'

    /** @var object $apo */
    $apo    = new $AutoPoCtrl();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // /auto-po
    if ($s1 === '') {
        if ($method === 'GET') { $apo->index($ctx); exit; }
        http_response_code(405); exit('Method not allowed');
    }

    // /auto-po/run  (GET preview JSON | POST save snapshot)
    if ($s1 === 'run' && $s2 === '') {
        // Controller handles GET vs POST internally
        $apo->run($ctx); exit;
    }

    // /auto-po/runs  (GET list)
    if ($s1 === 'runs' && $s2 === '') {
        if ($method === 'GET') { $apo->runs($ctx); exit; }
        http_response_code(405); exit('Method not allowed');
    }

    // /auto-po/run/{id}[/{action}]
    if ($s1 === 'run' && ctype_digit($s2)) {
        $id = (int)$s2;

        // /auto-po/run/{id}
        if ($s3 === '') {
            if     ($method === 'GET')  { $apo->showRun($ctx, $id);   exit; }
            elseif ($method === 'POST') { $apo->commitRun($ctx, $id); exit; } // fallback: POST to same URL
            http_response_code(405); exit('Method not allowed');
        }

        // /auto-po/run/{id}/commit  (POST)
        if ($s3 === 'commit') {
            if ($method === 'POST') { $apo->commitRun($ctx, $id); exit; }
            http_response_code(405); exit('Method not allowed');
        }

        // /auto-po/run/{id}/pdf  (GET)
        if ($s3 === 'pdf') {
            if ($method === 'GET') { $apo->pdfRun($ctx, $id); exit; }
            http_response_code(405); exit('Method not allowed');
        }

        // /auto-po/run/{id}/csv  (GET)
        if ($s3 === 'csv') {
            if ($method === 'GET') { $apo->csvRun($ctx, $id); exit; }
            http_response_code(405); exit('Method not allowed');
        }

        // /auto-po/run/{id}/email  (POST)
        if ($s3 === 'email') {
            if ($method === 'POST') { $apo->emailRun($ctx, $id); exit; }
            http_response_code(405); exit('Method not allowed');
        }

        http_response_code(404); exit('Unknown /auto-po run sub-route');
    }

    http_response_code(404);
    exit('Unknown /auto-po route');
}
/* =============================================================================
 * SEGMENT 15: Products (tiers pages + api)
 * =========================================================================== */

if ($first === 'products' && class_exists(ProductsController::class)) {
    $p = new ProductsController();

    if ($second === 'create' && $method==='GET')           { $p->create($ctx);      exit; }
    if ($second === 'barcode.json' && $method==='GET')     { $p->barcodeJson($ctx); exit; }
    if ($second === 'lookup.json' && $method==='GET')      { $p->lookup($ctx);      exit; }

    if ($second === '' && $method==='GET')                 { $p->index($ctx);  exit; }
    if ($second === '' && $method==='POST')                { $p->store($ctx);  exit; }

    // ----- tiers (HTML & JSON) -----
    if (ctype_digit($second)) {
        $id = (int)$second;

        // HTML tiers page
        if ($third === 'tiers' && $method==='GET' && class_exists(PriceTiersController::class)) {
            (new PriceTiersController())->page($ctx, $id); exit;
        }
        // JSON list of tiers
        if ($third === 'tiers.json' && $method==='GET' && class_exists(PriceTiersController::class)) {
            (new PriceTiersController())->index($ctx, $id); exit;
        }
        // create draft tier (from tiers page form)
        if ($third === 'tiers' && $method==='POST' && class_exists(PriceTiersController::class)) {
            (new PriceTiersController())->store($ctx, $id); exit;
        }

        if     ($third === 'edit' && $method==='GET')      { $p->edit($ctx,$id);   exit; }
        elseif ($third === ''     && $method==='GET')      { $p->show($ctx,$id);   exit; }
        elseif ($third === ''     && $method==='POST')     { $p->update($ctx,$id); exit; }
    }
}

// tier state mutations
if ($first === 'tiers' && class_exists(PriceTiersController::class) && ctype_digit($second)) {
    $tid = (int)$second;
    $t = new PriceTiersController();
    if ($third === 'publish' && $method==='POST') { $t->publish($ctx, $tid); exit; }
    if ($third === 'retire'  && $method==='POST') { $t->retire($ctx,  $tid); exit; }
    if ($third === 'delete'  && $method==='POST') { $t->destroy($ctx, $tid); exit; }
}

/* =============================================================================
 * SEGMENT 16: Categories
 * =========================================================================== */
if ($first === 'categories' && class_exists(CategoriesController::class)) {
    $c = new CategoriesController();

    $m = $method;
    if ($m==='POST' && isset($_POST['_method'])) {
        $ov = strtoupper(trim((string)$_POST['_method']));
        if (in_array($ov, ['PUT','PATCH','DELETE'], true)) $m = $ov;
    }

    if ($second === '' && $m==='GET')                  { $c->index($ctx);  exit; }
    if ($second === '' && $m==='POST')                 { $c->store($ctx);  exit; }
    if ($second === 'create' && $m==='GET')            { $c->create($ctx); exit; }

    if (ctype_digit($second)) {
        $id = (int)$second;
        $sub = (string)($seg(2) ?? '');
        if ($sub === ''      && $m==='GET')           { $c->show($ctx,$id);   exit; }
        if ($sub === 'edit'  && $m==='GET')           { $c->edit($ctx,$id);   exit; }
        if ($sub === ''      && in_array($m,['POST','PUT','PATCH'], true)) { $c->update($ctx,$id); exit; }
        if ($sub === 'delete'&& in_array($m,['POST','DELETE'], true))      { $c->destroy($ctx,$id); exit; }
    }

    $notFound('Unknown /categories route.');
}

/* =============================================================================
 * SEGMENT 17: Inventory
 * =========================================================================== */
if ($first === 'inventory' && class_exists(InventoryController::class)) {
    $inv = new InventoryController();
    if ($second === ''       && $method==='GET')  { $inv->index($ctx);       exit; }
    if ($second === 'aging'  && $method==='GET')  { $inv->aging($ctx);       exit; }
    if ($second === 'adjust' && $method==='GET')  { $inv->adjust($ctx);      exit; }
    if ($second === 'adjust' && $method==='POST') { $inv->storeAdjust($ctx); exit; }
    if ($second === 'damage' && $method==='GET')  { $inv->damage($ctx);      exit; }
    if ($second === 'damage' && $method==='POST') { $inv->storeDamage($ctx); exit; }
}

/* =============================================================================
 * SEGMENT 18: Bank Accounts + Expenses
 * =========================================================================== */
if ($first === 'bank-accounts' && class_exists(BankAccountsController::class)) {
    $b = new BankAccountsController();

    if ($second === '' && $method==='GET' && !isset($_GET['create'])) { $b->index($ctx);  exit; }
    if (($second === '' && $method==='GET' && isset($_GET['create'])) || $second==='create') { $b->create($ctx); exit; }
    if ($second === '' && $method==='POST') { $b->store($ctx); exit; }

    if (ctype_digit($second) && ($third ?? '') === 'make-master' && $method==='POST') { $b->makeMaster($ctx,(int)$second); exit; }
    if (ctype_digit($second) && ($third ?? '') === 'edit' && $method==='GET')         { $b->edit($ctx,(int)$second);      exit; }
    if (ctype_digit($second) && ($third ?? '') === '' && $method==='GET')             { $b->show($ctx,(int)$second);      exit; }
    if (ctype_digit($second) && ($third ?? '') === '' && $method==='POST')            { $b->update($ctx,(int)$second);    exit; }
}
if ($first === 'expenses' && class_exists(ExpensesController::class)) {
    $e = new ExpensesController();
    if ($second === '' && $method==='GET')  { $e->index($ctx);  exit; }
    if ($second === '' && $method==='POST') { $e->store($ctx);  exit; }
    if ($second === 'create' && $method==='GET') { $e->create($ctx); exit; }
}

/* =============================================================================
 * SEGMENT 19: Customers / Suppliers
 * =========================================================================== */
if ($first === 'customers' && class_exists(CustomersController::class)) {
    $c = new CustomersController();
    if ($second === '' && $method==='GET')                          { $c->index($ctx);  exit; }
    if ($second === '' && $method==='POST')                         { $c->store($ctx);  exit; }
    if ($second === 'create' && $method==='GET')                    { $c->create($ctx); exit; }
    if ($second === 'credit-summary' && $method==='GET')            { $c->creditSummary($ctx); exit; }
    if (ctype_digit($second) && $third==='' && $method==='GET')     { $c->show($ctx,(int)$second);  exit; }
    if (ctype_digit($second) && $third==='edit' && $method==='GET') { $c->edit($ctx,(int)$second);  exit; }
    if (ctype_digit($second) && $third==='' && $method==='POST')    { $c->update($ctx,(int)$second); exit; }
}
if ($first === 'suppliers' && class_exists(SuppliersController::class)) {
    $s = new SuppliersController();
    if ($second === '' && $method==='GET')  { $s->index($ctx);  exit; }
    if ($second === '' && $method==='POST') { $s->store($ctx);  exit; }
    if ($second === 'create' && $method==='GET') { $s->create($ctx); exit; }
    if ($second === 'export.csv' && $method==='GET') { $s->export($ctx); exit; } // keep before numeric
    if ($second === 'lookup.json' && $method==='GET') { $s->lookup($ctx); exit; }
    if (ctype_digit($second) && $third==='' && $method==='GET')     { $s->show($ctx,(int)$second);   exit; }
    if (ctype_digit($second) && $third==='edit' && $method==='GET') { $s->edit($ctx,(int)$second);   exit; }
    if (ctype_digit($second) && $third==='' && $method==='POST')    { $s->update($ctx,(int)$second); exit; }
    if (ctype_digit($second) && $third==='delete' && $method==='POST') { $s->destroy($ctx,(int)$second); exit; }
}
if ($first === 'suppliers.lookup.json' && class_exists(SuppliersController::class) && $method==='GET') {
    (new SuppliersController())->lookup($ctx); exit;
}
if ($first === 'suppliers.export.csv' && class_exists(SuppliersController::class) && $method==='GET') {
    (new SuppliersController())->export($ctx); exit;
}

/* =============================================================================
 * SEGMENT 20: Stakeholders (SR/DSR etc.)
 * =========================================================================== */
if ($first === 'stakeholders' && class_exists(StakeholdersController::class)) {
    $st = new StakeholdersController();
    $s1 = (string)($seg(1) ?? '');
    $s2 = (string)($seg(2) ?? '');

    if ($s1 === 'sr') {
        if ($s2 === 'create' && $method==='GET') { $st->srCreate($ctx); exit; }
        if ($s2 === '' && $method==='POST')      { $st->srStore($ctx);  exit; }
        if ($s2 === '' && $method==='GET')       { $st->srIndex($ctx);  exit; }
        $notFound('Unknown stakeholders/sr route.');
    }
    if ($s1 === 'performance' && $method==='GET') { $st->performance($ctx); exit; }
    if ($s1 === 'visit') {
        if     ($method==='GET')  { $st->visit($ctx);      exit; }
        elseif ($method==='POST') { $st->storeVisit($ctx); exit; }
    }
    if ($s1 === 'assign') {
        if     ($method==='GET')  { $st->assign($ctx);          exit; }
        elseif ($method==='POST') { $st->storeAssignment($ctx); exit; }
    }
    if ($s1 === 'route') {
        if     ($method==='GET')  { $st->route($ctx);      exit; }
        elseif ($method==='POST') { $st->storeRoute($ctx); exit; }
    }
    if ($s1 === 'mapping') {
        if     ($method==='GET')  { $st->mapping($ctx);      exit; }
        elseif ($method==='POST') { $st->storeMapping($ctx); exit; }
    }

    if ($s1 === '' && $method==='GET')           { $st->index($ctx);  exit; }
    if ($s1 === 'create' && $method==='GET')     { $st->create($ctx); exit; }
    if ($s1 === '' && $method==='POST')          { $st->store($ctx);  exit; }

    if ($s1 !== '' && ctype_digit($s1)) {
        $id = (int)$s1;
        if ($s2 === ''      && $method==='GET')                    { $st->show($ctx,$id);    exit; }
        if ($s2 === 'edit'  && $method==='GET')                    { $st->edit($ctx,$id);    exit; }
        if ($s2 === ''      && in_array($method,['POST','PUT','PATCH'], true)) { $st->update($ctx,$id); exit; }
        if ($s2 === 'delete'&& in_array($method,['POST','DELETE'], true) && method_exists($st,'destroy')) {
            $st->destroy($ctx,$id); exit;
        }
        $notFound('Unknown stakeholders/{id} route.');
    }

    $notFound('Unknown stakeholders route.');
}
/* Back-compat only; do not expose "dealer" anywhere */
if ($first === 'dealers') {
    header('Location: '.$module_base.'/stakeholders', true, 302); exit;
}

/* =============================================================================
 * SEGMENT 21: Free Products
 * =========================================================================== */
if (($first === 'free' || $first === 'free-products') && class_exists(FreeProductsController::class)) {
    $f = new FreeProductsController();
    if ($second === '' && $method==='GET')            { $f->index($ctx);         exit; }
    if ($second === 'create' && $method==='GET')      { $f->create($ctx);        exit; }
    if ($second === '' && $method==='POST')           { $f->store($ctx);         exit; }
    if ($second === 'receive' && $method==='GET')     { $f->receive($ctx);       exit; }
    if ($second === 'receive' && $method==='POST')    { $f->receiveStore($ctx);  exit; }
    if ($second === 'issue' && $method==='GET')       { $f->issue($ctx);         exit; }
    if ($second === 'issue' && $method==='POST')      { $f->issueStore($ctx);    exit; }
    if ($second === 'inventory' && $method==='GET')   { $f->index($ctx);         exit; }
    if ($second === 'movements' && $method==='GET')   { $f->movementsAll($ctx);  exit; }
    if (ctype_digit($second) && $third === 'movements' && $method==='GET') { $f->movements($ctx,(int)$second); exit; }
    if (ctype_digit($second) && $method==='GET')      { $f->show($ctx,(int)$second); exit; }
}

/* =============================================================================
 * SEGMENT 22: Settings
 * =========================================================================== */
if ($first === 'settings' && class_exists(DmsSettingsController::class)) {
    $sc = new DmsSettingsController();
    if ($method==='GET')  { $sc->index($ctx);  exit; }
    if ($method==='POST') { $sc->update($ctx); exit; }
    $notFound('Unknown /settings route');
}

/* =============================================================================
 * SEGMENT 23: Legacy redirect: /invoices → /sales (301)
 * =========================================================================== */
if ($first === 'invoices') {
    $redirTail = ($tail && $tail !== 'invoices') ? substr($tail, strlen('invoices')) : '';
    header('Location: '.$ctx['module_base'].'/sales'.$redirTail, true, 301); exit;
}

/* =============================================================================
 * SEGMENT 23-A: Demand forecasting and Auto Challan (All Automation)
 * =========================================================================== */


// --- Convenience: /challan/* under apps/dms
if ($first === 'challan' || $first === 'dispatch') {
    if (!class_exists(DmsChallanController::class)) {
        http_response_code(501);
        exit('DmsChallanController not available');
    }
    $cSeg1 = (string)($seg(1) ?? ''); // id or action
    $cSeg2 = (string)($seg(2) ?? '');
    $ctl   = new DmsChallanController();

    if ($cSeg1 === ''                    && $method === 'GET')  { $ctl->index();             exit; }
    if ($cSeg1 === 'create-from-invoice' && $method === 'POST') { $ctl->createFromInvoice(); exit; }

    if (ctype_digit($cSeg1)) {
        $id = (int)$cSeg1;
        if ($cSeg2 === ''                && $method === 'GET')  { $ctl->show($id);           exit; }
        if ($cSeg2 === 'edit-delivery'   && $method === 'GET')  { $ctl->editDelivery($id);   exit; }
        if ($cSeg2 === 'update-delivery' && $method === 'POST') { $ctl->updateDelivery($id); exit; }
    }

    http_response_code(404);
    exit('Unknown /challan route');
}


// --- Demand Forecasting: /products/forecast/*
if ($first === 'products') {
    $pSeg1 = (string)($seg(1) ?? '');
    if ($pSeg1 === 'forecast') {

        if (!class_exists(DmsForecastController::class)) {
            http_response_code(501);
            exit('DmsForecastController not available');
        }
        $pSeg2 = (string)($seg(2) ?? '');
        $ctl   = new DmsForecastController();

        // Pages
        if ($pSeg2 === ''        && $method === 'GET')  { $ctl->index($ctx);   exit; }  // UI page
        if ($pSeg2 === 'run'     && $method === 'POST') { $ctl->run($ctx);     exit; }  // compute (AJAX/form)
        if ($pSeg2 === 'export'  && $method === 'GET')  { $ctl->export($ctx);  exit; }  // optional CSV/XLSX

        http_response_code(404);
        exit('Unknown /products/forecast route');
    }
}




/* ============================================================================
 * SEGMENT: Products → Demand Forecasting
 * Routes:
 *   /products/demand                 GET index
 *   /products/demand/recompute       POST recompute
 * ========================================================================== */
if ($first === 'products' && $second === 'demand') {
    $dc = '\Modules\DMS\Controllers\DemandController';
    if (!class_exists($dc) && class_exists('\Modules\dms\Controllers\DemandController')) {
        $dc = '\Modules\dms\Controllers\DemandController';
    }
    if (!class_exists($dc)) { http_response_code(501); exit('DemandController not available'); }

    $dSeg1 = (string)($seg(2) ?? '');   // demand
    $dSeg2 = (string)($seg(3) ?? '');

    $ctl = new $dc();
    if ($dSeg2 === ''      && $method === 'GET')  { $ctl->index($ctx);      exit; }
    if ($dSeg2 === 'recompute' && $method === 'POST') { $ctl->recompute($ctx); exit; }

    http_response_code(404); exit('Unknown /products/demand route');
}

/* ============================================================================
 * SEGMENT: Purchases → Auto PO
 * Routes:
 *   /purchases/auto-po              GET index (preview)
 *   /purchases/auto-po/create       POST create purchase(s) [stub]
 * ========================================================================== */
if ($first === 'purchases' && $second === 'auto-po') {
    $ac = '\Modules\DMS\Controllers\AutoPoController';
    if (!class_exists($ac) && class_exists('\Modules\dms\Controllers\AutoPoController')) {
        $ac = '\Modules\dms\Controllers\AutoPoController';
    }
    if (!class_exists($ac)) { http_response_code(501); exit('AutoPoController not available'); }

    $aSeg1 = (string)($seg(2) ?? '');  // auto-po
    $aSeg2 = (string)($seg(3) ?? '');

    $ctl = new $ac();
    if ($aSeg2 === ''        && $method === 'GET')  { $ctl->index($ctx);  exit; }
    if ($aSeg2 === 'create'  && $method === 'POST') { $ctl->create($ctx); exit; }

    http_response_code(404); exit('Unknown /purchases/auto-po route');
}


/* =============================================================================
 * SEGMENT 24: Sales JSON shims (Create Invoice UI)
 * =========================================================================== */
if ($first === 'sales.customers.lookup.json' && class_exists(SalesController::class) && $method==='GET') {
    (new SalesController())->apiLookupCustomers($ctx); exit;
}
if ($first === 'sales.products.lookup.json' && class_exists(SalesController::class) && $method==='GET') {
    (new SalesController())->apiLookupProducts($ctx); exit;
}
if ($first === 'sales.users.lookup.json' && class_exists(SalesController::class) && $method==='GET') {
    (new SalesController())->apiLookupUsers($ctx); exit;
}
if ($first === 'sales.order.fetch.json' && class_exists(SalesController::class) && $method==='GET') {
    (new SalesController())->apiOrderFetch($ctx); exit; // ?id= or ?no=
}

/* =============================================================================
 * SEGMENT 25: API (JSON) unified lookup
 *   GET {base}/api/lookup                 → discovery
 *   GET {base}/api/lookup/{entity}?q=... → array
 * =========================================================================== */
if ($first === 'api' && class_exists(LookupController::class)) {
    $lookup = new LookupController();
    $entity = (string)($seg(2) ?? '');
    if ($second === 'lookup' && $entity === '') { $lookup->index($ctx);  exit; }
    if ($second === 'lookup' && $entity !== '') { $lookup->handle($ctx, $entity); exit; }
}
/* =============================================================================
 * SEGMENT 26: Final 404
 * =========================================================================== */
$notFound('Route not found: /'.$tail);