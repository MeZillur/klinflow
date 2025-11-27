<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * modules/POS/routes.php
 * Landing (no shell)
 * Transactional dashboard (with shared shell)
 * Sales (NO /sales/register anywhere)
 */

use Modules\POS\Controllers\{
//Core
    AccountsController,
    AccountingOverviewController,
    AccountingController,
    AccountingSettingsController,
    PaymentsController,
    LookupController,
    LandingController,
    DashboardController,
    ReportsController,

//Banking
    BankAccountsController,
    BranchBankAccountsController,
    CashRegistersController,
    DepositsController,
    BankingApiController,

//Accounting / GL
    JournalsController,
    LedgerController,
    GlController,
    

//POS / Commerce
    SalesController,
    ProductsController,
    CategoriesController,
    CustomersController,
    SuppliersController,
    InventoryController,
    StockTransfersController,
    BrandsController,
    BranchesController,

// Settings
    
    SettingsController as PosSettings,
};

$ctx    = $__POS__['ctx']    ?? [];
$render = $__POS__['render'] ?? null;
$method = strtoupper((string)($__POS__['method'] ?? 'GET'));
$tail   = trim((string)($__POS__['tail'] ?? ''), '/');

if (!is_callable($render)) { 
    http_response_code(500); 
    echo 'POS routes: render not ready.'; 
    exit; 
}

if (!is_callable($render)) { http_response_code(500); echo 'POS routes: render not ready.'; exit; }
if ($method === 'HEAD' || $method === 'OPTIONS') $method = 'GET';

// Simple shared 404 helper for all sections
$notFound = function (string $msg = 'Route not found') use ($ctx): void {
    $base = $ctx['module_base'] ?? '/apps/pos';

    http_response_code(404);

    echo '<div style="padding:24px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto">';
    echo '<h2 style="margin:0 0 8px">404 — POS</h2>';
    echo '<div style="margin-bottom:16px;color:#6b7280">'
        . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
        . '</div>';
    echo '<a href="'
        . htmlspecialchars(rtrim($base, '/') . '/landing', ENT_QUOTES, 'UTF-8')
        . '" style="display:inline-block;background:#228B22;color:#fff;'
        . 'padding:8px 12px;border-radius:8px;text-decoration:none;">'
        . 'Back to Landing</a>';
    echo '</div>';

    exit;
};


$parts  = $tail === '' ? [] : array_values(array_filter(explode('/', $tail), fn($x)=>$x!==''));
$seg    = static fn(int $i) => $parts[$i] ?? null;
$first  = (string)($seg(0) ?? '');
$second = (string)($seg(1) ?? '');
$third  = (string)($seg(2) ?? '');

/* ==============================================================
 * A) Landing (NO shared shell)
 *    /t/{slug}/apps/pos
 *    /t/{slug}/apps/pos/landing | /home
 * ============================================================== */
if ($tail === '' || in_array($first, ['landing','home'], true)) {
    (new LandingController())->home($ctx);
    exit;
}

/* ==============================================================
 * B) Transactional dashboard (WITH shared shell)
 *    /t/{slug}/apps/pos/posdashboard | /dashboard | /maindash
 * ============================================================== */
if (in_array($first, ['posdashboard','dashboard','maindash'], true)) {
    (new DashboardController())->app($ctx);
    exit;
}




/* ==============================================================
 * POS — Sales
 *   /sales
 *   /sales/register
 *   /sales/hold
 *   /sales/resume
 *   /sales/refunds
 *   /sales/{id}
 *   /sales/{id}/print/a4      (or ?fmt=a4)
 *   /sales/{id}/print/pos     (or ?fmt=pos)
 *   /sales/branch/switch
 *   /sales/api/products
 *   /sales/api/tiles
 *   /sales/api/customers.create
 * ============================================================== */

