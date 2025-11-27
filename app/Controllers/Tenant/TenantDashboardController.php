<?php
declare(strict_types=1);

namespace Apps\Tenant\Controllers;

use Shared\View;
use Shared\DB;

final class TenantDashboardController
{
    public function index(array $args): void
    {
        $slug = $args['slug'] ?? null;
        $pdo  = DB::pdo();

        // Validate tenant org exists
        $q = $pdo->prepare("SELECT id, name, plan, status FROM cp_organizations WHERE slug=? LIMIT 1");
        $q->execute([$slug]);
        $org = $q->fetch();

        if (!$org) {
            http_response_code(404);
            View::render('tenant/errors/404', ['slug'=>$slug]);
            return;
        }

        // You can check session tenant_user if needed for access control
        $user = $_SESSION['tenant_user'] ?? null;

        View::render('tenant/dashboard', [
            'title' => 'Dashboard — '.$org['name'],
            'org'   => $org,
            'user'  => $user,
            'scope' => 'tenant',
        ]);
    }
}