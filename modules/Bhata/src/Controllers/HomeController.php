<?php
declare(strict_types=1);

namespace Modules\BhataFlow\Controllers;

final class HomeController extends \Modules\DMS\Controllers\BaseController
{
    public function index(array $ctx): void
    {
        $this->view('bhata/home', [
            'title'  => 'BhataFlow',
            'active' => 'bhata',
        ], $ctx);
    }
}