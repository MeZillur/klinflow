<?php
declare(strict_types=1);

use Modules\BizFlow\Controllers\LandingController;
use Modules\BizFlow\Controllers\ItemsController;
use Modules\BizFlow\Controllers\CustomersController;
use Modules\BizFlow\Controllers\SuppliersController;
use Modules\BizFlow\Controllers\QuotesController;
use Modules\BizFlow\Controllers\OrdersController;
use Modules\BizFlow\Controllers\InvoicesController;
use Modules\BizFlow\Controllers\PurchasesController;
use Modules\BizFlow\Controllers\TendersController;
use Modules\BizFlow\Controllers\InventoryController;
use Modules\BizFlow\Controllers\ReportsController;
use Modules\BizFlow\Controllers\PaymentsController;
use Modules\BizFlow\Controllers\SettingsController;
use Modules\BizFlow\Controllers\ItemCategoriesController;
use Modules\BizFlow\Controllers\UomsController;
use Modules\BizFlow\Controllers\WarehousesController;
use Modules\BizFlow\Controllers\LcsController;
use Modules\BizFlow\Controllers\GrnController;
use Modules\BizFlow\Controllers\ExpensesController;
use Modules\BizFlow\Controllers\TaxCenterController;
use Modules\BizFlow\Controllers\EmployeesController;
use Modules\BizFlow\Controllers\PayrollController;
use Modules\BizFlow\Controllers\AccountingController;
use Modules\BizFlow\Controllers\GlJournalsController;
use Modules\BizFlow\Controllers\DashboardController;
use Modules\BizFlow\Controllers\LookupController;
use Modules\BizFlow\Controllers\AwardsController;
use Modules\BizFlow\Controllers\LtasController;

/**
 * Ensure BizFlow BaseController is loaded before any child controller.
 * (Composer autoload is not resolving this namespace here.)
 */

$bizBase = __DIR__ . '/src/Controllers/BaseController.php';
if (is_file($bizBase)) {
    require_once $bizBase;
} else {
    http_response_code(500);
    echo "BizFlow BaseController missing at: " . htmlspecialchars($bizBase, ENT_QUOTES, 'UTF-8');
    return;
}

/* ------------------------------------------------------------------ *
 * 0) Core context from front.php (NOTE: front exports $__BIZ__)
 * ------------------------------------------------------------------ */
$ctx    = $__BIZ__['ctx']    ?? [];
$render = $__BIZ__['render'] ?? null;
$method = strtoupper((string)($__BIZ__['method'] ?? 'GET'));
$tail   = trim((string)($__BIZ__['tail'] ?? ''), '/');

if (!is_callable($render)) {
    http_response_code(500);
    echo 'BizFlow routes: render not ready.';
    return;
}

/* Normalise HEAD / OPTIONS to behave like GET for UI routes */
if ($method === 'HEAD' || $method === 'OPTIONS') {
    $method = 'GET';
}

/* ------------------------------------------------------------------ *
 * 1) Helpers: segments, loader, 404
 * ------------------------------------------------------------------ */
$parts = $tail === ''
    ? []
    : array_values(array_filter(explode('/', $tail), static fn($x) => $x !== ''));

$seg = static fn(int $i): ?string => $parts[$i] ?? null;

$first  = (string)($seg(0) ?? '');
$second = (string)($seg(1) ?? '');
$third  = (string)($seg(2) ?? '');
$fourth = (string)($seg(3) ?? '');

$ctlDir = __DIR__ . '/src/Controllers';

/**
 * Lazy controller loader — only touches the file for the section we are in.
 */
$load = static function (string $class, string $file) use ($ctlDir): bool {
    if (class_exists($class, false)) {
        return true;
    }
    $path = $ctlDir . '/' . ltrim($file, '/');
    if (is_file($path)) {
        require_once $path;
    }
    return class_exists($class, false);
};

/** Simple 404 helper (keeps tenant slug via module_base in $ctx) */
$notFound = function (string $msg = 'BizFlow route not found') use ($ctx): void {
    http_response_code(404);
    $base = rtrim((string)($ctx['module_base'] ?? '/apps/bizflow'), '/');

    echo '<div style="padding:24px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto">';
    echo '<h2 style="margin:0 0 8px">404 — BizFlow</h2>';
    echo '<div style="margin-bottom:16px;color:#6b7280">'
        . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
        . '</div>';
    echo '<a href="'
        . htmlspecialchars($base . '/dashboard', ENT_QUOTES, 'UTF-8')
        . '" style="display:inline-block;background:#228B22;color:#fff;'
        . 'padding:8px 12px;border-radius:8px;text-decoration:none;">'
        . 'Back to BizFlow home</a>';
    echo '</div>';
    exit;
};

/* ------------------------------------------------------------------ *
 * 1a) Tenant logo asset route
 *      /t/{slug}/apps/bizflow/assets/logo
 * ------------------------------------------------------------------ */
