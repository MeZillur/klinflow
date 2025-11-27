<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use Throwable;
use PDO;
use Shared\DB;

final class LandingController extends BaseController
{
    /**
     * Read BizFlow org identity (name, address, phone, email) from the
     * settings table, if it exists. If anything goes wrong (table missing,
     * etc) we just return an empty array so landing still loads.
     */
    private function loadOrgIdentity(int $orgId): array
    {
        if ($orgId <= 0) {
            return [];
        }

        try {
            $pdo = DB::pdo();

            // Table/columns that your settings form writes into
            $sql = "SELECT org_name, org_address, org_phone, org_email
                      FROM biz_settings
                     WHERE org_id = ?
                     LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$orgId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (!$row) {
                return [];
            }

            return [
                'name'    => trim((string)($row['org_name']    ?? '')),
                'address' => trim((string)($row['org_address'] ?? '')),
                'phone'   => trim((string)($row['org_phone']   ?? '')),
                'email'   => trim((string)($row['org_email']   ?? '')),
            ];
        } catch (Throwable $e) {
            // Do NOT break the landing page if the table is missing, etc.
            return [];
        }
    }

    /** GET /apps/bizflow — render landing directly (NO shell) */
    public function home(?array $ctx = null): void
    {
        $c = $this->ctx($ctx ?? []);

        // Base + view path from your existing code
        $dir   = rtrim((string)($c['module_dir']  ?? __DIR__ . '/../../'), '/');
        $base  = (string)($c['module_base'] ?? '/apps/bizflow');
        $title = 'BizFlow — Apps';

        // Org from context / session (fallbacks kept)
        $orgCtx = (array)($c['org'] ?? ($_SESSION['tenant_org'] ?? []));
        $orgId  = (int)($orgCtx['id'] ?? ($c['org_id'] ?? 0));

        // Pull identity from biz_settings and overlay on top of $orgCtx
        if ($orgId > 0) {
            $identity = $this->loadOrgIdentity($orgId);

            if (!empty($identity)) {
                // Only override fields that actually exist in settings
                if ($identity['name'] !== '') {
                    $orgCtx['name'] = $identity['name'];
                }
                if ($identity['address'] !== '') {
                    $orgCtx['address'] = $identity['address'];
                }
                if ($identity['phone'] !== '') {
                    $orgCtx['phone'] = $identity['phone'];
                }
                if ($identity['email'] !== '') {
                    $orgCtx['email'] = $identity['email'];
                }
            }
        }

        $view = $dir . '/Views/landing/index.php'; // same view as before

        try {
            if (!is_file($view)) {
                throw new \RuntimeException("Landing view missing: {$view}");
            }

            // Isolated scope include – unchanged
            (static function (array $__vars, string $__view) {
                extract($__vars, EXTR_SKIP);
                require $__view;
            })([
                'title'      => $title,
                'brandColor' => '#228B22',
                'base'       => $base,
                'org'        => $orgCtx,  // <— now includes identity from settings
                'ctx'        => $c,
            ], $view);

        } catch (Throwable $e) {
            http_response_code(500);
            $safe = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            echo "<pre>BizFlow Landing — fallback\n\n{$safe}</pre>";
        }
    }
}