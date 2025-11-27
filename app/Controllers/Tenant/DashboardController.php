<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Services\ModuleAccess;

final class DashboardController extends BaseController
{
    public function index(array $params = []): void
    {
        // Ensure tenant context; require auth
        [$org, $user, $slug] = $this->tenantGuard($params['slug'] ?? null, true);

        // By default, send users to their default module dashboard
        // Add ?keep=1 to view the legacy app dashboard instead.
        if (($_GET['keep'] ?? '') !== '1') {
            $def = ModuleAccess::defaultModuleFor($slug);
            if ($def) {
                $this->redirect("/t/{$slug}/{$def}");
                return;
            }
        }

        // Fallback: render tenant dashboard view (uses shell from UiFrame)
        $this->render('tenant/dashboard/index', [
            'title'      => 'Tenant Dashboard',
            'org'        => $org,
            'slug'       => $slug,
            'brand'      => 'KlinFlow',
            'brandColor' => '#228B22',
        ]);
    }
}