if ($first === 'assets' && $second === 'logo') {
    if (\PHP_SESSION_ACTIVE !== \session_status()) {
        @\session_start();
    }

    $org = $_SESSION['tenant_org'] ?? null;
    if (!$org || !isset($org['id'])) {
        http_response_code(404);
        echo 'Organisation not found for logo.';
        return;
    }

    $orgId = (int)$org['id'];

    // File system path:
    // modules/bizflow/Assets/brand/logo/org_{id}/logo.{ext}
    $root = __DIR__;
    $dir  = $root . '/Assets/brand/logo/org_' . $orgId;

    $fsPath = null;
    $mime   = 'image/png';

    foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
        $candidate = $dir . '/logo.' . $ext;
        if (is_file($candidate)) {
            $fsPath = $candidate;
            if ($ext === 'png') {
                $mime = 'image/png';
            } elseif ($ext === 'jpg' || $ext === 'jpeg') {
                $mime = 'image/jpeg';
            } elseif ($ext === 'webp') {
                $mime = 'image/webp';
            } elseif ($ext === 'svg') {
                $mime = 'image/svg+xml';
            }
            break;
        }
    }

    if (!$fsPath) {
        http_response_code(404);
        echo 'Logo file not found for this tenant.';
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
    }

    readfile($fsPath);
    exit;
}

/* ------------------------------------------------------------------ *
 * 1) Root + landing
 *    - /apps/bizflow
 *    - /apps/bizflow/home
 * ------------------------------------------------------------------ */
if ($tail === '' || $first === '' || $first === null || $first === 'home') {
    if ($load(LandingController::class, 'LandingController.php')) {
        (new LandingController())->home($ctx);
        return;
    }

    // Soft fallback if controller missing
    $render('landing/index', [
        'title' => 'BizFlow — Apps',
    ]);
    return;
}

/* ============================================================================
 * 2) Dashboard as main system
 *    - /apps/bizflow/dashboard
 * ========================================================================== */
if ($first === 'dashboard') {

    if (!$load(DashboardController::class, 'DashboardController.php')) {
        $notFound('Dashboard controller not available');
        return;
    }

    $ctl = new DashboardController();

    // GET /apps/bizflow/dashboard → dashboard
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // Anything else under /dashboard → 404
    $notFound('Dashboard route not found');
    return;
}

/* ============================================================================
 * Accounting overview + reports
 *   /accounting
 *   /accounting/trial-balance
 *   /accounting/balance-sheet
 *   /accounting/bank-reco
 * ========================================================================== */
if ($first === 'accounting') {

    if (!$load(AccountingController::class, 'AccountingController.php')) {
        $notFound('Accounting module not available');
        return;
    }

    $ctl   = new AccountingController();
    $id    = (isset($second) && ctype_digit((string)$second)) ? (int)$second : null;
    $third = $third ?? '';

    /* ---------- GET /accounting (overview) ---------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- GET /accounting/trial-balance ---------- */
    if ($second === 'trial-balance' && $method === 'GET') {
        $ctl->trialBalance($ctx);
        return;
    }

    /* ---------- GET /accounting/balance-sheet ---------- */
    if ($second === 'balance-sheet' && $method === 'GET') {
        $ctl->balanceSheet($ctx);
        return;
    }

    /* ---------- GET /accounting/bank-reco ---------- */
    if ($second === 'bank-reco' && $method === 'GET') {
        $ctl->bankReco($ctx);
        return;
    }

    $notFound('Accounting route not found');
    return;
}

/* ============================================================================
 * GL journals
 *   /journals
 *   /journals/create
 *   /journals/{id}
 *   /journals/{id}/edit
 * ========================================================================== */
if ($first === 'journals') {

    if (!$load(GlJournalsController::class, 'GlJournalsController.php')) {
        $notFound('Journals controller not available');
        return;
    }

    $ctl   = new GlJournalsController();
    $id    = (isset($second) && ctype_digit((string)$second)) ? (int)$second : null;
    $third = $third ?? '';

    /* ---------- GET /journals (index) ---------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- GET /journals/create ---------- */
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    /* ---------- POST /journals (store new) ---------- */
    if (($second === '' || $second === null) && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    /* ---------- GET /journals/{id} (show) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'GET') {
        $ctl->show($ctx, $id);
        return;
    }

    /* ---------- GET /journals/{id}/edit ---------- */
    if ($id !== null && $third === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, $id);
        return;
    }

    /* ---------- POST /journals/{id} (update) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'POST') {
        $ctl->update($ctx, $id);
        return;
    }

    $notFound('Journals route not found');
    return;
}


/* ------------------------------------------------------------------ *
 * Expenses
 * ------------------------------------------------------------------ */


if ($first === 'expenses') {

    if (!$load(Modules\BizFlow\Controllers\ExpensesController::class, 'ExpensesController.php')) {
        $notFound('Expenses module not available');
        return;
    }

    $ctl   = new Modules\BizFlow\Controllers\ExpensesController();
    $id    = (isset($second) && ctype_digit((string)$second)) ? (int)$second : null;
    $third = $third ?? '';
    $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');

    /* ---------- GET /expenses (index) ---------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- GET /expenses/create ---------- */
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    /* ---------- POST /expenses (store new) ---------- */
    if (($second === '' || $second === null) && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    /* ---------- GET /expenses/{id} (show) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'GET') {
        $ctl->show($ctx, $id);
        return;
    }

    /* ---------- GET /expenses/{id}/edit ---------- */
    if ($id !== null && $third === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, $id);
        return;
    }

    /* ---------- POST /expenses/{id} (update) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'POST') {
        $ctl->update($ctx, $id);
        return;
    }

    // Anything else → 404
    $notFound('Expenses route not found');
    return;
}


/* ------------------------------------------------------------------ *
 * 12) Reports
 * ------------------------------------------------------------------ */
if ($first === 'reports') {
    if (!$load(ReportsController::class, 'ReportsController.php')) {
        $render('reports/index', ['title' => 'Reports']);
        return;
    }

    $ctl = new ReportsController();

    if ($second === '' && $method === 'GET') {
        $ctl->index($ctx); return;
    }

    $notFound('Reports route not found');
}

/* ------------------------------------------------------------------ *
 * 13) Payments
 * ------------------------------------------------------------------ */
if ($first === 'payments') {
    if (!$load(PaymentsController::class, 'PaymentsController.php')) {
        $render('payments/index', ['title' => 'Payments']);
        return;
    }

    $ctl = new PaymentsController();
    $id  = ctype_digit($second) ? (int)$second : null;

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); return;
    }

    if ($id !== null && $third === '' && $method === 'GET') {
        $ctl->show($ctx, $id); return;
    }

    $notFound('Payments route not found');
}


