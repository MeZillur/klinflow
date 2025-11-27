<?php
declare(strict_types=1);

/**
 * modules/hotelflow/front.php
 * HotelFlow front controller (POS-style) with _debug=1 support.
 */

namespace {
    /* ------------------------------------------------------------------ *
     * 0) Debug switches
     * ------------------------------------------------------------------ */
    $KF_HOTEL_DEBUG =
        getenv('APP_DEBUG') === '1'
        || strcasecmp((string)getenv('APP_DEBUG'), 'true') === 0
        || (isset($_GET['_debug']) && $_GET['_debug'] === '1');

    if (!headers_sent()) {
        header('X-KF-Debug: ' . ($KF_HOTEL_DEBUG ? 'hotelflow=on' : 'hotelflow=off'));
    }

    /* ------------------------------------------------------------------ *
     * 1) Minimal error/exception/shutdown handler
     * ------------------------------------------------------------------ */
    $kf_hotel_log = '/tmp/klinflow-hotelflow.log';

    $kf_emit = function (string $title, \Throwable $e) use ($KF_HOTEL_DEBUG, $kf_hotel_log): void {
        $id   = bin2hex(random_bytes(6));
        $line = '[' . date('Y-m-d H:i:s') . "] {$id} {$title}: "
              . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
              . "\n" . $e->getTraceAsString() . "\n";

        @file_put_contents($kf_hotel_log, $line, FILE_APPEND);

        http_response_code(500);

        if ($KF_HOTEL_DEBUG) {
            echo "<pre style='white-space:pre-wrap;font:14px/1.45 monospace;padding:16px;'>"
               . "HotelFlow Error ({$id}) — {$title}\n\n"
               . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n"
               . $e->getTraceAsString()
               . "</pre>";
        } else {
            echo "Something went wrong. Error ID: {$id}";
        }
    };

    if ($KF_HOTEL_DEBUG) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }

    set_exception_handler(function (\Throwable $e) use ($kf_emit): void {
        $kf_emit('Uncaught exception', $e);
        exit;
    });

    set_error_handler(function ($sev, $msg, $file, $line) use ($kf_emit) {
        if (!(error_reporting() & $sev)) {
            return false;
        }
        $kf_emit('PHP error', new \ErrorException($msg, 0, $sev, $file, $line));
        return true;
    });

    register_shutdown_function(function () use ($kf_emit): void {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $kf_emit('Fatal error', new \ErrorException(
                $e['message'],
                0,
                $e['type'],
                $e['file'],
                $e['line']
            ));
        }
    });
}

namespace {
    use Shared\View;

    /* ------------------------------------------------------------------ *
     * 2) Kernel-provided module context (required)
     * ------------------------------------------------------------------ */
    $__KF_MODULE__ = $__KF_MODULE__ ?? null;
    if (!$__KF_MODULE__ || !is_array($__KF_MODULE__)) {
        http_response_code(500);
        echo 'HotelFlow front: missing module context.';
        exit;
    }

    /* Core paths */
    $modDir     = __DIR__;
    $viewsDir   = $modDir . '/Views';
    $routesFile = $modDir . '/routes.php';
    $manifest   = is_file($modDir . '/manifest.php')
        ? (array)require $modDir . '/manifest.php'
        : [];

