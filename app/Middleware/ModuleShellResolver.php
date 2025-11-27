<?php
declare(strict_types=1);

namespace App\Middleware;

final class ModuleShellResolver
{
    /** @var array<string,array{prefix:string, layout:string}> */
    private array $map;

    public function __construct(array $map)
    {
        // Example item: ['bhata' => ['prefix' => '/t/{org}/apps/bhata', 'layout' => '/abs/path/.../Views/_shell/layout.php']]
        $this->map = $map;
    }

    public function handle($req, $next)
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        foreach ($this->map as $mod) {
            $prefix = $this->resolveOrgPrefix($mod['prefix']);
            if ($prefix && str_starts_with($uri, $prefix)) {
                $layout = $mod['layout'] ?? '';
                if ($layout && is_file($layout)) {
                    $GLOBALS['__KF_LAYOUT_OVERRIDE__'] = $layout;
                }
                break;
            }
        }
        return $next($req);
    }

    /** Replace {org} with actual slug from route/session if present */
    private function resolveOrgPrefix(string $prefix): string
    {
        $org = $_SESSION['tenant_org']['slug'] ?? null;
        return $org ? str_replace('{org}', $org, $prefix) : $prefix;
    }
}