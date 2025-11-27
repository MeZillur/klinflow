<?php
declare(strict_types=1);

namespace Modules\school\Controllers;

use Shared\View;

final class DashboardController
{
    public function index(array $moduleCtx): void
    {
        $org         = $moduleCtx['org'] ?? [];
        $module_base = $moduleCtx['module_base'] ?? '';
        $sidenav     = __DIR__ . '/../../Views/shared/partials/sidenav.php';

        // ✅ No "Views" here — the renderer adds it
        View::render('modules/school/dashboard/index', [
            'scope'         => 'tenant',
            'layout'        => null,                // manifest supplies shell
            'title'         => 'School',
            'org'           => $org,
            'module_base'   => $module_base,
            'moduleSidenav' => $sidenav,
        ]);
    }
}