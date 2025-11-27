<?php
declare(strict_types=1);

namespace Modules\medflow\Controllers;

use Shared\View;

final class DashboardController
{
    private array $ctx;
    public function __construct(array $ctx){ $this->ctx = $ctx; }

    public function index(): void
    {
        $org         = $this->ctx['org'] ?? ($_SESSION['tenant_org'] ?? []);
        $module_base = $this->ctx['module_base'] ?? '';

        // 👇 DO NOT include "Views/" here
        View::render('modules/medflow/dashboard/index', [
            'scope'         => 'tenant',
            'layout'        => null, // use manifest layout
            'title'         => 'MedFlow',
            'org'           => $org,
            'module_base'   => $module_base,
            'moduleSidenav' => __DIR__ . '/../../Views/shared/partials/sidenav.php',
        ]);
    }
}