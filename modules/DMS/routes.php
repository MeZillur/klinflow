<?php
declare(strict_types=1);

/* ============================================================================
 * SEGMENT 0: Hard includes (Landing controller only; avoid autoload races)
 * ========================================================================== */
$ctlPath = __DIR__ . '/src/Controllers/LandingController.php';
if (is_file($ctlPath)) { require_once $ctlPath; }

/* ============================================================================
 * SEGMENT 1: Debug bootstrap (safe for tenant)
 * ========================================================================== */
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

/* ============================================================================
 * SEGMENT 2: Context boot (slug, module base, layout, org)
 * ========================================================================== */
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
if (!is_dir($moduleDir)) $moduleDir = realpath(dirname(__DIR__)) ?: __DIR__;

/* Resolve org: session first, then DB by slug (soft, non-fatal) */
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

/* Redirect plain /apps/dms to tenant path if slug known */
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

/* ============================================================================
 * SEGMENT 3: Render helpers
 * ========================================================================== */
$render = function (string $view, array $data = []) use ($ctx, $moduleDir): void {
    $viewPath = "$moduleDir/Views/$view.php";
    if (!is_file($viewPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "View not found: $view";
        exit;
    }
    $side = "$moduleDir/Views/shared/sidenav.php"; // single canonical sidenav
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

/* ============================================================================
 * SEGMENT 4: URL segments helper
 * ========================================================================== */
$parts = $tail ? array_values(array_filter(explode('/', $tail))) : [];
$seg   = fn(int $i) => $parts[$i] ?? null;
[$first, $second, $third] = array_map('strval', [$seg(0), $seg(1), $seg(2)]);

/* ============================================================================
 * SEGMENT 5: Controller imports
 * ========================================================================== */
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
  DemandForecastController,        // unified name here
  AutoPoController,                // unified name here
  SettingsController as DmsSettingsController,
  LandingController
};
use Modules\DMS\Controllers\Accounts\{
  CoaController, JournalsController, LedgerController,
  TrialBalanceController, ProfitLossController, BalanceSheetController
};
use App\Controllers\Tenant\UsersController;

/* ============================================================================
 * SEGMENT 6: Landing & Dashboard
 *   "" or "home" -> LandingController::home
 *   "dashboard"  -> DashboardController::index
 * ========================================================================== */
if ($tail === '' || $first === 'home') {
    if ($has(LandingController::class)) { (new LandingController())->home($ctx); exit; }
    $render('dashboard/index', ['title' => 'Dashboard']); exit;
}
if ($first === 'dashboard') {
    if ($has(DashboardController::class)) { (new DashboardController())->index($ctx); exit; }
    $render('dashboard/index', ['title' => 'Dashboard']); exit;
}

/* ============================================================================
 * SEGMENT 7: Accounts / GL hub
 * ========================================================================== */
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
        if ($third === 'update')                     { $coa->update($ctx,(int)$third); exit; } // if you later pass id, change pattern
        if ($third === 'delete')                     { $coa->destroy($ctx,(int)$third); exit; }
    }

    $notFound('/accounts route not found');
}

/* ============================================================================
 * SEGMENT 8: Reports
 * ========================================================================== */
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

/* ============================================================================
 * SEGMENT 9: Payments
 * ========================================================================== */
if ($first === 'payments') {
    $p = new PaymentsController();
    if ($second === '' && $method==='GET')             { $p->index($ctx);  exit; }
    if ($second === '' && $method==='POST')            { $p->store($ctx);  exit; }
    if ($second === 'create' && $method==='GET')       { $p->create($ctx); exit; }
    if (ctype_digit($second) && $third==='edit' && $method==='GET') { $p->edit($ctx,(int)$second); exit; }
    if (ctype_digit($second)) {
        $id=(int)$second;
        if     ($method==='GET')  { $p->show($ctx,$id);  exit; }
        elseif ($method==='POST') { $p->update($ctx,$id); exit; }
    }
}


