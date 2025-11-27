<?php
declare(strict_types=1);

/**
 * BhataFlow — routes.php (stable)
 * - Pure PHP router (no framework)
 * - Controller-first with view fallback
 * - Landing is standalone (no shell)
 */

/* ── Polyfills (PHP 7.2 safe) ─────────────────────────────────────────── */
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

/* ── Boot context ─────────────────────────────────────────────────────── */
$__ctx  = (array)($__KF_MODULE__ ?? []);
$slug   = (string)($__ctx['slug'] ?? '');
$tail   = trim((string)($__ctx['tail'] ?? ''), '/');
$dir    = (string)($__ctx['module_dir'] ?? __DIR__);
$views  = rtrim($dir, '/\\') . '/Views';
$method = strtoupper((string)($__ctx['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
$org    = (array)($__ctx['org'] ?? []);

if (!is_dir($views)) {
    http_response_code(500);
    echo "BhataFlow boot error: missing Views directory at {$views}";
    return;
}

$module_base = (string)($__ctx['module_base'] ?? '');
if ($module_base === '') {
    $module_base = ($slug !== '' && $slug !== '_')
        ? '/t/' . rawurlencode($slug) . '/apps/bhata'
        : '/apps/bhata';
}
$module_base = rtrim($module_base, '/');

$ctx = [
    'slug'        => $slug,
    'org'         => $org,
    'method'      => $method,
    'tail'        => $tail,
    'module_dir'  => $dir,
    'module_base' => $module_base,
];

/* ── Utilities ────────────────────────────────────────────────────────── */
$render = static function (string $rel, array $data = []) use ($views, $ctx): void {
    // allow "foo.php" or "foo/index.php"
    $rel = ltrim($rel, '/');
    $file = $views . '/' . $rel;
    if (!is_file($file)) {
        $alt = $views . '/' . rtrim($rel, '/');
        if (!str_ends_with($alt, '.php')) $alt .= '/index.php';
        if (is_file($alt)) $file = $alt;
    }
    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "View not found: {$file}";
        return;
    }

    // expose $base for views (prevents "double base" bugs)
    $base = $ctx['module_base'];

    extract($ctx,  EXTR_SKIP);
    extract($data, EXTR_SKIP);
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    require $file;
};

$callController = static function (?string $spec, array $vars = []) {
    if (!$spec || strpos($spec, '@') === false) return false;
    [$fqcn, $fn] = explode('@', $spec, 2);
    if (!class_exists($fqcn) || !method_exists($fqcn, $fn)) return false;
    (new $fqcn())->{$fn}($vars);
    return true;
};

$notFound = static function (string $msg = '') use ($module_base): void {
    http_response_code(404);
    $m = htmlspecialchars($msg ?: 'Route not found.', ENT_QUOTES, 'UTF-8');
    echo "<div style='padding:20px;font-family:system-ui'>
            <h2>404</h2><p>{$m}</p>
            <a href='{$module_base}' style='color:#228B22;text-decoration:none'>← Back to BhataFlow</a>
          </div>";
};

$json = static function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
};

/* ── Landing (standalone; no shell) ───────────────────────────────────── */
if ($method === 'GET' && ($tail === '' || $tail === 'dashboard' || $tail === 'landing')) {
    if ($callController('Modules\\Bhata\\Controllers\\LandingController@index', $ctx)) return;
    if (is_file($views.'/landing.php')) { $render('landing.php'); return; }

    http_response_code(500);
    echo "<h1>BhataFlow</h1><p>Landing view missing at:</p>
          <code>{$views}/landing.php</code>";
    return;
}

/* ── ROUTES (declare BEFORE dispatch!) ─────────────────────────────────── */
$routes = [
    // Health
    'GET api/health' => [null, null],

    // Production (home + sections)
    'GET production'              => ['Modules\Bhata\Controllers\Production\HomeController@index',       'production/index.php'],
    'GET production/moulding'     => ['Modules\Bhata\Controllers\Production\MouldingController@index',   'production/moulding/index.php'],
    'POST production/moulding'    => ['Modules\Bhata\Controllers\Production\MouldingController@store',   'production/moulding/index.php'],
    'GET production/firing'       => ['Modules\Bhata\Controllers\Production\FiringController@index',     'production/firing/index.php'],
    'POST production/firing'      => ['Modules\Bhata\Controllers\Production\FiringController@store',     'production/firing/index.php'],
    'GET production/dispatch'     => ['Modules\Bhata\Controllers\Production\DispatchController@index',   'production/dispatch/index.php'],
    'POST production/dispatch'    => ['Modules\Bhata\Controllers\Production\DispatchController@store',   'production/dispatch/index.php'],
];

/* ── Matcher & dispatch ───────────────────────────────────────────────── */
$match = static function (string $method, string $tail, array $routes): ?array {
    foreach ($routes as $key => $target) {
        [$m, $path] = explode(' ', $key, 2);
        if ($m !== $method) continue;

        // {id} param support
        $regex = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([^/]+)', $path);
        $regex = '#^' . rtrim($regex, '/') . '$#';

        if (preg_match($regex, rtrim($tail, '/'), $mats)) {
            array_shift($mats);
            return [$path, $target, $mats];
        }
    }
    return null;
};

if ($hit = $match($method, $tail, $routes)) {
    [$path, $target, $params] = $hit;
    [$ctrlSpec, $viewRel] = $target;

    if ($path === 'api/health') {
        $json(['ok' => true, 'module' => 'BhataFlow', 'time' => gmdate('c'), 'tenant' => $slug]);
        return;
    }

    if ($callController($ctrlSpec, $ctx + ['params' => $params])) return;
    if ($viewRel) { $render($viewRel, ['params' => $params]); return; }
}

/* ── Fallback: try view auto-discovery after routes ───────────────────── */
$try = [$views.'/'.$tail.'.php', $views.'/'.$tail.'/index.php'];
foreach ($try as $f) {
    if (is_file($f)) { $render(str_replace($views.'/', '', $f)); return; }
}

/* ── 404 ──────────────────────────────────────────────────────────────── */
$notFound('/'.$tail);