if ($first === 'sales' && class_exists(\Modules\POS\Controllers\SalesController::class)) {
    $ctl    = new \Modules\POS\Controllers\SalesController();
    $seg2   = (string)($second ?? '');   // "register", "hold", "resume", "api", "123", ...
    $seg3   = (string)($third  ?? '');   // "print", "products", "tiles", "switch", ...
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Convenience: current path (for sniffing trailing segment like "a4" or "pos")
    $uriPath   = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $pathParts = $uriPath ? explode('/', trim($uriPath, '/')) : [];
    $lastSeg   = strtolower($pathParts ? end($pathParts) : '');

    /* -------- PAGE ROUTES -------- */

    // GET /sales → list of sales
    if ($seg2 === '' && $method === 'GET') {
        $ctl->index($ctx);
        exit;
    }

    // GET /sales/register → Sales Register UI
    if ($seg2 === 'register' && $method === 'GET' && method_exists($ctl, 'register')) {
        $ctl->register($ctx);
        exit;
    }

    // POST /sales/register → optional non-JSON submit
    if ($seg2 === 'register' && $method === 'POST' && method_exists($ctl, 'storeFromForm')) {
        $ctl->storeFromForm($ctx);
        exit;
    }

    // GET /sales/hold → held sales list
    if ($seg2 === 'hold' && $method === 'GET') {
        $methodName = method_exists($ctl, 'holdPage') ? 'holdPage' : 'hold';
        if (method_exists($ctl, $methodName)) {
            $ctl->{$methodName}($ctx);
            exit;
        }
        $notFound('Hold page not available');
        return;
    }

    // GET /sales/resume → resume held sales
    if ($seg2 === 'resume' && $method === 'GET') {
        $methodName = method_exists($ctl, 'resumePage') ? 'resumePage' : 'resume';
        if (method_exists($ctl, $methodName)) {
            $ctl->{$methodName}($ctx);
            exit;
        }
        $notFound('Resume page not available');
        return;
    }

    // GET /sales/refunds → refunds page
    if ($seg2 === 'refunds' && $method === 'GET') {
        $methodName = method_exists($ctl, 'refundsPage') ? 'refundsPage' : 'refunds';
        if (method_exists($ctl, $methodName)) {
            $ctl->{$methodName}($ctx);
            exit;
        }
        $notFound('Refunds page not available');
        return;
    }

    /* -------- Branch switch from sales register -------- */
    // URL: /sales/branch/switch?id=BRANCH_ID
    if ($seg2 === 'branch' && $seg3 === 'switch') {
        if (!class_exists(\Modules\POS\Controllers\BranchesController::class)) {
            $notFound('Branches not available');
            return;
        }

        $bctl = new \Modules\POS\Controllers\BranchesController();

        $branchId = (int)(
            $_GET['id']
            ?? $_GET['branch_id']
            ?? $_POST['id']
            ?? $_POST['branch_id']
            ?? 0
        );

        $moduleBase      = (string)($ctx['module_base'] ?? '/apps/pos');
        $defaultRedirect = rtrim($moduleBase, '/') . '/sales/register';

        $bctl->switchBranch($ctx, $branchId, $defaultRedirect);
        exit;
    }

    // GET /sales/{id} → view single sale
    if ($seg2 !== '' && ctype_digit($seg2) && $seg3 === '' && $method === 'GET') {
        $ctl->showOne($ctx, (int)$seg2);
        exit;
    }

    // GET /sales/{id}/print[/a4|/pos]
    if ($seg2 !== '' && ctype_digit($seg2) && $seg3 === 'print' && $method === 'GET') {
        $fmt = strtolower((string)($_GET['fmt'] ?? ''));

        if ($fmt === '' && in_array($lastSeg, ['a4', 'pos'], true)) {
            $fmt = $lastSeg;
        }

        if ($fmt === 'pos' && method_exists($ctl, 'printPos')) {
            $ctl->printPos($ctx, (int)$seg2);
            exit;
        }

        if (method_exists($ctl, 'printA4')) {
            $ctl->printA4($ctx, (int)$seg2);
            exit;
        }

        $notFound('Print view not available');
        return;
    }

    /* -------- JSON APIs used by Sales Register -------- */

    // POST /sales (JSON from register)
    if ($seg2 === '' && $method === 'POST' && method_exists($ctl, 'store')) {
        $ctl->store($ctx);
        exit;
    }

    // GET /sales/api/products
    if ($seg2 === 'api' && $seg3 === 'products' && $method === 'GET' && method_exists($ctl, 'apiProducts')) {
        $ctl->apiProducts($ctx);
        exit;
    }

    // GET /sales/api/tiles
    if ($seg2 === 'api' && $seg3 === 'tiles' && $method === 'GET' && method_exists($ctl, 'apiTiles')) {
        $ctl->apiTiles($ctx);
        exit;
    }

    // POST /sales/api/customers.create
    if ($seg2 === 'api' && $seg3 === 'customers.create' && $method === 'POST' && method_exists($ctl, 'apiCustomersCreate')) {
        $ctl->apiCustomersCreate($ctx);
        exit;
    }

    /* -------- Fallback -------- */
    $notFound('Unknown /sales route');
}

/* ==============================================================
 * D) Products
 * ============================================================== */
if ($first === 'products' && class_exists(ProductsController::class)) {
    $ctl = new ProductsController();

    // /products
    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  exit; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  exit; }

    // /products/create
    if ($second === 'create' && $method === 'GET') { $ctl->create($ctx); exit; }

    // ---------- NEW: /products/export?format=csv|xlsx ----------
    if ($second === 'export' && $method === 'GET' && method_exists($ctl, 'export')) {
        $ctl->export($ctx);
        exit;
    }
    // -----------------------------------------------------------

    // /products/{id}
    if (ctype_digit($second) && $third === '' && $method === 'GET')  { $ctl->showOne($ctx,(int)$second); exit; }
    if (ctype_digit($second) && $third === '' && $method === 'POST') { $ctl->update($ctx,(int)$second); exit; }

    // /products/{id}/edit
    if (ctype_digit($second) && $third === 'edit' && $method === 'GET') { $ctl->edit($ctx,(int)$second); exit; }


    // Fallback for unknown /products/* route
    http_response_code(404);
    echo 'Route not found: /products/' .
         rawurlencode($second . ($third ? '/' . $third : ''));
    return;
}

