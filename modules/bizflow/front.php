<?php
declare(strict_types=1);

/**
 * modules/bizflow/front.php
 * BizFlow front controller with tenant slug healing + routes handoff.
 */

namespace {
    /* -------------------------------------------------------------
     * 0) Debug switches
     * ----------------------------------------------------------- */
    $KF_BIZ_DEBUG =
        getenv('APP_DEBUG') === '1'
        || strcasecmp((string)getenv('APP_DEBUG'), 'true') === 0
        || (isset($_GET['_debug']) && $_GET['_debug'] === '1');

    if (!headers_sent()) {
        header('X-KF-Debug: ' . ($KF_BIZ_DEBUG ? 'bizflow=on' : 'bizflow=off'));
    }

    /* -------------------------------------------------------------
     * 1) Minimal error / exception / shutdown handler
     * ----------------------------------------------------------- */
    $kf_biz_log  = '/tmp/klinflow-bizflow.log';

    $kf_biz_emit = function (string $title, \Throwable $e) use ($KF_BIZ_DEBUG, $kf_biz_log): void {
        $id   = bin2hex(random_bytes(6));
        $line = '[' . date('Y-m-d H:i:s') . "] {$id} {$title}: "
              . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
              . "\n" . $e->getTraceAsString() . "\n";
        @file_put_contents($kf_biz_log, $line, FILE_APPEND);

        http_response_code(500);

        if ($KF_BIZ_DEBUG) {
            echo "<pre style='white-space:pre-wrap;font:14px/1.45 monospace;padding:16px;'>"
               . "BizFlow Error ({$id}) — {$title}\n\n"
               . $e->getMessage() . "\n"
               . $e->getFile() . ':' . $e->getLine() . "\n\n"
               . $e->getTraceAsString()
               . "</pre>";
        } else {
            echo "Something went wrong in BizFlow. Error ID: {$id}";
        }
    };

    if ($KF_BIZ_DEBUG) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }

    set_exception_handler(function (\Throwable $e) use ($kf_biz_emit) {
        $kf_biz_emit('Uncaught exception', $e);
        exit;
    });

    set_error_handler(function ($sev, $msg, $file, $line) use ($kf_biz_emit) {
        if (!(error_reporting() & $sev)) return false;
        $kf_biz_emit('PHP error', new \ErrorException($msg, 0, $sev, $file, $line));
        return true;
    });

    register_shutdown_function(function () use ($kf_biz_emit) {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $kf_biz_emit(
                'Fatal error',
                new \ErrorException($e['message'], 0, $e['type'], $e['file'], $e['line'])
            );
        }
    });
}

namespace {
    /* -------------------------------------------------------------
     * 2) Kernel-provided module context (required)
     * ----------------------------------------------------------- */
    $__KF_MODULE__ = $__KF_MODULE__ ?? null;
    if (!$__KF_MODULE__ || !is_array($__KF_MODULE__)) {
        http_response_code(500);
        echo 'BizFlow front: missing module context.';
        exit;
    }

    /* Core paths */
    $modDir     = __DIR__;                   // modules/BizFlow
    $viewsDir   = $modDir . '/Views';
    $routesFile = $modDir . '/routes.php';
    $manifest   = is_file($modDir . '/manifest.php')
        ? (array) require $modDir . '/manifest.php'
        : [];

