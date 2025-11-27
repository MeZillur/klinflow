<?php
declare(strict_types=1);

namespace Shared\Modules;

use Shared\DB;

/**
 * Dispatches a tenant request into a module:
 *   /t/{slug}/apps/{moduleKey}/{tail}
 *
 * Contract per module:
 *   modules/Tenant/<Studly>/routes.php returns an array of route defs:
 *     [ [ 'GET',  '',                [Controller::class, 'index'] ],
 *       [ 'GET',  'notes',           [NotesController::class, 'index'] ],
 *       [ 'GET',  'notes/create',    [NotesController::class, 'create'] ],
 *       [ 'POST', 'notes',           [NotesController::class, 'store'] ],
 *       ... ]
 *
 *   modules/Tenant/<Studly>/migrations/*.sql are applied once per tenant DB.
 */
final class Dispatcher
{
    public static function tenant(string $moduleKey, string $slug, string $tail, string $method): void
    {
        $studly = self::studly($moduleKey);
        $base   = BASE_PATH . "/modules/Tenant/{$studly}";

        // If module folder missing, 404
        if (!is_dir($base)) {
            http_response_code(404); echo "Module not found: {$moduleKey}"; return;
        }

        // Run migrations once per tenant (row_guard => global DB, db_per_org => tenant DB)
        try { Migrator::run($moduleKey, $base . '/migrations'); } catch (\Throwable $e) {
            http_response_code(500); echo "Module migration failed."; return;
        }

        // Load route table
        $routesFile = $base . '/routes.php';
        if (!is_file($routesFile)) {
            http_response_code(404); echo "Module routes missing."; return;
        }
        $routes = require $routesFile;
        if (!is_array($routes)) {
            http_response_code(500); echo "Module routes invalid."; return;
        }

        $method = strtoupper($method);
        $tail   = trim($tail, '/'); // '' allowed

        // Simple matcher (exact or with {id} like placeholder for numbers)
        foreach ($routes as $def) {
            [$m, $pattern, $handler] = [$def[0] ?? 'GET', trim((string)($def[1] ?? ''), '/'), $def[2] ?? null];
            if (strtoupper($m) !== $method) continue;

            $re = self::toRegex($pattern);
            if (preg_match($re, $tail, $mm)) {
                array_shift($mm); // captured wildcards
                return self::invoke($handler, $moduleKey, $slug, $mm);
            }
        }

        http_response_code(404); echo "Module route not found.";
    }

    private static function invoke($handler, string $moduleKey, string $slug, array $params): void
    {
        if (is_callable($handler)) { call_user_func_array($handler, $params); return; }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $class = $handler[0];
            $meth  = $handler[1];
            if (!class_exists($class) || !method_exists($class, $meth)) {
                http_response_code(500); echo "Module controller missing."; return;
            }
            $ctrl = new $class();
            // Common param payload for controllers
            $payload = ['slug'=>$slug, 'module'=>$moduleKey];
            // If method expects array, pass $payload; else spread scalars
            $ref = new \ReflectionMethod($class, $meth);
            if ($ref->getNumberOfParameters() >= 1 && ($ref->getParameters()[0]->hasType() === false || (string)$ref->getParameters()[0]->getType() === 'array')) {
                $ctrl->$meth($payload, ...$params);
            } else {
                $ctrl->$meth(...$params);
            }
            return;
        }
        http_response_code(500); echo "Invalid route handler.";
    }

    private static function toRegex(string $pattern): string
    {
        // support: '', 'notes', 'notes/create', 'notes/{id}', 'notes/{id}/edit'
        $p = preg_quote(trim($pattern, '/'), '#');
        // replace \{id\} with ([0-9]+), generic \{([a-z0-9_]+)\} with ([^/]+)
        $p = preg_replace('#\\\\\{id\\\\\}#i', '([0-9]+)', $p);
        $p = preg_replace('#\\\\\{[a-z0-9_]+\\\\\}#i', '([^/]+)', $p);
        return "#^{$p}$#i";
    }

    private static function studly(string $key): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));
    }
}