<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

final class LandingController extends BaseController
{
    /** GET /apps/pos — render landing directly (NO shell) */
    public function home(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $dir   = rtrim((string)($c['module_dir'] ?? __DIR__.'/../../'), '/');
        $base  = (string)($c['module_base'] ?? '/apps/pos');
        $org   = (array)($c['org'] ?? []);
        $title = 'POS — Apps';

        $view = $dir . '/Views/landing/index.php'; // <-- only index.php

        try {
            if (!is_file($view)) {
                throw new \RuntimeException("Landing view missing: {$view}");
            }

            // Isolated scope include
            (static function(array $__vars, string $__view) {
                extract($__vars, EXTR_SKIP);
                require $__view;
            })([
                'title'      => $title,
                'brandColor' => '#228B22',
                'base'       => $base,
                'org'        => $org,
                // keep these available because your landing uses them
                'ctx'        => $c,
            ], $view);

        } catch (\Throwable $e) {
            http_response_code(500);
            $safe = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            echo "<pre>POS Landing — fallback\n\n{$safe}</pre>";
        }
    }
}