    /* Request meta */
    $method  = strtoupper((string) ($__KF_MODULE__['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $reqPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');

    if (\PHP_SESSION_ACTIVE !== \session_status()) {
        @\session_start();
    }

    /* -------------------------------------------------------------
     * 3) Heal slug + org from kernel / session / URL
     * ----------------------------------------------------------- */
    $slug = (string) ($__KF_MODULE__['slug'] ?? '');
    if ($slug === '' && preg_match('#^/t/([^/]+)/apps/bizflow#i', $reqPath, $m)) {
        $slug = $m[1];
    }

    $org = (array) ($__KF_MODULE__['org'] ?? ($_SESSION['tenant_org'] ?? []));
    if ($slug !== '' && (!isset($org['slug']) || $org['slug'] === '')) {
        $org['slug'] = $slug;
    }

    // Resolve org_id from kernel → session → org array → 0
    $orgId = 0;
    if (isset($__KF_MODULE__['org']['id'])) {
        $orgId = (int) $__KF_MODULE__['org']['id'];
    } elseif (!empty($_SESSION['tenant_org']['id'])) {
        $orgId = (int) $_SESSION['tenant_org']['id'];
    } elseif (isset($org['id'])) {
        $orgId = (int) $org['id'];
    }

    /* -------------------------------------------------------------
     * 4) Normalize module_base (always prefer slugged URL)
     * ----------------------------------------------------------- */
    $moduleBase = (string) ($__KF_MODULE__['module_base'] ?? '');
    if ($moduleBase === '') {
        $moduleBase = $slug ? "/t/{$slug}/apps/bizflow" : "/apps/bizflow";
    } else {
        // ensure leading slash, no trailing slash
        $moduleBase = '/' . ltrim($moduleBase, '/');
        $moduleBase = rtrim($moduleBase, '/');

        // if kernel thinks base is /apps/bizflow but we know slug → rewrite
        if ($slug !== '' && preg_match('#^/apps/bizflow(?:$|/)#', $moduleBase)) {
            $moduleBase = "/t/{$slug}/apps/bizflow";
        }
    }

    /* -------------------------------------------------------------
     * 5) Redirect plain /apps/bizflow[*] → /t/{slug}/apps/bizflow[*]
     * ----------------------------------------------------------- */
    if ($slug !== '' && preg_match('#^/apps/bizflow(?:/|$)#', $reqPath)) {
        $tail = ltrim(substr($reqPath, strlen('/apps/bizflow')), '/');
        $to   = $moduleBase . ($tail !== '' ? '/' . $tail : '');

        if (!headers_sent()) {
            header('Location: ' . $to, true, 302);
        }
        exit;
    }

    /* -------------------------------------------------------------
     * 6) Tail inside module (/quotes, /customers, etc.)
     * ----------------------------------------------------------- */
    $tail = (string) ($__KF_MODULE__['tail'] ?? '');
    if ($tail === '' && $reqPath !== '') {
        $base = rtrim($moduleBase, '/');
        if ($base !== '' && str_starts_with($reqPath, $base)) {
            $tail = ltrim(substr($reqPath, strlen($base)), '/');
        } elseif (str_starts_with($reqPath, '/bizflow')) {
            // Optional alias: /bizflow/*
            $tail = ltrim(substr($reqPath, strlen('/bizflow')), '/');
        }
    }
    $tail = rawurldecode(trim(preg_replace('#//+#', '/', $tail), "/ \t\r\n\0\x0B"));

    /* -------------------------------------------------------------
     * 7) Resolve shell layout
     * ----------------------------------------------------------- */
    $layoutFile = null;
    if (isset($manifest['layout']) && is_string($manifest['layout']) && is_file($manifest['layout'])) {
        $layoutFile = $manifest['layout'];
    } else {
        $try = $modDir . '/Views/shared/layouts/shell.php';
        $layoutFile = is_file($try) ? $try : null;
    }

    /* -------------------------------------------------------------
     * 8) Canonical context passed into routes + controllers
     * ----------------------------------------------------------- */
    $ctx = [
        'slug'        => $slug,
        'org'         => $org,
        'org_id'      => $orgId,
        'module_base' => $moduleBase,
        'module_dir'  => $modDir,
        'layout'      => $layoutFile,
        'scope'       => 'tenant',
        'manifest'    => $manifest,
    ];

    if ($KF_BIZ_DEBUG) {
        @file_put_contents(
            '/tmp/klinflow-bizflow.log',
            '[' . date('Y-m-d H:i:s') . '] ctx=' . json_encode($ctx) . "\n",
            FILE_APPEND
        );
    }

    /* -------------------------------------------------------------
     * 9) Render helper (view key → /Views/{key}.php)
     * ----------------------------------------------------------- */
    $render = function (string $relView, array $data = [], ?string $layout = null) use ($ctx, $viewsDir): void {
        $file = rtrim($viewsDir, '/') . '/' . ltrim($relView, '/');
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }

        if (!is_file($file)) {
            http_response_code(404);
            echo 'BizFlow view not found: '
               . htmlspecialchars($relView, ENT_QUOTES, 'UTF-8');
            return;
        }

        $vars = array_merge($ctx, $data);
        extract($vars, EXTR_SKIP);

        $shell = $layout ?? ($ctx['layout'] ?? null);
        if ($shell && is_file($shell)) {
            $_content = $file;    // shell will require this
            require $shell;
        } else {
            require $file;        // bare include
        }
    };

    /* -------------------------------------------------------------
     * 10) Hand off to routes.php with readable failure
     * ----------------------------------------------------------- */
    $__BIZ__ = [
        'ctx'    => $ctx,
        'render' => $render,
        'method' => $method,
        'tail'   => $tail,
    ];

    try {
        if (!is_file($routesFile)) {
            throw new \RuntimeException("BizFlow routes.php missing at {$routesFile}");
        }
        require $routesFile;
    } catch (\Throwable $e) {
        http_response_code(500);
        $id = bin2hex(random_bytes(6));
        @file_put_contents(
            '/tmp/klinflow-bizflow.log',
            '[' . date('Y-m-d H:i:s') . "] {$id} Routes failure: "
            . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
            . "\n" . $e->getTraceAsString() . "\n",
            FILE_APPEND
        );

        if ($KF_BIZ_DEBUG) {
            echo "<pre style='white-space:pre-wrap;font:14px/1.45 monospace;padding:16px;'>"
               . "BizFlow Error ({$id}) — Routes failure\n\n"
               . $e->getMessage() . "\n"
               . $e->getFile() . ':' . $e->getLine() . "\n\n"
               . $e->getTraceAsString()
               . "</pre>";
        } else {
            echo "Something went wrong in BizFlow. Error ID: {$id}";
        }
    }
}