/* ------------------------------------------------------------------ *
 * X) Banking (bank & cash accounts)
 * ------------------------------------------------------------------ */
if ($first === 'banking') {
    if (!$load(BankingController::class, 'BankingController.php')) {
        // Soft fallback if controller file is missing
        $render('banking/index', [
            'title'        => 'Banking',
            'org'          => $ctx['org'] ?? [],
            'module_base'  => $ctx['module_base'] ?? '/apps/bizflow',
            'accounts'     => [],
            'metrics'      => [
                'total'         => 0,
                'bank'          => 0,
                'cash'          => 0,
                'mobile'        => 0,
                'storage_ready' => false,
            ],
            'search'       => '',
            'filter_type'  => '',
            'filter_curr'  => '',
            'storage_ready'=> false,
        ]);
        return;
    }

    $ctl = new BankingController();
    $id  = ctype_digit($second) ? (int)$second : null;

    // /banking  (index + store)
    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    // /banking/create
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); return;
    }

    // /banking/{id}, /banking/{id}/edit
    if ($id !== null) {
        if ($third === ''     && $method === 'GET')  { $ctl->show($ctx, $id);   return; }
        if ($third === 'edit' && $method === 'GET')  { $ctl->edit($ctx, $id);   return; }
        if ($third === ''     && $method === 'POST') { $ctl->update($ctx, $id); return; }
    }

    $notFound('Banking route not found');
}


/* ------------------------------------------------------------------ *
 * Tax Section
 * ------------------------------------------------------------------ */

