<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use Throwable;

final class PurchasesController extends BaseController
{
    /**
     * GET /purchases  → Coming soon dashboard
     */
    public function index(array $ctx = []): void
    {
        try {
            $c    = $this->ctx($ctx);
            $dir  = rtrim((string)($c['module_dir'] ?? __DIR__ . '/../../'), '/');
            $base = (string)($c['module_base'] ?? '/apps/pos');

            $this->view($dir . '/Views/purchases/index.php', [
                'title' => 'Purchases — Coming Soon',
                'base'  => $base,
                'ctx'   => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Purchases placeholder failed', $e);
        }
    }

    /**
     * GET /purchases/create → for now same coming-soon page
     */
    public function create(array $ctx = []): void
    {
        $this->index($ctx);
    }
}