/* ==============================================================
 * E) Inventory
 *    /inventory
 *    /inventory/adjust          (POST: perform adjustment)
 *    /inventory/adjustments     (GET : list recent adjustments)
 *    /inventory/transfers       (GET : stub page)
 *    /inventory/aging           (GET : stub page)
 *    /inventory/low-stock       (GET : low stock list)
 *    /inventory/movements       (GET : stock movements)
 * ============================================================== */

if ($first === 'inventory' && class_exists(\Modules\POS\Controllers\InventoryController::class)) {
    $ctl = new \Modules\POS\Controllers\InventoryController();

    // /inventory (dashboard)
    if ($second === '' && $method === 'GET' && method_exists($ctl, 'index')) {
        $ctl->index($ctx);
        exit;
    }

    // /inventory/adjust  (GET = form, POST = apply)
    if ($second === 'adjust' && $third === '' && $method === 'GET' && method_exists($ctl, 'adjustForm')) {
        $ctl->adjustForm($ctx);
        exit;
    }
    if ($second === 'adjust' && $third === '' && $method === 'POST' && method_exists($ctl, 'adjust')) {
        $ctl->adjust($ctx);
        exit;
    }

    // /inventory/adjustments
    if ($second === 'adjustments' && $method === 'GET' && method_exists($ctl, 'adjustments')) {
        $ctl->adjustments($ctx);
        exit;
    }

    // /inventory/transfers
    if ($second === 'transfers' && $method === 'GET' && method_exists($ctl, 'transfers')) {
        $ctl->transfers($ctx);
        exit;
    }

    // /inventory/aging
    if ($second === 'aging' && $method === 'GET' && method_exists($ctl, 'aging')) {
        $ctl->aging($ctx);
        exit;
    }

    // /inventory/low-stock
    if ($second === 'low-stock' && $method === 'GET' && method_exists($ctl, 'lowStock')) {
        $ctl->lowStock($ctx);
        exit;
    }

    // /inventory/movements
    if ($second === 'movements' && $method === 'GET' && method_exists($ctl, 'movements')) {
        $ctl->movements($ctx);
        exit;
    }

    http_response_code(404);
    echo 'Unknown /inventory route';
    return;
}


/* ==============================================================
 * F) Category Section
 * ============================================================== */
if ($first === 'categories') {
    if (!class_exists(CategoriesController::class)) $notFound('Categories not available');
    $ctl = new CategoriesController();

    // GET /categories
    if ($second === '' && $method === 'GET') { $ctl->index($ctx); exit; }

    // GET /categories/create
    if ($second === 'create' && $method === 'GET' && method_exists($ctl,'create')) { $ctl->create($ctx); exit; }

    // POST /categories (store)
    if ($second === '' && $method === 'POST' && method_exists($ctl,'store')) { $ctl->store($ctx); exit; }

    // GET /categories/{id}
    if (ctype_digit($second) && $method === 'GET' && method_exists($ctl,'show')) { $ctl->show($ctx, (int)$second); exit; }

    // GET /categories/{id}/edit
    if (ctype_digit($second) && $third === 'edit' && $method === 'GET' && method_exists($ctl,'edit')) { $ctl->edit($ctx, (int)$second); exit; }

    // POST /categories/{id} (update)  OR  POST /categories/{id}/delete
    if (ctype_digit($second) && $method === 'POST' && $third === '' && method_exists($ctl,'update')) { $ctl->update($ctx, (int)$second); exit; }
    if (ctype_digit($second) && $third === 'delete' && $method === 'POST' && method_exists($ctl,'destroy')) { $ctl->destroy($ctx, (int)$second); exit; }

    // GET /categories/api/search?q=...
    if ($second === 'api' && $third === 'search' && $method === 'GET' && method_exists($ctl,'apiSearch')) { $ctl->apiSearch($ctx); exit; }

    $notFound('Unknown /categories route');
}

/* ==============================================================
 * G) Customers Section
 * ============================================================== */
if ($first === 'customers') {
    if (!class_exists(CustomersController::class)) $notFound('Customers not available');
    $ctl = new CustomersController();

    // GET /customers
    if ($second === '' && $method === 'GET') { $ctl->index($ctx); exit; }

    // GET /customers/create
    if ($second === 'create' && $method === 'GET' && method_exists($ctl,'create')) { $ctl->create($ctx); exit; }

    // POST /customers
    if ($second === '' && $method === 'POST' && method_exists($ctl,'store')) { $ctl->store($ctx); exit; }

    // GET /customers/{id}
    if (ctype_digit($second) && $method === 'GET' && method_exists($ctl,'show')) { $ctl->show($ctx, (int)$second); exit; }

    // GET /customers/{id}/edit
    if (ctype_digit($second) && $third === 'edit' && $method === 'GET' && method_exists($ctl,'edit')) { $ctl->edit($ctx, (int)$second); exit; }

    // POST /customers/{id}
    if (ctype_digit($second) && $method === 'POST' && $third === '' && method_exists($ctl,'update')) { $ctl->update($ctx, (int)$second); exit; }

    // POST /customers/{id}/delete
    if (ctype_digit($second) && $third === 'delete' && $method === 'POST' && method_exists($ctl,'destroy')) { $ctl->destroy($ctx, (int)$second); exit; }

    // JSON helpers
    // GET /customers/api/search?q=
    if ($second === 'api' && $third === 'search' && $method === 'GET' && method_exists($ctl,'apiSearch')) { $ctl->apiSearch($ctx); exit; }
    // POST /customers/api/create  (body: {name, phone?})
    if ($second === 'api' && $third === 'create' && $method === 'POST' && method_exists($ctl,'apiCreate')) { $ctl->apiCreate($ctx); exit; }

    $notFound('Unknown /customers route');
}
/* ==============================================================
 * H) Suppliers Section
 * ============================================================== */


