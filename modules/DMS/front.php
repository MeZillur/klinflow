<?php
declare(strict_types=1);

/**
 * modules/DMS/front.php
 * Front controller for the DMS module (uses manifest-defined shell).
 * - Boots Shared\Debug\ErrorHandler (HTML/JSON friendly errors + logging)
 * - Accepts $__KF_MODULE__ from the app kernel
 * - Resolves module base + manifest
 * - Hands off to routes.php (single source of truth for paths)
 */

/* ─────────────────────────── Debug bootstrap (must be first) ─────────────────────────── */
namespace {
    // We keep this block in the global namespace to avoid import issues.
    // Try to load the handler class if the autoloader hasn't yet.
    $ehClass = '\\Shared\\Debug\\ErrorHandler';
    if (!class_exists($ehClass, false)) {
        // Common relative locations (CloudPanel layout, repo layout, etc.)
        $modDir = __DIR__; // /modules/DMS
        $candidates = [
            dirname($modDir, 1) . '/shared/Debug/ErrorHandler.php',   // /modules/shared/Debug/ErrorHandler.php
            dirname($modDir, 2) . '/shared/Debug/ErrorHandler.php',   // /shared/Debug/ErrorHandler.php (repo root)
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/shared/Debug/ErrorHandler.php',
        ];
        foreach ($candidates as $cand) {
            if ($cand && is_file($cand)) { require_once $cand; break; }
        }
    }

    if (class_exists($ehClass)) {
        // Decide debug mode from env or ?_debug=1
        $debug = false;
        $env = getenv('APP_DEBUG');
        if ($env === '1' || strcasecmp((string)$env, 'true') === 0) $debug = true;
        if (isset($_GET['_debug']) && $_GET['_debug'] === '1')      $debug = true;

        // Writable log path (CloudPanel-safe)
        $logFile = '/tmp/klinflow-dms.log';

        /** @var \Shared\Debug\ErrorHandler $eh */
        $eh = $ehClass;
        $eh::boot([
            'debug'    => $debug,
            'log_file' => $logFile,
        ]);

        // Useful for correlating requests in logs
        if (!headers_sent()) {
            header('X-Request-ID: ' . bin2hex(random_bytes(6)));
        }
    }
}

/* ─────────────────────────────────────────────────────────────────────────────────────── */

namespace {
    // Back to global namespace for the rest of the file.
    // NOTE: Do not print/echo anything before this point; headers must be free.

    $modDir     = __DIR__;                 // /modules/DMS
    $viewsDir   = $modDir . '/Views';
    $routesFile = $modDir . '/routes.php';
    $manifest   = require $modDir . '/manifest.php';

    $__KF_MODULE__ = $__KF_MODULE__ ?? null;
    if (!$__KF_MODULE__ || !is_array($__KF_MODULE__)) {
        http_response_code(500);
        echo "DMS front: missing module context.";
        exit;
    }

    /* Normalize context */
    $slug       = (string) ($__KF_MODULE__['slug']   ?? '');
    $method     = strtoupper((string) ($__KF_MODULE__['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $tail       = trim((string) ($__KF_MODULE__['tail'] ?? ''), '/');
    $org        = (array)  ($__KF_MODULE__['org']    ?? []);
    $moduleBase = (string) ($__KF_MODULE__['module_base'] ?? ($slug ? "/t/{$slug}/apps/dms" : "/apps/dms"));

    /* Enrich context with module_dir for routes + controllers */
    $__KF_MODULE__ = array_merge($__KF_MODULE__, [
        'slug'        => $slug,
        'method'      => $method,
        'tail'        => $tail,
        'org'         => $org,
        'module_base' => $moduleBase,
        'module_dir'  => $modDir,
        'manifest'    => $manifest,
    ]);

    /* Hand off to the router (all paths resolved there) */
    require $routesFile;
}