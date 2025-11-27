<?php
declare(strict_types=1);

/**
 * BhataFlow — front controller (minimal debug version)
 */

try {
    $modDir = __DIR__;
    $routesFile = $modDir . '/routes.php';

    if (!isset($__KF_MODULE__) || !is_array($__KF_MODULE__)) {
        throw new Exception("Missing or invalid module context (\$__KF_MODULE__).");
    }

    $slug   = (string)($__KF_MODULE__['slug'] ?? '');
    $method = strtoupper((string)($__KF_MODULE__['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $tail   = trim((string)($__KF_MODULE__['tail'] ?? ''), '/');
    $org    = (array)($__KF_MODULE__['org'] ?? []);

    $moduleBase = (string)($__KF_MODULE__['module_base'] ?? '');
    if ($moduleBase === '') {
        $moduleBase = ($slug !== '' && $slug !== '_')
            ? '/t/' . rawurlencode($slug) . '/apps/bhata'
            : '/apps/bhata';
    }

    $__KF_MODULE__ = array_merge($__KF_MODULE__, [
        'slug'        => $slug,
        'method'      => $method,
        'tail'        => $tail,
        'org'         => $org,
        'module_base' => rtrim($moduleBase, '/'),
        'module_dir'  => $modDir,
    ]);

    if (!is_file($routesFile)) {
        throw new Exception("Missing routes.php at: {$routesFile}");
    }

    require $routesFile;

} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "[BhataFlow/front.php] " . $e->getMessage() . "\n\n" . $e->getTraceAsString();
    exit;
}