<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use Throwable;

final class DashboardController extends BaseController
{
    /* -------------------------------------------------------------
     * GET /dashboard  (and /apps/bizflow root) — main BizFlow dashboard
     * ----------------------------------------------------------- */
    public function index(?array $ctx = null): void
    {
        try {
            $c   = $this->ctx($ctx ?? []);
            $org = $c['org'] ?? [];

            $this->view('dashboard/index', [
                'title'       => 'BizFlow dashboard',
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',

                // OPTIONAL: you can later pass real numbers from GL / modules
                // For now UI will use its own safe defaults.
                // 'kpis'      => [...],
                // 'pipelines' => [...],
                // 'cash'      => [...],
                // 'vat_tax'   => [...],
                // 'chart'     => [...],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Dashboard failed', $e);
        }
    }
}