<?php
declare(strict_types=1);

namespace Modules\medflow\Controllers;

use Shared\View;

final class SalesReturnsController
{
    public function __construct(private array $ctx) {}

    public function index(): void
    {
        $base = $this->ctx['module_base'] ?? '/apps/medflow';

        // Render: modules/medflow/Views/sales/returns/index.php
        View::render('modules/medflow/sales/returns/index', [
            'scope'       => 'tenant',
            'layout'      => null,          // module shell comes from manifest
            'title'       => 'Sales Returns',
            'module_base' => $base,
            'org'         => $this->ctx['org'] ?? [],
            'returns'     => [],            // fill later with DB results
        ]);
    }

    public function create(): void
    {
        $base = $this->ctx['module_base'] ?? '/apps/medflow';

        // Render: modules/medflow/Views/sales/returns/create.php
        View::render('modules/medflow/sales/returns/create', [
            'scope'       => 'tenant',
            'layout'      => null,
            'title'       => 'New Sales Return',
            'module_base' => $base,
            'org'         => $this->ctx['org'] ?? [],
        ]);
    }

    public function show(int $id): void
    {
        $base = $this->ctx['module_base'] ?? '/apps/medflow';

        View::render('modules/medflow/Views/_stubs/coming', [
            'scope'       => 'tenant',
            'layout'      => null,
            'title'       => "Return #{$id}",
            'message'     => 'Return viewer will be wired next.',
            'actions'     => [
                ['href' => $base.'/sales/returns', 'icon' => 'fa-rotate-left', 'label' => 'Back to Returns'],
                ['href' => $base,                  'icon' => 'fa-house',       'label' => 'Dashboard'],
            ],
            'module_base' => $base,
            'org'         => $this->ctx['org'] ?? [],
        ]);
    }
}