if ($first === 'suppliers') {
    if (!class_exists(SuppliersController::class)) $notFound('Suppliers not available');
    $ctl = new SuppliersController();

    // Pages
    if ($second === '' && $method === 'GET')                               { $ctl->index($ctx);  exit; }
    if ($second === 'create' && $method === 'GET' && method_exists($ctl,'create')) { $ctl->create($ctx); exit; }
    if ($second === '' && $method === 'POST' && method_exists($ctl,'store'))       { $ctl->store($ctx);  exit; }
    if (ctype_digit($second) && $third === 'edit' && $method === 'GET' && method_exists($ctl,'edit')) { $ctl->edit($ctx,(int)$second); exit; }
    if (ctype_digit($second) && $third === '' && $method === 'POST' && method_exists($ctl,'update'))  { $ctl->update($ctx,(int)$second); exit; }
    if (ctype_digit($second) && $third === 'delete' && $method === 'POST' && method_exists($ctl,'destroy')) { $ctl->destroy($ctx,(int)$second); exit; }

    // APIs
    if ($second === 'api' && $third === 'list'    && $method === 'GET' && method_exists($ctl,'apiList'))    { $ctl->apiList($ctx);    exit; }
    if ($second === 'api' && $third === 'metrics' && $method === 'GET' && method_exists($ctl,'apiMetrics')) { $ctl->apiMetrics($ctx); exit; }

    $notFound('Unknown /suppliers route');
}

/* ==============================================================
 * J) Accounting & Banking Section
 * ============================================================== */


/* --------------------------------------------------------------
 * Accounting Dashboard
 * -------------------------------------------------------------- */
if ($first === 'accounting') {
    if (!class_exists(\Modules\POS\Controllers\AccountingController::class))
        $notFound('Accounting not available');
    $ctl = new \Modules\POS\Controllers\AccountingController();

    if ($second === '' && $method === 'GET' && method_exists($ctl,'index')) { 
        $ctl->index($ctx); exit; 
    }

    $notFound('Unknown /accounting route');
}

/* -------------------------
 * /banking → Banking hub
 * ------------------------- */
