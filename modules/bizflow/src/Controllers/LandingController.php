<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use Throwable;
use PDO;
use Shared\DB;

final class LandingController extends BaseController
{
    /* -------------------------------------------------------------
     * Shared helpers (same pattern as QuotesController)
     * ----------------------------------------------------------- */

    /** Base dir where all logos live: modules/bizflow/Assets/brand/logo */
    private function logoBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/brand/logo';
    }

    /** Base dir where per-tenant identity JSON lives */
    private function identityBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/settings';
    }

    /** Ensure a directory exists (used by logo + identity) */
    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
    }

    /**
     * Find current logo for this org.
     *
     * Returns:
     * [
     *   'dir'      => filesystem dir,
     *   'path'     => full filesystem path to logo.* or null,
     *   'url'      => web URL (/modules/bizflow/…/logo.ext) or null,
     *   'data_url' => base64 data: URL (for inline use), or null,
     *   'exists'   => bool
     * ]
     */
    private function currentLogoInfoForOrg(int $orgId): array
    {
        $baseDir = $this->logoBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $candidates = ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.svg'];

        $filePath = null;
        $fileUrl  = null;
        $dataUrl  = null;

        foreach ($candidates as $file) {
            $p = $dir . '/' . $file;
            if (is_file($p)) {
                $filePath = $p;

                // HTTP path (if /modules is web-reachable)
                $fileUrl  = '/modules/bizflow/Assets/brand/logo/' . $orgKey . '/' . $file;

                // Also build a data: URL (useful if you later need inline)
                $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $mime = 'image/png';
                if ($ext === 'jpg' || $ext === 'jpeg') {
                    $mime = 'image/jpeg';
                } elseif ($ext === 'webp') {
                    $mime = 'image/webp';
                } elseif ($ext === 'svg') {
                    $mime = 'image/svg+xml';
                }

                $raw = @file_get_contents($p);
                if ($raw !== false) {
                    $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
                }

                break;
            }
        }

        return [
            'dir'      => $dir,
            'path'     => $filePath,
            'url'      => $fileUrl,
            'data_url' => $dataUrl,
            'exists'   => $filePath !== null,
        ];
    }

    /**
     * Load identity (name, address, phone, email) for this org from JSON,
     * falling back to cp_organizations values if JSON is missing/partial.
     *
     * Same logic as QuotesController::currentIdentityValuesForOrg()
     */
    private function currentIdentityValuesForOrg(int $orgId, array $org): array
    {
        $baseDir = $this->identityBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $file   = $dir . '/identity.json';
        $values = [
            'name'    => trim((string)($org['name'] ?? '')),
            'address' => trim((string)($org['address'] ?? '')),
            'phone'   => trim((string)($org['phone'] ?? '')),
            'email'   => trim((string)($org['email'] ?? '')),
        ];

        if (is_file($file)) {
            $raw  = @file_get_contents($file);
            $data = json_decode((string)$raw, true);
            if (is_array($data)) {
                foreach (['name', 'address', 'phone', 'email'] as $k) {
                    if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') {
                        $values[$k] = $data[$k];
                    }
                }
            }
        }

        return $values;
    }

    /* -------------------------------------------------------------
     * GET /apps/bizflow — render landing directly (NO shell)
     * ----------------------------------------------------------- */
    public function home(?array $ctx = null): void
    {
        $c = $this->ctx($ctx ?? []);

        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        // Org from ctx / session (same as other controllers)
        $org = (array)($c['org'] ?? ($_SESSION['tenant_org'] ?? []));
        if (empty($org['id']) && isset($c['org_id'])) {
            $org['id'] = (int)$c['org_id'];
        }

        // Make sure org_id is valid
        $orgId = (int)($org['id'] ?? 0);
        if ($orgId <= 0) {
            // Rely on BaseController helper to enforce tenant
            $orgId = $this->requireOrg();
            if (!isset($org['id'])) {
                $org['id'] = $orgId;
            }
        }

        // 1) Identity from JSON (same as Quotes print/PDF)
        $identityValues = $this->currentIdentityValuesForOrg($orgId, $org);

        foreach (['name', 'address', 'phone', 'email'] as $k) {
            if (array_key_exists($k, $identityValues)) {
                $org[$k] = trim((string)$identityValues[$k]);
            }
        }

        // 2) Logo from Assets/brand/logo/org_{id}/logo.* (same as Quotes)
        $logoInfo = $this->currentLogoInfoForOrg($orgId);
        $logoUrl  = '/assets/brand/logo.png'; // safe global fallback

        if (!empty($logoInfo['url'])) {
            // For browser pages we can safely use the HTTP URL
            $logoUrl = (string)$logoInfo['url'];
        }

        $dir   = rtrim((string)($c['module_dir']  ?? __DIR__ . '/../../'), '/');
        $base  = (string)($c['module_base'] ?? '/apps/bizflow');
        $title = 'BizFlow — Apps';

        $view = $dir . '/Views/landing/index.php';

        try {
            if (!is_file($view)) {
                throw new \RuntimeException("Landing view missing: {$view}");
            }

            // Isolated scope include
            (static function (array $__vars, string $__view) {
                extract($__vars, EXTR_SKIP);
                require $__view;
            })([
                'title'      => $title,
                'brandColor' => '#228B22',
                'base'       => $base,
                'org'        => $org,               // enriched with identity
                'ctx'        => $c,
                'logo'       => ['url' => $logoUrl] // passes URL to view
            ], $view);

        } catch (Throwable $e) {
            http_response_code(500);
            $safe = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            echo "<pre>BizFlow Landing — fallback\n\n{$safe}</pre>";
        }
    }
}