/* ============================================================================
 * SALES (Invoices) + RETURNS + CHALLAN — FINAL ROUTE BLOCK
 * - Clean, minimal, and consistent ($ctx always passed to controller methods)
 * - “Dispatch / Issue Challan” flows go to /challan/prepare?invoice_id={id}
 * - Removes any old /challan/create-from-invoice usage (keeps a safe 302 shim)
 * ========================================================================== */

/* ------------------------------- RETURNS --------------------------------- */
if ($first === 'returns' || ($first === 'sales' && $second === 'returns')) {
    if (!class_exists(SalesReturnsController::class)) { http_response_code(501); exit('SalesReturnsController not available'); }

    // Normalize segments for /returns/* OR /sales/returns/*
    $r1 = ($first === 'returns') ? rawurldecode((string)($seg(1) ?? ''))
                                 : rawurldecode((string)($seg(2) ?? ''));
    $r2 = ($first === 'returns') ? rawurldecode((string)($seg(2) ?? ''))
                                 : rawurldecode((string)($seg(3) ?? ''));

    $ret = new SalesReturnsController();

    // Collection
    if ($r1 === ''       && $method === 'GET')  { $ret->index($ctx);  exit; }
    if ($r1 === ''       && $method === 'POST') { $ret->store($ctx);  exit; }
    if ($r1 === 'create' && $method === 'GET')  { $ret->create($ctx); exit; }

    // Item
    if ($r1 !== '' && ctype_digit($r1)) {
        $id = (int)$r1;
        if ($r2 === ''       && $method === 'GET')  { $ret->show($ctx, $id);   exit; }
        if ($r2 === 'edit'   && $method === 'GET')  { $ret->edit($ctx, $id);   exit; }
        if ($r2 === ''       && $method === 'POST') { $ret->update($ctx, $id); exit; }
        if ($r2 === 'print'  && $method === 'GET')  { $ret->print($ctx, $id);  exit; }
        if ($r2 === 'delete' && in_array($method, ['POST','DELETE'], true) && method_exists($ret,'destroy')) {
            $ret->destroy($ctx, $id); exit;
        }
    }

    http_response_code(404); exit('Unknown /returns route');
}