if ($first === 'banking') {

    /* ---- 1) HQ Bank accounts (master) ---- */

    // GET /banking  → alias of /banking/accounts
    // GET /banking/accounts
    if (
        $method === 'GET'
        && (
            $second === ''
            || ($second === 'accounts' && $third === '')
        )
    ) {
        if (!class_exists(BankAccountsController::class)) {
            $notFound('Bank accounts not available');
        }
        (new BankAccountsController())->index($ctx);
        exit;
    }

    // GET /banking/accounts/create
    if ($second === 'accounts' && $third === 'create' && $method === 'GET') {
        if (!class_exists(BankAccountsController::class)) {
            $notFound('Bank accounts not available');
        }
        (new BankAccountsController())->create($ctx);
        exit;
    }

    // POST /banking/accounts
    if ($second === 'accounts' && $third === '' && $method === 'POST') {
        if (!class_exists(BankAccountsController::class)) {
            $notFound('Bank accounts not available');
        }
        (new BankAccountsController())->store($ctx);
        exit;
    }

    // GET /banking/accounts/{id}
    if ($second === 'accounts' && ctype_digit($third) && $fourth === '' && $method === 'GET') {
        if (!class_exists(BankAccountsController::class)) {
            $notFound('Bank accounts not available');
        }
        (new BankAccountsController())->show($ctx, (int)$third);
        exit;
    }

    // GET /banking/accounts/{id}/edit
    if ($second === 'accounts' && ctype_digit($third) && $fourth === 'edit' && $method === 'GET') {
        if (!class_exists(BankAccountsController::class)) {
            $notFound('Bank accounts not available');
        }
        (new BankAccountsController())->edit($ctx, (int)$third);
        exit;
    }

    // POST /banking/accounts/{id}
    if ($second === 'accounts' && ctype_digit($third) && $fourth === '' && $method === 'POST') {
        if (!class_exists(BankAccountsController::class)) {
            $notFound('Bank accounts not available');
        }
        (new BankAccountsController())->update($ctx, (int)$third);
        exit;
    }

    // POST /banking/accounts/{id}/make-master
    if ($second === 'accounts' && ctype_digit($third) && $fourth === 'make-master' && $method === 'POST') {
        if (!class_exists(BankAccountsController::class)) {
            $notFound('Bank accounts not available');
        }
        (new BankAccountsController())->makeMaster($ctx, (int)$third);
        exit;
    }

    /* ---- 2) Branch-level bank accounts (outlet scopes) ---- */

    // GET /banking/branches
    if ($second === 'branches' && $third === '' && $method === 'GET') {
        if (!class_exists(BranchBankAccountsController::class)) {
            $notFound('Branch bank accounts not available');
        }
        (new BranchBankAccountsController())->index($ctx);
        exit;
    }

    // GET /banking/branches/create
    if ($second === 'branches' && $third === 'create' && $method === 'GET') {
        if (!class_exists(BranchBankAccountsController::class)) {
            $notFound('Branch bank accounts not available');
        }
        (new BranchBankAccountsController())->create($ctx);
        exit;
    }

    // POST /banking/branches
    if ($second === 'branches' && $third === '' && $method === 'POST') {
        if (!class_exists(BranchBankAccountsController::class)) {
            $notFound('Branch bank accounts not available');
        }
        (new BranchBankAccountsController())->store($ctx);
        exit;
    }

    // GET /banking/branches/{id}/edit
    if ($second === 'branches' && ctype_digit($third) && $fourth === 'edit' && $method === 'GET') {
        if (!class_exists(BranchBankAccountsController::class)) {
            $notFound('Branch bank accounts not available');
        }
        (new BranchBankAccountsController())->edit($ctx, (int)$third);
        exit;
    }

    // POST /banking/branches/{id}
    if ($second === 'branches' && ctype_digit($third) && $fourth === '' && $method === 'POST') {
        if (!class_exists(BranchBankAccountsController::class)) {
            $notFound('Branch bank accounts not available');
        }
        (new BranchBankAccountsController())->update($ctx, (int)$third);
        exit;
    }

    /* ---- 3) Cash registers (per outlet tills) ---- */

    // GET /banking/cash-registers
    if ($second === 'cash-registers' && $third === '' && $method === 'GET') {
        if (!class_exists(CashRegistersController::class)) {
            $notFound('Cash registers not available');
        }
        (new CashRegistersController())->index($ctx);
        exit;
    }

    // GET /banking/cash-registers/create
    if ($second === 'cash-registers' && $third === 'create' && $method === 'GET') {
        if (!class_exists(CashRegistersController::class)) {
            $notFound('Cash registers not available');
        }
        (new CashRegistersController())->create($ctx);
        exit;
    }

    // POST /banking/cash-registers
    if ($second === 'cash-registers' && $third === '' && $method === 'POST') {
        if (!class_exists(CashRegistersController::class)) {
            $notFound('Cash registers not available');
        }
        (new CashRegistersController())->store($ctx);
        exit;
    }

    // GET /banking/cash-registers/{id}/edit
    if ($second === 'cash-registers' && ctype_digit($third) && $fourth === 'edit' && $method === 'GET') {
        if (!class_exists(CashRegistersController::class)) {
            $notFound('Cash registers not available');
        }
        (new CashRegistersController())->edit($ctx, (int)$third);
        exit;
    }

    // POST /banking/cash-registers/{id}
    if ($second === 'cash-registers' && ctype_digit($third) && $fourth === '' && $method === 'POST') {
        if (!class_exists(CashRegistersController::class)) {
            $notFound('Cash registers not available');
        }
        (new CashRegistersController())->update($ctx, (int)$third);
        exit;
    }

    // POST /banking/cash-registers/{id}/close
    if ($second === 'cash-registers' && ctype_digit($third) && $fourth === 'close' && $method === 'POST') {
        if (!class_exists(CashRegistersController::class)) {
            $notFound('Cash registers not available');
        }
        (new CashRegistersController())->close($ctx, (int)$third);
        exit;
    }

    /* ---- 4) Deposits (branch → bank) ---- */

    // GET /banking/deposits
    if ($second === 'deposits' && $third === '' && $method === 'GET') {
        if (!class_exists(DepositsController::class)) {
            $notFound('Deposits not available');
        }
        (new DepositsController())->index($ctx);
        exit;
    }

    // GET /banking/deposits/create
    if ($second === 'deposits' && $third === 'create' && $method === 'GET') {
        if (!class_exists(DepositsController::class)) {
            $notFound('Deposits not available');
        }
        (new DepositsController())->create($ctx);
        exit;
    }

    // POST /banking/deposits
    if ($second === 'deposits' && $third === '' && $method === 'POST') {
        if (!class_exists(DepositsController::class)) {
            $notFound('Deposits not available');
        }
        (new DepositsController())->store($ctx);
        exit;
    }

    // GET /banking/deposits/{id}
    if ($second === 'deposits' && ctype_digit($third) && $fourth === '' && $method === 'GET') {
        if (!class_exists(DepositsController::class)) {
            $notFound('Deposits not available');
        }
        (new DepositsController())->show($ctx, (int)$third);
        exit;
    }

    /* ---- 5) Banking API (AJAX helpers) ---- */

    if ($second === 'api') {
        if (!class_exists(BankingApiController::class)) {
            $notFound('Banking API not available');
        }
        $api = new BankingApiController();

        // GET /banking/api/accounts
        if ($third === 'accounts' && $method === 'GET' && method_exists($api,'accounts')) {
            $api->accounts($ctx); exit;
        }

        // GET /banking/api/branch-accounts
        if ($third === 'branch-accounts' && $method === 'GET' && method_exists($api,'branchAccounts')) {
            $api->branchAccounts($ctx); exit;
        }

        // GET /banking/api/statements
        if ($third === 'statements' && $method === 'GET' && method_exists($api,'statements')) {
            $api->statements($ctx); exit;
        }

        $notFound('Unknown /banking/api route');
    }

    $notFound('Unknown /banking route');
}

