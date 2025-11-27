<?php
declare(strict_types=1);

namespace Apps\Tenant\Controllers;

use Shared\Tenant;
use Shared\TenantDB;
use Shared\View;

final class DocumentsController
{
    public function index(): void
    {
        Tenant::requireTenantAuth(); // any tenant role
        $orgId = (int)$_SESSION['current_org_id'];

        // row_guard → scoped; db_per_org → normal
        $st = TenantDB::scopedQuery($orgId, "SELECT id, title, created_at FROM tenant_documents ORDER BY id DESC");
        $docs = $st->fetchAll();

        View::render('tenant/docs/index', [
            'title' => 'Documents',
            'docs'  => $docs,
        ]);
    }
}