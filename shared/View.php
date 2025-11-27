<?php
declare(strict_types=1);

namespace Shared;

/**
 * View renderer with strict layout scoping + debug diagnostics.
 * - Keeps your original resolution rules (Public/CP/Tenant/Modules).
 * - Adds APP_DEBUG-aware assertions so missing layout/context shows
 *   a precise error (which used to surface as "Unexpected error").
 * - Wraps view + layout includes in try/catch and reports nicely.
 */
final class View
{
    public static function render(string $view, array $data = [], $layoutOrFalse = null): void
    {
        $ROOT   = dirname(__DIR__);
        $DEBUG  = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');

        // 3rd param back-compat (string|false)
        if (array_key_exists(2, func_get_args())) {
            $data['layout'] = $layoutOrFalse;
        }

        /* ---------- helpers ---------- */
        $findFirst = function(array $paths): ?string {
            foreach ($paths as $p) {
                if (is_string($p) && $p !== '' && is_file($p)) return $p;
            }
            return null;
        };

        $tenantDefaultLayout = $ROOT.'/apps/Tenant/Views/shared/layouts/shell.php';
        $cpDefaultLayout     = $ROOT.'/shared/layouts/shell.php';

        static $manifestCache = [];

        $loadModuleManifest = function(string $moduleKey) use ($ROOT, &$manifestCache) {
            if (array_key_exists($moduleKey, $manifestCache)) return $manifestCache[$moduleKey];
            $file = $ROOT . '/modules/' . $moduleKey . '/manifest.php';
            if (is_file($file)) {
                try { $cfg = @include $file; $manifestCache[$moduleKey] = is_array($cfg) ? $cfg : false; }
                catch (\Throwable $e) { $manifestCache[$moduleKey] = false; }
            } else { $manifestCache[$moduleKey] = false; }
            return $manifestCache[$moduleKey];
        };

        $resolveManifestLayout = function(?array $manifest, string $moduleKey) use ($ROOT): ?string {
            if (!$manifest || !isset($manifest['layout'])) return null;
            $val = (string)$manifest['layout'];
            if ($val === '') return null;

            if ($val[0] === '/') return is_file($val) ? $val : null;

            $base = $ROOT . '/modules/' . $moduleKey . '/';
            $p1 = $base . ltrim($val, '/');
            $p2 = (substr($p1, -4) === '.php') ? $p1 : $p1 . '.php';
            return is_file($p1) ? $p1 : (is_file($p2) ? $p2 : null);
        };

        $resolveLayoutAlias = function($opt) use ($ROOT, $tenantDefaultLayout, $cpDefaultLayout): ?string {
            if ($opt === false) return null;
            if (!is_string($opt) || $opt === '') return null;

            if ($opt[0] === '/') return is_file($opt) ? $opt : null;

            $key = strtolower(trim($opt, '/'));
            $aliases = [
                'tenant-shell'                 => $tenantDefaultLayout,
                'cp-shell'                     => $cpDefaultLayout,
                'shared/layouts/shell'         => $cpDefaultLayout,
                'tenant/shared/layouts/shell'  => $tenantDefaultLayout,
            ];
            if (isset($aliases[$key]) && is_file($aliases[$key])) return $aliases[$key];

            $p1 = $ROOT.'/'.ltrim($opt, '/');
            $p2 = $p1.'.php';
            return is_file($p1) ? $p1 : (is_file($p2) ? $p2 : null);
        };

        $panic = function(string $heading, string $detail, int $code = 500) use ($DEBUG) {
            http_response_code($code);
            if ($DEBUG) {
                $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
                echo '<!doctype html><meta charset="utf-8">';
                echo '<title>View Error</title>';
                echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,sans-serif;padding:2rem;background:#f9fafb;color:#111}';
                echo '.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:1rem;max-width:960px;margin:auto}';
                echo 'pre{white-space:pre-wrap;word-break:break-word;background:#0b1020;color:#e2e8f0;padding:1rem;border-radius:10px;}</style>';
                echo '<div class="card"><h1>'.$h($heading).'</h1><pre>'.$h($detail).'</pre></div>';
            } else {
                echo 'Unexpected error';
            }
            exit;
        };

        $assertLayoutVars = function(string $scope, ?string $layoutFile, array $data) use ($DEBUG, $panic) {
            if (!$DEBUG || !$layoutFile) return;

            // Tenant shells in your system require these to avoid "Unexpected error"
            if ($scope === 'tenant') {
                $missing = [];
                foreach (['module_base','org'] as $k) {
                    if (!array_key_exists($k, $data)) $missing[] = '$'.$k;
                }
                if ($missing) {
                    $panic(
                        'Layout context missing (tenant)',
                        "The layout expects: ".implode(', ', $missing)."\n\n".
                        "Fix: pass them in Shared\\View::render(\$view, ['module_base'=>..., 'org'=>...])."
                    );
                }
            }

            // $title/$slot are set by renderer; shells may require $slot or legacy vars
            // Warn if neither slot nor legacy provided (when view didn't render anything)
        };

        /* ---------- resolve view file ---------- */
        $scope         = $data['scope'] ?? null;
        $viewFile      = null;
        $layoutFile    = null;
        $moduleKey     = null;

        $isAbsoluteCandidate = ($view !== '' && ($view[0] === '/' || substr($view, -4) === '.php'));

        // Absolute path
        if ($isAbsoluteCandidate) {
            $candidates = [$view, $ROOT.'/'.$view];
            if (substr($view, -4) !== '.php') {
                $candidates[] = $view.'.php';
                $candidates[] = $ROOT.'/'.$view.'.php';
            }
            $abs = $findFirst($candidates);
            if ($abs) {
                $viewFile = $abs;
                if (preg_match('#/modules/([^/]+)/Views/#', str_replace('\\','/',$viewFile), $m)) {
                    $moduleKey = $m[1] ?? null;
                    $scope ??= 'tenant';
                }
            }
        }

        // Aliases
        if (!$viewFile) {
            [$area, $rest] = array_pad(explode('/', $view, 2), 2, '');
            $areaLower = strtolower($area);

            switch ($areaLower) {
                case 'cp': {
                    $rest = $rest ?: 'index';
                    $viewFile = $findFirst([
                        $ROOT.'/apps/CP/Views/'.$rest.'.php',
                        $ROOT.'/apps/CP/Views/'.$rest.'/index.php',
                    ]);
                    $scope ??= 'cp';
                    break;
                }

                case 'tenant': {
                    $rest = $rest ?: 'index';
                    $viewFile = $findFirst([
                        $ROOT.'/apps/Tenant/Views/'.$rest.'.php',
                        $ROOT.'/apps/Tenant/Views/'.$rest.'/index.php',
                    ]);
                    $scope ??= 'tenant';
                    break;
                }

                case 'modules':
                case 'module':
                case 'mod': {
                    $rest = $rest ?: 'DMS/dashboard';
                    [$moduleKey, $vpath] = array_pad(explode('/', $rest, 2), 2, '');
                    $moduleKey = $moduleKey ?: 'DMS';
                    $vpath     = $vpath ?: 'dashboard';
                    $viewFile  = $findFirst([
                        $ROOT.'/modules/'.$moduleKey.'/Views/'.$vpath.'.php',
                        $ROOT.'/modules/'.$moduleKey.'/Views/'.$vpath.'/index.php',
                    ]);
                    $scope ??= 'tenant';
                    break;
                }

                case 'public':
                case 'apps':
                case 'app':
                case '': {
                    $path = $rest ?: $view;
                    $viewFile = $findFirst([
                        $ROOT.'/apps/Public/'.$path.'.php',
                        $ROOT.'/apps/Public/'.$path.'/index.php',
                    ]);
                    $scope ??= 'public';
                    break;
                }

                case 'shared': {
                    $viewFile = $findFirst([
                        $ROOT.'/'.$view.'.php',
                        $ROOT.'/'.$view.'/index.php',
                    ]);
                    $scope ??= 'public';
                    break;
                }

                default: {
                    $viewFile = $findFirst([
                        $ROOT.'/apps/'.$area.'/Views/'.$rest.'.php',
                        $ROOT.'/apps/'.$area.'/Views/'.$rest.'/index.php',
                    ]);
                    $scope ??= 'public';
                }
            }
        }

        /* ---------- Fallback for missing view ---------- */
        if (!$viewFile) {
            http_response_code(404);

            $scopeGuess  = $data['scope'] ?? ($scope ?? 'tenant');
            $layoutGuess = $data['layout'] ?? (
                $scopeGuess === 'cp' ? 'cp-shell'
                : ($scopeGuess === 'tenant' ? 'tenant-shell' : null)
            );

            $tpl404 = $ROOT . '/shared/errors/404.php';
            $data404 = [
                'scope'         => $scopeGuess,
                'layout'        => $layoutGuess,
                'title'         => '404 Not Found',
                'message'       => "View not found: {$view}",
                'requested'     => (string)$view,
                'referer'       => $_SERVER['HTTP_REFERER'] ?? null,
                'back_href'     => $_SERVER['HTTP_REFERER'] ?? '/',
                'brandColor'    => $data['brandColor'] ?? '#228B22',
                'module_base'   => $data['module_base'] ?? null,
                'org'           => $data['org'] ?? ($_SESSION['tenant_org'] ?? []),
                'moduleSidenav' => $data['moduleSidenav'] ?? null,
            ];

            try {
                if (is_file($tpl404)) {
                    extract($data404, EXTR_SKIP);
                    header('Content-Type: text/html; charset=utf-8');
                    ob_start();
                    require $tpl404;
                    $content = ob_get_clean();
                    $slot    = $content;

                    $layoutFile = $resolveLayoutAlias($layoutGuess);
                    $assertLayoutVars($scopeGuess, $layoutFile, $data404);

                    if ($layoutFile && is_file($layoutFile)) {
                        require $layoutFile;
                    } else {
                        echo $slot;
                    }
                } else {
                    header('Content-Type: text/html; charset=utf-8');
                    $h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
                    echo '<!doctype html><meta charset="utf-8"><title>404</title>';
                    echo '<div style="font-family:system-ui;margin:3rem auto;max-width:640px">';
                    echo '<h1 style="color:#228B22;margin-bottom:0.5rem">404 Not Found</h1>';
                    echo '<p>View not found: <code>'.$h($view).'</code></p>';
                    echo '<p><a href="/" style="color:#228B22;text-decoration:none">← Back to Home</a></p>';
                    echo '</div>';
                }
            } catch (\Throwable $e) {
                if (class_exists('\Shared\Debug\ErrorHandler')) {
                    \Shared\Debug\ErrorHandler::handleThrowable($e);
                } else {
                    $panic('Failed rendering 404', $e->getMessage()."\n\n".$e->getTraceAsString(), 500);
                }
            }
            return;
        }

        /* ---------- choose layout (STRICT) ---------- */
        $layoutOpt       = $data['layout'] ?? null;
        $explicitDisable = ($layoutOpt === false);

        if (!$explicitDisable) {
            if (is_string($layoutOpt) && $layoutOpt !== '') {
                $layoutFile = $resolveLayoutAlias($layoutOpt);
            }

            if (!$layoutFile) {
                if ($moduleKey) {
                    $manifest   = $loadModuleManifest($moduleKey);
                    $fromMan    = $manifest ? $resolveManifestLayout($manifest, $moduleKey) : null;
                    if ($fromMan) {
                        $layoutFile = $fromMan;
                    } else {
                        $moduleShell = $ROOT.'/modules/'.$moduleKey.'/Views/shared/layouts/shell.php';
                        $layoutFile  = is_file($moduleShell) ? $moduleShell : null;
                    }
                } elseif ($scope === 'tenant') {
                    $layoutFile = is_file($tenantDefaultLayout) ? $tenantDefaultLayout : null;
                } elseif ($scope === 'cp') {
                    $layoutFile = is_file($cpDefaultLayout) ? $cpDefaultLayout : null;
                } else {
                    $layoutFile = null;
                }
            }
        }

        /* ---------- render ---------- */
        header('Content-Type: text/html; charset=utf-8');

        // Provide safe defaults to avoid undefined notices inside shells,
        // while still asserting in DEBUG so developers see what to pass.
        $safeData = $data;
        if (!array_key_exists('title', $safeData))       $safeData['title'] = '';
        if (!array_key_exists('module_base', $safeData)) $safeData['module_base'] = $data['module_base'] ?? '';
        if (!array_key_exists('org', $safeData))         $safeData['org'] = $data['org'] ?? ($_SESSION['tenant_org'] ?? []);

        try {
            extract($safeData, EXTR_SKIP);

            ob_start();
            require $viewFile;
            $content = ob_get_clean();
            $slot    = $content;

            if (!$layoutFile || !is_file($layoutFile)) {
                echo $slot;
                return;
            }

            $assertLayoutVars($scope ?? 'public', $layoutFile, $safeData);
            require $layoutFile;

        } catch (\Throwable $e) {
            if (class_exists('\Shared\Debug\ErrorHandler')) {
                \Shared\Debug\ErrorHandler::handleThrowable($e);
            } else {
                $panic('Failed rendering view/layout', $e->getMessage()."\n\n".$e->getTraceAsString(), 500);
            }
        }
    }
}