if ($first === 'tax') {

    if (!$load(Modules\BizFlow\Controllers\TaxCenterController::class, 'TaxCenterController.php')) {
        $notFound('Tax center not available');
        return;
    }

    $ctl    = new Modules\BizFlow\Controllers\TaxCenterController();
    $second = $second ?? '';
    $third  = $third  ?? '';
    $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');

    /* ---------- GET /tax (overview) ---------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // For now only overview; later we can add:
    //   /tax/vat-input
    //   /tax/vat-output
    //   /tax/wht
    //   /tax/returns
    $notFound('Tax route not found');
    return;
}




/* ============================================================================
 * Items
 *   /items
 *   /items/create
 *   /items/{id}/edit
 *   /items/{id}/update
 *   /items/bulk-template.csv
 *   /items/bulk-preview
 *   /items/bulk-commit
 * ========================================================================== */
if ($first === 'items') {
    if (!$load(ItemsController::class, 'ItemsController.php')) {
        $notFound('Items controller not available');
        return;
    }

    $ctl = new ItemsController();

    // GET /apps/bizflow/items  → list
    if ($method === 'GET' && ($second === '' || $second === null)) {
        $ctl->index($ctx);
        return;
    }

    // GET /apps/bizflow/items/create  → create form
    if ($method === 'GET' && $second === 'create') {
        $ctl->create($ctx);
        return;
    }

    // POST /apps/bizflow/items  → store single item
    if ($method === 'POST' && ($second === '' || $second === null)) {
        $ctl->store($ctx);
        return;
    }

    // GET /apps/bizflow/items/bulk-template.csv  → download CSV template
    if ($method === 'GET' && $second === 'bulk-template.csv') {
        $ctl->bulkTemplate($ctx);
        return;
    }

    // POST /apps/bizflow/items/bulk-preview  → upload CSV for preview
    if ($method === 'POST' && $second === 'bulk-preview') {
        $ctl->bulkPreview($ctx);
        return;
    }

    // POST /apps/bizflow/items/bulk-commit  → commit OK+warning rows
    if ($method === 'POST' && $second === 'bulk-commit') {
        $ctl->bulkCommit($ctx);
        return;
    }

    // GET /apps/bizflow/items/{id}/edit  → edit form
    if ($method === 'GET'
        && ctype_digit((string) $second)
        && $third === 'edit') {
        $ctl->edit($ctx, (int) $second);
        return;
    }

    // POST /apps/bizflow/items/{id}/update  → update existing item
    if ($method === 'POST'
        && ctype_digit((string) $second)
        && $third === 'update') {
        $ctl->update($ctx, (int) $second);
        return;
    }

    // (optional) also accept POST /items/{id} without /update
    if ($method === 'POST'
        && ctype_digit((string) $second)
        && ($third === '' || $third === null)) {
        $ctl->update($ctx, (int) $second);
        return;
    }

    $notFound('Items route not found');
    return;
}

/* ------------------------------------------------------------------ *
 * 4) Item categories (top-level)
 *    /categories
 *    /categories/create
 *    /categories/{id}/edit
 * ------------------------------------------------------------------ */
if ($first === 'categories') {
    if (!$load(ItemCategoriesController::class, 'ItemCategoriesController.php')) {
        // Fallback if controller file missing
        $render('categories/index', ['title' => 'Item categories']);
        return;
    }

    $ctl = new ItemCategoriesController();
    $id  = ctype_digit($second) ? (int)$second : null;

    // /categories
    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    // /categories/create
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    // /categories/{id}/edit
    if ($id !== null) {
        if ($third === 'edit' && $method === 'GET')  { $ctl->edit($ctx, $id);   return; }
        if ($third === ''     && $method === 'POST') { $ctl->update($ctx, $id); return; }
    }
  
  
  	// NEW: downloadable CSV template
    if (in_array($second, ['bulk-template.csv', 'bulk-template'], true) && $method === 'GET') {
        $ctl->bulkTemplate($ctx);
        return;
    }


    $notFound('Categories route not found');
}

/* ------------------------------------------------------------------ *
 * Units of Measure
 *   /uoms
 *   /uoms/create
 *   /uoms/{id}/edit
 * ------------------------------------------------------------------ */
if ($first === 'uoms') {

    // Load controller class file (PSR-4 friendly, same pattern as Items)
    if (!$load(UomsController::class, 'UomsController.php')) {
        $notFound('Units of measure not available');
        return;
    }

    $ctl = new UomsController();

    // GET /uoms
    if ($second === '' && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // GET /uoms/create
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    // POST /uoms  (create)
    if ($second === '' && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    // GET /uoms/{id}/edit
    if (ctype_digit($second) && $third === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, (int)$second);
        return;
    }

    // POST /uoms/{id}  (update)
    if (ctype_digit($second) && $third === '' && $method === 'POST') {
        $ctl->update($ctx, (int)$second);
        return;
    }

    $notFound('UoM route not found');
    return;
}


/* ------------------------------------------------------------------ *
 * Warehouses
 *   /warehouse
 *   /warehouse/create
 *   /warehouse/{id}
 *   /warehouse/{id}/edit
 * ------------------------------------------------------------------ */
if ($first === 'warehouse') {

    // Load controller (same helper you use for Items, UoMs, etc.)
    if (!$load(WarehousesController::class, 'WarehousesController.php')) {
        $notFound('Warehouse module not available');
        return;
    }

    $ctl   = new WarehousesController();
    $id    = (isset($second) && ctype_digit((string)$second)) ? (int)$second : null;
    $third = $third ?? '';

    /* ---------- GET /warehouse (index) ---------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- GET /warehouse/create ---------- */
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    /* ---------- POST /warehouse (store new) ---------- */
    if (($second === '' || $second === null) && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    /* ---------- GET /warehouse/{id} (show) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'GET') {
        $ctl->show($ctx, $id);
        return;
    }

    /* ---------- GET /warehouse/{id}/edit ---------- */
    if ($id !== null && $third === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, $id);
        return;
    }

    /* ---------- POST /warehouse/{id} (update) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'POST') {
        $ctl->update($ctx, $id);
        return;
    }

    // Anything else under /warehouse → 404
    $notFound('Warehouse route not found');
    return;
}


/* ------------------------------------------------------------------ *
 * 6) Quotes
 *    Canonical UI list:
 *      /t/{slug}/apps/bizflow/quotes
 * ------------------------------------------------------------------ */
if ($first === 'quotes') {
    if (!$load(QuotesController::class, 'QuotesController.php')) {
        http_response_code(500);
        echo 'QuotesController not available.';
        return;
    }

    $ctl    = new QuotesController();
    $id     = ctype_digit($second) ? (int)$second : null;
    // $method, $tail already come from front.php

    /* -------------------------------------------------------------
     * Child routes (must come first)
     * ----------------------------------------------------------- */

    // 6.1 — PDF: GET /quotes/{id}/pdf
    if ($id !== null && $third === 'pdf'
        && $method === 'GET'
        && method_exists($ctl, 'downloadPdf')) {
        $ctl->downloadPdf($ctx, $id);
        return;
    }

    // 6.2 — Create award from quote:
    //       GET or POST /quotes/{id}/award
    if ($id !== null && $third === 'award' && in_array($method, ['GET', 'POST'], true)) {
        if (!$load(\Modules\BizFlow\Controllers\AwardsController::class, 'AwardsController.php')) {
            http_response_code(500);
            echo 'AwardsController not available.';
            return;
        }

        $ac = new \Modules\BizFlow\Controllers\AwardsController();
        $ac->createFromQuote($ctx, $id);
        return;
    }

    // 6.3 — Inline status update: POST /quotes/{id}/status
    if ($id !== null && $third === 'status'
        && $method === 'POST'
        && method_exists($ctl, 'updateStatus')) {
        $ctl->updateStatus($ctx, $id);
        return;
    }

    // 6.4 — Fallback regex form (e.g. tail == "quotes/6/status")
    if ($method === 'POST'
        && preg_match('#^quotes/(\d+)/status$#', $tail, $m)
        && method_exists($ctl, 'updateStatus')) {
        $ctl->updateStatus($ctx, (int)$m[1]);
        return;
    }

    /* -------------------------------------------------------------
     * Standard REST-ish routes
     * ----------------------------------------------------------- */

    // GET /quotes → index list
    if ($second === '' && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // GET /quotes/create → new quote form
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    // POST /quotes → store
    if ($second === '' && $method === 'POST'
        && method_exists($ctl, 'store')) {
        $ctl->store($ctx);
        return;
    }

    // GET /quotes/{id}
    if ($id !== null && $third === '' && $method === 'GET'
        && method_exists($ctl, 'show')) {
        $ctl->show($ctx, $id);
        return;
    }

    // GET /quotes/{id}/edit
    if ($id !== null && $third === 'edit' && $method === 'GET'
        && method_exists($ctl, 'edit')) {
        $ctl->edit($ctx, $id);
        return;
    }

    // GET /quotes/{id}/print
    if ($id !== null && $third === 'print' && $method === 'GET'
        && method_exists($ctl, 'printView')) {
        $ctl->printView($ctx, $id);
        return;
    }

    // POST /quotes/{id}/email
    if ($id !== null && $third === 'email' && $method === 'POST'
        && method_exists($ctl, 'sendEmail')) {
        $ctl->sendEmail($ctx, $id);
        return;
    }

    // Fallback
    $notFound('Quotes route not found');
}

/* ------------------------------------------------------------------ *
 * 7) Awards
 *    /t/{slug}/apps/bizflow/awards
 *    /t/{slug}/apps/bizflow/awards/{id}
 *    /t/{slug}/apps/bizflow/awards/{id}/purchase
 * ------------------------------------------------------------------ */
if ($first === 'awards') {
    if (!$load(\Modules\BizFlow\Controllers\AwardsController::class, 'AwardsController.php')) {
        http_response_code(500);
        echo 'AwardsController not available.';
        return;
    }

    $ctl = new \Modules\BizFlow\Controllers\AwardsController();
    $id  = ctype_digit($second) ? (int)$second : null;

    // GET /awards → index
    if ($second === '' && $third === '' && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // GET /awards/{id} → details
    if ($id && $third === '' && $method === 'GET') {
        $ctl->show($ctx, $id);
        return;
    }

    // GET or POST /awards/{id}/purchase → create PO from award
    if ($id && $third === 'purchase' && in_array($method, ['GET', 'POST'], true)) {
        $ctl->createPurchaseFromAward($ctx, $id);
        return;
    }

    //  ADD THIS: GET or POST /awards/{id}/invoice → create invoice from award
    if ($id && $third === 'invoice' && in_array($method, ['GET', 'POST'], true)) {
        $ctl->createInvoiceFromAward($ctx, $id);
        return;
    }

    $notFound('Awards route not found');
}


/* ============================================================================
 * LTAs (Long Term Agreements)
 *   /ltas
 *   /ltas/create
 *   /ltas/{id}
 *   /ltas/{id}/edit
 *   /ltas/{id}/update
 *   /ltas/{id}/status
 * ========================================================================== */
if ($first === 'ltas') {

    // Ensure controller file is loaded (same pattern as Awards)
    if (!$load(\Modules\BizFlow\Controllers\LtasController::class, 'LtasController.php')) {
        http_response_code(500);
        echo 'LtasController not available.';
        return;
    }

    $ctl = new \Modules\BizFlow\Controllers\LtasController();
    $id  = ctype_digit($second) ? (int)$second : null;

    // GET /ltas → index
    if ($second === '' && $third === '' && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // GET /ltas/create → new LTA form
    if ($second === 'create' && $third === '' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    // POST /ltas → store new LTA
    if ($second === '' && $third === '' && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    // Routes with {id}
    if ($id) {
        // GET /ltas/{id} → details
        if ($third === '' && $method === 'GET') {
            $ctl->show($ctx, $id);
            return;
        }

        // GET /ltas/{id}/edit → edit form
        if ($third === 'edit' && $method === 'GET') {
            $ctl->edit($ctx, $id);
            return;
        }

        // POST /ltas/{id}/update → update header + items
        if ($third === 'update' && $method === 'POST') {
            $ctl->update($ctx, $id);
            return;
        }

        // POST /ltas/{id}/status → quick status change
        if ($third === 'status' && $method === 'POST') {
            $ctl->updateStatus($ctx, $id);
            return;
        }
    }

    // If nothing matched
    $notFound('LTA route not found');
    return;
}


/* ------------------------------------------------------------------ *
 * 7) Orders (Sales Orders)
 * ------------------------------------------------------------------ */
if ($first === 'orders') {
    if (!$load(OrdersController::class, 'OrdersController.php')) {
        $render('orders/index', ['title' => 'Orders']);
        return;
    }

    $ctl = new OrdersController();
    $id  = ctype_digit($second) ? (int)$second : null;

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); return;
    }

    if ($id !== null) {
        if ($third === ''     && $method === 'GET')  { $ctl->show($ctx, $id);   return; }
        if ($third === 'edit' && $method === 'GET')  { $ctl->edit($ctx, $id);   return; }
        if ($third === ''     && $method === 'POST') { $ctl->update($ctx, $id); return; }
    }

    $notFound('Orders route not found');
}

/* ------------------------------------------------------------------ *
 * 8) Invoices
 * ------------------------------------------------------------------ */
if ($first === 'invoices') {
    if (!$load(InvoicesController::class, 'InvoicesController.php')) {
        $render('invoices/index', ['title' => 'Invoices']);
        return;
    }

    $ctl = new InvoicesController();
    $id  = ctype_digit($second) ? (int)$second : null;

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); return;
    }

    if ($id !== null) {
        if ($third === ''       && $method === 'GET')  { $ctl->show($ctx, $id);   return; }
        if ($third === 'edit'   && $method === 'GET')  { $ctl->edit($ctx, $id);   return; }
        if ($third === ''       && $method === 'POST') { $ctl->update($ctx, $id); return; }

        if ($third === 'print'  && $method === 'GET')  { $ctl->print($ctx, $id);  return; }
        if ($third === 'pdf'    && $method === 'GET')  { $ctl->pdf($ctx, $id);    return; }
    }

    $notFound('Invoices route not found');
}

/* ------------------------------------------------------------------ *
 *  Purchases (supplier-side docs)
 * ------------------------------------------------------------------ */
if ($first === 'purchases') {
    if (!$load(PurchasesController::class, 'PurchasesController.php')) {
        $render('purchases/index', ['title' => 'Purchases']);
        return;
    }

    $ctl   = new PurchasesController();
    $id    = ctype_digit($second) ? (int)$second : null;
    $third = $third ?? '';

    // GET /purchases
    if ($second === '' && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // POST /purchases  (legacy store – thakle use hobe, na thakle ignore korbe)
    if ($second === '' && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    // GET /purchases/create
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    if ($id !== null) {
        // GET /purchases/{id}
        if (($third === '' || $third === null) && $method === 'GET') {
            $ctl->show($ctx, $id);
            return;
        }

        // GET /purchases/{id}/edit   (jodi edit() thake)
        if ($third === 'edit' && $method === 'GET') {
            $ctl->edit($ctx, $id);
            return;
        }

        // POST /purchases/{id}       (legacy update – thakle use hobe)
        if (($third === '' || $third === null) && $method === 'POST') {
            $ctl->update($ctx, $id);
            return;
        }

        // 🔹 NEW: GET /purchases/{id}/pdf — download / open PDF version
        if ($third === 'pdf' && $method === 'GET') {
            // Controller method: public function pdf(?array $ctx, int $id): void
            $ctl->pdf($ctx, $id);
            return;
        }

        // 🔹 NEW: GET /purchases/{id}/print — print-friendly HTML (auto window.print)
        if ($third === 'print' && $method === 'GET') {
            // Controller method: public function print(?array $ctx, int $id): void
            $ctl->print($ctx, $id);
            return;
        }

        // 🔹 EXISTING: GET /purchases/{id}/receive → entry point to GRN-from-purchase
        if ($third === 'receive' && $method === 'GET') {
            $ctl->receive($ctx, $id);
            return;
        }
    }

    $notFound('Purchases route not found');
}


/* ============================================================================
 * GRN — Goods Receipt Notes
 *   /grn
 *   /grn/create-from-purchase?purchase_id={id}
 *   /grn/create           (manual page – still available but not linked)
 *   /grn/{id}
 *   /grn/{id}/edit
 *   /grn/{id}/post        (post to inventory)
 * ========================================================================== */
if ($first === 'grn') {

    // Load controller (same helper you use for warehouse, items, etc.)
    if (!$load(GrnController::class, 'GrnController.php')) {
        $notFound('GRN module not available');
        return;
    }

    $ctl    = new GrnController();
    $second = $second ?? '';
    $third  = $third  ?? '';
    $id     = ctype_digit($second) ? (int)$second : null;

    /* ---------- GET /grn (index) ---------- */
    if ($second === '' && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- GET /grn/create-from-purchase ---------- */
    if ($second === 'create-from-purchase' && $method === 'GET') {
        // inside controller it will read ?purchase_id= from query string
        $ctl->createFromPurchase($ctx);
        return;
    }

    /* ---------- GET /grn/create (manual page – optional) ---------- */
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    /* ---------- POST /grn (store new GRN) ---------- */
    if ($second === '' && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    /* ---------- GET /grn/{id} (show) ---------- */
    if ($id !== null && $third === '' && $method === 'GET') {
        $ctl->show($ctx, $id);
        return;
    }

    /* ---------- GET /grn/{id}/edit ---------- */
    if ($id !== null && $third === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, $id);
        return;
    }

    /* ---------- POST /grn/{id} (update existing GRN) ---------- */
    if ($id !== null && $third === '' && $method === 'POST') {
        $ctl->update($ctx, $id);
        return;
    }

    /* ---------- POST/GET /grn/{id}/post (post to inventory) ---------- */
    if ($id !== null && $third === 'post' && in_array($method, ['POST', 'GET'], true)) {
        // If you do CSRF in the controller, keep it there.
        // This will now work whether the button is a form POST or a simple link.
        $ctl->post($ctx, $id);
        return;
    }

    // Anything else under /grn → 404
    $notFound('GRN route not found');
    return;
}

/* ------------------------------------------------------------------ *
 * 10) Tenders / RFQs
 * ------------------------------------------------------------------ */
if ($first === 'tenders') {
    if (!$load(TendersController::class, 'TendersController.php')) {
        $render('tenders/index', ['title' => 'Tenders']);
        return;
    }

    $ctl = new TendersController();
    $id  = ctype_digit($second) ? (int)$second : null;

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); return;
    }

    if ($id !== null) {
        if ($third === ''     && $method === 'GET')  { $ctl->show($ctx, $id);   return; }
        if ($third === 'edit' && $method === 'GET')  { $ctl->edit($ctx, $id);   return; }
        if ($third === ''     && $method === 'POST') { $ctl->update($ctx, $id); return; }
    }

    $notFound('Tenders route not found');
}

/* ------------------------------------------------------------------ *
 *  LC (Import Letters of Credit)
 *  URLs:
 *    /lcs              → index
 *    /lcs/create       → create
 *    /lcs/{id}         → show
 *    /lcs/{id}/edit    → edit
 *    /lcs   [POST]     → store
 *    /lcs/{id} [POST]  → update
 * ------------------------------------------------------------------ */
if ($first === 'lcs') {

    if (!$load(LcsController::class, 'LcsController.php')) {
        $notFound('LC module not available');
        return;
    }

    $ctl   = new LcsController();
    $id    = (isset($second) && ctype_digit((string)$second)) ? (int)$second : null;
    $third = $third ?? '';

    // GET /lcs (index)
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // GET /lcs/create
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    // POST /lcs
    if (($second === '' || $second === null) && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    // GET /lcs/{id}
    if ($id !== null && ($third === '' || $third === null) && $method === 'GET') {
        $ctl->show($ctx, $id);
        return;
    }

    // GET /lcs/{id}/edit
    if ($id !== null && $third === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, $id);
        return;
    }

    // POST /lcs/{id}
    if ($id !== null && ($third === '' || $third === null) && $method === 'POST') {
        $ctl->update($ctx, $id);
        return;
    }

    $notFound('LC route not found');
    return;
}




/* ============================================================================
 * Employees
 *   /employees
 *   /employees/create
 *   /employees/{id}
 *   /employees/{id}/edit
 * ========================================================================== */
if ($first === 'employees') {

    // Load controller (same helper style as Warehouse)
    if (!$load(EmployeesController::class, 'EmployeesController.php')) {
        $notFound('Employees module not available');
        return;
    }

    $ctl   = new EmployeesController();
    $id    = (isset($second) && ctype_digit((string)$second)) ? (int)$second : null;
    $third = $third ?? '';

    /* ---------- GET /employees (index) ---------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- GET /employees/create ---------- */
    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx);
        return;
    }

    /* ---------- POST /employees (store new) ---------- */
    if (($second === '' || $second === null) && $method === 'POST') {
        $ctl->store($ctx);
        return;
    }

    /* ---------- GET /employees/{id} (show) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'GET') {
        $ctl->show($ctx, $id);
        return;
    }

    /* ---------- GET /employees/{id}/edit ---------- */
    if ($id !== null && $third === 'edit' && $method === 'GET') {
        $ctl->edit($ctx, $id);
        return;
    }

    /* ---------- POST /employees/{id} (update) ---------- */
    if ($id !== null && ($third === '' || $third === null) && $method === 'POST') {
        $ctl->update($ctx, $id);
        return;
    }

    // Anything else under /employees → 404
    $notFound('Employees route not found');
    return;
}

/* ============================================================================
 * Payroll
 *   /payroll           (GET  → monthly sheet)
 *   /payroll/recalc    (POST → recalc preview)
 *   /payroll/lock      (POST → lock / posting preview)
 * ========================================================================== */
if ($first === 'payroll') {

    // Load controller (same helper pattern as warehouse, items, etc.)
    if (!$load(PayrollController::class, 'PayrollController.php')) {
        $notFound('Payroll module not available');
        return;
    }

    $ctl    = new PayrollController();
    $second = $second ?? '';
    $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');

    /* ---------- GET /payroll (index: monthly sheet) ---------- */
    if (($second === '' || $second === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- POST /payroll/recalc (recalculate preview) --- */
    if ($second === 'recalc' && $method === 'POST') {
        $ctl->recalc($ctx);
        return;
    }

    /* ---------- POST /payroll/lock (lock / posting preview) -- */
    if ($second === 'lock' && $method === 'POST') {
        $ctl->lock($ctx);
        return;
    }

    // Anything else under /payroll → 404
    $notFound('Payroll route not found');
    return;
}







/* ------------------------------------------------------------------ *
 * 4) Customers
 * ------------------------------------------------------------------ */
if ($first === 'customers') {
    if (!$load(CustomersController::class, 'CustomersController.php')) {
        $render('customers/index', ['title' => 'Customers']);
        return;
    }

    $ctl = new CustomersController();
    $id  = ctype_digit($second) ? (int)$second : null;

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); return;
    }

    if ($id !== null) {
        if ($third === ''     && $method === 'GET')  { $ctl->show($ctx, $id);   return; }
        if ($third === 'edit' && $method === 'GET')  { $ctl->edit($ctx, $id);   return; }
        if ($third === ''     && $method === 'POST') { $ctl->update($ctx, $id); return; }
    }

    $notFound('Customers route not found');
}

/* ------------------------------------------------------------------ *
 * 5) Suppliers
 * ------------------------------------------------------------------ */
if ($first === 'suppliers') {
    if (!$load(SuppliersController::class, 'SuppliersController.php')) {
        $render('suppliers/index', ['title' => 'Suppliers']);
        return;
    }

    $ctl = new SuppliersController();
    $id  = ctype_digit($second) ? (int)$second : null;

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->store($ctx);  return; }

    if ($second === 'create' && $method === 'GET') {
        $ctl->create($ctx); return;
    }

    if ($id !== null) {
        if ($third === ''     && $method === 'GET')  { $ctl->show($ctx, $id);   return; }
        if ($third === 'edit' && $method === 'GET')  { $ctl->edit($ctx, $id);   return; }
        if ($third === ''     && $method === 'POST') { $ctl->update($ctx, $id); return; }
    }

    $notFound('Suppliers route not found');
}


/* ------------------------------------------------------------------ *
 * 11) Inventory
 *   /inventory
 *   /inventory/movements
 *   /inventory/transfers
 *   /inventory/transfers/create
 *   /inventory/adjustments
 *   /inventory/adjustments/create
 * ------------------------------------------------------------------ */
if ($first === 'inventory') {
    if (!$load(InventoryController::class, 'InventoryController.php')) {
        $render('inventory/index', ['title' => 'Inventory']);
        return;
    }

    $ctl = new InventoryController();

    /* ---------- GET /inventory (dashboard) ---------- */
    if ($second === '' && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    /* ---------- Optional list endpoints (if you add them later) ---------- */
    // GET /inventory/movements
    if ($second === 'movements' && $third === '' && $method === 'GET' && method_exists($ctl, 'movements')) {
        $ctl->movements($ctx);
        return;
    }

    // GET /inventory/transfers  (list)
    if ($second === 'transfers' && $third === '' && $method === 'GET' && method_exists($ctl, 'transfers')) {
        $ctl->transfers($ctx);
        return;
    }

    // GET /inventory/adjustments (list)
    if ($second === 'adjustments' && $third === '' && $method === 'GET' && method_exists($ctl, 'adjustments')) {
        $ctl->adjustments($ctx);
        return;
    }

    /* ---------- TRANSFERS: create + store ---------- */

    // GET /inventory/transfers/create
    if ($second === 'transfers' && $third === 'create' && $method === 'GET'
        && method_exists($ctl, 'createTransfer')) {
        $ctl->createTransfer($ctx);
        return;
    }

    // POST /inventory/transfers
    if ($second === 'transfers' && $third === '' && $method === 'POST'
        && method_exists($ctl, 'storeTransfer')) {
        $ctl->storeTransfer($ctx);
        return;
    }

    /* ---------- ADJUSTMENTS: create + store ---------- */

    // GET /inventory/adjustments/create
    if ($second === 'adjustments' && $third === 'create' && $method === 'GET'
        && method_exists($ctl, 'createAdjustment')) {
        $ctl->createAdjustment($ctx);
        return;
    }

    // POST /inventory/adjustments
    if ($second === 'adjustments' && $third === '' && $method === 'POST'
        && method_exists($ctl, 'storeAdjustment')) {
        $ctl->storeAdjustment($ctx);
        return;
    }

    /* ---------- Fallback ---------- */
    $notFound('Inventory route not found');
}







/* ------------------------------------------------------------------ *
 * 14) Settings
 * ------------------------------------------------------------------ */
if ($first === 'settings') {
    if (!$load(SettingsController::class, 'SettingsController.php')) {
        $render('settings/index', ['title' => 'Settings']);
        return;
    }

    $ctl = new SettingsController();

    if ($second === '' && $method === 'GET')  { $ctl->index($ctx);  return; }
    if ($second === '' && $method === 'POST') { $ctl->update($ctx); return; }

    $notFound('Settings route not found');
}


/* ============================================================================
 * API — unified lookup
 *   /api/lookup
 *   /api/lookup/{entity}
 * ========================================================================== */
if ($first === 'api' && $second === 'lookup') {

    if (!$load(LookupController::class, 'LookupController.php')) {
        $notFound('Lookup API not available');
        return;
    }

    $ctl   = new LookupController();
    $third = $third ?? '';

    // GET /api/lookup
    if (($third === '' || $third === null) && $method === 'GET') {
        $ctl->index($ctx);
        return;
    }

    // GET /api/lookup/{entity}
    if ($third !== '' && $method === 'GET') {
        $ctl->handle($ctx, $third);
        return;
    }

    $notFound('Lookup route not found');
    return;
}


/* ------------------------------------------------------------------ *
 * 15) Fallback 404
 * ------------------------------------------------------------------ */
$notFound('BizFlow route not found: ' . (string)$tail);