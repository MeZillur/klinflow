<?php
declare(strict_types=1);

namespace Modules\Bhata\Controllers;

final class LandingController extends BaseController
{
    /**
     * GET /t/{slug}/apps/bhata (and /dashboard, /landing)
     * Renders a clean, shell-less landing (your existing landing.php).
     */
    public function index(array $ctx = []): void
    {
        $c   = $this->ctx($ctx);
        $vars = [
            'title' => 'BhataFlow — Landing',
            'org'   => $c['org'] ?? [],
            'ctx'   => $c,
            'base'  => $c['module_base'],
        ];

        // Use EXACTLY your plain landing view (no shell/sidenav)
        $this->renderStandaloneFromModuleDir((string)$c['module_dir'], 'landing.php', $vars);
    }
}