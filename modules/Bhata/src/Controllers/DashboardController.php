<?php
declare(strict_types=1);

namespace Modules\Bhata\Controllers;

final class DashboardController
{
    /** @var callable */
    private $render;

    public function __construct(callable $render)
    {
        $this->render = $render;
    }

    public function index(array $ctx): void
    {
        ($this->render)('dashboard', [
            'title' => 'Bhata • Dashboard',
            'base'  => (string)($ctx['base'] ?? ''),
            'org'   => (array)  ($ctx['org']  ?? []),
        ]);
    }
}