    /* Request meta */
    $method  = strtoupper((string)($__KF_MODULE__['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $reqPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');

    /* ------------------------------------------------------------------ *
     * 3) Heal slug + org from kernel/session/URL
     * ------------------------------------------------------------------ */
    $slug = (string)($__KF_MODULE__['slug'] ?? '');
    if ($slug === '' && preg_match('#^/t/([^/]+)/apps/hotelflow#i', $reqPath, $m)) {
        $slug = $m[1];
    }

    $org = (array)($__KF_MODULE__['org'] ?? ($_SESSION['tenant_org'] ?? []));
    if ($slug !== '' && (!isset($org['slug']) || $org['slug'] === '')) {
        $org['slug'] = $slug;
    }

    // Resolve org_id from kernel -> session -> org array
    $orgId = 0;
    if (isset($__KF_MODULE__['org']['id'])) {
        $orgId = (int)$__KF_MODULE__['org']['id'];
    } elseif (!empty($_SESSION['tenant_org']['id'])) {
        $orgId = (int)$_SESSION['tenant_org']['id'];
    } elseif (isset($org['id'])) {
        $orgId = (int)$org['id'];
    }

    if ($orgId && !isset($org['org_id'])) {
        $org['org_id'] = $orgId;
    }

    /* ------------------------------------------------------------------ *
     * 4) Normalize module_base
     * ------------------------------------------------------------------ */
    $moduleBase = (string)($__KF_MODULE__['module_base'] ?? '');
    if ($moduleBase === '') {
        $moduleBase = $slug ? "/t/{$slug}/apps/hotelflow" : "/apps/hotelflow";
    } else {
        $moduleBase = '/' . ltrim($moduleBase, '/');
        $moduleBase = rtrim($moduleBase, '/');

        // If pointing at /apps/hotelflow but we know slug, rewrite to tenant base
        if ($slug !== '' && preg_match('#^/apps/hotelflow(?:$|/)#', $moduleBase)) {
            $moduleBase = "/t/{$slug}/apps/hotelflow";
        }
    }

    /* Redirect /apps/hotelflow → /t/{slug}/apps/hotelflow */
    if ($slug !== '' && preg_match('#^/apps/hotelflow(?:/|$)#', $reqPath)) {
        $tail = ltrim(substr($reqPath, strlen('/apps/hotelflow')), '/');
        $to   = $moduleBase . ($tail !== '' ? '/' . $tail : '');
        if (!headers_sent()) {
            header('Location: ' . $to, true, 302);
        }
        exit;
    }

    /* ------------------------------------------------------------------ *
     * 5) Tail inside module
     * ------------------------------------------------------------------ */
    $tail = (string)($__KF_MODULE__['tail'] ?? '');
    if ($tail === '' && $reqPath !== '') {
        $base = rtrim($moduleBase, '/');
        if ($base !== '' && str_starts_with($reqPath, $base)) {
            $tail = ltrim(substr($reqPath, strlen($base)), '/');
        }
    }
    $tail = rawurldecode(trim(preg_replace('#//+#', '/', $tail), "/ \t\r\n\0\x0B"));

    /* ------------------------------------------------------------------ *
     * 6) Resolve shell layout once
     * ------------------------------------------------------------------ */
    $layoutFile = null;
    if (isset($manifest['layout']) && is_string($manifest['layout']) && is_file($manifest['layout'])) {
        $layoutFile = $manifest['layout'];
    } else {
        $try = $modDir . '/Views/shared/layouts/shell.php';
        if (is_file($try)) {
            $layoutFile = $try;
        }
    }

    /* ------------------------------------------------------------------ *
     * 7) Canonical context (what controllers receive)
     * ------------------------------------------------------------------ */
    $ctx = [
        'slug'        => $slug,
        'org'         => $org,
        'org_id'      => $orgId,
        'module_base' => $moduleBase,
        'module_dir'  => $modDir,
        'layout'      => $layoutFile,
        'scope'       => 'tenant',
        'manifest'    => $manifest,
        'debug'       => $KF_HOTEL_DEBUG ?? false,
    ];

    /* ------------------------------------------------------------------ *
     * 8) Render helper (like POS)
     * ------------------------------------------------------------------ */
    $render = function (string $relView, array $data = [], ?string $layout = null) use ($ctx, $viewsDir): void {
        $file = rtrim($viewsDir, '/') . '/' . ltrim($relView, '/');
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }

        if (!is_file($file)) {
            http_response_code(404);
            echo 'View not found: ' . htmlspecialchars($relView, ENT_QUOTES, 'UTF-8');
            return;
        }

        $vars = array_merge($ctx, $data);
        extract($vars, EXTR_SKIP);

        $shell = $layout ?? ($ctx['layout'] ?? null);
        if ($shell && is_file($shell)) {
            $_content = $file;          // shell will require this
            require $shell;
        } else {
            require $file;              // bare include (landing-style)
        }
    };

    /* ------------------------------------------------------------------ *
     * 9) Hand off to routes, like $__POS__
     * ------------------------------------------------------------------ */
    $__HOTEL__ = [
        'ctx'    => $ctx,
        'render' => $render,
        'method' => $method,
        'tail'   => $tail,
    ];

    try {
        if (!is_file($routesFile)) {
            throw new \RuntimeException("routes.php missing at {$routesFile}");
        }
        require $routesFile;
    } catch (\Throwable $e) {
        // Re-use same debug flag + log for route failures
        $id = bin2hex(random_bytes(6));
        @file_put_contents(
            '/tmp/klinflow-hotelflow.log',
            '[' . date('Y-m-d H:i:s') . "] {$id} Routes failure: "
            . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
            . "\n" . $e->getTraceAsString() . "\n",
            FILE_APPEND
        );

        http_response_code(500);
        if ($KF_HOTEL_DEBUG) {
            echo "<pre style='white-space:pre-wrap;font:14px/1.45 monospace;padding:16px;'>"
               . "HotelFlow Error ({$id}) — Routes failure\n\n"
               . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n"
               . $e->getTraceAsString()
               . "</pre>";
        } else {
            echo "Something went wrong. Error ID: {$id}";
        }
    }
}