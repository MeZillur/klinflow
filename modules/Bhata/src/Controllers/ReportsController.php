<?php
namespace Modules\BhataFlow\src\Controllers;

final class ReportsController extends BaseController
{
    public function index(): void
    {
        $this->render('reports/index', [
            'title' => 'BhataFlow — Reports',
        ]);
    }

    }