/* -------------------------------- SALES ---------------------------------- */
if ($first === 'sales') {
    if (!class_exists(SalesController::class)) {
        http_response_code(501);
        exit('SalesController not available');
    }
    $sales = new SalesController();

    // /sales/dispatch → open challan/dispatch board (delegates to DmsChallanController@index)
    if ($second === 'dispatch') {
        if ($method !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        $ChCtlFqn = '\Modules\DMS\Controllers\DmsChallanController';
        if (!class_exists($ChCtlFqn)) {
            http_response_code(501);
            exit('DmsChallanController not available');
        }
        (new $ChCtlFqn())->index($ctx);
        exit;
    }

    // Collection: /sales  (GET index, POST store)
    if ($second === '' || $second === null) {
        if     ($method === 'GET')  { $sales->index($ctx);  exit; }
        elseif ($method === 'POST') { $sales->store($ctx);  exit; }
        http_response_code(405);
        exit('Method Not Allowed');
    }

    // Create form: /sales/create
    if ($second === 'create' && $method === 'GET') {
        $sales->create($ctx);
        exit;
    }

    // Item routes: /sales/{id}/...
    if (ctype_digit($second)) {
        $id  = (int)$second;
        $act = rawurldecode((string)($seg(3) ?? ''));

        // /sales/{id}
        if ($act === '') {
            if     ($method === 'GET')  { $sales->show($ctx, $id);   exit; }
            elseif ($method === 'POST') { $sales->update($ctx, $id); exit; }
        }

        if ($act === 'edit'    && $method === 'GET')  { $sales->edit($ctx, $id);    exit; }
        if ($act === 'print'   && $method === 'GET')  { $sales->print($ctx, $id);   exit; }
        if ($act === 'pay'     && $method === 'POST') { $sales->pay($ctx, $id);     exit; }
        if ($act === 'deliver' && $method === 'POST') { $sales->deliver($ctx, $id); exit; }

        // ✅ Auto–challan entry points from invoice:
        //    /sales/{id}/dispatch
        //    /sales/{id}/create-challan
        //    /sales/{id}/challan
        if (in_array($act, ['dispatch','create-challan','challan'], true)) {
            $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
            $base = $slug ? '/t/' . rawurlencode($slug) . '/apps/dms' : '/apps/dms';
            header('Location: '.$base.'/challan/prepare?invoice_id='.$id, true, 302);
            exit;
        }

        http_response_code(404);
        exit('Unknown /sales item route');
    }

    http_response_code(404);
    exit('Unknown /sales route');
}

/* ============================================================================
 * Challan + Master Challan routes
 * ========================================================================== */
if ($first === 'challan') {
    $CtlFqn = '\Modules\DMS\Controllers\DmsChallanController';
    if (!class_exists($CtlFqn)) {
        http_response_code(501);
        exit('DmsChallanController missing');
    }

    /** @var \Modules\DMS\Controllers\DmsChallanController $ctl */
    $ctl = new $CtlFqn();

    // /challan/... segments
    $c1 = rawurldecode((string)($seg(1) ?? ''));
    $c2 = rawurldecode((string)($seg(2) ?? ''));

    /* ---- Compatibility shims ----------------------------------------- */

    // /challan/create-from-invoice?invoice_id=#
    if ($c1 === 'create-from-invoice') {
        $qid  = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
        $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
        $base = $slug ? '/t/' . rawurlencode($slug) . '/apps/dms' : '/apps/dms';
        $to   = $base . '/challan/prepare' . ($qid > 0 ? ('?invoice_id=' . $qid) : '');
        header('Location: ' . $to, true, 302);
        exit;
    }

    // /challan?invoice_id=# → /challan/prepare?invoice_id=#
    if ($c1 === '' && isset($_GET['invoice_id'])) {
        $qid  = (int)$_GET['invoice_id'];
        $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
        $base = $slug ? '/t/' . rawurlencode($slug) . '/apps/dms' : '/apps/dms';
        header('Location: ' . $base . '/challan/prepare?invoice_id=' . $qid, true, 302);
        exit;
    }

    /* ---- Debug -------------------------------------------------------- */
    if ($c1 === 'debug') {
        $ctl->debug($ctx);
        exit;
    }

    /* ---- Prepare (from invoice) -------------------------------------- */
    if ($c1 === 'prepare') {
        if ($method !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        $ctl->prepare($ctx);
        exit;
    }

    /* ---- Master challan canonical path redirect ---------------------- */
    if ($c1 === 'master') {
        $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
        $base = $slug
            ? '/t/' . rawurlencode($slug) . '/apps/dms'
            : '/apps/dms';

        $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
            ? ('?' . $_SERVER['QUERY_STRING'])
            : '';

        header('Location: ' . $base . '/challan/master-from-challan' . $qs, true, 302);
        exit;
    }

    /* ---- Master challan STORE (selected challans) -------------------- */
    // /challan/master-from-challan/store
    if ($c1 === 'master-from-challan' && $c2 === 'store') {
        if ($method === 'POST') {
            // actually save master challan
            $ctl->storeMasterChallan($ctx);
            exit;
        }

        // any GET on /store → redirect back to preview, no 405
        $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
        $base = $slug
            ? '/t/' . rawurlencode($slug) . '/apps/dms'
            : '/apps/dms';

        $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
            ? ('?' . $_SERVER['QUERY_STRING'])
            : '';

        header('Location: ' . $base . '/challan/master-from-challan' . $qs, true, 302);
        exit;
    }

    /* ---- Master challan PREVIEW (from selected challans) ------------- */
    // /challan/master-from-challan
    if ($c1 === 'master-from-challan' && $c2 === '') {
        if ($method === 'GET' || $method === 'POST') {
            $ctl->masterFromChallan($ctx);
            exit;
        }
        http_response_code(405);
        exit('Method Not Allowed');
    }

    /* ---- Export CSV --------------------------------------------------- */
    if ($c1 === 'export') {
        if ($method !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        $ctl->exportCsv($ctx);
        exit;
    }

    /* ---- Bulk mark as dispatched ------------------------------------ */
    if ($c1 === 'mark-dispatched') {
        if ($method === 'POST') {
            $ctl->markDispatched($ctx);
            exit;
        }
        http_response_code(405);
        exit('Method Not Allowed');
    }

    /* ---- Index + store ----------------------------------------------- */
    if ($c1 === '') {
        if ($method === 'GET') {
            $ctl->index($ctx);
            exit;
        }
        if ($method === 'POST') {
            $ctl->store($ctx);
            exit;
        }
        http_response_code(405);
        exit('Method Not Allowed');
    }

    /* ---- Item routes: /challan/{id}/... ------------------------------ */
    if ($c1 !== '' && ctype_digit($c1)) {
        $id = (int)$c1;

        if ($c2 === 'print' && $method === 'GET') {
            $ctl->print($ctx, $id);
            exit;
        }
      
      // /challan/{id}/edit
    	if ($c2 === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, $id);
        exit;
    	}
      
       // POST /challan/{id}/update-delivery
        if ($c2 === 'update-delivery' && $method === 'POST') {
            $ctl->updateDelivery($ctx, $id);
            exit;
        }

        if ($c2 === 'pdf' && $method === 'GET') {
            $ctl->pdf($ctx, $id);
            exit;
        }

        if ($c2 === '' && $method === 'GET') {
            $ctl->show($ctx, $id);
            exit;
        }

        http_response_code(405);
        exit('Method Not Allowed');
    }

    /* ---- Fallback ----------------------------------------------------- */
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    echo "Unknown /challan route";
    exit;
}

/* ============================================================================
 * Compatibility: /master-from-challan → /challan/master-from-challan
 * ========================================================================== */
if ($first === 'master-from-challan') {
    $slug = '';
    if (!empty($_SESSION['tenant_org']['slug'])) {
        $slug = (string)$_SESSION['tenant_org']['slug'];
    } elseif (!empty($_SERVER['REQUEST_URI'])
        && preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
        $slug = $m[1];
    }

    $moduleBase = $slug !== ''
        ? '/t/' . rawurlencode($slug) . '/apps/dms'
        : '/apps/dms';

    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? ('?' . $_SERVER['QUERY_STRING'])
        : '';

    header('Location: ' . $moduleBase . '/challan/master-from-challan' . $qs, true, 302);
    exit;
}

/* ============================================================================
 * SEGMENT 11: I18N (project-only) — uses app/Support/i18n.php + resources/lang/*
 * ========================================================================== */
if ($first === 'i18n') {
    $jsonOut = static function(array $data, int $code = 200) : void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    };

    $allowedLocales = ['en','bn'];

    // Ensure project helper is loaded
    $projectI18n = __DIR__ . '/../../../../../app/Support/i18n.php';
    if (!is_file($projectI18n)) {
        // fallback absolute path for this project (adjust if necessary)
        $projectI18n = '/home/klinflow/htdocs/www.klinflow.com/app/Support/i18n.php';
    }
    if (is_file($projectI18n)) require_once $projectI18n;

    // Helper: get locale (project helper -> session/cookie -> default 'en')
    $getLocale = function() use ($allowedLocales) : string {
        if (function_exists('current_locale')) {
            $l = (string)@current_locale();
            if (in_array($l, $allowedLocales, true)) return $l;
        }
        $l = (string)($_SESSION['dms_locale'] ?? $_COOKIE['dms_locale'] ?? '');
        if (in_array($l, $allowedLocales, true)) return $l;
        return 'en';
    };

    // Helper: load strings from project helper or resources/lang/<locale>.php
    $loadStrings = function(string $locale) use ($moduleDir) : array {
        if (function_exists('load_lang')) {
            $arr = (array) @load_lang($locale);
            return $arr;
        }
        // fallback to resources/lang in project root
        $cand = __DIR__ . '/../../../../../resources/lang/' . $locale . '.php';
        if (!is_file($cand)) {
            $cand = rtrim($moduleDir, '/') . '/resources/lang/' . $locale . '.php';
        }
        if (is_file($cand)) {
            try { return (array) include $cand; } catch (\Throwable $e) {}
        }
        return [];
    };

    // /i18n/set?locale=bn
    if ($second === 'set') {
        $loc = strtolower((string)($_GET['locale'] ?? ''));
        if (!in_array($loc, $allowedLocales, true)) {
            if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || isset($_GET['json'])) {
                $jsonOut(['ok'=>false,'error'=>'invalid_locale'], 400);
            }
            http_response_code(400); exit('Invalid locale');
        }
        // set session + cookie
        $_SESSION['dms_locale'] = $loc;
        setcookie('dms_locale', $loc, time()+86400*365, '/', '', isset($_SERVER['HTTPS']), true);
        if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || isset($_GET['json'])) {
            $jsonOut(['ok'=>true,'locale'=>$loc]);
        }
        http_response_code(204); exit;
    }

    // /i18n/strings
    if ($second === 'strings') {
        $locale = $getLocale();
        $strings = $loadStrings($locale);
        $jsonOut(['locale' => $locale, 'strings' => $strings]);
    }

    // /i18n/locale
    if ($second === 'locale') {
        $jsonOut(['locale' => $getLocale()]);
    }

    $notFound('Unknown /i18n route.');
}