/* --------------------------------------------------------------
 * GL module
 *   /gl/journals
 *   /gl/journals/{id}
 *   /gl/ledger
 *   /gl/ledger/{account_code}
 *   /gl/trial-balance
 *   /gl/chart
 *   /gl/chart/create
 *   /gl/chart/{id}
 * -------------------------------------------------------------- */
if ($first === 'gl' && class_exists(\Modules\POS\Controllers\GlController::class)) {
    $ctl    = new \Modules\POS\Controllers\GlController();
    $seg2   = (string)($second ?? '');
    $seg3   = (string)($third  ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    /* -------- CHART OF ACCOUNTS (more specific routes first) -------- */

    // GET /gl/chart/create
    if ($seg2 === 'chart' && $seg3 === 'create' && $method === 'GET' && method_exists($ctl, 'chartCreate')) {
        $ctl->chartCreate($ctx);
        exit;
    }

    // POST /gl/chart/create
    if ($seg2 === 'chart' && $seg3 === 'create' && $method === 'POST' && method_exists($ctl, 'chartStore')) {
        $ctl->chartStore($ctx);
        exit;
    }

    // GET /gl/chart/{id} → edit form
    if ($seg2 === 'chart' && $seg3 !== '' && ctype_digit($seg3) && $method === 'GET' && method_exists($ctl, 'chartEdit')) {
        $ctl->chartEdit($ctx, (int)$seg3);
        exit;
    }

    // POST /gl/chart/{id} → update
    if ($seg2 === 'chart' && $seg3 !== '' && ctype_digit($seg3) && $method === 'POST' && method_exists($ctl, 'chartUpdate')) {
        $ctl->chartUpdate($ctx, (int)$seg3);
        exit;
    }

    // GET /gl/chart → listing
    if ($seg2 === 'chart' && $seg3 === '' && $method === 'GET' && method_exists($ctl, 'chart')) {
        $ctl->chart($ctx);
        exit;
    }

    /* -------- JOURNALS -------- */

    // GET /gl/journals (index)
    if (($seg2 === '' || $seg2 === 'journals') && $seg3 === '' && $method === 'GET') {
        $ctl->journals($ctx);
        exit;
    }

    // GET /gl/journals/{id}
    if ($seg2 === 'journals' && $seg3 !== '' && ctype_digit($seg3) && $method === 'GET') {
        $ctl->journalShow($ctx, (int)$seg3);
        exit;
    }

    /* -------- LEDGER -------- */

    // GET /gl/ledger[?account=CODE]
    if ($seg2 === 'ledger' && $seg3 === '' && $method === 'GET') {
        $ctl->ledger($ctx); // uses $_GET['account']
        exit;
    }

    // GET /gl/ledger/{account_code}
    if ($seg2 === 'ledger' && $seg3 !== '' && $method === 'GET') {
        $_GET['account'] = $seg3;       // normalise to query param
        $ctl->ledger($ctx);
        exit;
    }

    /* -------- TRIAL BALANCE -------- */

    // GET /gl/trial-balance
    if ($seg2 === 'trial-balance' && $method === 'GET') {
        $ctl->trialBalance($ctx);
        exit;
    }

    $notFound('Unknown /gl route');
}


/* --------------------------------------------------------------
 * Banking (Reconcile, overview-level)
 * -------------------------------------------------------------- */
if ($first === 'banking') {
    if (!class_exists(\Modules\POS\Controllers\BankingController::class))
        $notFound('Banking not available');
    $ctl = new \Modules\POS\Controllers\BankingController();

    if ($second === 'accounts'   && $method === 'GET'  && method_exists($ctl, 'accounts'))       { $ctl->accounts($ctx); exit; }
    if ($second === 'reconcile'  && $method === 'GET'  && method_exists($ctl, 'reconcile'))      { $ctl->reconcile($ctx); exit; }
    if ($second === 'reconcile'  && $method === 'POST' && method_exists($ctl, 'storeReconcile')) { $ctl->storeReconcile($ctx); exit; }

    $notFound('Unknown /banking route');
}

/* --------------------------------------------------------------
 * Payments
 *  - /payments
 *  - /accounts/payments (shim, no AccountsController needed)
 * -------------------------------------------------------------- */
if (
    $first === 'payments'
    || ($first === 'accounts' && $second === 'payments')
) {
    if (!class_exists(\Modules\POS\Controllers\PaymentsController::class)) {
        $notFound('Payments not available');
    }

    $ctl = new \Modules\POS\Controllers\PaymentsController();

    // Normalise segment:
    //   /payments           → $seg2 = $second
    //   /accounts/payments  → $seg2 = $third   (so /accounts/payments/create works too)
    $seg2 = ($first === 'payments') ? $second : $third;

    // List page
    if ($seg2 === '' && $method === 'GET' && method_exists($ctl, 'index')) {
        $ctl->index($ctx);
        exit;
    }

    // Create form
    if ($seg2 === 'create' && $method === 'GET' && method_exists($ctl, 'create')) {
        $ctl->create($ctx);
        exit;
    }

    // Store
    if ($seg2 === '' && $method === 'POST' && method_exists($ctl, 'store')) {
        $ctl->store($ctx);
        exit;
    }

    $notFound('Unknown payments route');
}



/* --------------------------------------------------------------
 * POS — Reports
 *   /reports
 * -------------------------------------------------------------- */
if ($first === 'reports') {

    if (!class_exists(\Modules\POS\Controllers\ReportsController::class)) {
        $notFound('Reports not available');
    }

    $ctl = new \Modules\POS\Controllers\ReportsController();

    // GET /reports → landing page (coming soon)
    if ($second === '' && $method === 'GET' && method_exists($ctl, 'index')) {
        $ctl->index($ctx);
        exit;
    }

    $notFound('Unknown /reports route');
}


/* ==============================================================
 * K) Purchase Section  (COMING SOON PLACEHOLDER)
 * ============================================================== */
if ($first === 'purchases') {
    // Make sure controller exists
    if (!class_exists(\Modules\POS\Controllers\PurchasesController::class)) {
        http_response_code(404);
        echo 'Purchases module not available.'; 
        exit;
    }

    $ctl = new \Modules\POS\Controllers\PurchasesController();

    // For now we only show the coming-soon page, both for
    // /purchases and /purchases/create so nothing breaks.
    if ($method === 'GET') {
        // /purchases or /purchases/create or anything under it → same page
        $ctl->index($ctx);
        exit;
    }

    // Any POST/other verbs for purchases are not implemented yet.
    http_response_code(501);
    echo 'Purchases API not implemented yet.';
    exit;
}

/* ==============================================================
 * L)  POS — Settings (Branding)
 *   /settings           GET  → branding form
 *   /settings           POST → save branding + logo
 *   /settings/brand.json GET → JSON for other modules / JS (optional)
 * ============================================================== */


if ($first === 'settings' && class_exists(PosSettings::class)) {
    $ctl  = new PosSettings();
    $seg2 = (string)($second ?? '');
    $seg3 = (string)($third  ?? '');

    // GET /settings → branding form
    if ($seg2 === '' && $method === 'GET') {
        $ctl->index($ctx);
        exit;
    }

    // POST /settings → save branding
    if ($seg2 === '' && $method === 'POST') {
        $ctl->save($ctx);
        exit;
    }

    // GET /settings/brand.json → small JSON of org + logo (optional)
    if ($seg2 === 'brand.json' && $method === 'GET' && method_exists($ctl, 'brandJson')) {
        $ctl->brandJson($ctx);
        exit;
    }

    // Fallback
    $notFound('Unknown /settings route');
    return;
}

/* ==============================================================
 * M) Expenses
 * ============================================================== */
if ($first === 'expenses') {
    if (!class_exists(\Modules\POS\Controllers\ExpensesController::class)) $notFound('Expenses not available');
    $ctl = new \Modules\POS\Controllers\ExpensesController();

    if ($second === '' && $method === 'GET') { $ctl->index($ctx); exit; }
    if ($second === 'create' && $method === 'GET') { $ctl->create($ctx); exit; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx); exit; }
    if (ctype_digit($second) && $third === 'edit' && $method === 'GET') { $ctl->edit($ctx, (int)$second); exit; }
    if (ctype_digit($second) && $method === 'POST' && $third === '') { $ctl->update($ctx, (int)$second); exit; }
    if (ctype_digit($second) && $third === 'delete' && $method === 'POST') { $ctl->destroy($ctx, (int)$second); exit; }

    $notFound('Unknown /expenses route');
}


