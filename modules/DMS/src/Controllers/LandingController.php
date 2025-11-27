<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

final class LandingController extends BaseController
{
    /**
     * GET /apps/dms (home) — full-page landing with its own shell (no Shared\View)
     */
    public function home(?array $ctx = null): void
    {
        try {
            // Resolve base context (reuses BaseController::ctx)
            $c = $this->ctx($ctx ?? []);

            // Module dir: e.g. /.../modules/DMS
            $moduleDir = rtrim(
                (string)($c['module_dir'] ?? \dirname(__DIR__, 2)),
                '/'
            );

            // Organisation + slug (fallback to session like other DMS controllers)
            $org  = (array)($c['org'] ?? ($_SESSION['tenant_org'] ?? []));
            $slug = (string)($org['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));

            // Normalise module base to /t/{slug}/apps/dms when slug is known
            $base = (string)($c['module_base'] ?? '/apps/dms');
            $base = '/' . \ltrim($base, '/');
            $base = \rtrim($base, '/');

            if ($slug !== '' && \preg_match('#^/apps/dms(?:$|/)#', $base)) {
                $base = "/t/{$slug}/apps/dms";
            }

            $title = 'DMS — Apps';

            // Dedicated landing shell + body (like BizFlow)
            $shell = $moduleDir . '/Views/landing/shell.php';
            $body  = $moduleDir . '/Views/landing/index.php';

            if (!\is_file($shell) || !\is_file($body)) {
                throw new \RuntimeException("Landing views missing.\nshell={$shell}\nbody={$body}");
            }

            // -----------------------------------------------------------------
            // ORG LOGO RESOLUTION
            // Canonical path for DMS logo (per org):
            //   modules/DMS/storage/uploads/logo/org_<id>/logo.(png|jpg|jpeg|webp|svg)
            // -----------------------------------------------------------------
            $orgId    = (int)($org['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0));
            $orgLogo  = null;

            if ($orgId > 0) {
                $orgKey  = 'org_' . $orgId;
                $logoDir = $moduleDir . '/storage/uploads/logo/' . $orgKey;

                $candidates = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

                foreach ($candidates as $ext) {
                    $fsPath = $logoDir . '/logo.' . $ext;
                    if (!\is_file($fsPath)) {
                        continue;
                    }

                    // MIME type
                    $mime = 'image/png';
                    if ($ext === 'jpg' || $ext === 'jpeg') {
                        $mime = 'image/jpeg';
                    } elseif ($ext === 'webp') {
                        $mime = 'image/webp';
                    } elseif ($ext === 'svg') {
                        $mime = 'image/svg+xml';
                    }

                    $raw = @\file_get_contents($fsPath);
                    if ($raw !== false) {
                        // Inline data URL (works everywhere: browser + dompdf)
                        $orgLogo = 'data:' . $mime . ';base64,' . \base64_encode($raw);
                    } else {
                        // Fallback to HTTP path if direct read fails
                        // NOTE: /modules is web-visible on your host.
                        $orgLogo = "/modules/DMS/storage/uploads/logo/{$orgKey}/logo.{$ext}";
                    }
                    break;
                }
            }

            // Vars for both shell and body
            $vars = [
                'title'       => $title,
                'brandColor'  => '#228B22',
                'base'        => $base,        // legacy
                'module_base' => $base,        // preferred
                'org'         => $org,
                'slug'        => $slug,
                'logoutUrl'   => '/tenant/logout',
                'orgLogo'     => $orgLogo,     // <- NEW
            ];

            // Render body first into $slot (content-only view)
            $slot = (static function (array $__vars, string $__view) {
                \extract($__vars, EXTR_SKIP);
                \ob_start();
                require $__view;
                return \ob_get_clean();
            })($vars, $body);

            // Now render shell with $slot injected
            (static function (array $__vars, string $__view, string $__slot) {
                \extract($__vars, EXTR_SKIP);
                $slot = $__slot;
                require $__view;
            })($vars, $shell, $slot);

        } catch (\Throwable $e) {
            http_response_code(500);
            $safe = \htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            echo "<pre>DMS Landing — fallback\n\n{$safe}</pre>";
        }
    }
}