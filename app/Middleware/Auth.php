<?php
declare(strict_types=1);

namespace App\Middleware;

final class Auth
{
    /**
     * Control Panel auth gate.
     * Usage from a controller action: Auth::ensureCp();
     * (Note: Shared\Router already supports the ['auth'] tag. This is a helper.)
     */
    public static function ensureCp(): void
    {
        if (empty($_SESSION['cp_user'])) {
            header('Location: /cp/login', true, 302);
            exit;
        }
    }

    /**
     * Convenience to get the current CP user array or null.
     */
    public static function user(): ?array
    {
        return $_SESSION['cp_user'] ?? null;
    }

    /**
     * Optional role check. Example: ensureRole(['superadmin','admin']);
     */
    public static function ensureRole(array $roles): void
    {
        $u = self::user();
        if (!$u || !in_array(($u['role'] ?? ''), $roles, true)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}