/* ==============================================================
 * N) Branches
 *    /branches                     GET   → index
 *    /branches/create              GET   → create form
 *    /branches                     POST  → store
 *    /branches/{id}/edit           GET   → edit form
 *    /branches/{id}                POST  → update
 *    /branches/{id}/delete         POST  → destroy   (optional)
 *    /branches/switch              GET/POST → switch active branch
 * ============================================================== */


if ($first === 'branches') {
    if (!class_exists(\Modules\POS\Controllers\BranchesController::class)) {
        $notFound('Branches not available');
    }

    $ctl  = new \Modules\POS\Controllers\BranchesController();
    $seg2 = (string)($second ?? '');
    $seg3 = (string)($third  ?? '');

    // GET /branches
    if ($seg2 === '' && $seg3 === '' && $method === 'GET' && method_exists($ctl, 'index')) {
        $ctl->index($ctx); exit;
    }

    // GET /branches/create
    if ($seg2 === 'create' && $seg3 === '' && $method === 'GET' && method_exists($ctl,'create')) {
        $ctl->create($ctx); exit;
    }

    // POST /branches
    if ($seg2 === '' && $seg3 === '' && $method === 'POST' && method_exists($ctl,'store')) {
        $ctl->store($ctx); exit;
    }

    // GET /branches/{id}/edit
    if (ctype_digit($seg2) && $seg3 === 'edit' && $method === 'GET' && method_exists($ctl,'edit')) {
        $ctl->edit($ctx, (int)$seg2); exit;
    }

    // POST /branches/{id}
    if (ctype_digit($seg2) && $seg3 === '' && $method === 'POST' && method_exists($ctl,'update')) {
        $ctl->update($ctx, (int)$seg2); exit;
    }

    // POST /branches/{id}/delete (optional)
    if (ctype_digit($seg2) && $seg3 === 'delete' && $method === 'POST' && method_exists($ctl,'destroy')) {
        $ctl->destroy($ctx, (int)$seg2); exit;
    }

    /* ==============================================================
 * Quick branch switch from Sales register
 *   /sales/branch/switch?id={branch_id}
 * ============================================================== */

if ($first === 'sales' && $second === 'branch' && $third === 'switch') {
    // We don’t need the Sales controller for this, only Branches
    if (!class_exists(\Modules\POS\Controllers\BranchesController::class)) {
        $notFound('Branches not available');
    }

    $bctl = new \Modules\POS\Controllers\BranchesController();

    // Accept id from GET or POST, with several common names
    $branchId = (int)($_GET['id']
        ?? $_GET['branch_id']
        ?? $_POST['id']
        ?? $_POST['branch_id']
        ?? 0);

    // Where to go back after switching; default: sales register
    $moduleBase     = (string)($ctx['module_base'] ?? '/apps/pos');
    $defaultRedirect = rtrim($moduleBase, '/') . '/sales/register';

    $bctl->switchBranch($ctx, $branchId, $defaultRedirect);
    exit;
}

    $notFound('Unknown /branches route');
}

