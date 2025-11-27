<?php
declare(strict_types=1);

namespace App\Middleware;

use Shared\Csrf as SharedCsrf;

final class Csrf
{
    /**
     * Middleware-style check for POST/PUT/DELETE forms.
     * Use in a controller: Csrf::ensure();
     * Your Shared\Router also supports ['csrf'] in route definitions.
     */
    public static function ensure(): void
    {
        $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($m, ['POST','PUT','DELETE'], true)) {
            $ok = SharedCsrf::verify($_POST['_csrf'] ?? '');
            if (!$ok) {
                http_response_code(419);
                echo 'CSRF token mismatch.';
                exit;
            }
        }
    }

    /**
     * Helper to print a hidden input inside your forms.
     * Example: <form> <?= Csrf::field() ?> ... </form>
     */
    public static function field(): string
    {
        $t = SharedCsrf::token();
        return '<input type="hidden" name="_csrf" value="'.htmlspecialchars($t, ENT_QUOTES, 'UTF-8').'">';
    }
}