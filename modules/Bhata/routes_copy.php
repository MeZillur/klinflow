<?php
declare(strict_types=1);

/* ─────────────────────── Polyfills (PHP 7.2 safe) ─────────────────────── */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return $len <= strlen($haystack) && substr($haystack, -$len) === $needle;
    }
}

/* ───────────────────────── Context & Paths ─────────────────────────────── */
$ctx    = (isset($__KF_MODULE__) && is_array($__KF_MODULE__)) ? $__KF_MODULE__ : [];
$tail   = trim((string)($ctx['tail']   ?? ''), '/');                // "", "dashboard", "landing", "production/kiln"
$dir    = (string)($ctx['module_dir'] ?? __DIR__);                 // /modules/Bhata
$views  = rtrim($dir, '/\\') . '/Views';                           // /modules/Bhata/Views
$method = strtoupper((string)($ctx['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));

/* Ensure Views folder exists to avoid warnings on is_file() */
if (!is_dir($views)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "BhataFlow boot error: missing Views directory at {$views}";
    return;
}

/* ───────────────────────── Small view loader ───────────────────────────── */
$show = function (string $relative) use ($views): bool {
    $file = $views . '/' . ltrim($relative, '/');
    if (is_file($file)) {
        /** @noinspection PhpIncludeInspection */
        require $file;
        return true;
    }
    return false;
};

/* =================== Dashboard / Landing (default) =================== */
/* Matches:
 *   GET /t/{slug}/apps/bhata
 *   GET /t/{slug}/apps/bhata/dashboard
 *   GET /t/{slug}/apps/bhata/landing
 */
if ($method === 'GET' && ($tail === '' || $tail === 'dashboard' || $tail === 'landing')) {
    if ($show('landing.php')) { return; }

    // Developer hint page (no fatal)
    header('Content-Type: text/html; charset=utf-8');
    $v = htmlspecialchars($views, ENT_QUOTES, 'UTF-8');
    echo "<h1>BhataFlow</h1><p>View not found. Create one of:</p><ul>
            <li><code>{$v}/landing.php</code></li>
            <li><code>{$v}/dashboard/index.php</code></li>
          </ul>";
    return;
}

/* --------------------------------------------------------------------------
 * Example route group: Production
 *   /production        → Views/production/index.php
 *   /production/kiln   → Views/production/kiln.php  (or /kiln/index.php)
 * ------------------------------------------------------------------------*/
if ($method === 'GET' && ($tail === 'production' || str_starts_with($tail, 'production/'))) {
    $sub = trim(substr($tail, strlen('production')), '/'); // "" or "kiln", "moulding", ...
    $candidates = $sub === ''
        ? [$views.'/production/index.php']
        : [
            $views.'/production/'.$sub.'.php',
            $views.'/production/'.$sub.'/index.php',
          ];
    foreach ($candidates as $v) {
        if (is_file($v)) { require $v; return; }
    }
    http_response_code(404);
    echo "BhataFlow: production view not found for /{$tail}";
    return;
}

/* --------------------------------------------------------------------------
 * Example route group: Dispatch
 * ------------------------------------------------------------------------*/
if ($method === 'GET' && ($tail === 'dispatch' || str_starts_with($tail, 'dispatch/'))) {
    $sub = trim(substr($tail, strlen('dispatch')), '/');
    $candidates = $sub === ''
        ? [$views.'/dispatch/index.php']
        : [
            $views.'/dispatch/'.$sub.'.php',
            $views.'/dispatch/'.$sub.'/index.php',
          ];
    foreach ($candidates as $v) {
        if (is_file($v)) { require $v; return; }
    }
    http_response_code(404);
    echo "BhataFlow: dispatch view not found for /{$tail}";
    return;
}

$routes = [

    /* ======================== Dashboard / Landing ======================== */
    'GET '          => [ 'Modules\Bhata\Controllers\LandingController@index', 'landing.php' ],
'GET dashboard' => [ 'Modules\Bhata\Controllers\LandingController@index', 'landing.php' ],
'GET landing'   => [ 'Modules\Bhata\Controllers\LandingController@index', 'landing.php' ],

    /* ============================ Production ============================= */
    // Clamp setup, sections, curing plan
    'GET production/clamps'           => [ 'Modules\Bhata\Controllers\Production\ClampsController@index', 'production/clamps/index.php' ],
    'GET production/clamps/create'    => [ 'Modules\Bhata\Controllers\Production\ClampsController@create', 'production/clamps/create.php' ],
    'POST production/clamps'          => [ 'Modules\Bhata\Controllers\Production\ClampsController@store',  'production/clamps/create.php' ],
    'GET production/clamps/{id}'      => [ 'Modules\Bhata\Controllers\Production\ClampsController@show',   'production/clamps/show.php' ],
    'GET production/clamps/{id}/edit' => [ 'Modules\Bhata\Controllers\Production\ClampsController@edit',   'production/clamps/edit.php' ],
    'POST production/clamps/{id}'     => [ 'Modules\Bhata\Controllers\Production\ClampsController@update', 'production/clamps/edit.php' ],

    // Green bricks moulding (by gang/party, piece-rate capture)
    'GET production/moulding'         => [ 'Modules\Bhata\Controllers\Production\MouldingController@index', 'production/moulding/index.php' ],
    'POST production/moulding'        => [ 'Modules\Bhata\Controllers\Production\MouldingController@store', 'production/moulding/index.php' ],

    // Firing batches (kiln stage tracking, coal use)
    'GET production/firing'           => [ 'Modules\Bhata\Controllers\Production\FiringController@index', 'production/firing/index.php' ],
    'POST production/firing'          => [ 'Modules\Bhata\Controllers\Production\FiringController@store', 'production/firing/index.php' ],

    // Dispatch/loading to yards or trucks
    'GET production/dispatch'         => [ 'Modules\Bhata\Controllers\Production\DispatchController@index', 'production/dispatch/index.php' ],
    'POST production/dispatch'        => [ 'Modules\Bhata\Controllers\Production\DispatchController@store', 'production/dispatch/index.php' ],

    /* ============================= Materials ============================= */
    'GET materials/clay'              => [ 'Modules\Bhata\Controllers\Materials\ClayController@index',   'materials/clay/index.php' ],
    'POST materials/clay'             => [ 'Modules\Bhata\Controllers\Materials\ClayController@store',   'materials/clay/index.php' ],
    'GET materials/coal'              => [ 'Modules\Bhata\Controllers\Materials\CoalController@index',   'materials/coal/index.php' ],
    'POST materials/coal'             => [ 'Modules\Bhata\Controllers\Materials\CoalController@store',   'materials/coal/index.php' ],
    'GET materials/sand'              => [ 'Modules\Bhata\Controllers\Materials\SandController@index',   'materials/sand/index.php' ],
    'POST materials/sand'             => [ 'Modules\Bhata\Controllers\Materials\SandController@store',   'materials/sand/index.php' ],
    'GET materials/stock'             => [ 'Modules\Bhata\Controllers\Materials\StockController@index',  'materials/stock/index.php' ],
    'GET materials/vouchers'          => [ 'Modules\Bhata\Controllers\Materials\VouchersController@index','materials/vouchers/index.php' ],

    /* ================================ Sales ============================== */
    'GET sales/orders'                => [ 'Modules\Bhata\Controllers\Sales\OrdersController@index',     'sales/orders/index.php' ],
    'GET sales/orders/create'         => [ 'Modules\Bhata\Controllers\Sales\OrdersController@create',    'sales/orders/create.php' ],
    'POST sales/orders'               => [ 'Modules\Bhata\Controllers\Sales\OrdersController@store',     'sales/orders/create.php' ],
    'GET sales/customers'             => [ 'Modules\Bhata\Controllers\Sales\CustomersController@index',  'sales/customers/index.php' ],
    'GET sales/pricing'               => [ 'Modules\Bhata\Controllers\Sales\PricingController@index',    'sales/pricing/index.php' ],
    'GET sales/invoices'              => [ 'Modules\Bhata\Controllers\Sales\InvoicesController@index',   'sales/invoices/index.php' ],
    'GET sales/returns'               => [ 'Modules\Bhata\Controllers\Sales\ReturnsController@index',    'sales/returns/index.php' ],

    /* ================================= HR ================================ */
    'GET hr/attendance'               => [ 'Modules\Bhata\Controllers\HR\AttendanceController@index',    'hr/attendance/index.php' ],
    'POST hr/attendance'              => [ 'Modules\Bhata\Controllers\HR\AttendanceController@store',    'hr/attendance/index.php' ],
    'GET hr/piece-rate'               => [ 'Modules\Bhata\Controllers\HR\PieceRateController@index',     'hr/piece_rate/index.php' ],
    'POST hr/piece-rate'              => [ 'Modules\Bhata\Controllers\HR\PieceRateController@store',     'hr/piece_rate/index.php' ],
    'GET hr/advances'                 => [ 'Modules\Bhata\Controllers\HR\AdvancesController@index',      'hr/advances/index.php' ],
    'POST hr/advances'                => [ 'Modules\Bhata\Controllers\HR\AdvancesController@store',      'hr/advances/index.php' ],
    'GET hr/payroll'                  => [ 'Modules\Bhata\Controllers\HR\PayrollController@index',        'hr/payroll/index.php' ],

    /* ============================== Finance ============================== */
    'GET finance/expenses'            => [ 'Modules\Bhata\Controllers\Finance\ExpensesController@index', 'finance/expenses/index.php' ],
    'POST finance/expenses'           => [ 'Modules\Bhata\Controllers\Finance\ExpensesController@store', 'finance/expenses/index.php' ],
    'GET finance/banking'             => [ 'Modules\Bhata\Controllers\Finance\BankingController@index',  'finance/banking/index.php' ],
    'GET finance/cashbook'            => [ 'Modules\Bhata\Controllers\Finance\CashbookController@index', 'finance/cashbook/index.php' ],
    'GET gl/journals'                 => [ 'Modules\Bhata\Controllers\Finance\JournalsController@index', 'gl/journals/index.php' ],
    'POST gl/journals'                => [ 'Modules\Bhata\Controllers\Finance\JournalsController@store', 'gl/journals/index.php' ],
    'GET banking/reconcile'           => [ 'Modules\Bhata\Controllers\Finance\ReconcileController@index','banking/reconcile/index.php' ],

    /* ======================== Operations / Compliance ==================== */
    'GET ops/maintenance'             => [ 'Modules\Bhata\Controllers\Ops\MaintenanceController@index',  'ops/maintenance/index.php' ],
    'GET ops/compliance'              => [ 'Modules\Bhata\Controllers\Ops\BlueprintController@compliance','ops/compliance/index.php' ],

    /* =============================== Reports ============================= */
    'GET reports'                     => [ 'Modules\Bhata\Controllers\Reports\HomeController@index',     'reports/index.php' ],
    'GET reports/production'          => [ 'Modules\Bhata\Controllers\Reports\ProductionController@index','reports/production/index.php' ],
    'GET reports/sales'               => [ 'Modules\Bhata\Controllers\Reports\SalesController@index',    'reports/sales/index.php' ],
    'GET reports/finance'             => [ 'Modules\Bhata\Controllers\Reports\FinanceController@index',  'reports/finance/index.php' ],
    'GET reports/hr'                  => [ 'Modules\Bhata\Controllers\Reports\HrController@index',       'reports/hr/index.php' ],

    /* =============================== Settings ============================ */
    'GET settings'                    => [ 'Modules\Bhata\Controllers\Settings\HomeController@index',    'settings/index.php' ],
    'GET settings/masters/grades'     => [ 'Modules\Bhata\Controllers\Settings\MastersController@grades','settings/masters/grades.php' ],
    'GET settings/masters/sizes'      => [ 'Modules\Bhata\Controllers\Settings\MastersController@sizes', 'settings/masters/sizes.php' ],
    'GET settings/masters/locations'  => [ 'Modules\Bhata\Controllers\Settings\MastersController@locations','settings/masters/locations.php' ],
    'GET settings/masters/pits'       => [ 'Modules\Bhata\Controllers\Settings\MastersController@pits',  'settings/masters/pits.php' ],
    'GET settings/wage-rates'         => [ 'Modules\Bhata\Controllers\Settings\RatesController@index',   'settings/wage_rates/index.php' ],

    /* ================================= API =============================== */
    'GET api/health'                  => [ null, null ], // simple JSON
];

/* -------------------------------------------------------------
 * Tiny matcher with {id} support
 * ----------------------------------------------------------- */
$matchRoute = function (string $method, string $tail, array $routes): ?array {
    foreach ($routes as $key => $target) {
        [$m, $path] = explode(' ', $key, 2);
        if ($m !== $method) continue;

        // Handle path params like {id}
        $regex = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $path);
        $regex = '#^' . rtrim($regex, '/') . '$#';

        if (preg_match($regex, rtrim($tail, '/'), $mats)) {
            array_shift($mats);
            return [$path, $target, $mats];
        }
    }
    return null;
};

/* -------------------------------------------------------------
 * Dispatch
 * ----------------------------------------------------------- */
if ($tail === '' && $method !== 'GET') {
    // Root only accepts GET
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method not allowed ({$method})";
    return;
}

if ($hit = $matchRoute($method, $tail, $routes)) {
    [$path, $target, $params] = $hit;
    [$ctrlSpec, $viewRel] = $target;

    // Special JSON for api/health (zero-deps)
    if ($path === 'api/health') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'         => true,
            'module'     => 'BhataFlow',
            'time'       => gmdate('c'),
            'tenantSlug' => (string)($ctx['slug'] ?? ''),
        ]);
        return;
    }

    // If a controller is specified, split "FQCN@method"
    if (is_string($ctrlSpec) && strpos($ctrlSpec, '@') !== false) {
        [$fqcn, $methodName] = explode('@', $ctrlSpec, 2);
        $vars = $ctx + [
            'params'  => $params,
            '_view'   => $viewRel,   // fallback view path if controller not present yet
            'base'    => $base,
        ];
        if ($dispatch($fqcn, $methodName, $vars) === true) return;
    }

    // Otherwise render the view directly
    $vars = $ctx + ['params' => $params, 'base' => $base];
    $render($viewRel, $vars);
    return;
}

/* No match → 404 */
$notFound();