/* ==============================================================
 * API lookup (smart selects)
 *    /api/lookup
 *    /api/lookup/{entity}?q=...&limit=...
 * ============================================================== */
if ($first === 'api' && $second === 'lookup' && class_exists(LookupController::class)) {
    $ctl = new LookupController();

    // Discovery
    if (($third === '' || $third === null) && $method === 'GET') { $ctl->index($ctx); exit; }

    // Entity handler
    $entity = (string)($seg(2) ?? '');
    if ($entity !== '' && $method === 'GET') { $ctl->handle($ctx, $entity); exit; }
}

/* ==============================================================
 * N) Stock Transfers
 * ============================================================== */
if ($first === 'stock-transfers') {
    if (!class_exists(\Modules\POS\Controllers\StockTransfersController::class)) {
        $notFound('Stock transfers not available');
    }
    $ctl = new \Modules\POS\Controllers\StockTransfersController();

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);   exit; }
    if ($second === 'create' && $method === 'GET') { $ctl->create($ctx);  exit; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  exit; }

    $notFound('Unknown /stock-transfers route');
}


/* ==============================================================
 * Brands (index / CRUD + quick-create API)
 * ============================================================== */
if ($first === 'brands' && class_exists(\Modules\POS\Controllers\BrandsController::class)) {
    $ctl = new \Modules\POS\Controllers\BrandsController();

    // /brands/api/quick-create  (AJAX from product form – GET or POST)
    if (
        $second === 'api' &&
        $third === 'quick-create' &&
        method_exists($ctl, 'apiQuickCreate')
    ) {
        $ctl->apiQuickCreate($ctx);
        exit;
    }

    // /brands  (index + create)
    if ($second === '' && $method === 'GET' && method_exists($ctl, 'index')) {
        $ctl->index($ctx);
        exit;
    }
    if ($second === '' && $method === 'POST' && method_exists($ctl, 'store')) {
        $ctl->store($ctx);
        exit;
    }

    // /brands/create
    if ($second === 'create' && $method === 'GET' && method_exists($ctl, 'create')) {
        $ctl->create($ctx);
        exit;
    }

    // /brands/{id}
    if (ctype_digit($second) && $third === '' && $method === 'GET' && method_exists($ctl, 'showOne')) {
        $ctl->showOne($ctx, (int)$second);
        exit;
    }
    if (ctype_digit($second) && $third === '' && $method === 'POST' && method_exists($ctl, 'update')) {
        $ctl->update($ctx, (int)$second);
        exit;
    }

    // /brands/{id}/edit
    if (ctype_digit($second) && $third === 'edit' && $method === 'GET' && method_exists($ctl, 'edit')) {
        $ctl->edit($ctx, (int)$second);
        exit;
    }

    // Fallback for anything else under /brands
    http_response_code(404);
    echo 'Unknown /brands route';
    return;
}



/* ==============================================================
 * E) 404 with link back to landing
 * ============================================================== */
http_response_code(404);
$base = $ctx['module_base'] ?? '/apps/pos';
echo '<div style="padding:24px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto">'
   . '<h2 style="margin:0 0 8px">404 — POS</h2>'
   . '<div style="margin-bottom:16px;color:#6b7280">Route not found: '
   . htmlspecialchars("/$tail", ENT_QUOTES, 'UTF-8') . '</div>'
   . '<a href="'.htmlspecialchars(rtrim($base,'/').'/landing', ENT_QUOTES, 'UTF-8').'" '
   . 'style="display:inline-block;background:#228B22;color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none;">'
   . 'Back to Landing</a></div>';