/* ============================================================================
 * SEGMENT 12: Tenant Users (universal)
 * ========================================================================== */
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

/* ============================================================================
 * SEGMENT 13: Orders
 * ========================================================================== */
if ($first === 'orders' && class_exists(OrdersController::class)) {
    $o = new OrdersController();

    // Create
    if ($second === 'create' && $method === 'GET')  { $o->create($ctx); exit; }
    if ($second === 'create' && $method === 'POST') { $o->store($ctx);  exit; }

    // Collection
    if ($second === '' && $method === 'GET')  { $o->index($ctx); exit; }
    if ($second === '' && $method === 'POST') { $o->store($ctx); exit; }

    // Debug
    if ($second === 'smoke-insert' && $method === 'GET') { $o->smokeInsert($ctx); exit; }

    // Member
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

/* ============================================================================
 * SEGMENT 14: Purchases (incl. legacy lookups)
 * ========================================================================== */
if ($first === 'purchases' && class_exists(PurchasesController::class)) {
    $pc = new PurchasesController();

    if ($second === '' && $method==='GET')                { $pc->index($ctx);  exit; }
    if ($second === '' && $method==='POST')               { $pc->store($ctx);  exit; }
    if ($second === 'create' && $method==='GET')          { $pc->create($ctx); exit; }

    // Purchase-scoped lookups (legacy)
    if ($second === 'suppliers.lookup.json' && $method==='GET') { $pc->suppliersLookup($ctx); exit; }
    if ($second === 'products.lookup.json'  && $method==='GET') { $pc->productsLookup($ctx);  exit; }

    if (ctype_digit($second)) {
        $id = (int)$second;
        if ($third === ''       && $method==='GET')  { $pc->show($ctx,$id);    exit; }
        if ($third === 'edit'   && $method==='GET')  { $pc->edit($ctx,$id);    exit; }
        if ($third === ''       && $method==='POST') { $pc->update($ctx,$id);  exit; }
        if ($third === 'delete' && $method==='POST') { $pc->destroy($ctx,$id); exit; }
    }
}
if ($first === 'purchase' && class_exists(PurchasesController::class)) {
    (new PurchasesController())->create($ctx); exit; // legacy alias
}

/* ============================================================================
 * SEGMENT 14-A: Demand Forecasting routes
 *   /forecast                 GET  index
 *   /forecast/run             POST run
 *   /forecast/status          GET  status (progress / last run)
 *   /forecast/download        GET  optional (if controller has it)
 *   /products/forecast        alias to index (GET) or run (POST)
 * ========================================================================== */
if ($first === 'forecast' || ($first === 'products' && $second === 'forecast')) {
    // Resolve controller class (supports both Modules\DMS and Modules\dms)
    $fq1 = '\\Modules\\DMS\\Controllers\\DemandForecastController';
    $fq2 = '\\Modules\\dms\\Controllers\\DemandForecastController';
    $DemandCtrl = class_exists($fq1) ? $fq1 : (class_exists($fq2) ? $fq2 : null);

    if (!$DemandCtrl) { http_response_code(501); exit('DemandForecastController not available'); }

    // Path segment after /forecast (or after /products/forecast)
    $fSeg1 = ($first === 'forecast') ? (string)($seg(1) ?? '') : (string)($seg(2) ?? '');
    $fc = new $DemandCtrl();

    if ($fSeg1 === ''         && $method === 'GET')  { $fc->index($ctx);    exit; }
    if ($fSeg1 === 'run'      && $method === 'POST') { $fc->run($ctx);      exit; }
    if ($fSeg1 === 'status'   && $method === 'GET')  { $fc->status($ctx);   exit; }
    if ($fSeg1 === 'download' && $method === 'GET')  { $fc->download($ctx); exit; }
    http_response_code(404); exit('Unknown /forecast route');
}

/* ============================================================================
 * SEGMENT 14-B: Auto Purchase Orders (Auto-PO) — unified
 *   /auto-po
 *   /auto-po/run
 *   /auto-po/runs
 *   /auto-po/run/{id}
 *   /auto-po/run/{id}/commit|pdf|csv|email
 *   (alias) /products/auto-po/*
 * ========================================================================== */
if ($first === 'auto-po' || ($first === 'products' && $second === 'auto-po')) {
    $AutoPoCtrl = AutoPoController::class;
    if (!class_exists($AutoPoCtrl)) { http_response_code(501); exit('AutoPoController not available'); }

    $offset = ($first === 'auto-po') ? 0 : 1;
    $s1 = (string)($seg(1 + $offset) ?? '');
    $s2 = (string)($seg(2 + $offset) ?? '');
    $s3 = (string)($seg(3 + $offset) ?? '');

    $apo = new $AutoPoCtrl();

    if ($s1 === '') {
        if ($method === 'GET') { $apo->index($ctx); exit; }
        http_response_code(405); exit('Method not allowed');
    }

    if ($s1 === 'run' && $s2 === '') { $apo->run($ctx); exit; } // GET or POST handled inside controller
    if ($s1 === 'runs' && $s2 === '') {
        if ($method === 'GET') { $apo->runs($ctx); exit; }
        http_response_code(405); exit('Method not allowed');
    }

    if ($s1 === 'run' && ctype_digit($s2)) {
        $id = (int)$s2;

        if ($s3 === '') {
            if     ($method === 'GET')  { $apo->showRun($ctx, $id);   exit; }
            elseif ($method === 'POST') { $apo->commitRun($ctx, $id); exit; }
            http_response_code(405); exit('Method not allowed');
        }
        if ($s3 === 'commit') { if ($method === 'POST') { $apo->commitRun($ctx,$id); exit; } http_response_code(405); exit('Method not allowed'); }
        if ($s3 === 'pdf')    { if ($method === 'GET')  { $apo->pdfRun($ctx,$id);    exit; } http_response_code(405); exit('Method not allowed'); }
        if ($s3 === 'csv')    { if ($method === 'GET')  { $apo->csvRun($ctx,$id);    exit; } http_response_code(405); exit('Method not allowed'); }
        if ($s3 === 'email')  { if ($method === 'POST') { $apo->emailRun($ctx,$id);  exit; } http_response_code(405); exit('Method not allowed'); }

        http_response_code(404); exit('Unknown /auto-po run sub-route');
    }

    http_response_code(404); exit('Unknown /auto-po route');
}

/* ============================================================================
 * SEGMENT 15: Products (+ price tiers and API)
 * ========================================================================== */
if ($first === 'products' && class_exists(ProductsController::class)) {
    $p = new ProductsController();

    if ($second === 'create'        && $method === 'GET')  { $p->create($ctx);      exit; }
    if ($second === 'barcode.json'  && $method === 'GET')  { $p->barcodeJson($ctx); exit; }
    if ($second === 'lookup.json'   && $method === 'GET')  { $p->lookup($ctx);      exit; }

    if ($second === ''              && $method === 'GET')  { $p->index($ctx);       exit; }
    if ($second === ''              && $method === 'POST') { $p->store($ctx);       exit; }

    // NEW: bulk CSV submit (from /products/create → Bulk tab)
     if ($second === 'bulk'         && $method === 'POST') { $p->bulk($ctx);        exit; }

    // tiers
    if (ctype_digit($second)) {
        $id = (int)$second;

        if ($third === 'tiers'      && $method === 'GET'  && class_exists(PriceTiersController::class)) { (new PriceTiersController())->page($ctx,  $id); exit; }
        if ($third === 'tiers.json' && $method === 'GET'  && class_exists(PriceTiersController::class)) { (new PriceTiersController())->index($ctx, $id); exit; }
        if ($third === 'tiers'      && $method === 'POST' && class_exists(PriceTiersController::class)) { (new PriceTiersController())->store($ctx, $id); exit; }

        if     ($third === 'edit'   && $method === 'GET')  { $p->edit($ctx,   $id); exit; }
        elseif ($third === ''       && $method === 'GET')  { $p->show($ctx,   $id); exit; }
        elseif ($third === ''       && $method === 'POST') { $p->update($ctx, $id); exit; }
    }
}

// tier state mutations
if ($first === 'tiers' && class_exists(PriceTiersController::class) && ctype_digit($second)) {
    $tid = (int)$second;
    $t   = new PriceTiersController();
    if ($third === 'publish' && $method === 'POST') { $t->publish($ctx, $tid); exit; }
    if ($third === 'retire'  && $method === 'POST') { $t->retire($ctx,  $tid); exit; }
    if ($third === 'delete'  && $method === 'POST') { $t->destroy($ctx, $tid); exit; }
}

/* ============================================================================
 * SEGMENT 16: Categories
 * ========================================================================== */
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

/* ============================================================================
 * SEGMENT 17: Inventory
 * ========================================================================== */
if ($first === 'inventory' && class_exists(InventoryController::class)) {
    $inv = new InventoryController();
    if ($second === ''       && $method==='GET')  { $inv->index($ctx);       exit; }
    if ($second === 'aging'  && $method==='GET')  { $inv->aging($ctx);       exit; }
    if ($second === 'adjust' && $method==='GET')  { $inv->adjust($ctx);      exit; }
    if ($second === 'adjust' && $method==='POST') { $inv->storeAdjust($ctx); exit; }
    if ($second === 'damage' && $method==='GET')  { $inv->damage($ctx);      exit; }
    if ($second === 'damage' && $method==='POST') { $inv->storeDamage($ctx); exit; }
}

/* ============================================================================
 * SEGMENT 18: Bank Accounts + Expenses
 * ========================================================================== */
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

/* ============================================================================
 * SEGMENT 19: Customers / Suppliers (with convenience lookups/exports)
 * ========================================================================== */
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
// Top-level convenience endpoints (kept for back-compat)
if ($first === 'suppliers.lookup.json' && class_exists(SuppliersController::class) && $method==='GET') {
    (new SuppliersController())->lookup($ctx); exit;
}
if ($first === 'suppliers.export.csv' && class_exists(SuppliersController::class) && $method==='GET') {
    (new SuppliersController())->export($ctx); exit;
}

/* ============================================================================
 * SEGMENT 20: Stakeholders (SR/DSR etc.) + legacy redirect
 * ========================================================================== */
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
// Back-compat only; do not expose "dealer" anywhere
if ($first === 'dealers') {
    header('Location: '.$module_base.'/stakeholders', true, 302); exit;
}

/* ============================================================================
 * SEGMENT 21: Free Products
 * ========================================================================== */
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

/* ============================================================================
 * SEGMENT 22: Settings
 * ========================================================================== */
if ($first === 'settings' && class_exists(DmsSettingsController::class)) {
    $sc = new DmsSettingsController();
    if ($method==='GET')  { $sc->index($ctx);  exit; }
    if ($method==='POST') { $sc->update($ctx); exit; }
    $notFound('Unknown /settings route');
}

/* ============================================================================
 * SEGMENT 23: Legacy Redirects
 * ========================================================================== */
if ($first === 'invoices') {
    $redirTail = ($tail && $tail !== 'invoices') ? substr($tail, strlen('invoices')) : '';
    header('Location: '.$ctx['module_base'].'/sales'.$redirTail, true, 301); exit;
}

/* ============================================================================
 * SEGMENT 24: Sales JSON shims (Create Invoice UI)
 * ========================================================================== */
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

/* ============================================================================
 * SEGMENT 24b: Challan JSON shims (prepare/master screens)
 * ========================================================================== */
if ($first === 'challan.invoices.lookup.json' && class_exists(\Modules\DMS\Controllers\LookupController::class) && $method === 'GET') {
    (new \Modules\DMS\Controllers\LookupController())->handle($ctx, 'invoices'); exit;
}
if ($first === 'challan.orders.lookup.json' && class_exists(\Modules\DMS\Controllers\LookupController::class) && $method === 'GET') {
    (new \Modules\DMS\Controllers\LookupController())->handle($ctx, 'orders'); exit;
}
if ($first === 'challan.products.lookup.json' && class_exists(\Modules\DMS\Controllers\LookupController::class) && $method === 'GET') {
    (new \Modules\DMS\Controllers\LookupController())->handle($ctx, 'products'); exit;
}
if ($first === 'challan.customers.lookup.json' && class_exists(\Modules\DMS\Controllers\LookupController::class) && $method === 'GET') {
    (new \Modules\DMS\Controllers\LookupController())->handle($ctx, 'customers'); exit;
}

/* ============================================================================
 * SEGMENT 25: API (unified lookup for KF stack)
 *   /api/lookup               → discovery (GET)
 *   /api/lookup/{entity}      → entity lookup (GET)
 * ========================================================================== */
if ($first === 'api') {

    // Only lookup API is currently defined under /apps/dms/api/*
    if (!class_exists(LookupController::class)) {
        http_response_code(501);
        header('Content-Type: text/plain; charset=utf-8');
        exit('LookupController not available');
    }

    // We only support GET for lookup endpoints
    if ($method !== 'GET') {
        http_response_code(405);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Method Not Allowed');
    }

    $lookup = new LookupController();
    $entity = (string)($seg(2) ?? '');

    // /api/lookup              → discovery
    if ($second === 'lookup' && $entity === '') {
        $lookup->index($ctx);
        exit;
    }

    // /api/lookup/{entity}     → entity search
    if ($second === 'lookup' && $entity !== '') {
        $lookup->handle($ctx, $entity);
        exit;
    }

    // Anything else under /api/* → 404 (within DMS scope)
    $notFound('Unknown /api route.');
}

/* ============================================================================
 * SEGMENT 26: Final 404
 * ========================================================================== */
$notFound('Route not found: /'.$tail);