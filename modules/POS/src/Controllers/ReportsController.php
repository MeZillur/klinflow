<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class ReportsController extends BaseController
{
    /**
     * Small helper: resolve ctx → [$c, $base, $orgId, $pdo]
     */
    private function env(array $ctx): array
    {
        $c     = $this->ctx($ctx);
        $base  = (string)($c['module_base'] ?? '/apps/pos');
        $orgId = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
        $pdo   = $this->pdo(); // for future reports

        return [$c, $base, $orgId, $pdo];
    }

    /* ============================================================
     * GET /reports  → POS Reports landing (Coming soon)
     * ============================================================ */
    public function index(array $ctx = []): void
    {
        try {
            [$c, $base] = $this->env($ctx);

            $this->view(
                'reports/index',
                [
                    'title' => 'POS Reports',
                    'base'  => $base,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Reports landing failed', $e);
        }
    }
}