<?php
namespace Modules\BhataFlow\src\Middleware;

/**
 * Flags the request to use BhataFlow's local shell instead of the global one.
 * The kernel / View layer will read these globals.
 */
final class UseModuleShell
{
    public function handle($req, $next)
    {
        // flag the kernel / View to skip global shell
        $GLOBALS['__KF_USE_MODULE_SHELL__'] = true;
        // tell the renderer which layout to use
        $GLOBALS['__KF_MODULE_LAYOUT__'] = __DIR__ . '/../../Views/_shell/layout.php';
        return $next($req);
    }
}